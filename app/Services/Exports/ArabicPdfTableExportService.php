<?php

namespace App\Services\Exports;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Fresh, self-contained Arabic PDF table exporter.
 * - No external renderer or blade required
 * - A4 portrait, RTL, Arabic-friendly font (DejaVu Sans)
 * - Robust temp-file download flow
 */
class ArabicPdfTableExportService implements ExportServiceInterface
{
    /**
     * @param array $data expects keys: data (Collection), summary (array), date_range (array)
     */
    public function export(array $data, string $filename)
    {
        /** @var Collection $rowsCollection */
        $rowsCollection = $data['data'] ?? collect();

        // Determine headers/rows from the dataset
        if ($rowsCollection->isEmpty()) {
            $keys = ['patient_name', 'doctor_or_reviewed_party'];
        } else {
            $first = $rowsCollection->first();
            $keys = array_values(array_filter(array_keys($first), fn($k) => $k !== 'patient_id'));
        }

        $headers = array_map(function ($key) {
            return match ($key) {
                'patient_name' => 'اسم المريض',
                'doctor_or_reviewed_party' => 'الطبيب',
                default => (string)$key, // problem type labels are Arabic already
            };
        }, $keys);

        $rows = $rowsCollection->map(function ($row) use ($keys) {
            return array_map(function ($key) use ($row) {
                return (string)($row[$key] ?? '');
            }, $keys);
        })->all();

        $title = 'تقرير النقل اليومي';
        $generatedAt = now()->format('Y-m-d H:i:s');
        $dateRange = $data['date_range'] ?? null;

        $html = $this->buildHtml($title, $generatedAt, $headers, $rows, $dateRange);

        // Configure Dompdf for Arabic/RTL
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('dpi', 120);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $binary = $dompdf->output();

        // Safe download via temp file
        $tempDir = 'temp';
        Storage::makeDirectory($tempDir);
        $tempPath = storage_path('app/' . $tempDir . '/' . $filename);
        file_put_contents($tempPath, $binary);

        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) { ob_end_clean(); }
        }

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Build minimal, professional RTL A4 table HTML.
     */
    private function buildHtml(string $title, string $generatedAt, array $headers, array $rows, ?array $dateRange): string
    {
        $colspan = max(1, count($headers));

        $dateRow = '';
        if ($dateRange) {
            $from = $dateRange['from_date'] ?? '';
            $to = $dateRange['to_date'] ?? '';
            $dateRow = '&nbsp;&nbsp;|&nbsp;&nbsp; الفترة: من ' . e($from) . ' إلى ' . e($to);
        }

        $theadCols = '';
        foreach ($headers as $h) {
            $theadCols .= '<th>' . e($h) . '</th>';
        }

        $tbodyRows = '';
        foreach ($rows as $r) {
            $tbodyRows .= '<tr>';
            foreach ($r as $cell) {
                $safe = nl2br(e((string)$cell));
                $tbodyRows .= '<td>' . $safe . '</td>';
            }
            $tbodyRows .= '</tr>';
        }

        return '<!DOCTYPE html>
        <html dir="rtl" lang="ar"><head>
        <meta charset="UTF-8">
        <style>
            @page { size: A4 portrait; margin: 18mm; }
            body { font-family: "DejaVu Sans", "Cairo", "Amiri", sans-serif; direction: rtl; unicode-bidi: bidi-override; color: #111; line-height: 1.85; }
            table { width: 100%; border-collapse: collapse; table-layout: fixed; }
            thead { display: table-header-group; }
            tfoot { display: table-row-group; }
            tr { page-break-inside: avoid; }
            thead th { background: #f3f4f6; color: #111; }
            th, td { border: 1px solid #aeb4bb; padding: 8px 10px; text-align: right; font-size: 12px; word-wrap: break-word; white-space: pre-wrap; word-break: break-word; hyphens: auto; }
            tbody tr:nth-child(even) td { background-color: #fbfbfb; }
            .title-cell { background: #e5e7eb; font-weight: bold; font-size: 16px; text-align: center; padding: 10px; }
            .subtitle-cell { background: #f9fafb; font-size: 12px; text-align: center; color: #444; padding: 6px; }
            tbody td:first-child, thead th:first-child { width: 22%; }
            tbody td:nth-child(2), thead th:nth-child(2) { width: 18%; }
        </style>
        </head><body>
        <table>
            <thead>
                <tr><th class="title-cell" colspan="' . $colspan . '">' . e($title) . '</th></tr>
                <tr><th class="subtitle-cell" colspan="' . $colspan . '">نظام السجلات الطبية - AWDA &nbsp;&nbsp;|&nbsp;&nbsp; تاريخ التقرير: ' . e($generatedAt) . $dateRow . '</th></tr>
                <tr>' . $theadCols . '</tr>
            </thead>
            <tbody>' . $tbodyRows . '</tbody>
        </table>
        </body></html>';
    }
}


