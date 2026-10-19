<?php
// public/manager/admin.php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// НАДЖЕСТКИЙ ЗАПРЕТ КЭШИРОВАНИЯ ДЛЯ БРАУЗЕРА:
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache");                                   // HTTP 1.0
header("Expires: 0");                                          // Прокси

require_once dirname(__DIR__, 2) . '/app/config/config.php';
require_once PRIVATE_PATH . 'core/auth.php';

// 1. Извлекаем параметры маршрута сразу
$currentMod = $_GET['mod'] ?? '';
$currentOp  = $_GET['op'] ?? '';

// 2. Авторизуем пользователя
$userData = authenticate_user();
$allowedMenuKeys = $userData['allowed_menus'];

// =========================================================================
// 🔥 ШАГ 1: ПЕРЕХВАТ И ОБРАБОТКА ПОСТ-ЗАПРОСОВ (До вывода какого-либо HTML!)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($currentMod === 'ad' && in_array('ad', $allowedMenuKeys, true)) {
        require_once CONTROLLERS_PATH . 'AdminController.php';
        
        // Передаем два аргумента: операцию и имя текущего юзера (для защиты директора)
        run_admin_controller($currentOp, $userData['username']);
        
        // Внутри контроллера в конце стоит header('Location: ...') и exit();
        // Поэтому, если это был POST, скрипт ТУТ И ЗАВЕРШИТ работу.
    }
}

// =========================================================================
// 🖥️ ШАГ 2: РЕНДЕРИНГ ИНТЕРФЕЙСА (Срабатывает ТОЛЬКО для чистых GET-запросов)
// =========================================================================
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyNewAttemp Engine — Панель управления</title>
    <link rel="stylesheet" href="<?php echo URL_BOOTSTRAP_CSS; ?>">
    <link rel="stylesheet" href="<?php echo URL_MANAGER_CSS; ?>">
</head>
<body class="bg-light">

    <nav class="navbar navbar-dark bg-dark shadow-sm">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">MyNewAttemp Engine</span>
            <div class="navbar-text text-white-50">
                Вы вошли как: <strong><?php echo htmlspecialchars($userData['username']); ?></strong> 
                (<small><?php echo htmlspecialchars($userData['role']); ?></small>)
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            
            <!-- Левое динамическое меню с аккордеоном (Перерисуется на основе свежих данных) -->
            <aside class="col-md-3 col-lg-2 mb-4">
                <div class="accordion shadow-sm" id="menuAccordion">
                    <?php 
                    foreach (SYSTEM_MENUS as $modKey => $menuInfo) {
                        if (!in_array($modKey, $allowedMenuKeys, true)) {
                            continue;
                        }

                        $isCurrentModule = ($currentMod === $modKey);
                        $collapseStateClass = $isCurrentModule ? 'show' : '';
                        $buttonCollapsedClass = $isCurrentModule ? '' : 'collapsed';
                        ?>
                        
                        <div class="accordion-item border-bottom-0">
                            <h2 class="accordion-header" id="heading_<?php echo $modKey; ?>">
                                <button class="accordion-button py-2 px-3 bg-light text-dark font-weight-bold small text-uppercase <?php echo $buttonCollapsedClass; ?>" 
                                        type="button" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#collapse_<?php echo $modKey; ?>" 
                                        aria-expanded="<?php echo $isCurrentModule ? 'true' : 'false'; ?>" 
                                        aria-controls="collapse_<?php echo $modKey; ?>"
                                        style="letter-spacing: 0.5px; font-size: 11px;">
                                    <?php echo htmlspecialchars($menuInfo['title']); ?>
                                </button>
                            </h2>
                            
                            <div id="collapse_<?php echo $modKey; ?>" 
                                 class="accordion-collapse collapse <?php echo $collapseStateClass; ?>" 
                                 aria-labelledby="heading_<?php echo $modKey; ?>" 
                                 data-bs-parent="#menuAccordion">
                                <div class="accordion-body p-0">
                                    <div class="list-group list-group-flush">
                                        <?php 
                                        if (!empty($menuInfo['submenu'])) {
                                            foreach ($menuInfo['submenu'] as $opKey => $subTitle) {
                                                $isActive = ($isCurrentModule && $currentOp === $opKey) ? 'active' : '';
                                                $link = "?mod={$modKey}&op={$opKey}";
                                                
                                                echo '<a href="' . $link . '" class="list-group-item list-group-item-action ps-4 py-2 small ' . $isActive . '" style="font-size: 13px;">';
                                                echo '— ' . htmlspecialchars($subTitle);
                                                echo '</a>';
                                            }
                                        } else {
                                            echo '<div class="list-group-item ps-4 py-2 small text-muted italic">Нет доступных операций</div>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </aside>

            <!-- Рабочая область -->
            <main class="col-md-9 col-lg-10">
                <div class="card shadow-sm p-4">
                    <?php 
                    // Сюда мы попадём ТОЛЬКО при GET-запросе (отображение страницы)
                    if ($currentMod === 'ad' && in_array('ad', $allowedMenuKeys, true)) {
                        require_once CONTROLLERS_PATH . 'AdminController.php';
                        run_admin_controller($currentOp, $userData['username']);
                    } else {
                        echo '<h5 class="text-secondary">Рабочая область модуля</h5><hr>';
                        echo '<p class="text-muted">Выберите доступный раздел в меню слева для начала работы.</p>';
                    }
                    ?>
                </div>
            </main>

        </div>
    </div>

    <script src="<?php echo URL_BOOTSTRAP_JS; ?>"></script>
</body>
</html>