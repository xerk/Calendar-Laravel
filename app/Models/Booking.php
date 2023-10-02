<?php

namespace App\Models;

use DateTime;
use DateTimeZone;
use Carbon\Carbon;
use App\Services\Zoom;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Services\Google\Event as GoogleEvent;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\IcalendarGenerator\Components\Event;
use Spatie\IcalendarGenerator\Components\Timezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\IcalendarGenerator\Enums\TimezoneEntryType;
use Spatie\IcalendarGenerator\Components\TimezoneEntry;
use Spatie\IcalendarGenerator\Enums\ParticipationStatus;
use Spatie\IcalendarGenerator\Components\Calendar as ICalendar;

class Booking extends Model
{
    use HasFactory, LogsActivity, SoftDeletes, Notifiable;

    // Notification email

    public function routeNotificationForMail($notification)
    {
        return $this->invitee_email;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'user_id',
                'calendar_id',
                'date',
                'date_time',
                'start',
                'end',
                'invitee_name',
                'invitee_email',
                'invitee_phone',
                'invitee_note',
                'timezone',
                'location',
                'other_invitees',
                'additional_answers',
                'meeting_notes',
                'is_confirmed',
                'cancelled_at',
                'expired_at',
                'rescheduled_at'
            ]);
        // Chain fluent methods for configuration options
    }

    protected $fillable = [
        'user_id',
        'calendar_id',
        'date',
        'date_time',
        'start',
        'end',
        'invitee_name',
        'invitee_email',
        'invitee_phone',
        'invitee_note',
        'timezone',
        'location',
        'other_invitees',
        'additional_answers',
        'meeting_notes',
        'is_confirmed',
        'cancelled_at',
        'expired_at',
        'rescheduled_at',
        "cancellation_reason",
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'location' => 'array',
        'other_invitees' => 'array',
        'additional_answers' => 'array',
        'date' => 'date',
        'is_confirmed' => 'boolean',
        'cancelled_at' => 'datetime',
        'expired_at' => 'datetime',
        'rescheduled_at' => 'datetime',
        'reschedule_token_expires_at' => 'datetime',
        'reschedule_data' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function calendar()
    {
        return $this->belongsTo(Calendar::class)->withTrashed();
    }

    /**
     * Generate ics file for booking unsing icalendar-generator
     * @return invite.ics
     */
    public function generateIcs()
    {

        $eventName = "Event Name: {$this->calendar->name}";
        // humanize date
        $eventDate = "Event Date: " . Carbon::parse($this->date_time)->setTimezone($this->timezone)->format('l, F j, Y \a\t g:i A');
        $eventLocation = $this->location ? "Location: {$this->getLocation(true)}\n\n" : '';
        $eventInviteeNote = $this->invitee_notes ? 'Please share anything that will help prepare for our meeting.: ' . $this->invitee_notes : '';
        $eventCancel = "Need to make changes to this event?\nCancel: ".$this->cancellationLink();
        $eventReschedule = "Reschedule: ". $this->reschedulingLink();
        $eventPoweredBy = "Powered by Grandcalendar.io";

        $eventDescription = "{$eventName}\n\n{$eventDate}\n\n{$eventLocation}{$eventInviteeNote}\n\n{$eventCancel}\n{$eventReschedule}\n\n{$eventPoweredBy}";

        $timezoneEntry = TimezoneEntry::create(
            TimezoneEntryType::daylight(),
            Carbon::parse($this->date_time),
            Carbon::now(new DateTimeZone($this->timezone))->format('P'),
            Carbon::now(new DateTimeZone($this->calendar->availability->timezone))->format('P'),
        );

        $timezone = Timezone::create($this->calendar->availability->timezone)
            ->entry($timezoneEntry);

        $event = Event::create($this->calendar->name);
        $event->createdAt(DateTime::createFromFormat('Y-m-d H:i:s', $this->created_at))
                    ->startsAt(
                        Carbon::parse($this->date_time)->setTimezone($this->timezone)
                    )
                    ->endsAt(
                        Carbon::parse($this->date_time)->setTimezone($this->timezone)->addMinutes($this->calendar->duration)
                    )
                    ->address($this->getLocation(true))
                    ->organizer($this->user->email, $this->user->name)
                    ->attendee($this->invitee_email, $this->invitee_name, ParticipationStatus::accepted(), true)
                    ->image(config('saas.app_logo_url'))
                    ->description($eventDescription);
                    if (!empty($this->other_invitees)) {
                        foreach ($this->other_invitees as $invitee) {
                            // slice the email to get the username
                            $usernameOfEmail = explode('@', $invitee['value'])[0];
                            $event->attendee($invitee['value'], $usernameOfEmail, $this->calendar->requires_confirmation ? ParticipationStatus::needs_action() : ParticipationStatus::accepted(), true);
                        }
                    }

        $calendar = ICalendar::create($this->calendar->name)
        ->timezone($timezone)
        ->productIdentifier('-//Grandcalendar//grandcalendar.io//EN')
        ->event($event)->get();

        return response()->streamDownload(function () use ($calendar) {
            echo $calendar;
        }, 'invite.ics');
    }

    public function eventIntegration()
    {
        return $this->hasOne(EventIntegration::class);
    }

    /**
     * Location of the booking
     * {"id": "d7cc077d-64f8-4ce9-8281-e0ab42abf2cd", "kind": "location", "link": "", "location": "https://www.tomorrowland.com/en/festival/welcome", "position": 0, "phone_number": "", "additional_info": "", "conferencing_configured": false}
     */
    public function getLocation($meet = false)
    {
        if (!$this->location) {
            return '';
        }
        switch ($this->location['kind']) {
            case 'location':
                return $this->location['location'];
                break;
            case 'link':
                return $this->location['link'];
                break;
            case 'outbound_call':
                return $this->location['phone_number'];
                break;
            case 'inbound_call':
                return $this->location['phone_number'];
                break;
            case 'google_meet':
                return $this->location['link'] ? $this->location['link'] : 'Google Meet (instructions in description)' ;
                // return $this->isGoogleMeet ? 'Google Meet (instructions in description)' : $this->location['link'];
                break;
            case 'zoom':
                // return $this->location['link'] ? $this->location['link'] : 'Google Meet (instructions in description)' ;
                return $this->location['link'];
                break;
            default:
                return $this->location['location'];
                break;
        }
    }


    public function googleCalendarEvent($update = false) {
        $user = $this->user->googleProvider;

        if (!$user) {
            return;
        }

        // Refresh token if expired
        if ($user->tokenHasExpired()) {
            $user->refreshTokenGoogle();
        }

        if ($update) {
            $event = new GoogleEvent($user, $this->eventIntegration->event_id);
        } else {
            $event = new GoogleEvent($user);
        }

        // Human Resource Interview between Ahmed Mamdouh and Dacey Luna
        $event->name = $this->calendar->name . ' between ' . $this->user->name . ' and ' . $this->invitee_name;
        $event->description = $this->getEventDescription();
        $event->startDateTime = Carbon::parse($this->date_time)->setTimezone($this->timezone);
        $event->endDateTime = Carbon::parse($this->date_time)->setTimezone($this->timezone)->addMinutes($this->calendar->duration);
        $event->location = $this->getLocation();
        $event->sendUpdates = 'all';
        $event->addAttendee([
            'email' => $this->user->email,
            'name' => $this->user->name,
            'status' => $this->calendar->requires_confirmation ? 'needsAction' : 'accepted',
        ]);
        $event->addAttendee([
            'email' => $this->invitee_email,
            'name' => $this->invitee_name,
            'status' => $this->calendar->requires_confirmation ? 'needsAction' : 'accepted',
            'comment' => $this->invitee_note,
        ]);
        if (!empty($this->other_invitees)) {
            foreach ($this->other_invitees as $invitee) {
                $event->addAttendee([
                    'email' => $invitee['value'],
                    'status' => $this->calendar->requires_confirmation ? 'needsAction' : 'accepted',
                ]);
            }
        }

        if ($user->meeting_type === 'google_meet' && $this->isGoogleMeet()) {
            $event->addMeetLink();
        }

        $event->save($this);
    }

    private function isGoogleMeet() {
        return $this->location && $this->location['kind'] === 'google_meet';
    }

    private function isZoomMeeting() {
        return $this->location && $this->location['kind'] === 'zoom';
    }

    private function getEventDescription($status = null) {
        $eventName = "Event Name: {$this->calendar->name}\n\n";
        $eventDate = "Event Date: " . Carbon::parse($this->date_time)->setTimezone($this->timezone)->format('l, F j, Y \a\t g:i A') . "\n\n";
        if ($status === 'declined') {
            $eventDate = "";
        }

        $eventLocation = $this->location ? "Location: {$this->getLocation()}\n\n" : '';
        $eventInviteeNote = $this->invitee_notes ? 'Please share anything that will help prepare for our meeting.: ' . $this->invitee_notes . "\n\n" : '';
        if ($status === 'declined') {
            $eventCancel = "";
            $eventReschedule = "";
            $eventCancellationReason = "Cancellation Reason: " . $this->cancellation_reason . "\n\n";
        } else {
            $eventCancel = "Need to make changes to this event?\nCancel: " . $this->cancellationLink() . "\n";
            $eventReschedule = "Reschedule: " . $this->reschedulingLink() . "\n\n";
            $eventCancellationReason = "";
        }
        $eventPoweredBy = "Powered by Grandcalendar.io";

        return "{$eventName}{$eventDate}{$eventLocation}{$eventInviteeNote}{$eventCancel}{$eventReschedule}{$eventCancellationReason}{$eventPoweredBy}";
    }

    public function cancellationLink() {
        $app = config('saas.app_url');

        return "{$app}/cancellations/{$this->uid}";
    }
    public function reschedulingLink() {
        $app = config('saas.app_url');

        return "{$app}/reschedulings/{$this->uid}";
    }

    public function googleDeleteEvent() {
        $user = $this->user->googleProvider;

        if (!$user) {
            return;
        }

        // Refresh token if expired
        if ($user->tokenHasExpired()) {
            $user->refreshTokenGoogle();
        }

        $event = new GoogleEvent($user, $this->eventIntegration->event_id);
        $event->delete();
    }

    public function googleCancelEvent($status = null) {
        $user = $this->user->googleProvider;

        if (!$user) {
            return;
        }

        // Refresh token if expired
        if ($user->tokenHasExpired()) {
            $user->refreshTokenGoogle();
        }

        $event = new GoogleEvent($user, $this->eventIntegration->event_id);

        $event->name = 'Canceled: ' . $this->user->name . ' and ' . $this->invitee_name;
        $event->description = $this->getEventDescription('declined');
        $event->startDateTime = Carbon::parse($this->date_time)->setTimezone($this->timezone);
        $event->endDateTime = Carbon::parse($this->date_time)->setTimezone($this->timezone)->addMinutes($this->calendar->duration);
        // $event->location = $this->getLocation();
        $event->sendUpdates = 'all';
        $event->addAttendee([
            'email' => $this->user->email,
            'name' => $this->user->name,
            'status' => 'declined',
        ]);
        $event->addAttendee([
            'email' => $this->invitee_email,
            'name' => $this->invitee_name,
            'status' => 'declined',
            'comment' => $this->invitee_note,
        ]);
        if (!empty($this->other_invitees)) {
            foreach ($this->other_invitees as $invitee) {
                $event->addAttendee([
                    'email' => $invitee['value'],
                    'status' => $status,
                ]);
            }
        }

        $event->save($this);
    }

    public function zoomMeeting() {
        return "fdsfd";
    }
}
