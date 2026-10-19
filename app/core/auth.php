<?php
// app/core/auth.php
declare(strict_types=1);

// Подключаем конфигурацию путей и манифест меню
require_once dirname(__DIR__) . '/config/config.php';

/**
 * Функция аутентификации и строгой проверки прав сотрудника
 * 
 * @return array Возвращает массив данных авторизованного сотрудника
 */
function authenticate_user(): array 
{
    // 1. Перехватываем имя пользователя, прошедшего проверку через .htaccess
    $remoteUser = $_SERVER['REMOTE_USER'] ?? '';
    
    // Предохранитель на случай непредвиденных сбоев конфигурации Apache
    if ($remoteUser === '') {
        header('HTTP/1.1 401 Unauthorized');
        exit('Ошибка безопасности: Пользователь Apache не определен.');
    }

    $rolesJsonPath = SYSTEM_DATA . 'user_role.json';
    
    // Если критически важный файл реестра сотрудников физически отсутствует на диске
    if (!file_exists($rolesJsonPath)) {
        header('HTTP/1.1 500 Internal Server Error');
        exit('Критическая ошибка системы: Реестр сотрудников не найден. Доступ заблокирован.');
    }

    // Считываем текущую базу данных сотрудников
    $jsonContent = file_get_contents($rolesJsonPath);
    $database = json_decode($jsonContent, true);
    
    if (!is_array($database) || !isset($database['staff_registry'])) {
        header('HTTP/1.1 500 Internal Server Error');
        exit('Критическая ошибка системы: Сбой структуры данных реестра.');
    }

    // 2. ПРОВЕРКА ПО БЕЛОМУ СПИСКУ (Анти-Саморегистрация)
    if (!isset($database['staff_registry'][$remoteUser])) {
        header('HTTP/1.1 403 Forbidden');
        exit("Ошибка доступа: Пользователь '{$remoteUser}' не зарегистрирован в системе. Обратитесь к суперадмину.");
    }

    $currentUser = $database['staff_registry'][$remoteUser];

    // 3. Проверка флага активности сотрудника (Блокировка «на лету»)
    if (($currentUser['is_active'] ?? false) !== true) {
        header('HTTP/1.1 403 Forbidden');
        exit("Доступ заблокирован: Учетная запись '{$remoteUser}' деактивирована администратором.");
    }

    // Возвращаем чистый массив данных для динамической сборки интерфейса дашборда
    return [
        'username'      => $remoteUser,
        'role'          => $currentUser['role'] ?? 'Сотрудник',
        'allowed_menus' => $currentUser['allowed_menus'] ?? []
    ];
}
