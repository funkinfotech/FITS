<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: Helvetica, Arial, sans-serif;
            color: #1f2937;
            font-size: 12px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
        }
        .header-table td {
            vertical-align: top;
        }
        .brand-name {
            font-size: 16px;
            font-weight: bold;
            color: #052a44;
        }
        .muted {
            color: #6b7280;
        }
        .invoice-title {
            font-size: 26px;
            font-weight: bold;
            color: #052a44;
            text-align: right;
        }
        .panel {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 12px;
        }
        .panel-label {
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #052a44;
            margin-bottom: 4px;
        }
        .line-items th {
            background-color: #052a44;
            color: #ffffff;
            text-align: left;
            padding: 8px;
            font-size: 11px;
            text-transform: uppercase;
        }
        .line-items td {
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        .line-items .amount-col {
            text-align: right;
            white-space: nowrap;
        }
        .total-row td {
            font-weight: bold;
            font-size: 14px;
            border-top: 2px solid #052a44;
            border-bottom: none;
        }
        .footer {
            margin-top: 24px;
            font-size: 11px;
            color: #374151;
        }
    </style>
</head>
<body>

<table class="header-table">
    <tr>
        <td width="60%">
            @if ($logoDataUri)
                <img src="{{ $logoDataUri }}" alt="{{ $invoice->from_business_name }}" height="40" style="margin-bottom: 8px;">
            @endif
            <div class="brand-name">{{ $invoice->from_business_name }}</div>
            @if ($invoice->from_address)
                <div class="muted">{{ $invoice->from_address }}</div>
            @endif
            @if ($invoice->from_email)
                <div class="muted">{{ $invoice->from_email }}</div>
            @endif
            @if ($invoice->from_phone)
                <div class="muted">{{ $invoice->from_phone }}</div>
            @endif
            @if ($invoice->from_tax_id)
                <div class="muted">Tax ID: {{ $invoice->from_tax_id }}</div>
            @endif
        </td>
        <td width="40%">
            <div class="invoice-title">INVOICE</div>
            <div class="muted" style="text-align: right;">{{ $invoice->invoice_number }}</div>
        </td>
    </tr>
</table>

<table style="margin-top: 24px;">
    <tr>
        <td width="50%" style="padding-right: 8px;">
            <div class="panel">
                <div class="panel-label">Bill To</div>
                <div>{{ $invoice->bill_to_name }}</div>
                @if ($invoice->bill_to_address)
                    <div class="muted">{{ $invoice->bill_to_address }}</div>
                @endif
            </div>
        </td>
        <td width="50%" style="padding-left: 8px;">
            <div class="panel">
                <div class="panel-label">Invoice Details</div>
                <table>
                    <tr>
                        <td class="muted">Issue Date</td>
                        <td>{{ $invoice->issue_date?->format('M j, Y') }}</td>
                    </tr>
                    <tr>
                        <td class="muted">Due Date</td>
                        <td>{{ $invoice->due_date?->format('M j, Y') }}</td>
                    </tr>
                    <tr>
                        <td class="muted">Status</td>
                        <td>{{ $invoice->status->value }}</td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<table class="line-items" style="margin-top: 24px;">
    <thead>
        <tr>
            <th>Description</th>
            <th style="text-align: right;">Qty</th>
            <th style="text-align: right;">Unit Price</th>
            <th style="text-align: right;">Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($invoice->lineItems as $item)
            <tr>
                <td>{{ $item->description }}</td>
                <td class="amount-col">{{ number_format($item->quantity, 2) }}</td>
                <td class="amount-col">${{ number_format($item->unit_price, 2) }}</td>
                <td class="amount-col">${{ number_format($item->amount, 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="3" style="text-align: right;">Total</td>
            <td class="amount-col">${{ number_format($invoice->total, 2) }}</td>
        </tr>
    </tbody>
</table>

@if ($invoice->terms || $invoice->notes || $invoice->from_bank_details)
    <div class="footer">
        @if ($invoice->terms)
            <p><strong>Terms:</strong> {{ $invoice->terms }}</p>
        @endif
        @if ($invoice->notes)
            <p><strong>Notes:</strong> {{ $invoice->notes }}</p>
        @endif
        @if ($invoice->from_bank_details)
            <p><strong>Payment Instructions:</strong><br>{{ $invoice->from_bank_details }}</p>
        @endif
    </div>
@endif

</body>
</html>
