<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
    <title>Re: [Ticket #{{ $ticket->ticket_number }}] {{ $ticket->subject }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6;">
    <tr>
        <td align="center" style="padding: 32px 16px;">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%;">

                <tr>
                    <td style="padding-bottom: 20px;">
                        <a href="{{ url('/') }}" style="text-decoration: none;">
                            <img src="{{ asset('images/funkit-logo.png') }}" alt="FunkIT" width="28" height="28" style="vertical-align: middle; border-radius: 6px;">
                            <span style="vertical-align: middle; margin-left: 8px; font-size: 15px; font-weight: 600; color: #052a44; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">FunkIT HelpDesk</span>
                        </a>
                    </td>
                </tr>

                <tr>
                    <td style="background-color: #ffffff; border-radius: 10px; padding: 28px;">
                        <p style="margin: 0 0 4px; font-size: 11px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #052a44;">Ticket received</p>
                        <h1 style="margin: 0 0 4px; font-size: 20px; font-weight: 700; color: #111827;">{{ $ticket->subject }}</h1>
                        <p style="margin: 0 0 20px; font-size: 13px; color: #6b7280;">Ticket #{{ $ticket->ticket_number }}</p>

                        <p style="margin: 0 0 16px; font-size: 15px; line-height: 1.6; color: #1f2937;">
                            @if ($firstName)
                                Hi {{ $firstName }},
                            @else
                                Hi there,
                            @endif
                        </p>

                        <p style="margin: 0 0 20px; font-size: 15px; line-height: 1.6; color: #1f2937;">
                            Thanks for reaching out. We've received your message and opened the ticket above. A technician will be assigned to it shortly and will follow up with you soon.
                        </p>

                        <a href="{{ $ticketUrl }}" style="display: inline-block; padding: 10px 20px; background-color: #052a44; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: 600;">
                            View Ticket
                        </a>
                    </td>
                </tr>

                <tr>
                    <td style="padding-top: 20px;">
                        <p style="margin: 0; font-size: 12px; color: #9ca3af;">
                            Reply to this email to add to the conversation on this ticket.
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
