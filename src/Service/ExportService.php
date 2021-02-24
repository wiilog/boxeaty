<?php

namespace App\Service;

use App\Entity\GlobalSetting;
use App\Helper\Stream;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService {

    public const USER_HEADER = [
        "Nom d'utilisateur",
        "Adresse email",
        "Rôle",
        "Actif",
        "Date de création",
        "Dernière connexion",
    ];

    public const CLIENT_HEADER = [
        "Nom du client",
        "Actif",
        "Adresse",
        "Contact attribué",
        "Groupe",
        "Multi-site"
    ];

    public const GROUP_HEADER = [
        "Nom de groupe",
        "Actif",
    ];

    public const MOVEMENT_HEADER = [
        "Date",
        "Numéro de box",
        "Qualité",
        "Etat",
        "Client",
        "Commentaire",
    ];

    public const BOX_TYPE_HEADER = [
        "Type de box",
        "Prix",
        "Actif"
    ];

    public const QUALITY_HEADER = [
        "Nom",
    ];

    public const DEPOSIT_TICKET_HEADER = [
        "Date de création",
        "Lieu de création",
        "Date de validité",
        "Numéro de consigne",
        "Date et heure d'utilisation de la consigne",
        "Emplacement de la consigne",
        "Etat",
    ];

    public const KIOSK_HEADER = [
        "Nom de la borne",
        "Client",
    ];

    public const ENCODING_UTF8 = "UTF8";
    public const ENCODING_WINDOWS = "WINDOWS";

    private EntityManagerInterface $manager;
    private ?string $encoding;

    public function __construct(EntityManagerInterface $manager) {
        $this->manager = $manager;
        $this->encoding = $manager->getRepository(GlobalSetting::class)
            ->findOneBy(["name" => GlobalSetting::CSV_EXPORTS_ENCODING])
            ->getValue();
    }

    public function export(callable $generator, string $name, ?array $headers = null): StreamedResponse {
        $response = new StreamedResponse(function() use ($generator, $headers) {
            $output = fopen("php://output", "wb");
            if ($headers) {
                $firstCell = $headers[0] ?? null;
                if (is_array($firstCell)) {
                    foreach ($headers as $headerLine) {
                        $this->putLine($output, $headerLine);
                    }
                } else {
                    $this->putLine($output, $headers);
                }
            }

            $generator($output);
            fclose($output);
        });

        $disposition = HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $name);

        $response->headers->set("Content-Type", "text/csv");
        $response->headers->set("Content-Disposition", $disposition);
        return $response;
    }

    public function putLine($handle, array $row) {
        $row = $this->stringify($row);

        $encodedRow = $this->encoding === self::ENCODING_UTF8
            ? array_map("utf8_decode", $row)
            : $row;

        fputcsv($handle, $encodedRow, ";");
    }

    public function createWorksheet(Spreadsheet $spreadsheet, string $name, string $class, array $header, callable $transformer = null): Worksheet {
        $sheet = new Worksheet(null, $name);
        $export = Stream::from($this->manager->getRepository($class)->iterateAll())
            ->map(function(array $row) use ($transformer) {
                if($transformer) {
                    $row = $transformer($row);
                }
                return $this->stringify($row);
            })
            ->prepend($header)
            ->toArray();

        $sheet->fromArray($export);
        $spreadsheet->addSheet($sheet);
        return $sheet;
    }

    public function stringify(array $row) {
        return array_map(function($cell) {
            if ($cell instanceof DateTime) {
                return $cell->format("d/m/Y H:i:s");
            } else if (is_bool($cell)) {
                return $cell ? 'oui' : 'non';
            } else {
                return $cell;
            }
        }, $row);
    }

}
