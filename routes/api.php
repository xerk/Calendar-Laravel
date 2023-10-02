<?php

use App\Http\Middleware\ValidCountry;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\SanctumController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\BookingCalendarController;
use App\Http\Controllers\BookingCalendarV2Controller;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('login', [SanctumController::class, 'login'])->name('api.login');
Route::post('register', [UserController::class, 'store'])->name('api.register.store');
Route::post('reset-password', [AuthController::class, 'resetPasswordSendEmail'])->name('api.auth.resetPasswordSendEmail');
Route::post('reset-password-update', [AuthController::class, 'resetPasswordUpdate'])->name('api.auth.resetPasswordUpdate');

// add ValidCountry
Route::middleware('auth:sanctum', ValidCountry::class)->get('/me', [UserController::class, 'me'])->name('api.me');
Route::middleware('auth:sanctum')->put('/me', [UserController::class, 'update'])->name('api.me.update');
Route::middleware('auth:sanctum')->put('/my-subscription', [UserController::class, 'updateMySubscription'])->name('api.me.updateMySubscription');
Route::middleware('auth:sanctum')->post('/change-email-request', [UserController::class, 'changeEmailRequest'])->name('api.me.changeEmailRequest');
Route::middleware('auth:sanctum')->post('/change-email-confirm', [UserController::class, 'changeEmailConfirm'])->name('api.me.changeEmailConfirm');
Route::middleware('auth:sanctum')->post('/check-username', [UserController::class, 'checkUsername'])->name('api.checkUsername');
Route::middleware('auth:sanctum')->post('/check-calendar-slug', [CalendarController::class, 'checkSlug'])->name('api.checkCalendarSlug');
Route::middleware('auth:sanctum')->post('/check-email', [UserController::class, 'checkEmail'])->name('api.checkEmail');

Route::middleware('auth:sanctum')->resource('/plans', PlanController::class);
Route::middleware('auth:sanctum')->resource('/transactions', TransactionController::class);
Route::middleware('auth:sanctum')->resource('/availabilities', AvailabilityController::class);
Route::middleware('auth:sanctum')->resource('/calendars', CalendarController::class);
Route::middleware('auth:sanctum')->resource('/bookings', BookingController::class)->except(['store', 'update']);
Route::middleware('auth:sanctum')->post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('api.bookings.cancel');
Route::middleware('auth:sanctum')->post('/bookings/{booking}/confirm', [BookingController::class, 'confirm'])->name('api.calendars.confirm');
Route::middleware('auth:sanctum')->post('/bookings/{booking}/update-meeting-note', [BookingController::class, 'meetingNote'])->name('api.bookings.meetingNote');
Route::middleware('auth:sanctum')->post('/calendars/{id}/clone', [CalendarController::class, 'clone'])->name('api.calendars.clone');
Route::middleware('auth:sanctum')->put('/calendars/{calendar}/update-status', [CalendarController::class, 'updateStatus'])->name('api.calendars.status');
Route::middleware('auth:sanctum')->get('/default-availabilities', [AvailabilityController::class, 'indexDefault'])->name('api.availabilities.indexDefault');
Route::middleware('auth:sanctum')->put('/default-availability/{id}', [AvailabilityController::class, 'updateDefault'])->name('api.availabilities.updateDefault');

// Dashboard
Route::middleware('auth:sanctum')->get('/dashboard', [DashboardController::class, 'index'])->name('api.dashboard.index');

Route::middleware('auth:sanctum')->post('/renew-subscription', [SubscriptionController::class, 'processRecurringPayment'])->name('api.subscriptions.processRecurringPayment');
Route::middleware('auth:sanctum')->delete('/subscriptions/{subscriptionId}/cancel', [SubscriptionController::class, 'cancel'])->name('api.subscriptions.cancel');
Route::middleware('auth:sanctum')->post('/subscriptions/{subscriptionId}/resume', [SubscriptionController::class, 'resume'])->name('api.subscriptions.resume');
Route::middleware('auth:sanctum')->post('/subscriptions/{subscriptionId}/upgrade', [SubscriptionController::class, 'cancelAndUpgrade'])->name('api.subscriptions.cancel-and-upgrade');
Route::middleware('auth:sanctum')->get('/subscriptions/{subscriptionId}', [SubscriptionController::class, 'show'])->name('api.subscriptions.show');

Route::middleware('auth:sanctum')->post('/verify-email', [UserController::class, 'verifyEmail'])->name('api.verifyEmail');
Route::middleware('auth:sanctum')->post('/resend-verify-email-otp', [UserController::class, 'resendVerifyEmailOtp'])->name('api.resendVerifyEmailOtp');

// Booking Calendar API
Route::get('/booking-calendars/{username}', [BookingCalendarController::class, 'index'])->name('api.bookingCalendars.index');
Route::get('/booking-calendars/{username}/{slug}', [BookingCalendarController::class, 'show'])->name('api.bookingCalendars.show');
Route::post('/bookings', [BookingController::class, 'store'])->name('api.bookings.store');
Route::put('/bookings/{booking}', [BookingController::class, 'update'])->name('api.bookings.update');

// Booking Calendar V2 API
Route::get('/booking-calendars-v2/{username}', [BookingCalendarV2Controller::class, 'index'])->name('api.bookingCalendarsV2.index');
Route::get('/booking-calendars-v2/{username}/{slug}', [BookingCalendarV2Controller::class, 'show'])->name('api.bookingCalendarsV2.show');
Route::get('/booking-calendars-v2/{username}/{slug}/{booking}/reschedule', [BookingCalendarV2Controller::class, 'reschedule'])->name('api.bookingCalendarsV2.reschedule');
Route::post('/bookings/{booking}/cancel-booking', [BookingController::class, 'cancel'])->name('api.bookings.cancel');
Route::post('/bookings/{booking}/reschedule', [BookingController::class, 'reschedule'])->name('api.bookings.reschedule');
// Download ICS
Route::get('/bookings/{booking}/download-ics', [BookingController::class, 'downloadICS'])->name('api.bookings.downloadICS');
Route::get('/bookings/{uid}/booking-uid', [BookingController::class, 'bookingUid'])->name('api.bookings.downloadICS');

Route::name('oauth2.auth')->get('/oauth2/{provider}', [AccountController::class, 'auth']);
Route::name('oauth2.callback')->get('/oauth2/{provider}/callback', [AccountController::class, 'callback']);
Route::middleware('auth:sanctum')->post('/oauth2/{provider}/connect/{provider_id}', [AccountController::class, 'connect'])->name('oauth.connect');
Route::middleware('auth:sanctum')->delete('/oauth2/{provider}/disconnect/{id}', [AccountController::class, 'disconnect'])->name('oauth.connect');
