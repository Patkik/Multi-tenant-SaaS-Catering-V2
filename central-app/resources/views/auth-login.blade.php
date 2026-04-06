<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=space-grotesk:400,500,700|fraunces:600" rel="stylesheet" />

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif

    <style>
        body {
            font-family: 'Space Grotesk', system-ui, sans-serif;
            background:
                radial-gradient(circle at 10% 0%, rgba(209, 75, 56, 0.12), transparent 44%),
                radial-gradient(circle at 90% 100%, rgba(42, 133, 115, 0.16), transparent 40%),
                linear-gradient(180deg, #f6f2e8 0%, #efe9db 100%);
            color: #15191f;
        }

        .display {
            font-family: 'Fraunces', Georgia, serif;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-md rounded-2xl border border-[#15191f]/20 bg-white/80 p-6 shadow-[0_10px_40px_rgba(21,25,31,0.12)] backdrop-blur-sm">
        <h1 class="display text-3xl">{{ $title }}</h1>
        <p class="mt-2 text-sm text-[#15191f]/70">{{ $subtitle }}</p>

        <form method="POST" action="{{ $action }}" class="mt-6 space-y-4">
            @csrf

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f]/65">{{ $emailLabel }}</span>
                <input
                    type="email"
                    name="email"
                    required
                    value="{{ old('email') }}"
                    class="w-full rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] px-3 py-2 text-sm outline-none transition focus:border-[#2f4254]"
                    placeholder="name@example.com"
                >
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f]/65">{{ $passwordLabel }}</span>
                <input
                    type="password"
                    name="password"
                    required
                    class="w-full rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] px-3 py-2 text-sm outline-none transition focus:border-[#2f4254]"
                    placeholder="Enter password"
                >
            </label>

            @if (!empty($roleOptions) && is_array($roleOptions))
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f]/65">{{ $roleLabel ?? 'Role' }}</span>
                    <select
                        name="role"
                        required
                        class="w-full rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] px-3 py-2 text-sm outline-none transition focus:border-[#2f4254]"
                    >
                        @foreach ($roleOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('role') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            @if ($errors->any())
                <div class="rounded-lg border border-[#d14b38]/40 bg-[#d14b38]/10 px-3 py-2 text-sm text-[#7a2e23]">
                    {{ $errors->first() }}
                </div>
            @endif

            <button
                type="submit"
                class="w-full rounded-lg border border-[#15191f]/25 bg-[#15191f] px-4 py-2 text-xs font-semibold uppercase tracking-[0.12em] text-[#f6f2e8] transition hover:bg-[#2f4254]"
            >
                {{ $submitLabel }}
            </button>

            @if (!empty($showRegisterLink) && $showRegisterLink)
                <p class="text-center text-sm text-[#15191f]/60">
                    Don't have an account?
                    <a href="{{ $registerUrl ?? '#' }}" class="font-medium text-[#2f4254] hover:underline">Create one</a>
                </p>
            @endif
        </form>
    </div>
</body>
</html>
