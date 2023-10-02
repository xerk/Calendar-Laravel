<?php

namespace App\Services\Google;

use DateTime;
use Carbon\Carbon;
use App\Models\Booking;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Models\EventIntegration;
use Illuminate\Support\Facades\Http;

class Event
{
    public $client;
    public $user;
    public $base_url = 'https://www.googleapis.com/calendar/v3/calendars/';

    public $googleEvent;

    /** @var string */
    protected $calendarId;

    /** @var array */
    protected $attendees;

    /** @var bool */
    protected $hasMeetLink = false;

    protected $event_id = null;

    public function __construct($user, $event_id = null)
    {
        $this->user = $user;
        $this->attendees = [];

        if ($event_id) {
            $this->event_id = $event_id;
        }
        // Set base url to client
        $this->client = Http::baseUrl($this->base_url);
        // Add header to client
        $this->client = Http::withHeaders(['Authorization' => 'Bearer ' . $this->user->access_token]);

        $this->googleEvent = [];

    }

    public function addAttendee(array $attendee)
    {
        $this->attendees = [
            'email' => $attendee['email'],
            'displayName' => $attendee['name'] ?? null,
            'responseStatus' => $attendee['status'] ?? 'needsAction',
            'comment' => $attendee['comment'] ?? null,
        ];

        // push attendee to attendees array
        $this->setAttendees($this->attendees);

    }

    public function setAttendees(array $attendees)
    {
        $this->googleEvent['attendees'][] = $attendees;
    }

    public function addMeetLink()
    {
        $conferenceData = [
            'createRequest' => [
                'requestId' => Str::random(10),
                'conferenceSolutionKey' => [
                    'type' => 'hangoutsMeet',
                ],
            ],
        ];

        $this->googleEvent['conferenceData'] = $conferenceData;

        $this->hasMeetLink = true;
    }

    public function save(Booking $booking):self
    {
        $optParams = [];
        if ($this->hasMeetLink) {
            $optParams['conferenceDataVersion'] = 1;
        }

        // dd($this, 'create', $this->calendarId, $optParams);
        $event = $this->call($optParams);

        if ($booking->location && $booking->location['kind'] == 'google_meet') {
            $booking->location = [
                'id' => $booking->location['id'],
                'kind' => $booking->location['kind'],
                'link' => json_decode($event)->hangoutLink ?? null,
                'location' => $booking->location['location'],
                'position' => $booking->location['position'],
                'phone_number' => $booking->location['phone_number'],
                'additional_info' => $booking->location['additional_info'],
                'conferencing_configured' => $booking->location['conferencing_configured'],
            ];
        }
        \Log::info('Event created', ['event' => $event]);

        $this->storeEventData($booking, $event);

        $booking->save();

        return $this;
    }

    private function storeEventData(Booking $booking, $event)
    {
        $event = json_decode($event);
        if (isset($event->id))  {
            $eventIntegration = new EventIntegration();
            $eventIntegration->booking_id = $booking->id;
            $eventIntegration->event_id = $event->id;
            $eventIntegration->provider_account_id = $this->user->id;
            $eventIntegration->provider_type = 'google';
            $eventIntegration->response = $event;
            $eventIntegration->status = 'success';
            $eventIntegration->save();
        }
    }

    private function call($optParams = [])
    {
        \Log::info('Event updated', ['event' => $this->googleEvent]);
        // php html query builder
        $query = http_build_query($optParams);
        $url = $this->base_url . $this->user->email . '/events?' . $query;
        
        if ($this->event_id) {
            $url = $this->base_url . $this->user->email . '/events/'.$this->event_id.'?' . $query;
            \Log::info('Event updated', ['url' => $url]);
            return $this->client->put($url, $this->googleEvent)->body();
        }

        return $this->client->post($url, $this->googleEvent)->body();
    }

    public function delete()
    {
        $url = $this->base_url . $this->user->email . '/events/'. $this->event_id;

        return $this->client->delete($url);
    }

    protected function getFieldName(string $name): string
    {
        return [
            'name' => 'summary',
            'startDate' => 'start.date',
            'endDate' => 'end.date',
            'startDateTime' => 'start.dateTime',
            'endDateTime' => 'end.dateTime',
        ][$name] ?? $name;
    }

    public function __get($name)
    {
        $name = $this->getFieldName($name);

        $value = Arr::get($this->googleEvent, $name);

        if (in_array($name, ['start.date', 'end.date']) && $value) {
            $value = Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        }

        if (in_array($name, ['start.dateTime', 'end.dateTime']) && $value) {
            $value = Carbon::createFromFormat(DateTime::RFC3339, $value);
        }

        return $value;
    }

    public function __set($name, $value)
    {
        $name = $this->getFieldName($name);

        if (in_array($name, ['start.date', 'end.date', 'start.dateTime', 'end.dateTime'])) {
            $this->setDateProperty($name, $value);

            return;
        }

        Arr::set($this->googleEvent, $name, $value);
    }

    protected function setDateProperty(string $name, CarbonInterface $date)
    {
        $eventDateTime = [];
        if (in_array($name, ['start.date', 'end.date'])) {
            $eventDateTime['dateTime'] = $date->format('Y-m-d');
            $eventDateTime['timezone'] = (string) $date->getTimezone();
        }

        if (in_array($name, ['start.dateTime', 'end.dateTime'])) {
            $eventDateTime['dateTime'] = $date->format(DateTime::RFC3339);
            $eventDateTime['timezone'] = (string) $date->getTimezone();
        }

        if (Str::startsWith($name, 'start')) {
            $this->googleEvent['start'] = $eventDateTime;
        }

        if (Str::startsWith($name, 'end')) {
            $this->googleEvent['end'] = $eventDateTime;
        }
    }


}
