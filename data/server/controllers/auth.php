<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

function sendAuthResponse($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendAuthResponse(false, 'Неверный метод запроса.');
}

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

// Фиксируем ваши боевые учетные данные напрямую в PHP
if ($username === 'ssyck' && $password === 'password2026') {
    $_SESSION['is_engineer_authorized'] = true;
    $_SESSION['operator_name'] = $username;
    $_SESSION['auth_created'] = time();
    
    sendAuthResponse(true, 'Авторизация успешно пройдена.');
} else {
    sendAuthResponse(false, 'Неверное имя инженера или пароль.');
}