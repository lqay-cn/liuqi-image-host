<?php
require 'config.php';
checkAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newUser = sanitize($_POST['username']);
    $newPass = $_POST['password'];
    $users = json_decode(file_get_contents(DATA_DIR . 'users.json'), true);
    if (isset($users[$newUser])) {
        echo json_encode(['success' => false, 'error' => '用户已存在']);
    } else {
        $users[$newUser] = password_hash($newPass, PASSWORD_DEFAULT);
        file_put_contents(DATA_DIR . 'users.json', json_encode($users, JSON_PRETTY_PRINT));
        logAction('create_user', "管理员创建用户 $newUser");
        echo json_encode(['success' => true, 'message' => "用户 $newUser 创建成功"]);
    }
}
?>