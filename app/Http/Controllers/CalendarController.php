<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Calendar;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Rules\UniqueCalendarSlug;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\CalendarResource;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\StoreCalendarRequest;
use App\Http\Requests\UpdateCalendarRequest;

class CalendarController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        if ($request->type == 'select') {
            $calendars = Calendar::orderByDesc('created_at')
                ->where('user_id', $user->id)
                ->select(['id', 'name', 'color', 'user_id', 'created_at'])
                ->get();
            return $calendars;
        }

        $calendars = Calendar::orderByDesc('created_at')->where('user_id', $user->id);

        if ($request->q) {
            $calendars = $calendars->where('name', 'like', '%' . $request->q . '%');
        }

        $calendars = $calendars->with(['availability'])->paginate(15);

        return CalendarResource::collection($calendars);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreCalendarRequest $request)
    {
        $user = auth()->user();

        $locations = $request->locations ? json_decode($request->locations) : null;

        // Add UUID to each location
        $locations = collect($locations)->map(function ($location) {
            $location->id = Str::uuid();
            return $location;
        });

        $calendar = Calendar::create([
            'user_id' => $user->id,
            'name' => $request->name,
            'is_on' => true,
            'slug' => $request->slug,
            'is_show_on_booking_page' => $request->is_show_on_booking_page === 'yes' ? true : false,
            'welcome_message' => $request->welcome_message ?: null,
            'locations' => $locations,
            'availability_id' => $request->availability_id,
            'disable_guests' => $request->disable_guests == 'yes' ? true : false,
            'requires_confirmation' => $request->requires_confirmation == 'yes' ? true : false,
            'redirect_on_booking' => $request->redirect_on_booking ?: null,
            'invitees_emails' => $request->invitees_emails ? json_decode($request->invitees_emails) : null,
            'enable_signup_form_after_booking' => $request->enable_signup_form_after_booking == 'yes' ? true : false,
            'color' => $request->color ?: null,
            'time_slots_intervals' => $request->time_slots_intervals ?: null,
            'duration' => $request->duration ?: null,
            'invitees_can_schedule' => $request->invitees_can_schedule ? json_decode($request->invitees_can_schedule) : null,
            'buffer_time' => $request->buffer_time ? json_decode($request->buffer_time) : null,
            'additional_questions' => $request->additional_questions ? json_decode($request->additional_questions) : null,
            'is_isolate' => $request->is_isolate == 'yes' ? true : false,
        ]);

        if($request->has('time_slots_intervals_type')) {
            $calendar->custom_select_intervals = [
                'type' => $request->time_slots_intervals_type,
                'value' => $request->time_slots_intervals_number
            ];
        }

        if($request->has('duration_type')) {
            $calendar->custom_select_duration = [
                'type' => $request->duration_type,
                'value' => $request->duration_number
            ];
        }

        if ($request->cover_url) {
            $year = Carbon::now()->format('Y');
            $month = Carbon::now()->format('m');
            $path = "public/calendars/covers/{$year}/{$month}/";
            $filePath = $path . $this->generateRandomName($path, $request->cover_url->extension());
            $file = Storage::disk(config('filesysystems.default'))->put($filePath, $request->cover_url);

            if ($file) {
                $calendar->cover_url = $file;
            }
        }

        // Check if user hasn't available calendars to be enabled based on his plan limits (if he has a plan) then make calendar is_on = false
        $userCalendarsCount = Calendar::where('user_id', $user->id)->where('is_on', true)->count();
        if ($user->current_plan) {
            if ($user->current_plan['calendars'] < $userCalendarsCount && $user->current_plan['calendars'] != -1) {
                $calendar->is_on = false;
            } else {
                // Update subscription usage
                if ($user->currentSubscription) {
                    $user->currentSubscription->currentSubscriptionUsage->update([
                        'calendars' => $userCalendarsCount + 1,
                    ]);
                }
            }
        } else {
            if ($userCalendarsCount > 1) {
                $calendar->is_on = false;
            }
        }

        if ($calendar->save()) {
            return response()->json([
                'status' => 'success',
                'data' => new CalendarResource($calendar),
            ]);
        }
    }

    public function generateRandomName($path, $extension)
    {
        $randomName = Str::random(20) . '.' . $extension;
        while (Storage::disk(config('filesysystems.default'))->exists($path . '/' . $randomName)) {
            $randomName = Str::random(20) . '.' . $extension;
        }
        return $randomName;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $calendar = Calendar::find($id);

        if (!$calendar) {
            return response()->json([
                'status' => 'not_found',
            ]);
        }

        return new CalendarResource($calendar);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateCalendarRequest $request, Calendar $calendar)
    {
        if (!$calendar) {
            return response()->json([
                'status' => 'not_found',
            ]);
        }

        $locations = $request->locations ? json_decode($request->locations) : null;

        // Add UUID to each location
        $locations = collect($locations)->map(function ($location) {
            $location->id = Str::uuid();
            return $location;
        });

        $calendar->update([
            'is_show_on_booking_page' => $request->is_show_on_booking_page == 'yes' ? true : false,
            'name' => $request->name,
            'slug' => $request->slug,
            'welcome_message' => $request->welcome_message ?: null,
            'locations' => $locations,
            'availability_id' => $request->availability_id,
            'disable_guests' => $request->disable_guests == 'yes' ? true : false,
            'requires_confirmation' => $request->requires_confirmation == 'yes' ? true : false,
            'redirect_on_booking' => $request->redirect_on_booking ?: null,
            'invitees_emails' => $request->invitees_emails ? json_decode($request->invitees_emails) : null,
            'enable_signup_form_after_booking' => $request->enable_signup_form_after_booking == 'yes' ? true : false,
            'color' => $request->color ?: null,
            'time_slots_intervals' => $request->time_slots_intervals ?: null,
            'duration' => $request->duration_calendar ?: null,
            'invitees_can_schedule' => $request->invitees_can_schedule ? json_decode($request->invitees_can_schedule) : null,
            'buffer_time' => $request->buffer_time ? json_decode($request->buffer_time) : null,
            'additional_questions' => $request->additional_questions ? json_decode($request->additional_questions) : null,
            'is_isolate' => $request->is_isolate == 'yes' ? true : false,
        ]);

        if($request->has('time_slots_intervals_type')) {
            $calendar->custom_select_intervals = [
                'type' => $request->time_slots_intervals_type,
                'value' => $request->time_slots_intervals_number
            ];
        }

        if($request->has('duration_type')) {
            $calendar->custom_select_duration = [
                'type' => $request->duration_type,
                'value' => $request->duration_number
            ];
        }


        // Check if user hasn't available calendars to be enabled based on his plan limits (if he has a plan) then make calendar is_on = false
        $userCalendarsCount = Calendar::where('user_id', $calendar->user->id)->where('is_on', true)->count();
        if ($calendar->user->current_plan) {
            if ($calendar->user->current_plan['calendars'] < $userCalendarsCount && $calendar->user->current_plan['calendars'] != -1) {
                $calendar->is_on = false;
            } else {
                // Update subscription usage
                if ($calendar->user->currentSubscription) {
                    $calendar->user->currentSubscription->currentSubscriptionUsage->update([
                        'calendars' => $userCalendarsCount + 1,
                    ]);
                }
            }
        } else {
            if ($userCalendarsCount > 1) {
                $calendar->is_on = false;
            }
        }

        if ($request->hasFile('cover_url')) {
            $year = Carbon::now()->format('Y');
            $month = Carbon::now()->format('m');
            $path = "public/calendars/covers/{$year}/{$month}/";
            $filePath = $path . $this->generateRandomName($path, $request->cover_url->extension());
            $file = Storage::disk(config('filesysystems.default'))->put($filePath, $request->cover_url);

            if ($file) {
                $calendar->cover_url = $file;
            }
        }

        if ($calendar->save()) {
            return response()->json([
                'status' => 'success',
                'data' => new CalendarResource($calendar),
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $calendar = Calendar::find($id);

        if (!$calendar) {
            return response()->json([
                'status' => 'not_found',
            ]);
        }

        if ($calendar->delete()) {
            return response()->json([
                'status' => 'success',
            ]);
        }
    }

    public function checkSlug(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // Unique slug for the user (user_id) and not for the calendar (id) itself (in case of create new calendar)
            'slug' => 'required|max:100|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/' . ($request->id ? '|unique:calendars,slug,' . $request->id . ',id,user_id,' . auth()->id() : '|unique:calendars,slug,NULL,id,user_id,' . auth()->id()),
        ], [
            'slug.unique' => 'This URL is already taken. Please try another one.',
            'slug.required' => 'Please enter a URL for your calendar.',
            'slug.max' => 'The URL may not be greater than 100 characters.',
            'slug.regex' => 'The URL format is invalid. Please use only lowercase letters (a-z), numbers, and hyphens.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        return response()->json([
            'status' => 'success',
        ]);
    }

    public function clone(Request $request, $id)
    {
        $calendar = Calendar::find($id);

        if (!$calendar) {
            return response()->json([
                'status' => 'not_found',
            ]);
        }

        $newCalendar = $calendar->replicate()->fill($this->generateReplicate($calendar));

        // set is_on to false
        $newCalendar->is_on = false;


        if ($newCalendar->save()) {
            return response()->json([
                'status' => 'success',
                'data' => new CalendarResource($newCalendar),
            ]);
        }
    }

    function generateReplicate(Calendar $calendar)
    {
        $i = 1;
        $name = $calendar->name . ' (copy ' . $i . ')';
        $slug = $calendar->slug . '-copy-' . $i;

        while (Calendar::where('slug', $slug)->count()) {
            $i++;
            $name = $calendar->name . ' (copy ' . $i . ')';
            $slug = $calendar->slug . '-copy-' . $i;
        }

        return [
            'slug' => $slug,
            'name' => $name,
        ];
    }

    public function updateStatus(Request $request, Calendar $calendar)
    {
        // Validate is_one
        $validator = Validator::make($request->all(), [
            'is_on' => 'required|in:yes,no',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }
        if ($request->is_on == 'yes') {
            // Check if user hasn't available calendars to be enabled based on his plan limits (if he has a plan) then make calendar is_on = false
            $userCalendarsCount = Calendar::where('user_id', auth()->id())->where('is_on', 1)->count();
            $bookingsCount = $calendar->bookings()->count();


            if (auth()->user()->current_plan) {
                // if calendars equal -1 then unlimited calendars
                // calendars limit reached
                // booking limit reached
                if ((auth()->user()->current_plan['calendars'] <= $userCalendarsCount && auth()->user()->current_plan['calendars'] != -1)) {
                    return response()->json([
                        'status' => 'error',
                        'errors' => [
                            'is_on' => ['You have reached your limit of active calendars. Please upgrade your plan to add more calendars.'],
                        ],
                    ], 422);
                }
            }

            // if user has subscription
            if (auth()->user()->currentSubscription) {
               // Update subscription usage
                auth()->user()->currentSubscription->currentSubscriptionUsage->update([
                    'calendars' => $userCalendarsCount + 1,
                ]);
            }
        }

        $calendar->is_on = $request->is_on == 'yes' ? true : false;
        $calendar->save();

        return response()->json([
            'status' => 'success',
        ]);
    }

    
}
