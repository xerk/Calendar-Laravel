<?php

namespace App\Mail;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;

class RecurringFailMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $plan;
    public $reason;
    public $expire_at;
    public $period;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(User $user = null, Plan $plan = null, $reason = null, $expire_at = null, $period = null)
    {
        $this->user = $user;
        $this->plan = $plan;
        $this->reason = $reason;
        $this->expire_at = $expire_at;
        $this->period = $period;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: 'Your Monthly Subscription Payment Has Failed',
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
            markdown: 'emails.subscribe.recurring-fail',
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
