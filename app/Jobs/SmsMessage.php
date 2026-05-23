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

    /**
     * The project instance.
     *
     * @var Project
     */
    private $project;
    // private $user;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Project $project, private Message $message)
    {
        $this->project = $project;
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
