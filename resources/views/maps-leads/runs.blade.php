{{-- Import history: one row per collection run reported by the extension. --}}
@extends('admin.layouts.app')
@section('title', 'Maps Leads - Import history')

@push('head')
    <style>
        .rsm-runs { --line:#e2e8f0; --muted:#64748b; }
        .rsm-runs .wrap { overflow-x:auto; background:#fff; border:1px solid var(--line); border-radius:10px; }
        .rsm-runs table { width:100%; border-collapse:collapse; font-size:13px; }
        .rsm-runs th, .rsm-runs td { padding:9px 11px; text-align:left; border-bottom:1px solid var(--line); }
        .rsm-runs th { background:#f1f5f9; font-size:11px; text-transform:uppercase; letter-spacing:.4px; color:var(--muted); }
        .rsm-runs tr:last-child td { border-bottom:0; }
        .rsm-runs code { font-size:12px; color:var(--muted); }
        .rsm-runs .muted { color:var(--muted); }
        .rsm-runs .toolbar { margin-bottom:16px; }
    </style>
@endpush

@section('content')
    <div class="rsm-runs">
        <div class="toolbar">
            <a class="muted" href="{{ route('admin.maps-leads.dashboard') }}">&larr; Back to maps leads</a>
        </div>

        <div class="wrap">
            <table>
                <thead>
                <tr>
                    <th>Run</th>
                    <th>Search</th>
                    <th>Received</th>
                    <th>Created</th>
                    <th>Duplicates</th>
                    <th>Rejected</th>
                    <th>Started</th>
                    <th>Last activity</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($runs as $run)
                    <tr>
                        <td><code>{{ $run->run_id }}</code></td>
                        <td>
                            {{ $run->query ?: '-' }}
                            <div class="muted">{{ collect([$run->city, $run->country])->filter()->join(', ') }}</div>
                        </td>
                        <td>{{ number_format($run->received) }}</td>
                        <td>{{ number_format($run->created) }}</td>
                        <td>{{ number_format($run->duplicates) }}</td>
                        <td>{{ number_format($run->rejected) }}</td>
                        <td>{{ $run->started_at?->diffForHumans() }}</td>
                        <td>{{ $run->last_seen_at?->diffForHumans() ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted" style="padding:22px;text-align:center">No runs recorded yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:14px">{{ $runs->links() }}</div>
    </div>
@endsection
