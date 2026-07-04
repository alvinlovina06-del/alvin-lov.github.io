<?php
require 'config/app.php';
require 'src/OTPService.php';

use App\OTPService;

$otpService = new OTPService();
$verifyResult = $otpService->verify(1, '123456'); // passing arbitrary user id and OTP
var_dump($verifyResult);
