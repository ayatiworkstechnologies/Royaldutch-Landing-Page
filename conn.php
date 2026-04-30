<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "royaldutch";

/* ---------- CREATE CONNECTION ---------- */
$conn = new mysqli($host, $user, $pass, $db);

/* ---------- CHECK CONNECTION ---------- */
if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode([
        "status" => "error",
        "message" => "Database connection failed"
    ]));
}

/* ---------- SET CHARSET ---------- */
$conn->set_charset("utf8mb4");

/* ---------- OPTIONAL: STRICT MODE ---------- */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
?>