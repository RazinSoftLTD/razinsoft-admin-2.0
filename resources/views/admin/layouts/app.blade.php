<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') · RazinSoft</title>
    @vite(['resources/css/app.css'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="h-full bg-[var(--color-body)] text-[var(--color-heading)] antialiased" x-data="{ sidebar: false }">
    <!-- Sidebar -->
    <aside
        class="fixed inset-y-0 left-0 z-40 w-64 transform overflow-y-auto border-r border-gray-100 bg-white transition-transform lg:translate-x-0"
        :class="sidebar ? 'translate-x-0' : '-translate-x-full'"
    >
        @include('admin.partials.sidebar')
    </aside>

    <!-- Overlay (mobile) -->
    <div x-show="sidebar" @click="sidebar = false" class="fixed inset-0 z-30 bg-black/30 lg:hidden" x-cloak></div>

    <!-- Main -->
    <div class="lg:pl-64">
        @include('admin.partials.topbar')

        <main class="p-4 sm:p-6">
            @if (session('status'))
                <div class="mb-5 flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                    {{ session('status') }}
                </div>
            @endif
            @if (session('error'))
                <div class="mb-5 flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                    {{ session('error') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <p class="font-semibold">Please fix the following:</p>
                    <ul class="mt-1 list-inside list-disc">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <style>[x-cloak]{display:none!important}</style>
</body>
</html>
