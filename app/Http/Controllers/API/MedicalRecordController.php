<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\StaticData;
use App\Models\User;
use App\Models\RecordTransfer;
use Illuminate\Database\Eloquent\Builder; // Added for applyFilters

class MedicalRecordController extends BaseController
{
    /**
     * Display a listing of medical records with advanced filtering
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', MedicalRecord::class);

        $user = auth()->user();
        $query = MedicalRecord::with([
            'patient', 
            'healthCenter', 
            'status', 
            'problemType', 
            'creator', 
            'lastModifier',
            'transfers.recipient',
            'transfers.sender',
            'transfers.workflowSteps'
        ]);

        // Base authorization - Admin sees all records, others only see records they created or modified
        if (!$user->isAdmin()) {
            $query->where(function($q) use ($user) {
                $q->where('created_by', $user->user_id)
                  ->orWhere('last_modified_by', $user->user_id);
            });
        }

        // ===== PATIENT FILTERS =====
        // Filter by patient ID
        if ($request->filled('patient_id')) {
            $query->forPatient($request->patient_id);
        }

        // Filter by patient full name (partial match)
        if ($request->filled('patient_name')) {
            $query->whereHas('patient', function($q) use ($request) {
                $q->where('full_name', 'like', '%' . $request->patient_name . '%');
            });
        }

        // Filter by patient national ID (partial match)
        if ($request->filled('patient_national_id')) {
            $query->whereHas('patient', function($q) use ($request) {
                $q->where('national_id', 'like', '%' . $request->patient_national_id . '%');
            });
        }

        // Filter by patient gender
        if ($request->filled('patient_gender')) {
            $query->whereHas('patient', function($q) use ($request) {
                $q->where('gender_code', $request->patient_gender);
            });
        }

        // ===== RECORD STATUS & TYPE FILTERS =====
        // Filter by status
        if ($request->filled('status_code')) {
            $query->withStatus($request->status_code);
        }

        // Filter by multiple statuses
        if ($request->filled('status_codes')) {
            $statusCodes = is_array($request->status_codes) ? $request->status_codes : explode(',', $request->status_codes);
            $query->whereIn('status_code', $statusCodes);
        }

        // Filter by health center
        if ($request->filled('health_center_code')) {
            $query->fromHealthCenter($request->health_center_code);
        }

        // Filter by multiple health centers
        if ($request->filled('health_center_codes')) {
            $healthCenterCodes = is_array($request->health_center_codes) ? $request->health_center_codes : explode(',', $request->health_center_codes);
            $query->whereIn('health_center_code', $healthCenterCodes);
        }

        // Filter by problem type
        if ($request->filled('problem_type_code')) {
            $query->withProblemType($request->problem_type_code);
        }

        // Filter by multiple problem types
        if ($request->filled('problem_type_codes')) {
            $problemTypeCodes = is_array($request->problem_type_codes) ? $request->problem_type_codes : explode(',', $request->problem_type_codes);
            $query->whereIn('problem_type_code', $problemTypeCodes);
        }

        // ===== DATE RANGE FILTERS =====
        // Filter by creation date range
        if ($request->filled('created_from')) {
            $query->whereDate('created_at', '>=', $request->created_from);
        }
        if ($request->filled('created_to')) {
            $query->whereDate('created_at', '<=', $request->created_to);
        }

        // Filter by last modification date range
        if ($request->filled('modified_from')) {
            $query->whereDate('updated_at', '>=', $request->modified_from);
        }
        if ($request->filled('modified_to')) {
            $query->whereDate('updated_at', '<=', $request->modified_to);
        }

        // Filter by specific date
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        // ===== USER FILTERS =====
        // Filter by creator
        if ($request->filled('created_by')) {
            $query->createdBy($request->created_by);
        }

        // Filter by last modifier
        if ($request->filled('last_modified_by')) {
            $query->where('last_modified_by', $request->last_modified_by);
        }

        // ===== TRANSFER FILTERS =====
        // Filter by transfer status (has transfers or not)
        if ($request->filled('has_transfers')) {
            if ($request->has_transfers === 'true' || $request->has_transfers === true) {
                $query->whereHas('transfers');
            } else {
                $query->whereDoesntHave('transfers');
            }
        }

        // Filter by transfer sender
        if ($request->filled('transfer_sender_id')) {
            $query->whereHas('transfers', function($q) use ($request) {
                $q->where('sender_id', $request->transfer_sender_id);
            });
        }

        // Filter by transfer recipient
        if ($request->filled('transfer_recipient_id')) {
            $query->whereHas('transfers', function($q) use ($request) {
                $q->where('recipient_id', $request->transfer_recipient_id);
            });
        }

        // Filter by transfer date range
        if ($request->filled('transfer_from')) {
            $query->whereHas('transfers', function($q) use ($request) {
                $q->whereDate('created_at', '>=', $request->transfer_from);
            });
        }
        if ($request->filled('transfer_to')) {
            $query->whereHas('transfers', function($q) use ($request) {
                $q->whereDate('created_at', '<=', $request->transfer_to);
            });
        }

        // Filter by transfer notes content
        if ($request->filled('transfer_notes')) {
            $query->whereHas('transfers', function($q) use ($request) {
                $q->where('transfer_notes', 'like', '%' . $request->transfer_notes . '%');
            });
        }

        // ===== ADVANCED FILTERS =====
        // Filter by workflow step status
        if ($request->filled('workflow_step_status')) {
            $query->whereHas('transfers.workflowSteps', function($q) use ($request) {
                $q->where('step_status_code', $request->workflow_step_status);
            });
        }

        // Filter by completed workflow steps
        if ($request->filled('has_completed_workflow')) {
            if ($request->has_completed_workflow === 'true' || $request->has_completed_workflow === true) {
                $query->whereHas('transfers.workflowSteps', function($q) {
                    $q->whereNotNull('completed_at');
                });
            } else {
                $query->whereHas('transfers.workflowSteps', function($q) {
                    $q->whereNull('completed_at');
                });
            }
        }

        // ===== SEARCH FILTERS =====
        // Global search across patient name, national ID, and transfer notes
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->whereHas('patient', function($patientQuery) use ($searchTerm) {
                    $patientQuery->where('full_name', 'like', '%' . $searchTerm . '%')
                                ->orWhere('national_id', 'like', '%' . $searchTerm . '%');
                })
                ->orWhereHas('transfers', function($transferQuery) use ($searchTerm) {
                    $transferQuery->where('transfer_notes', 'like', '%' . $searchTerm . '%');
                });
            });
        }

        // ===== SORTING =====
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        // Validate sort fields
        $allowedSortFields = [
            'created_at', 'updated_at', 'patient_id', 'status_code', 
            'health_center_code', 'problem_type_code'
        ];
        
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc'); // Default sorting
        }

        // ===== PAGINATION =====
        $perPage = $request->get('per_page', 15);
        $perPage = min(max($perPage, 1), 100); // Limit between 1 and 100
        
        $records = $query->paginate($perPage);
        
        // Add filter summary to response
        $filters = $request->only([
            'patient_name', 'patient_national_id', 'patient_gender',
            'status_code', 'status_codes', 'health_center_code', 'health_center_codes',
            'problem_type_code', 'problem_type_codes', 'created_from', 'created_to',
            'modified_from', 'modified_to', 'date', 'created_by', 'last_modified_by',
            'has_transfers', 'transfer_sender_id', 'transfer_recipient_id',
            'transfer_from', 'transfer_to', 'transfer_notes', 'workflow_step_status',
            'has_completed_workflow', 'search', 'sort_by', 'sort_order'
        ]);
        
        // Remove empty filters
        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });
        
        $response = [
            'data' => $records->items(),
            'pagination' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
                'from' => $records->firstItem(),
                'to' => $records->lastItem(),
            ],
            'filters_applied' => $filters,
            'total_filtered' => $records->total()
        ];
        
        return $this->sendResponse($response, 'تم جلب قائمة السجلات الطبية بنجاح');
    }

    /**
     * Display the specified medical record
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $record = MedicalRecord::with([
            'patient', 'healthCenter', 'status', 'problemType', 'creator', 'lastModifier', 'transfers.recipient','transfers.sender'
        ])->findOrFail($id);
        
        $this->authorize('view', $record);
        
        return $this->sendResponse($record, 'تم جلب بيانات السجل الطبي بنجاح');
    }

    /**
     * Store a newly created medical record and automatically transfer it
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $this->authorize('create', MedicalRecord::class);

        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|integer|exists:patients,patient_id',
            'recipient_id' => 'required|integer|exists:users,user_id',
            'health_center_code' => 'required|string|exists:static_data,code',
            'problem_type_code' => 'required|string|exists:static_data,code',
            'status_code' => 'sometimes|string|exists:static_data,code',
            'transfer_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('بيانات غير صحيحة', $validator->errors(), 422);
        }

        $user = auth()->user();

        // Verify patient exists
        $patient = Patient::find($request->patient_id);
        if (!$patient) {
            return $this->sendError('لم يتم العثور على المريض', [], 404);
        }

        // Verify recipient exists and is not the same as sender
        $recipient = User::find($request->recipient_id);
        if (!$recipient) {
            return $this->sendError('لم يتم العثور على المستلم', [], 422);
        }

        if ($recipient->user_id === $user->user_id) {
            return $this->sendError('لا يمكن إرسال السجل لنفسك', [], 422);
        }

        // Verify health center exists
        $healthCenter = StaticData::where('type', 'health_center_type')
            ->where('code', $request->health_center_code)->first();
        if (!$healthCenter) {
            return $this->sendError('نوع المركز الصحي غير صحيح', [], 422);
        }

        // Verify problem type exists
        $problemType = StaticData::where('type', 'problem_type')
            ->where('code', $request->problem_type_code)->first();
        if (!$problemType) {
            return $this->sendError('نوع المشكلة غير صحيح', [], 422);
        }

        // Verify status exists (default to 'initiated' if not provided)
        $statusCode = $request->status_code ?? 'initiated';
        $status = StaticData::where('type', 'status')
            ->where('code', $statusCode)->first();
        if (!$status) {
            return $this->sendError('حالة السجل غير صحيحة', [], 422);
        }


        // Create the medical record
        $record = MedicalRecord::create([
            'patient_id' => $request->patient_id,
            'health_center_code' => $request->health_center_code,
            'problem_type_code' => $request->problem_type_code,
            'status_code' => $statusCode,
            'created_by' => $user->user_id,
            'last_modified_by' => $user->user_id,
        ]);

        // Check if record is already in transfer (since we removed status tracking, just check if any transfer exists)
        $existingTransfer = RecordTransfer::where('record_id', $record->record_id)->first();
        
        if ($existingTransfer) {
            return $this->sendError('السجل الطبي قيد النقل حالياً', [], 422);
        }

        // Create the transfer automatically
        $transfer = RecordTransfer::create([
            'record_id' => $record->record_id,
            'sender_id' => $user->user_id,
            'recipient_id' => $request->recipient_id,
            'transfer_notes' => $request->transfer_notes,
        ]);

        // Load relationships for notification
        $transfer->load(['medicalRecord.patient', 'medicalRecord.problemType', 'medicalRecord.status', 'sender']);

        // Real-time broadcasting removed

        return $this->sendResponse([
            'record' => $record->load(['patient', 'healthCenter', 'status', 'problemType', 'creator', 'transfers.recipient']),
            'transfer' => $transfer->load(['medicalRecord.patient', 'sender', 'recipient'])
        ], 'تم إنشاء السجل الطبي ونقله بنجاح', 201);
    }

    /**
     * Update the specified medical record
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $record = MedicalRecord::findOrFail($id);
        $this->authorize('update', $record);

        $validator = Validator::make($request->all(), [
            'health_center_code' => 'sometimes|string|exists:static_data,code',
            'problem_type_code' => 'sometimes|string|exists:static_data,code',
            'status_code' => 'sometimes|string|exists:static_data,code',
        ]);

        if ($validator->fails()) {
            return $this->sendError('بيانات غير صحيحة', $validator->errors(), 422);
        }

        // Update fields
        if ($request->has('health_center_code')) $record->health_center_code = $request->health_center_code;
        if ($request->has('problem_type_code')) $record->problem_type_code = $request->problem_type_code;
        if ($request->has('status_code')) $record->status_code = $request->status_code;
        
        $record->last_modified_by = auth()->user()->user_id;
        $record->save();

        return $this->sendResponse(
            ['record' => $record->load(['patient', 'healthCenter', 'status', 'problemType', 'creator', 'transfers.recipient'])],
            'تم تحديث السجل الطبي بنجاح'
        );
    }

    /**
     * Remove the specified medical record (Admin only)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $record = MedicalRecord::findOrFail($id);
        $this->authorize('delete', $record);

        // Check if record has related transfers
        if ($record->transfers()->exists()) {
            return $this->sendError('لا يمكن حذف السجل الطبي لوجود عمليات نقل مرتبطة به', [], 422);
        }

        $record->delete();

        return $this->sendResponse([], 'تم حذف السجل الطبي بنجاح');
    }

    /**
     * Get available filter options for medical records
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFilterOptions()
    {
        $this->authorize('viewAny', MedicalRecord::class);

        $user = auth()->user();

        // Get filter options based on user's accessible records
        $query = MedicalRecord::where(function($q) use ($user) {
            $q->where('created_by', $user->user_id)
              ->orWhere('last_modified_by', $user->user_id);
        });

        $options = [
            'statuses' => StaticData::where('type', 'status')
                ->whereIn('code', $query->distinct()->pluck('status_code'))
                ->select('code', 'label_en', 'label_ar')
                ->get(),
            
            'health_centers' => StaticData::where('type', 'health_center_type')
                ->whereIn('code', $query->distinct()->pluck('health_center_code'))
                ->select('code', 'label_en', 'label_ar')
                ->get(),
            
            'problem_types' => StaticData::where('type', 'problem_type')
                ->whereIn('code', $query->distinct()->pluck('problem_type_code'))
                ->select('code', 'label_en', 'label_ar')
                ->get(),
            
            'patients' => Patient::whereIn('patient_id', $query->distinct()->pluck('patient_id'))
                ->select('patient_id', 'full_name', 'national_id')
                ->get(),
            
            'users' => User::whereIn('user_id', $query->distinct()->pluck('created_by', 'last_modified_by')->flatten())
                ->select('user_id', 'full_name', 'username')
                ->get(),
        ];

        return $this->sendResponse($options, 'تم جلب خيارات الفلترة بنجاح');
    }

    /**
     * Get statistics for medical records
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatistics(Request $request)
    {
        $this->authorize('viewAny', MedicalRecord::class);

        $user = auth()->user();
        $query = MedicalRecord::where(function($q) use ($user) {
            $q->where('created_by', $user->user_id)
              ->orWhere('last_modified_by', $user->user_id);
        });

        // Apply filters if provided
        if ($request->filled('patient_id')) {
            $query->forPatient($request->patient_id);
        }
        if ($request->filled('status_code')) {
            $query->withStatus($request->status_code);
        }
        if ($request->filled('health_center_code')) {
            $query->fromHealthCenter($request->health_center_code);
        }
        if ($request->filled('created_from')) {
            $query->whereDate('created_at', '>=', $request->created_from);
        }
        if ($request->filled('created_to')) {
            $query->whereDate('created_at', '<=', $request->created_to);
        }

        $stats = [
            'total_records' => $query->count(),
            'records_by_status' => $query->selectRaw('status_code, COUNT(*) as count')
                ->groupBy('status_code')
                ->get(),
            'records_by_health_center' => $query->selectRaw('health_center_code, COUNT(*) as count')
                ->groupBy('health_center_code')
                ->get(),
            'records_by_problem_type' => $query->selectRaw('problem_type_code, COUNT(*) as count')
                ->groupBy('problem_type_code')
                ->get(),
            'records_with_transfers' => $query->hasTransfers()->count(),
            'records_without_transfers' => $query->noTransfers()->count(),
            'records_created_today' => $query->createdOn(now()->toDateString())->count(),
            'records_created_this_week' => $query->createdBetween(
                now()->startOfWeek()->toDateString(),
                now()->endOfWeek()->toDateString()
            )->count(),
            'records_created_this_month' => $query->createdBetween(
                now()->startOfMonth()->toDateString(),
                now()->endOfMonth()->toDateString()
            )->count(),
        ];

        return $this->sendResponse($stats, 'تم جلب الإحصائيات بنجاح');
    }

    /**
     * Export medical records with filters
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', MedicalRecord::class);

        $user = auth()->user();
        $query = MedicalRecord::with([
            'patient', 
            'healthCenter', 
            'status', 
            'problemType', 
            'creator', 
            'lastModifier',
            'transfers.recipient',
            'transfers.sender'
        ]);

        // Apply the same filters as index method
        $query->where(function($q) use ($user) {
            $q->where('created_by', $user->user_id)
              ->orWhere('last_modified_by', $user->user_id);
        });

        // Apply all filters (same logic as index method)
        $this->applyFilters($query, $request);

        // Get all records (no pagination for export)
        $records = $query->get();

        // Transform data for export
        $exportData = $records->map(function($record) {
            return [
                'Record ID' => $record->record_id,
                'Patient Name' => $record->patient->full_name ?? 'N/A',
                'Patient National ID' => $record->patient->national_id ?? 'N/A',
                'Health Center' => $record->healthCenter->label_en ?? 'N/A',
                'Status' => $record->status->label_en ?? 'N/A',
                'Problem Type' => $record->problemType->label_en ?? 'N/A',
                'Created By' => $record->creator->full_name ?? 'N/A',
                'Created At' => $record->created_at->format('Y-m-d H:i:s'),
                'Last Modified By' => $record->lastModifier->full_name ?? 'N/A',
                'Last Modified At' => $record->updated_at->format('Y-m-d H:i:s'),
                'Transfers Count' => $record->transfers->count(),
                'Transfer Notes' => $record->transfers->pluck('transfer_notes')->implode('; '),
            ];
        });

        return $this->sendResponse([
            'total_records' => $exportData->count(),
            'data' => $exportData,
            'filters_applied' => $request->only([
                'patient_name', 'patient_national_id', 'status_code', 'health_center_code',
                'problem_type_code', 'created_from', 'created_to', 'search'
            ])
        ], 'تم تصدير البيانات بنجاح');
    }

    /**
     * Apply filters to the query (helper method)
     *
     * @param Builder $query
     * @param Request $request
     * @return void
     */
    private function applyFilters($query, $request)
    {
        // Patient filters
        if ($request->filled('patient_id')) {
            $query->forPatient($request->patient_id);
        }
        if ($request->filled('patient_name')) {
            $query->whereHas('patient', function($q) use ($request) {
                $q->where('full_name', 'like', '%' . $request->patient_name . '%');
            });
        }
        if ($request->filled('patient_national_id')) {
            $query->whereHas('patient', function($q) use ($request) {
                $q->where('national_id', 'like', '%' . $request->patient_national_id . '%');
            });
        }

        // Status and type filters
        if ($request->filled('status_code')) {
            $query->withStatus($request->status_code);
        }
        if ($request->filled('status_codes')) {
            $statusCodes = is_array($request->status_codes) ? $request->status_codes : explode(',', $request->status_codes);
            $query->whereIn('status_code', $statusCodes);
        }
        if ($request->filled('health_center_code')) {
            $query->fromHealthCenter($request->health_center_code);
        }
        if ($request->filled('problem_type_code')) {
            $query->withProblemType($request->problem_type_code);
        }

        // Date filters
        if ($request->filled('created_from')) {
            $query->whereDate('created_at', '>=', $request->created_from);
        }
        if ($request->filled('created_to')) {
            $query->whereDate('created_at', '<=', $request->created_to);
        }

        // Search filter
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->whereHas('patient', function($patientQuery) use ($searchTerm) {
                    $patientQuery->where('full_name', 'like', '%' . $searchTerm . '%')
                                ->orWhere('national_id', 'like', '%' . $searchTerm . '%');
                })
                ->orWhereHas('transfers', function($transferQuery) use ($searchTerm) {
                    $transferQuery->where('transfer_notes', 'like', '%' . $searchTerm . '%');
                });
            });
        }
    }
}
