<!DOCTYPE html>
<html>
<head>
    <title>Story Upload Test</title>
</head>
<body>

<h2>Upload Story Test</h2>

<form action="../../api/test-upload.php" method="POST" enctype="multipart/form-data">
    <label>Select Image or Video:</label><br><br>
    <input type="file" name="media" required><br><br>
    <input type="submit" name="upload" value="Upload Story">
</form>

<hr>

<?php
if (isset($_POST['upload'])) {

    // API endpoint
    $apiUrl = "https://allamericaatlantic.com/api";

    // File from form
    $file = $_FILES['media'];

    // Prepare cURL
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => [
            "media" => new CURLFile($file['tmp_name'], $file['type'], $file['name'])
        ]
    ]);

    $response = curl_exec($curl);
    $error = curl_error($curl);

    curl_close($curl);

    echo "<h3>API Response:</h3>";

    if ($error) {
        echo "<pre style='color:red;'>cURL Error: $error</pre>";
    } else {
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    }
}
?>

</body>
</html>
