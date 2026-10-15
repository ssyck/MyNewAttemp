<?php
// auth_core.php - Ядро распределения ролей и контроля доступа
ini_set('display_errors', 0); // Отключаем вывод HTML-ошибок
error_reporting(E_ALL);

$registryPath = "C:/xampp/htdocs/MyNewAttemp/data/server/system/roles_registry.json";

// 1. Перехват оператора Apache [source: 0.1.1, 1]
$remoteUser = $_SERVER['REMOTE_USER'] ?? $_SERVER['REDIRECT_REMOTE_USER'] ?? null;

if (!$remoteUser) {
    die("<div style='color:red; font-family:sans-serif; text-align:center; margin-top:50px; padding:20px; border:1px solid red; display:inline-block;'>
            <h3>Критическая ошибка безопасности</h3>
            <p>Вы не авторизованы на сервере Apache.</p>
         </div>");
}

// 2. Чтение или инициализация реестра прав (Zero-Trust по умолчанию)
$currentData = file_exists($registryPath) ? json_decode(file_get_contents($registryPath), true) : [];

// Если пользователя нет в базе — регистрируем его "на лету" с НУЛЕВЫМИ правами
if (!isset($currentData[$remoteUser])) {
    $currentData[$remoteUser] = [
        'role' => 'Новый сотрудник (Ожидает проверки)',
        'is_active' => false,
        'allowed_menus' => [] // Полноценный блок по умолчанию закрыт
    ];
    file_put_contents($registryPath, json_encode($currentData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$userData = $currentData[$remoteUser];

// Проверка на блокировку аккаунта Суперадмином
if (!$userData['is_active'] && $remoteUser !== 'ssyck') {
    die("<div style='color:orange; font-family:sans-serif; text-align:center; margin-top:50px;'>
            <h3>Доступ заблокирован</h3>
            <p>Ваша учетная запись [<strong>" . htmlspecialchars($remoteUser) . "</strong>] зарегистрирована «на лету», но еще не активирована администратором.</p>
         </div>");
}

$allowedMenus = $userData['allowed_menus'] ?? [];
$userRole = $userData['role'] ?? 'Сотрудник';

// Жесткие сквозные права для главного суперадмина
if ($remoteUser === 'ssyck') {
    $allowedMenus = ['admin_panel', 'import_pipeline', 'tech_passport', 'accounting', 'sys_logs'];
    $userRole = 'Главный Суперадмин';
}

// Функция контроля прав для использования в HTML
function hasAccess($menuId) {
    global $allowedMenus;
    return in_array($menuId, $allowedMenus);
}