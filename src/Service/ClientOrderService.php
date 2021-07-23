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
use DateTimeZone;
use mysql_xdevapi\Exception;
use PhpParser\Node\Expr\Cast\Object_;
use Symfony\Component\HttpFoundation\Request;
use WiiCommon\Helper\Stream;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

use Twig\Environment;

class ClientOrderService
{

    public function handleCartLines(EntityManagerInterface $entityManager,
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
                    || $quantity < 1
                    || !$unitPrice
                    || $unitPrice <= 0) {
                    $form->addError("Le panier contient une ligne invalide");
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

    public function createClientOrderLine(ClientOrder $clientOrder, EntityManagerInterface $entityManager, Request $request): ?ClientOrderLine
    {
        $boxTypeRepository = $entityManager->getRepository(BoxType::class);
        $form = Form::create();
        $content = (object)$request->request->all();
        $quantity = $content->quantity;
        $boxType = $boxTypeRepository->findBy(["id" => $content->boxTypeId]);
        dump($boxType);
        if ($form->isValid()) {
            dump('coucou');
            for ($i = 0; $i < count($boxType); $i++) {
                dump("test");
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

}
