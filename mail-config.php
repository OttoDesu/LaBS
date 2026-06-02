<?php
return [
    // Supported: 'auto', 'phpmailer', 'smtp', or 'mail'
    // 'phpmailer' will use Composer-installed PHPMailer with SMTP settings below.
    'transport' => 'phpmailer',

    // SMTP server settings
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'encryption' => 'tls', // tls, ssl, or none
    'username' => 'tengkunormazlina@gmail.com',
    'password' => 'jzqp cajz ozte ohde',
    'timeout' => 15,

    // Sender identity
    'from_email' => 'tengkunormazlina@gmail.com',
    'from_name' => 'LaBS PPMKCP',

    // Optional debug log
    'debug_log' => __DIR__ . '/logs/mail.log'
];
