<?php


namespace App\Controller\Tracking;


use App\Entity\Client;
use App\Entity\ClientOrder;
use App\Entity\Collect;
use App\Entity\Delivery;
use App\Entity\DeliveryMethod;
use App\Entity\DeliveryRound;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;
use WiiCommon\Helper\Stream;

/**
 * Route("tracabilite/indicateurs")
 */
class IndicatorController extends AbstractController
{
    /**
     * @Route("/api", name="indicators_api", options={"expose": true})
     */
    public function api(Request $request, EntityManagerInterface $manager): Response
    {   $params = $request->query->all();
        $totalQuantityDelivered = 0;
        $softMobilityTotalDistance = 0;
        $motorVehiclesTotalDistance = 0;
        $returnRate = 0;
        $chartLabels = [];
        $dataDeliveredBoxs = [];
        $dataCollectedBoxs = [];

        if (!empty($params)) {
            if (count($params) < 3) {
                return $this->json([
                    "success" => false,
                    "message" => "Vous devez renseigner les 3 filtres"
                ]);
            } else {

                $deliveryRepository = $manager->getRepository(Delivery::class);
                $deliveryRoundsRepository = $manager->getRepository(DeliveryRound::class);
                $collectRepository = $manager->getRepository(Collect::class);
                $clientRepository = $manager->getRepository(Client::class);
                $client = $clientRepository->findOneBy(['id' => $params['client']]);
                $clientOrderRepository = $manager->getRepository(ClientOrder::class);

                $startDate = \DateTime::createFromFormat("Y-m-d", $params["from"]);
                $endDate = \DateTime::createFromFormat("Y-m-d", $params["to"]);

                for ($i = clone $startDate; $i <= $endDate; $i->modify("+1 day")) {
                    $chartLabels[] = $i->format("d/m/Y");
                    $dateMin = clone $i;
                    $dateMax = clone $i;
                    $dateMin->setTime(0, 0, 0);
                    $dateMax->setTime(23, 59, 59);

                    $dataDeliveredBoxs[] = $deliveryRepository->getTotalQuantityByClientAndDeliveredDate($params['client'], $dateMin, $dateMax) ?? 0;
                    $dataCollectedBoxs[] = $collectRepository->getTotalQuantityByClientAndCollectedDate($params['client'], $dateMin, $dateMax) ?? 0;
                }

                $returnRate = round((array_sum($dataCollectedBoxs) * 100) / array_sum($dataDeliveredBoxs), 2);;

                $isMultiSite = $client->isMultiSite();
                $dateMin = clone $startDate;
                $dateMax = clone $endDate;
                $dateMin->setTime(0, 0, 0);
                $dateMax->setTime(23, 59, 59);
                $totalQuantityDelivered = $clientOrderRepository->findQuantityDeliveredBetweenDateAndClient($dateMin, $dateMax, $client);
                $softMobilityTotalDistance = $deliveryRoundsRepository->findDeliveryTotalDistance($dateMin, $dateMax, $client, [DeliveryMethod::BIKE]);
                $motorVehiclesTotalDistance = $deliveryRoundsRepository->findDeliveryTotalDistance($dateMin, $dateMax, $client, [DeliveryMethod::LIGHT_TRUCK, DeliveryMethod::HEAVY_TRUCK]);
                $softMobilityTotalDistance = Stream::from($softMobilityTotalDistance)
                    ->map(fn(array $distance) => intval($distance["distance"]))
                    ->sum();
                $motorVehiclesTotalDistance = Stream::from($motorVehiclesTotalDistance)
                    ->map(fn(array $distance) => intval($distance["distance"]))
                    ->sum();

                if ($isMultiSite) {

                    $group = $client->getGroup();
                    $clients = $clientRepository->findBy(['group' => $group]);

                    foreach ($clients as $subClient) {
                        if ($client->getId() !== $subClient->getId()) {
                            $totalQuantityDelivered += $clientOrderRepository->findQuantityDeliveredBetweenDateAndClient($dateMin, $dateMax, $subClient);
                            $subClientSoftMobilityTotalDistance = $deliveryRoundsRepository->findDeliveryTotalDistance($dateMin, $dateMax, $subClient, [DeliveryMethod::BIKE]);
                            $subClientMotorVehiclesTotalDistance = $deliveryRoundsRepository->findDeliveryTotalDistance($dateMin, $dateMax, $subClient, [DeliveryMethod::LIGHT_TRUCK, DeliveryMethod::HEAVY_TRUCK]);
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
                        'label' => "Box colléctées",
                        'data' => $dataCollectedBoxs,
                        'backgroundColor' => ['#76B39D'],
                        'borderColor' => ['#76B39D'],
                    ],
                ],
                'scales' => [
                    'y' => [
                        'beginAtZero' => false,
                    ]
                ]
            ],
        ];

        return $this->json([
            "success" => true,
            "containersUsed" => !empty($params) ? $totalQuantityDelivered : '--',
            "wasteAvoided" => !empty($params) ? (($totalQuantityDelivered * 35) / 1000) : '--',
            "softMobilityTotalDistance" => !empty($params) ? $softMobilityTotalDistance : '--',
            "motorVehiclesTotalDistance" => !empty($params) ? $motorVehiclesTotalDistance : '--',
            "returnRate" => !empty($params) ? $returnRate : '--',
            "chart" => json_encode($config),
        ]);
    }
}
