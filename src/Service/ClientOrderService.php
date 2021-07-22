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

    /** @Required */
    public UniqueNumberService $uniqueNumberService;

    public function createClientOrder(User $requester, EntityManagerInterface $entityManager, Request $request): ?ClientOrder
    {

        $form = Form::create();
        $content = (object)$request->request->all();
        $statusRepository = $entityManager->getRepository(Status::class);
        $typeRepository = $entityManager->getRepository(OrderType::class);
        $clientRepository = $entityManager->getRepository(Client::class);
        $deliveryMethodRepository = $entityManager->getRepository(DeliveryMethod::class);

        $deliveryMethod = $deliveryMethodRepository->findOneBy(["id" => $content->deliveryMethod]);
        $status = $statusRepository->findOneBy(['code' => 'ORDER_TO_VALIDATE']);
        $client = $clientRepository->findOneBy(["id" => $content->client]);
        $information = $client->getClientOrderInformation();
        if (isset($information)) {
            $deliveryRate = $information->getWorkingDayDeliveryRate();
            $serviceCost = $information->getServiceCost();
        } else {
            $deliveryRate = null;
            $serviceCost = null;
        }
        $number = $this->uniqueNumberService->createUniqueNumber($entityManager, ClientOrder::PREFIX_NUMBER, ClientOrder::class);
        $now = new DateTime('now', new DateTimeZone('Europe/Paris'));
        $expectedDelivery = new DateTime($content->date);
        $type = $typeRepository->findOneBy(["code" => $content->type]);
        $collect = false;
        $collectNumber = 0;
        if ($type->getCode() == 'AUTONOMOUS_MANAGEMENT') {
            $collect = $content->collect;
            // $collectNumber = $content->collectNumber;
        }

        $this->handleCart($form,$request,$content);
        if ($form->isValid()) {
            $clientOrder = (new ClientOrder())
                ->setNumber($number)
                ->setCreatedAt($now)
                ->setExpectedDelivery($expectedDelivery)
                ->setClient($client)
                ->setShouldCreateCollect($collect)
                ->setCollectNumber($collectNumber)
                ->setAutomatic(false)
                ->setDeliveryPrice($deliveryRate)
                ->setServicePrice($serviceCost)
                ->setValidatedAt(null)
                ->setComment($content->comment ?? null)
                ->setType($type)
                ->setStatus($status)
                ->setDeliveryMethod($deliveryMethod)
                ->setRequester($requester)
                ->setValidator(null)
                ->setDeliveryRound(null);


            $entityManager->persist($clientOrder);
            $entityManager->flush();
        }
        return $clientOrder ?? null;
    }

    public function handleCart(Form $form, Request $request, object $formContent)
    {
        $quantities = Stream::explode(',', $formContent->quantity ?? "")->filter(fn($e) => $e);
        $boxTypeIds = Stream::explode(',', $formContent->boxTypeId ?? "")->filter(fn($e) => $e);
        $unitPrices = Stream::explode(',', $formContent->unitPrice ?? "")->filter(fn($e) => $e);

        if($quantities->count() > 0
            && $quantities->count() === $boxTypeIds->count()
            && $quantities->count() === $unitPrices->count() ){


        }
        else{
            $form->addError("Le panier est invalide");
        }
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
