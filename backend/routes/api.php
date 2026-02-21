<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\SurgeryController;
use App\Http\Controllers\ConfigReportePermisoController;

// Activación de cuenta por token
Route::get('/activate-account/{token}', [AuthController::class, 'activateAccount']);

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/check-patient', [AuthController::class, 'checkPatient']);
Route::get('/verify-registration-token/{token}', [AuthController::class, 'verifyRegistrationToken']);
Route::post('/recover-password', [AuthController::class, 'recoverPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::get('/manual', [AuthController::class, 'downloadManual']);
Route::get('/document-types', [AuthController::class, 'getDocumentTypes']);


// Rutas de Agendamiento (Públicas para catálogos)
Route::prefix('appointments')->group(function () {
    Route::get('/plans', [AppointmentController::class, 'getPlans']);
    Route::get('/affiliate-types', [AppointmentController::class, 'getAffiliateTypes']); 
    Route::get('/patient-last-data', [AppointmentController::class, 'getPatientLastData']); 
    Route::get('/types', [AppointmentController::class, 'getAppointmentTypes']);
    Route::get('/services', [AppointmentController::class, 'getServices']);
    Route::get('/professionals', [AppointmentController::class, 'getProfessionals']);
    Route::get('/availability', [AppointmentController::class, 'getAvailability']);
    Route::get('/assigned', [AppointmentController::class, 'getAssignedAppointments']);
    Route::get('/cancellation-types', [AppointmentController::class, 'getCancellationTypes']);
});

// Rutas protegidas
Route::middleware('auth:sanctum')->group(function () {
    // Agendamiento (Solo reservar requiere auth)
    Route::post('/appointments/book', [AppointmentController::class, 'bookAppointment']);
    Route::post('/appointments/cancel', [AppointmentController::class, 'cancelAppointment']);

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Módulo de Historia Clínica y Reportes
    Route::get('/medical-history', [\App\Http\Controllers\ReportController::class, 'getMedicalHistory']);
    Route::post('/medical-history/satisfaction-survey', [\App\Http\Controllers\ReportController::class, 'storeSurvey']);
    Route::get('/medical-history/attachments', [\App\Http\Controllers\ReportController::class, 'getAttachments']);
    Route::get('/medical-history/{ingreso}', [\App\Http\Controllers\ReportController::class, 'getHistoryDetail']);
    
    // Módulo de Cirugía (Notas Operatorias)
    Route::get('/medical-history/surgeries/{ingreso}', [SurgeryController::class, 'getSurgeries']);
    Route::get('/medical-history/surgeries/{notaId}/pdf', [SurgeryController::class, 'generatePdf']);
    Route::post('/medical-history/surgeries/{notaId}/send-email', [SurgeryController::class, 'sendEmail']);

    // Generación de PDFs (Siguen por evolución ID para precisión, o podemos cambiar a ingreso si se requiriera)
    Route::get('/medical-history/{evolucion_id}/pdf-formula', [\App\Http\Controllers\ReportController::class, 'generateFormulaPdf']);
    Route::get('/medical-history/{evolucion_id}/pdf-orden', [\App\Http\Controllers\ReportController::class, 'generateOrderPdf']);
    Route::get('/medical-history/{evolucion_id}/pdf-incapacidad', [\App\Http\Controllers\ReportController::class, 'generateIncapacidadPdf']);
    Route::get('/medical-history/{ingreso}/pdf-completo', [\App\Http\Controllers\ReportController::class, 'generateHistoryPdf']);
    
    // Envío de reportes por correo
    Route::post('/medical-history/{ingreso}/send-email', [\App\Http\Controllers\ReportController::class, 'sendHistoryEmail']);

    // Módulo de Hospitalización (Query separado)
    Route::get('/hospitalization', [\App\Http\Controllers\HospitalizationController::class, 'index']);
    Route::get('/hospitalization/{ingreso}/pdf-no-qx', [\App\Http\Controllers\HospitalizationController::class, 'generateNoQxPdf']);
    Route::post('/hospitalization/{ingreso}/send-email-no-qx', [\App\Http\Controllers\HospitalizationController::class, 'sendNoQxEmail']);

    // Módulo de Apoyos Diagnósticos
    Route::get('/diagnostic-support', [\App\Http\Controllers\DiagnosticSupportController::class, 'index']);
    Route::get('/diagnostic-support/{id}/pdf', [\App\Http\Controllers\DiagnosticSupportController::class, 'generatePdf']);
    Route::post('/diagnostic-support/{id}/send-email', [\App\Http\Controllers\DiagnosticSupportController::class, 'sendEmail']);

    // Perfil de Usuario
    Route::put('/profile/update', [AuthController::class, 'updateProfile']);
    Route::post('/profile/change-password', [AuthController::class, 'changePassword']);
});

// Obtener permisos (Público o Autenticado según tu lógica)
Route::get('/config-reporte-permisos', [ConfigReportePermisoController::class, 'index']);
// Actualizar permisos (Debería ser solo admin)
Route::post('/config-reporte-permisos', [ConfigReportePermisoController::class, 'update']);

