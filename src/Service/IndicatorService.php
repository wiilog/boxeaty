<?php


namespace App\Service;

use App\Entity\Client;
use App\Entity\ClientOrder;
use App\Entity\Collect;
use App\Entity\Delivery;
use App\Entity\DeliveryMethod;
use App\Entity\DeliveryRound;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use phpDocumentor\Reflection\Types\Boolean;
use WiiCommon\Helper\Stream;

class IndicatorService
{

    /** @Required */
    public EntityManagerInterface $manager;

    public function getIndicatorsValues(array $params, EntityManagerInterface $manager, Client $client, bool $fromClient = false): array {
        $totalQuantityDelivered = 0;
        $softMobilityTotalDistance = 0;
        $motorVehiclesTotalDistance = 0;
        $returnRateNumerator = 0;
        $returnRateDenominator = 0;
        $chartLabels = [];
        $dataDeliveredBoxs = [];
        $dataCollectedBoxs = [];

        if(!empty($params)) {
            if(count($params) < 2) {
                return [
                    "success" => false,
                    "message" => "Vous devez renseigner les 2 filtres de date",
                ];
            } else {
                $deliveryRepository = $manager->getRepository(Delivery::class);
                $deliveryRoundsRepository = $manager->getRepository(DeliveryRound::class);
                $collectRepository = $manager->getRepository(Collect::class);
                $clientRepository = $manager->getRepository(Client::class);
                $clientOrderRepository = $manager->getRepository(ClientOrder::class);

                $startDate = $params["from"] instanceof DateTime ? $params["from"] : DateTime::createFromFormat("Y-m-d", $params["from"]);
                $endDate = $params["to"] instanceof DateTime ? $params["to"] : DateTime::createFromFormat("Y-m-d", $params["to"]);

                for($i = clone $startDate; $i <= $endDate; $i->modify("+1 day")) {
                    $chartLabels[] = $i->format("d/m/Y");
                    $dateMin = clone $i;
                    $dateMax = clone $i;
                    $dateMin->setTime(0, 0, 0);
                    $dateMax->setTime(23, 59, 59);

                    $dataDeliveredBoxs[] = $deliveryRepository->getTotalQuantityByClientAndDeliveredDate($client, $dateMax, $dateMin) ?? 0;
                    $dataCollectedBoxs[] = $collectRepository->getTotalQuantityByClientAndCollectedDate($client, $dateMax, $dateMin) ?? 0;
                }

                if(array_sum($dataDeliveredBoxs)) {
                    $returnRateNumerator += array_sum($dataCollectedBoxs);
                    $returnRateDenominator += array_sum($dataDeliveredBoxs);
                }

                $isMultiSite = $client->isMultiSite();
                $dateMin = clone $startDate;
                $dateMax = clone $endDate;
                $dateMin->setTime(0, 0);
                $dateMax->setTime(23, 59, 59);
                $totalQuantityDelivered = $clientOrderRepository->findQuantityDeliveredBetweenDateAndClient($dateMax, $client, $dateMin);
                $softMobilityTotalDistance = $deliveryRoundsRepository->findDeliveryTotalDistance($dateMax, $client, [DeliveryMethod::BIKE], $dateMin);
                $motorVehiclesTotalDistance = $deliveryRoundsRepository->findDeliveryTotalDistance($dateMax, $client, [DeliveryMethod::LIGHT_TRUCK, DeliveryMethod::HEAVY_TRUCK], $dateMin);
                $softMobilityTotalDistance = Stream::from($softMobilityTotalDistance)
                    ->map(fn(array $distance) => intval($distance["distance"]))
                    ->sum();
                $motorVehiclesTotalDistance = Stream::from($motorVehiclesTotalDistance)
                    ->map(fn(array $distance) => intval($distance["distance"]))
                    ->sum();

                if($isMultiSite) {

                    $group = $client->getGroup();
                    $clients = $clientRepository->findBy(['group' => $group]);

                    foreach($clients as $subClient) {
                        if($client->getId() !== $subClient->getId()) {
                            $totalQuantityDelivered += $clientOrderRepository->findQuantityDeliveredBetweenDateAndClient($dateMax, $subClient, $dateMin);
                            $subClientSoftMobilityTotalDistance = $deliveryRoundsRepository->findDeliveryTotalDistance($dateMax, $subClient, [DeliveryMethod::BIKE], $dateMin);
                            $subClientMotorVehiclesTotalDistance = $deliveryRoundsRepository->findDeliveryTotalDistance($dateMax, $subClient, [DeliveryMethod::LIGHT_TRUCK, DeliveryMethod::HEAVY_TRUCK], $dateMin);
                            $softMobilityTotalDistance += Stream::from($subClientSoftMobilityTotalDistance)
                                ->map(fn(array $distance) => intval($distance["distance"]))
                                ->sum();
                            $motorVehiclesTotalDistance += Stream::from($subClientMotorVehiclesTotalDistance)
                                ->map(fn(array $distance) => intval($distance["distance"]))
                                ->sum();
                        }
                    }
                }
            }
        }

        $config = [
            'type' => 'line',
            'data' => [
                'labels' => $chartLabels,
                'datasets' => [
                    [
                        'label' => "Box livrées",
                        'data' => $dataDeliveredBoxs,
                        'backgroundColor' => ['#EB611B'],
                        'borderColor' => ['#EB611B'],
                    ],
                    [
                        'label' => "Box collectées",
                        'data' => $dataCollectedBoxs,
                        'backgroundColor' => ['#76B39D'],
                        'borderColor' => ['#76B39D'],
                    ],
                ],
                'scales' => [
                    'y' => [
                        'beginAtZero' => false,
                    ],
                ],
            ],
        ];

        $values = [];
        if (!$fromClient) {
            $values = [
                "success" => true,
                "chart" => json_encode($config),
            ];
        }

        $values = array_merge($values, [
            "containersUsed" => !empty($params) ? $totalQuantityDelivered : '--',
            "wasteAvoided" => !empty($params) ? (($totalQuantityDelivered * 35) / 1000) : '--',
            "softMobilityTotalDistance" => !empty($params) ? $softMobilityTotalDistance : '--',
            "motorVehiclesTotalDistance" => !empty($params) ? $motorVehiclesTotalDistance : '--',
            "returnRateNumerator" => !empty($params) ? $returnRateNumerator : '--',
            "returnRateDenominator" => !empty($params) ? $returnRateDenominator : '--',
        ]);

        return $values;
    }
}
