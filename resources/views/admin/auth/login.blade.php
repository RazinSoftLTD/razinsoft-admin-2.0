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
                    <label for="email" class="mb-1.5 block text-sm font-medium">Email or User ID</label>
                    <input id="email" name="email" type="text" required autofocus value="{{ old('email') }}"
                           class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]"
                           placeholder="Email or User ID">
                </div>
                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium">Password</label>
                    <div class="relative">
                        <input id="password" name="password" type="password" required
                               class="h-11 w-full rounded-lg border border-gray-200 pl-3 pr-10 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]"
                               placeholder="••••••••">
                        <button type="button" id="pw-toggle" title="Show / hide"
                                class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-gray-400 hover:text-[var(--color-heading)]">
                            <svg id="pw-eye" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                            <svg id="pw-eye-off" class="hidden h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
                        </button>
                    </div>
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
    <script>
        (function () {
            const input = document.getElementById('password');
            const btn = document.getElementById('pw-toggle');
            const eye = document.getElementById('pw-eye');
            const eyeOff = document.getElementById('pw-eye-off');
            if (!input || !btn) return;
            btn.addEventListener('click', function () {
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                if (eye) eye.classList.toggle('hidden', show);
                if (eyeOff) eyeOff.classList.toggle('hidden', !show);
            });
        })();
    </script>
</body>
</html>
