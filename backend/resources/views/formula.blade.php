<!DOCTYPE html>
<html>
<head>
    <title>Fórmula Médica</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; }
        .info-box { border: 1px solid #ccc; padding: 10px; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .signature { margin-top: 50px; border-top: 1px solid #000; width: 200px; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>FÓRMULA MÉDICA</h2>
        <p>Fecha de atención: {{ $fecha }}</p>
    </div>

    <div class="info-box">
        <strong>Paciente:</strong> {{ $paciente->nombre_completo }} <br>
        <strong>Identificación:</strong> {{ $paciente->tipo_id_paciente }} {{ $paciente->paciente_id }}
    </div>

    <h3>Medicamentos Recetados</h3>
    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Medicamento / Producto</th>
                <th>Principio Activo</th>
            </tr>
        </thead>
        <tbody>
            @foreach($medicamentos as $med)
            <tr>
                <td>{{ $med->codigo_medicamento }}</td>
                <td>{{ $med->producto }}</td>
                <td>{{ $med->principio_activo }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div style="margin-top: 40px;">
        <div class="signature">
            <strong>Profesional:</strong><br>
            {{ $profesional }}
        </div>
    </div>
</body>
</html>