<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Historia Clínica</title>

    {{-- Para que funcionen images/, firmas/, etc --}}
    <base href="{{ rtrim($baseUrl, '/') }}/">

    <style>
        @page { margin: 14mm 12mm; }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 12px;
            color: #111;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table[border="1"],
        table[border="1"] td,
        table[border="1"] th {
            border: 1px solid #cfcfcf;
        }

        td, th {
            padding: 6px 8px;
            vertical-align: top;
        }

        .modulo_table_title,
        .hc_table_submodulo_list_title {
            background: #f3f6ff;
            font-weight: bold;
        }

        img {
            max-width: 100%;
        }
    </style>
</head>
<body>

    {!! $html !!}

</body>
</html>