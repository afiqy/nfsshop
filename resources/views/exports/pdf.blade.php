<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, DroidFallback, sans-serif; font-size: 9px; }
        table { border-collapse: collapse; width: 100%; table-layout: fixed; }
        th, td { border: 1px solid #aaa; padding: 4px; text-align: left; word-wrap: break-word; }
        th { background: #eee; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    @if ($context)<p>{{ $context }}</p>@endif
    <p>Batch {{ $batch }}. {{ $more ? 'More rows are available in the next batch.' : 'No further rows.' }} Generated {{ now(\App\Models\Calendar::TIMEZONE)->format('Y-m-d H:i') }} Malaysia time.</p>
    <table>
        <thead><tr>@foreach ($headings as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>@foreach ($row as $value)<td>{{ $value }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($headings) }}">No matching records.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
