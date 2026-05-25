<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Message;
use App\Models\Project;
use App\Services\VonageSmsService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SmsMessage implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // private $user;
    /**
     * Create a new job instance.
     */
    public function __construct(/**
     * The project instance.
     */
        private Project $project, private Message $message)
    {
        // $this->user=$user;
    }

    /**
     * Execute the job.
     */
    public function handle(VonageSmsService $service): void
    {
        $service->send($this->project, $this->message);
    }
}
