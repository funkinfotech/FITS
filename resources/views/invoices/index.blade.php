@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto py-10 px-6">
    <h1 class="text-3xl font-bold mb-6 text-primary">Invoices</h1>

    <div class="mb-8 bg-white shadow rounded p-6">
        <p class="text-sm text-gray-500">Total balance due</p>
        <p class="text-3xl font-bold text-primary">${{ number_format((float) $balanceOwed, 2) }}</p>
    </div>

    @if ($invoices->isEmpty())
        <p class="text-gray-600">You don't have any invoices yet.</p>
    @else
        <div class="bg-white shadow rounded divide-y">
            @foreach ($invoices as $invoice)
                <a href="{{ route('invoices.show', $invoice) }}" wire:navigate class="block hover:bg-gray-50 transition rounded-md">
                    <div class="p-4 border-b">
                        <div class="flex justify-between items-center gap-3">
                            <div class="min-w-0">
                                <h2 class="text-lg font-semibold text-gray-900">
                                    Invoice #{{ $invoice->invoice_number }}
                                </h2>
                                <p class="text-sm text-gray-600">
                                    Issued {{ $invoice->issue_date?->format('M j, Y') }} &middot; Due {{ $invoice->due_date?->format('M j, Y') }}
                                </p>
                            </div>
                            <div class="flex flex-col items-end gap-1 shrink-0">
                                <span class="inline-block rounded-md px-2 py-1 text-xs font-semibold {{ $invoice->status->colorClass() }}">
                                    {{ $invoice->status->value }}
                                </span>
                                <span class="text-sm font-semibold text-gray-900">${{ number_format((float) $invoice->total, 2) }}</span>
                            </div>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        @if ($invoices->hasPages())
            <div class="mt-6">{{ $invoices->links() }}</div>
        @endif
    @endif
</div>
@endsection
