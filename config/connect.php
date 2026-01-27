<?php
/**
 * Database connection and app paths.
 * APP_ROOT: filesystem path to project root.
 * BASE_PATH: web-relative base path ('' if at root, '/complaint_system' if in subdir).
 */

define('APP_ROOT', dirname(__DIR__));
define('BASE_PATH', '/complaint_system');

function base_url($path) {
    $p = '/' . ltrim($path, '/');
    return (defined('BASE_PATH') && BASE_PATH !== '') ? rtrim(BASE_PATH, '/') . $p : $p;
}

$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "complaintsystem";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
