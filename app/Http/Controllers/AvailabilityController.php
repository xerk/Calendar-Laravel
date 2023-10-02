<?php

namespace App\Http\Controllers;

use DateTimeZone;
use Carbon\Carbon;
use App\Models\Calendar;
use App\Models\Availability;
use Illuminate\Http\Request;
use App\Http\Resources\AvailabilityResource;

class AvailabilityController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        if($request->type == 'all') {
            $availabilities = Availability::orderByDesc('created_at')->where('user_id', $user->id)
                ->select(['id', 'name', 'user_id'])
                ->get();
        } else {
            $availabilities = Availability::orderByDesc('created_at')->where('user_id', $user->id)
                ->where('id', '!=', $user->default_availability_id)
                ->paginate(15);
        }



        return AvailabilityResource::collection($availabilities);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function indexDefault()
    {
        $user = auth()->user();
        $availabilities = Availability::orderByDesc('created_at')->where('id', $user->default_availability_id)->get();
        return AvailabilityResource::collection($availabilities);
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

        // returned array data: [{"name":"Sunday","slots":[{"end":{"label":"05:00 PM","value":69},"start":{"label":"09:00 AM","value":37}}],"enabled":false},{"name":"Monday","slots":[],"enabled":false},{"name":"Tuesday","slots":[{"end":{"label":"05:00 PM","value":69},"start":{"label":"09:00 AM","value":37}}],"enabled":false},{"name":"Wednesday","slots":[{"end":{"label":"05:00 PM","value":69},"start":{"label":"09:00 AM","value":37}}],"enabled":false},{"name":"Thursday","slots":[{"end":{"label":"05:00 PM","value":69},"start":{"label":"09:00 AM","value":37}}],"enabled":false},{"name":"Friday","slots":[{"end":{"label":"05:00 PM","value":69},"start":{"label":"09:00 AM","value":37}}],"enabled":false},{"name":"Saturday","slots":[{"end":{"label":"05:00 PM","value":69},"start":{"label":"09:00 AM","value":37}}],"enabled":false}]
        // At least one availability data enabled is required
        $data = json_decode($request->data);

        if (count(array_filter($data, function($item) {
            return $item->enabled;
        })) == 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'At least one availability data enabled is required',
            ], 422);
        }


        $user = auth()->user();
        $availability = new Availability;
        $availability->user_id = $user->id;
        $availability->name = $request->name;
        $availability->timezone = $request->timezone;
        $availability->data = $data;

        if($availability->save()) {
            return response()->json([
                'status' => 'success',
                'data' => new AvailabilityResource($availability),
            ]);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $availability = Availability::find($id);

        if (!$availability) {
            return response()->json([
                'status' => 'not_found',
            ]);
        }

        return new AvailabilityResource($availability);
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
        $availability = Availability::find($id);

        if (!$availability) {
            return response()->json([
                'status' => 'not_found',
            ]);
        }

        $data = json_decode($request->data);

        if (count(array_filter($data, function($item) {
            return $item->enabled;
        })) == 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'At least one availability data enabled is required',
            ], 422);
        }

        // [{"name":"Sunday","slots":[{"end":{"label":"05:00 PM","value":69},"start":{"label":"08:00 AM","value":33}}],"enabled":false},{"name":"Monday","slots":[{"end":{"label":"05:00 PM","value":69},"start":{"label":"04:00 PM","value":65}}],"enabled":true},{"name":"Tuesday","slots":[{"end":{"label":"05:00 PM","value":69},"start":{"label":"09:00 AM","value":37}}],"enabled":true},{"name":"Wednesday","slots":[{"end":{"label":"05:00 PM","value":69},"start":{"label":"01:00 PM","value":53}}],"enabled":true},{"name":"Thursday","slots":[{"end":{"label":"05:00 PM","value":69},"start":{"label":"09:00 AM","value":37}}],"enabled":true},{"name":"Friday","slots":[{"end":{"label":"05:00 PM","value":69},"start":{"label":"09:00 AM","value":37}}],"enabled":true},{"name":"Saturday","slots":[{"end":{"label":"05:00 PM","value":69},"start":{"label":"03:00 PM","value":61}}],"enabled":false}]
        // Africa/Cairo
        // Convert label time from timezone Africa/Cairo to UTC
        // $data = array_map(function($item) use ($request) {
        //     $item->slots = array_map(function($slot) use ($request) {
        //         // to label
        //         $slot->start->label = Carbon::parse($slot->start->label, $request->timezone)->format('h:i A P');
        //         $slot->end->label = Carbon::parse($slot->end->label, $request->timezone)->format('h:i A P');
        //         return $slot;
        //     }, $item->slots);
        //     return $item;
        // }, $data);


        $availability->name = $request->name;
        $availability->timezone = $request->timezone;
        $availability->data = $data;

        if($availability->save()) {
            return response()->json([
                'status' => 'success',
                'data' => new AvailabilityResource($availability),
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
        $user = auth()->user();

        $availability = Availability::find($id);
        if (!$availability) {
            return response()->json([
                'status' => 'not_found',
            ]);
        }

        $oldAvailabilityId = $availability->id;

        if ($availability->delete()) {
            Calendar::where('availability_id', $oldAvailabilityId)->update([
                'availability_id' => $user->default_availability_id,
            ]);

            return response()->json([
                'status' => 'success',
            ]);
        }
    }

    public function updateDefault($id)
    {
        $user = auth()->user();
        $user->default_availability_id = $id;
        $user->save();

        return response()->json([
            'status' => 'success',
            'data' => 'Default availability updated',
        ]);
    }
}
