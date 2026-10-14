<?php
// controllers/admin_control.php
session_start(); // ОБЯЗАТЕЛЬНО включаем сессию и здесь!

ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// ... (код чтения config.ini и путей остается прежним) ...

// Перехватываем имя пользователя из защищенной сессии сервера
$remoteUser = $_SESSION['backend_auth_user'] ?? null;

// Жесткий замок безопасности: теперь XAMPP гарантированно увидит, что это ssyck
if ($remoteUser !== 'ssyck') {
    echo json_encode([
        'success' => false, 
        'message' => 'Критический отказ: сессия пуста или доступ к изменению ролей разрешен только Суперадмину.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$currentData = file_exists($registryPath) ? json_decode(file_get_contents($registryPath), true) : [];

// ОБРАБОТКА GET-ЗАПРОСА
if ($method === 'GET') {
    echo json_encode(['success' => true, 'users' => $currentData], JSON_UNESCAPED_UNICODE);
    exit;
}

// ОБРАБОТКА POST-ЗАПРОСА: Изменение прав сотрудников «на лету»
if ($method === 'POST') {
    // Читаем сырой JSON-поток, прилетевший из Chrome через fetch
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Валидация входных данных
    if (!isset($input['username']) || empty(trim($input['username']))) {
        echo json_encode(['success' => false, 'message' => 'Не указано имя сотрудника.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $targetUser = trim($input['username']);
    
    // ЗАЩИТА ОТ САМОЛИКВИДАЦИИ: Запрещаем изменять главного суперадмина ssyck
    if ($targetUser === 'ssyck') {
        echo json_encode(['success' => false, 'message' => 'Редактирование прав главного Суперадмина заблокировано на уровне ядра бэкенда.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Формируем обновленную структуру для записи (Zero-Trust по умолчанию, если массивы пусты)
    $currentData[$targetUser] = [
        'role' => htmlspecialchars(trim($input['role'] ?? 'Сотрудник')),
        'is_active' => (bool)($input['is_active'] ?? false),
        'allowed_menus' => $input['allowed_menus'] ?? [] // Если галочки не выбраны — доступ ко всем меню закрыт
    ];

    // Запись обновленной матрицы ролей на диск
    if (file_put_contents($registryPath, json_encode($currentData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false) {
        echo json_encode([
            'success' => true, 
            'message' => "Права доступа для сотрудника [{$targetUser}] успешно обновлены «на лету»!"
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'message' => 'Критическая ошибка бэкенда: не удалось перезаписать реестр ролей на диск.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// Если метод запроса не GET и не POST
echo json_encode(['success' => false, 'message' => 'Неподдерживаемый метод запроса.'], JSON_UNESCAPED_UNICODE);
exit;