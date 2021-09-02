<?php

namespace App\Service;


use App\Entity\ClientOrder;
use App\Entity\DeliveryRound;
use App\Entity\Status;
use Doctrine\ORM\EntityManagerInterface;
use WiiCommon\Helper\Stream;

class DeliveryRoundService {

    public function updateDeliveryRound(EntityManagerInterface $entityManager, DeliveryRound $deliveryRound) {
        $statusRepository = $entityManager->getRepository(Status::class);
        $orders = $deliveryRound->getOrders()->toArray();

        $ready = Stream::from($orders)
            ->filter(fn(ClientOrder $order) => !$order->hasStatusCode(Status::CODE_ORDER_AWAITING_DELIVERER))
            ->isEmpty();

        $deliveryRound->setStatus($statusRepository->findOneBy([
            "code" => $ready ? Status::CODE_ROUND_AWAITING_DELIVERER : Status::CODE_ROUND_CREATED
        ]));
    }

}
