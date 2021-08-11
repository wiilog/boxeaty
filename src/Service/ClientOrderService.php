<?php

namespace App\Service;

use App\Entity\Box;
use App\Entity\BoxType;
use App\Entity\Client;
use App\Entity\ClientOrder;
use App\Entity\ClientOrderLine;
use App\Entity\DeliveryMethod;
use App\Entity\DepositTicket;
use App\Entity\OrderType;
use App\Entity\Status;
use App\Entity\User;
use App\Helper\Form;
use DateTime;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\RouterInterface;
use WiiCommon\Helper\Stream;
use Doctrine\ORM\EntityManagerInterface;

use Twig\Environment;

class ClientOrderService
{
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

    /**
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param User $requester
     * @param ClientOrder $clientOrder
     */
    public function updateClientOrder(Request $request,
                                      EntityManagerInterface $entityManager,
                                      Form $form,
                                      ClientOrder $clientOrder): void {
        $content = (object) $request->request->all();
        $statusRepository = $entityManager->getRepository(Status::class);
        $typeRepository = $entityManager->getRepository(OrderType::class);
        $clientRepository = $entityManager->getRepository(Client::class);
        $deliveryMethodRepository = $entityManager->getRepository(DeliveryMethod::class);

        $status = $statusRepository->findOneBy(['code' => Status::CODE_ORDER_TO_VALIDATE_CLIENT]);

        $deliveryMethodId = $content->deliveryMethod ?? null;
        $deliveryMethod = $deliveryMethodId
            ? $deliveryMethodRepository->find($deliveryMethodId)
            : null;
        if (!$deliveryMethod) {
            $form->addError('Vous devez sélectionner au moins un moyen de transport');
        }

        $typeId = $content->type ?? null;
        $type = $typeId
            ? $typeRepository->find($typeId)
            : null;
        if (!$type) {
            $form->addError('Vous devez sélectionner au moins un type de commande');
        }

        $clientId = $content->client ?? null;
        $client = $clientId
            ? $clientRepository->find($clientId)
            : null;
        if (!$client) {
            $form->addError('client', 'Ce champ est requis');
        }

        $information = $client->getClientOrderInformation();
        $expectedDelivery = DateTime::createFromFormat('Y-m-d\TH:i', $content->date ?? null);

        if (isset($information)) {
            // check if it's the weekend
            $deliveryRate = !in_array($expectedDelivery->format('N'), [6, 7])
                ? $information->getWorkingDayDeliveryRate()
                : $information->getNonWorkingDayDeliveryRate();
            $serviceCost = $information->getServiceCost();
        } else {
            $deliveryRate = null;
            $serviceCost = null;
        }

        if ($type && $type->getCode() == OrderType::AUTONOMOUS_MANAGEMENT) {
            $collectRequired = (bool)($content->collectRequired ?? false);
            if ($collectRequired) {
                if (!isset($content->cratesAmountToCollect)
                    || $content->cratesAmountToCollect < 1) {
                    $form->addError('cratesAmountToCollect', 'Le nombre de caisses à collecter est invalide');
                }
            }
        }

        $handledCartLines = $this->handleCartLines($entityManager, $form, $content);
        if ($form->isValid()) {
            $clientOrderInformation = $client->getClientOrderInformation();
            $clientOrder = $clientOrder
                ->setExpectedDelivery($expectedDelivery)
                ->setClient($client)
                ->setCratesAmountToCollect($content->cratesAmountToCollect ?? null)
                ->setCollectRequired($collectRequired ?? false)
                ->setDeliveryPrice($deliveryRate)
                ->setServicePrice($serviceCost)
                ->setComment($content->comment ?? null)
                ->setType($type)
                ->setStatus($status)
                ->setDeliveryMethod($deliveryMethod)
                ->setTokensAmount(($clientOrderInformation ? $clientOrderInformation->getTokenAmount() : null) ?: 0);

            foreach ($clientOrder->getLines()->toArray() as $line) {
                $clientOrder->removeLine($line);
                $entityManager->remove($line);
            }

            foreach ($handledCartLines as $cartLine) {
                $boxType = $cartLine['boxType'];
                $clientOrderLine = new ClientOrderLine();
                $clientOrderLine
                    ->setBoxType($boxType)
                    ->setQuantity($cartLine['quantity'])
                    ->setCustomUnitPrice($cartLine['customUnitPrice'])
                    ->setClientOrder($clientOrder);

                $entityManager->persist($clientOrderLine);
            }
        }
    }

    private function handleCartLines(EntityManagerInterface $entityManager,
                                    Form $form,
                                    object $formContent): ? array {

        $quantities = Stream::explode(',', $formContent->quantity ?? "")->filter(fn($e) => $e);
        $boxTypeIds = Stream::explode(',', $formContent->boxTypeId ?? "")->filter(fn($e) => $e);
        $unitPrices = Stream::explode(',', $formContent->unitPrice ?? "")->filter(fn($e) => $e);

        $boxTypeRepository = $entityManager->getRepository(BoxType::class);

        $quantitiesCount = $quantities->count();
        if ($quantitiesCount > 0
            && $quantitiesCount === $boxTypeIds->count()
            && $quantitiesCount === $unitPrices->count()){
            $quantitiesArray = $quantities->values();
            $boxTypeIdsArray = $boxTypeIds->values();
            $unitPricesArray = $unitPrices->values();

            $handledCartLines = [];

            for ($cartLineIndex = 0; $cartLineIndex < $quantitiesCount; $cartLineIndex++) {
                $boxType = $boxTypeRepository->find($boxTypeIdsArray[$cartLineIndex]);
                $quantity = (int) $quantitiesArray[$cartLineIndex];
                $unitPrice = (float) $unitPricesArray[$cartLineIndex];
                if (!$boxType
                    || !$quantity
                    || $quantity < 1) {
                    $form->addError("Veuillez saisir une quantité pour toutes les lignes du panier.");
                    $handledCartLines = [];
                    break;
                }

                $handledCartLines[] = [
                    'boxType' => $boxType,
                    'quantity' => $quantity,
                    'customUnitPrice' => $unitPrice !== $boxType->getPrice()
                        ? $unitPrice
                        : null
                ];
            }
        }
        else {
            $form->addError("Le panier est invalide");
        }

        return $handledCartLines ?? null;
    }

    public function createClientOrderLine(ClientOrder $clientOrder,
                                          EntityManagerInterface $entityManager,
                                          Request $request): ?ClientOrderLine
    {
        $boxTypeRepository = $entityManager->getRepository(BoxType::class);
        $form = Form::create();
        $content = (object)$request->request->all();
        $quantity = $content->quantity;
        $boxType = $boxTypeRepository->findBy(["id" => $content->boxTypeId]);
        if ($form->isValid()) {
            for ($i = 0; $i < count($boxType); $i++) {
                $clientOrderLine = (new ClientOrderLine())
                    ->setClientOrder($clientOrder)
                    ->setBoxType($boxType[$i])
                    ->setQuantity($quantity[$i]);
                $entityManager->persist($clientOrder);
                $entityManager->flush();
            }
        }


        return $clientOrderLine ?? null;
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
        if (!$this->token) {
            $this->token = $this->request->getCurrentRequest()->get("session");

            if (!preg_match("/^[A-Z0-9]{8,32}$/i", $this->token)) {
                throw new BadRequestHttpException("Invalid client order session token");
            }
        }

        return $this->token;
    }

}
