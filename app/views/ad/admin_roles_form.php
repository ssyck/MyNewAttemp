<!-- app/views/ad/admin_roles_form.php -->
<?php
if (!isset($roles_db)) { 
    die('Доступ запрещен.'); 
}
?>

<h5 class="text-dark font-weight-bold">Управление персонажами и ролями доступов</h5>
<hr>

<?php if (!empty($_GET['success'])): ?>
    <div class="alert alert-success py-2 small shadow-sm border-0">Успех: Права сотрудника успешно обновлены в файле user_role.json!</div>
<?php endif; ?>

<div class="row">
    <!-- Левая колонка: Список сотрудников -->
    <div class="col-md-4 border-end">
        <div class="list-group list-group-flush shadow-sm">
            <?php if (!empty($roles_db['staff_registry']) && is_array($roles_db['staff_registry'])): ?>
                <?php foreach ($roles_db['staff_registry'] as $username => $info): ?>
                    <a href="?mod=ad&op=roles&user=<?php echo urlencode($username); ?>" class="list-group-item list-group-item-action py-2">
                        <div class="font-weight-bold small text-dark"><?php echo htmlspecialchars($username); ?></div>
                        <small class="text-muted" style="font-size: 11px;"><?php echo htmlspecialchars($info['role'] ?? 'Сотрудник'); ?></small>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="list-group-item small text-muted italic">Реестр сотрудников пуст</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Правая колонка: Матрица прав выбранного сотрудника -->
    <div class="col-md-8 ps-4">
        <?php 
        $selected_user = $_GET['user'] ?? '';
        if (!empty($selected_user) && isset($roles_db['staff_registry'][$selected_user])): 
            $user_rights = $roles_db['staff_registry'][$selected_user]['allowed_menus'] ?? [];
            ?>
            <h6 class="text-secondary mb-3">Настройка доступов для: <strong class="text-dark"><?php echo htmlspecialchars($selected_user); ?></strong></h6>
            
            <form method="POST" action="?mod=ad&op=save_roles">
                <input type="hidden" name="target_username" value="<?php echo htmlspecialchars($selected_user); ?>">
                
                <div class="card bg-light p-3 border-0 shadow-sm mb-3">
                    <?php 
                    if (defined('SYSTEM_MENUS') && is_array(SYSTEM_MENUS)) {
                        foreach (SYSTEM_MENUS as $mod_key => $menu_info) {
                            $title = htmlspecialchars($menu_info['title'] ?? $mod_key);
                            $is_checked = in_array($mod_key, $user_rights, true) ? 'checked' : '';
                            ?>
                            <div class="form-check my-2">
                                <input class="form-check-input" type="checkbox" name="menus[]" value="<?php echo htmlspecialchars($mod_key); ?>" id="chk_<?php echo $mod_key; ?>" <?php echo $is_checked; ?>>
                                <label class="form-check-label small font-weight-bold text-dark text-uppercase" for="chk_<?php echo $mod_key; ?>">
                                    <?php echo $title; ?>
                                </label>
                                <div class="form-text text-muted ms-3" style="font-size: 11px;">
                                    Предоставляет полный доступ к глобальному контуру и всем внутренним операциям модуля.
                                </div>
                            </div>
                            <?php
                        }
                    }
                    ?>
                </div>

                <button type="submit" class="btn btn-dark btn-sm px-4 shadow-sm font-weight-bold">Сохранить матрицу прав</button>
            </form>
        <?php else: ?>
            <div class="p-4 text-center border rounded bg-white shadow-sm mt-2">
                <p class="text-muted italic small mb-0">Выберите персонажа в левой колонке для редактирования его матрицы доступов.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
