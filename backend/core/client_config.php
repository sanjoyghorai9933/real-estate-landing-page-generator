<?php
function property_matrimony_config(): array
{
    return [
        'smtp' => [
            'server' => getenv('PROPERTY_MATRIMONY_SMTP_SERVER') ?: '',
            'port' => (int) (getenv('PROPERTY_MATRIMONY_SMTP_PORT') ?: 465),
            'secure' => getenv('PROPERTY_MATRIMONY_SMTP_SECURE') ?: 'ssl',
            'username' => getenv('PROPERTY_MATRIMONY_SMTP_USERNAME') ?: '',
            'password' => getenv('PROPERTY_MATRIMONY_SMTP_PASSWORD') ?: '',
        ],
    ];
}
