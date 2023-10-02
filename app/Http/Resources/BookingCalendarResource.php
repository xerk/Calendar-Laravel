<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use App\Models\Booking;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingCalendarResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $dayIds = [
            'Sunday' => 0,
            'Monday' => 1,
            'Tuesday' => 2,
            'Wednesday' => 3,
            'Thursday' => 4,
            'Friday' => 5,
            'Saturday' => 6,
        ];
        $days = [];
        $availableDays = [];
        $dates = '';
        if($this->availability->data) {
            foreach($this->availability->data as $day) {
                if($day['enabled']) {
                    $days[] = $day['name'];
                    $availableDays[] = $dayIds[$day['name']];
                }
            }
            if(count($days)) {
                $dates = implode(', ', $days);
            }
        }

        $minDate = null;
        $expiredAt = null;
        if($this->invitees_can_schedule) {
            if($this->invitees_can_schedule['type'] == 1) {
                // if($this->invitees_can_schedule['into_the_future_type'] == 'business_days') {
                //     $expiredAt = now()->addWeekdays($this->invitees_can_schedule['into_the_future_days']);
                // } else {
                    $expiredAt = now()->addDays($this->invitees_can_schedule['into_the_future_days']);
                // }
            }

            if($this->invitees_can_schedule['type'] == 2) {
                $minDate = Carbon::parse($this->invitees_can_schedule['date_range']['startDate']);
                $expiredAt = Carbon::parse($this->invitees_can_schedule['date_range']['endDate']);
            }
        }

        $filteredProviders = [];

if ($this->user->providers !== null) { // Add this null check
    foreach ($this->user->providers->toArray() as $provider) {
        $conditionMet = false;

        if ($provider['provider'] === 'google' && $provider['meeting_type'] === 'google_meet') {
            $conditionMet = true;
        }

        if ($provider['provider'] === 'zoom' && $provider['meeting_type'] === 'zoom') {
            $conditionMet = true;
        }

        // Add the provider to the filtered list if any condition was met
        if ($conditionMet) {
            $filteredProviders[] = $provider;
        }
    }
}

$filteredLocations = [];

foreach ($this->locations as $location) {
    // Check if the location's kind matches the provider's meeting type
    if ($location['kind'] === 'google_meet' && !in_array('google_meet', array_column($filteredProviders, 'meeting_type'))) {
        continue; // Skip this location if it's a Google Meet location and not supported by any provider
    }

    if ($location['kind'] === 'zoom' && !in_array('zoom', array_column($filteredProviders, 'meeting_type'))) {
        continue; // Skip this location if it's a Zoom location and not supported by any provider
    }

    // If the location matches the provider's meeting type or is not associated with any meeting type, add it to the filtered list
    $filteredLocations[] = $location;
}

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'user' => $this->user,
            'is_on' => $this->is_on,
            'is_exceed_limit' => $this->is_exceed_limit,
            'availability' => $this->convertedAvailability($this->availability),
            'name' => $this->name,
            'dates' => $dates,
            'available_days' => $availableDays,
            'min_date' => $minDate,
            'expired_at_raw' => $expiredAt,
            'expired_at' => $expiredAt ? $expiredAt->toFormattedDateString() : null,
            'is_show_on_booking_page' => $this->is_show_on_booking_page,
            'welcome_message' => $this->welcome_message,
            'locations' => $filteredLocations,
            'requires_confirmation' => $this->requires_confirmation,
            'enable_signup_form_after_booking' => $this->enable_signup_form_after_booking,
            'time_slots_intervals' => (int) $this->time_slots_intervals,
            'duration' => $this->duration,
            'invitees_can_schedule' => $this->invitees_can_schedule,
            'buffer_time' => $this->buffer_time,
            'additional_questions' => $this->additional_questions,
            'disable_guests' => $this->disable_guests,
            'redirect_on_booking' => $this->redirect_on_booking,
            'created_at' => $this->created_at,
            'bookings' => Booking::where('calendar_id', $this->id)->where('cancelled_at', null)->get(),
        ];
    }

    public function convertedAvailability($availability)
    {
        // return $availability;

        $data = [];

        if($availability->data) {
            foreach($availability->data as $day) {
                $data[] = [
                    'enabled' => $day['enabled'],
                    'name' => $day['name'],
                    'slots' => $this->convertedSlots($day['slots'], $availability->timezone),
                ];
            }
        }

        return [
            'data' => $data,
            'id' => $availability->id,
            'name' => $availability->name,
            'timezone' => $availability->timezone,
            'user_id' => $availability->user_id,
        ];
    }

    public function convertedSlots($slots, $timezone)
    {
        $data = [];
        foreach ($slots as $slot) {
            // get Offset from current timezone
            // return 09:00 AM
            $slot['end']['label'] = Carbon::parse($slot['end']['label'], $timezone)->format('H:i P');
            $slot['start']['label'] = Carbon::parse($slot['start']['label'], $timezone)->format('H:i P');
            $data[] = $slot;
        }
        return $data;
    }
}
