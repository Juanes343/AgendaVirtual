<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/check-patient', [AuthController::class, 'checkPatient']);
Route::post('/recover-password', [AuthController::class, 'recoverPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Rutas protegidas
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Módulo de Historia Clínica y Reportes
    Route::get('/medical-history', [\App\Http\Controllers\ReportController::class, 'getMedicalHistory']);
    Route::get('/medical-history/{evolucion_id}', [\App\Http\Controllers\ReportController::class, 'getHistoryDetail']);
    
    // Generación de PDFs
    Route::get('/medical-history/{evolucion_id}/pdf-formula', [\App\Http\Controllers\ReportController::class, 'generateFormulaPdf']);
    Route::get('/medical-history/{evolucion_id}/pdf-orden', [\App\Http\Controllers\ReportController::class, 'generateOrderPdf']);
});

