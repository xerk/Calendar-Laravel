<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\Booking;
use Illuminate\Console\Command;
use App\Notifications\GeneralNotification;

class ExpiredBooking extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'expired:booking';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancel booking if the date is passed and the booking is not confirmed by the host';

    /**
     * Execute the console command.
     * Confirm a booking that requires confirmation (if the user is the owner of the calendar)
     * Date shouldn't be in the past and the booking shouldn't be cancelled
     * is there like expiry time where if meeting not confirmed by host to be auto canceled? or it will be canclled if the booking date is passed as below example
     * What if meeting is booked for 11 of April.. then if 11 of April passed then it will be not valid.. meaning host can not confirm it.. and invitee will get email saying its canceled cuz time passed..etc
     * @return int
     */
    public function handle()
    {
        $bookings = Booking::where('is_confirmed', false)->whereNull('cancelled_at')->whereNull('expired_at')->get();
        if ($bookings->count() == 0) {
            $this->info('No bookings to confirm');
            return 0;
        }

        // dd($bookings);

        foreach ($bookings as $booking) {
            // Add time to booking->date + $booking->start
            // utc
            $date = Carbon::parse($booking->date_time);

            if ($date->isPast()) {
                $booking->expired_at = now();
                $booking->save();
                // Send notifications
                if ($booking) {
                    $booking->user->notify(new GeneralNotification('Email:29: Expired or cancelled Booking', 'Email:29:Content Expired or cancelled Host'));

                    // Notify invitee_email
                    $booking->notify(new GeneralNotification('Email:30: Expired or cancelled Booking', 'Email:30:Content Expired or cancelled Invitee'));
                    $this->info('Booking Cancelled #'.$booking->id);
                }
                \DB::commit();
            }
        }

        return Command::SUCCESS;

    }
}
