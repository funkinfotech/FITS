@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto pt-12 py-10 px-6">

    <a href="{{ route('invoices.index') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">
        &larr; Back to invoices
    </a>

    <div class="mt-4 bg-white shadow-md rounded-lg p-6 sm:p-8">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-sm text-gray-500">Invoice #{{ $invoice->invoice_number }}</p>
                <h1 class="text-2xl font-bold text-gray-900">{{ $invoice->from_business_name }}</h1>
                <p class="mt-1 text-xs text-gray-400">
                    Issued {{ $invoice->issue_date?->format('M j, Y') }} &middot; Due {{ $invoice->due_date?->format('M j, Y') }}
                </p>
            </div>

            <span class="inline-block px-3 py-1 rounded-full text-sm font-semibold {{ $invoice->status->colorClass() }}">
                {{ $invoice->status->value }}
            </span>
        </div>

        <table class="w-full mt-6 text-sm">
            <thead>
                <tr class="text-left text-gray-500 border-b">
                    <th class="py-2">Description</th>
                    <th class="py-2 text-right">Qty</th>
                    <th class="py-2 text-right">Unit Price</th>
                    <th class="py-2 text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->lineItems as $item)
                    <tr class="border-b">
                        <td class="py-2">{{ $item->description }}</td>
                        <td class="py-2 text-right">{{ number_format($item->quantity, 2) }}</td>
                        <td class="py-2 text-right">${{ number_format($item->unit_price, 2) }}</td>
                        <td class="py-2 text-right">${{ number_format($item->amount, 2) }}</td>
                    </tr>
                @endforeach
                <tr class="font-bold">
                    <td colspan="3" class="py-2 text-right">Total</td>
                    <td class="py-2 text-right">${{ number_format((float) $invoice->total, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ route('invoices.pdf', $invoice) }}"
               class="inline-block px-5 py-3 bg-primary text-white font-semibold rounded shadow hover:bg-opacity-90">
                Download PDF
            </a>
        </div>

        @if ($receipt)
            <div class="mt-6 border-t pt-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-2">Payment Receipt</h2>
                <p class="text-sm text-gray-600">
                    Receipt #{{ $receipt->receipt_number }} &middot; Paid {{ $receipt->paid_date?->format('M j, Y') }} via {{ $receipt->method->value }}
                </p>
                <a href="{{ route('invoices.receipt', $invoice) }}"
                   class="mt-3 inline-block px-5 py-3 bg-primary text-white font-semibold rounded shadow hover:bg-opacity-90">
                    Download Receipt
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
