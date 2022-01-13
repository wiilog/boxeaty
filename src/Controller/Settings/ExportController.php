<?php

namespace App\Controller\Settings;

use App\Annotation\HasPermission;
use App\Controller\AbstractController;
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
use App\Entity\PreparationLine;
use App\Entity\Quality;
use App\Entity\Role;
use App\Entity\User;
use App\Helper\FormatHelper;
use App\Service\BoxStateService;
use App\Service\ExportService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

/**
 * @Route("/parametrage/exports")
 */
class ExportController extends AbstractController {

    /**
     * @Route("/", name="exports_index")
     * @HasPermission(Role::MANAGE_EXPORTS)
     */
    public function index(): Response {
        return $this->render("settings/export/index.html.twig");
    }

    /**
     * @Route("/client-order-one-time", name="client_order_export_one_time", options={"expose": true})
     */
    public function exportOneTimeService(EntityManagerInterface $manager, ExportService $exportService, Request $request): Response {
        $query = $request->query;
        $depositTicketRepository = $manager->getRepository(DepositTicket::class);
        $boxRepository = $manager->getRepository(Box::class);
        $clientOrderRepository = $manager->getRepository(ClientOrder::class);

        $from = new DateTime($query->get('from'));
        $to = new DateTime($query->get('to'));

        $clientOrderOneTimeServiceArray = [];
        $clientOrderLines = $clientOrderRepository->findByType(OrderType::ONE_TIME_SERVICE, $from, $to);

        $brokenBoxGroupedByType = $boxRepository->countBrokenGroupedByType();
        $depositoryValidGroupedByType = $depositTicketRepository->countByStatusGroupedByType(DepositTicket::VALID);
        $depositorySpentGroupedByType = $depositTicketRepository->countByStatusGroupedByType(DepositTicket::SPENT);

        foreach($clientOrderLines as $clientOrderLine) {
            $boxType = $clientOrderLine['boxTypeId'];

            $clientOrderOneTimeServiceArray[] = [
                "clientOrder" => $clientOrderLine['number'],
                "boxTypeName" => $clientOrderLine['boxTypeName'],
                "boxDelivered" => $clientOrderLine['lineQuantity'],
                "tokenDelivered" => $clientOrderLine['deliveryTokens'],
                "brokenBoxes" => $brokenBoxGroupedByType[$boxType] ?? 0,
                "unitPrice" => $clientOrderLine['unitPrice'],
                "paymentMode" => $clientOrderLine['paymentModes'],
                "workingDayDeliveryRate" => $clientOrderLine['workingDayDeliveryRate'],
                "nonWorkingDayDeliveryRate" => $clientOrderLine['nonWorkingDayDeliveryRate'],
                "deliveryPrice" => intval($clientOrderLine['lineQuantity']) * floatval($clientOrderLine['unitPrice']),
                "depositTicketUsed" => ($depositoryValidGroupedByType[$boxType] ?? 0) - ($depositorySpentGroupedByType[$boxType] ?? 0),
                "automatic" => $clientOrderLine['automatic'],
            ];
        }

        $today = new DateTime();
        $today = $today->format("d-m-Y-H-i-s");

        return $exportService->export(function($output) use ($exportService, $clientOrderOneTimeServiceArray) {
            foreach($clientOrderOneTimeServiceArray as $array) {
                $exportService->putLine($output, $array);
            }
        }, "export-commandes-prestation-ponctuelle-$today.csv", ExportService::CLIENT_ORDER_HEADER_ONE_TIME);
    }

    /**
     * @Route("/client-order-autonomous-management", name="client_order_export_autonomous_management", options={"expose": true})
     */
    public function exportAutonomous(EntityManagerInterface $manager, ExportService $exportService, Request $request): Response {
        $query = $request->query;
        $clientOrderRepository = $manager->getRepository(ClientOrder::class);
        $clientRepository = $manager->getRepository(Client::class);
        $preparationLineRepository = $manager->getRepository(PreparationLine::class);

        $from = new DateTime($query->get('from'));
        $to = new DateTime($query->get('to'));

        $clientOrderLines = $clientOrderRepository->findByType(OrderType::AUTONOMOUS_MANAGEMENT, $from, $to);
        $cratePatternAmounts = $clientRepository->getCratePatternAmountGroupedByClient();

        $preparationLines = $preparationLineRepository->countDeliveredByType($from, $to);

        $clientOrderAutonomousManagementArray = Stream::from($clientOrderLines)
            ->map(fn(array $clientOrderLine) => [
                "clientOrder" => $clientOrderLine['number'],
                "deliveredBoxes" => $preparationLines[$clientOrderLine['boxTypeId']] ?? 0,
                "monthlyPrice" => $clientOrderLine['monthlyPrice'],
                "workingDayDeliveryRate" => $clientOrderLine['workingDayDeliveryRate'],
                "nonWorkingDayDeliveryRate" => $clientOrderLine['nonWorkingDayDeliveryRate'],
                "deliveryCost" => $clientOrderLine['deliveryCost'],
                "paymentMode" => $clientOrderLine['paymentModes'],
                "tokenDelivered" => $clientOrderLine['deliveryTokens'],
                "crateAmount" => $clientOrderLine['crateAmount'],
                "cratePrice" => $cratePatternAmounts[$clientOrderLine['clientId']] ?? null,
                "automatic" => $clientOrderLine['automatic'],
            ])
            ->toArray();

        $today = new DateTime();
        $today = $today->format("d-m-Y-H-i-s");

        return $exportService->export(function($output) use ($exportService, $clientOrderAutonomousManagementArray) {
            foreach($clientOrderAutonomousManagementArray as $array) {
                $exportService->putLine($output, $array);
            }
        }, "export-gestion-autonome-$today.csv", ExportService::CLIENT_ORDER_HEADER_AUTONOMOUS_MANAGEMENT);
    }

    /**
     * @Route("/client-commandes-order-purchase-trade", name="client_order_export_purchase_trade", options={"expose": true})
     */
    public function exportPurchaseTradeService(EntityManagerInterface $manager, ExportService $exportService, Request $request): Response {
        $query = $request->query;
        $clientOrderRepository = $manager->getRepository(ClientOrder::class);

        $from = new DateTime($query->get('from'));
        $to = new DateTime($query->get('to'));

        $clientOrderLines = $clientOrderRepository->findByType(OrderType::PURCHASE_TRADE, $from, $to);
        $starterKit = $manager->getRepository(BoxType::class)->findOneBy(['name' => BoxType::STARTER_KIT]);

        $clientOrderLinesData = Stream::from($clientOrderLines)
            ->map(fn(array $clientOrderLine) => [
                "clientOrder" => $clientOrderLine['number'],
                "boxTypeName" => $clientOrderLine['boxTypeName'],
                "boxAmount" => $clientOrderLine['lineQuantity'],
                "unitPrice" => $clientOrderLine['unitPrice'],
                "starterKitAmount" => FormatHelper::price($starterKit->getPrice()),
                "workingDayDeliveryRate" => $clientOrderLine['workingDayDeliveryRate'],
                "nonWorkingDayDeliveryRate" => $clientOrderLine['nonWorkingDayDeliveryRate'],
                "deliveryPrice" => $clientOrderLine['deliveryPrice'],
                "automatic" => $clientOrderLine['automatic'],
            ])
            ->toArray();

        $today = new DateTime();
        $today = $today->format("d-m-Y-H-i-s");

        return $exportService->export(function($output) use ($exportService, $clientOrderLinesData) {
            foreach($clientOrderLinesData as $array) {
                $exportService->putLine($output, $array);
            }
        }, "export-commandes-achat-negoce-$today.csv", ExportService::CLIENT_ORDER_TRADE);
    }

    /**
     * @Route("/client-recurrent-order", name="client_order_export_recurrent", options={"expose": true})
     */
    public function exportRecurrent(EntityManagerInterface $manager, ExportService $exportService, Request $request): Response {
        $query = $request->query;
        $clientOrderRepository = $manager->getRepository(ClientOrder::class);

        $from = new DateTime($query->get('from'));
        $to = new DateTime($query->get('to'));

        $clientOrderLines = $clientOrderRepository->findByType(OrderType::RECURRENT, $from, $to);
        $starterKit = $manager->getRepository(BoxType::class)->findOneBy(['name' => BoxType::STARTER_KIT]);

        $clientOrderLinesData = Stream::from($clientOrderLines)
            ->map(fn(array $clientOrderLine) => [
                "clientOrder" => $clientOrderLine['number'],
                "boxTypeName" => $clientOrderLine['boxTypeName'],
                "boxAmount" => $clientOrderLine['lineQuantity'],
                "unitPrice" => $clientOrderLine['unitPrice'],
                "starterKitAmount" => FormatHelper::price($starterKit->getPrice()),
                "workingDayDeliveryRate" => $clientOrderLine['workingDayDeliveryRate'],
                "nonWorkingDayDeliveryRate" => $clientOrderLine['nonWorkingDayDeliveryRate'],
                "deliveryPrice" => $clientOrderLine['deliveryPrice'],
                "automatic" => $clientOrderLine['automatic'],
            ])
            ->toArray();

        $today = new DateTime();
        $today = $today->format("d-m-Y-H-i-s");

        return $exportService->export(function($output) use ($exportService, $clientOrderLinesData) {
            foreach($clientOrderLinesData as $array) {
                $exportService->putLine($output, $array);
            }
        }, "export-commandes-recurrent-$today.csv", ExportService::CLIENT_ORDER_TRADE);
    }

    /**
     * @Route("/global", name="global_export", options={"expose": true})
     * @HasPermission(Role::MANAGE_EXPORTS)
     */
    public function export(ExportService $service): Response {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->disconnectWorksheets();

        $service->addWorksheet($spreadsheet, Box::class, ExportService::BOX_HEADER, $service->stateMapper(BoxStateService::BOX_STATES));
        $service->addWorksheet($spreadsheet, BoxRecord::class, ExportService::MOVEMENT_HEADER, $service->stateMapper(BoxStateService::RECORD_STATES));
        $service->addWorksheet($spreadsheet, DepositTicket::class, ExportService::TICKET_HEADER, $service->stateMapper(DepositTicket::NAMES));

        $service->addWorksheet($spreadsheet, Client::class, ExportService::CLIENT_HEADER);
        $service->addWorksheet($spreadsheet, Group::class, ExportService::GROUP_HEADER);
        $service->addWorksheet($spreadsheet, Location::class, ExportService::LOCATION_HEADER);
        $service->addWorksheet($spreadsheet, BoxType::class, ExportService::BOX_TYPE_HEADER);
        $service->addWorksheet($spreadsheet, Depository::class, ExportService::DEPOSITORY_HEADER);

        $service->addWorksheet($spreadsheet, User::class, ExportService::USER_HEADER);
        $service->addWorksheet($spreadsheet, Role::class, ExportService::ROLE_HEADER);
        $service->addWorksheet($spreadsheet, Quality::class, ExportService::QUALITY_HEADER);

        $file = "exports/export-general-" . bin2hex(random_bytes(8)) . ".xlsx";

        $writer = new Xlsx($spreadsheet);
        $writer->save($file);

        return $this->redirect("/$file");
    }

}
