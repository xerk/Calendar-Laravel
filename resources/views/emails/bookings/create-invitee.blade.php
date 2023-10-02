<x-mail::message>
Dear **{{ $invitee_name }}**,

We are pleased to inform you that your appointment with {{ $calendar->name }} has been successfully booked on Grandcalendar.io.

Your appointment details are as follows:

- When: {{ $bookings->date }} Time: {{ $bookings->start }} {{ $bookings->timezone }}
- How you will meet: {{ $bookings->getLocation(true) }}
- With: {{ $calendar->user->name }}

Booking status: {{ $bookings->is_confirmed ? 'Confirmed' : 'Waiting for confirmation' }}

We recommend that you arrive 10-15 minutes early for your appointment to ensure that everything runs smoothly.

To Cancel {{ $bookings->cancellationLink() }}
To Reschedule {{ $bookings->reschedulingLink() }}

If you have any questions or concerns, please do not hesitate to contact us.

Best regards,

The Grandcalendar.io Team
</x-mail::message>
