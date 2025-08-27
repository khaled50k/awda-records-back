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
                body { font-family: Arial, sans-serif; margin: 20px; direction: rtl; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
                .header h1 { color: #333; margin: 0; }
                .header p { color: #666; margin: 5px 0; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: right; font-size: 12px; }
                th { background-color: #f2f2f2; font-weight: bold; }
                tr:nth-child(even) { background-color: #f9f9f9; }
                .summary { margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 5px; }
                .summary h3 { margin-top: 0; color: #333; }
                .summary p { margin: 5px 0; }
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
                $html .= '<td>' . htmlspecialchars($value) . '</td>';
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
