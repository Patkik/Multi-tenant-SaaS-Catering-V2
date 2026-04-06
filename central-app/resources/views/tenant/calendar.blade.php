@extends('layouts.tenant-app')

@section('title', 'Calendar')

@section('content')
    <!-- Page Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="display-font text-3xl lg:text-4xl">Calendar</h1>
            <p class="mt-1 text-sm text-[var(--muted)]">
                Schedule and manage events, deliveries, and catering bookings.
            </p>
        </div>
        <button class="rounded-full bg-[var(--brand)] px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[var(--brand-deep)] transition">
            + New Event
        </button>
    </div>

    <!-- Calendar View -->
    <section class="mt-6">
        <div class="tile p-6">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-4">
                    <button class="p-2 hover:bg-gray-100 rounded-lg">←</button>
                    <h2 class="font-semibold text-xl">April 2026</h2>
                    <button class="p-2 hover:bg-gray-100 rounded-lg">→</button>
                </div>
                <div class="flex gap-2">
                    <button class="rounded-lg border border-[var(--line)] px-3 py-1.5 text-sm hover:bg-gray-50">Month</button>
                    <button class="rounded-lg bg-[var(--brand-light)] px-3 py-1.5 text-sm font-medium text-[var(--brand)]">Week</button>
                    <button class="rounded-lg border border-[var(--line)] px-3 py-1.5 text-sm hover:bg-gray-50">Day</button>
                </div>
            </div>

            <!-- Week View -->
            <div class="grid grid-cols-7 gap-2 text-center text-sm">
                @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
                    <div class="py-2 text-xs font-semibold uppercase text-[var(--muted)]">{{ $day }}</div>
                @endforeach
            </div>

            <div class="grid grid-cols-7 gap-2 mt-2">
                @for ($i = 1; $i <= 7; $i++)
                    <div class="min-h-[120px] rounded-lg border border-[var(--line)] p-2 {{ $i === 6 ? 'bg-[var(--brand-light)]' : '' }}">
                        <p class="text-sm font-semibold {{ $i === 6 ? 'text-[var(--brand)]' : '' }}">{{ $i + 5 }}</p>
                        @if ($i === 2)
                            <div class="mt-2 rounded bg-amber-100 px-2 py-1 text-xs text-amber-700">
                                Corporate Lunch
                            </div>
                        @endif
                        @if ($i === 4)
                            <div class="mt-2 rounded bg-green-100 px-2 py-1 text-xs text-green-700">
                                Wedding Reception
                            </div>
                        @endif
                        @if ($i === 6)
                            <div class="mt-2 rounded bg-[var(--brand)] px-2 py-1 text-xs text-white">
                                Birthday Party
                            </div>
                            <div class="mt-1 rounded bg-blue-100 px-2 py-1 text-xs text-blue-700">
                                Team Meeting
                            </div>
                        @endif
                    </div>
                @endfor
            </div>
        </div>
    </section>

    <!-- Upcoming Events -->
    <section class="mt-6">
        <h2 class="font-semibold text-lg mb-4">Upcoming Events</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="tile p-4">
                <div class="flex items-start justify-between">
                    <span class="badge bg-amber-100 text-amber-700">Catering</span>
                    <span class="text-sm text-[var(--muted)]">Apr 7</span>
                </div>
                <h3 class="mt-2 font-semibold">Corporate Lunch</h3>
                <p class="text-sm text-[var(--muted)]">50 guests · Tech Corp HQ</p>
            </div>
            <div class="tile p-4">
                <div class="flex items-start justify-between">
                    <span class="badge bg-green-100 text-green-700">Wedding</span>
                    <span class="text-sm text-[var(--muted)]">Apr 9</span>
                </div>
                <h3 class="mt-2 font-semibold">Wedding Reception</h3>
                <p class="text-sm text-[var(--muted)]">150 guests · Grand Ballroom</p>
            </div>
            <div class="tile p-4">
                <div class="flex items-start justify-between">
                    <span class="badge badge-brand">Party</span>
                    <span class="text-sm text-[var(--muted)]">Apr 11</span>
                </div>
                <h3 class="mt-2 font-semibold">Birthday Party</h3>
                <p class="text-sm text-[var(--muted)]">30 guests · Private Venue</p>
            </div>
        </div>
    </section>
@endsection
