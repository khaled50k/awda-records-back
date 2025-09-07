<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\StaticData;
use App\Models\User;
use App\Models\RecordTransfer;
use App\Events\TransferCreated;
use App\Events\TransferReceived;
use Illuminate\Database\Eloquent\Builder; // Added for applyFilters
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Rap2hpoutre\FastExcel\FastExcel;

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
            'patient.healthCenter',
            'status',
            'problemType',
            'dangerLevel',
            'transferStatus',
            'creator',
            'lastModifier',
            'transfers.recipient',
            'transfers.sender',
            'transfers.workflowSteps'
        ]);

        // Base authorization - Admin sees all records, others only see records they created or modified
        if (!$user->isAdmin()) {
            $query->where(function ($q) use ($user) {
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
            $query->whereHas('patient', function ($q) use ($request) {
                $q->where('full_name', 'like', '%' . $request->patient_name . '%');
            });
        }

        // Filter by patient national ID (partial match)
        if ($request->filled('patient_national_id')) {
            $query->whereHas('patient', function ($q) use ($request) {
                $q->where('national_id', 'like', '%' . $request->patient_national_id . '%');
            });
        }

        // Filter by patient gender
        if ($request->filled('patient_gender')) {
            $query->whereHas('patient', function ($q) use ($request) {
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



        // Filter by problem type
        if ($request->filled('problem_type_code')) {
            $query->withProblemType($request->problem_type_code);
        }

        // Filter by multiple problem types
        if ($request->filled('problem_type_codes')) {
            $problemTypeCodes = is_array($request->problem_type_codes) ? $request->problem_type_codes : explode(',', $request->problem_type_codes);
            $query->whereIn('problem_type_code', $problemTypeCodes);
        }

        // Filter by danger level
        if ($request->filled('danger_level_code')) {
            $query->withDangerLevel($request->danger_level_code);
        }

        // Filter by multiple danger levels
        if ($request->filled('danger_level_codes')) {
            $dangerLevelCodes = is_array($request->danger_level_codes) ? $request->danger_level_codes : explode(',', $request->danger_level_codes);
            $query->whereIn('danger_level_code', $dangerLevelCodes);
        }

        // Filter by reviewed party user
        if ($request->filled('reviewed_party_user_id')) {
            $query->reviewedBy($request->reviewed_party_user_id);
        }
        if ($request->filled('final_status_code')) {
            $query->withFinalStatus($request->final_status_code);
        }

        // Filter by transfer status
        if ($request->filled('transfer_status_code')) {
            $query->withTransferStatus($request->transfer_status_code);
        }

        // Filter by multiple transfer statuses
        if ($request->filled('transfer_status_codes')) {
            $transferStatusCodes = is_array($request->transfer_status_codes) ? $request->transfer_status_codes : explode(',', $request->transfer_status_codes);
            $query->whereIn('transfer_status_code', $transferStatusCodes);
        }

        // ===== DATE RANGE FILTERS =====
        // Filter by creation date range
        if ($request->filled('created_from') || $request->filled('created_to')) {
            // If date range is provided, use it
            if ($request->filled('created_from')) {
                $query->whereDate('created_at', '>=', $request->created_from);
            }
            if ($request->filled('created_to')) {
                $query->whereDate('created_at', '<=', $request->created_to);
            }
        } else {
            // If no date range is provided, show only today's records
            $query->whereDate('created_at', today());
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
            $query->whereHas('transfers', function ($q) use ($request) {
                $q->where('sender_id', $request->transfer_sender_id);
            });
        }

        // Filter by transfer recipient
        if ($request->filled('transfer_recipient_id')) {
            $query->whereHas('transfers', function ($q) use ($request) {
                $q->where('recipient_id', $request->transfer_recipient_id);
            });
        }

        // Filter by transfer date range
        if ($request->filled('transfer_from')) {
            $query->whereHas('transfers', function ($q) use ($request) {
                $q->whereDate('created_at', '>=', $request->transfer_from);
            });
        }
        if ($request->filled('transfer_to')) {
            $query->whereHas('transfers', function ($q) use ($request) {
                $q->whereDate('created_at', '<=', $request->transfer_to);
            });
        }

        // Filter by transfer notes content
        if ($request->filled('transfer_notes')) {
            $query->whereHas('transfers', function ($q) use ($request) {
                $q->where('transfer_notes', 'like', '%' . $request->transfer_notes . '%');
            });
        }

        // ===== ADVANCED FILTERS =====
        // Filter by workflow step status
        if ($request->filled('workflow_step_status')) {
            $query->whereHas('transfers.workflowSteps', function ($q) use ($request) {
                $q->where('step_status_code', $request->workflow_step_status);
            });
        }

        // Filter by completed workflow steps
        if ($request->filled('has_completed_workflow')) {
            if ($request->has_completed_workflow === 'true' || $request->has_completed_workflow === true) {
                $query->whereHas('transfers.workflowSteps', function ($q) {
                    $q->whereNotNull('completed_at');
                });
            } else {
                $query->whereHas('transfers.workflowSteps', function ($q) {
                    $q->whereNull('completed_at');
                });
            }
        }

        // ===== SEARCH FILTERS =====
        // Global search across patient name, national ID, and transfer notes
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->whereHas('patient', function ($patientQuery) use ($searchTerm) {
                    $patientQuery->where('full_name', 'like', '%' . $searchTerm . '%')
                        ->orWhere('national_id', 'like', '%' . $searchTerm . '%');
                })
                    ->orWhereHas('transfers', function ($transferQuery) use ($searchTerm) {
                        $transferQuery->where('transfer_notes', 'like', '%' . $searchTerm . '%');
                    });
            });
        }

        // ===== SORTING =====
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        // Validate sort fields
        $allowedSortFields = [
            'created_at',
            'updated_at',
            'patient_id',
            'status_code',
            'problem_type_code',
            'danger_level_code',
            'reviewed_party_user_id',
            'final_status_code'
        ];

        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc'); // Default sorting
        }

        // ===== PAGINATION =====
        $perPage = $request->get('per_page', 100);
        $perPage = min(max($perPage, 1), 100); // Limit between 1 and 100

        $records = $query->paginate($perPage);

        // Add filter summary to response
        $filters = $request->only([
            'patient_name',
            'patient_national_id',
            'patient_gender',
            'status_code',
            'status_codes',
            'problem_type_code',
            'problem_type_codes',
            'danger_level_code',
            'danger_level_codes',
            'reviewed_party_user_id',
            'transfer_status_code',
            'transfer_status_codes',
            'created_from',
            'created_to',
            'modified_from',
            'modified_to',
            'date',
            'created_by',
            'last_modified_by',
            'has_transfers',
            'transfer_sender_id',
            'transfer_recipient_id',
            'transfer_from',
            'transfer_to',
            'transfer_notes',
            'workflow_step_status',
            'has_completed_workflow',
            'search',
            'sort_by',
            'sort_order'
        ]);

        // Remove empty filters
        $filters = array_filter($filters, function ($value) {
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
            'patient.healthCenter',
            'status',
            'problemType',
            'dangerLevel',
            'transferStatus',
            'creator',
            'lastModifier'
        ])->findOrFail($id);

        $this->authorize('view', $record);

        $user = auth()->user();
        
        // If user is admin, load all transfers
        // If user is employee and created this record, load only their last sent transfer
        if ($user->isAdmin()) {
            $record->load(['transfers.recipient', 'transfers.sender']);
        } else if ($record->created_by === $user->user_id) {
            // Load only the last transfer sent by this user
            $record->load(['transfers' => function($query) use ($user) {
                $query->where('sender_id', $user->user_id)
                      ->orderBy('created_at', 'desc')
                      ->limit(1);
            }, 'transfers.recipient', 'transfers.sender']);
        } else {
            // For other users, load transfers where they are the recipient
            $record->load(['transfers' => function($query) use ($user) {
                $query->where('recipient_id', $user->user_id);
            }, 'transfers.recipient', 'transfers.sender']);
        }

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
            'problem_type_code' => 'required|string|exists:static_data,code',
            'danger_level_code' => 'required|string|exists:static_data,code',
            'reviewed_party' => 'required|string|max:50',
            'status_code' => 'required|string|exists:static_data,code',
            'recipient_ids' => 'nullable|array', // Array of recipient IDs for immediate transfer
            'recipient_ids.*' => 'integer|exists:users,user_id', // Each recipient must be a valid user
            'transfer_notes' => 'required|string', // Optional transfer notes
        ], [
            'patient_id.required' => 'معرف المريض مطلوب',
            'patient_id.integer' => 'معرف المريض يجب أن يكون رقماً صحيحاً',
            'patient_id.exists' => 'معرف المريض غير موجود',
            'problem_type_code.required' => 'نوع المشكلة مطلوب',
            'problem_type_code.string' => 'نوع المشكلة يجب أن يكون نصاً',
            'problem_type_code.exists' => 'نوع المشكلة غير موجود',
            'danger_level_code.required' => 'مستوى الخطر مطلوب',
            'danger_level_code.string' => 'مستوى الخطر يجب أن يكون نصاً',
            'danger_level_code.exists' => 'مستوى الخطر غير موجود',
            'reviewed_party.required' => 'الطرف المراجع مطلوب',
            'reviewed_party.string' => 'الطرف المراجع يجب أن يكون نصاً',
            'reviewed_party.max' => 'الطرف المراجع يجب ألا يتجاوز 50 حرف',
            'status_code.required' => 'الحالة مطلوبة',
            'status_code.string' => 'رمز الحالة يجب أن يكون نصاً',
            'status_code.exists' => 'رمز الحالة غير موجود',
            'recipient_ids.array' => 'معرفات المستلمين يجب أن تكون مصفوفة',
            'recipient_ids.*.integer' => 'معرف المستلم يجب أن يكون رقماً صحيحاً',
            'recipient_ids.*.exists' => 'معرف المستلم غير موجود',
            'transfer_notes.string' => 'ملاحظات النقل يجب أن تكون نصاً',
            'transfer_notes.required' => 'الملاحظات  مطلوبة',
        ]);

        // Additional validation: if recipients are provided, transfer_notes is required
        if ($request->filled('recipient_ids') && !$request->filled('transfer_notes')) {
            return $this->sendError('ملاحظات النقل مطلوبة عند تحديد المستلمين', [], 422);
        }

        if ($validator->fails()) {
            return $this->sendError('بيانات غير صحيحة', $validator->errors(), 422);
        }

        $user = auth()->user();

        // Verify patient exists
        $patient = Patient::find($request->patient_id);
        if (!$patient) {
            return $this->sendError('لم يتم العثور على المريض', [], 404);
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


        // Use transaction for critical operations
        try {
            DB::beginTransaction();

            // Create the medical record
            $record = MedicalRecord::create([
                'patient_id' => $request->patient_id,
                'problem_type_code' => $request->problem_type_code,
                'danger_level_code' => $request->danger_level_code,
                'reviewed_party' => $request->reviewed_party,
                'status_code' => $statusCode,
                'transfer_status_code' => null, // No transfer status initially
                'created_by' => $user->user_id,
                'last_modified_by' => $user->user_id,
            ]);
            // Create transfers for each recipient or all admins if no recipients provided
            $transfers = [];
            $recipientIds = $request->recipient_ids ?? [];
            
            if (!empty($recipientIds)) {
                // Create transfer for each specified recipient
                foreach ($recipientIds as $recipientId) {
                    $recipient = User::find($recipientId);
                    if ($recipient && $recipient->user_id !== $user->user_id) {
                        $transfer = RecordTransfer::create([
                            'record_id' => $record->record_id,
                            'sender_id' => $user->user_id,
                            'recipient_id' => $recipientId,
                            'transfer_notes' => $request->transfer_notes,
                        ]);
                        $transfers[] = $transfer;
                        
                        // Broadcast transfer events
                        event(new TransferCreated($transfer, $user, $recipient));
                        event(new TransferReceived($transfer, $user, $recipient));
                    }
                }
            } else {
                // Create transfer with no specific recipient and send to all admins
                $transfer = RecordTransfer::create([
                    'record_id' => $record->record_id,
                    'sender_id' => $user->user_id,
                    'recipient_id' => null,
                    'transfer_notes' => $request->transfer_notes,
                ]);
                $transfers[] = $transfer;
                
                // Send notification to all admins
                $admins = User::where('role_code', 'admin')->get();
                foreach ($admins as $admin) {
                    event(new TransferCreated($transfer, $user, $admin));
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error creating medical record: ' . $e->getMessage());
            return $this->sendError('حدث خطأ أثناء إنشاء السجل الطبي', [], 500);
        }

        // Broadcast event for admin notifications
        // event(new \App\Events\MedicalRecordCreated($record, $user));

        $message = !empty($transfers) ? 'تم إنشاء السجل الطبي مع النقل تلقائياً بنجاح' : 'تم إنشاء السجل الطبي بنجاح';

        $responseData = [
            'record' => $record->load(['patient.healthCenter', 'status', 'problemType', 'dangerLevel', 'transferStatus', 'creator'])
        ];

        // If transfers were created, include them in the response
        if (!empty($transfers)) {
            $responseData['transfers'] = collect($transfers)->map(function($transfer) {
                return $transfer->load(['recipient', 'sender']);
            });
        }

        return $this->sendResponse($responseData, $message, 201);
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
        $record = MedicalRecord::with([
            'patient.healthCenter',
            'status',
            'problemType',
            'dangerLevel',
            'transferStatus',
            'creator',
            'lastModifier',
            'transfers.recipient',
            'transfers.sender'
        ])->findOrFail($id);
        
        $this->authorize('update', $record);

        $user = auth()->user();
        
        // Load the last transfer for this record
        $lastTransfer = $record->transfers()->latest('created_at')->first();
        
        // Validation rules for medical record
        $validator = Validator::make($request->all(), [
            'patient_id' => 'sometimes|integer|exists:patients,patient_id',
            'problem_type_code' => 'sometimes|string|exists:static_data,code',
            'danger_level_code' => 'sometimes|string|exists:static_data,code',
            'reviewed_party' => 'sometimes|string|max:50',
            'status_code' => 'sometimes|string|exists:static_data,code',
            'transfer_status_code' => 'sometimes|nullable|string|exists:static_data,code',
            
            // Transfer data validation (if transfer exists)
            'transfer_notes' => 'sometimes|string|max:1000',
            'recipient_id' => 'sometimes|nullable|integer|exists:users,user_id',
        ], [
            'patient_id.integer' => 'معرف المريض يجب أن يكون رقماً صحيحاً',
            'patient_id.exists' => 'معرف المريض غير موجود',
            'problem_type_code.string' => 'نوع المشكلة يجب أن يكون نصاً',
            'problem_type_code.exists' => 'نوع المشكلة غير موجود',
            'danger_level_code.string' => 'مستوى الخطر يجب أن يكون نصاً',
            'danger_level_code.exists' => 'مستوى الخطر غير موجود',
            'reviewed_party.string' => 'الطرف المراجع يجب أن يكون نصاً',
            'reviewed_party.max' => 'الطرف المراجع يجب ألا يتجاوز 50 حرف',
            'status_code.string' => 'رمز الحالة يجب أن يكون نصاً',
            'status_code.exists' => 'رمز الحالة غير موجود',
            'transfer_status_code.string' => 'رمز حالة النقل يجب أن يكون نصاً',
            'transfer_status_code.exists' => 'رمز حالة النقل غير موجود',
            'transfer_notes.string' => 'ملاحظات النقل يجب أن تكون نصاً',
            'transfer_notes.max' => 'ملاحظات النقل يجب ألا تتجاوز 1000 حرف',
            'recipient_id.integer' => 'معرف المستلم يجب أن يكون رقماً صحيحاً',
            'recipient_id.exists' => 'معرف المستلم غير موجود',
        ]);

        if ($validator->fails()) {
            return $this->sendError('بيانات غير صحيحة', $validator->errors(), 422);
        }

        // Get current user before transaction
        $currentUser = auth()->user();

        // Use transaction for critical operations
        try {
            DB::beginTransaction();

            // Update medical record fields
            $recordFields = [
                'patient_id',
                'problem_type_code', 
                'danger_level_code',
                'reviewed_party',
                'status_code',
                'transfer_status_code'
            ];

            foreach ($recordFields as $field) {
                if ($request->has($field)) {
                    $record->$field = $request->$field;
                }
            }

            $record->last_modified_by = $currentUser->user_id;
            $record->save();

            // Update transfer data if transfer exists and user has permission
            if ($lastTransfer && ($user->isAdmin() || $lastTransfer->sender_id === $user->user_id)) {
                $transferUpdated = false;
                
                if ($request->has('transfer_notes')) {
                    $lastTransfer->transfer_notes = $request->transfer_notes;
                    $transferUpdated = true;
                }
                
                if ($request->has('recipient_id')) {
                    $lastTransfer->recipient_id = $request->recipient_id;
                    $transferUpdated = true;
                }
                
                if ($transferUpdated) {
                    $lastTransfer->save();
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating medical record: ' . $e->getMessage());
            return $this->sendError('حدث خطأ أثناء تحديث السجل الطبي', [], 500);
        }

        // Reload the record with all relationships
        $record->refresh();
        $record->load([
            'patient.healthCenter',
            'status',
            'problemType',
            'dangerLevel',
            'transferStatus',
            'creator',
            'lastModifier',
            'transfers.recipient',
            'transfers.sender'
        ]);

        // Get the updated last transfer
        $updatedLastTransfer = $record->transfers()->latest('created_at')->first();

        $responseData = [
            'record' => $record,
            'last_transfer' => $updatedLastTransfer
        ];

        return $this->sendResponse($responseData, 'تم تحديث السجل الطبي بنجاح');
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

        // Use transaction for critical operations
        try {
            DB::beginTransaction();

            $record->delete();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('حدث خطأ أثناء حذف السجل الطبي', [], 500);
        }

        return $this->sendResponse([], 'تم حذف السجل الطبي بنجاح');
    }

    /**
     * Get daily transfers report grouped by patient
     * Returns comprehensive report of all transfers for medical records within date range
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDailyTransfersReport(Request $request)
    {
        $this->authorize('viewAny', MedicalRecord::class);

        $user = auth()->user();
        
        // FIX 1: Parse and reformat incoming dates to the standard 'Y-m-d' format.
        // This ensures the database query works correctly, regardless of the input format.
        try {
            $fromDate = Carbon::parse($request->get('from_date', today()))->toDateString();
            $toDate = Carbon::parse($request->get('to_date', today()))->toDateString();
        } catch (\Exception $e) {
            // Handle cases where the date format is invalid
            return $this->sendError('Invalid date format. Please use a recognizable date format like Y-m-d or d-m-Y.', [], 422);
        }

        // Dynamically fetch problem type columns in Arabic from the database
        $problemTypeColumnsAr = StaticData::where('type', 'problem_type')
            ->where('is_active', true)
            ->pluck('label_ar', 'label_ar')
            ->mapWithKeys(function ($label) {
                return [$label => ''];
            })
            ->toArray();

        // Build the database query with necessary relationships.
        $query = MedicalRecord::with([
            'patient',
            'problemType',
            'transfers' => function ($q) use ($fromDate, $toDate) {
                // FIX 2: Use the correctly formatted dates in the query.
                $q->whereBetween('created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
                  ->with(['recipient'])
                  ->orderBy('created_at', 'desc');
            }
        ])
        ->whereHas('transfers', function ($q) use ($fromDate, $toDate) {
            // FIX 3: Also use the correctly formatted dates here.
            $q->whereBetween('created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59']);
        });

        // Apply authorization scope for non-admin users.
        if (!$user->isAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->user_id)
                  ->orWhere('last_modified_by', $user->user_id)
                  ->orWhereHas('transfers', function ($transferQ) use ($user) {
                      $transferQ->where('sender_id', $user->user_id)
                                ->orWhere('recipient_id', $user->user_id);
                  });
            });
        }

        $records = $query->get();

        // If the query returns no records, we can stop here and return the empty summary.
        if ($records->isEmpty()) {
            return $this->sendResponse([
                'date_range' => ['from_date' => $fromDate, 'to_date' => $toDate],
                'summary' => ['total_patients' => 0, 'total_records' => 0, 'total_transfers' => 0],
                'patients' => [],
            ], 'تم جلب تقرير السجلات الطبية اليومي بنجاح');
        }

        // Process the records into the final pivoted format.
        $groupedByPatient = $records->groupBy('patient_id');

        $formattedData = $groupedByPatient->map(function (Collection $patientRecords) use ($problemTypeColumnsAr) {
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

        // Use FastExcel for proper Arabic RTL support
        $filename = "daily_transfers_report_{$fromDate}_to_{$toDate}.xlsx";
        
        // Prepare data for Excel export with proper headers
        $exportData = $formattedData->map(function ($patient) {
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

        // Create temp directory if it doesn't exist
        $tempDir = storage_path('app/public/temp');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        // Create unique filename to avoid conflicts
        $uniqueFilename = time() . '_' . $filename;
        $filePath = $tempDir . DIRECTORY_SEPARATOR . $uniqueFilename;
        
        // Use FastExcel to generate Excel file
        (new FastExcel($exportData))->export($filePath);
        
        // Generate public URL for the file
        $fileUrl = asset('storage/temp/' . $uniqueFilename);
        
        // Return JSON response with file URL
        return $this->sendResponse([
            'file_url' => $fileUrl,
            'filename' => $filename,
            'message' => 'تم إنشاء ملف التقرير بنجاح'
        ], 'تم جلب تقرير السجلات الطبية اليومي بنجاح');
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
        $query = MedicalRecord::where(function ($q) use ($user) {
            $q->where('created_by', $user->user_id)
                ->orWhere('last_modified_by', $user->user_id);
        });

        $options = [
            'statuses' => StaticData::where('type', 'status')
                ->whereIn('code', $query->distinct()->pluck('status_code'))
                ->select('code', 'label_en', 'label_ar')
                ->get(),

            'problem_types' => StaticData::where('type', 'problem_type')
                ->whereIn('code', $query->distinct()->pluck('problem_type_code'))
                ->select('code', 'label_en', 'label_ar')
                ->get(),

            'danger_levels' => StaticData::where('type', 'danger_level')
                ->whereIn('code', $query->distinct()->pluck('danger_level_code'))
                ->select('code', 'label_en', 'label_ar')
                ->get(),

            'transfer_statuses' => StaticData::where('type', 'transfer_status')
                ->whereIn('code', $query->distinct()->pluck('transfer_status_code'))
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
        $query = MedicalRecord::where(function ($q) use ($user) {
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

            'records_by_problem_type' => $query->selectRaw('problem_type_code, COUNT(*) as count')
                ->groupBy('problem_type_code')
                ->get(),

            'records_by_danger_level' => $query->selectRaw('danger_level_code, COUNT(*) as count')
                ->groupBy('danger_level_code')
                ->get(),

            'records_by_transfer_status' => $query->selectRaw('transfer_status_code, COUNT(*) as count')
                ->groupBy('transfer_status_code')
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
            'patient.healthCenter',
            'status',
            'problemType',
            'dangerLevel',

            'transferStatus',
            'creator',
            'lastModifier',
            'transfers.recipient',
            'transfers.sender'
        ]);

        // Apply the same filters as index method
        $query->where(function ($q) use ($user) {
            $q->where('created_by', $user->user_id)
                ->orWhere('last_modified_by', $user->user_id);
        });

        // Apply all filters (same logic as index method)
        $this->applyFilters($query, $request);

        // Get all records (no pagination for export)
        $records = $query->get();

        // Transform data for export with Arabic headers
        $exportData = $records->map(function ($record) {
            return [
                'رقم السجل' => $record->record_id,
                'اسم المريض' => $record->patient->full_name ?? 'غير محدد',
                'الرقم الوطني' => $record->patient->national_id ?? 'غير محدد',
                'المركز الصحي' => $record->patient->healthCenter->label_ar ?? $record->patient->healthCenter->label_en ?? 'غير محدد',
                'الحالة' => $record->status->label_ar ?? $record->status->label_en ?? 'غير محدد',
                'نوع المشكلة' => $record->problemType->label_ar ?? $record->problemType->label_en ?? 'غير محدد',
                'مستوى الخطورة' => $record->dangerLevel->label_ar ?? $record->dangerLevel->label_en ?? 'غير محدد',
                'تمت المراجعة من قبل' => $record->reviewed_party ?? 'غير محدد',
                'حالة التحويل' => $record->transferStatus->label_ar ?? $record->transferStatus->label_en ?? 'غير محدد',
                'أنشئ بواسطة' => $record->creator->full_name ?? 'غير محدد',
                'تاريخ الإنشاء' => $record->created_at->format('Y-m-d H:i:s'),
                'آخر تعديل بواسطة' => $record->lastModifier->full_name ?? 'غير محدد',
                'تاريخ آخر تعديل' => $record->updated_at->format('Y-m-d H:i:s'),
                'عدد التحويلات' => $record->transfers->count(),
                'ملاحظات التحويل' => $record->transfers->pluck('transfer_notes')->implode('; '),
            ];
        });

        // Create temp directory if it doesn't exist
        $tempDir = storage_path('app/public/temp');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        // Create unique filename to avoid conflicts
        $filename = 'medical_records_export_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
        $uniqueFilename = time() . '_' . $filename;
        $filePath = $tempDir . DIRECTORY_SEPARATOR . $uniqueFilename;
        
        // Use FastExcel to generate Excel file with Arabic RTL support
        (new FastExcel($exportData))->export($filePath);
        
        // Generate public URL for the file
        $fileUrl = asset('storage/temp/' . $uniqueFilename);
        
        // Return JSON response with file URL
        return $this->sendResponse([
            'file_url' => $fileUrl,
            'filename' => $filename,
            'message' => 'تم إنشاء ملف التصدير بنجاح'
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
            $query->whereHas('patient', function ($q) use ($request) {
                $q->where('full_name', 'like', '%' . $request->patient_name . '%');
            });
        }
        if ($request->filled('patient_national_id')) {
            $query->whereHas('patient', function ($q) use ($request) {
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
        if ($request->filled('problem_type_code')) {
            $query->withProblemType($request->problem_type_code);
        }
        if ($request->filled('danger_level_code')) {
            $query->withDangerLevel($request->danger_level_code);
        }
        if ($request->filled('reviewed_party_user_id')) {
            $query->reviewedBy($request->reviewed_party_user_id);
        }
        if ($request->filled('transfer_status_code')) {
            $query->withTransferStatus($request->transfer_status_code);
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
            $query->where(function ($q) use ($searchTerm) {
                $q->whereHas('patient', function ($patientQuery) use ($searchTerm) {
                    $patientQuery->where('full_name', 'like', '%' . $searchTerm . '%')
                        ->orWhere('national_id', 'like', '%' . $searchTerm . '%');
                })
                    ->orWhereHas('transfers', function ($transferQuery) use ($searchTerm) {
                        $transferQuery->where('transfer_notes', 'like', '%' . $searchTerm . '%');
                    });
            });
        }
    }
}
