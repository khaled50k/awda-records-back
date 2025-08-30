<?php

namespace App\Services\Exports;

use Illuminate\Support\Collection;

class PdfExportService implements ExportServiceInterface
{
    public function __construct(private PdfRenderer $renderer)
    {
    }

    /**
     * Export data to PDF format (Arabic/RTL, A4 landscape) using a reusable renderer.
     *
     * @param array $data expects keys: data (Collection), summary, date_range
     * @param string $filename
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function export(array $data, string $filename)
    {
        /** @var Collection $rowsCollection */
        $rowsCollection = $data['data'] ?? collect();

        if ($rowsCollection->isEmpty()) {
            $headers = ['اسم المريض', 'الطبيب'];
            $rows = [];
        } else {
            // Determine headers from first row; drop patient_id; localize known labels
            $first = $rowsCollection->first();
            $keys = array_values(array_filter(array_keys($first), fn($k) => $k !== 'patient_id'));

            $headers = array_map(function ($key) {
                return match ($key) {
                    'patient_name' => 'اسم المريض',
                    'doctor_or_reviewed_party' => 'الطبيب',
                    default => $key, // Arabic labels for problem types already
                };
            }, $keys);

            // Build rows in the same order as headers
            $rows = $rowsCollection->map(function ($row) use ($keys) {
                return array_map(function ($key) use ($row) {
                    $value = $row[$key] ?? '';
                    return (string) $value;
                }, $keys);
            })->all();
        }

        $meta = [
            'date_range' => $data['date_range'] ?? null,
            'summary' => $data['summary'] ?? null,
        ];

        return $this->renderer->downloadTable(
            $filename,
            'تقرير السجلات الطبية اليومي',
            $headers,
            $rows,
            $meta
        );
    }
}
