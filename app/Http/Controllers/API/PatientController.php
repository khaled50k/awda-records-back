<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Patient;
use App\Models\StaticData;

class PatientController extends BaseController
{
    /**
     * Display a listing of patients
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Patient::class);

        $query = Patient::with(['gender', 'healthCenter']);

        // Search by name
        if ($request->has('search')) {
            $query->searchByName($request->search);
        }
    

        // Filter by gender
        if ($request->has('gender_code')) {
            $query->withGender($request->gender_code);
        }

        // Filter by health center
        if ($request->has('health_center_code')) {
            $query->fromHealthCenter($request->health_center_code);
        }

        $patients = $query->paginate(15);
        
        return $this->sendResponse($patients, 'تم جلب قائمة المرضى بنجاح');
    }

    /**
     * Display the specified patient
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $patient = Patient::with(['gender', 'healthCenter', 'medicalRecords.transfers.sender', 'medicalRecords.transfers.recipient', 'medicalRecords.status', 'medicalRecords.problemType', 'medicalRecords.creator', 'medicalRecords.lastModifiedBy'])->findOrFail($id);
        $this->authorize('view', $patient);
        
        return $this->sendResponse($patient, 'تم جلب بيانات المريض بنجاح');
    }

    /**
     * Store a newly created patient
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $this->authorize('create', Patient::class);

        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:100',
            'national_id' => 'required|integer|unique:patients',
            'gender_code' => 'required|string|exists:static_data,code',
            'health_center_code' => 'nullable|string|exists:static_data,code',
        ]);

        if ($validator->fails()) {
            return $this->sendError('بيانات غير صحيحة', $validator->errors(), 422);
        }

        // Verify gender exists
        $gender = StaticData::where('type', 'gender')->where('code', $request->gender_code)->first();
        if (!$gender) {
            return $this->sendError('نوع الجنس غير صحيح', [], 422);
        }

  
        $patient = Patient::create([
            'full_name' => $request->full_name,
            'national_id' => $request->national_id,
            'gender_code' => $request->gender_code,
            'health_center_code' => $request->health_center_code,
        ]);

        return $this->sendResponse(
            ['patient' => $patient->load(['gender', 'healthCenter'])],
            'تم إنشاء المريض بنجاح',
            201
        );
    }

    /**
     * Update the specified patient
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $patient = Patient::findOrFail($id);
        $this->authorize('update', $patient);

        $validator = Validator::make($request->all(), [
            'full_name' => 'sometimes|string|max:100',
            'national_id' => 'sometimes|integer|unique:patients,national_id,' . $id . ',patient_id',
            'gender_code' => 'sometimes|string|exists:static_data,code',
            'health_center_code' => 'sometimes|string|exists:static_data,code',
        ]);

        if ($validator->fails()) {
            return $this->sendError('بيانات غير صحيحة', $validator->errors(), 422);
        }

        // Update fields
        if ($request->has('full_name')) $patient->full_name = $request->full_name;
        if ($request->has('national_id')) $patient->national_id = $request->national_id;
        if ($request->has('gender_code')) $patient->gender_code = $request->gender_code;
        if ($request->has('health_center_code')) $patient->health_center_code = $request->health_center_code;

        $patient->save();

        return $this->sendResponse(
            ['patient' => $patient->load(['gender', 'healthCenter'])],
            'تم تحديث بيانات المريض بنجاح'
        );
    }

    /**
     * Remove the specified patient (Admin only)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $patient = Patient::findOrFail($id);
        $this->authorize('delete', $patient);

        // Check if patient has related records
        if ($patient->medicalRecords()->exists() || $patient->accessLogs()->exists()) {
            return $this->sendError('لا يمكن حذف المريض لوجود سجلات طبية مرتبطة به', [], 422);
        }

        $patient->delete();

        return $this->sendResponse([], 'تم حذف المريض بنجاح');
    }
}
