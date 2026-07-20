<?php
// Keep PHP warnings out of the JSON response. They are still written to the
// configured server error log.
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");

$raw_data = file_get_contents("php://input");
$input = json_decode($raw_data, true);

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../core/Response.php";
require_once __DIR__ . "/../controllers/AuthController.php";

AuthController::register($input);
