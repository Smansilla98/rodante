<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExportService
{
    /**
     * @param  list<string>  $headers
     * @param  callable(\Closure):void  $writer  receives a writer fn(array $row): void
     */
    public function download(string $resource, array $headers, callable $writer): StreamedResponse
    {
        $filename = 'rodante-'.$resource.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($headers, $writer) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ';');
            $write = static function (array $row) use ($out): void {
                fputcsv($out, $row, ';');
            };
            $writer($write);
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
