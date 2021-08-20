<?php

namespace App\Controller\Settings;

use App\Annotation\HasPermission;
use App\Entity\Box;
use App\Entity\BoxType;
use App\Entity\Client;
use App\Entity\ClientBoxType;
use App\Entity\ClientOrder;
use App\Entity\ClientOrderLine;
use App\Entity\Collect;
use App\Entity\Delivery;
use App\Entity\DepositTicket;
use App\Entity\OrderType;
use App\Entity\Quality;
use App\Entity\Role;
use App\Helper\Form;
use App\Helper\FormatHelper;
use App\Repository\QualityRepository;
use App\Service\ExportService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

/**
 * @Route("/parametrage/exports")
 */
class ExportController extends AbstractController
{

    /**
     * @Route("/", name="exports_index")
     * @HasPermission(Role::MANAGE_EXPORTS)
     */
    public function index(): Response
    {
        return $this->render("settings/export/index.html.twig");
    }

    /**
     * @Route("/client-order-autonomous-management", name="client_order_export_autonomous_management", options={"expose": true})
     */
    public function exportAutonomous(EntityManagerInterface $manager, ExportService $exportService, Request $request): Response
    {
        $query = $request->query;
        $deliveryRepository = $manager->getRepository(Delivery::class);
        $collectRepository = $manager->getRepository(Collect::class);
        $depositTicketRepository = $manager->getRepository(DepositTicket::class);
        $boxRepository = $manager->getRepository(Box::class);
        $clientOrderRepository = $manager->getRepository(ClientOrder::class);

        $clientOrderAutonomousManagementArray = [];
        $clientOrderLines = $clientOrderRepository->findByType(OrderType::AUTONOMOUS_MANAGEMENT, $query->get('from'), $query->get('to'));

        foreach ($clientOrderLines as $clientOrderLine) {
            $boxDelivered = count($deliveryRepository->findBy(['order'=>$clientOrderLine['clientOrderId']]));
            $boxCollect = count($collectRepository->findBy(['order'=>$clientOrderLine['clientOrderId']]));

            $tokenDelivered = $deliveryRepository->getDeliveredTokenByClientOrder($clientOrderLine['clientOrderId']);

            $boxType = $clientOrderLine['boxTypeId'];

            if ($clientOrderLine['customUnitPrice']) {
                $clientBoxTypePrice = $clientOrderLine['customUnitPrice'] / $clientOrderLine['lineQuantity'];
            } else {
                $clientBoxTypePrice = $clientOrderLine['boxTypePrice'];
            }

            $clientOrder = $clientOrderRepository->findOneBy(['id' => $clientOrderLine['clientOrderId']]);
            $amount = $clientOrder->getTotalAmount();

            $depositTicketValid = 0;
            $depositTicketSpent = 0;

            $broken = 0;
            $boxes = $boxRepository->findBy(['type' => $boxType]);

            foreach ($boxes as $box) {
                $depositoryValid = $depositTicketRepository->findByBoxAndStatus($box->getId(), DepositTicket::VALID);
                $depositTicketValid += $depositoryValid;
                $depositorySpent = $depositTicketRepository->findByBoxAndStatus($box->getId(), DepositTicket::SPENT);
                $depositTicketSpent += $depositorySpent;
                $boxQuality = $box->getQuality();
                if ($boxQuality) {
                    if ($boxQuality->getBroken()) {
                        $broken++;
                    }
                }
            }

            $array = [
                "clientOrder" => $clientOrderLine['clientOrderId'],
                "boxDelivered" => $boxDelivered,
                "boxCollect" => $boxCollect,
                "tokenDelivered" => $tokenDelivered,
                "brokenBoxes" => $broken,
                "boxTypeName" => $clientOrderLine['boxTypeName'],
                "clientBoxTypePrice" => round($clientBoxTypePrice, 1),
                "paymentMode" => $clientOrderLine['paymentModes'],
                "amount" => $amount,
                "depositTicketValid" => $depositTicketValid,
                "depositTicketSpent" => $depositTicketSpent,
                "automatic" => $clientOrderLine['automatic']
            ];
            $clientOrderAutonomousManagementArray[] = $array;
        }


        $today = new DateTime();
        $today = $today->format("d-m-Y-H-i-s");

        return $exportService->export(function ($output) use ($exportService, $clientOrderAutonomousManagementArray) {
            foreach ($clientOrderAutonomousManagementArray as $array) {
                $exportService->putLine($output, $array);
            }
        }, "export-client-order-autonomous-management-$today.csv", ExportService::CLIENT_ORDER_HEADER_AUTONOMOUS_MANAGEMENT);
    }


    /**
     * @Route("/client-order-one-time", name="client_order_export_one_time_service", options={"expose": true})
     */
    public function exportOneTimeService(EntityManagerInterface $manager, ExportService $exportService, Request $request): Response
    {
        $query = $request->query;
        $deliveryRepository = $manager->getRepository(Delivery::class);


        $clientOrderOneTimeServiceArray = [];
        $clientOrderLines = $manager->getRepository(ClientOrder::class)->findByType(OrderType::ONE_TIME_SERVICE, $query->get('from'), $query->get('to'));
        foreach ($clientOrderLines as $clientOrderLine) {

            $tokenDelivered = $deliveryRepository->getDeliveredTokenByClientOrder($clientOrderLine['clientOrderId']);

            $array = [
                "clientOrder" => $clientOrderLine['clientOrderId'],
                "monthlyPrice" => $clientOrderLine['monthlyPrice'],
                "deliveryCost" => $clientOrderLine['deliveryCost'],
                "paymentMode" => $clientOrderLine['paymentModes'],
                "prorateAmount" => $clientOrderLine['prorateAmount'],
                "tokenDelivered" => $tokenDelivered,
                "crateAmount" => $clientOrderLine['crateAmount'],
                "cratePrice" => $clientOrderLine['totalCrateTypePrice'],
                "automatic" => $clientOrderLine['automatic']
            ];
            $clientOrderOneTimeServiceArray[] = $array;
        }

        $today = new DateTime();
        $today = $today->format("d-m-Y-H-i-s");

        return $exportService->export(function ($output) use ($exportService, $clientOrderOneTimeServiceArray) {
            foreach ($clientOrderOneTimeServiceArray as $array) {
                $exportService->putLine($output, $array);
            }
        }, "export-client-order-one-time-$today.csv", ExportService::CLIENT_ORDER_HEADER_ONE_TIME);
    }

    /**
     * @Route("/client-order-purchase-trade", name="client_order_export_purchase_trade_service", options={"expose": true})
     */
    public function exportPurchaseTradeService(EntityManagerInterface $manager, ExportService $exportService, Request $request): Response
    {
        $query = $request->query;
        $starterKit = $manager->getRepository(BoxType::class)->findStarterKit();
        $clientOrderOneTimeServiceArray = [];
        $clientOrderLines = $manager->getRepository(ClientOrder::class)->findByType(OrderType::PURCHASE_TRADE, $query->get('from'), $query->get('to'));

        foreach ($clientOrderLines as $clientOrderLine) {
            $array = [
                "clientOrder" => $clientOrderLine['clientOrderId'],
                "clientOrderPrice" => $clientOrderLine['lineQuantity'] * $clientOrderLine['boxTypePrice'],
                "boxAmount" => $clientOrderLine['lineQuantity'],
                "starterKit" => $starterKit['price'],
                "deliveryPrice" => $clientOrderLine['deliveryPrice'],
                "automatic" => $clientOrderLine['automatic']
            ];
            $clientOrderOneTimeServiceArray[] = $array;
        }


        $today = new DateTime();
        $today = $today->format("d-m-Y-H-i-s");

        return $exportService->export(function ($output) use ($exportService, $clientOrderOneTimeServiceArray) {
            foreach ($clientOrderOneTimeServiceArray as $array) {
                $exportService->putLine($output, $array);
            }
        }, "export-client-order-trade-$today.csv", ExportService::CLIENT_ORDER_TRADE);
    }

}
