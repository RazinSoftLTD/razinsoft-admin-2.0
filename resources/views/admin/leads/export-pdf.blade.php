<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { margin: 0; color: #1f2937; }
        .head { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 12px; }
        h1 { font-size: 16px; margin: 0; color: #111827; }
        .meta { font-size: 9px; color: #6b7280; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e5e7eb; padding: 4px 6px; font-size: 8px; text-align: left; }
        th { background: #f3f4f6; color: #374151; text-transform: uppercase; letter-spacing: .03em; }
        tr:nth-child(even) td { background: #fafafa; }
    </style>
</head>
<body>
    <div class="head">
        <h1>RazinSoft — Leads Export</h1>
        <span class="meta">{{ count($rows) }} lead(s) · generated {{ $generatedAt }}</span>
    </div>
    <table>
        <thead>
            <tr>@foreach ($headers as $h)<th>{{ $h }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($headers) }}" style="text-align:center;padding:16px;color:#9ca3af;">No leads to export.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
