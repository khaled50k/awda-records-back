<?php

namespace App\Services\Exports;

use InvalidArgumentException;

class ExportServiceFactory
{
    /**
     * Create an export service based on the requested format.
     *
     * @param string $format
     * @return ExportServiceInterface
     * @throws InvalidArgumentException
     */
    public static function create(string $format): ExportServiceInterface
    {
        return match ($format) {
            'csv' => new CsvExportService(),
            'excel' => new ExcelExportService(),
            'pdf' => new PdfExportService(),
            default => throw new InvalidArgumentException("Export format '{$format}' is not supported.")
        };
    }
}
