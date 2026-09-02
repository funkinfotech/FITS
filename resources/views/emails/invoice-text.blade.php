Invoice {{ $invoice->invoice_number }}
{{ $invoice->from_business_name }}

@if ($customMessage)
{{ $customMessage }}

@endif
Bill To: {{ $invoice->bill_to_name }}
Issue Date: {{ $invoice->issue_date?->format('M j, Y') }}
Due Date: {{ $invoice->due_date?->format('M j, Y') }}
Total Due: ${{ number_format($invoice->total, 2) }}

The full invoice is attached to this email as a PDF.

Questions about this invoice? Reply to this email.
