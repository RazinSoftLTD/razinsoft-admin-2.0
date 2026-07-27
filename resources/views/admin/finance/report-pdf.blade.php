<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Finance — {{ ucwords(str_replace('_', ' ', $report)) }} Report</title>
    <style>
        body { font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif; color: #111827; margin: 32px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        p.meta { color: #6b7280; font-size: 12px; margin: 0 0 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th { text-align: left; background: #f9fafb; color: #6b7280; text-transform: uppercase; font-size: 10px; letter-spacing: .04em; padding: 8px; border-bottom: 1px solid #e5e7eb; }
        td { padding: 8px; border-bottom: 1px solid #f3f4f6; }
        .summary { margin-top: 20px; font-size: 13px; font-weight: 700; }
        .summary span { margin-right: 16px; }
        @media print { .noprint { display: none; } }
    </style>
</head>
<body>
    <button class="noprint" onclick="window.print()" style="float:right;padding:8px 14px;border:1px solid #d1d5db;border-radius:8px;background:#fff;cursor:pointer">Print / Save as PDF</button>

    <h1>{{ ucwords(str_replace('_', ' ', $report)) }} Report</h1>
    <p class="meta">{{ \App\Models\InvoiceSetting::current()->brand_name }} · {{ \Carbon\Carbon::parse($from)->format('d M Y') }} – {{ \Carbon\Carbon::parse($to)->format('d M Y') }} · generated {{ now()->format('d M Y, g:i A') }}</p>

    <table>
        <thead><tr>@foreach ($columns as $col)<th>{{ $col }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ max(1, count($columns)) }}" style="text-align:center;color:#9ca3af;padding:24px">Nothing in this range.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if (count($summary))
        <p class="summary">@foreach ($summary as $line)<span>{{ $line }}</span>@endforeach</p>
    @endif

    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>
