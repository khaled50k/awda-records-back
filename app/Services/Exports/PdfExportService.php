<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class PdfExportService implements ExportServiceInterface
{
    /**
     * Export data to PDF format.
     *
     * @param array $data
     * @param string $filename
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function export(array $data, string $filename)
    {
        // Ensure DomPDF is configured for UTF-8 and Arabic-capable fonts
        Pdf::setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'defaultFont' => 'DejaVu Sans', // Supports Arabic glyphs
        ]);

        $html = $this->generateHtml($data);
        
        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'landscape');
        
        return $pdf->download($filename);
    }

    /**
     * Generate HTML content for the PDF.
     *
     * @param array $data
     * @return string
     */
    private function generateHtml(array $data): string
    {
        $html = $this->getHtmlHeader();
        $html .= $this->getHtmlTable($data['data']);
        $html .= $this->getHtmlFooter();
        
        return $html;
    }

    /**
     * Get HTML header with styling.
     *
     * @return string
     */
    private function getHtmlHeader(): string
    {
        return '
        <!DOCTYPE html>
        <html dir="rtl" lang="ar">
        <head>
            <meta charset="UTF-8">
            <title>تقرير النقل اليومي</title>
            <style>
                @page { margin: 20px; }
                body { font-family: "DejaVu Sans", "Amiri", "Arial", sans-serif; direction: rtl; unicode-bidi: embed; color: #111; }
                .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
                .header h1 { color: #333; margin: 0; font-size: 20px; }
                .header p { color: #666; margin: 4px 0; font-size: 12px; }
                table { width: 100%; border-collapse: collapse; margin-top: 12px; table-layout: fixed; }
                th, td { border: 1px solid #bbb; padding: 6px; text-align: right; font-size: 11px; word-wrap: break-word; }
                th { background-color: #f2f2f2; font-weight: bold; }
                tr:nth-child(even) { background-color: #fbfbfb; }
                .summary { margin-top: 14px; padding: 10px; background-color: #f8f9fa; border-radius: 4px; font-size: 12px; }
                .summary h3 { margin: 0 0 6px 0; color: #333; font-size: 14px; }
                .nowrap { white-space: nowrap; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>تقرير النقل اليومي</h1>
                <p>نظام السجلات الطبية - AWDA</p>
                <p>تاريخ التقرير: ' . now()->format('Y-m-d H:i:s') . '</p>
            </div>';
    }

    /**
     * Get HTML table content.
     *
     * @param Collection $data
     * @return string
     */
    private function getHtmlTable(Collection $data): string
    {
        if ($data->isEmpty()) {
            return '<p>لا توجد بيانات لعرضها</p>';
        }

        $headers = array_keys($data->first());
        
        $html = '<table>';
        
        // Table headers
        $html .= '<tr>';
        foreach ($headers as $header) {
            $html .= '<th>' . htmlspecialchars($header) . '</th>';
        }
        $html .= '</tr>';
        
        // Table data
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($headers as $header) {
                $value = $row[$header] ?? '';
                // Convert newlines to <br> to preserve multi-line Arabic text
                $value = nl2br((string) $value);
                $html .= '<td>' . $value . '</td>';
            }
            $html .= '</tr>';
        }
        
        $html .= '</table>';
        
        return $html;
    }

    /**
     * Get HTML footer.
     *
     * @return string
     */
    private function getHtmlFooter(): string
    {
        return '
        </body>
        </html>';
    }
}
