Invoice {{ $invoice->invoice_number }}
{{ $invoice->from_business_name }}

@if ($customMessage)
{{ $customMessage }}

@endif
Bill To: {{ $invoice->bill_to_name }}
Issue Date: {{ $invoice->issue_date?->format('M j, Y') }}
Due Date: {{ $invoice->due_date?->format('M j, Y') }}
Total Due (this invoice): ${{ number_format($invoice->total, 2) }}
@if ($invoice->previous_balance > 0)
Previous Balance (other open invoices): ${{ number_format($invoice->previous_balance, 2) }}
Total Account Balance Due: ${{ number_format($invoice->total_balance_due, 2) }}
@endif

The full invoice is attached to this email as a PDF.

Questions about this invoice? Reply to this email.
