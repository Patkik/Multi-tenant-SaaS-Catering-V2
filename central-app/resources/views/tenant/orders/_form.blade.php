@php
    $editing = isset($order);
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <label class="block text-sm">
        <span class="mb-1 block font-medium">Customer Name</span>
        <input
            type="text"
            name="customer_name"
            value="{{ old('customer_name', $order->customer_name ?? '') }}"
            required
            maxlength="255"
            class="w-full rounded-lg border border-[var(--line)] bg-white px-3 py-2 outline-none focus:border-[var(--brand)]"
        />
        @error('customer_name')
            <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
        @enderror
    </label>

    <label class="block text-sm">
        <span class="mb-1 block font-medium">Items Count</span>
        <input
            type="number"
            name="items_count"
            value="{{ old('items_count', $order->items_count ?? 1) }}"
            required
            min="1"
            class="w-full rounded-lg border border-[var(--line)] bg-white px-3 py-2 outline-none focus:border-[var(--brand)]"
        />
        @error('items_count')
            <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
        @enderror
    </label>

    <label class="block text-sm">
        <span class="mb-1 block font-medium">Total Amount</span>
        <input
            type="number"
            name="total_amount"
            value="{{ old('total_amount', isset($order) ? number_format((float) $order->total_amount, 2, '.', '') : '0.00') }}"
            required
            min="0"
            step="0.01"
            class="w-full rounded-lg border border-[var(--line)] bg-white px-3 py-2 outline-none focus:border-[var(--brand)]"
        />
        @error('total_amount')
            <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
        @enderror
    </label>

    <label class="block text-sm">
        <span class="mb-1 block font-medium">Order Type</span>
        <select
            name="order_type"
            required
            class="w-full rounded-lg border border-[var(--line)] bg-white px-3 py-2 outline-none focus:border-[var(--brand)]"
        >
            @foreach ($orderTypes as $type)
                <option value="{{ $type }}" @selected(old('order_type', $order->order_type ?? '') === $type)>{{ $type }}</option>
            @endforeach
        </select>
        @error('order_type')
            <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
        @enderror
    </label>

    <label class="block text-sm">
        <span class="mb-1 block font-medium">Status</span>
        <select
            name="status"
            required
            class="w-full rounded-lg border border-[var(--line)] bg-white px-3 py-2 outline-none focus:border-[var(--brand)]"
        >
            @foreach ($orderStatuses as $status)
                <option value="{{ $status }}" @selected(old('status', $order->status ?? '') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        @error('status')
            <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
        @enderror
    </label>

    <label class="block text-sm">
        <span class="mb-1 block font-medium">Ordered At</span>
        <input
            type="datetime-local"
            name="ordered_at"
            value="{{ old('ordered_at', isset($order) && $order->ordered_at ? $order->ordered_at->format('Y-m-d\\TH:i') : now()->format('Y-m-d\\TH:i')) }}"
            required
            class="w-full rounded-lg border border-[var(--line)] bg-white px-3 py-2 outline-none focus:border-[var(--brand)]"
        />
        @error('ordered_at')
            <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
        @enderror
    </label>
</div>

<div class="mt-5 flex gap-2">
    <button type="submit" class="rounded-lg bg-[var(--brand)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--brand-deep)]">
        {{ $editing ? 'Update Order' : 'Create Order' }}
    </button>
    <a href="{{ route('tenant.orders.index') }}" class="rounded-lg border border-[var(--line)] bg-white px-4 py-2 text-sm hover:bg-gray-50">
        Cancel
    </a>
</div>