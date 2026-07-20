<?php
// 1. Force error reporting to screen/browser console
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Try to write to a brand new log file to bypass old file permission locks
file_put_contents(__DIR__ . "/test_debug.txt", "PHP IS ALIVE\n", FILE_APPEND);

// 3. Set your API headers
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");

// 4. Safely capture input
$raw_data = file_get_contents("php://input");
$input = json_decode($raw_data, true);

// 5. Load external files
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../core/Response.php";
require_once __DIR__ . "/../controllers/AuthController.php";

// 6. Run process
AuthController::register($input);
