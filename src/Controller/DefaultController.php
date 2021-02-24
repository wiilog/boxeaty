<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\BoxType;
use App\Entity\Client;
use App\Entity\DepositTicket;
use App\Entity\Group;
use App\Entity\Kiosk;
use App\Entity\Quality;
use App\Entity\Role;
use App\Entity\TrackingMovement;
use App\Entity\User;
use App\Service\ExportService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController {

    /**
     * @Route("/accueil", name="home")
     */
    public function home(): Response {
        return $this->render("home.html.twig");
    }

    /**
     * @Route("/export", name="export")
     * @HasPermission(Role::GENERAL_EXPORT)
     */
    public function export(ExportService $exportService): Response {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->disconnectWorksheets();

        $exportService->createWorksheet($spreadsheet, "Bornes", Kiosk::class, ExportService::KIOSK_HEADER);
        $exportService->createWorksheet($spreadsheet, "Mouvements", TrackingMovement::class, ExportService::MOVEMENT_HEADER);
        $exportService->createWorksheet($spreadsheet, "Tickets-consigne", DepositTicket::class, ExportService::DEPOSIT_TICKET_HEADER, function (array $row) {
            $row["condition"] = DepositTicket::NAMES[$row["condition"]];
            return $row;
        });

        $exportService->createWorksheet($spreadsheet, "Clients", Client::class, ExportService::CLIENT_HEADER);
        $exportService->createWorksheet($spreadsheet, "Groupes", Group::class, ExportService::GROUP_HEADER);
        $exportService->createWorksheet($spreadsheet, "Qualités", Quality::class, ExportService::QUALITY_HEADER);
        $exportService->createWorksheet($spreadsheet, "Types de Box", BoxType::class, ExportService::BOX_TYPE_HEADER);

        $exportService->createWorksheet($spreadsheet, "Utilisateurs", User::class, ExportService::USER_HEADER);

        $file = "exports/export-general-" . bin2hex(random_bytes(8)) . ".xlsx";

        $writer = new Xlsx($spreadsheet);
        $writer->save($file);

        return $this->redirect("/$file");
    }

    /**
     * @Route("/lost", name="missing_route")
     */
    public function missingRoute(): Response {
        return $this->render("home.html.twig");
    }

}
