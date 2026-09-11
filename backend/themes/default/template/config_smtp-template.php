<?php
// SMTP credentials are intentionally read from server environment variables.
// Never commit the real password to this repository.
$cfg_server   = getenv('PROPERTY_MATRIMONY_SMTP_SERVER') ?: '';
$cfg_port     = (int) (getenv('PROPERTY_MATRIMONY_SMTP_PORT') ?: 465);
$cfg_secure   = getenv('PROPERTY_MATRIMONY_SMTP_SECURE') ?: 'ssl';
$cfg_username = getenv('PROPERTY_MATRIMONY_SMTP_USERNAME') ?: '';
$cfg_password = getenv('PROPERTY_MATRIMONY_SMTP_PASSWORD') ?: '';
