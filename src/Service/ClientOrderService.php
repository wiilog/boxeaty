<?php

namespace App\Service;

use App\Entity\Box;
use App\Entity\BoxType;
use App\Entity\Client;
use App\Entity\ClientOrder;
use App\Entity\ClientOrderLine;
use App\Entity\CratePatternLine;
use App\Entity\DeliveryMethod;
use App\Entity\DepositTicket;
use App\Entity\GlobalSetting;
use App\Entity\OrderStatusHistory;
use App\Entity\OrderType;
use App\Entity\PreparationLine;
use App\Entity\Status;
use App\Entity\User;
use App\Entity\WorkFreeDay;
use App\Helper\Form;
use App\Helper\FormatHelper;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;
use WiiCommon\Helper\Stream;

class ClientOrderService {

    /** @Required */
    public SessionInterface $session;

    /** @Required */
    public RequestStack $request;

    /** @Required */
    public RouterInterface $router;

    /** @Required */
    public Environment $twig;

    /** @Required */
    public EntityManagerInterface $entityManager;

    private ?string $token = null;

    public function updateClientOrderStatus(ClientOrder $clientOrder, Status $status, ?User $user): OrderStatusHistory {
        $now = new DateTime("now");
        $history = (new OrderStatusHistory())
            ->setOrder($clientOrder)
            ->setStatus($status)
            ->setUser($user)
            ->setChangedAt($now);

        $clientOrder->setStatus($status);

        return $history;
    }

    public function updateClientOrder(Request                $request,
                                      EntityManagerInterface $entityManager,
                                      Form                   $form,
                                      ClientOrder            $clientOrder): void {
        $content = (object)$request->request->all();
        $typeRepository = $entityManager->getRepository(OrderType::class);
        $clientRepository = $entityManager->getRepository(Client::class);
        $deliveryMethodRepository = $entityManager->getRepository(DeliveryMethod::class);
        $worFreeDayRepository = $entityManager->getRepository(WorkFreeDay::class);

        $deliveryMethodId = $content->deliveryMethod ?? null;
        $deliveryMethod = $deliveryMethodId
            ? $deliveryMethodRepository->find($deliveryMethodId)
            : null;
        if(!$deliveryMethod) {
            $form->addError('Vous devez sélectionner au moins un moyen de transport');
        }

        $typeId = $content->type ?? null;
        $type = $typeId
            ? $typeRepository->find($typeId)
            : null;
        if(!$type) {
            $form->addError('Vous devez sélectionner au moins un type de commande');
        }

        $clientId = $content->client ?? null;
        $client = $clientId
            ? $clientRepository->find($clientId)
            : null;
        if(!$client) {
            $form->addError('client', 'Ce champ est requis');
        }

        $information = $client->getClientOrderInformation();
        $expectedDelivery = DateTime::createFromFormat('Y-m-d', $content->date ?? null);
        if(isset($information)) {

            $worFreeDay = $worFreeDayRepository->findDayAndMonth($expectedDelivery->format('j'), $expectedDelivery->format('n'));
            // check if it's the weekend
            $deliveryRate = ((!in_array($expectedDelivery->format('N'), [6, 7])) && !count($worFreeDay) != 0)
                ? $information->getWorkingDayDeliveryRate()
                : $information->getNonWorkingDayDeliveryRate();
            $serviceCost = $information->getServiceCost();
        } else {
            $deliveryRate = null;
            $serviceCost = null;
        }

        if($type && $type->getCode() == OrderType::AUTONOMOUS_MANAGEMENT) {
            $collectRequired = (bool)($content->collectRequired ?? false);
            if($collectRequired) {
                if(!isset($content->cratesAmountToCollect)
                    || $content->cratesAmountToCollect < 1) {
                    $form->addError('cratesAmountToCollect', 'Le nombre de caisses à collecter est invalide');
                }
            }
        }

        $handledCartLines = $this->handleCartLines($entityManager, $form, $content);
        if($form->isValid()) {
            $clientOrderInformation = $client->getClientOrderInformation();
            $clientOrder = $clientOrder
                ->setExpectedDelivery($expectedDelivery)
                ->setClient($client)
                ->setDepository($clientOrderInformation ? $clientOrderInformation->getDepository() : null)
                ->setCratesAmountToCollect($content->cratesAmountToCollect ?? null)
                ->setCollectRequired($collectRequired ?? false)
                ->setDeliveryPrice($deliveryRate)
                ->setServicePrice($serviceCost)
                ->setComment($content->comment ?? null)
                ->setType($type)
                ->setDeliveryMethod($deliveryMethod)
                ->setTokensAmount(($clientOrderInformation ? $clientOrderInformation->getTokenAmount() : null) ?: 0);

            foreach($clientOrder->getLines()->toArray() as $line) {
                $clientOrder->removeLine($line);
                $entityManager->remove($line);
            }

            $cartVolume = 0;
            foreach($handledCartLines as $cartLine) {
                $boxType = $cartLine['boxType'];
                $quantity = $cartLine['quantity'];
                $clientOrderLine = new ClientOrderLine();
                $clientOrderLine
                    ->setBoxType($boxType)
                    ->setQuantity($cartLine['quantity'])
                    ->setUnitPrice($cartLine['unitPrice'])
                    ->setClientOrder($clientOrder);

                $entityManager->persist($clientOrderLine);

                if($boxType && $boxType->getVolume()) {
                    $cartVolume += $quantity * $boxType->getVolume();
                }
            }

            if(!$cartVolume) {
                $form->addError('Le volume total du panier doit être supérieur à 0. Vous devez définir un volume pour les types de box renseignés.');
            } else {
                $cratesAmount = $this->getCartSplitting($entityManager, $client, $handledCartLines);
                $clientOrder->setCratesAmount(count($cratesAmount));
            }

            $this->updateCratesAmount($entityManager, $clientOrder, $handledCartLines);
        }
    }

    private function handleCartLines(EntityManagerInterface $entityManager,
                                     Form                   $form,
                                     object                 $formContent): ?array {

        $quantities = Stream::explode(',', $formContent->quantity ?? "")->filter(fn($e) => $e);
        $boxTypeIds = Stream::explode(',', $formContent->boxTypeId ?? "")->filter(fn($e) => $e);
        $unitPrices = Stream::explode(',', $formContent->unitPrice ?? "")->filter(fn($e) => $e);

        $boxTypeRepository = $entityManager->getRepository(BoxType::class);

        $quantitiesCount = $quantities->count();
        if($quantitiesCount > 0
            && $quantitiesCount === $boxTypeIds->count()
            && $quantitiesCount === $unitPrices->count()) {
            $quantitiesArray = $quantities->values();
            $boxTypeIdsArray = $boxTypeIds->values();
            $unitPricesArray = $unitPrices->values();

            $handledCartLines = [];

            for($cartLineIndex = 0; $cartLineIndex < $quantitiesCount; $cartLineIndex++) {
                $boxType = $boxTypeRepository->find($boxTypeIdsArray[$cartLineIndex]);
                $quantity = (int)$quantitiesArray[$cartLineIndex];
                $unitPrice = (float)$unitPricesArray[$cartLineIndex];
                if(!$boxType || !$quantity || $quantity < 1) {
                    $form->addError("Veuillez saisir une quantité pour toutes les lignes du panier.");
                    $handledCartLines = [];
                    break;
                }

                $handledCartLines[] = [
                    'boxType' => $boxType,
                    'quantity' => $quantity,
                    'unitPrice' => $unitPrice ?: $boxType->getPrice(),
                ];
            }
        } else {
            $form->addError("Le panier est invalide");
        }

        return $handledCartLines ?? null;
    }

    /**
     * @return array|Box[]|DepositTicket[]
     */
    public function get(string $class): array {
        $id = $this->getToken();

        if(!isset($this->$class)) {
            $this->$class = $this->entityManager
                ->getRepository($class)
                ->findBy(["number" => $this->session->get("client_order.$id.$class", [])]);
        }

        return $this->$class;
    }

    public function getToken(): ?string {
        if(!$this->token) {
            $this->token = $this->request->getCurrentRequest()->get("session");

            if(!preg_match("/^[A-Z0-9]{8,32}$/i", $this->token)) {
                throw new BadRequestHttpException("Invalid client order session token");
            }
        }

        return $this->token;
    }

    private function updateCratesAmount(EntityManagerInterface $entityManager,
                                        ClientOrder            $clientOrder,
                                        array                  $cart): void {
        $splitting = $this->getCartSplitting($entityManager, $clientOrder->getClient(), $cart);
        $cratesAmount = count($splitting) ?: 1;
        if($cratesAmount) {
            $clientOrder->setCratesAmount($cratesAmount);
        }
    }

    /**
     * @param ['boxType' => BoxType, 'quantity' => int][] $cart
     * @return
     *     [
     * 'type' => string,
     * 'boxes' => [
     * 'type' => string,
     * 'quantity' => number
     * ][]
     * ][]
     */
    public function getCartSplitting(EntityManagerInterface $entityManager,
                                     Client                 $client,
                                     array                  $cart): array {
        $boxTypeRepository = $entityManager->getRepository(BoxType::class);
        $globalSettingRepository = $entityManager->getRepository(GlobalSetting::class);

        foreach($cart as &$line) {
            if(!($line['boxType'] instanceof BoxType)) {
                $line['boxType'] = $boxTypeRepository->find($line['boxType']);
            }
        }

        $defaultCrateTypeId = $globalSettingRepository->getValue(GlobalSetting::DEFAULT_CRATE_TYPE);
        $defaultCrateType = !empty($defaultCrateTypeId)
            ? $boxTypeRepository->find($defaultCrateTypeId)
            : null;

        $cartSplitting = [];
        $defaultCrateVolume = $defaultCrateType ? $defaultCrateType->getVolume() : null;

        if($defaultCrateType && $defaultCrateVolume) {
            $serializeCrate = fn(BoxType $crateType, array $boxes = []) => [
                'type' => $crateType->getName(),
                'boxes' => $boxes,
            ];
            $serializeBox = fn(BoxType $boxType, int $quantity) => [
                'type' => $boxType->getName(),
                'quantity' => $quantity,
            ];

            $cartTypeToQuantities = Stream::from($cart)
                ->keymap(fn(array $line) => [
                    $line['boxType']->getId(),
                    [
                        'quantity' => (int)$line['quantity'],
                        'boxType' => $line['boxType'],
                    ],
                ])
                ->toArray();

            $cratePatternLines = $client->getCratePatternLines()
                ->map(fn(CratePatternLine $line) => $serializeBox($line->getBoxType(), $line->getQuantity()))
                ->getValues();

            // first part: with client crate pattern
            $cartPatternTakeIntoAccount = false;
            if(!empty($cratePatternLines)) {
                while(!$cartPatternTakeIntoAccount) {
                    $cartContainsPattern = true;
                    foreach($cratePatternLines as $cratePatternLine) {
                        $type = $cratePatternLine['type'];
                        if(!isset($cartTypeToQuantities[$type])
                            || $cartTypeToQuantities[$type]['quantity'] < $cratePatternLine['quantity']) {
                            $cartContainsPattern = false;
                            break;
                        }
                    }

                    if($cartContainsPattern) {
                        foreach($cratePatternLines as $cratePatternLine) {
                            $type = $cratePatternLine['type'];
                            $cartTypeToQuantities[$type]['quantity'] -= $cratePatternLine['quantity'];
                            if(!$cartTypeToQuantities[$type]['quantity']) {
                                unset($cartTypeToQuantities[$type]);
                            }
                        }
                        $cartSplitting[] = $serializeCrate($defaultCrateType, $cratePatternLines);
                    } else {
                        $cartPatternTakeIntoAccount = true;
                    }
                }
            }

            // second part: remaining box in cart
            $cartSplittingLine = $serializeCrate($defaultCrateType);

            $splittingLineVolume = 0;

            foreach($cartTypeToQuantities as $cartLine) {
                $quantity = $cartLine['quantity'];
                $boxType = $cartLine['boxType'];
                $currentBoxVolume = $boxType->getVolume();

                do {
                    if($currentBoxVolume) {
                        $remainingVolumeInCurrentCrate = ($defaultCrateVolume - $splittingLineVolume);
                        $availableQuantityOfCurrentBox = floor($remainingVolumeInCurrentCrate / $currentBoxVolume);

                        // IF crate is full
                        // OR we can't put any box of this type
                        // THEN we get another crate
                        if($remainingVolumeInCurrentCrate <= 0
                            || $availableQuantityOfCurrentBox <= 0) {
                            $cartSplitting[] = $cartSplittingLine;

                            $cartSplittingLine = $serializeCrate($defaultCrateType);
                            $splittingLineVolume = 0;
                            continue;
                        }

                        // we put selected quantity of the current box into current crate
                        $boxQuantitySelected = min($quantity, $availableQuantityOfCurrentBox);
                    } else {
                        $boxQuantitySelected = $quantity;
                    }

                    $splittingLineVolume += ($currentBoxVolume * $boxQuantitySelected);
                    $cartSplittingLine['boxes'][] = $serializeBox($boxType, $boxQuantitySelected);

                    $quantity -= $boxQuantitySelected;
                } while($quantity > 0);
            }

            if(!empty($cartSplittingLine['boxes'])) {
                $cartSplitting[] = $cartSplittingLine;
                $cartSplittingLine = null;
            }
        }

        return $cartSplitting;
    }

    public function clientOrderLinesMapper(string $type, array $clientOrderLines, DateTime $from = null): array {
        $boxRepository = $this->entityManager->getRepository(Box::class);
        $boxTypeRepository = $this->entityManager->getRepository(BoxType::class);
        $depositTicketRepository = $this->entityManager->getRepository(DepositTicket::class);
        $clientRepository = $this->entityManager->getRepository(Client::class);
        $preparationLineRepository = $this->entityManager->getRepository(PreparationLine::class);

        $starterKit = $boxTypeRepository->findOneBy(['name' => BoxType::STARTER_KIT]);
        $now = new DateTime();
        $clientOrderLinesData = [];
        switch ($type) {
            case OrderType::RECURRENT:
            case OrderType::PURCHASE_TRADE:
                $clientOrderLinesData = Stream::from($clientOrderLines)
                    ->map(fn(array $clientOrderLine) => [
                        "clientOrder" => $clientOrderLine['number'],
                        "boxTypeName" => $clientOrderLine['boxTypeName'],
                        "boxAmount" => $clientOrderLine['lineQuantity'],
                        "unitPrice" => FormatHelper::price($clientOrderLine['unitPrice']),
                        "starterKitAmount" => FormatHelper::price($starterKit->getPrice()),
                        "workingDayDeliveryRate" => FormatHelper::price($clientOrderLine['workingDayDeliveryRate']),
                        "nonWorkingDayDeliveryRate" => FormatHelper::price($clientOrderLine['nonWorkingDayDeliveryRate']),
                        "deliveryPrice" => FormatHelper::price($clientOrderLine['deliveryPrice']),
                        "automatic" => FormatHelper::bool($clientOrderLine['automatic']),
                    ])
                    ->toArray();
                break;
            case OrderType::AUTONOMOUS_MANAGEMENT:
                $cratePatternAmounts = $clientRepository->getCratePatternAmountGroupedByClient();

                $preparationLines = $preparationLineRepository->countDeliveredByType($now, $from);
                $clientOrderLinesData = Stream::from($clientOrderLines)
                    ->map(fn(array $clientOrderLine) => [
                        "clientOrder" => $clientOrderLine['number'],
                        "deliveredBoxes" => $preparationLines[$clientOrderLine['boxTypeId']] ?? 0,
                        "monthlyPrice" => FormatHelper::price($clientOrderLine['monthlyPrice']),
                        "workingDayDeliveryRate" => FormatHelper::price($clientOrderLine['workingDayDeliveryRate']),
                        "nonWorkingDayDeliveryRate" => FormatHelper::price($clientOrderLine['nonWorkingDayDeliveryRate']),
                        "deliveryCost" => FormatHelper::price($clientOrderLine['deliveryCost']),
                        "paymentMode" => $clientOrderLine['paymentModes'],
                        "tokenDelivered" => $clientOrderLine['deliveryTokens'],
                        "crateAmount" => $clientOrderLine['crateAmount'],
                        "cratePrice" => $cratePatternAmounts[$clientOrderLine['clientId']] ?? null,
                        "automatic" => FormatHelper::bool($clientOrderLine['automatic']),
                    ])
                    ->toArray();
                break;
            case OrderType::ONE_TIME_SERVICE:
                $brokenBoxGroupedByType = $boxRepository->countBrokenGroupedByType();
                $depositoryValidGroupedByType = $depositTicketRepository->countByStatusGroupedByType(DepositTicket::VALID);
                $depositorySpentGroupedByType = $depositTicketRepository->countByStatusGroupedByType(DepositTicket::SPENT);

                $clientOrderLinesData = Stream::from($clientOrderLines)
                    ->map(function(array $clientOrderLine) use ($brokenBoxGroupedByType, $depositoryValidGroupedByType, $depositorySpentGroupedByType) {
                        $boxType = $clientOrderLine['boxTypeId'];
                        return [
                            "clientOrder" => $clientOrderLine['number'],
                            "boxTypeName" => $clientOrderLine['boxTypeName'],
                            "boxDelivered" => $clientOrderLine['lineQuantity'],
                            "tokenDelivered" => $clientOrderLine['deliveryTokens'],
                            "brokenBoxes" => $brokenBoxGroupedByType[$boxType] ?? 0,
                            "unitPrice" => FormatHelper::price($clientOrderLine['unitPrice']),
                            "paymentMode" => $clientOrderLine['paymentModes'],
                            "workingDayDeliveryRate" => FormatHelper::price($clientOrderLine['workingDayDeliveryRate']),
                            "nonWorkingDayDeliveryRate" => FormatHelper::price($clientOrderLine['nonWorkingDayDeliveryRate']),
                            "deliveryPrice" => FormatHelper::price(intval($clientOrderLine['lineQuantity']) * floatval($clientOrderLine['unitPrice'])),
                            "depositTicketUsed" => ($depositoryValidGroupedByType[$boxType] ?? 0) - ($depositorySpentGroupedByType[$boxType] ?? 0),
                            "automatic" => FormatHelper::bool($clientOrderLine['automatic']),
                        ];
                    })->toArray();
                break;
        }

        return $clientOrderLinesData;
    }

}
