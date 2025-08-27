<?php

namespace App\Services\Exports;

interface ExportServiceInterface
{
    /**
     * Export data to a file and return a downloadable response.
     *
     * @param array $data
     * @param string $filename
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function export(array $data, string $filename);
}
