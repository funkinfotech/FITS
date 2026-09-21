Payment Received
{{ $invoice->from_business_name }} — Admin Alert

Invoice: {{ $invoice->invoice_number }}
Receipt: {{ $payment->receipt_number }}
Company: {{ $invoice->bill_to_name }}
Method: {{ $payment->method->value }}
@if ($payment->reference)
Reference: {{ $payment->reference }}
@endif
Amount Paid: ${{ number_format($payment->amount, 2) }}

View payment: {{ $paymentUrl }}
