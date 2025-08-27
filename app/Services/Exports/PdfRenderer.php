<?php

namespace App\Services\Exports;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Omaralalwi\Gpdf\Gpdf;
use Omaralalwi\Gpdf\GpdfConfig;

class PdfRenderer
{
    public function __construct(private ViewFactory $view)
    {
    }

    /**
     * Render a standardized Arabic/RTL A4 PDF table and return a downloadable response.
     *
     * @param string $filename
     * @param string $title
     * @param array<int,string> $headers
     * @param array<int,array<int,string>|array<string,string>> $rows
     * @param array<string,mixed> $meta
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadTable(string $filename, string $title, array $headers, array $rows, array $meta = [])
    {
        $html = $this->view->make('reports.base_table', [
            'title' => $title,
            'headers' => $headers,
            'rows' => $rows,
            'meta' => $meta,
            'generatedAt' => now()->format('Y-m-d H:i:s'),
        ])->render();

        // Load Gpdf config (published or package default)
        $configArray = config('gpdf');
        if (empty($configArray)) {
            $vendorConfig = base_path('vendor/omaralalwi/gpdf/config/gpdf.php');
            if (file_exists($vendorConfig)) {
                $configArray = require $vendorConfig;
            } else {
                $configArray = [];
            }
        }

        $config = new GpdfConfig($configArray);

        // Enforce A4 portrait and Arabic-friendly defaults
        $config->set('page.size', 'A4');
        $config->set('page.orientation', 'portrait');
        $config->set('pdf.default_font', 'tajawal'); // one of Gpdf built-in Arabic fonts
        $config->set('pdf.isHtml5ParserEnabled', true);
        $config->set('pdf.isRemoteEnabled', true);
        $config->set('pdf.dpi', 120);

        $gpdf = new Gpdf($config);

        // Generate raw PDF binary
        $binary = $gpdf->generate($html);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => strlen($binary),
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
        ]);
    }
}
