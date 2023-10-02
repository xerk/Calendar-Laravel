<x-mail::message>
Dear **{{ $user->name }}**,

We are pleased to inform you that a new appointment has been booked on your calendar {{ $calendar->name }} on Grandcalendar.io. The appointment details are as follows:

- When: {{ $booking->date }} Time: {{ $booking->start }} {{ $booking->timezone }}
- How you will meet:{{ $booking->getLocation(true) }}
- With: {{ $booking->invitee_name }}
	- Email: {{ $booking->invitee_email }}
	- phone: {{ $booking->invitee_phone }}
    - Note: {{ $booking->invitee_note }}
    @if($booking->invitee_other)
    @foreach($booking->invitee_other as $invitee)
    - {{ $invitee['value'] }}
    @endforeach
    @endif
- Booking status: {{ $booking->is_confirmed ? 'Confirmed' : 'Waiting for confirmation' }}

@if($booking->is_confirmed)
Please click here to confirm the booking
@else
Please ensure that you are available at the scheduled time and date
@endif

If you need to make any changes to the appointment, you can do so by logging in to your Grandcalendar.io account and accessing your calendar.

Thank you for using Grandcalendar.io. If you have any questions or concerns, please do not hesitate to contact us.

Best regards,

The Grandcalendar.io Team
</x-mail::message>
