<?php

namespace App\Controller\Settings;

use App\Annotation\HasPermission;
use App\Entity\Box;
use App\Entity\BoxRecord;
use App\Entity\BoxType;
use App\Entity\Client;
use App\Entity\ClientOrder;
use App\Entity\Depository;
use App\Entity\DepositTicket;
use App\Entity\Group;
use App\Entity\Location;
use App\Entity\OrderType;
use App\Entity\Quality;
use App\Entity\Role;
use App\Entity\User;
use App\Service\BoxStateService;
use App\Service\ExportService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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
        $depositTicketRepository = $manager->getRepository(DepositTicket::class);
        $boxRepository = $manager->getRepository(Box::class);
        $clientOrderRepository = $manager->getRepository(ClientOrder::class);

        $clientOrderAutonomousManagementArray = [];
        $clientOrderLines = $clientOrderRepository->findByType(OrderType::AUTONOMOUS_MANAGEMENT, $query->get('from'), $query->get('to'));

        $brokenBoxGroupedByType = $boxRepository->countBrokenGroupedByType();
        $depositoryValidGroupedByType = $depositTicketRepository->countByStatusGroupedByType(DepositTicket::VALID);
        $depositorySpentGroupedByType = $depositTicketRepository->countByStatusGroupedByType(DepositTicket::SPENT);

        foreach ($clientOrderLines as $clientOrderLine) {
            $boxType = $clientOrderLine['boxTypeId'];
            $clientOrder = $clientOrderRepository->findOneBy(['id' => $clientOrderLine['clientOrderId']]);
            $amount = $clientOrder->getTotalAmount();

            $array = [
                "clientOrder" => $clientOrderLine['clientOrderNumber'],
                "boxTypeName" => $clientOrderLine['boxTypeName'],
                "boxDelivered" => $clientOrderLine['lineQuantity'],
                "tokenDelivered" => $clientOrderLine['deliveryTokens'],
                "brokenBoxes" => $brokenBoxGroupedByType[$boxType] ?? 0,
                "unitPrice" => $clientOrderLine['customUnitPrice'] ?? $clientOrderLine['boxTypePrice'],
                "paymentMode" => $clientOrderLine['paymentModes'],
                "amount" => $amount,
                "depositTicketValid" => $depositoryValidGroupedByType[$boxType] ?? 0,
                "depositTicketSpent" => $depositorySpentGroupedByType[$boxType] ?? 0,
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
        $clientOrderRepository = $manager->getRepository(ClientOrder::class);

        $clientOrderLines = $clientOrderRepository->findByType(OrderType::ONE_TIME_SERVICE, $query->get('from'), $query->get('to'));

        $clientOrderOneTimeServiceArray = Stream::from($clientOrderLines)
            ->map(fn(array $clientOrderLine) => [
                "clientOrder" => $clientOrderLine['clientOrderId'],
                "monthlyPrice" => $clientOrderLine['monthlyPrice'],
                "deliveryCost" => $clientOrderLine['deliveryCost'],
                "paymentMode" => $clientOrderLine['paymentModes'],
                "prorateAmount" => $clientOrderLine['prorateAmount'],
                "tokenDelivered" => $clientOrderLine['deliveryTokens'],
                "crateAmount" => $clientOrderLine['crateAmount'],
                "cratePrice" => $clientOrderLine['totalCrateTypePrice'],
                "automatic" => $clientOrderLine['automatic']
            ])
            ->toArray();

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
        $clientOrderRepository = $manager->getRepository(ClientOrder::class);

        $clientOrderLines = $clientOrderRepository->findByType(OrderType::PURCHASE_TRADE, $query->get('from'), $query->get('to'));
        $clientOrderLinesData = Stream::from($clientOrderLines)
            ->map(fn(array $clientOrderLine) => [
                "clientOrder" => $clientOrderLine['clientOrderId'],
                "boxTypeName" => $clientOrderLine['boxTypeName'],
                "unitPrice" => $clientOrderLine['customUnitPrice'] ?? $clientOrderLine['boxTypePrice'],
                "boxAmount" => $clientOrderLine['lineQuantity'],
                "deliveryPrice" => $clientOrderLine['deliveryPrice'],
                "automatic" => $clientOrderLine['automatic']
            ])
            ->toArray();

        $today = new DateTime();
        $today = $today->format("d-m-Y-H-i-s");

        return $exportService->export(function ($output) use ($exportService, $clientOrderLinesData) {
            foreach ($clientOrderLinesData as $array) {
                $exportService->putLine($output, $array);
            }
        }, "export-client-order-trade-$today.csv", ExportService::CLIENT_ORDER_TRADE);
    }

    /**
     * @Route("/global", name="global_export")
     * @HasPermission(Role::MANAGE_EXPORTS)
     */
    public function export(ExportService $exportService): Response {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->disconnectWorksheets();

        $exportService->createWorksheet($spreadsheet, "Box", Box::class, ExportService::BOX_HEADER, function(array $row) {
            $row["state"] = isset($row["state"]) ? BoxStateService::BOX_STATES[$row["state"]] : '';
            return $row;
        });
        $exportService->createWorksheet($spreadsheet, "Mouvements", BoxRecord::class, ExportService::MOVEMENT_HEADER, function(array $row) {
            $row["state"] = isset($row["state"]) ? BoxStateService::RECORD_STATES[$row["state"]] : '';
            return $row;
        });
        $exportService->createWorksheet($spreadsheet, "Tickets-consigne", DepositTicket::class, ExportService::DEPOSIT_TICKET_HEADER, function(array $row) {
            $row["state"] = isset($row["state"]) ? DepositTicket::NAMES[$row["state"]] : '';
            return $row;
        });

        $exportService->createWorksheet($spreadsheet, "Clients", Client::class, ExportService::CLIENT_HEADER);
        $exportService->createWorksheet($spreadsheet, "Groupes", Group::class, ExportService::GROUP_HEADER);
        $exportService->createWorksheet($spreadsheet, "Emplacements", Location::class, ExportService::LOCATION_HEADER);
        $exportService->createWorksheet($spreadsheet, "Types de Box", BoxType::class, ExportService::BOX_TYPE_HEADER);
        $exportService->createWorksheet($spreadsheet, "Dépôt", Depository::class, ExportService::DEPOSITORY_HEADER);

        $exportService->createWorksheet($spreadsheet, "Utilisateurs", User::class, ExportService::USER_HEADER);
        $exportService->createWorksheet($spreadsheet, "Rôles", Role::class, ExportService::ROLE_HEADER);
        $exportService->createWorksheet($spreadsheet, "Qualités", Quality::class, ExportService::QUALITY_HEADER);

        $file = "exports/export-general-" . bin2hex(random_bytes(8)) . ".xlsx";

        $writer = new Xlsx($spreadsheet);
        $writer->save($file);

        return $this->redirect("/$file");
    }

}
