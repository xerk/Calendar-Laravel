<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use App\Models\Booking;
use App\Models\Calendar;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingCalendarV2Resource extends JsonResource
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
        if ($this->availability->data) {
            foreach ($this->availability->data as $day) {
                if ($day['enabled']) {
                    $days[] = $day['name'];
                    $availableDays[] = $dayIds[$day['name']];
                }
            }
            if (count($days)) {
                $dates = implode(', ', $days);
            }
        }

        $minDate = null;
        $expiredAt = null;
        if ($this->invitees_can_schedule) {
            if ($this->invitees_can_schedule['type'] == 1) {
                // if($this->invitees_can_schedule['into_the_future_type'] == 'business_days') {
                //     $expiredAt = now()->addWeekdays($this->invitees_can_schedule['into_the_future_days']);
                // } else {
                $expiredAt = now()->addDays($this->invitees_can_schedule['into_the_future_days']);
                // }
            }

            if ($this->invitees_can_schedule['type'] == 2) {
                $minDate = Carbon::parse($this->invitees_can_schedule['date_range']['startDate']);
                $expiredAt = Carbon::parse($this->invitees_can_schedule['date_range']['endDate']);
            }
        }

        // return [
        //     'id' => $this->id,
        //     'availability' => $this->configAvailability($this->availability, $request),
        // ];

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'user' => $this->user,
            'is_on' => $this->is_on,
            'is_exceed_limit' => $this->is_exceed_limit,
            'availability' => $this->configAvailability($this->availability, $request),
            'name' => $this->name,
            'dates' => $dates,
            'available_days' => $availableDays,
            'min_date' => $minDate,
            'expired_at_raw' => $expiredAt,
            'expired_at' => $expiredAt ? $expiredAt->toFormattedDateString() : null,
            'is_show_on_booking_page' => $this->is_show_on_booking_page,
            'welcome_message' => $this->welcome_message,
            'locations' => $this->locations,
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
            'is_isolate' => $this->is_isolate,
        ];
    }

    private function configAvailability($availability, $request)
    {
        $userTimezone = $request->timezone ?? 'UTC';
        $availabilityTimezone = $availability->timezone ?? 'UTC';

        // get current date and last date in current month
        $currentDate = $request->range_start ?? Carbon::now($userTimezone)->format('Y-m-d');
        $lastDate = $request->range_end ?? Carbon::now($userTimezone)->endOfMonth()->format('Y-m-d');

        // Check currentDate is within min_date expired_at_raw range if not set currentDate to min_date
        if (isset($this->invitees_can_schedule) && $this->invitees_can_schedule['type'] == 2) {
            $minDate = Carbon::parse($this->invitees_can_schedule['date_range']['startDate']);
            $expiredAt = Carbon::parse($this->invitees_can_schedule['date_range']['endDate']);
            if ($currentDate < $minDate->format('Y-m-d')) {
                $currentDate = $minDate->format('Y-m-d');
            }
            if ($currentDate > $expiredAt) {
                $currentDate = $minDate->format('Y-m-d');
            }

            // Check if ExpireAt month greater than current month then set lastDate to last day of current month
            if ($expiredAt->format('m') > Carbon::parse($currentDate)->format('m')) {
                $lastDate = Carbon::parse($currentDate)->endOfMonth()->format('Y-m-d');
            }

            if ($expiredAt->format('m') == Carbon::parse($currentDate)->format('m')) {
                $lastDate = $expiredAt->format('Y-m-d');
            }
        }

        $days = [];

        // Loop from current date to last date in current month and check if day is enabled in availability
        if ($availability->data) {
            for ($date = Carbon::parse($currentDate); $date->lte(Carbon::parse($lastDate)); $date->addDay()) {
                $day = $date->format('l');
                $dayId = $date->format('d');
                foreach ($availability->data as $availabilityDay) {

                    if ($availabilityDay['name'] == $day && $availabilityDay['enabled']) {
                        $days[$dayId] = [
                            'date' => $date->format('Y-m-d'),
                            'invitee_events' => [],
                            'enabled' => true,
                            'status' => 'available', // 'available', 'unavailable
                            'spots' => $this->generateSpots($availabilityDay['slots'], $userTimezone, $availabilityTimezone, $date->format('Y-m-d')),
                        ];
                        break;
                    } else {

                        $days[$dayId]['date'] = $date->format('Y-m-d');
                        $days[$dayId]['spots'] = [];
                        $days[$dayId]['enabled'] = false;
                        $days[$dayId]['status'] = 'unavailable';
                    }
                }
            }
        }

        // pluk all spots
        $newDays = collect($days);
        $spots = $newDays->pluck('spots')->flatten(1)->toArray();
        $date = collect($spots)->groupBy(function ($item, $key) use ($day) {
            return Carbon::parse($item['start_time'])->format('Y-m-d');
        })->toArray();
        // dd($date, $days);

        foreach ($days as $key => &$day) {
            foreach ($date as $dateKey => $value) {
                if ($day['date'] === $dateKey) {
                    $day['day'] = $dateKey;
                    $day['adate'] = $day['date'];
                    $day['spots'] = $value;
                    $day['date'] = $dateKey;
                    $day['enabled'] = true;
                    $day['status'] = 'available';
                }
            }

            if (!isset($day['day'])) {
                $day['day'] = $day['date'];
                $day['spots'] = [];
                $day['enabled'] = false;
                $day['status'] = 'unavailable';
            }
        }


        // Remote days with no spots
        $days = collect($days)->where('enabled', true)->where('spots', '!=', [])->toArray();
        // available_days add any day has sports length > 0 return day number

        if ($this->is_isolate) {
            $bookings = Booking::where('calendar_id', $this->id)->where('cancelled_at', null)->get();
        } else {
            // bookings for this calendar user
            $bookings = Booking::where('user_id', $this->user_id)->where('cancelled_at', null)->get();
        }

        // Loop through bookings and remove spot from array if it's between the booking start and end date range  if date is available and spots length > 0 and booking date is equal to day date and booking date is equal to day date and booking date is equal to day date
        $this->removeBookedSpots($days, $bookings, $userTimezone);

        $availabilityDays = [];
        $pluckDate = collect($days)->where('enabled', true)->where('status', 'available')
        // Has spots status available
        ->filter(function ($day) {
            return collect($day['spots'])->where('status', 'available')->count();
        })
        ->pluck('date')->flatten(1)->toArray();
        foreach ($pluckDate as $key => $day) {
            // check if one of sports in day is available then add day number to available_days array
            $availabilityDays[] = (int)Carbon::parse($day)->format('d');
        }



        return [
            'days' => $days,
            'available_days' => $availabilityDays,
            'today' => $currentDate,
        ];
    }

    /**
     * Remove booked spots from days array
     * @param $days
     * @param $bookings
     */
    private function removeBookedSpots(&$days, $bookings, $userTimezone)
    {
        foreach ($bookings as $booking) {
            foreach ($days as $key => &$day) {

                if ($day['enabled'] && count($day['spots'])) {
                    foreach ($day['spots'] as $spotKey => &$spot) {
                        if (Carbon::parse($booking->date_time)->setTimezone($userTimezone)->format('Y-m-d') == $day['date']) {
                            // Booking "date_time" => "2023-05-23T05:00:00+03:00", duration is 70 minutes so end time is 06:10:00 set status to unavailable for all spots between start and end time
                            $duration = Carbon::parse($booking->start)->diffInMinutes(Carbon::parse($booking->end)) ?? $this->duration;
                            $bookingStart = Carbon::parse($booking->date_time)->setTimezone($userTimezone);
                            // booking end time = 6:10:00
                            $bookingEnd = Carbon::parse($booking->date_time)->setTimezone($userTimezone)->addMinutes($duration);
                            $spotStart = Carbon::parse($spot['start_time']);
                            $spotEnd = Carbon::parse($spot['start_time'])->addMinutes($duration);

                            if ($spotStart->greaterThan($bookingStart) && $spotStart->lessThan($bookingEnd)) {
                                $spot['status'] = 'unavailable';
                                $spot['data']['status'] = 'event';
                            }

                            // Close  spot between but not equal booking start and end time
                            if ($spotStart->equalTo($bookingStart)) {
                                $spot['status'] = 'unavailable';
                                $spot['data']['status'] = 'event';
                            }

                            // Consider before event duration
                            $bookingStart = $bookingStart->subMinutes($this->duration);
                            if ($spotStart->greaterThan($bookingStart) && $spotStart->lessThan($bookingEnd)) {
                                $spot['status'] = 'unavailable';
                                $spot['data']['status'] = 'before event';
                            }

                            if ($this->buffer_time) {
                                // Add buffer time before the startDateTime
                                $beforeBookingStart = $bookingStart->subMinutes($this->buffer_time['before'] > 0 ? $this->buffer_time['before'] : 0);

                                $beforeBookingEnd = $bookingEnd->addMinutes($this->buffer_time['before'] > 0 ? $this->buffer_time['before'] : 0);

                                if ($this->buffer_time['before'] > 0) {

                                    if ($spotStart->equalTo($beforeBookingStart)) {
                                        $spot['status'] = 'unavailable';
                                        $spot['data']['status'] = 'before buffer';
                                    }
                                    if ($spotStart->greaterThan($beforeBookingStart) && $spotStart->lessThan($beforeBookingEnd)) {
                                        $spot['status'] = 'unavailable';
                                        $spot['data']['status'] = 'before buffer';
                                    }
                                }
                                // Add buffer time after the endDateTime
                                // Check if buffer time has value
                                $afterBookingStart = $bookingStart->subMinutes($this->buffer_time['after'] > 0 ? $this->buffer_time['after'] : 0);

                                $afterBookingEnd = $bookingEnd->addMinutes($this->buffer_time['after'] > 0 ? $this->buffer_time['after'] : 0);

                                if ($this->buffer_time['after'] > 0) {
                                    if ($spotStart->equalTo($afterBookingStart)) {
                                        $spot['status'] = 'unavailable';
                                        $spot['data']['status'] = 'after buffer';
                                    }
                                    if ($spotStart->greaterThan($afterBookingStart) && $spotStart->lessThan($afterBookingEnd)) {
                                        $spot['status'] = 'unavailable';
                                        $spot['data']['status'] = 'after buffer';
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Export availability slots for each day based on start slots from availability date using interval value variable  ex:30min foreach spot
     * ex output: {"status": "available", "start_time": "2023-05-24T06:00:00Z", "invitees_remaining": 1}
     * @param $slots
     * @param $userTimezone
     * @param $availabilityTimezone
     * @param $date
     * @return array
     */
    private function generateSpots($slots, $userTimezone, $availabilityTimezone, $date): array
    {
        $spots = [];

        foreach ($slots as $slot) {
            $startDateTime = Carbon::parse($slot['start']['label'], $availabilityTimezone)->setDateFrom($date)->setTimezone($userTimezone);
            $endDateTime = Carbon::parse($slot['end']['label'], $availabilityTimezone)->setDateFrom($date)->setTimezone($userTimezone);
            $interval = $this->time_slots_intervals;

            // Remove duration from end time consider we need to remove duration from end time to get the last spot
            $endDateTime = $endDateTime->subMinutes($this->duration);

            // check if the start date is less than today time with timezone then set start date to today time with timezone and consider buffer time if exist in calendar
            // Buffer object {"after": "15", "before": "15"}
            // Check if the start date is less than today's time with timezone
            $today = Carbon::now($userTimezone);

            if ($startDateTime->lessThan($today)) {
                $startDateTime = $today;
            }

            // if ($this->buffer_time) {

            //     // Add buffer time before the startDateTime
            //     $startDateTime = $startDateTime->addMinutes($this->buffer_time['before'] > 0 ? $this->buffer_time['before'] : 0);
            //     // Add buffer time after the endDateTime
            //     // Check if buffer time has value
            //     $endDateTime = $endDateTime->subMinutes($this->buffer_time['after'] > 0 ? $this->buffer_time['after'] : 0);
            // }

            // Round startDateTime to match interval time
            $startDateTime = $this->roundToInterval($startDateTime, $interval);

            // Generate time slots with 30-minute intervals
            $interval = new \DateInterval('PT' . $interval . 'M');
            $period = new \DatePeriod($startDateTime, $interval, $endDateTime);
            $lastDate = null;
            foreach ($period as $date) {
                $lastDate = $date; // Store the current date in a variable
                $spots[] = [
                    'status' => 'available',
                    'start_time' => $date->format('Y-m-d\TH:i:sP'),
                    'time' => $date->format('h:i A'),
                    'timezone' => $userTimezone,
                    'invitees_remaining' => 1,
                ];
            }

            // Add the last date separately, if it exists
            if ($lastDate !== null && $lastDate < $endDateTime) {
                $spots[] = [
                    'status' => 'available',
                    'start_time' => $endDateTime->format('Y-m-d\TH:i:sP'),
                    'time' => $endDateTime->format('h:i A'),
                    'timezone' => $userTimezone,
                    'invitees_remaining' => 1,
                ];
            }
        }

        return $spots;
    }

    /**
     * Round the given date to match the interval time als Round seconds to 0
     * @param $date
     * @param $interval
     * @return mixed
     */
    private function roundToInterval($date, $interval)
    {
        $minutes = $date->minute;
        $remainder = $minutes % $interval;
        if ($remainder !== 0) {
            $roundedMinutes = $interval - $remainder;
            $date->addMinutes($roundedMinutes);

            // Round seconds to 0
            $date->second = 0;
        }
        return $date;
    }
}
