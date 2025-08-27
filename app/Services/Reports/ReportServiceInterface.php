<?php

namespace App\Services\Reports;

interface ReportServiceInterface
{
    /**
     * Generate report data based on the provided filters.
     *
     * @param array $filters
     * @return array
     */
    public function generate(array $filters = []): array;

    /**
     * Get the report metadata (title, description, columns, etc.).
     *
     * @return array
     */
    public function getMetadata(): array;
}
