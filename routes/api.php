<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
 

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\StaticController;
use App\Http\Controllers\API\StaticDataController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\PatientController;
use App\Http\Controllers\API\MedicalRecordController;
use App\Http\Controllers\API\RecordTransferController;
use App\Http\Controllers\API\ReportsController;

// Public routes
Route::controller(AuthController::class)->group(function(){
	Route::post('login', 'login');
});

// Static data routes (public)
Route::controller(StaticController::class)->group(function(){
	Route::get('static', 'index');
	Route::get('static/types', 'getTypes');
	Route::get('static/{type}', 'getByType');
	Route::get('static/{type}/{code}', 'getByCode');
});

//

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
	// Auth routes
	Route::controller(AuthController::class)->group(function(){
		Route::post('logout', 'logout');
		Route::get('profile', 'profile');
		Route::put('profile', 'updateProfile');
	});

	// User management (Admin only)
	Route::controller(UserController::class)->group(function(){
		Route::get('users', 'index');
		Route::get('users/{id}', 'show');
		Route::post('users', 'store');
		Route::put('users/{id}', 'update');
		// Route::delete('users/{id}', 'destroy');
	});

	// Patient management
	Route::controller(PatientController::class)->group(function(){
		Route::get('patients', 'index');
		Route::get('patients/{id}', 'show');
		Route::post('patients', 'store');
		Route::put('patients/{id}', 'update');
		// Route::delete('patients/{id}', 'destroy');
	});

	// Medical records management
	Route::controller(MedicalRecordController::class)->group(function(){
		Route::get('records', 'index');
		Route::get('records/filter-options', 'getFilterOptions');
		Route::get('records/statistics', 'getStatistics');
		Route::get('records/export', 'export');
		Route::get('records/daily-transfers-report', 'getDailyTransfersReport');
		Route::get('records/{id}', 'show');
		Route::post('records', 'store');
		Route::put('records/{id}', 'update');
		// Route::delete('records/{id}', 'destroy');
	});

	// Record transfers management
	Route::controller(RecordTransferController::class)->group(function(){
		Route::get('transfers', 'index');
		Route::get('transfers/{id}', 'show');
		Route::post('transfers', 'store');
		// Route::put('transfers/{id}', 'update');
		// Route::delete('transfers/{id}', 'destroy');
		Route::post('transfers/{id}/receive', 'receive');
		Route::post('transfers/{id}/complete', 'complete');
	});

	// Reports management
	Route::controller(ReportsController::class)->group(function(){
		Route::get('reports/available', 'getAvailableReports');
		Route::post('reports/generate', 'generateReport');
	});

	// Static data management (Admin only)
	Route::controller(StaticDataController::class)->group(function(){
		Route::get('static-data', 'index');
		Route::get('static-data/types', 'getTypes');
		Route::get('static-data/{id}', 'show');
		Route::post('static-data', 'store');
		Route::put('static-data/{id}', 'update');
		Route::delete('static-data/{id}', 'destroy');
		Route::post('static-data/{id}/toggle-status', 'toggleStatus');
		Route::post('static-data/bulk-update-status', 'bulkUpdateStatus');
		Route::get('static-data/type/{type}', 'getByType');
		Route::get('static-data/type/{type}/code/{code}', 'getByCode');
	});

	// Test broadcasting routes (for development/testing)
	Route::post('test/broadcast', function () {
		// Test admin notification
		event(new \App\Events\MedicalRecordCreated(
			\App\Models\MedicalRecord::first() ?? new \App\Models\MedicalRecord(['record_id' => 1]),
			auth()->user()
		));
		
		return response()->json(['message' => 'Test event broadcasted successfully']);
	});

	// Broadcasting authentication route
	Route::post('broadcasting/auth', function (Request $request) {
		// Debug the entire request to see what's coming in
		\Log::info('Broadcasting auth request debug', [
			'method' => $request->method(),
			'url' => $request->url(),
			'headers' => $request->headers->all(),
			'content_type' => $request->header('Content-Type'),
			'raw_body' => $request->getContent(),
			'input_data' => $request->all(),
			'post_data' => $request->post(),
			'query_params' => $request->query()
		]);
		
		// Get the channel name and socket ID from form data
		$channelName = $request->input('channel_name');
		$socketId = $request->input('socket_id');
		
		// Check if user is authenticated
		if (!auth()->check()) {
			return response()->json(['message' => 'Unauthenticated'], 403);
		}
		
		$user = auth()->user();
		
		// Debug logging
		\Log::info('Broadcasting auth attempt', [
			'user_id' => $user->user_id,
			'username' => $user->username,
			'role_code' => $user->role_code,
			'is_active' => $user->is_active,
			'channel_name' => $channelName,
			'socket_id' => $socketId,
			'isAdmin' => $user->isAdmin()
		]);
		
		// Check channel authorization based on our rules
		$canAccess = false;
		
		if ($channelName === 'admin.notifications') {
			$canAccess = $user->isAdmin();
		} elseif (str_starts_with($channelName, 'user.')) {
			$userId = explode('.', $channelName)[1];
			$canAccess = (int) $user->user_id === (int) $userId;
		}
		
		if (!$canAccess) {
			return response()->json([
				'message' => 'Unauthorized',
				'debug' => [
					'user_id' => $user->user_id,
					'role_code' => $user->role_code,
					'isAdmin' => $user->isAdmin(),
					'channel' => $channelName,
					'socket_id' => $socketId,
					'request_data' => $request->all()
				]
			], 403);
		}
		
		// Generate Pusher auth response
		$pusher = new \Pusher\Pusher(
			env('PUSHER_APP_KEY'),
			env('PUSHER_APP_SECRET'),
			env('PUSHER_APP_ID'),
			[
				'cluster' => env('PUSHER_APP_CLUSTER'),
				'useTLS' => true
			]
		);
		
		$auth = $pusher->socket_auth($channelName, $socketId);
		
		return response($auth);
	});
});

// Test Pusher connection
Route::post('test/pusher-connection', function () {
    try {
        $pusher = new \Pusher\Pusher(
            env('PUSHER_APP_KEY'),
            env('PUSHER_APP_SECRET'),
            env('PUSHER_APP_ID'),
            [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'useTLS' => true,
                'curl_options' => [
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_SSL_VERIFYPEER => 0,
                ],
            ]
        );
        
        // Test the connection
        $response = $pusher->get('/channels');
        
        return response()->json([
            'success' => true,
            'message' => 'Pusher connection successful',
            'channels' => $response
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Pusher connection failed',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

//
