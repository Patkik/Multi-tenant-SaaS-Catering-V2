@extends('layouts.tenant-app')

@section('title', 'Kitchen Board')

@section('content')
    @php
        $kitchenOrders = [
            ['id' => '1234', 'table' => 'Delivery', 'items' => ['Grilled Chicken x3', 'Caesar Salad x2', 'Pasta Carbonara x1'], 'status' => 'cooking', 'time' => '12 min', 'priority' => 'normal'],
            ['id' => '1233', 'table' => 'Event Hall', 'items' => ['Buffet Setup x50', 'Appetizer Platter x10'], 'status' => 'prep', 'time' => '45 min', 'priority' => 'high'],
            ['id' => '1235', 'table' => 'Pickup', 'items' => ['Burger Combo x2', 'Fries x2', 'Drinks x2'], 'status' => 'ready', 'time' => '2 min', 'priority' => 'normal'],
            ['id' => '1236', 'table' => 'Delivery', 'items' => ['Steak Dinner x1', 'Wine Selection x1'], 'status' => 'cooking', 'time' => '8 min', 'priority' => 'rush'],
        ];

        $statusColors = [
            'prep' => 'bg-gray-100 border-gray-300 text-gray-700',
            'cooking' => 'bg-amber-100 border-amber-300 text-amber-800',
            'ready' => 'bg-green-100 border-green-300 text-green-800',
        ];
    @endphp

    <!-- Page Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="display-font text-3xl lg:text-4xl">Kitchen Board</h1>
            <p class="mt-1 text-sm text-[var(--muted)]">
                Real-time order tracking for kitchen operations.
            </p>
        </div>
        <div class="flex gap-2">
            <button class="rounded-full border border-[var(--line)] px-4 py-2 text-sm font-medium hover:bg-gray-50">
                🔔 Sound On
            </button>
            <button class="rounded-full bg-[var(--brand)] px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[var(--brand-deep)] transition">
                Full Screen
            </button>
        </div>
    </div>

    <!-- Status Columns -->
    <section class="mt-6 grid gap-4 lg:grid-cols-3">
        <!-- Prep -->
        <div>
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold flex items-center gap-2">
                    <span class="h-3 w-3 rounded-full bg-gray-400"></span>
                    Prep Queue
                </h2>
                <span class="badge badge-muted">1</span>
            </div>
            <div class="space-y-3">
                @foreach ($kitchenOrders as $order)
                    @if ($order['status'] === 'prep')
                        <div class="tile p-4 border-l-4 {{ $order['priority'] === 'high' ? 'border-l-red-500' : 'border-l-gray-300' }}">
                            <div class="flex items-start justify-between">
                                <span class="font-mono font-bold">#{{ $order['id'] }}</span>
                                @if ($order['priority'] === 'high')
                                    <span class="badge bg-red-100 text-red-700">HIGH</span>
                                @endif
                            </div>
                            <p class="text-sm text-[var(--muted)] mt-1">{{ $order['table'] }}</p>
                            <ul class="mt-3 space-y-1 text-sm">
                                @foreach ($order['items'] as $item)
                                    <li class="flex items-center gap-2">
                                        <span class="h-1.5 w-1.5 rounded-full bg-[var(--muted)]"></span>
                                        {{ $item }}
                                    </li>
                                @endforeach
                            </ul>
                            <div class="mt-3 flex items-center justify-between">
                                <span class="text-xs text-[var(--muted)]">⏱ {{ $order['time'] }}</span>
                                <button class="rounded-lg bg-amber-500 px-3 py-1 text-xs font-semibold text-white hover:bg-amber-600">
                                    Start Cooking
                                </button>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        <!-- Cooking -->
        <div>
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold flex items-center gap-2">
                    <span class="h-3 w-3 rounded-full bg-amber-500 animate-pulse"></span>
                    Cooking
                </h2>
                <span class="badge bg-amber-100 text-amber-700">2</span>
            </div>
            <div class="space-y-3">
                @foreach ($kitchenOrders as $order)
                    @if ($order['status'] === 'cooking')
                        <div class="tile p-4 border-l-4 {{ $order['priority'] === 'rush' ? 'border-l-red-500 bg-red-50' : 'border-l-amber-400' }}">
                            <div class="flex items-start justify-between">
                                <span class="font-mono font-bold">#{{ $order['id'] }}</span>
                                @if ($order['priority'] === 'rush')
                                    <span class="badge bg-red-100 text-red-700 animate-pulse">RUSH</span>
                                @endif
                            </div>
                            <p class="text-sm text-[var(--muted)] mt-1">{{ $order['table'] }}</p>
                            <ul class="mt-3 space-y-1 text-sm">
                                @foreach ($order['items'] as $item)
                                    <li class="flex items-center gap-2">
                                        <input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-[var(--brand)]" />
                                        {{ $item }}
                                    </li>
                                @endforeach
                            </ul>
                            <div class="mt-3 flex items-center justify-between">
                                <span class="text-xs font-semibold text-amber-600">⏱ {{ $order['time'] }} remaining</span>
                                <button class="rounded-lg bg-green-500 px-3 py-1 text-xs font-semibold text-white hover:bg-green-600">
                                    Mark Ready
                                </button>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        <!-- Ready -->
        <div>
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold flex items-center gap-2">
                    <span class="h-3 w-3 rounded-full bg-green-500"></span>
                    Ready for Pickup
                </h2>
                <span class="badge badge-brand">1</span>
            </div>
            <div class="space-y-3">
                @foreach ($kitchenOrders as $order)
                    @if ($order['status'] === 'ready')
                        <div class="tile p-4 border-l-4 border-l-green-500 bg-green-50">
                            <div class="flex items-start justify-between">
                                <span class="font-mono font-bold">#{{ $order['id'] }}</span>
                                <span class="badge badge-brand">READY</span>
                            </div>
                            <p class="text-sm text-[var(--muted)] mt-1">{{ $order['table'] }}</p>
                            <ul class="mt-3 space-y-1 text-sm">
                                @foreach ($order['items'] as $item)
                                    <li class="flex items-center gap-2 line-through text-[var(--muted)]">
                                        <span class="text-green-500">✓</span>
                                        {{ $item }}
                                    </li>
                                @endforeach
                            </ul>
                            <div class="mt-3 flex items-center justify-between">
                                <span class="text-xs text-green-600 font-medium">Waiting {{ $order['time'] }}</span>
                                <button class="rounded-lg bg-[var(--brand)] px-3 py-1 text-xs font-semibold text-white hover:bg-[var(--brand-deep)]">
                                    Complete
                                </button>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </section>
@endsection
