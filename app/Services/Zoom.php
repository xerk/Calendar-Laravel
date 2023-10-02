<?php

namespace App\Services;

use DateTime;
use Carbon\Carbon;
use App\Models\Booking;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Models\EventIntegration;
use Illuminate\Support\Facades\Http;

class Zoom
{
    public $client;
    public $user;
    public $base_url = 'https://api.zoom.us/v2/';

    public $zoomMeeting;

    public function __construct($user)
    {
        $this->user = $user;

        // Set base url to client
        $this->client = Http::baseUrl($this->base_url);
        // Add header to client
        $this->client = Http::withHeaders(['Authorization' => 'Bearer ' . $this->user->access_token]);

        $this->zoomMeeting = [];

    }

    public function save(Booking $booking):self
    {
        $optParams = [];

        $meeting = $this->call($optParams);

        $meeting = json_decode($meeting);

        if ($booking->location && $booking->location['kind'] == 'zoom') {
            $booking->location = [
                'id' => $booking->location['id'],
                'kind' => $booking->location['kind'],
                'link' => $meeting->join_url ?? null,
                'location' => $booking->location['location'],
                'position' => $booking->location['position'],
                'phone_number' => $booking->location['phone_number'],
                'additional_info' => $booking->location['additional_info'],
                'conferencing_configured' => $booking->location['conferencing_configured'],
            ];
        }
        \Log::info('Event created', ['event' => $meeting]);

        $this->storeMeetingData($booking, $meeting);

        $booking->save();

        return $this;
    }

    private function storeMeetingData(Booking $booking, $meeting)
    {
        if (isset($meeting->id))  {
            $eventIntegration = new EventIntegration();
            $eventIntegration->booking_id = $booking->id;
            $eventIntegration->event_id = $meeting->id;
            $eventIntegration->provider_account_id = $this->user->id;
            $eventIntegration->provider_type = 'zoom';
            $eventIntegration->response = $meeting;
            $eventIntegration->status = 'success';
            $eventIntegration->save();
        }
    }

    private function call($optParams = [])
    {
        \Log::info('Event updated', ['event' => $this->zoomMeeting]);
        // php html query builder
        $query = http_build_query($optParams);
        $url = $this->base_url . 'users/' . $this->user->email . '/meetings?' . $query;

        return $this->client->post($url, $this->zoomMeeting)->body();
    }

    protected function getFieldName(string $name): string
    {
        return [
            'name' => 'agenda',
            'default_password' => 'default_password',
            'startTime' => 'start_time',
        ][$name] ?? $name;
    }

    public function __get($name)
    {
        $name = $this->getFieldName($name);

        $value = Arr::get($this->zoomMeeting, $name);

        return $value;
    }

    public function __set($name, $value)
    {
        $name = $this->getFieldName($name);

        Arr::set($this->zoomMeeting, $name, $value);
    }


}
