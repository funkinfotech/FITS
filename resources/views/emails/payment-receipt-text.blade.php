Payment Receipt {{ $payment->receipt_number }}
{{ $invoice->from_business_name }}

For Invoice: {{ $invoice->invoice_number }}
Date Paid: {{ $payment->paid_date?->format('M j, Y') }}
Method: {{ $payment->method->value }}
Amount Paid: ${{ number_format($payment->amount, 2) }}

Thank you! Your receipt is attached to this email as a PDF for your records.

Questions about this payment? Reply to this email.
