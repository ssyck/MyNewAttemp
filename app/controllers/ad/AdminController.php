<?php
// app/controllers/ad/AdminController.php
declare(strict_types=1);

/**
 * Главный роутер контура "Администрирование" (Parent: ad)
 */
function run_admin_controller(string $op, string $currentUsername): void 
{
    // Если пользователь только зашел в модуль ad, по умолчанию открываем роли
    if (empty($op)) {
        $op = 'roles';
    }

    // 1. Маршрут для Редактора ролей
    if ($op === 'roles' || $op === 'save_roles') {
        require_once __DIR__ . '/RolesController.php';
        run_roles_controller($op, $currentUsername);
        return;
    }

    // 2. Маршрут для Менеджера плагинов (Ключ pm зафиксирован!)
    if ($op === 'pm' || $op === 'plugin_upload') {
        require_once __DIR__ . '/PluginsController.php';
        run_plugins_controller($op, $currentUsername);
        return;
    }

    echo "<h6 class='text-muted italic small p-3'>Операция [{$op}] не найдена в контуре администрирования.</h6>";
}
