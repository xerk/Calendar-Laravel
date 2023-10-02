<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookingCalendarResource;
use App\Models\Calendar;
use App\Models\User;
use Illuminate\Http\Request;

class BookingCalendarController extends Controller
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
            ->get();

        return [
            'user' => $user,
            'calendars' => BookingCalendarResource::collection($calendars),
        ];
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
        //
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
            ->first();

        if(!$calendar) {
            return response()->json([
                'message' => 'Calendar not found.'
            ], 404);
        }

        return [
            'user' => $user,
            'calendar' => new BookingCalendarResource($calendar),
        ];
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
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
