{{-- PDF of a filtered student list (dompdf, landscape). --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 96px 32px 56px 32px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8px; color: #111827; }
        header { position: fixed; top: -80px; left: 0; right: 0; height: 72px; text-align: center; border-bottom: 1px solid #9ca3af; }
        header .uni { font-size: 11px; font-weight: bold; }
        header .confidential { font-size: 9px; font-weight: bold; color: #b91c1c; letter-spacing: 2px; }
        footer { position: fixed; bottom: -40px; left: 0; right: 0; font-size: 7.5px; color: #4b5563; border-top: 1px solid #d1d5db; padding-top: 4px; }
        h1 { font-size: 11px; margin: 0 0 4px; }
        .filters { font-size: 7.5px; color: #374151; margin-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 0.5px solid #d1d5db; padding: 2px 4px; text-align: left; vertical-align: top; }
        th { background: #e5e7eb; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    <header>
        <div class="uni">{{ \App\Support\Exports\RecordDocument::UNIVERSITY }}</div>
        <div>{{ \App\Support\Exports\RecordDocument::CENTER }} · {{ \App\Support\Exports\RecordDocument::CITY }}</div>
        <div class="confidential">{{ \App\Support\Exports\RecordDocument::CONFIDENTIAL }}</div>
    </header>
    <footer><strong style="color: #b91c1c;">{{ \App\Support\Exports\RecordDocument::CONFIDENTIAL }}</strong> · {{ $generatedLine }}</footer>

    <h1>{{ $title }} ({{ count($rows) }})</h1>
    <div class="filters">{{ __('Filters') }}: {{ \App\Support\Exports\DocumentWriter::describeFilters($filters) }}</div>
    <table>
        <thead><tr>@foreach ($headers as $header)<th>{{ $header }}</th>@endforeach</tr></thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
