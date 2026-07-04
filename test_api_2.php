<?php
$ch = curl_init('http://localhost/uaskte/public/api/otp-verify.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['otp' => 123456])); // Sending as INT!
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
echo "RAW RESPONSE:\n" . $response;
