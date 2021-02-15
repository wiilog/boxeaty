<?php

namespace App\Service;

use DateTime;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService {

    public const ENCODING_UTF8 = "UTF8";
    public const ENCODING_WINDOWS = "WINDOWS";

    private string $encoding = self::ENCODING_UTF8;

    public function export(callable $generator, string $name, ?array $headers = null): StreamedResponse {
        $response = new StreamedResponse(function () use ($generator, $headers) {
            $output = fopen("php://output", "wb");
            if ($headers) {
                $firstCell = $headers[0] ?? null;
                if (is_array($firstCell)) {
                    foreach ($headers as $headerLine) {
                        $this->putLine($output, $headerLine);
                    }
                }
                else {
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
        $row = array_map(function($cell) {
            if($cell instanceof DateTime) {
                return $cell->format("d/m/Y H:i:s");
            } else {
                return $cell;
            }
        }, $row);

        $encodedRow = $this->encoding === self::ENCODING_UTF8
            ? array_map("utf8_decode", $row)
            : $row;

        fputcsv($handle, $encodedRow, ";");
    }

}
