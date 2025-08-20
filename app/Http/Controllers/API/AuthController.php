<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\StaticData;
use Illuminate\Support\Facades\DB;

class AuthController extends BaseController
{
    /**
     * Register a new user (Admin only)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        // Check if user is admin for registration
        if (Auth::check()) {
            $this->authorize('create', User::class);
        }

        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:50|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'full_name' => 'required|string|max:100',
            'role_code' => 'required|string|exists:static_data,code',
        ], [
            'username.required' => 'اسم المستخدم مطلوب',
            'username.string' => 'اسم المستخدم يجب أن يكون نصاً',
            'username.max' => 'اسم المستخدم يجب ألا يتجاوز 50 حرفاً',
            'username.unique' => 'اسم المستخدم مستخدم بالفعل',
            'email.required' => 'البريد الإلكتروني مطلوب',
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
                'email' => $request->email,
                'password_hash' => Hash::make($request->password),
                'full_name' => $request->full_name,
                'role_code' => $request->role_code,
                'is_active' => true,
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
     * Login user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
        ], [
            'username.required' => 'اسم المستخدم مطلوب',
            'username.string' => 'اسم المستخدم يجب أن يكون نصاً',
            'password.required' => 'كلمة المرور مطلوبة',
            'password.string' => 'كلمة المرور يجب أن تكون نصاً',
        ]);

        if ($validator->fails()) {
            return $this->sendError('بيانات غير صحيحة', $validator->errors(), 422);
        }

        $credentials = $request->only('username', 'password');
        
        // Check if user exists and is active
        $user = User::where('username', $credentials['username'])->first();
        
        if (!$user || !$user->is_active) {
            return $this->sendError('اسم المستخدم أو كلمة المرور غير صحيحة', [], 401);
        }

        // Verify password
        if (!Hash::check($credentials['password'], $user->password_hash)) {
            return $this->sendError('اسم المستخدم أو كلمة المرور غير صحيحة', [], 401);
        }

        // Create token
        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->sendResponse([
            'user' => $user->load('role'),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'تم تسجيل الدخول بنجاح');
    }

    /**
     * Logout user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        
        return $this->sendResponse([], 'تم تسجيل الخروج بنجاح');
    }

    /**
     * Get authenticated user profile
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function profile(Request $request)
    {
        $user = $request->user()->load('role');
        
        return $this->sendResponse([
            'user' => $user
        ], 'تم جلب بيانات المستخدم بنجاح');
    }

    /**
     * Update authenticated user profile
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'full_name' => 'sometimes|string|max:100',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->user_id . ',user_id',
            'password' => 'filled|string|min:8',
        ], [
            'full_name.string' => 'الاسم الكامل يجب أن يكون نصاً',
            'full_name.max' => 'الاسم الكامل يجب ألا يتجاوز 100 حرف',
            'email.string' => 'البريد الإلكتروني يجب أن يكون نصاً',
            'email.email' => 'البريد الإلكتروني يجب أن يكون صحيحاً',
            'email.max' => 'البريد الإلكتروني يجب ألا يتجاوز 255 حرفاً',
            'email.unique' => 'البريد الإلكتروني مستخدم بالفعل',
            'password.string' => 'كلمة المرور يجب أن تكون نصاً',
            'password.min' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل',
        ]);

        if ($validator->fails()) {
            return $this->sendError('بيانات غير صحيحة', $validator->errors(), 422);
        }

        $updateData = [];
        
        if ($request->has('full_name')) {
            $updateData['full_name'] = $request->full_name;
        }
        
        if ($request->has('email')) {
            $updateData['email'] = $request->email;
        }
        
        if ($request->has('password')) {
            $updateData['password_hash'] = Hash::make($request->password);
        }

        if (empty($updateData)) {
            return $this->sendError('لم يتم توفير بيانات للتحديث', [], 400);
        }

        // Use transaction for critical operations
        try {
            DB::beginTransaction();

            $user->update($updateData);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('حدث خطأ أثناء تحديث بيانات المستخدم', [], 500);
        }
        
        return $this->sendResponse([
            'user' => $user->fresh()->load('role')
        ], 'تم تحديث بيانات المستخدم بنجاح');
    }
}
