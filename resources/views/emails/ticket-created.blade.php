<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
    <title>[Ticket #{{ $ticket->ticket_number }}] {{ $ticket->subject }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #111827;">
    <div style="max-width: 600px; margin: 0 auto; padding: 24px;">
        <p>A support ticket has been opened on your behalf:</p>

        <p style="white-space: pre-line;">{{ $ticket->message }}</p>

        <p>
            &mdash;<br>
            Ticket #{{ $ticket->ticket_number }}: {{ $ticket->subject }}
        </p>

        <p>
            <a href="{{ $ticketUrl }}" style="display: inline-block; padding: 10px 18px; background-color: #052a44; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 14px;">
                View Ticket
            </a>
        </p>

        <p style="color: #6b7280; font-size: 13px;">
            Reply to this email to add to the conversation on this ticket.
        </p>
    </div>
</body>
</html>
