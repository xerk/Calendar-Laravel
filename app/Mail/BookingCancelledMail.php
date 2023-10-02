<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Booking;
use App\Models\Calendar;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;

class BookingCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $booking;
    public $calendar;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(User $user = null, Booking $booking = null, Calendar $calendar = null) {
        $this->user = $user;
        $this->booking = $booking;
        $this->calendar = $calendar;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: 'Appointment Canceled on Your Calendar',
        );
    }

    /**
     * Get the message content definition.
     *
     * @return \Illuminate\Mail\Mailables\Content
     */
    public function content()
    {
        return new Content(
            markdown: 'emails.bookings.cancelled',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array
     */
    public function attachments()
    {
        return [];
    }
}
