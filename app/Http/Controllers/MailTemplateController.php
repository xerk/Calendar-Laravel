<?php

namespace App\Http\Controllers;

use App\Mail\WelcomeMail;
use Illuminate\Http\Request;
use Spatie\MailTemplates\Models\MailTemplate;

class MailTemplateController extends Controller
{
    public function index()
    {
        $mailTemplates = MailTemplate::all();
        return view('mail-templates.index', compact('mailTemplates'));
    }

    /**
     * Create a new mail template.
     */
    public function create()
    {
        // Return List of mail in mail folder app/Mail
        $mails = array_diff(scandir(app_path('Mail')), array('.', '..'));
        return view('mail-templates.create', compact('mails'));
    }

    /**
     * Edit a mail template.
     */
    public function edit(MailTemplate $mailTemplate)
    {
        // Get variables from mail template
        return view('mail-templates.edit', compact('mailTemplate'));
    }

    /**
     * Store a new mail template, and create a artisan command to generate mail template.
     */
    public function store(Request $request)
    {
        $request->validate(
            [
                // valid as "WelcomeMail" camel case
                'mailable' => 'required|regex:/^[a-zA-Z]+$/u',
            ],
            [
                'mailable.regex' => 'Mailable name must be in camel case, eg: WelcomeMail',
            ]
        );
        // Check if file exist in app/Mail folder
        if (file_exists(app_path('Mail/'.$request->mailable.'.php'))) {
            // validation error to mailable field
            return redirect()->back()->withErrors(['mailable' => 'Mailable already exist.']);
        }

        // Create a artisan command to generate mail template. `php artisan make:mail WelcomeMail`
        $call = \Artisan::call('make:mail', [
            'name' => $request->mailable,
        ]);

        $mailTemplate = MailTemplate::create([
            'mailable' => 'App\Mail\\' . $request->mailable,
        ]);

        return redirect()->route('mailable.edit', ['mailTemplate' => $mailTemplate->id])->with('success', 'Mail template created successfully.');
    }
}
