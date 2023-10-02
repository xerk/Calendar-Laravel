<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Booking;
use App\Models\Calendar;
use Illuminate\Http\Request;
use App\Http\Resources\BookingResource;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $now = now(auth()->user()->timezone)->toIso8601String();

        $totalBookings = Booking::where('user_id', auth()->user()->id)->where('date_time', '>=', $now)->whereNull('cancelled_at')->whereNull('expired_at')->count();
        $totalPendingBookings = Booking::where('user_id', auth()->user()->id)->where('date_time', '>=', $now)->where('is_confirmed', false)->whereNull('cancelled_at')->whereNull('expired_at')->count();
        $totalActiveCalendars = Calendar::where('user_id', auth()->user()->id)->where('is_on', true)->count();
        $totalDeactivatedCalendars = Calendar::where('user_id', auth()->user()->id)->where('is_on', false)->count();
        $totalCalendars = Calendar::where('user_id', auth()->user()->id)->count();

        // Today Bookings max 3
        $todayBookings = Booking::where('user_id', auth()->user()->id)
            ->where('date_time', '>=',now(auth()->user()->timezone)->toIso8601String())
            ->where('date_time', '<=', now(auth()->user()->timezone)->endOfDay()->toIso8601String())
            ->whereNull('cancelled_at')->whereNull('expired_at')
            ->take(3)->get();



        // This Week Bookings max 3 but not in today bookings
        $thisWeekBookings = Booking::where('user_id', auth()->user()->id)
            ->whereDate('date_time', '>=', now(auth()->user()->timezone)->toIso8601String())
            ->whereDate('date_time', '<=', now(auth()->user()->timezone)->endOfWeek(Carbon::THURSDAY)->toIso8601String())
            ->whereNull('cancelled_at')->whereNull('expired_at')
            // ->whereBetween('date_time', [now(auth()->user()->timezone)->startOfWeek()->toIso8601String(), now(auth()->user()->timezone)->endOfWeek()->toIso8601String()])
            ->whereNotIn('id', $todayBookings->pluck('id'))
            ->take(3)->get();

        return response()->json([
            'total_bookings' => $totalBookings,
            'total_pending_bookings' => $totalPendingBookings,
            'total_active_calendars' => $totalActiveCalendars,
            'total_deactivated_calendars' => $totalDeactivatedCalendars,
            'total_calendars' => $totalCalendars,
            'today_bookings' => BookingResource::collection($todayBookings),
            'this_week_bookings' => BookingResource::collection($thisWeekBookings),
        ]);
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
    public function show($id)
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
