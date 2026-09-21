<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
    <title>Payment Receipt {{ $payment->receipt_number }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6;">
    <tr>
        <td align="center" style="padding: 32px 16px;">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%;">

                <tr>
                    <td style="padding-bottom: 20px;">
                        <a href="{{ url('/') }}" style="text-decoration: none;">
                            <img src="{{ asset('images/funkit-logo.png') }}" alt="{{ $invoice->from_business_name }}" width="28" height="28" style="vertical-align: middle; border-radius: 6px;">
                            <span style="vertical-align: middle; margin-left: 8px; font-size: 15px; font-weight: 600; color: #052a44; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">{{ $invoice->from_business_name }}</span>
                        </a>
                    </td>
                </tr>

                <tr>
                    <td style="background-color: #ffffff; border-radius: 10px; padding: 28px;">
                        <p style="margin: 0 0 4px; font-size: 11px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #15803d;">Payment Received</p>
                        <h1 style="margin: 0 0 4px; font-size: 20px; font-weight: 700; color: #111827;">{{ $payment->receipt_number }}</h1>
                        <p style="margin: 0 0 20px; font-size: 13px; color: #6b7280;">For Invoice {{ $invoice->invoice_number }}</p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 20px;">
                            <tr>
                                <td style="font-size: 13px; color: #6b7280; padding: 4px 0;">Date Paid</td>
                                <td style="font-size: 13px; color: #1f2937; text-align: right; padding: 4px 0;">{{ $payment->paid_date?->format('M j, Y') }}</td>
                            </tr>
                            <tr>
                                <td style="font-size: 13px; color: #6b7280; padding: 4px 0;">Method</td>
                                <td style="font-size: 13px; color: #1f2937; text-align: right; padding: 4px 0;">{{ $payment->method->value }}</td>
                            </tr>
                            <tr>
                                <td style="font-size: 15px; font-weight: 700; color: #052a44; padding: 8px 0 0; border-top: 1px solid #e5e7eb;">Amount Paid</td>
                                <td style="font-size: 15px; font-weight: 700; color: #052a44; text-align: right; padding: 8px 0 0; border-top: 1px solid #e5e7eb;">${{ number_format($payment->amount, 2) }}</td>
                            </tr>
                        </table>

                        <p style="margin: 0; font-size: 13px; color: #6b7280;">Thank you! Your receipt is attached to this email as a PDF for your records.</p>
                    </td>
                </tr>

                <tr>
                    <td style="padding-top: 20px;">
                        <p style="margin: 0; font-size: 12px; color: #9ca3af;">
                            Questions about this payment? Reply to this email.
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
