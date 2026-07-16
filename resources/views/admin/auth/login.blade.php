<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · RazinSoft Admin</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css'])
</head>
<body class="grid min-h-full place-items-center bg-[var(--color-body)] p-4 text-[var(--color-heading)]">
    <div class="w-full max-w-sm">
        <div class="mb-6 flex items-center justify-center gap-2">
            <img src="{{ asset('images/razinsoft-icon.svg') }}" alt="RazinSoft" class="h-10 w-10 rounded-lg shadow-sm">
            <span class="text-xl font-extrabold">RazinSoft</span>
        </div>

        <div class="rounded-2xl border border-gray-100 bg-white p-7 shadow-sm">
            <h1 class="text-xl font-bold">Welcome back 👋</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">Sign in to the admin panel.</p>

            @if ($errors->any())
                <p class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">{{ $errors->first() }}</p>
            @endif

            <form method="POST" action="{{ route('admin.login.attempt') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium">Email</label>
                    <input id="email" name="email" type="email" required autofocus value="{{ old('email') }}"
                           class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]"
                           placeholder="admin@razinsoft.com">
                </div>
                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium">Password</label>
                    <input id="password" name="password" type="password" required
                           class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]"
                           placeholder="••••••••">
                </div>
                <label class="flex items-center gap-2 text-sm text-[var(--color-muted)]">
                    <input type="checkbox" name="remember" class="h-4 w-4 rounded border-gray-300 accent-[var(--color-primary)]"> Remember me
                </label>
                <button type="submit" class="h-11 w-full rounded-lg bg-[var(--color-primary)] text-sm font-semibold text-white transition hover:bg-[var(--color-primary-hover)]">
                    Sign in
                </button>
            </form>
        </div>
    </div>
</body>
</html>
