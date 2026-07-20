<?php
$data = [
    "username" => "testuser",
    "email" => "test@example.com",
    "password" => "123456"
];

$ch = curl_init("https://allamericaatlantic.com/app/auth/register");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

$result = curl_exec($ch);
curl_close($ch);

echo $result;
