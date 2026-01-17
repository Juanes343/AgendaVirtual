<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AppointmentController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/check-patient', [AuthController::class, 'checkPatient']);
Route::post('/recover-password', [AuthController::class, 'recoverPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::get('/manual', [AuthController::class, 'downloadManual']);

// Rutas de Agendamiento (Públicas para catálogos)
Route::prefix('appointments')->group(function () {
    Route::get('/plans', [AppointmentController::class, 'getPlans']);
    Route::get('/affiliate-types', [AppointmentController::class, 'getAffiliateTypes']); 
    Route::get('/patient-last-data', [AppointmentController::class, 'getPatientLastData']); 
    Route::get('/types', [AppointmentController::class, 'getAppointmentTypes']);
    Route::get('/services', [AppointmentController::class, 'getServices']);
    Route::get('/professionals', [AppointmentController::class, 'getProfessionals']);
    Route::get('/availability', [AppointmentController::class, 'getAvailability']);
});

// Rutas protegidas
Route::middleware('auth:sanctum')->group(function () {
    // Agendamiento (Solo reservar requiere auth)
    Route::post('/appointments/book', [AppointmentController::class, 'bookAppointment']);

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Módulo de Historia Clínica y Reportes
    Route::get('/medical-history', [\App\Http\Controllers\ReportController::class, 'getMedicalHistory']);
    Route::get('/medical-history/{ingreso}', [\App\Http\Controllers\ReportController::class, 'getHistoryDetail']);
    
    // Generación de PDFs (Siguen por evolución ID para precisión, o podemos cambiar a ingreso si se requiriera)
    Route::get('/medical-history/{evolucion_id}/pdf-formula', [\App\Http\Controllers\ReportController::class, 'generateFormulaPdf']);
    Route::get('/medical-history/{evolucion_id}/pdf-orden', [\App\Http\Controllers\ReportController::class, 'generateOrderPdf']);
    Route::get('/medical-history/{evolucion_id}/pdf-incapacidad', [\App\Http\Controllers\ReportController::class, 'generateIncapacidadPdf']);

    // Perfil de Usuario
    Route::put('/profile/update', [AuthController::class, 'updateProfile']);
    Route::post('/profile/change-password', [AuthController::class, 'changePassword']);
});

