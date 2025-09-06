<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\GenerateReportRequest;
use App\Services\Reports\DailyTransfersReportService;
use App\Services\Exports\ExportServiceFactory;
use App\Services\Reports\ReportServiceInterface;
use App\Models\Reports;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Rap2hpoutre\FastExcel\FastExcel;

class ReportsController extends BaseController
{
    /**
     * Generate and download a report based on the request parameters.
     *
     * @param GenerateReportRequest $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
     */
    public function generateReport(GenerateReportRequest $request)
    {
        $this->authorize('create', Reports::class);

        try {
            $reportType = $request->validated('report_type');
            $format = $request->validated('format', 'csv');
            $filters = $request->validated('filters', []);

            // Get the appropriate report service
            $reportService = $this->getReportService($reportType);
            
            // Generate report data
            $reportData = $reportService->generate($filters);
            
            // Generate filename
            $filename = $this->generateFilename($reportType, $format, $filters);
            
            // Handle different export formats
            return $this->handleExportFormat($format, $reportData, $filename);
            
        } catch (\Exception $e) {
            Log::error('Report generation failed', [
                'report_type' => $request->get('report_type'),
                'format' => $request->get('format'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'فشل في إنشاء التقرير',
                'error' => config('app.debug') ? $e->getMessage() : 'حدث خطأ أثناء إنشاء التقرير'
            ], 500);
        }
    }

    /**
     * Get available report types.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAvailableReports()
    {
        $this->authorize('viewAny', Reports::class);

        return response()->json([
            'success' => true,
            'data' => [
                'reports' => [
                    [
                        'type' => 'daily_transfers',
                        'name' => 'تقرير السجلات الطبية اليومي',
                        'description' => 'تقرير شامل لعمليات نقل السجلات الطبية',
                        'supported_formats' => ['csv', 'excel', 'pdf'],
                        'filters' => [
                            'from_date' => 'date',
                            'to_date' => 'date',
                            'health_center_code' => 'string',
                            'problem_type_code' => 'string'
                        ]
                    ]
                ],
                'formats' => [
                    'csv' => 'CSV ملف',
                    'excel' => 'Excel ملف',
                    'pdf' => 'PDF ملف'
                ]
            ],
            'message' => 'تم جلب أنواع التقارير المتاحة بنجاح'
        ]);
    }

    /**
     * Get the appropriate report service based on report type.
     *
     * @param string $reportType
     * @return \App\Services\Reports\ReportServiceInterface
     * @throws \InvalidArgumentException
     */
    private function getReportService(string $reportType): ReportServiceInterface
    {
        return match ($reportType) {
            'daily_transfers' => new DailyTransfersReportService(),
            default => throw new \InvalidArgumentException("Report type '{$reportType}' is not supported.")
        };
    }

    /**
     * Handle different export formats.
     *
     * @param string $format
     * @param array $reportData
     * @param string $filename
     * @return \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    private function handleExportFormat(string $format, array $reportData, string $filename)
    {
        return match ($format) {
            'excel' => $this->exportToExcel($reportData, $filename),
            default => $this->exportWithService($format, $reportData, $filename)
        };
    }

    /**
     * Export data to Excel format with Arabic RTL support.
     *
     * @param array $reportData
     * @param string $filename
     * @return \Illuminate\Http\JsonResponse
     */
    private function exportToExcel(array $reportData, string $filename)
    {
        // Prepare data with Arabic headers
        $exportData = $this->prepareExcelData($reportData['data']);

        // Create temp directory and file path
        $filePath = $this->createTempFile($filename, 'xlsx');
        $fullPath = storage_path('app/public/' . $filePath);

        // Export to Excel file
        (new FastExcel($exportData))->export($fullPath);

        // Return file URL response
        return $this->createFileUrlResponse($filePath, basename($filePath));
    }

    /**
     * Export using the service factory for other formats.
     *
     * @param string $format
     * @param array $reportData
     * @param string $filename
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    private function exportWithService(string $format, array $reportData, string $filename)
    {
        $exportService = ExportServiceFactory::create($format);
        return $exportService->export($reportData, $filename);
    }

    /**
     * Prepare data for Excel export with Arabic headers.
     *
     * @param \Illuminate\Support\Collection $data
     * @return \Illuminate\Support\Collection
     */
    private function prepareExcelData($data)
    {
        return collect($data)->map(function ($patient) {
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

    /**
     * Create a temporary file path for export.
     *
     * @param string $filename
     * @param string $extension
     * @return string
     */
    private function createTempFile(string $filename, string $extension): string
    {
        $tempDir = 'temp';
        
        // Create temp directory if it doesn't exist
        if (!Storage::disk('public')->exists($tempDir)) {
            Storage::disk('public')->makeDirectory($tempDir);
        }

        // Generate filename with correct extension
        $finalFilename = str_replace(['.excel', '.csv', '.pdf'], '.' . $extension, $filename);
        
        return $tempDir . '/' . $finalFilename;
    }

    /**
     * Create a JSON response with file URL.
     *
     * @param string $filePath
     * @param string $filename
     * @return \Illuminate\Http\JsonResponse
     */
    private function createFileUrlResponse(string $filePath, string $filename)
    {
        $fileUrl = url('storage/' . $filePath);
        
        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء التقرير بنجاح',
            'file_url' => $fileUrl,
            'filename' => $filename
        ]);
    }

    /**
     * Generate filename for the report.
     *
     * @param string $reportType
     * @param string $format
     * @param array $filters
     * @return string
     */
    private function generateFilename(string $reportType, string $format, array $filters): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $dateRange = '';
        
        if (isset($filters['from_date']) && isset($filters['to_date'])) {
            $fromDate = \Carbon\Carbon::parse($filters['from_date'])->format('Y-m-d');
            $toDate = \Carbon\Carbon::parse($filters['to_date'])->format('Y-m-d');
            $dateRange = "_{$fromDate}_to_{$toDate}";
        }
        
        return "{$reportType}_report{$dateRange}_{$timestamp}.{$format}";
    }
}
