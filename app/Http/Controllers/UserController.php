<?php

namespace App\Http\Controllers;

use Image;
use Carbon\Carbon;
use App\Models\User;
use App\Mail\WelcomeMail;
use App\Models\UserProfile;
use Illuminate\Support\Str;
use App\Mail\UserRegistered;
use App\Models\Availability;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Mail\ChangeEmailRequest;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Mail\ChangeEmailConfirmMail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Notifications\GeneralNotification;

class UserController extends Controller
{
    public function me()
    {
        if(auth()->check()) {
            $user = auth()->user();
            $user->last_online_at = now();
            $user->save();

            return new UserResource($user);
        }

        return response()->json([
            'status' => 'not_logged_in',
            'message' => 'Not logged in',
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $english = ['id' => "en", "name" => "English", "localName" => "English", "countries" => ["United Kingdom", "Nigeria", "Philippines", "Bangladesh", "India"]];

        $otp = rand(100000, 999999);
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'group_id' => 2,
            'languages' => [$english],
            'email_verify_otp' => md5($otp),
        ]);

        if($user) {
            UserProfile::create([
                'user_id' => $user->id,
            ]);

            Mail::to($user)->queue(new UserRegistered($otp, $user->name));

            return response()->json([
                'status' => 'success',
                'token' => $user->createToken('web')->plainTextToken
            ]);
        }

        return response()->json([
            'status' => 'failed',
            'message' => 'Oops! Something went wrong. Please try again later.',
        ]);
    }

    public function update(Request $request)
    {
        if (!$request->username) {
            // append request username from auth
            $request->request->add(['username' => auth()->user()->username]);
        }

        // validate unique username
        $validator = Validator::make($request->all(), [
            'username' => [
                'min:3',
                'alpha_dash',
                Rule::unique('users')->ignore(auth()->user()->id),
            ],
            'profile_photo_url' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:1024'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = auth()->user();

        if($request->username) {
            $user->username = $request->username;
        }
        if($request->name) {
            $user->name = $request->name;
        }
        if($request->title) {
            $user->title = $request->title;
        }
        if($request->description) {
            $user->description = $request->description;
        } else {
            $user->description = null;
        }
        if($request->profile_photo_url) {
            if($request->profile_photo_url == 'remove') {
                $user->profile_photo_url = null;
            } else {
                if($request->hasFile('profile_photo_url')) {
                    $year = Carbon::now()->format('Y');
                    $month = Carbon::now()->format('m');
                    $fileExtension = $request->profile_photo_url->getClientOriginalExtension();
                    $fileName = $this->generateFileName("profile-photos/{$year}/{$month}/", $fileExtension);
                    $fileUrl = "profile-photos/{$year}/{$month}/".$fileName;
                    $optimizedFile = Image::make($request->profile_photo_url)
                        ->fit(100, 100, function ($constraint) {
                            $constraint->upsize();
                        })->stream($fileExtension, 100)->__toString();
                    $upload = Storage::put($fileUrl, $optimizedFile, ['visibility' => 'public', 'mimetype' => 'image/'.$fileExtension]);
                    if($upload) {
                        $user->profile_photo_url = Storage::url($fileUrl);
                    }
                }
            }

        }
        if($request->display_language) {
            $user->display_language = $request->display_language;
        }
        if($request->languages) {
            $user->languages = json_decode($request->input('languages'));
        }
        if($request->timezone) {
            $user->timezone = $request->timezone;
        }
        if($request->is_available) {
            $user->profile->is_available = $request->input('is_available') == 'true' ? true : false;
        }
        if($request->is_change_booking_page_off_message) {
            $user->profile->booking_page_off_message = $request->booking_page_off_message;
        }
        if($request->profile_form == 'yes') {
            $user->billing_address = $request->billing_address;
            $user->billing_city = $request->billing_city;
            $user->billing_region = $request->billing_region;
            $user->billing_country = $request->billing_country;
            $user->billing_zipcode = $request->billing_zipcode;
        }

        $changePassword = false;
        if($request->old_password && $request->new_password && $request->new_password_confirmation) {
            if (! Hash::check($request->old_password, $user->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'The provided credentials are incorrect.'
                ]);
            }
            if($request->new_password != $request->new_password_confirmation) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Repeat passwords do not match.'
                ]);
            }
            $user->password = Hash::make($request->new_password);
            $changePassword = true;
        }

        if($request->availability_data) {
            $availability = new Availability;
            $availability->user_id = $user->id;
            $availability->name = 'Default';
            $availability->timezone = $user->timezone;
            $availability->data = json_decode($request->availability_data);

            if($availability->save()) {
                $user->default_availability_id = $availability->id;
            }
        }

        if($user->save() && $user->profile->save()) {
            if($changePassword) {
                // Send Email:25:
                $user->notify(new GeneralNotification('Email:25:', 'Email:25:Content'));
            }

            return response()->json([
                'status' => 'success',
            ]);
        }

        return response()->json([
            'status' => 'failed',
            'message' => 'Oops! Something went wrong. Please try again later.',
        ]);
    }

    protected function generateFileName($path, $extension)
    {
        $fileName = Str::random(30);
        $fullFileName = $fileName.'.'.$extension;
        $filePath = $path.$fullFileName;
        while(Storage::exists($filePath)) {
            $fileName = Str::random(10);
            $fullFileName = $fileName.'.'.$extension;
            $filePath = $path.$fullFileName;
        }
        return $fullFileName;
    }

    public function checkUsername(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // Unique username except current user username
            'username' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('users')->ignore(auth()->user()->id)],
        ], [
            'username.unique' => 'This username is already taken. Please try another one.',
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

    public function checkEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = auth()->user();

        if($request->email != $user->email) {
            $duplicateUser = User::where('email', $request->email)
                ->select(['email'])->first();

            if($duplicateUser) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This email is already taken. Please try another one.',
                ]);
            }
        }

        return response()->json([
            'status' => 'success',
        ]);
    }

    public function changeEmailRequest(Request $request)
    {
        $user = auth()->user();

        $old_email_otp = rand(100000, 999999);
        $user->old_email_verify_otp = md5($old_email_otp);
        $otp = rand(100000, 999999);
        $user->email_verify_otp = md5($otp);

        if($user->save()) {
            Mail::to($user)->queue(new ChangeEmailRequest($old_email_otp, $user->name));
            $user->email = $request->new_email;
            Mail::to($user)->queue(new ChangeEmailRequest($otp, $user->name));
        }

        return response()->json([
            'status' => 'success',
        ]);
    }

    public function changeEmailConfirm(Request $request)
    {
        $user = auth()->user();

        if(md5($request->otp) != $user->email_verify_otp || md5($request->old_email_otp) != $user->old_email_verify_otp) {
            return response()->json([
                'status' => 'error',
                'message' => 'Wrong OTP, please try again.',
            ]);
        }

        $user->email = $request->email;

        if($user->save()) {
            // Send Email:7:
            Mail::to($user)->queue(new ChangeEmailConfirmMail($user));

            return response()->json([
                'status' => 'success',
                'message' => 'Email changed successfully.',
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Oops! Something went wrong. Please try again later.',
        ]);
    }

    public function verifyEmail(Request $request)
    {
        $user = auth()->user();

        if(md5($request->otp) != $user->email_verify_otp) {
            return response()->json([
                'status' => 'error',
                'message' => 'Wrong OTP, please try again.',
            ]);
        }

        $user->email_verified_at = now();

        if($user->save()) {
            // Send Email:2:
            Mail::to($user)->queue(new WelcomeMail($user));


            return response()->json([
                'status' => 'success',
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Oops! Something went wrong. Please try again later.',
        ]);
    }

    public function resendVerifyEmailOtp(Request $request)
    {
        $user = auth()->user();

        if($user->email_verified_at) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email already verified',
            ]);
        }

        $otp = rand(100000, 999999);
        $user->email_verify_otp = md5($otp);

        if($user->save()) {
            Mail::to($user)->queue(new UserRegistered($otp, $user->name));

            return response()->json([
                'status' => 'success',
            ]);
        }

        return response()->json([
            'status' => 'failed',
            'message' => 'Oops! Something went wrong. Please try again later.',
        ]);
    }

    public function updateMySubscription(Request $request) {
        $user = auth()->user();
        if($user->currentSubscription) {
            $user->currentSubscription->is_yearly_auto_renew = $request->value;
            $user->currentSubscription->save();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Auto renew setting updated successfully.',
        ]);
    }
}
