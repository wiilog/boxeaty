<?php


namespace App\Controller\Tracking;

use App\Annotation\HasPermission;
use App\Controller\AbstractController;
use App\Entity\Client;
use App\Entity\ClientOrder;
use App\Entity\Collect;
use App\Entity\Delivery;
use App\Entity\DeliveryMethod;
use App\Entity\DeliveryRound;
use App\Entity\Role;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Snappy\Pdf;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

/**
 * @Route("/tracabilite/indicateurs")
 */
class IndicatorController extends AbstractController {

    private const FORBIDDEN_FILE_CHARACTERS = [" ", "_", ".", "/", "\\", "?", ":", "\"", "*", "|", "<", ">"];

    /**
     * @Route("/index", name="indicators_index")
     * @HasPermission(Role::VIEW_INDICATORS)
     */
    public function index(Request $request, EntityManagerInterface $manager): Response {
        if($request->query->has("client")) {
            $client = $manager->find(Client::class, $request->query->get("client"));
        }

        return $this->render("tracking/indicators/index.html.twig", [
            "client" => $client ?? null,
            "from" => $request->query->get("from"),
            "to" => $request->query->get("to"),
        ]);
    }

    /**
     * @Route("/api", name="indicators_api", options={"expose": true})
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $params = $request->query->all();
        $values = [];
        $containersUsed = 0;
        $wasteAvoided = 0;
        $softMobilityTotalDistance = 0;
        $motorVehiclesTotalDistance = 0;
        $returnRateNumerator = 0;
        $returnRateDenominator = 0;
        if(isset($params['client'])) {
            $clients = $manager->getRepository(Client::class)->findBy(["id" => explode(',',$params['client'])]);
            foreach ($clients as $client) {
                $values = self::getIndicatorsValues($params, $manager, $client);
                $returnRateNumerator += $values['returnRateNumerator'];
                $returnRateDenominator += $values['returnRateDenominator'];
                $containersUsed += $values['containersUsed'];
                $wasteAvoided += $values['wasteAvoided'];
                $softMobilityTotalDistance += $values['softMobilityTotalDistance'];
                $motorVehiclesTotalDistance += $values['motorVehiclesTotalDistance'];
            }

            $values['wasteAvoided'] = $wasteAvoided;
            $values['containersUsed'] = $containersUsed;
            $values['motorVehiclesTotalDistance'] = $motorVehiclesTotalDistance;
            $values['softMobilityTotalDistance'] = $softMobilityTotalDistance;
            $values['returnRate'] = $returnRateNumerator && $returnRateDenominator
                ? round(($returnRateNumerator * 100) / $returnRateDenominator, 2)
                : 0;
        } else {
            $values['wasteAvoided'] = '--';
            $values['containersUsed'] = '--';
            $values['motorVehiclesTotalDistance'] = '--';
            $values['softMobilityTotalDistance'] = '--';
            $values['returnRate'] = '--';
            $values['success'] = true;
        }

        if(!$values['success']) {
            return $this->json([
                'success' => false,
                'message' => $values['message'],
            ]);
        }

        return $this->json($values);
    }

    /**
     * @Route("/print-indicators", name="print_indicators", options={"expose": true})
     */
    public function print(Pdf $snappy, Request $request, EntityManagerInterface $manager) {
        $params = $request->request->all();
        $boxesHistoryChartBase64 = $params["boxesHistoryChartBase64"] ?? null;
        $date = (new DateTime())->format('d-m-Y');
        $startDate = DateTime::createFromFormat("Y-m-d", $params["from"])->format('d/m/Y');
        $endDate = DateTime::createFromFormat("Y-m-d", $params["to"])->format('d/m/Y');
        $values = [];
        $containersUsed = 0;
        $wasteAvoided = 0;
        $softMobilityTotalDistance = 0;
        $motorVehiclesTotalDistance = 0;
        $returnRateNumerator = 0;
        $returnRateDenominator = 0;
        if(isset($params['client'])) {
            $clients = $manager->getRepository(Client::class)->findBy(["id" => explode(',',$params['client'])]);
            $clientName = Stream::from($clients)->map(fn(Client $client) => $client->getName())->join(",");
            foreach ($clients as $client) {
                $values = self::getIndicatorsValues($params, $manager, $client);
                $returnRateNumerator += $values['returnRateNumerator'];
                $returnRateDenominator += $values['returnRateDenominator'];
                $containersUsed += $values['containersUsed'];
                $wasteAvoided += $values['wasteAvoided'];
                $softMobilityTotalDistance += $values['softMobilityTotalDistance'];
                $motorVehiclesTotalDistance += $values['motorVehiclesTotalDistance'];
            }

            $values['wasteAvoided'] = $wasteAvoided;
            $values['containersUsed'] = $containersUsed;
            $values['motorVehiclesTotalDistance'] = $motorVehiclesTotalDistance;
            $values['softMobilityTotalDistance'] = $softMobilityTotalDistance;
            $values['returnRate'] = round(($returnRateNumerator * 100) / $returnRateDenominator, 2);
        } else {
            $values['wasteAvoided'] = '--';
            $values['containersUsed'] = '--';
            $values['motorVehiclesTotalDistance'] = '--';
            $values['softMobilityTotalDistance'] = '--';
            $values['returnRate'] = '--';
            $values['success'] = true;
        }

        $html = $this->renderView("print/indicators/base.html.twig", [
            'values' => $values,
            'from_print' => true,
            'print_details' => [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'client' => $clientName,
            ],
            'boxesHistoryChartBase64' => $boxesHistoryChartBase64,
            'title' => "Indicateurs $clientName du $date",
        ]);

        $pdf = $snappy->getOutputFromHtml($html, [
            "orientation" => "Landscape",
            "enable-local-file-access" => true,
            "margin-top" => 0,
            "margin-right" => 0,
            "margin-bottom" => 0,
            "margin-left" => 0,
            "page-size" => "A4",
            "zoom" => "1.30",
        ]);

        $response = new Response();
        $response->headers->set("Cache-Control", "private");
        $response->headers->set("Content-type", "application/pdf");
        $response->headers->set("Content-length",  strlen($pdf));
        $response->headers->set("X-Filename", "export-indicateurs-$clientName-$date.pdf");

        $response->sendHeaders();
        $response->setContent($pdf);

        return $response;
    }

    private static function getIndicatorsValues(array $params, EntityManagerInterface $manager, Client $client): array {
        $totalQuantityDelivered = 0;
        $softMobilityTotalDistance = 0;
        $motorVehiclesTotalDistance = 0;
        $returnRateNumerator = 0;
        $returnRateDenominator = 0;
        $chartLabels = [];
        $dataDeliveredBoxs = [];
        $dataCollectedBoxs = [];

        if(!empty($params)) {
            if(count($params) < 3) {
                return [
                    "success" => false,
                    "message" => "Vous devez renseigner les 3 filtres",
                ];
            } else {
                $deliveryRepository = $manager->getRepository(Delivery::class);
                $deliveryRoundsRepository = $manager->getRepository(DeliveryRound::class);
                $collectRepository = $manager->getRepository(Collect::class);
                $clientRepository = $manager->getRepository(Client::class);
                $clientOrderRepository = $manager->getRepository(ClientOrder::class);

                $startDate = DateTime::createFromFormat("Y-m-d", $params["from"]);
                $endDate = DateTime::createFromFormat("Y-m-d", $params["to"]);

                for($i = clone $startDate; $i <= $endDate; $i->modify("+1 day")) {
                    $chartLabels[] = $i->format("d/m/Y");
                    $dateMin = clone $i;
                    $dateMax = clone $i;
                    $dateMin->setTime(0, 0, 0);
                    $dateMax->setTime(23, 59, 59);

                    $dataDeliveredBoxs[] = $deliveryRepository->getTotalQuantityByClientAndDeliveredDate($client, $dateMin, $dateMax) ?? 0;
                    $dataCollectedBoxs[] = $collectRepository->getTotalQuantityByClientAndCollectedDate($client, $dateMin, $dateMax) ?? 0;
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
                $totalQuantityDelivered = $clientOrderRepository->findQuantityDeliveredBetweenDateAndClient($dateMin, $dateMax, $client);
                $softMobilityTotalDistance = $deliveryRoundsRepository->findDeliveryTotalDistance($dateMin, $dateMax, $client, [DeliveryMethod::BIKE]);
                $motorVehiclesTotalDistance = $deliveryRoundsRepository->findDeliveryTotalDistance($dateMin, $dateMax, $client, [DeliveryMethod::LIGHT_TRUCK, DeliveryMethod::HEAVY_TRUCK]);
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

        return [
            "success" => true,
            "containersUsed" => !empty($params) ? $totalQuantityDelivered : '--',
            "wasteAvoided" => !empty($params) ? (($totalQuantityDelivered * 35) / 1000) : '--',
            "softMobilityTotalDistance" => !empty($params) ? $softMobilityTotalDistance : '--',
            "motorVehiclesTotalDistance" => !empty($params) ? $motorVehiclesTotalDistance : '--',
            "returnRateNumerator" => !empty($params) ? $returnRateNumerator : '--',
            "returnRateDenominator" => !empty($params) ? $returnRateDenominator : '--',
            "chart" => json_encode($config),
        ];
    }

}
