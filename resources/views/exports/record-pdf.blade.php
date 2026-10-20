{{-- PDF of one student's record (dompdf). Inline CSS only; DejaVu Sans covers ñ and accents. --}}
@php($doc = $document)
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $doc->title }} · {{ $doc->studentNumber }}</title>
    <style>
        @page { margin: 118px 42px 64px 42px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #111827; }
        header { position: fixed; top: -100px; left: 0; right: 0; height: 90px; text-align: center; border-bottom: 1px solid #9ca3af; }
        header .uni { font-size: 13px; font-weight: bold; }
        header .center { font-size: 11.5px; font-weight: bold; }
        header .city { font-size: 10px; }
        header .confidential { margin-top: 4px; font-size: 10px; font-weight: bold; color: #b91c1c; letter-spacing: 2px; }
        footer { position: fixed; bottom: -46px; left: 0; right: 0; height: 30px; font-size: 8px; color: #4b5563; border-top: 1px solid #d1d5db; padding-top: 4px; }
        footer .confidential { color: #b91c1c; font-weight: bold; }
        h1 { font-size: 13px; text-align: center; margin: 0 0 8px; }
        h2 { font-size: 10px; text-transform: uppercase; background: #1f2937; color: #fff; padding: 4px 6px; margin: 12px 0 0; }
        h3 { font-size: 9.5px; font-style: italic; margin: 8px 0 3px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 0.6px solid #d1d5db; padding: 3px 5px; vertical-align: top; text-align: left; }
        th { background: #e5e7eb; font-weight: bold; }
        td.label { width: 34%; font-weight: bold; background: #f9fafb; }
        .value { white-space: pre-line; }
        .muted { color: #9ca3af; }
        .page-break { page-break-before: always; }
        .identity td { border: none; padding: 1px 4px; }
        .photo { width: 96px; height: 96px; border: 0.6px solid #9ca3af; object-fit: cover; }
        .no-photo { width: 96px; height: 96px; border: 0.6px dashed #9ca3af; color: #9ca3af; text-align: center; font-size: 8px; line-height: 96px; }
    </style>
</head>
<body>
    <header>
        <div class="uni">{{ \App\Support\Exports\RecordDocument::UNIVERSITY }}</div>
        <div class="center">{{ \App\Support\Exports\RecordDocument::CENTER }}</div>
        <div class="city">{{ \App\Support\Exports\RecordDocument::CITY }}</div>
        <div class="confidential">{{ \App\Support\Exports\RecordDocument::CONFIDENTIAL }}</div>
    </header>
    <footer>
        <span class="confidential">{{ \App\Support\Exports\RecordDocument::CONFIDENTIAL }}</span> · {{ $doc->generatedLine }}
    </footer>

    <h1>{{ $doc->title }}</h1>

    <table class="identity">
        <tr>
            <td>
                <div style="font-size: 13px; font-weight: bold; margin-bottom: 4px;">{{ $doc->studentName }}</div>
                @foreach ($doc->meta as $label => $value)
                    <div><strong>{{ $label }}:</strong> {{ $value }}</div>
                @endforeach
            </td>
            <td style="width: 100px; text-align: right;">
                @if ($doc->photo)
                    <img class="photo" src="data:{{ $doc->photo['mime'] }};base64,{{ base64_encode($doc->photo['data']) }}" alt="2x2 photo">
                @else
                    <div class="no-photo">No 2x2 photo</div>
                @endif
            </td>
        </tr>
    </table>

    @foreach ($doc->sections as $section)
        <div @class(['page-break' => $section['pageBreak']])>
            <h2>{{ $section['title'] }}</h2>
            @if ($section['fields'])
                <table>
                    @foreach ($section['fields'] as [$label, $value])
                        <tr>
                            <td class="label">{{ $label }}</td>
                            <td class="value">@if ($value === '')<span class="muted">—</span>@else{{ $value }}@endif</td>
                        </tr>
                    @endforeach
                </table>
            @endif
            @foreach ($section['tables'] as $table)
                <div style="page-break-inside: avoid;">
                @if ($table['title'] !== '')
                    <h3>{{ $table['title'] }}</h3>
                @endif
                <table style="margin-top: 3px;">
                    <thead>
                        <tr>
                            @foreach ($table['headers'] as $header)
                                <th>{{ $header }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($table['rows'] as $row)
                            <tr>
                                @foreach ($row as $cell)
                                    <td class="value">{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($table['headers']) }}" class="muted">None</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            @endforeach
        </div>
    @endforeach
</body>
</html>
