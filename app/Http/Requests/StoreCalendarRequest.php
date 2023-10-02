<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCalendarRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => ['required', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:calendars,slug,user_id' . auth()->id()],
            'is_show_on_booking_page' => 'nullable|string|in:yes,no',
            'welcome_message' => 'nullable|string',
            'locations' => 'nullable|string|json',
            'availability_id' => 'required|exists:availabilities,id,user_id,' . auth()->id(),
            'disable_guests' => 'nullable|string|in:yes,no',
            'requires_confirmation' => 'nullable|string|in:yes,no',
            'redirect_on_booking' => 'nullable|string',
            'invitees_emails' => 'nullable|string|json',
            'enable_signup_form_after_booking' => 'nullable|string|in:yes,no',
            'color' => 'nullable|string|max:255',
            'cover_url' => 'nullable|image',
            'time_slots_intervals' => 'required|string',
            'duration' => 'required|numeric|min:15|max:1440',
            'invitees_can_schedule' => 'nullable|string|json',
            'buffer_time' => 'nullable|string|json',
            'additional_questions' => 'nullable|string|json',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, mixed>
     */
    public function messages() {
        return [
            'slug.unique' => 'This URL is already taken. Please try another one.',
            'slug.regex' => 'The URL can only contain lowercase letters, numbers and dashes.'
        ];
    }

}
