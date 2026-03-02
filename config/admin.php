<?php

declare(strict_types=1);

$adminEmails = array_values(array_filter(
    array_map('trim', explode(',', (string) env('ADMIN_EMAILS', '')))
));

$config = [
    'emails' => $adminEmails,
];

return $config;
