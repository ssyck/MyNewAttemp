<?php
// app/controllers/AdminController.php
declare(strict_types=1);

function run_admin_controller(string $operation): void
{
    $rolesJsonPath = SYSTEM_DATA . 'roles.json';

    // 1. Читаем текущую базу данных
    $jsonContent = file_exists($rolesJsonPath) ? file_get_contents($rolesJsonPath) : '{}';
    $database = json_decode($jsonContent, true) ?? ['menu_manifest' => [], 'staff_registry' => []];

    // 2. ОБРАБОТЧИК СОХРАНЕНИЯ С АВТО-РЕНДЕРОМ (Паттерн PRG)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $operation === 'roles') {
        $postedRegistry = $_POST['staff'] ?? [];
        
        $newStaffRegistry = [];
        foreach ($postedRegistry as $username => $data) {
            $username = trim($username);
            if ($username === '') continue;

            $newStaffRegistry[$username] = [
                'role'          => trim($data['role'] ?? 'Сотрудник'),
                'is_active'     => isset($data['is_active']),
                'allowed_menus' => array_keys($data['menus'] ?? [])
            ];
        }

        $database['staff_registry'] = $newStaffRegistry;
        file_put_contents($rolesJsonPath, json_encode($database, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Кладем сообщение об успехе в сессию — оно переживет перезагрузку
        $_SESSION['flash_message'] = '<div class="alert alert-success py-2 shadow-sm">💾 Изменения успешно сохранены! Страница обновлена.</div>';

        // АВТОМАТИЧЕСКИЙ ПЕРЕРЕНДЕР: отправляем браузер на чистый GET-запрос этой же страницы
        header('Location: ?mod=ad&op=roles&t=' . time());
        exit(); // Намертво обрываем выполнение POST-потока
    }

    // Перехватываем сообщение из сессии для вывода на экран
    $saveMessage = $_SESSION['flash_message'] ?? '';
    unset($_SESSION['flash_message']); // Сразу стираем его, чтобы оно не висело вечно

    // 3. РЕНДЕРИНГ ТАБЛИЧНОГО ИНТЕРФЕЙСА ДЛЯ ДИРЕКТОРА
    if ($operation === 'roles') {
        echo '<h5 class="text-dark font-weight-bold mb-1">🛡️ Управление сотрудниками и правами доступа</h5>';
        echo '<p class="text-muted small mb-4">Табличный редактор прав. Отмечайте галочками разделы системы, к которым разрешен доступ.</p>';
        echo $saveMessage; // Выводим наше сессионное сообщение
        ?>

        <form method="POST" action="?mod=ad&op=roles">
            <!-- Таблица вывода сотрудников остается точно такой же, как вчера -->
            <div class="table-responsive shadow-sm rounded mb-3">
                <table class="table table-bordered table-hover bg-white mb-0 align-middle" style="font-size: 14px;">
                    <thead class="table-dark text-uppercase small" style="font-size: 12px; letter-spacing: 0.5px;">
                        <tr>
                            <th style="width: 15%;">Логин (Apache)</th>
                            <th style="width: 20%;">Должность / Роль</th>
                            <th style="width: 15%;" class="text-center">Статус аккаунта</th>
                            <th style="width: 50%;">Разрешенные разделы системы (Доступы)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($database['staff_registry'] as $username => $info): 
                            $isActive = (bool)($info['is_active'] ?? false);
                            $userMenus = $info['allowed_menus'] ?? [];
                        ?>
                            <tr>
                                <td class="font-weight-bold text-secondary">
                                    <?php echo htmlspecialchars($username); ?>
                                    <input type="hidden" name="staff[<?php echo $username; ?>][present]" value="1">
                                </td>
                                <td>
                                    <input type="text" name="staff[<?php echo $username; ?>][role]" 
                                           class="form-control form-control-sm" 
                                           value="<?php echo htmlspecialchars($info['role'] ?? ''); ?>" required>
                                </td>
                                <td class="text-center">
                                    <div class="form-check form-switch d-inline-block">
                                        <input class="form-check-input" type="checkbox" 
                                               name="staff[<?php echo $username; ?>][is_active]" 
                                               <?php echo $isActive ? 'checked' : ''; ?>>
                                        <label class="form-check-label small text-muted"><?php echo $isActive ? 'Активен' : 'Блок'; ?></label>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-3">
                                        <?php foreach (SYSTEM_MENUS as $menuKey => $menuData): 
                                            $hasAccess = in_array($menuKey, $userMenus, true);
                                        ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="staff[<?php echo $username; ?>][menus][<?php echo $menuKey; ?>]" 
                                                       id="chk_<?php echo $username . '_' . $menuKey; ?>"
                                                       <?php echo $hasAccess ? 'checked' : ''; ?>>
                                                <label class="form-check-label text-dark small" for="chk_<?php echo $username . '_' . $menuKey; ?>">
                                                    <?php echo htmlspecialchars($menuData['title']); ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="d-flex justify-content-between align-items-center">
                <button type="submit" class="btn btn-success btn-sm shadow-sm px-4 font-weight-bold">💾 Сохранить изменения</button>
                <span class="text-muted small">Данные будут атомарно записаны в реестр.</span>
            </div>
        </form>
        <?php
    } else {
        echo '<h5 class="text-secondary">Модуль admin</h5><hr>';
    }
}