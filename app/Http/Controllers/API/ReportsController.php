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
            
            // Get the export service for the requested format
            $exportService = ExportServiceFactory::create($format);
            
            // Generate and return the file
            $filename = $this->generateFilename($reportType, $format, $filters);
            
            return $exportService->export($reportData, $filename);
            
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
