<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Client;
use App\Entity\Group;
use App\Entity\Role;
use App\Entity\TrackingMovement;
use App\Entity\User;
use App\Helper\Stream;
use App\Service\ExportService;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
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

        $exportService->createWorksheet($spreadsheet, "Clients", Client::class, ExportService::CLIENT_HEADER);
        $exportService->createWorksheet($spreadsheet, "Groupes", Group::class, ExportService::GROUP_HEADER);
        $exportService->createWorksheet($spreadsheet, "Utilisateurs", User::class, ExportService::USER_HEADER);
        $exportService->createWorksheet($spreadsheet, "Mouvements", TrackingMovement::class, ExportService::MOVEMENT_HEADER);

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
