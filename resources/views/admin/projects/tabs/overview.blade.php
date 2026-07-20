@php
    $o = $overview;
    $progress = $project->progressPercent();
    $ring = 2 * M_PI * 34;                       // circumference of the progress ring (r=34)
    $statusLabel = \App\Models\Project::STATUSES[$project->status] ?? $project->status;
@endphp

{{-- ===== Stat cards — one row on desktop, 2-up on tablet, stacked on phone ===== --}}
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    {{-- Overall progress ring --}}
    <div class="flex flex-col rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <div class="flex items-center gap-4">
            <div class="relative shrink-0" style="height:76px;width:76px">
                <svg class="h-full w-full -rotate-90" viewBox="0 0 80 80">
                    <circle cx="40" cy="40" r="34" fill="none" stroke="#eef1f6" stroke-width="9"></circle>
                    <circle cx="40" cy="40" r="34" fill="none" stroke="var(--color-primary)" stroke-width="9" stroke-linecap="round"
                            stroke-dasharray="{{ $ring }}" stroke-dashoffset="{{ $ring - ($ring * min(100, $progress) / 100) }}"></circle>
                </svg>
                <span class="absolute inset-0 grid place-items-center text-sm font-bold text-[var(--color-heading)]">{{ $progress }}%</span>
            </div>
            <p class="text-sm text-[var(--color-muted)]">Overall Progress</p>
        </div>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 pt-4 text-xs text-[var(--color-muted)]" style="margin-top:auto">
            <span class="inline-flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-[var(--color-primary)]"></span>{{ $statusLabel }}</span>
        </div>
    </div>

    @foreach ([
        ['Tasks', $o['tasksTotal'], 'bg-indigo-50 text-indigo-600', 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01', [['bg-gray-400', $o['tasksTodo'].' To Do'], ['bg-emerald-500', $o['tasksDone'].' Done']]],
        ['Milestones', $o['milestonesTotal'], 'bg-emerald-50 text-emerald-600', 'M5 21V4m0 0h11l-1.5 3.5L16 11H5', [['bg-emerald-500', $o['milestonesDone'].' Completed'], ['bg-amber-500', ($o['milestonesTotal'] - $o['milestonesDone']).' Pending']]],
        ['Team Members', $o['membersTotal'], 'bg-violet-50 text-violet-600', 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM23 21v-2a4 4 0 0 0-3-3.9', [['bg-violet-500', $o['membersClient'].' Client'], ['bg-blue-500', $o['membersTeam'].' Team']]],
    ] as [$label, $value, $chip, $icon, $legend])
        <div class="flex flex-col rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <div class="flex items-center gap-4">
                <span class="grid shrink-0 place-items-center rounded-2xl {{ $chip }}" style="height:52px;width:52px">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-sm text-[var(--color-muted)]">{{ $label }}</p>
                    <p class="text-3xl font-bold text-[var(--color-heading)]" style="line-height:1.15">{{ $value }}</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 pt-4 text-xs text-[var(--color-muted)]" style="margin-top:auto">
                @foreach ($legend as [$dot, $text])
                    <span class="inline-flex items-center gap-2"><span class="h-2 w-2 rounded-full {{ $dot }}"></span>{{ $text }}</span>
                @endforeach
            </div>
        </div>
    @endforeach
</div>

{{-- ===== Progress overview + Task status ===== --}}
<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <div class="min-w-0 overflow-hidden rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <div class="mb-2 flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-lg font-bold text-[var(--color-heading)]">Progress Overview</h3>
            <select onchange="window.location = '{{ route('admin.projects.show', $project) }}?tab=overview&range=' + this.value"
                    class="h-9 rounded-lg border-gray-200 text-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                <option value="month" @selected($o['range'] === 'month')>This Month</option>
                <option value="all" @selected($o['range'] === 'all')>All Time</option>
            </select>
        </div>
        <div id="projProgressChart" class="min-w-0"></div>
        <div class="flex flex-wrap items-center justify-center gap-5 text-xs text-[var(--color-muted)]">
            <span class="inline-flex items-center gap-2"><span class="w-6 rounded bg-[var(--color-primary)]" style="height:2px"></span>Actual Progress</span>
            <span class="inline-flex items-center gap-2"><span class="w-6" style="height:0;border-top:2px dashed #d1d5db"></span>Planned Progress</span>
        </div>
    </div>

    <div class="min-w-0 overflow-hidden rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <h3 class="mb-3 text-lg font-bold text-[var(--color-heading)]">Task Status</h3>
        @if ($o['tasksTotal'] === 0)
            <p class="py-12 text-center text-sm text-gray-400">No tasks yet.</p>
        @else
            <div class="flex flex-col items-center gap-4 sm:flex-row">
                <div id="projStatusChart" class="shrink-0" style="width:200px"></div>
                <ul class="w-full space-y-3">
                    @foreach ($o['breakdown'] as $b)
                        <li class="flex items-center gap-3 text-sm">
                            <span class="h-3 w-3 shrink-0 rounded-full" style="background: {{ $b['color'] }}"></span>
                            <span class="w-7 shrink-0 font-bold text-[var(--color-heading)]">{{ $b['count'] }}</span>
                            <span class="flex-1 truncate text-[var(--color-muted)]">{{ $b['name'] }}</span>
                            <span class="shrink-0 text-right text-[var(--color-muted)]" style="width:44px">{{ $b['pct'] }}%</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>

{{-- ===== Upcoming milestones + Recent activity ===== --}}
<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-lg font-bold text-[var(--color-heading)]">Upcoming Milestones</h3>
            <a href="{{ route('admin.projects.show', $project) }}?tab=milestones" class="text-sm font-semibold text-[var(--color-primary)] hover:underline">View All</a>
        </div>
        @if (empty($o['upcoming']))
            <p class="py-8 text-center text-sm text-gray-400">No upcoming milestones.</p>
        @else
            <ul class="divide-y divide-gray-50">
                @foreach ($o['upcoming'] as $ms)
                    @php
                        $due = $ms['end_date'];
                        $days = $due ? (int) now()->startOfDay()->diffInDays($due, false) : null;
                        $badge = $days === null ? 'bg-gray-100 text-gray-500' : ($days < 0 ? 'bg-red-50 text-red-600' : ($days <= 30 ? 'bg-amber-50 text-amber-700' : 'bg-blue-50 text-blue-700'));
                        $flag = $days === null ? 'bg-gray-50 text-gray-400' : ($days < 0 ? 'bg-red-50 text-red-500' : ($days <= 30 ? 'bg-amber-50 text-amber-500' : 'bg-emerald-50 text-emerald-600'));
                    @endphp
                    <li class="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0">
                        <span class="grid shrink-0 place-items-center rounded-xl {{ $flag }}" style="height:38px;width:38px">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 21V4m0 0h11l-1.5 3.5L16 11H5"/></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-[var(--color-heading)]">{{ $ms['title'] }}</p>
                            <p class="text-xs text-[var(--color-muted)]">{{ $ms['remaining'] }} task{{ $ms['remaining'] === 1 ? '' : 's' }} remaining</p>
                        </div>
                        <span class="hidden shrink-0 text-sm text-[var(--color-muted)] sm:block">{{ $due?->format('d M, Y') ?? '—' }}</span>
                        @if ($days !== null)
                            <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold {{ $badge }}">{{ $days < 0 ? abs($days).' days late' : 'In '.$days.' days' }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-lg font-bold text-[var(--color-heading)]">Recent Activity</h3>
            <a href="{{ route('admin.projects.show', $project) }}?tab=activity" class="text-sm font-semibold text-[var(--color-primary)] hover:underline">View All</a>
        </div>
        @if ($activities->isEmpty())
            <p class="py-8 text-center text-sm text-gray-400">No activity yet.</p>
        @else
            <ul class="relative space-y-4">
                {{-- timeline rail behind the icons --}}
                <span class="pointer-events-none absolute left-4 top-3 bottom-3 w-px bg-gray-100"></span>
                @foreach ($activities as $a)
                    <li class="relative flex items-center gap-3">
                        @php
                            $d = strtolower($a->description ?? '');
                            $glyph = match (true) {
                                str_contains($d, 'milestone') => 'M5 21V4m0 0h11l-1.5 3.5L16 11H5',
                                str_contains($d, 'file')      => 'M7 3h7l5 5v13H7zM14 3v5h5',
                                str_contains($d, 'completed') => 'm5 13 4 4L19 7',
                                default                       => 'M9 12l2 2 4-4M7 3h10a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z',
                            };
                        @endphp
                        <span class="z-10 grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-[var(--color-primary-soft)] text-[var(--color-primary)] ring-4 ring-white">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $glyph }}"/></svg>
                        </span>
                        @if ($a->user?->photo_url)
                            <img src="{{ $a->user->photo_url }}" class="h-8 w-8 shrink-0 rounded-full object-cover" alt="">
                        @else
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-gray-100 text-xs font-bold text-gray-500">{{ strtoupper(substr($a->user?->name ?? '?', 0, 1)) }}</span>
                        @endif
                        <p class="min-w-0 flex-1 text-sm text-[var(--color-muted)]">
                            <span class="font-semibold text-[var(--color-heading)]">{{ $a->user?->name ?? 'System' }}</span>
                            {{ $a->description }}
                        </p>
                        <span class="shrink-0 text-xs text-gray-400">{{ $a->created_at->diffForHumans(null, true) }} ago</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
    (function renderProjectCharts() {
        if (!window.ApexCharts) return setTimeout(renderProjectCharts, 200);

        const line = document.querySelector('#projProgressChart');
        if (line && !line.dataset.done) {
            line.dataset.done = '1';
            new ApexCharts(line, {
                chart: { type: 'line', height: 240, width: '100%', toolbar: { show: false }, fontFamily: 'inherit', parentHeightOffset: 0, redrawOnParentResize: true },
                series: [
                    { name: 'Actual Progress', data: @js($o['chart']['actual']) },
                    { name: 'Planned Progress', data: @js($o['chart']['planned']) },
                ],
                xaxis: { categories: @js($o['chart']['labels']), axisBorder: { show: false }, axisTicks: { show: false } },
                yaxis: { min: 0, max: 100, tickAmount: 4, labels: { formatter: v => v + '%' } },
                stroke: { curve: 'smooth', width: [3, 2], dashArray: [0, 6] },
                colors: ['#4f46e5', '#cbd5e1'],
                legend: { show: false },
                grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
                markers: { size: 0, hover: { size: 5 } },
                tooltip: { y: { formatter: v => v + '%' } },
            }).render();
        }

        const donut = document.querySelector('#projStatusChart');
        if (donut && !donut.dataset.done) {
            donut.dataset.done = '1';
            new ApexCharts(donut, {
                chart: { type: 'donut', height: 200, width: '100%', fontFamily: 'inherit', redrawOnParentResize: true },
                series: @js(collect($o['breakdown'])->pluck('count')->all()),
                labels: @js(collect($o['breakdown'])->pluck('name')->all()),
                colors: @js(collect($o['breakdown'])->pluck('color')->all()),
                legend: { show: false },
                dataLabels: { enabled: false },
                stroke: { width: 0 },
                plotOptions: { pie: { donut: { size: '70%', labels: {
                    show: true,
                    value: { fontSize: '26px', fontWeight: 700, color: '#0f172a', offsetY: 5 },
                    total: { show: true, label: 'Total Tasks', fontSize: '12px', color: '#94a3b8',
                             formatter: () => @js((string) $o['tasksTotal']) },
                } } } },
            }).render();
        }

        // Apex can measure before the grid has settled — nudge it once the layout is final.
        requestAnimationFrame(() => window.dispatchEvent(new Event('resize')));
    })();
</script>
