<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\LogRecord;

class ScrubSensitiveData
{
    /** @var array<string> */
    private array $sensitiveKeys = ['password', 'token', 'cc_number', 'password_confirmation'];

    public function __invoke(object $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->pushProcessor(function (LogRecord|array $record) {
                // Handle both Monolog 2 (array) and Monolog 3 (LogRecord)
                $data = $record instanceof LogRecord ? $record->toArray() : $record;

                $data['context'] = $this->scrub($data['context']);
                $data['extra'] = $this->scrub($data['extra']);

                return $record instanceof LogRecord
                    ? $record->with(context: $data['context'], extra: $data['extra'])
                    : $data;
            });
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function scrub(array $data): array
    {
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $value = $this->scrub($value);
            } elseif (in_array(mb_strtolower((string) $key), $this->sensitiveKeys, true)) {
                $value = '********';
            }
        }

        return $data;
    }
}
