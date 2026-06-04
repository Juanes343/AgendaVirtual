<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SurgeryReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

class SurgeryController extends Controller
{
    protected $surgeryService;

    public function __construct(SurgeryReportService $surgeryService)
    {
        $this->surgeryService = $surgeryService;
    }

    /**
     * Obtener listado de cirugías por ingreso o paciente
     */
    public function getSurgeries(Request $request, $ingresoId)
    {
        try {
            // Si hay usuario autenticado y es paciente, obtener TODAS sus cirugías de todos los ingresos
            $user = $request->user();
            if ($user && isset($user->paciente_id) && isset($user->tipo_documento)) {
                $surgeries = $this->surgeryService->getSurgeriesByPatient($user->paciente_id, $user->tipo_documento);
            } else {
                // Comportamiento anterior (fallback)
                $surgeries = $this->surgeryService->getSurgeriesByIngreso($ingresoId);
            }
            
            // Post-procesar para añadir encuesta_completada y datos de encuesta
            foreach ($surgeries as &$surgery) {
                // Si ya viene del service no hace falta, pero usualmente es un objeto o array
                $ing = is_object($surgery) ? $surgery->ingreso : ($surgery['ingreso'] ?? null);
                if ($ing) {
                    $surveyHeader = DB::table('hc_encuesta_satisfaccion')->where('ingreso', $ing)->first();
                    $hasSurvey = !empty($surveyHeader);

                    $pregunta1 = null;
                    $pregunta2 = null;
                    $fechaEncuesta = null;

                    if ($hasSurvey) {
                        $fechaEncuesta = substr($surveyHeader->fecha_registro ?? '', 0, 10);
                        $detalles = DB::table('hc_encuesta_satisfaccion_detalle as d')
                            ->join('encuesta_satisfaccion_preguntas as p', 'p.pregunta_id', '=', 'd.pregunta_id')
                            ->where('d.ingreso', $ing)
                            ->orderBy('p.indice_orden')
                            ->select('d.pregunta_id', 'd.respuesta', 'p.descripcion_pregunta', 'p.indice_orden')
                            ->get();
                    }

                    if (is_object($surgery)) {
                        $surgery->encuesta_completada = $hasSurvey ? 1 : 0;
                        $surgery->encuesta_respuestas = $hasSurvey ? $detalles : [];
                        $surgery->encuesta_fecha_registro = $fechaEncuesta;
                    } else {
                        $surgery['encuesta_completada'] = $hasSurvey ? 1 : 0;
                        $surgery['encuesta_respuestas'] = $hasSurvey ? $detalles : [];
                        $surgery['encuesta_fecha_registro'] = $fechaEncuesta;
                    }
                } else {
                    if (is_object($surgery)) {
                        $surgery->encuesta_completada = 0;
                        $surgery->encuesta_pregunta_1 = null;
                        $surgery->encuesta_pregunta_2 = null;
                        $surgery->encuesta_fecha_registro = null;
                    } else {
                        $surgery['encuesta_completada'] = 0;
                        $surgery['encuesta_pregunta_1'] = null;
                        $surgery['encuesta_pregunta_2'] = null;
                        $surgery['encuesta_fecha_registro'] = null;
                    }
                }
            }
            
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
            
            // Prepare data for email view
            $emailData = [
                'nombre' => $data['paciente']->nombre_completo,
                'fecha' => $data['nota']->hora_inicio,
                'profesional' => $data['profesional']->nombre,
                'tipo_reporte' => $data['nota']->tipo ?? 'Procedimiento Quirúrgico'
            ];

            // Enviar correo
            Mail::send('emails.surgery_note_report', $emailData, function ($message) use ($pacienteEmail, $pdf, $notaId) {
                $message->to($pacienteEmail)
                        ->subject("Reporte Nota Operatoria - Cirugía #{$notaId}")
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
