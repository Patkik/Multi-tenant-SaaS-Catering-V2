<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Central User Management</title>

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
<body class="min-h-screen p-5 sm:p-10">
    <div class="mx-auto max-w-6xl space-y-6">
        <header class="rounded-2xl border border-[#15191f]/20 bg-white/80 p-6 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="display text-3xl">Central User Management</h1>
                    <p class="mt-1 text-sm text-[#15191f]/70">Manage central control-plane users and access roles.</p>
                </div>
                <a href="{{ url('/') }}" class="rounded-lg border border-[#15191f]/25 bg-[#15191f] px-3 py-2 text-xs font-semibold uppercase tracking-[0.12em] text-[#f6f2e8] transition hover:bg-[#2f4254]">
                    Back to Dashboard
                </a>
            </div>
        </header>

        @if (session('user_success'))
            <div class="rounded-xl border border-[#2a8573]/40 bg-[#2a8573]/10 px-4 py-3 text-sm font-medium text-[#17463d]">
                {{ session('user_success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-[#d14b38]/40 bg-[#d14b38]/10 px-4 py-3 text-sm font-medium text-[#6b261c]">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="rounded-2xl border border-[#15191f]/20 bg-white/80 p-6 shadow-sm">
            <h2 class="display text-2xl">Create Central User</h2>
            <form method="POST" action="{{ route('central.users.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                @csrf

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f]/65">Name</span>
                    <input name="name" value="{{ old('name') }}" required class="w-full rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] px-3 py-2 text-sm outline-none transition focus:border-[#2f4254]">
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f]/65">Email</span>
                    <input type="email" name="email" value="{{ old('email') }}" required class="w-full rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] px-3 py-2 text-sm outline-none transition focus:border-[#2f4254]">
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f]/65">Password</span>
                    <input type="password" name="password" required class="w-full rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] px-3 py-2 text-sm outline-none transition focus:border-[#2f4254]">
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f]/65">Role</span>
                    <select name="role" required class="w-full rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] px-3 py-2 text-sm outline-none transition focus:border-[#2f4254]">
                        <option value="super_admin">Super Admin</option>
                        <option value="operations_admin">Operations Admin</option>
                        <option value="auditor">Auditor</option>
                    </select>
                </label>

                <div class="sm:col-span-2">
                    <button type="submit" class="rounded-lg border border-[#15191f]/25 bg-[#15191f] px-4 py-2 text-xs font-semibold uppercase tracking-[0.12em] text-[#f6f2e8] transition hover:bg-[#2f4254]">
                        Create User
                    </button>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-[#15191f]/20 bg-white/80 p-6 shadow-sm">
            <h2 class="display text-2xl">Current Central Users</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[680px] text-sm">
                    <thead>
                        <tr class="border-b border-[#15191f]/15 text-left text-xs uppercase tracking-[0.1em] text-[#15191f]/60">
                            <th class="pb-2 pr-3">Name</th>
                            <th class="pb-2 pr-3">Email</th>
                            <th class="pb-2 pr-3">Role</th>
                            <th class="pb-2 pr-3">Status</th>
                            <th class="pb-2 pr-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr class="border-b border-[#15191f]/10">
                                <td class="py-3 pr-3 font-medium">{{ $user->name }}</td>
                                <td class="py-3 pr-3">{{ $user->email }}</td>
                                <td class="py-3 pr-3">
                                    <form method="POST" action="{{ route('central.users.update', ['user' => $user->id]) }}" class="flex items-center gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <select name="role" class="rounded-md border border-[#15191f]/25 bg-[#fdfaf3] px-2 py-1 text-xs">
                                            <option value="super_admin" @selected($user->role === 'super_admin')>Super Admin</option>
                                            <option value="operations_admin" @selected($user->role === 'operations_admin')>Operations Admin</option>
                                            <option value="auditor" @selected($user->role === 'auditor')>Auditor</option>
                                        </select>
                                </td>
                                <td class="py-3 pr-3">
                                    <select name="is_active" class="rounded-md border border-[#15191f]/25 bg-[#fdfaf3] px-2 py-1 text-xs">
                                        <option value="1" @selected((bool) $user->is_active === true)>Active</option>
                                        <option value="0" @selected((bool) $user->is_active === false)>Inactive</option>
                                    </select>
                                </td>
                                <td class="py-3 pr-3">
                                        <button type="submit" class="rounded-md border border-[#15191f]/20 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-[#15191f]/80 transition hover:bg-white">
                                            Save
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-4 text-center text-[#15191f]/60">No central users found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</body>
</html>
