<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Historia Clínica Completa</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #333; }
        .header-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .header-table td { vertical-align: top; }
        .logo { max-width: 150px; }
        .empresa-info { text-align: center; font-weight: bold; font-size: 14px; }
        .section-title { 
            background-color: #f0f0f0; 
            padding: 5px; 
            font-weight: bold; 
            border: 1px solid #ccc; 
            margin-top: 15px; 
            margin-bottom: 5px;
        }
        .content-block { margin-bottom: 10px; padding: 5px; text-align: justify; }
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .data-table th, .data-table td { border: 1px solid #ccc; padding: 4px; text-align: left; font-size: 10px; }
        .data-table th { background-color: #f9f9f9; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; border-top: 1px solid #ccc; padding-top: 10px; }
        .patient-info td { padding: 2px 5px; }
        .label { font-weight: bold; }
    </style>
</head>
<body>

    <!-- Encabezado con Logo y Datos Empresa -->
    <table class="header-table">
        <tr>
            <td width="20%">
                @if($logoBase64)
                    <img src="{{ $logoBase64 }}" class="logo" />
                @endif
            </td>
            <td width="60%" class="empresa-info">
                {{ $empresa->nombre ?? 'NOMBRE DE LA EMPRESA' }}<br>
                <span style="font-size: 11px; font-weight: normal;">
                    NIT: {{ $empresa->nit ?? '' }}<br>
                    {{ $empresa->direccion ?? '' }} - {{ $empresa->telefono ?? '' }}
                </span>
            </td>
            <td width="20%" style="text-align: right; font-size: 10px;">
                <strong>Fecha Impresión:</strong><br>
                {{ date('Y-m-d H:i') }}
            </td>
        </tr>
    </table>

    <!-- Datos del Paciente -->
    <div class="section-title">DATOS DEL PACIENTE</div>
    <table class="header-table patient-info">
        <tr>
            <td width="15%" class="label">Identificación:</td>
            <td width="35%">{{ $paciente->identificacion }}</td>
            <td width="15%" class="label">Paciente:</td>
            <td width="35%">{{ $paciente->nombre_paciente }}</td>
        </tr>
        <tr>
            <td class="label">Edad:</td>
            <td>{{ $paciente->edad }}</td>
            <td class="label">Sexo:</td>
            <td>{{ $paciente->sexo }}</td>
        </tr>
        <tr>
            <td class="label">Dirección:</td>
            <td>{{ $paciente->direccion }}</td>
            <td class="label">Teléfono:</td>
            <td>{{ $paciente->telefono }}</td>
        </tr>
        <tr>
            <td class="label">Aseguradora:</td>
            <td>{{ $paciente->nombre_aseguradora }}</td>
            <td class="label">Ingreso:</td>
            <td>{{ $ingreso }} - {{ $fecha }}</td>
        </tr>
    </table>

    <!-- Motivo de Consulta y Enfermedad Actual -->
    @if(!empty($paciente->motivo_consulta))
    <div class="section-title">MOTIVO DE CONSULTA</div>
    <div class="content-block">{{ $paciente->motivo_consulta }}</div>
    @endif

    @if(!empty($paciente->enfermedad_actual))
    <div class="section-title">ENFERMEDAD ACTUAL</div>
    <div class="content-block">{{ $paciente->enfermedad_actual }}</div>
    @endif
    
    @if(!empty($paciente->revis_sistemas))
    <div class="section-title">REVISIÓN POR SISTEMAS</div>
    <div class="content-block">{{ $paciente->revis_sistemas }}</div>
    @endif

    @if(!empty($paciente->antecedentes_personales) || !empty($paciente->antecedentes_familiares))
    <div class="section-title">ANTECEDENTES</div>
    <div class="content-block">
        @if(!empty($paciente->antecedentes_personales))
        <strong>Personales:</strong> {{ $paciente->antecedentes_personales }}<br>
        @endif
        @if(!empty($paciente->antecedentes_familiares))
        <strong>Familiares:</strong> {{ $paciente->antecedentes_familiares }}
        @endif
    </div>
    @endif

    <!-- Examen Físico (Si existiera en el futuro, por ahora placeholder si hay datos) -->
    @if(!empty($paciente->examen_fisico))
    <div class="section-title">EXAMEN FÍSICO</div>
    <div class="content-block">{{ $paciente->examen_fisico }}</div>
    @endif

    <!-- SUBMÓDULOS DINÁMICOS (Incluyendo MotivoConsulta detallado) -->
    @if(isset($submodulos) && count($submodulos) > 0)
        @foreach($submodulos as $nombre => $registros)
            
            @if($nombre == 'MotivoConsulta')
                <div class="section-title">MOTIVO DE CONSULTA Y ENFERMEDAD ACTUAL (Detalle)</div>
                @foreach($registros as $row)
                <div class="content-block" style="border-bottom: 1px dotted #ccc; padding-bottom: 5px; margin-bottom: 5px;">
                    @if(!empty($row->fecha_registro))
                        <strong>Fecha Registro:</strong> {{ $row->fecha_registro }} 
                        @if(!empty($row->usuario_id)) - Usu: {{ $row->usuario_id }} @endif <br>
                    @endif

                    @if(!empty($row->descripcion))
                        <strong>MOTIVO DE CONSULTA:</strong><br>
                        {!! nl2br(e($row->descripcion)) !!}<br>
                    @endif
                    
                    @if(!empty($row->motivo_diagnostico_id))
                        <span style="font-size: 10px; color: #555;"><strong>Diagnóstico Motivo:</strong> {{ $row->motivo_diagnostico_id }} - {{ $row->diagnostico_motivo ?? '' }}</span><br>
                    @endif
                    <br>
                    
                    @if(!empty($row->enfermedadactual))
                        <strong>ENFERMEDAD ACTUAL:</strong><br>
                        {!! nl2br(e($row->enfermedadactual)) !!}<br>
                    @endif

                    @if(!empty($row->enfermedad_diagnostico_id))
                        <span style="font-size: 10px; color: #555;"><strong>Diagnóstico Enfermedad:</strong> {{ $row->enfermedad_diagnostico_id }} - {{ $row->diagnostico_enfermedad ?? '' }}</span><br>
                    @endif
                </div>
                @endforeach
            
            @elseif(strpos($nombre, 'PlanTerapeutico') !== false)
                <!-- Bloque Específico para Plan Terapéutico -->
                <div class="section-title">RESUMEN DEL PLAN TERAPEUTICO</div>
                <div class="content-block">
                @foreach($registros as $row)
                    @foreach((array)$row as $key => $val)
                        @if(!in_array($key, ['evolucion_id', 'ingreso', 'usuario_id', 'fecha_registro', 'hc_plan_terapeutico_id'])) 
                            @if(!empty($val))
                                <strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong> {!! nl2br(e($val)) !!}<br>
                            @endif
                        @endif
                    @endforeach
                    <hr style="border: 0; border-top: 1px dashed #eee;">
                @endforeach
                </div>

            @else
                <!-- Otros Submódulos Genéricos -->
                <div class="section-title">{{ strtoupper(preg_replace('/(?<!^)[A-Z]/', ' $0', $nombre)) }}</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <!-- Cabeceras dinámicas basadas en el primer registro -->
                            @foreach(array_keys((array)$registros[0]) as $col)
                                @if(!in_array($col, ['evolucion_id', 'ingreso', 'usuario_id'])) <!-- Ocultar columnas internas -->
                                    <th>{{ ucfirst(str_replace('_', ' ', $col)) }}</th>
                                @endif
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($registros as $reg)
                        <tr>
                            @foreach((array)$reg as $key => $val)
                                @if(!in_array($key, ['evolucion_id', 'ingreso', 'usuario_id']))
                                    <td>{{ $val }}</td>
                                @endif
                            @endforeach
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endforeach
    @endif

    <!-- Diagnósticos -->
    @if(count($diagnosticos) > 0)
    <div class="section-title">DIAGNÓSTICOS</div>
    <table class="data-table">
        <thead>
            <tr>
                <th width="15%">CIE10</th>
                <th width="65%">Descripción</th>
                <th width="20%">Tipo</th>
            </tr>
        </thead>
        <tbody>
            @foreach($diagnosticos as $diag)
            <tr>
                <td>{{ $diag->codigo }}</td>
                <td>{{ $diag->nombre }}</td>
                <td>{{ $diag->tipo_diagnostico == 'P' ? 'Principal' : 'Relacionado' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <!-- Análisis y Plan -->
    @if(!empty($paciente->analisis))
    <div class="section-title">ANÁLISIS</div>
    <div class="content-block">{{ $paciente->analisis }}</div>
    @endif

    @if(!empty($paciente->plan))
    <div class="section-title">PLAN DE MANEJO</div>
    <div class="content-block">{{ $paciente->plan }}</div>
    @endif

    <!-- Medicamentos -->
    @if(count($medicamentos) > 0)
    <div class="section-title">MEDICAMENTOS FORMULADOS</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Medicamento</th>
                <th width="15%">Cantidad</th>
                <th width="40%">Posología</th>
            </tr>
        </thead>
        <tbody>
            @foreach($medicamentos as $med)
            <tr>
                <td>{{ $med->nombre_medicamento }}</td>
                <td>{{ $med->cantidad }}</td>
                <td>{{ $med->posologia }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <!-- Solicitudes/Examenes -->
    @if(count($solicitudes) > 0)
    <div class="section-title">SOLICITUDES DE SERVICIOS</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Código</th>
                <th>Descripción</th>
                <th>Cantidad</th>
                <th>Observación</th>
            </tr>
        </thead>
        <tbody>
            @foreach($solicitudes as $sol)
            <tr>
                <td width="15%">{{ $sol->codigo }}</td>
                <td width="40%">{{ $sol->nombre_examen }}</td>
                <td width="10%">{{ $sol->cantidad }}</td>
                <td width="35%">{{ $sol->observacion }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <!-- Incapacidades -->
    @if(count($incapacidades) > 0)
    <div class="section-title">INCAPACIDADES</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Fecha Inicio</th>
                <th>Días</th>
                <th>Diagnóstico</th>
                <th>Observación</th>
            </tr>
        </thead>
        <tbody>
            @foreach($incapacidades as $inc)
            <tr>
                <td>{{ $inc->fecha_inicio }}</td>
                <td>{{ $inc->dias }}</td>
                <td>{{ $inc->codigo_diagnostico }}</td>
                <td>{{ $inc->observacion }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <!-- Notas de Evolución / Notas Médicas -->
    @if(count($notas) > 0)
    <div class="section-title">NOTAS DE EVOLUCIÓN / MÉDICAS</div>
    @foreach($notas as $nota)
        <div style="border-bottom: 1px dashed #ccc; padding: 5px 0;">
            <strong>Fecha:</strong> {{ $nota->fecha_nota }} - <strong>Autor:</strong> {{ $nota->nombre_usuario ?? 'Médico' }}<br>
            <div style="margin-top: 3px; white-space: pre-wrap;">{{ $nota->nota }}</div>
        </div>
    @endforeach
    @endif

    <!-- Footer / Firma -->
    <div class="footer">
        <br><br>
        <strong>{{ $profesional->nombre_completo ?? 'Profesional de la Salud' }}</strong><br>
        {{ $especialidad->nombre ?? 'Medicina General' }}<br>
        Registro Médico: {{ $profesional->registro_medico ?? '' }}
    </div>

</body>
</html>