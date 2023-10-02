<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;

class SubscriptionCancellationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $old_plan_name;
    public $new_plan_name;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(User $user = null, $old_plan_name = null, $new_plan_name = null)
    {
        $this->user = $user;
        $this->old_plan_name = $old_plan_name;
        $this->new_plan_name = $new_plan_name;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: 'Confirmation of Subscription Cancellation',
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
            markdown: 'emails.subscribe.cancellation',
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
