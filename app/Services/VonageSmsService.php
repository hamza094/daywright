<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Integrations\Sms\SmsException;
use App\Exceptions\Integrations\Sms\SmsMissingRecipientException;
use App\Exceptions\Integrations\Sms\SmsProviderException;
use Illuminate\Support\Facades\Log;
use Throwable;

class VonageSmsService
{
    private readonly \Vonage\Client $client;

    private readonly ?\Vonage\Client\Credentials\Basic $basic;

    public function __construct(?\Vonage\Client $client = null)
    {
        if ($client instanceof \Vonage\Client) {
            $this->client = $client;
            $this->basic = null;

            return;
        }

        $options = config('services.vonage');

        $this->basic = new \Vonage\Client\Credentials\Basic(
            $options['api_key'], $options['secret_key']
        );

        $this->client = new \Vonage\Client($this->basic);
    }

    public function send(\App\Models\Project $project, \App\Models\Message $message): string
    {
        $recipient = $message->users()
            ->with('info')
            ->get()
            ->map(fn (\App\Models\User $user): ?string => $user->info->mobile ?? ($user->getAttribute('mobile') !== null ? (string) $user->getAttribute('mobile') : null))
            ->filter()
            ->first();

        if (! $recipient) {
            Log::warning('Vonage SMS delivery failed: missing mobile recipient', [
                'project_id' => $project->id,
                'message_id' => $message->id,
            ]);

            throw new SmsMissingRecipientException("No mobile recipient available for message [{$message->id}].");
        }

        $body = $message->message."\n project link:\n".config('app.url').'/project/'.$project->slug;

        $msg = null;

        try {
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

            $status = $msg->getStatus();
            Log::error('Vonage SMS delivery rejected with non-zero status', [
                'project_id' => $project->id,
                'message_id' => $message->id,
                'recipient' => $recipient,
                'vonage_status' => $status,
            ]);

            throw SmsProviderException::fromStatus($status);
        } catch (SmsException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Vonage SMS API failed', [
                'project_id' => $project->id,
                'message_id' => $message->id,
                'recipient' => $recipient,
                'vonage_status' => $msg?->getStatus(),
                'exception_class' => $e::class,
                'exception_code' => $e->getCode(),
            ]);

            throw SmsProviderException::transient(
                message: 'Vonage SMS API request failed: '.$e->getMessage(),
                previous: $e,
            );
        }
    }
}
