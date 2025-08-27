<?php

namespace App\Services\Exports;

use Rap2hpoutre\FastExcel\FastExcel;
use Illuminate\Support\Collection;

class ExcelExportService implements ExportServiceInterface
{
    /**
     * Export data to Excel format using FastExcel.
     *
     * @param array $data
     * @param string $filename
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function export(array $data, string $filename)
    {
        $exportData = $this->prepareDataForExport($data['data']);
        
        // Change file extension to .xlsx for Excel format
        $excelFilename = str_replace('.excel', '.xlsx', $filename);
        
        return (new FastExcel($exportData))->download($excelFilename);
    }

    /**
     * Prepare data for Excel export.
     *
     * @param Collection $data
     * @return Collection
     */
    private function prepareDataForExport(Collection $data): Collection
    {
        return $data->map(function ($patient) {
            return [
                'رقم المريض' => $patient['patient_id'],
                'اسم المريض' => $patient['patient_name'],
                'الطبيب' => $patient['doctor_or_reviewed_party'],
                // Add problem type columns dynamically
                ...array_filter($patient, function ($value, $key) {
                    return !in_array($key, ['patient_id', 'patient_name', 'doctor_or_reviewed_party']);
                }, ARRAY_FILTER_USE_BOTH)
            ];
        });
    }
}
