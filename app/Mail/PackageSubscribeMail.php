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

class PackageSubscribeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $package_period;
    public $package_name;
    public $package_description;

    /**
     * Create a new message instance.
     *$transaction->subscription->user,
                    ($transaction->subscription->period == 1 ? 'Monthly' : 'Yearly'),
                    $transaction->subscription->plan_data['name'],
                    $transaction->subscription->plan_data['description'],
     * @return void
     */
    public function __construct(User $user = null, $package_period = null, $package_name = null, $package_description = null)
    {
        $this->user = $user;
        $this->package_period = $package_period;
        $this->package_name = $package_name;
        $this->package_description = $package_description;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            // subject: 'Subject: Welcome to Grandcalendar.io [Package Name]!',
            subject: 'Subject: Welcome to Grandcalendar.io '.$this->package_name.'!',
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
            markdown: 'emails.subscribe.subscribe-package',
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
