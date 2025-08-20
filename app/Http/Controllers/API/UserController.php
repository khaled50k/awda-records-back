<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\StaticData;
use Illuminate\Support\Facades\DB;

class UserController extends BaseController
{
    /**
     * Display a listing of users (Admin only)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        // Filter to show only today's users for fresh data
        // This ensures users always see the most recent and relevant data
        $users = User::with('role')
            ->paginate(15);
    
        return $this->sendResponse($users, 'تم جلب قائمة المستخدمين بنجاح');
    }

    /**
     * Display the specified user (Admin only)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $user = User::with('role','reviewedMedicalRecords')->findOrFail($id);
        $this->authorize('view', $user);
        
        return $this->sendResponse($user, 'تم جلب بيانات المستخدم بنجاح');
    }

    /**
     * Store a newly created user (Admin only)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $this->authorize('create', User::class);

        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:50|unique:users',
            'email' => 'nullable|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'full_name' => 'required|string|max:100',
            'role_code' => 'required|string|exists:static_data,code',
            'is_active' => 'boolean',
        ], [
            'username.required' => 'اسم المستخدم مطلوب',
            'username.string' => 'اسم المستخدم يجب أن يكون نصاً',
            'username.max' => 'اسم المستخدم يجب ألا يتجاوز 50 حرفاً',
            'username.unique' => 'اسم المستخدم مستخدم بالفعل',
            'email.string' => 'البريد الإلكتروني يجب أن يكون نصاً',
            'email.email' => 'البريد الإلكتروني يجب أن يكون صحيحاً',
            'email.max' => 'البريد الإلكتروني يجب ألا يتجاوز 255 حرفاً',
            'email.unique' => 'البريد الإلكتروني مستخدم بالفعل',
            'password.required' => 'كلمة المرور مطلوبة',
            'password.string' => 'كلمة المرور يجب أن تكون نصاً',
            'password.min' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل',
            'full_name.required' => 'الاسم الكامل مطلوب',
            'full_name.string' => 'الاسم الكامل يجب أن يكون نصاً',
            'full_name.max' => 'الاسم الكامل يجب ألا يتجاوز 100 حرف',
            'role_code.required' => 'نوع المستخدم مطلوب',
            'role_code.string' => 'نوع المستخدم يجب أن يكون نصاً',
            'role_code.exists' => 'نوع المستخدم غير موجود',
            'is_active.boolean' => 'حالة النشاط يجب أن تكون صحيحة أو خاطئة',
        ]);

        if ($validator->fails()) {
            return $this->sendError('بيانات غير صحيحة', $validator->errors(), 422);
        }

        // Verify role exists
        $role = StaticData::where('type', 'role')->where('code', $request->role_code)->first();
        if (!$role) {
            return $this->sendError('نوع المستخدم غير صحيح', [], 422);
        }

        // Use transaction for critical operations
        try {
            DB::beginTransaction();

            $user = User::create([
                'username' => $request->username,
                'email' => $request->email ?? null,
                'password_hash' => Hash::make($request->password),
                'full_name' => $request->full_name,
                'role_code' => $request->role_code,
                'is_active' => $request->get('is_active', true),
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('حدث خطأ أثناء إنشاء المستخدم', [], 500);
        }

        return $this->sendResponse(
            ['user' => $user->load('role')],
            'تم إنشاء المستخدم بنجاح',
            201
        );
    }

    /**
     * Update the specified user (Admin only)
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $this->authorize('update', $user);

        $validator = Validator::make($request->all(), [
            'username' => 'sometimes|string|max:50|unique:users,username,' . $id . ',user_id',
            'email' => 'nullable|string|email|max:255|unique:users,email,' . $id . ',user_id',
            'password' => 'filled|string|min:8',
            'full_name' => 'sometimes|string|max:100',
            'role_code' => 'sometimes|string|exists:static_data,code',
            'is_active' => 'sometimes|boolean',
        ], [
            'username.string' => 'اسم المستخدم يجب أن يكون نصاً',
            'username.max' => 'اسم المستخدم يجب ألا يتجاوز 50 حرفاً',
            'username.unique' => 'اسم المستخدم مستخدم بالفعل',
            'email.string' => 'البريد الإلكتروني يجب أن يكون نصاً',
            'email.email' => 'البريد الإلكتروني يجب أن يكون صحيحاً',
            'email.max' => 'البريد الإلكتروني يجب ألا يتجاوز 255 حرفاً',
            'email.unique' => 'البريد الإلكتروني مستخدم بالفعل',
            'password.string' => 'كلمة المرور يجب أن تكون نصاً',
            'password.min' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل',
            'full_name.string' => 'الاسم الكامل يجب أن يكون نصاً',
            'full_name.max' => 'الاسم الكامل يجب ألا يتجاوز 100 حرف',
            'role_code.string' => 'نوع المستخدم يجب أن يكون نصاً',
            'role_code.exists' => 'نوع المستخدم غير موجود',
            'is_active.boolean' => 'حالة النشاط يجب أن تكون صحيحة أو خاطئة',
        ]);

        if ($validator->fails()) {
            return $this->sendError('بيانات غير صحيحة', $validator->errors(), 422);
        }

        // Use transaction for critical operations
        try {
            DB::beginTransaction();

            // Update fields
            if ($request->has('username')) $user->username = $request->username;
            if ($request->has('email')) $user->email = $request->email ?? null;
            if ($request->has('password')) $user->password_hash = Hash::make($request->password);
            if ($request->has('full_name')) $user->full_name = $request->full_name;
            if ($request->has('role_code')) $user->role_code = $request->role_code;
            if ($request->has('is_active')) $user->is_active = $request->is_active;

            $user->save();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('حدث خطأ أثناء تحديث بيانات المستخدم', [], 500);
        }

        return $this->sendResponse(
            ['user' => $user->load('role')],
            'تم تحديث بيانات المستخدم بنجاح'
        );
    }

    /**
     * Remove the specified user (Admin only)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $this->authorize('delete', $user);

        // Check if user has related records
        if ($user->createdMedicalRecords()->exists() || 
            $user->lastModifiedMedicalRecords()->exists() ||
            $user->sentTransfers()->exists() ||
            $user->receivedTransfers()->exists()) {
            return $this->sendError('لا يمكن حذف المستخدم لوجود سجلات مرتبطة به', [], 422);
        }

        // Use transaction for critical operations
        try {
            DB::beginTransaction();

            $user->delete();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('حدث خطأ أثناء حذف المستخدم', [], 500);
        }

        return $this->sendResponse([], 'تم حذف المستخدم بنجاح');
    }
}
