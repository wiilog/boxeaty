<?php

namespace App\Service;

use App\Entity\GlobalSetting;
use App\Entity\User;
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
        "Nom d'utilisateur",
        "Actif",
        "Adresse",
        "Utilisateur attribué",
    ];

    public const GROUP_HEADER = [
        "Nom de groupe",
        "Nom d'établissement",
        "Actif",
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

    public function createWorksheet(Spreadsheet $spreadsheet, string $name, string $class, array $header): Worksheet {
        $sheet = new Worksheet(null, $name);
        $sheet->fromArray(Stream::from($this->manager->getRepository($class)->iterateAll())
            ->map(fn(array $row) => $this->stringify($row))
            ->prepend($header)
            ->toArray());

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
