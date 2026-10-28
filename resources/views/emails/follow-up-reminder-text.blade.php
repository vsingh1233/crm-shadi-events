Shadi Events CRM
{!! $heading !!}

Hello {!! $recipientName !!},
Please follow up with {!! $clientName !!}@if ($coupleName) regarding {!! $coupleName !!}{{ "'s wedding" }}@endif{{ '.' }}

FOLLOW-UP
Scheduled for: {!! $dueAt !!}
Responsible member: {!! $recipientName !!}
Purpose / notes:
{!! $notes !!}

LEAD DETAILS
@foreach ($details as $label => $value)
{!! $label !!}: {!! $value ?: 'Not provided' !!}
@endforeach

Open lead in CRM:
{!! $leadUrl !!}

Complete or cancel this follow-up in the CRM to stop reminders.
Pending follow-ups are reminded daily at 10 AM IST while the lead remains open.
