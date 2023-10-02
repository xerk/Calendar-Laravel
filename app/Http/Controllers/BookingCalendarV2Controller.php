<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Calendar;
use Illuminate\Http\Request;
use App\Http\Resources\BookingCalendarV2Resource;
use App\Http\Resources\BookingCalendarRescheduleResource;

class BookingCalendarV2Controller extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($username)
    {
        $user = User::where('username', $username)->select(['id', 'username', 'name', 'timezone', 'title', 'profile_photo_url', 'description', 'email'])
            ->with('profile:id,user_id,is_available,booking_page_off_message')->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        if(!$user->profile->is_available) {
            return [
                'user' => $user,
            ];
        }

        $calendars = Calendar::where('user_id', $user->id)
            ->where('is_show_on_booking_page', true)
            ->where('is_on', true)
            ->where('is_exceed_limit', false)
            ->withTrashed()
            ->get();

        return [
            'user' => $user,
            'calendars' => BookingCalendarV2Resource::collection($calendars),
        ];
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $username, $slug)
    {
        $user = User::where('username', $username)->select(['id', 'username', 'name', 'timezone', 'title', 'profile_photo_url', 'email'])
            ->with('profile:id,user_id,is_available,booking_page_off_message')->firstOrFail();

        $calendar = Calendar::where('user_id', $user->id)
            ->with(['availability', 'bookings:id,date,start,end,calendar_id'])
            ->where('slug', $slug)
            ->withTrashed()
            ->first();

        if(!$calendar) {
            return response()->json([
                'message' => 'Calendar not found.'
            ], 404);
        }

        return [
            'user' => $user,
            'calendar' => new BookingCalendarV2Resource($calendar),
        ];
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function reschedule(Request $request, $username, $slug, $booking)
    {
        $user = User::where('username', $username)->select(['id', 'username', 'name', 'timezone', 'title', 'profile_photo_url', 'email'])
            ->with('profile:id,user_id,is_available,booking_page_off_message')->firstOrFail();
        $calendar = Calendar::where('user_id', $user->id)
            ->with(['availability', 'bookings:id,date,start,end,calendar_id'])
            ->where('slug', $slug)->withTrashed()
            ->first();

        if(!$calendar) {
            return response()->json([
                'message' => 'Calendar not found.'
            ], 404);
        }

        $booking = $calendar->bookings()->where('reschedule_uuid', $booking)->firstOrFail();

        $token = $request->token;
        $checkValidToken = $this->checkValidToken($token, $booking);

        if($checkValidToken !== true) {
            return $checkValidToken;
        }




        return [
            'user' => $user,
            'calendar' => new BookingCalendarRescheduleResource($calendar, $booking),
            'booking' => $booking,
        ];
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

        return true;
    }
}
