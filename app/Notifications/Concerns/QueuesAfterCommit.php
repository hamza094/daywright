<?php

declare(strict_types=1);

namespace App\Notifications\Concerns;

trait QueuesAfterCommit
{
    /**
     * Configure the notification to queue after database commit and use the default queue.
     */
    protected function configureQueue(): void
    {
        $this->afterCommit();
        $this->onQueue('default');
    }
}
