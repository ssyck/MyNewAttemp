<?php
// app/controllers/ad/RolesController.php
declare(strict_types=1);

/**
 * Изолированный модуль управления ролями и правами сотрудников
 */
function run_roles_controller(string $op, string $current_username): void 
{
    $db_path = SYSTEM_DATA . 'user_role.json';
    $roles_db = file_exists($db_path) ? (json_decode(file_get_contents($db_path), true) ?? []) : [];

    // --- ОБРАБОТКА POST: СОХРАНЕНИЕ МАТРИЦЫ ПРАВ ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $op === 'save_roles') {
        $target_user = $_POST['target_username'] ?? '';
        $selected_menus = $_POST['menus'] ?? [];

        if (!empty($target_user) && isset($roles_db['staff_registry'][$target_user])) {
            // Защита Директора: нельзя забрать у себя права на контур ad
            if ($target_user === $current_username && !in_array('ad', $selected_menus, true)) {
                $selected_menus[] = 'ad';
            }
            $roles_db['staff_registry'][$target_user]['allowed_menus'] = $selected_menus;
            file_put_contents($db_path, json_encode($roles_db, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        header('Location: ?mod=ad&op=roles&success=1');
        exit();
    }

    // --- ОБРАБОТКА GET: ОТОБРАЖЕНИЕ ФОРМЫ ---
    $view_path = VIEWS_PATH . 'ad/admin_roles_form.php';
    if (file_exists($view_path)) {
        require_once $view_path;
    } else {
        echo "<div class='alert alert-danger small py-2'>Ошибка: Форма редактора ролей не найдена во views.</div>";
    }
}
