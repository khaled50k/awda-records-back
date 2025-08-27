<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Response as HttpResponse;

class PdfRenderer
{
    public function __construct(private ViewFactory $view)
    {
    }

    /**
     * Render a standardized Arabic/RTL A4 PDF table and return a downloadable response.
     *
     * @param string $filename      e.g. daily_transfers_2025-08-20.pdf
     * @param string $title         Report title in Arabic
     * @param array<int,string> $headers Arabic column headers (order shown)
     * @param array<int,array<int,string>|array<string,string>> $rows Rows as arrays (match headers order) or assoc arrays
     * @param array<string,mixed> $meta Optional metadata (date range, summary, etc.)
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadTable(string $filename, string $title, array $headers, array $rows, array $meta = []): HttpResponse
    {
        Pdf::setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
        ]);

        $html = $this->view->make('reports.base_table', [
            'title' => $title,
            'headers' => $headers,
            'rows' => $rows,
            'meta' => $meta,
            'generatedAt' => now()->format('Y-m-d H:i:s'),
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('A4', 'landscape');

        return $pdf->download($filename);
    }
}
