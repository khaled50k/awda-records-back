<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\RecordTransfer;
use App\Models\MedicalRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RecordTransferController extends BaseController
{
    /**
     * Display a listing of record transfers
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', RecordTransfer::class);

        $user = auth()->user();
        $query = RecordTransfer::with([
            'medicalRecord.patient.healthCenter', 
            'sender', 
            'recipient',
            'medicalRecord.problemType',
            'medicalRecord.status',
            'medicalRecord.transferStatus'
        ]);

        // Admin sees all transfers, others only see transfers where they are the recipient
        if (!$user->isAdmin()) {
            $query->where('recipient_id', $user->user_id);
            // ->orWhere('sender_id', $user->user_id);
        }

        // Filter by record
        if ($request->has('record_id')) {
            $query->forRecord($request->record_id);
        }

        // Filter to show only today's transfers for fresh data
        // This ensures users always see the most recent and relevant data
        $query->whereDate('created_at', today());
        $query->orderBy('created_at', 'desc');

        // ===== PAGINATION =====
        $perPage = $request->get('per_page', 100);
        $perPage = min(max($perPage, 1), 100); // Limit between 1 and 100

        // Execute the query with pagination
        $transfers = $query->paginate($perPage);
        
        return $this->sendResponse($transfers, 'تم جلب قائمة عمليات النقل المرسلة إليك بنجاح');
    }

    /**
     * Display the specified record transfer
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $transfer = RecordTransfer::with([
            'medicalRecord.patient.healthCenter', 
            'sender', 
            'recipient',
            'medicalRecord.problemType',
            'medicalRecord.status',
            'medicalRecord.transferStatus'
        ])->findOrFail($id);
        
        $this->authorize('view', $transfer);
        
        return $this->sendResponse($transfer, 'تم جلب تفاصيل النقل بنجاح');
    }

      /**
     * Store a newly created record transfer
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $this->authorize('create', RecordTransfer::class);

        $validator = Validator::make($request->all(), [
            'record_id' => 'required|integer|exists:medical_records,record_id',
            'recipient_ids' => 'nullable|array',
            'recipient_ids.*' => 'integer|exists:users,user_id',
            'transfer_notes' => 'required|string',
            'transfer_status_code' => 'required|string|exists:static_data,code', // Transfer status for the medical record
        ], [
            'record_id.required' => 'معرف السجل الطبي مطلوب',
            'record_id.integer' => 'معرف السجل الطبي يجب أن يكون رقماً صحيحاً',
            'record_id.exists' => 'معرف السجل الطبي غير موجود',
            'recipient_ids.array' => 'معرفات المستلمين يجب أن تكون مصفوفة',
            'recipient_ids.*.integer' => 'معرف المستلم يجب أن يكون رقماً صحيحاً',
            'recipient_ids.*.exists' => 'معرف المستلم غير موجود',
            'transfer_notes.required' => 'ملاحظات النقل مطلوبة',
            'transfer_notes.string' => 'ملاحظات النقل يجب أن تكون نصاً',
            'transfer_status_code.string' => 'رمز حالة النقل يجب أن يكون نصاً',
            'transfer_status_code.exists' => 'رمز حالة النقل غير موجود',
        ]);

        if ($validator->fails()) {
            return $this->sendError('بيانات غير صحيحة', $validator->errors(), 422);
        }

        // Verify record exists
        $record = MedicalRecord::find($request->record_id);
        if (!$record) {
            return $this->sendError('لم يتم العثور على السجل الطبي', [], 404);
        }



   

        // Use transaction for critical operations
        try {
            DB::beginTransaction();

            // Update medical record transfer status if provided
            if ($request->has('transfer_status_code') && $request->transfer_status_code) {
                $record->update(['transfer_status_code' => $request->transfer_status_code]);
            }

            $transfers = [];
            $recipientIds = $request->recipient_ids ?? [];

            // If no recipients provided, create one transfer without recipient
            if (empty($recipientIds)) {
                $transfers[] = RecordTransfer::create([
                    'record_id' => $request->record_id,
                    'sender_id' => auth()->user()->user_id,
                    'recipient_id' => null,
                    'transfer_notes' => $request->transfer_notes,
                ]);
            } else {
                // Create transfer for each recipient
                foreach ($recipientIds as $recipientId) {
                    $transfers[] = RecordTransfer::create([
                        'record_id' => $request->record_id,
                        'sender_id' => auth()->user()->user_id,
                        'recipient_id' => $recipientId,
                        'transfer_notes' => $request->transfer_notes,
                    ]);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('حدث خطأ أثناء إنشاء عملية النقل', [], 500);
        }

        // Get recipient users for notifications
        $recipientIds = $request->recipient_ids ?? [];
        
        // notification to all admins if no recipients are provided
        if (empty($recipientIds)) {
            $admins = User::where('role_code', 'admin')->get();
            foreach ($admins as $admin) {
                foreach ($transfers as $transfer) {
                    // event(new \App\Events\TransferCreated($transfer, auth()->user(), $admin));
                }
            }
        } else {
            // notification to each recipient
            foreach ($recipientIds as $recipientId) {
                $recipient = User::find($recipientId);
                if ($recipient) {
                    // Find the transfer for this specific recipient
                    $recipientTransfer = collect($transfers)->first(function($transfer) use ($recipientId) {
                        return $transfer->recipient_id == $recipientId;
                    });
                    
                    if ($recipientTransfer) {
                        // event(new \App\Events\TransferCreated($recipientTransfer, auth()->user(), $recipient));
                        // event(new \App\Events\TransferReceived($recipientTransfer, auth()->user(), $recipient));
                    }
                }
            }
        }

        // Load relationships for all transfers
        foreach ($transfers as $transfer) {
            $transfer->load(['medicalRecord.patient', 'medicalRecord.problemType', 'medicalRecord.status', 'sender', 'recipient']);
        }

        return $this->sendResponse(
            [
                'transfers' => $transfers,
                'total_transfers' => count($transfers),
                'recipients_count' => count($recipientIds)
            ],
            'تم إنشاء عمليات النقل بنجاح',
            201
        );
    }

    /**
     * Update the specified record transfer
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $transfer = RecordTransfer::findOrFail($id);
        $this->authorize('update', $transfer);

        $validator = Validator::make($request->all(), [
            'transfer_notes' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('بيانات غير صحيحة', $validator->errors(), 422);
        }

        // Use transaction for critical operations
        try {
            DB::beginTransaction();

            // Update fields
            if ($request->has('transfer_notes')) {
                $transfer->transfer_notes = $request->transfer_notes;
            }

            $transfer->save();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('حدث خطأ أثناء تحديث عملية النقل', [], 500);
        }

        return $this->sendResponse(
            ['transfer' => $transfer->load(['medicalRecord.patient', 'sender', 'recipient'])],
            'تم تحديث عملية النقل بنجاح'
        );
    }

    /**
     * Remove the specified record transfer (Admin only)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $transfer = RecordTransfer::findOrFail($id);
        $this->authorize('delete', $transfer);

        // Check if transfer has workflow steps
        if ($transfer->workflowSteps()->exists()) {
            return $this->sendError('لا يمكن حذف عملية النقل لوجود خطوات عمل مرتبطة بها', [], 422);
        }

        // Use transaction for critical operations
        try {
            DB::beginTransaction();

            $transfer->delete();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('حدث خطأ أثناء حذف عملية النقل', [], 500);
        }

        return $this->sendResponse([], 'تم حذف عملية النقل بنجاح');
    }

    /**
     * Mark transfer as received
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function receive($id)
    {
        $transfer = RecordTransfer::findOrFail($id);
        $this->authorize('receive', $transfer);

        // Use transaction for critical operations
        try {
            DB::beginTransaction();

            // Update the medical record transfer status to 'received'
            $transfer->medicalRecord->update(['transfer_status_code' => 'received']);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('حدث خطأ أثناء استلام السجل', [], 500);
        }

        return $this->sendResponse(
            ['transfer' => $transfer->load(['medicalRecord.patient', 'sender', 'recipient'])],
            'تم استلام السجل بنجاح'
        );
    }

    /**
     * Mark transfer as completed
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function complete($id)
    {
        $transfer = RecordTransfer::findOrFail($id);
        $this->authorize('complete', $transfer);

        // Use transaction for critical operations
        try {
            DB::beginTransaction();

            // Update the medical record transfer status to 'completed'
            $transfer->medicalRecord->update(['transfer_status_code' => 'completed']);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('حدث خطأ أثناء إكمال العملية', [], 500);
        }

        return $this->sendResponse(
            ['transfer' => $transfer->load(['medicalRecord.patient', 'sender', 'recipient'])],
            'تم إكمال العملية بنجاح'
        );
    }
}
