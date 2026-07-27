<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\LogRecord;

class ScrubSensitiveData
{
    private array $sensitiveKeys = ['password', 'token', 'cc_number', 'password_confirmation'];

    public function __invoke($logger)
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

    private function scrub(array $data): array
    {
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $value = $this->scrub($value);
            } elseif (is_string($key) && in_array(mb_strtolower($key), $this->sensitiveKeys, true)) {
                $value = '********';
            }
        }

        return $data;
    }
}
