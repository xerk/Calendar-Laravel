<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;

class TestAdminNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'saas:test_admin_notification';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->sendAdminNotification("Failed recurring payment (Code 422: Unable to process your request)");

        return Command::SUCCESS;
    }

    function sendAdminNotification($message)
    {

        $client = new Client(['http_errors' => false]);

        $body = [
            "message" => $message,
        ];

        $response = $client->request('POST', config('saas.admin_url').'/webhooks/notifications', [
            'form_params' => $body,
        ]);

        return [
            'code' => $response->getStatusCode(),
            'data' => $response->getBody()? json_decode($response->getBody()) : null,
        ];
    }
}
