
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<pre>";

echo "Testing basic PHP...\n";
echo "OK\n\n";

echo "Testing JSON...\n";
echo json_encode(["test" => 123]) . "\n\n";

echo "Testing cURL...\n";
if (function_exists('curl_version')) {
    echo "cURL is enabled\n";
} else {
    echo "cURL is DISABLED\n";
}

echo "</pre>";
