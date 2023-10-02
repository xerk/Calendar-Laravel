<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $otherInvitees = '';
        if($this->other_invitees) {
            $otherInviteesArray = [];
            foreach($this->other_invitees as $otherInvitee) {
                $otherInviteesArray[] = $otherInvitee['value'];
            }
            if(count($otherInviteesArray) > 0) {
                $otherInvitees = implode(', ', $otherInviteesArray);
            }
        }

        $additionalAnswers = [];
        // check if calendar has additional questions also use trash
        $calendar = $this->calendar()->withTrashed()->first();
        if(
            $calendar &&
            $calendar->additional_questions) {
            foreach($calendar->additional_questions as $key => $question) {
                $additionalAnswers[] = [
                    'label' => $question['label'],
                    'value' => isset($this->additional_answers['answer_'.$key]) ? $this->additional_answers['answer_'.$key] : '',
                ];
            }
        }

        // Convert date_time to Africa/Cairo timezone
        $dateTime = Carbon::parse($this->date_time)->setTimezone($this->calendar->availability->timezone);
        $duration = Carbon::parse($this->start)->diffInMinutes(Carbon::parse($this->end));

        return [
            'id' => $this->id,
            'uid' => $this->uid,
            'date_time' => $this->date_time,
            'date' => $dateTime->toFormattedDayDateString(),
            'is_past' => $dateTime->isPast(),
            // get start from date_time
            'start' => $dateTime->format('h:i A'),
            'duration' => $duration,
            'end' => $dateTime->addMinutes($duration)->format('h:i A'),
            'invitee_name' => $this->invitee_name,
            'invitee_email' => $this->invitee_email,
            'invitee_phone' => $this->invitee_phone,
            'invitee_notes' => $this->invitee_notes,
            'timezone' => $this->timezone,
            'location' => $this->location,
            'other_invitees' => $otherInvitees,
            'additional_answers' => $additionalAnswers,
            'meeting_notes' => $this->meeting_notes,
            'cancelled_at' => $this->cancelled_at,
            'calendar' => $this->calendar,
            'calendar_availability' => $this->calendar->availability,
            'is_confirmed' => $this->is_confirmed,
            'expired_at' => $this->expired_at,
            'reschedule_data' => $this->reschedule_data,
            'rescheduled_at' => $this->rescheduled_at,
            'created_at' => $this->created_at->toDayDateTimeString(),
            'created_by' => $this->created_by,
            // check if reschedule_token_expires_at is past or not
            'reschedule_uuid' => $this->reschedule_token_expires_at < now() ? null : $this->reschedule_uuid,
            'reschedule_token_expires_at' => $this->reschedule_token_expires_at,
            // Generate encode token has created_by and booking_id and reschedule_uuid
            'reschedule_token' => $this->reschedule_token_expires_at < now() ? null : base64_encode($this->created_by.'_'.$this->reschedule_uuid),
            'cancellation_reason' => $this->cancellation_reason,
            'reschedule_reason' => $this->reschedule_reason,
            'cancelled_by' => $this->cancelled_by,
            'rescheduled_by' => $this->rescheduled_by,
            'user' => $this->user,
        ];
    }
}
