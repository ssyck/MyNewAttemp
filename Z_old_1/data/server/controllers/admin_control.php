<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Чтение кроссплатформенного config.ini (на 3 уровня выше из папки controllers)
$configPath = dirname(__DIR__, 3) . '/config.ini'; 

if (!file_exists($configPath)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Системный сбой: config.ini не найден в корне.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$config = parse_ini_file($configPath, true);

// 2. Управление отображением ошибок на основе IS_PRODUCTION
$isProduction = filter_var($config['environment']['IS_PRODUCTION'] ?? true, FILTER_VALIDATE_BOOLEAN);
if ($isProduction) {
    ini_set('display_errors', 0);
    error_reporting(0);
} else {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

header('Content-Type: application/json; charset=utf-8');

// 3. Сборка абсолютного пути строго от единого ROOT_PATH
$rootPath = rtrim($config['paths']['ROOT_PATH'], '/\\');
$registryPath = $rootPath . '/data/server/system/roles_registry.json';

// Проверяем физическое наличие файла базы данных
if (!file_exists($registryPath)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Критическая ошибка бэкенда: файл реестра ролей не найден по пути: ' . $registryPath
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Читаем сырые данные с диска
$rawJson = file_get_contents($registryPath);
$currentData = json_decode($rawJson, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'success' => false, 
        'message' => 'Ошибка синтаксиса JSON в реестре ролей: ' . json_last_error_msg()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. Безопасность на основе ролевой модели (RBAC) без хардкода имени
$remoteUser = $_SESSION['backend_auth_user'] ?? $_SERVER['REMOTE_USER'] ?? $_SERVER['REDIRECT_REMOTE_USER'] ?? null;

if (!$remoteUser) {
    echo json_encode(['success' => false, 'message' => 'Критический отказ: пользователь не авторизован сервером.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Проверяем существование текущего оператора в реестре и его активность
if (!isset($currentData[$remoteUser]) || !$currentData[$remoteUser]['is_active']) {
    echo json_encode(['success' => false, 'message' => 'Критический отказ: ваша учетная запись заблокирована в матрице доступа.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userData = $currentData[$remoteUser];

// Доступ имеет либо Главный Суперадмин, либо тот, у кого явно включен пункт admin_panel
$hasAdminAccess = ($userData['role'] === 'Главный Суперадмин') || 
                  (isset($userData['allowed_menus']) && in_array('admin_panel', $userData['allowed_menus']));

if (!$hasAdminAccess) {
    echo json_encode(['success' => false, 'message' => 'Недостаточно прав для управления матрицей ролей сотрудников.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// ===================================================================
// ОБРАБОТКА GET-ЗАПРОСА: Отдача списка сотрудников на фронтенд
// ===================================================================
if ($method === 'GET') {
    echo json_encode(['success' => true, 'users' => $currentData], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===================================================================
// ОБРАБОТКА POST-ЗАПРОСА: Изменение прав сотрудников «на лету»
// ===================================================================
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['username']) || empty(trim($input['username']))) {
        echo json_encode(['success' => false, 'message' => 'Не указано имя сотрудника.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $targetUser = trim($input['username']);
    
    // Защита от самоликвидации суперадмина
    if (isset($currentData[$targetUser]) && $currentData[$targetUser]['role'] === 'Главный Суперадмин') {
        echo json_encode(['success' => false, 'message' => 'Редактирование или блокировка прав Главного Суперадмина запрещены на уровне ядра.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Обновляем структуру данных сотрудника
    $currentData[$targetUser] = [
        'role' => htmlspecialchars(trim($input['role'] ?? 'Сотрудник')),
        'is_active' => (bool)($input['is_active'] ?? false),
        'allowed_menus' => $input['allowed_menus'] ?? []
    ];

    // Запись обновленной матрицы ролей на диск
    if (file_put_contents($registryPath, json_encode($currentData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false) {
        echo json_encode([
            'success' => true, 
            'message' => "Матрица доступа для сотрудника [{$targetUser}] успешно обновлена «на лету»!"
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'message' => 'Критическая ошибка бэкенда: не удалось перезаписать файл реестра на диск.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Неподдерживаемый метод запроса.'], JSON_UNESCAPED_UNICODE);
exit;