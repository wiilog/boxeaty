<?php

namespace App\Service;


use App\Entity\ClientOrder;
use App\Entity\DeliveryRound;
use App\Entity\Status;
use Doctrine\ORM\EntityManagerInterface;
use WiiCommon\Helper\Stream;

class DeliveryRoundService {
    public function updateDeliveryRound(EntityManagerInterface $entityManager,
                                        DeliveryRound $deliveryRound) {
        $statusRepository = $entityManager->getRepository(Status::class);
        $orders = $deliveryRound->getOrders()->toArray();

        $prepared = Stream::from($orders)
            ->filter(fn(ClientOrder $order) => (
                $order->getPreparation()
                && $order->getPreparation()->hasStatusCode(Status::CODE_PREPARATION_PREPARED)
            ))
            ->count();

        $status = ($prepared === count($orders))
            ? $statusRepository->findOneBy(["code" => Status::CODE_ROUND_AWAITING_DELIVERER])
            : $statusRepository->findOneBy(["code" => Status::CODE_ROUND_CREATED]);

        $deliveryRound->setStatus($status);
    }
}
