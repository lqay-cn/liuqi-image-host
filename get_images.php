<?php
require 'config.php';
checkLogin();
header('Content-Type: application/json');
echo json_encode(array_values(getUserImages($_SESSION['user'])));
?>