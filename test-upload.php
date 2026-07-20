<?php

$token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpZCI6MTcsInVzZXJuYW1lIjoiY2hhcmxlc190ZXN0MyIsInJvbGUiOiJ1c2VyIiwiZXhwIjoxNzc2OTgxMjgzfQ.VN_zaR_pbzUUlBtrr7hYwykeZW8JE-7ioOb0utP6bjE";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

      $file = $_FILES['media'];

   $postData = [
    'caption'   => $_POST['caption'] ?? '',
    'thumbnail' => $_POST['thumbnail'] ?? '',
    'media'     => new CURLFile($file['tmp_name'], $file['type'], $file['name'])
];


   $ch = curl_init("https://allamericaatlantic.com/app/index.php?route=stories");


    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token"
    ]);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $error = curl_error($ch);

    curl_close($ch);

    echo "<pre>";
    echo $response ?: $error;
    echo "</pre>";

}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Test Story Upload</title>
</head>
<body>

<h2>Upload Story (Image or Video)</h2>

<form method="POST" enctype="multipart/form-data">
    <label>Select Image or Video:</label><br>
    <input type="file" name="media" id="mediaInput" required>
    <input type="hidden" name="thumbnail" id="thumbnailInput">


    <label>Caption (optional):</label><br>
    <input type="text" name="caption" placeholder="Enter caption"><br><br>

    <button type="submit">Upload Story</button>
</form>



<!-- java script for thumnail upload -->
<script>
document.getElementById('mediaInput').addEventListener('change', function (e) {
    const file = e.target.files[0];
    if (!file) return;

    if (!file.type.startsWith('video/')) {
        // Not a video ? no thumbnail needed
        return;
    }

    const video = document.createElement('video');
    video.src = URL.createObjectURL(file);
    video.crossOrigin = "anonymous";
    video.muted = true;
    video.playsInline = true;

    video.addEventListener('loadeddata', () => {
        video.currentTime = 1; // capture at 1 second
    });

   video.addEventListener('seeked', () => {
    const maxWidth = 320;
    const scale = maxWidth / video.videoWidth;

    const canvas = document.createElement('canvas');
    canvas.width = maxWidth;
    canvas.height = video.videoHeight * scale;

    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    const thumbnailBase64 = canvas.toDataURL('image/jpeg', 0.5);

    document.getElementById('thumbnailInput').value = thumbnailBase64;

    URL.revokeObjectURL(video.src);
  });

});
</script>

<!-- end of thumbnail upload -->
</body>
</html>
