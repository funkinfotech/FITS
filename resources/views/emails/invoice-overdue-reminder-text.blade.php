Payment Reminder: Invoice {{ $invoice->invoice_number }}
{{ $invoice->from_business_name }}

This invoice is now past due. If a balance remains outstanding when the next
billing cycle runs, it will be carried forward and added to that invoice.

Bill To: {{ $invoice->bill_to_name }}
Was Due: {{ $invoice->due_date?->format('M j, Y') }}
Total Due: ${{ number_format($invoice->total, 2) }}

The full invoice is attached to this email as a PDF.

Questions about this invoice? Reply to this email.
