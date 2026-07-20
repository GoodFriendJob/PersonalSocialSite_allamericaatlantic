<?php
session_start();
$name = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Welcome</title>
</head>
<body>

<h1>Welcome, <?php echo $name; ?>!</h1>
<p>You are now logged in.</p>

</body>
</html>
