Overdue Invoice: {{ $invoice->invoice_number }}

Company: {{ $invoice->bill_to_name }}
Was Due: {{ $invoice->due_date?->format('M j, Y') }}
Total Due: ${{ number_format($invoice->total, 2) }}

This customer has not paid by the due date. Open the invoice in the admin
panel to send them a reminder, or ignore this if you don't want to be
nagged again:

{{ $invoiceUrl }}
