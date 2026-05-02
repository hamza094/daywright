<?php

declare(strict_types=1);

namespace App\Actions\Project;

use App\Jobs\MailMessage;
use App\Jobs\SmsMessage;
use App\Models\Message;
use App\Models\Project;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Throwable;

final class DispatchProjectMessageAction
{
    public function execute(Project $project, Message $message): void
    {
        $jobClass = $message->type === 'mail' ? MailMessage::class : SmsMessage::class;

        $jobs = $message->users
            ->map(fn ($user): MailMessage|SmsMessage => new $jobClass($project, $message, $user));

        $batch = Bus::batch($jobs)
            ->allowFailures()
            ->then(function () use ($message): void {
                $message->delivered = true;
                $message->save();
            })
            ->catch(function (Batch $batch, Throwable $throwable): void {})
            ->dispatch();

        $message->update([
            'batch_id' => $batch->id,
        ]);
    }
}
