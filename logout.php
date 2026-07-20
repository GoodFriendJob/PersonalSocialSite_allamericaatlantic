<?php

require_once __DIR__ . '/app/core/Session.php';

Session::logout();

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header("Location: login.html");
exit;
