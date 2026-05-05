<?php

declare(strict_types=1);

namespace App\Services;

class SendSmsService
{
    private readonly \Vonage\Client $client;

    private readonly \Vonage\Client\Credentials\Basic $basic;

    public function __construct()
    {
        $options = config('services.vonage');

        $this->basic = new \Vonage\Client\Credentials\Basic(
            $options['api_key'], $options['secret_key']
        );

        $this->client = new \Vonage\Client($this->basic);
    }

    public function send($project, $message): string
    {
        $response = $this->client->sms()->send(
            new \Vonage\SMS\Message\SMS(
                '923096802966',
                config('services.vonage.from'),
                $message->message.'\n'.' project link:'.'\n'.config('app.url').'/project/'.$project->slug
            )
        );

        $msg = $response->current();

        if ($msg->getStatus() === 0) {
            return "The message was sent successfully\n";
        }

        return 'The message failed with status: '.$msg->getStatus()."\n";

    }
}
