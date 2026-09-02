FunkIT HelpDesk
===============

TICKET RECEIVED
Ticket #{{ $ticket->ticket_number }}: {{ $ticket->subject }}

@if ($firstName)
Hi {{ $firstName }},
@else
Hi there,
@endif

Thanks for reaching out. We've received your message and opened the ticket above. A technician will be assigned to it shortly and will follow up with you soon.

---
View ticket: {{ $ticketUrl }}

Reply to this email to add to the conversation on this ticket.
