<?php

declare(strict_types=1);

namespace App\Services;

class VonageSmsService
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

    public function send(\App\Models\Project $project, \App\Models\Message $message): string
    {
        $recipient = $message->users()->pluck('mobile')->filter()->first();

        if (! $recipient) {
            return 'No recipient available';
        }

        $body = $message->message."\n project link:\n".config('app.url').'/project/'.$project->slug;

        $response = $this->client->sms()->send(
            new \Vonage\SMS\Message\SMS(
                $recipient,
                config('services.vonage.from'),
                $body
            )
        );

        $msg = $response->current();

        if ($msg->getStatus() === 0) {
            return "The message was sent successfully\n";
        }

        return 'The message failed with status: '.$msg->getStatus()."\n";
    }
}
