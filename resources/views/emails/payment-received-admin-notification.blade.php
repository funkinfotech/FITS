<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
    <title>Payment received — {{ $payment->receipt_number }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6;">
    <tr>
        <td align="center" style="padding: 32px 16px;">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%;">

                <tr>
                    <td style="padding-bottom: 20px;">
                        <span style="font-size: 15px; font-weight: 600; color: #052a44;">{{ $invoice->from_business_name }} — Admin Alert</span>
                    </td>
                </tr>

                <tr>
                    <td style="background-color: #ffffff; border-radius: 10px; padding: 28px;">
                        <p style="margin: 0 0 4px; font-size: 11px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #15803d;">Payment Received</p>
                        <h1 style="margin: 0 0 4px; font-size: 20px; font-weight: 700; color: #111827;">{{ $invoice->invoice_number }}</h1>
                        <p style="margin: 0 0 20px; font-size: 13px; color: #6b7280;">Receipt {{ $payment->receipt_number }}</p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 20px;">
                            <tr>
                                <td style="font-size: 13px; color: #6b7280; padding: 4px 0;">Company</td>
                                <td style="font-size: 13px; color: #1f2937; text-align: right; padding: 4px 0;">{{ $invoice->bill_to_name }}</td>
                            </tr>
                            <tr>
                                <td style="font-size: 13px; color: #6b7280; padding: 4px 0;">Method</td>
                                <td style="font-size: 13px; color: #1f2937; text-align: right; padding: 4px 0;">{{ $payment->method->value }}</td>
                            </tr>
                            @if ($payment->reference)
                                <tr>
                                    <td style="font-size: 13px; color: #6b7280; padding: 4px 0;">Reference</td>
                                    <td style="font-size: 13px; color: #1f2937; text-align: right; padding: 4px 0;">{{ $payment->reference }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td style="font-size: 15px; font-weight: 700; color: #052a44; padding: 8px 0 0; border-top: 1px solid #e5e7eb;">Amount Paid</td>
                                <td style="font-size: 15px; font-weight: 700; color: #052a44; text-align: right; padding: 8px 0 0; border-top: 1px solid #e5e7eb;">${{ number_format($payment->amount, 2) }}</td>
                            </tr>
                        </table>

                        <a href="{{ $paymentUrl }}" style="display: inline-block; background-color: #052a44; color: #ffffff; text-decoration: none; font-size: 14px; font-weight: 600; padding: 10px 20px; border-radius: 8px;">View Payment</a>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
