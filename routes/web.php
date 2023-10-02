<?php

use Carbon\Carbon;
use Firebase\JWT\JWT;
use App\Services\Zoom;
use App\Mail\WelcomeMail;
use App\Services\Google\Event;
use App\Models\ProviderAccount;
use App\Services\TokenEncrypter;
use App\Services\Google\UserService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\PaytabsController;
use App\Http\Controllers\ProfileController;
use Spatie\MailTemplates\Models\MailTemplate;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\MailTemplateController;
use App\Http\Controllers\SubscriptionController;
use App\Services\Google\Provider\GoogleProvider;
use App\Http\Controllers\PaymentMethodController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('reset-password/{token}', [AuthController::class, 'resetPassword'])->name('password.reset');
Route::get('/email/verify/{id}/{hash}', [UserController::class, 'verifyEmail'])->middleware(['auth', 'signed'])->name('verification.verify');

Route::post('paytabs/return', [PaytabsController::class, 'return'])->name('paytabs.return');
Route::get('pay/{planId}', [PaytabsController::class, 'pay'])->name('paytabs.pay');

Route::post('subscribe/return', [SubscriptionController::class, 'paygateReturn'])->name('subscriptions.paygateReturn');
Route::get('subscribe/{userId}/{planId}', [SubscriptionController::class, 'subscribe'])->name('subscriptions.subscribe');

Route::get('invoices/{id}', [TransactionController::class, 'invoice'])->name('transaction.invoice');

Route::get('/payment-methods/create/{userId}', [PaymentMethodController::class, 'create'])->name('paymentMethods.create');
Route::post('/payment-methods/return/{paymentMethodId}', [PaymentMethodController::class, 'store'])->name('paymentMethods.return');

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/mail/list', [MailTemplateController::class, 'index']);
    // create
    Route::get('/mail/create', [MailTemplateController::class, 'create']);
    Route::get('/mail/edit/{mailTemplate}', [MailTemplateController::class, 'edit'])->name('mailable.edit');
    Route::post('/mail/create', [MailTemplateController::class, 'store'])->name('mailable.create');
});

// Delete all booking
Route::get('/delete-all-bookings/{token}', function () {
    if (request()->token == 'hadi-booking-delete') {
        \App\Models\Booking::truncate();
        return 'All bookings deleted';
    }
});

Route::get('/mail/send', function () {
    // dd(WelcomeMail::getVariables());
    $user = \App\Models\User::find(46);
    Mail::to($user->email)->send(new WelcomeMail($user));
});

Route::get('/test-app', function () {
    $user = ProviderAccount::where('provider', 'zoom')->first();

    $client = new \GuzzleHttp\Client();

    $client = Http::asForm()->post("https://zoom.us/oauth/token", [
        'grant_type' => 'refresh_token',
        'refresh_token' => $user->refresh_token,
        'client_id' => env('ZOOM_CLIENT_ID'),
        'client_secret' => env('ZOOM_CLIENT_SECRET'),
    ]);

    dd($client->json());


    $meeting = new Zoom($user);

    $meeting->name = 'Test Meeting';
    $meeting->duration = 60;
    $meeting->startTime = Carbon::now('Africa/Cairo');
    $meeting->timezone = 'Africa/Cairo';
    $meeting->save(\App\Models\Booking::first());

    return 'Zoom App';


    // // Refresh token if expired
    // if ($user->tokenHasExpired()) {
    //     $user->refreshToken();
    // }

    // $booking = \App\Models\Booking::first();

    // $event = new Event($user);
    // // Human Resource Interview between Ahmed Mamdouh and Dacey Luna
    // $event->name = 'Event Test';
    // $event->description = 'asdas';
    // $event->startDateTime = \Carbon\Carbon::parse();
    // $event->endDateTime = \Carbon\Carbon::now()->addMinutes(60);
    // $event->location = 'test';
    // // $event->addAttendee([
    // //     'email' => $this->invitee_email,
    // //     'displayName' => $this->invitee_name,
    // //     'comment' => $this->invitee_note
    // // ]);
    // // if (!empty($this->other_invitees)) {
    // //     foreach ($this->other_invitees as $invitee) {
    // //         $event->addAttendee([
    // //             'email' => $invitee['value'],
    // //         ]);
    // //     }
    // // }

    // // if ($user->meeting_type === 'google_meet' && $this->isGoogleMeet()) {
    //     $event->addMeetLink();
    // // }
    // $event->save($booking);
    // return 'Calendar App';

});



require __DIR__.'/auth.php';
