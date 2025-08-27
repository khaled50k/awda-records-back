<?php

namespace App\Services\Exports;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Facades\Storage;
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
            $configArray = file_exists($vendorConfig) ? require $vendorConfig : [];
        }

        $config = new GpdfConfig($configArray);

        // Enforce A4 portrait and Arabic-friendly defaults
        $config->set('page.size', 'A4');
        $config->set('page.orientation', 'portrait');
        $config->set('pdf.default_font', 'tajawal');
        $config->set('pdf.isHtml5ParserEnabled', true);
        $config->set('pdf.isRemoteEnabled', true);
        $config->set('pdf.dpi', 120);

        $gpdf = new Gpdf($config);

        // Generate raw PDF binary
        $binary = $gpdf->generate($html);

        // Write to temp file and return a clean download (most reliable for browsers/PDF viewers)
        $tempDir = 'temp';
        Storage::makeDirectory($tempDir);
        $tempPath = storage_path('app/' . $tempDir . '/' . $filename);
        file_put_contents($tempPath, $binary);

        // Ensure no previous output corrupts the PDF
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) { ob_end_clean(); }
        }

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
        ])->deleteFileAfterSend(true);
    }
}
