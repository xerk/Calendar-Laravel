<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\Calendar;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;

class BookingCancelledInviteeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $invitee_name;
    public $booking;
    public $calendar;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($invitee_name = null, Booking $booking = null, Calendar $calendar = null) {
        $this->invitee_name = $invitee_name;
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
            subject: 'Your Appointment with '.$this->calendar->name.' Has Been Canceled',
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
            markdown: 'emails.bookings.cancelled-invitee',
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
