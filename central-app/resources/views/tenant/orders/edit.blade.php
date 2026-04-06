@extends('layouts.tenant-app')

@section('title', 'Edit Order')

@section('content')
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="display-font text-3xl">Edit Order</h1>
            <p class="mt-1 text-sm text-[var(--muted)]">{{ $order->order_number }} for {{ $order->customer_name }}</p>
        </div>
        <a href="{{ route('tenant.orders.show', $order) }}" class="rounded-lg border border-[var(--line)] bg-white px-4 py-2 text-sm hover:bg-gray-50">View Order</a>
    </div>

    <section class="tile mt-6 p-5">
        <form method="POST" action="{{ route('tenant.orders.update', $order) }}">
            @csrf
            @method('PUT')
            @include('tenant.orders._form', ['order' => $order])
        </form>
    </section>
@endsection