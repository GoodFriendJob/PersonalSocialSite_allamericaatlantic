
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>Login Debug Start</h2>";

$url = "https://allamericaatlantic.com/api/index.php?route=auth/login";

$data = [
    "email" => "charlesf426@gmail.com",
    "password" => "freefall426"
];

echo "<pre>";
echo "URL: $url\n";
echo "POST DATA:\n";
print_r($data);
echo "</pre>";

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

$response = curl_exec($ch);
$error = curl_error($ch);
$info = curl_getinfo($ch);

curl_close($ch);

echo "<h3>cURL Info:</h3>";
echo "<pre>";
print_r($info);
echo "</pre>";

echo "<h3>cURL Error:</h3>";
echo "<pre>";
echo $error ? $error : "No cURL error";
echo "</pre>";

echo "<h3>API Response:</h3>";
echo "<pre>";
echo $response ? $response : "No response received";
echo "</pre>";


?>
