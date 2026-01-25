<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SurgeryReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;

class SurgeryController extends Controller
{
    protected $surgeryService;

    public function __construct(SurgeryReportService $surgeryService)
    {
        $this->surgeryService = $surgeryService;
    }

    /**
     * Obtener listado de cirugías por ingreso
     */
    public function getSurgeries($ingresoId)
    {
        try {
            $surgeries = $this->surgeryService->getSurgeriesByIngreso($ingresoId);
            return response()->json(['success' => true, 'data' => $surgeries]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Generar PDF de Nota Operatoria
     */
    public function generatePdf($notaId)
    {
        try {
            $data = $this->surgeryService->getSurgeryReportData($notaId);
            
            $pdf = Pdf::loadView('reportes.nota_operatoria', $data);
            $pdf->setPaper('letter', 'portrait'); // Ajustar tamaño según necesidad (oficio/legal es standard en legacy, pero letter es común)

            return $pdf->stream("nota_operatoria_{$notaId}.pdf");

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error generando PDF: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Enviar Nota Operatoria por correo
     */
    public function sendEmail(Request $request, $notaId)
    {
        try {
            $user = $request->user();
            // Asumiendo que el usuario es el paciente o tiene relación con él.
            // En un sistema real, validaríamos que el usuario tenga acceso a esta historia.
            
            // Obtener datos
            $data = $this->surgeryService->getSurgeryReportData($notaId);
            $pacienteEmail = $user->email; // O obtener del paciente en $data['paciente']
            
            // Si el user no tiene email, intentar del paciente
            if (!$pacienteEmail && isset($data['paciente']->email)) {
                $pacienteEmail = $data['paciente']->email;
            }

            if (!$pacienteEmail) {
                return response()->json(['success' => false, 'message' => 'No hay correo registrado para el usuario.'], 400);
            }

            $pdf = Pdf::loadView('reportes.nota_operatoria', $data);
            
            // Enviar correo
            Mail::send([], [], function ($message) use ($pacienteEmail, $pdf, $notaId) {
                $message->to($pacienteEmail)
                        ->subject("Nota Operatoria #{$notaId} - AgendaVirtual")
                        ->attachData($pdf->output(), "Nota_Operatoria_{$notaId}.pdf", [
                            'mime' => 'application/pdf',
                        ]);
            });

            return response()->json(['success' => true, 'message' => 'Correo enviado exitosamente']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error enviando correo: ' . $e->getMessage()], 500);
        }
    }
}
