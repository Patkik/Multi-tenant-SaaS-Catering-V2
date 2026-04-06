<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Central App')</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=space-grotesk:400,500,700|fraunces:600" rel="stylesheet" />

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif

    <style>
        :root {
            --ink: #15191f;
            --paper: #f6f2e8;
            --coral: #d14b38;
            --teal: #2a8573;
            --mustard: #d89a2a;
            --slate: #2f4254;
        }

        body {
            font-family: 'Space Grotesk', system-ui, sans-serif;
            background:
                radial-gradient(circle at 10% 0%, rgba(209, 75, 56, 0.12), transparent 44%),
                radial-gradient(circle at 90% 100%, rgba(42, 133, 115, 0.16), transparent 40%),
                linear-gradient(180deg, #f6f2e8 0%, #efe9db 100%);
            color: var(--ink);
        }

        .display {
            font-family: 'Fraunces', Georgia, serif;
            letter-spacing: 0.01em;
        }

        .grid-paper {
            background-image:
                linear-gradient(rgba(21, 25, 31, 0.06) 1px, transparent 1px),
                linear-gradient(90deg, rgba(21, 25, 31, 0.06) 1px, transparent 1px);
            background-size: 24px 24px;
        }

        .metric-card {
            animation: rise 420ms ease-out both;
        }

        .metric-card:nth-child(2) { animation-delay: 80ms; }
        .metric-card:nth-child(3) { animation-delay: 160ms; }
        .metric-card:nth-child(4) { animation-delay: 240ms; }

        @keyframes rise {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .nav-link {
            transition: all 150ms ease;
        }
        .nav-link:hover {
            background: rgba(255, 255, 255, 0.5);
        }
        .nav-link.active {
            background: rgba(255, 255, 255, 0.7);
            font-weight: 600;
        }
    </style>
    @stack('styles')
</head>
<body class="min-h-screen">
    {{-- Top Navigation Bar --}}
    <nav class="sticky top-0 z-50 border-b border-[#15191f]/10 bg-[#f6f2e8]/95 backdrop-blur-md">
        <div class="mx-auto max-w-7xl px-5 sm:px-10">
            <div class="flex h-14 items-center justify-between">
                {{-- Logo / Brand --}}
                <div class="flex items-center gap-6">
                    <a href="{{ route('central.dashboard') }}" class="display text-xl font-semibold">
                        Central Command
                    </a>
                    
                    {{-- Main Navigation --}}
                    <div class="hidden items-center gap-1 md:flex">
                        <a 
                            href="{{ route('central.dashboard') }}" 
                            class="nav-link rounded-lg px-3 py-1.5 text-sm {{ request()->routeIs('central.dashboard') ? 'active' : '' }}"
                        >
                            Dashboard
                        </a>
                        <a 
                            href="{{ route('central.tenants') }}" 
                            class="nav-link rounded-lg px-3 py-1.5 text-sm {{ request()->routeIs('central.tenants*') ? 'active' : '' }}"
                        >
                            Tenants
                        </a>
                        <a 
                            href="{{ route('central.users.index') }}" 
                            class="nav-link rounded-lg px-3 py-1.5 text-sm {{ request()->routeIs('central.users*') ? 'active' : '' }}"
                        >
                            Users
                        </a>
                    </div>
                </div>

                {{-- Right Side Actions --}}
                <div class="flex items-center gap-3">
                    {{-- Database Status Badge --}}
                    <span class="hidden items-center gap-2 rounded-full border border-[#15191f]/15 bg-white/60 px-2.5 py-1 text-xs font-medium sm:inline-flex">
                        <span class="h-1.5 w-1.5 rounded-full {{ $databaseOnline ?? true ? 'bg-[#2a8573]' : 'bg-[#d14b38]' }}"></span>
                        {{ $databaseOnline ?? true ? 'Online' : 'Offline' }}
                    </span>

                    {{-- Logout --}}
                    <form method="POST" action="{{ route('auth.central.logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="rounded-lg border border-[#15191f]/20 bg-white/60 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.1em] text-[#15191f]/80 transition hover:bg-white"
                        >
                            Logout
                        </button>
                    </form>

                    {{-- Mobile Menu Button --}}
                    <button 
                        type="button" 
                        class="rounded-lg border border-[#15191f]/20 p-2 md:hidden"
                        onclick="document.getElementById('mobile-menu').classList.toggle('hidden')"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Mobile Navigation --}}
            <div id="mobile-menu" class="hidden border-t border-[#15191f]/10 py-3 md:hidden">
                <div class="flex flex-col gap-1">
                    <a 
                        href="{{ route('central.dashboard') }}" 
                        class="nav-link rounded-lg px-3 py-2 text-sm {{ request()->routeIs('central.dashboard') ? 'active' : '' }}"
                    >
                        Dashboard
                    </a>
                    <a 
                        href="{{ route('central.tenants') }}" 
                        class="nav-link rounded-lg px-3 py-2 text-sm {{ request()->routeIs('central.tenants*') ? 'active' : '' }}"
                    >
                        Tenants
                    </a>
                    <a 
                        href="{{ route('central.users.index') }}" 
                        class="nav-link rounded-lg px-3 py-2 text-sm {{ request()->routeIs('central.users*') ? 'active' : '' }}"
                    >
                        Users
                    </a>
                </div>
            </div>
        </div>
    </nav>

    {{-- Main Content --}}
    <main class="mx-auto max-w-7xl p-5 sm:p-10">
        {{-- Flash Messages --}}
        @if (session('success') || session('tenant_create_success'))
            <div class="mb-4 rounded-xl border border-[#2a8573]/40 bg-[#2a8573]/10 px-4 py-3 text-sm font-medium text-[#17463d]">
                {{ session('success') ?? session('tenant_create_success') }}
            </div>
        @endif

        @if (session('error') || session('tenant_create_error'))
            <div class="mb-4 rounded-xl border border-[#d14b38]/40 bg-[#d14b38]/10 px-4 py-3 text-sm font-medium text-[#6b261c]">
                {{ session('error') ?? session('tenant_create_error') }}
            </div>
        @endif

        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
