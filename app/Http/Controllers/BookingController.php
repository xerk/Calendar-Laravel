<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Booking;
use App\Models\Calendar;
use Illuminate\Support\Str;
use App\Mail\BookingCreated;
use App\Mail\UserRegistered;
use Illuminate\Http\Request;
use App\Mail\BookingCancelledMail;
use App\Mail\BookingRescheduledMail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\BookingCreateInviteeMail;
use App\Http\Resources\BookingResource;
use App\Mail\BookingCancelledInviteeMail;
use App\Mail\BookingRescheduledInviteeMail;
use App\Http\Resources\BookingCalendarV2Resource;

class BookingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        $bookings = Booking::where('user_id', $user->id);
        if ($request->collection !== 'past' && $request->collection !== 'cancelled') {
            $bookings->orderBy('date_time');
        }
        $now = now($user->timezone)->toIso8601String();

        if($request->calendar_id) {
            $bookings = $bookings->where('calendar_id', $request->calendar_id);
        }

        if($request->status === 'cancelled') {
            $bookings = $bookings->whereNotNull('cancelled_at');
        }
        if($request->date_range) {
            $dates = explode(':', $request->date_range);
            $bookings = $bookings->where('date', '>=', $dates[0]);
            $bookings = $bookings->where('date', '<=', $dates[1]);
        }
        if($request->collection === 'pending') {
            $bookings = $bookings->where('date_time', '>=', $now)->where('is_confirmed', false)->whereNull('cancelled_at')->whereNull('expired_at');
        } elseif ($request->collection === 'past') {
            $bookings = $bookings->where('date_time', '<', $now)->where('is_confirmed', true)->whereNull('cancelled_at')->whereNull('expired_at')->orderBy('date_time', 'desc');
        } elseif ($request->collection === 'cancelled') {
            $bookings = $bookings->where(function($query) {
                $query->whereNotNull('cancelled_at')->orWhereNotNull('expired_at')->orderBy('date_time', 'desc');
            });
        } else {
            // date_time 2023-05-25T02:00:00+03:00
            $bookings = $bookings->where('date_time', '>=', $now)->whereNull('cancelled_at')->whereNull('expired_at');
        }


        if ($request->view === 'calendar') {
            $bookings = $bookings->paginate(10000);
        } else {
            $bookings = $bookings->paginate(10);
        }

        return BookingResource::collection($bookings);
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
    public function store(Request $request)
    {
        // withTrashed() to get the calendar even if it is soft deleted
        $calendar = Calendar::withTrashed()->find($request->calendar_id);

        if (!$calendar) {
            return response()->json([
                'status' => 'not_found',
            ]);
        }

        if ($calendar->is_exceed_limit) {
            return response()->json([
                'status' => 'exceed_limit',
            ]);
        }

        if (!$calendar->is_on) {
            return response()->json([
                'status' => 'not_available',
            ]);
        }

        // Check if date is booked or not
        // $validBooking = $this->checkValidBooking($request->date_time, $request->calendar_id, $request->timezone);

        $booking = new Booking;
        $booking->uid = Str::uuid();
        $booking->calendar_id = $request->calendar_id;
        $booking->user_id = $calendar->user_id;

        $booking->invitee_name = $request->invitee_name;
        $booking->invitee_email = $request->invitee_email;
        if($request->invitee_phone) {
            $booking->invitee_phone = $request->invitee_phone;
        }
        $booking->location = json_decode($request->location);

        if($request->other_invitees) {
            $booking->other_invitees = json_decode($request->other_invitees);
        }
        $booking->invitee_notes = $request->invitee_notes;

        $booking->timezone = $request->timezone ?: $calendar->timezone;
        $booking->date = $request->date;
        $booking->date_time = $request->date_time;
        $booking->start = $request->start;
        $end = Carbon::parse(now()->toDateString().' '.$request->start);
        $booking->end = $end->addMinutes($calendar->duration)->format('h:i A');

        $answers = [];
        foreach($calendar->additional_questions as $key => $question) {
            $answers['answer_'.$key] = $request->input('answer_'.$key);
        }
        $booking->additional_answers = $answers;

        if($calendar->requires_confirmation) {
            $booking->is_confirmed = false;
        }


        if($booking->save()) {
            // Get Count of total bookings of this calendar
            $totalBookings = Booking::where('calendar_id', $request->calendar_id)->count();
            // Get User
            $user = User::find($calendar->user_id);

            // Check if user has a plan and if he has check if he has reached the limit of bookings
            if($user->current_plan) {
                if($user->current_plan['bookings']) {
                    if($totalBookings >= $user->current_plan['bookings'] && $user->current_plan['bookings'] != -1) {
                        // Turn calendar is_on to false
                        $calendar->is_exceed_limit = true;
                        $calendar->save();
                    }
                }
            }
            
            if ($booking->user->zoomProvider) {
                \Log::info('Zoom Meeting');
                $booking->zoomMeeting();
            }

            if ($user->googleProvider) {
                // Create Google Calendar Event
                $booking->googleCalendarEvent();
            }

            Mail::to($user)->queue(new BookingCreated($user, $booking, $calendar));
            $invitee = new User();
            $invitee->name = $booking->invitee_name;
            $invitee->email = $booking->invitee_email;
            Mail::to($invitee)->queue(new BookingCreateInviteeMail($invitee->name, $booking, $calendar));

            if($calendar->invitees_emails) {
                foreach($calendar->invitees_emails as $inviteeEmail) {
                    $invitee = new User();
                    $invitee->name = 'Invitee';
                    $invitee->email = $inviteeEmail['value'];
                    Mail::to($invitee)->queue(new BookingCreateInviteeMail($invitee->name, $booking, $calendar));
                }
            }

            if($request->other_invitees) {
                foreach($booking->other_invitees as $inviteeEmail) {
                    $invitee = new User();
                    $invitee->name = 'Invitee';
                    $invitee->email = $inviteeEmail['value'];
                    Mail::to($invitee)->queue(new BookingCreateInviteeMail($invitee->name, $booking, $calendar));
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Booking scheduled',
                'data' => new BookingResource($booking),
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Oops! Something went wrong. Please try again later.',
        ]);
    }

    public function update(Request $request, $id) {

        $booking = Booking::where('reschedule_uuid', $id)->firstOrFail();
        // Validate token
        $token = $request->token;
        $validToken = $this->checkValidToken($token, $booking);
        if(!$validToken['status']) {
            return $validToken;
        }

        // withTrashed() to get the calendar even if it is soft deleted
        $calendar = Calendar::withTrashed()->find($request->calendar_id);
        if (!$calendar) {
            return response()->json([
                'status' => 'not_found',
            ]);
        }

        // store the old booking data in reschedule_data
        $booking->reschedule_data = $booking;
        $booking->rescheduled_by = $validToken['created_by'];

        $booking->calendar_id = $request->calendar_id;
        $booking->user_id = $calendar->user_id;

        $booking->invitee_name = $request->invitee_name;
        $booking->invitee_email = $request->invitee_email;
        if($request->invitee_phone) {
            $booking->invitee_phone = $request->invitee_phone;
        }
        $location = json_decode($request->location);
        if($location){
        if ($location->kind === 'google_meet' || $location->kind === 'zoom') {
            if ($location->kind != $booking->location['kind']) {
                $booking->location = json_decode($request->location);
            }
        } else {
            $booking->location = json_decode($request->location);
        }
    }

        if($request->other_invitees) {
            $booking->other_invitees = json_decode($request->other_invitees);
        }
        $booking->invitee_notes = $request->invitee_notes;

        $booking->timezone = $request->timezone ?: $calendar->timezone;
        $booking->date = $request->date;
        $booking->date_time = $request->date_time;
        $booking->start = $request->start;
        $end = Carbon::parse(now()->toDateString().' '.$request->start);
        $booking->end = $end->addMinutes($calendar->duration)->format('h:i A');

        $answers = [];
        foreach($calendar->additional_questions as $key => $question) {
            $answers['answer_'.$key] = $request->input('answer_'.$key);
        }
        $booking->additional_answers = $answers;

        if($calendar->requires_confirmation) {
            $booking->is_confirmed = false;
        }

        // rescheduled_at
        if ($request->has('reason')) {
            $booking->reschedule_reason = $request->reason;
        } else {
            $booking->reschedule_reason = '';
        }

        $booking->rescheduled_at = now();
        $booking->expired_at = null;
        $booking->cancelled_at = null;

        if($booking->save()) {

            if ($booking->user->googleProvider) {
                // Create Google Calendar Event
                $booking->googleCalendarEvent(true);
            }

            Mail::to($booking->user)->queue(new BookingRescheduledMail($booking->user, $booking, $booking->calendar));
            $invitee = new User();
            $invitee->name = $booking->invitee_name;
            $invitee->email = $booking->invitee_email;
            Mail::to($invitee)->queue(new BookingRescheduledInviteeMail($invitee->name, $booking, $calendar));

            if($calendar->invitees_emails) {
                foreach($calendar->invitees_emails as $inviteeEmail) {
                    $invitee = new User();
                    $invitee->name = 'Invitee';
                    $invitee->email = $inviteeEmail['value'];
                    Mail::to($invitee)->queue(new BookingRescheduledInviteeMail($invitee->name, $booking, $calendar));
                }
            }

            if($request->other_invitees) {
                foreach($booking->other_invitees as $inviteeEmail) {
                    $invitee = new User();
                    $invitee->name = 'Invitee';
                    $invitee->email = $inviteeEmail['value'];
                    Mail::to($invitee)->queue(new BookingRescheduledInviteeMail($invitee->name, $booking, $calendar));
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Booking rescheduled',
                'data' => new BookingResource($booking),
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Oops! Something went wrong. Please try again later.',
        ]);
    }

    public function cancel(Request $request, Booking $booking)
    {
        $date = Carbon::parse($booking->date_time);
        if ($date->isPast()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Booking is in the past',
            ]);
        }

        $booking->cancellation_reason = $request->reason;
        $booking->cancelled_by = $request->cancelled_by ?? 'host';
        $booking->cancelled_at = now();
        $booking->save();

        if ($booking->user->googleProvider) {
            $booking->googleCancelEvent();
        }

        Mail::to($booking->user)->queue(new BookingCancelledMail($booking->user, $booking, $booking->calendar));

        // Send email to the invitee
        $invitee = new User();
        $invitee->name = $booking->invitee_name;
        $invitee->email = $booking->invitee_email;
        Mail::to($invitee)->queue(new BookingCancelledInviteeMail($invitee->name, $booking, $booking->calendar));

        // if the booking is exceeded the limit and the user has a plan
        // then turn the calendar is_on to true
        $totalBookings = Booking::where('calendar_id', $booking->calendar_id)->count();
        // WithTrashed() to get the calendar even if it is soft deleted
        $calendar = Calendar::withTrashed()->find($booking->calendar_id);
        $user = User::find($calendar->user_id);
        if($user->current_plan) {
            if($user->current_plan['bookings']) {
                if($totalBookings < $user->current_plan['bookings']) {
                    // Turn calendar is_on to false
                    $calendar->is_exceed_limit = false;
                    $calendar->save();
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Booking cancelled',
        ]);
    }

    /**
     * Confirm a booking that requires confirmation (if the user is the owner of the calendar)
     * Date shouldn't be in the past and the booking shouldn't be cancelled
     * is there like expiry time where if meeting not confirmed by host to be auto canceled? or it will be canclled if the booking date is passed as below example
     * What if meeting is booked for 11 of April.. then if 11 of April passed then it will be not valid.. meaning host can not confirm it.. and invitee will get email saying its canceled cuz time passed..etc
     * @param Request $request
     * @param Booking $booking
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirm(Request $request, Booking $booking) {
        // Validate this booking belongs to the user
        if ($booking->user_id != auth()->user()->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Booking not found',
            ]);
        }

        $date = Carbon::parse($booking->date_time);


        if ($date->isPast()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Booking is in the past',
            ]);
        }

        if ($booking->cancelled_at) {
            return response()->json([
                'status' => 'error',
                'message' => 'Booking is cancelled',
            ]);
        }

        $booking->is_confirmed = true;

        if ($booking->save()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Booking confirmed',
            ]);
        }
    }

    public function reschedule(Request $request, Booking $booking) {

        $createdBy = $request->type;
        $booking->reschedule_uuid = uniqid();
        $booking->reschedule_token_expires_at = now()->addMinutes(5);
        $booking->save();

        // Generate a token and the token should has reschedule_uuid, reschedule_token_expires_at, created_by
        $token = base64_encode(json_encode([
            'reschedule_uuid' => $booking->reschedule_uuid,
            'reschedule_token_expires_at' => $booking->reschedule_token_expires_at,
            'created_by' => $createdBy,
        ]));

        if ($booking->save()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Booking rescheduled',
                'data' => new BookingResource($booking),
                'token' => $token,
            ]);
        }
    }

    /**
     * Check valid token.
     *
     * @param  int  $token
     * @return \Illuminate\Http\Response
     */
    private function checkValidToken($token, $booking)
    {
        $token = json_decode(base64_decode($token));

        if (!$token) {
            return response()->json([
                'message' => 'Booking reschedule token not valid.'
            ], 403);
        }

        // Check if booking reschedule_uuid and reschedule_token_expires_at
        if($booking->reschedule_uuid !== $token->reschedule_uuid) {
            return response()->json([
                'message' => 'Booking reschedule token not valid.'
            ], 403);
        }

        // Check token reschedule_token_expires_at
        if($booking->reschedule_token_expires_at < now()) {
            return response()->json([
                'message' => 'Booking reschedule token expired.'
            ], 403);
        }

        return [
            'status' => true,
            'created_by' => $token->created_by,
        ];
    }

    /**
     * Check valid booking.
     * Check the booking if contains the end
     * @param  int  $date_time
     * @param  int  $calendar_id
     * @param  int  $timezone
     * @return \Illuminate\Http\Response
     */
    private function checkValidBooking($date_time, $calendar_id, $timezone) {
        $calendar = Calendar::find($calendar_id);
        $data = new BookingCalendarV2Resource($calendar);
        $date = Carbon::parse($date_time)->setTimezone($timezone);
        // dd($date->toDateString());
        if (!$data) {
            return true;
        }

        $days = $data->availability->days;
        dd(collect($data->availability));
        $day = collect($days)->where('day', $date->dayOfWeek)->first();

        // Check if date is in the days
    }

    public function meetingNote(Request $request, Booking $booking) {
        $booking->meeting_notes = $request->meeting_notes;
        $booking->save();

        // Send email to the invitee
        $invitee = $booking->invitee_email;
        // General email
        Mail::to($invitee)->queue(new BookingCreated());

        return response()->json([
            'status' => 'success',
            'message' => 'Meeting notes saved',
        ]);
    }

    /**
     * Download ics file for booking
     *
     */
    public function downloadICS(Request $request, Booking $booking) {
        return $booking->generateIcs();
    }

    /**
     * get booking via uid
     */
    public function bookingUid(Request $request, $uid) {
        $authrized = false;
        $authToken = $request->bearerToken();
        // add middleware to check if the user is authorized to view this booking
        if ($authToken) {
            $user = auth('sanctum')->user();

            if ($user) {
                $authrized = true;
            }

        }


        $booking = Booking::where('uid', $uid)->first();

        if (!$booking) {
            return response()->json([
                'status' => 'error',
                'message' => 'Booking not found',
            ]);
        }

        if ($authrized && $booking->user_id == $user->id) {
            return response()->json([
                'status' => 'success',
                'data' => new BookingResource($booking),
                'authrized' => true,
            ]);
        }



        return response()->json([
            'status' => 'success',
            'data' => new BookingResource($booking),
            'authrized' => false,
        ]);
    }




    public function checkBookingAvailability(Request $request)
    {
        $date_time = $request->input('date_time');
        $calendar_id = $request->input('calendar_id');


        // Check if the booking exists for the given date and time
        $existingBooking = Booking::where('calendar_id', $calendar_id)
            ->where('date_time', $date_time)
            ->first();

        if ($existingBooking) {
            return response()->json([
                'status' => 'booking_exists',
                'message' => 'A booking already exists for the given date and time.',
                'booking' => new BookingResource($existingBooking),
            ]);
        }

        return response()->json([
            'status' => 'available',
            'message' => 'No booking exists for the given date and time.',
        ]);
    }
    
}
