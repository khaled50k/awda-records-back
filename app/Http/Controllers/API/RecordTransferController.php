<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\RecordTransfer;
use App\Models\MedicalRecord;
use App\Models\User;

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
        $query = RecordTransfer::with(['medicalRecord.patient', 'sender', 'recipient','medicalRecord.healthCenter','medicalRecord.problemType','medicalRecord.status']);

        // Admin sees all transfers, others only see transfers where they are the recipient
        if (!$user->isAdmin()) {
            $query->where('recipient_id', $user->user_id);
        }

        // Filter by record
        if ($request->has('record_id')) {
            $query->forRecord($request->record_id);
        }



        $transfers = $query->paginate(15);
        
        // Add is_replied field to each transfer to show if user has responded to this specific transfer
        $transfers->getCollection()->transform(function ($transfer) use ($user) {
            // Check if the current user has sent a transfer AFTER this specific transfer
            $hasUserResponded = RecordTransfer::where('record_id', $transfer->record_id)
                ->where('sender_id', $user->user_id)
                ->where('created_at', '>', $transfer->created_at)
                ->exists();
            
            $transfer->is_replied = $hasUserResponded;
            return $transfer;
        });
        
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
            'medicalRecord.patient', 'sender', 'recipient', 'medicalRecord.healthCenter','medicalRecord.problemType','medicalRecord.status'
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
            'recipient_id' => 'required|integer|exists:users,user_id',
            'transfer_notes' => 'nullable|string',
            'status_code' => 'nullable|string', // Make status code optional
        ]);

        if ($validator->fails()) {
            return $this->sendError('بيانات غير صحيحة', $validator->errors(), 422);
        }

        // Verify record exists
        $record = MedicalRecord::find($request->record_id);
        if (!$record) {
            return $this->sendError('لم يتم العثور على السجل الطبي', [], 404);
        }

        // Verify recipient exists and is not the same as sender
        $recipient = User::find($request->recipient_id);
        if (!$recipient) {
            return $this->sendError('لم يتم العثور على المستلم', [], 404);
        }

        if ($recipient->user_id === auth()->user()->user_id) {
            return $this->sendError('لا يمكن إرسال السجل لنفسك', [], 422);
        }

   

        // Update record status if status code is provided
        if ($request->has('status_code') && $request->status_code) {
            $record->update(['status_code' => $request->status_code]);
        }

        $transfer = RecordTransfer::create([
            'record_id' => $request->record_id,
            'sender_id' => auth()->user()->user_id,
            'recipient_id' => $request->recipient_id,
            'transfer_notes' => $request->transfer_notes,
        ]);

        // Load relationships for notification
        $transfer->load(['medicalRecord.patient', 'medicalRecord.problemType', 'medicalRecord.status', 'sender']);

        // Real-time broadcasting removed

        return $this->sendResponse(
            ['transfer' => $transfer->load(['medicalRecord.patient', 'sender', 'recipient'])],
            'تم إنشاء عملية النقل بنجاح',
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

        // Update fields
        if ($request->has('transfer_notes')) {
            $transfer->transfer_notes = $request->transfer_notes;
        }

        $transfer->save();

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

        $transfer->delete();

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

        return $this->sendResponse(
            ['transfer' => $transfer->load(['medicalRecord.patient', 'sender', 'recipient'])],
            'تم إكمال العملية بنجاح'
        );
    }
}
