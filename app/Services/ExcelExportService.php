<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelExportService
{
    /**
     * Excel XML (abre en Excel / LibreOffice). Una hoja por sección.
     *
     * @param  list<array{title: string, headers: list<string>, rows: list<list<string|int|float|null>>}>  $sheets
     */
    public function download(string $resource, array $sheets): StreamedResponse
    {
        $filename = 'rodante-'.$resource.'-'.now()->format('Y-m-d').'.xls';

        return response()->streamDownload(function () use ($sheets) {
            echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
            echo '<?mso-application progid="Excel.Sheet"?>'."\n";
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"';
            echo ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
            echo '<Styles><Style ss:ID="header"><Font ss:Bold="1"/></Style></Styles>';

            foreach ($sheets as $sheet) {
                $name = $this->sheetName((string) ($sheet['title'] ?? 'Hoja'));
                echo '<Worksheet ss:Name="'.$this->xml($name).'"><Table>';

                echo '<Row>';
                foreach ($sheet['headers'] as $header) {
                    echo '<Cell ss:StyleID="header"><Data ss:Type="String">'.$this->xml((string) $header).'</Data></Cell>';
                }
                echo '</Row>';

                foreach ($sheet['rows'] as $row) {
                    echo '<Row>';
                    foreach ($row as $cell) {
                        if (is_int($cell) || is_float($cell)) {
                            echo '<Cell><Data ss:Type="Number">'.$cell.'</Data></Cell>';
                        } else {
                            echo '<Cell><Data ss:Type="String">'.$this->xml((string) ($cell ?? '')).'</Data></Cell>';
                        }
                    }
                    echo '</Row>';
                }

                echo '</Table></Worksheet>';
            }

            echo '</Workbook>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function sheetName(string $name): string
    {
        $clean = preg_replace('/[\\\\\/\?\*\[\]:]/', '', $name) ?: 'Hoja';

        return mb_substr($clean, 0, 31);
    }
}
