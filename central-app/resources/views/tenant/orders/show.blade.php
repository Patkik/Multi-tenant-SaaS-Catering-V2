@extends('layouts.tenant-app')

@section('title', 'Order Details')

@section('content')
    @php
        $canUpdateOrders = (bool) ($canUpdateOrders ?? ($canEdit ?? false));
        $canDeleteOrders = (bool) ($canDeleteOrders ?? ($canDelete ?? false));
    @endphp

    @if (session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="display-font text-3xl">{{ $order->order_number }}</h1>
            <p class="mt-1 text-sm text-[var(--muted)]">Order details and status</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('tenant.orders.index') }}" class="rounded-lg border border-[var(--line)] bg-white px-4 py-2 text-sm hover:bg-gray-50">Back</a>
            @if ($canUpdateOrders)
                <a href="{{ route('tenant.orders.edit', $order) }}" class="rounded-lg bg-[var(--brand)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--brand-deep)]">Edit</a>
            @endif
            @if ($canDeleteOrders)
                <form method="POST" action="{{ route('tenant.orders.destroy', $order) }}" onsubmit="return confirm('Delete this order?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Delete</button>
                </form>
            @endif
        </div>
    </div>

    <section class="tile mt-6 p-5">
        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Customer</dt>
                <dd class="mt-1 text-base font-medium">{{ $order->customer_name }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Items</dt>
                <dd class="mt-1 text-base font-medium">{{ $order->items_count }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Total Amount</dt>
                <dd class="mt-1 text-base font-medium">${{ number_format((float) $order->total_amount, 2) }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Order Type</dt>
                <dd class="mt-1 text-base font-medium">{{ $order->order_type }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Status</dt>
                <dd class="mt-1 text-base font-medium">{{ $order->status }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-[0.12em] text-[var(--muted)]">Ordered At</dt>
                <dd class="mt-1 text-base font-medium">{{ $order->ordered_at?->format('M d, Y h:i A') }}</dd>
            </div>
        </dl>
    </section>
@endsection