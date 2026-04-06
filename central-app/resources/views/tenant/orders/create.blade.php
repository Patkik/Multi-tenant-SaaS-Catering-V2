@extends('layouts.tenant-app')

@section('title', 'New Order')

@section('content')
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="display-font text-3xl">Create Order</h1>
            <p class="mt-1 text-sm text-[var(--muted)]">Add a new order for this tenant.</p>
        </div>
        <a href="{{ route('tenant.orders.index') }}" class="rounded-lg border border-[var(--line)] bg-white px-4 py-2 text-sm hover:bg-gray-50">Back to Orders</a>
    </div>

    <section class="tile mt-6 p-5">
        <form method="POST" action="{{ route('tenant.orders.store') }}">
            @csrf
            @include('tenant.orders._form')
        </form>
    </section>
@endsection