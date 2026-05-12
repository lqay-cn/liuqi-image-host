<?php
require 'config.php';
if (isset($_SESSION['user'])) {
    echo json_encode([
        'username' => $_SESSION['user'],
        'is_admin' => ($_SESSION['user'] === 'admin')
    ]);
} else {
    echo json_encode(['username' => null]);
}
?>