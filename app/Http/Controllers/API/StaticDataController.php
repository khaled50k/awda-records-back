<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\StaticData;
use Illuminate\Support\Facades\DB;

class StaticDataController extends BaseController
{
    /**
     * Display a listing of static data with filtering
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', StaticData::class);

        $user = auth()->user();
        $query = StaticData::query();

        // Admin sees all static data, others see only active data
        if (!$user->isAdmin()) {
            $query->active();
        }

        // ===== FILTERS =====
        // Filter by type
        if ($request->filled('type')) {
            $query->ofType($request->type);
        }

        // Filter by multiple types
        if ($request->filled('types')) {
            $types = is_array($request->types) ? $request->types : explode(',', $request->types);
            $query->whereIn('type', $types);
        }

        // Filter by code (partial match)
        if ($request->filled('code')) {
            $query->where('code', 'like', '%' . $request->code . '%');
        }

        // Filter by label (search in both English and Arabic)
        if ($request->filled('label')) {
            $label = $request->label;
            $query->where(function($q) use ($label) {
                $q->where('label_en', 'like', '%' . $label . '%')
                  ->orWhere('label_ar', 'like', '%' . $label . '%');
            });
        }

        // Filter by language-specific label
        if ($request->filled('label_en')) {
            $query->where('label_en', 'like', '%' . $request->label_en . '%');
        }

        if ($request->filled('label_ar')) {
            $query->where('label_ar', 'like', '%' . $request->label_ar . '%');
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $isActive = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }

        // Filter by description
        if ($request->filled('description')) {
            $query->where('description', 'like', '%' . $request->description . '%');
        }

        // ===== SORTING =====
        $sortBy = $request->get('sort_by', 'type');
        $sortOrder = $request->get('sort_order', 'asc');
        
        $allowedSortFields = ['type', 'code', 'label_en', 'label_ar', 'is_active', 'created_at', 'updated_at'];
        
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('type', 'asc')->orderBy('code', 'asc');
        }

        // Filter to show only today's static data for fresh data
        // This ensures users always see the most recent and relevant data
        
        // ===== PAGINATION =====
        $perPage = $request->get('per_page', 20);
        $perPage = min(max($perPage, 1), 100);
        
        $staticData = $query->paginate($perPage);
        
        // Add filter summary to response
        $filters = $request->only([
            'type', 'types', 'code', 'label', 'label_en', 'label_ar', 
            'is_active', 'description', 'sort_by', 'sort_order'
        ]);
        
        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });
        
        $response = [
            'data' => $staticData->items(),
            'pagination' => [
                'current_page' => $staticData->currentPage(),
                'last_page' => $staticData->lastPage(),
                'per_page' => $staticData->perPage(),
                'total' => $staticData->total(),
                'from' => $staticData->firstItem(),
                'to' => $staticData->lastItem(),
            ],
            'filters_applied' => $filters,
            'total_filtered' => $staticData->total()
        ];
        
        return $this->sendResponse($response, 'تم جلب قائمة البيانات الثابتة بنجاح');
    }

    /**
     * Display the specified static data
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $staticData = StaticData::findOrFail($id);
        $this->authorize('view', $staticData);
        
        return $this->sendResponse($staticData, 'تم جلب البيانات الثابتة بنجاح');
    }

    /**
     * Store a newly created static data
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $this->authorize('create', StaticData::class);

        $validator = Validator::make($request->all(), [
            'type' => 'required|string|max:50',
            'code' => 'required|string|max:50|unique:static_data,code',
            'label_en' => 'required|string|max:255',
            'label_ar' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'metadata' => 'nullable|array',
        ], [
            'type.required' => 'النوع مطلوب',
            'type.string' => 'النوع يجب أن يكون نصاً',
            'type.max' => 'النوع يجب ألا يتجاوز 50 حرفاً',
            'code.required' => 'الرمز مطلوب',
            'code.string' => 'الرمز يجب أن يكون نصاً',
            'code.max' => 'الرمز يجب ألا يتجاوز 50 حرفاً',
            'code.unique' => 'الرمز مستخدم بالفعل',
            'label_en.required' => 'التسمية الإنجليزية مطلوبة',
            'label_en.string' => 'التسمية الإنجليزية يجب أن تكون نصاً',
            'label_en.max' => 'التسمية الإنجليزية يجب ألا تتجاوز 255 حرفاً',
            'label_ar.required' => 'التسمية العربية مطلوبة',
            'label_ar.string' => 'التسمية العربية يجب أن تكون نصاً',
            'label_ar.max' => 'التسمية العربية يجب ألا تتجاوز 255 حرفاً',
            'description.string' => 'الوصف يجب أن يكون نصاً',
            'description.max' => 'الوصف يجب ألا يتجاوز 1000 حرف',
            'is_active.boolean' => 'حالة النشاط يجب أن تكون صحيحة أو خاطئة',
            'metadata.array' => 'البيانات الوصفية يجب أن تكون مصفوفة',
        ]);

        if ($validator->fails()) {
            return $this->sendError('بيانات غير صحيحة', $validator->errors(), 422);
        }

        // Check if code already exists for this type
        $existingCode = StaticData::where('type', $request->type)
            ->where('code', $request->code)
            ->exists();
            
        if ($existingCode) {
            return $this->sendError('الكود موجود بالفعل لهذا النوع', [], 422);
        }

        // Use transaction for critical operations
        try {
            DB::beginTransaction();

            $staticData = StaticData::create([
                'type' => $request->type,
                'code' => $request->code,
                'label_en' => $request->label_en,
                'label_ar' => $request->label_ar,
                'description' => $request->description,
                'is_active' => $request->get('is_active', true),
                'metadata' => $request->metadata ?? [],
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('حدث خطأ أثناء إنشاء البيانات الثابتة', [], 500);
        }

        return $this->sendResponse(
            ['static_data' => $staticData],
            'تم إنشاء البيانات الثابتة بنجاح',
            201
        );
    }

    /**
     * Update the specified static data
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $staticData = StaticData::findOrFail($id);
        $this->authorize('update', $staticData);

        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|required|string|max:50',
            'code' => 'sometimes|required|string|max:50|unique:static_data,code,' . $id,
            'label_en' => 'sometimes|required|string|max:255',
            'label_ar' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'metadata' => 'nullable|array',
        ], [
            'type.required' => 'النوع مطلوب',
            'type.string' => 'النوع يجب أن يكون نصاً',
            'type.max' => 'النوع يجب ألا يتجاوز 50 حرفاً',
            'code.required' => 'الرمز مطلوب',
            'code.string' => 'الرمز يجب أن يكون نصاً',
            'code.max' => 'الرمز يجب ألا يتجاوز 50 حرفاً',
            'code.unique' => 'الرمز مستخدم بالفعل',
            'label_en.required' => 'التسمية الإنجليزية مطلوبة',
            'label_en.string' => 'التسمية الإنجليزية يجب أن تكون نصاً',
            'label_en.max' => 'التسمية الإنجليزية يجب ألا تتجاوز 255 حرفاً',
            'label_ar.required' => 'التسمية العربية مطلوبة',
            'label_ar.string' => 'التسمية العربية يجب أن تكون نصاً',
            'label_ar.max' => 'التسمية العربية يجب ألا تتجاوز 255 حرفاً',
            'description.string' => 'الوصف يجب أن يكون نصاً',
            'description.max' => 'الوصف يجب ألا يتجاوز 1000 حرف',
            'is_active.boolean' => 'حالة النشاط يجب أن تكون صحيحة أو خاطئة',
            'metadata.array' => 'البيانات الوصفية يجب أن تكون مصفوفة',
        ]);

        if ($validator->fails()) {
            return $this->sendError('بيانات غير صحيحة', $validator->errors(), 422);
        }

        // Check if code already exists for this type (excluding current record)
        if ($request->has('type') && $request->has('code')) {
            $existingCode = StaticData::where('type', $request->type)
                ->where('code', $request->code)
                ->where('id', '!=', $id)
                ->exists();
                
            if ($existingCode) {
                return $this->sendError('الكود موجود بالفعل لهذا النوع', [], 422);
            }
        }

        // Use transaction for critical operations
        try {
            DB::beginTransaction();

            // Update fields
            if ($request->has('type')) {
                $staticData->type = $request->type;
            }
            if ($request->has('code')) {
                $staticData->code = $request->code;
            }
            if ($request->has('label_en')) {
                $staticData->label_en = $request->label_en;
            }
            if ($request->has('label_ar')) {
                $staticData->label_ar = $request->label_ar;
            }
            if ($request->has('description')) {
                $staticData->description = $request->description;
            }
            if ($request->has('is_active')) {
                $staticData->is_active = $request->is_active;
            }
            if ($request->has('metadata')) {
                $staticData->metadata = $request->metadata;
            }

            $staticData->save();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('حدث خطأ أثناء تحديث البيانات الثابتة', [], 500);
        }

        return $this->sendResponse(
            ['static_data' => $staticData],
            'تم تحديث البيانات الثابتة بنجاح'
        );
    }

    /**
     * Remove the specified static data
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $staticData = StaticData::findOrFail($id);
        $this->authorize('delete', $staticData);

        // Check if this static data is being used by other models
        $usageChecks = [
            'users' => $staticData->users()->exists(),
            'patients' => $staticData->patients()->exists(),
            'healthCenterPatients' => $staticData->healthCenterPatients()->exists(),
            'statusMedicalRecords' => $staticData->statusMedicalRecords()->exists(),
            'actionAuditLogs' => $staticData->actionAuditLogs()->exists(),
            'accessTypeLogs' => $staticData->accessTypeLogs()->exists(),
            'workflowStepStatuses' => $staticData->workflowStepStatuses()->exists(),
        ];

        $usedIn = array_filter($usageChecks);
        
        if (!empty($usedIn)) {
            $usedInKeys = array_keys($usedIn);
            return $this->sendError(
                'لا يمكن حذف هذه البيانات لاستخدامها في: ' . implode(', ', $usedInKeys),
                ['used_in' => $usedInKeys],
                422
            );
        }

        // Use transaction for critical operations
        try {
            DB::beginTransaction();

            $staticData->delete();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('حدث خطأ أثناء حذف البيانات الثابتة', [], 500);
        }

        return $this->sendResponse([], 'تم حذف البيانات الثابتة بنجاح');
    }

    /**
     * Get all unique types
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTypes()
    {
        $types = StaticData::distinct()
            ->pluck('type')
            ->sort()
            ->values();
        
        return $this->sendResponse($types, 'تم جلب أنواع البيانات الثابتة بنجاح');
    }

    /**
     * Get static data by type
     *
     * @param string $type
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByType($type)
    {
        $staticData = StaticData::ofType($type)
            ->active()
            ->orderBy('code')
            ->get();
        
        return $this->sendResponse($staticData, 'تم جلب البيانات الثابتة حسب النوع بنجاح');
    }

    /**
     * Get static data by type and code
     *
     * @param string $type
     * @param string $code
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByCode($type, $code)
    {
        $staticData = StaticData::ofType($type)
            ->withCode($code)
            ->first();
        
        if (!$staticData) {
            return $this->sendError('لم يتم العثور على البيانات الثابتة', [], 404);
        }
        
        return $this->sendResponse($staticData, 'تم جلب البيانات الثابتة بنجاح');
    }

    /**
     * Toggle active status
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleStatus($id)
    {
        $staticData = StaticData::findOrFail($id);
        $this->authorize('update', $staticData);

        $staticData->is_active = !$staticData->is_active;
        $staticData->save();

        return $this->sendResponse(
            ['static_data' => $staticData],
            'تم تغيير حالة البيانات الثابتة بنجاح'
        );
    }

    /**
     * Bulk update active status
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUpdateStatus(Request $request)
    {
        $this->authorize('update', StaticData::class);

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:static_data,id',
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('بيانات غير صحيحة', $validator->errors(), 422);
        }

        $updated = StaticData::whereIn('id', $request->ids)
            ->update(['is_active' => $request->is_active]);

        return $this->sendResponse(
            ['updated_count' => $updated],
            'تم تحديث حالة ' . $updated . ' من البيانات الثابتة بنجاح'
        );
    }
}
