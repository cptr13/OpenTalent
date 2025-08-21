<?php // test_mailer.php
require __DIR__ . '/vendor/autoload.php';
echo class_exists(\PHPMailer\PHPMailer\PHPMailer::class) ? "OK" : "FAIL";
