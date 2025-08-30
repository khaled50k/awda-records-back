<?php

namespace App\Services\Reports;

use App\Models\MedicalRecord;
use App\Models\StaticData;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class DailyTransfersReportService implements ReportServiceInterface
{
    /**
     * Generate daily transfers report data.
     *
     * @param array $filters
     * @return array
     */
    public function generate(array $filters = []): array
    {
        $user = Auth::user();
        
        // Sanitize filters: default dates to today if null/empty; ignore empty codes
        $fromInput = $filters['from_date'] ?? null;
        $toInput = $filters['to_date'] ?? null;
        
        $fromDate = $this->parseDateOrToday($fromInput);
        $toDate = $this->parseDateOrToday($toInput ?? $fromInput);
        
        $healthCenterCode = $this->nullIfEmpty($filters['health_center_code'] ?? null);
        $problemTypeCode = $this->nullIfEmpty($filters['problem_type_code'] ?? null);
        
        // Get problem type columns in Arabic
        $problemTypeColumnsAr = $this->getProblemTypeColumns();
        
        // Build the query
        $query = $this->buildQuery($fromDate, $toDate, [
            'health_center_code' => $healthCenterCode,
            'problem_type_code' => $problemTypeCode,
        ]);
        
        // Apply authorization scope for non-admin users
        if (!$user->isAdmin()) {
            $this->applyUserScope($query, $user);
        }
        
        $records = $query->get();
        
        if ($records->isEmpty()) {
            return [
                'data' => collect(),
                'summary' => [
                    'total_patients' => 0,
                    'total_records' => 0,
                    'total_transfers' => 0
                ],
                'date_range' => [
                    'from_date' => $fromDate,
                    'to_date' => $toDate
                ]
            ];
        }
        
        // Process records into the final format
        $formattedData = $this->processRecords($records, $problemTypeColumnsAr);
        
        return [
            'data' => $formattedData,
            'summary' => [
                'total_patients' => $formattedData->count(),
                'total_records' => $records->count(),
                'total_transfers' => $records->sum(function ($record) {
                    return $record->transfers->count();
                })
            ],
            'date_range' => [
                'from_date' => $fromDate,
                'to_date' => $toDate
            ]
        ];
    }

    /**
     * Get report metadata.
     *
     * @return array
     */
    public function getMetadata(): array
    {
        return [
            'title' => 'تقرير السجلات الطبية اليومي',
            'description' => 'تقرير شامل لعمليات نقل السجلات الطبية',
            'columns' => [
                'patient_id' => 'رقم المريض',
                'patient_name' => 'اسم المريض',
                'doctor_or_reviewed_party' => 'الطبيب',
            ]
        ];
    }

    private function parseDateOrToday($date): string
    {
        if (empty($date)) {
            return today()->toDateString();
        }
        if ($date instanceof Carbon) {
            return $date->toDateString();
        }
        return Carbon::parse($date)->toDateString();
    }

    private function nullIfEmpty($value)
    {
        if ($value === null) return null;
        if (is_string($value) && trim($value) === '') return null;
        return $value;
    }

    /**
     * Get problem type columns in Arabic.
     *
     * @return array
     */
    private function getProblemTypeColumns(): array
    {
        return StaticData::where('type', 'problem_type')
            ->where('is_active', true)
            ->pluck('label_ar', 'label_ar')
            ->mapWithKeys(function ($label) {
                return [$label => ''];
            })
            ->toArray();
    }

    /**
     * Build the main query.
     *
     * @param string $fromDate
     * @param string $toDate
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function buildQuery(string $fromDate, string $toDate, array $filters)
    {
        $query = MedicalRecord::with([
            'patient',
            'problemType',
            'transfers' => function ($q) use ($fromDate, $toDate) {
                $q->whereBetween('created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
                  ->with(['recipient'])
                  ->orderBy('created_at', 'desc');
            }
        ])
        ->whereHas('transfers', function ($q) use ($fromDate, $toDate) {
            $q->whereBetween('created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59']);
        });

        // Apply additional filters only when provided (null means include all)
        if (!empty($filters['health_center_code'])) {
            $query->whereHas('patient', function ($q) use ($filters) {
                $q->where('health_center_code', $filters['health_center_code']);
            });
        }

        if (!empty($filters['problem_type_code'])) {
            $query->where('problem_type_code', $filters['problem_type_code']);
        }

        return $query;
    }

    /**
     * Apply user-specific scope for non-admin users.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param User $user
     * @return void
     */
    private function applyUserScope($query, User $user): void
    {
        $query->where(function ($q) use ($user) {
            $q->where('created_by', $user->user_id)
              ->orWhere('last_modified_by', $user->user_id)
              ->orWhereHas('transfers', function ($transferQ) use ($user) {
                  $transferQ->where('sender_id', $user->user_id)
                            ->orWhere('recipient_id', $user->user_id);
              });
        });
    }

    /**
     * Process records into the final format.
     *
     * @param Collection $records
     * @param array $problemTypeColumnsAr
     * @return Collection
     */
    private function processRecords(Collection $records, array $problemTypeColumnsAr): Collection
    {
        $groupedByPatient = $records->groupBy('patient_id');

        return $groupedByPatient->map(function (Collection $patientRecords) use ($problemTypeColumnsAr) {
            $firstRecord = $patientRecords->first();
            
            $patientRow = [
                'patient_id'   => $firstRecord->patient_id,
                'patient_name' => $firstRecord->patient->full_name,
                'doctor_or_reviewed_party' => '',
            ] + $problemTypeColumnsAr;

            foreach ($patientRecords as $record) {
                $reviewer = $record->reviewed_party && $record->reviewed_party !== 'N/A'
                    ? $record->reviewed_party
                    : ($record->transfers->first()->recipient->full_name ?? 'N/A');

                $problemTypeAr = $record->problemType->label_ar ?? 'غير معروف';
                $notes = $record->transfers->first()->transfer_notes ?? '';

                if (array_key_exists($problemTypeAr, $patientRow)) {
                    $patientRow[$problemTypeAr] = empty($patientRow[$problemTypeAr])
                        ? $notes
                        : $patientRow[$problemTypeAr] . "\n---\n" . $notes;
                }

                if (empty($patientRow['doctor_or_reviewed_party'])) {
                    $patientRow['doctor_or_reviewed_party'] = $reviewer;
                }
            }
            
            return $patientRow;
        });
    }
}
