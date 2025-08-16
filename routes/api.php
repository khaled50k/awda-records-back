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
		Route::get('records/{id}', 'show');
		Route::post('records', 'store');
		// Route::put('records/{id}', 'update');
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
});

//
