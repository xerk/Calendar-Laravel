<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Resources\Json\JsonResource;


class CalendarResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {

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

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'is_on' => $this->is_on,
            'is_exceed_limit' => $this->is_exceed_limit,
            'is_show_on_booking_page' => $this->is_show_on_booking_page,
            'name' => $this->name,
            'slug' => $this->slug,
            'welcome_message' => $this->welcome_message,
            'locations' => $this->locations,
            'availability_id' => $this->availability_id,
            'availability' => new AvailabilityResource($this->availability),
            'disable_guests' => $this->disable_guests,
            'requires_confirmation' => $this->requires_confirmation,
            'redirect_on_booking' => $this->redirect_on_booking,
            'invitees_emails' => $this->invitees_emails,
            'enable_signup_form_after_booking' => $this->enable_signup_form_after_booking,
            'color' => $this->color,
            'cover_url' => $this->cover_url ? asset(Storage::url($this->cover_url)) : '',
            'interval' => $this->time_slots_intervals,
            'time_slots_intervals' => $this->time_slots_intervals <= 60 ? ['id' => $this->time_slots_intervals, 'name' => $this->time_slots_intervals.' minutes'] : ['id' => 'custom', 'name' => 'Custom'],
            'time_slots_intervals_type' => $this->custom_select_intervals ? ['id' => $this->custom_select_intervals['type'], 'name' => $this->custom_select_intervals['type']] : ['id' => 'min', 'name' => 'min'],
            'time_slots_intervals_number' => $this->custom_select_intervals ? $this->custom_select_intervals['value'] : null,
            'original_duration' => $this->duration,
            'duration' => $this->duration <= 60 ? ['id' => $this->duration, 'name' => $this->duration.' minutes'] : ['id' => 'custom', 'name' => 'Custom'],
            'duration_type' => $this->custom_select_duration ? ['id' => $this->custom_select_duration['type'], 'name' => $this->custom_select_duration['type']] : ['id' => 'min', 'name' => 'min'],
            'duration_number' => $this->custom_select_duration ? $this->custom_select_duration['value'] : null,
            'invitees_can_schedule' => $this->invitees_can_schedule,
            'buffer_time' => $this->buffer_time,
            'additional_questions' => $this->additional_questions,
            'is_isolate' => $this->is_isolate,
            'expired_at' => $this->expired_at,
            'expired_at_raw' => $expiredAt,
            'expired_at' => $expiredAt ? $expiredAt->toFormattedDateString() : null,
            'total_bookings' => $this->bookings->count(),
            'upcoming_bookings' => $this->bookings->where('date_time', '>=', now()->toIso8601String())->whereNull('cancelled_at')->whereNull('expired_at')->count(),
        ];
    }
}
