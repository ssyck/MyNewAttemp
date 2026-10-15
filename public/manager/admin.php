<?php
// public/manager/admin.php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/app/config/config.php';
require_once PRIVATE_PATH . 'core/auth.php';

$userData = authenticate_user();
$allowedMenuKeys = $userData['allowed_menus'];

$currentMod = $_GET['mod'] ?? '';
$currentOp  = $_GET['op'] ?? '';
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
            
            <!-- Навигация -->
                        <!-- Левое динамическое меню с аккордеоном (Collapse) -->
            <aside class="col-md-3 col-lg-2 mb-4">
                <div class="accordion shadow-sm" id="menuAccordion">
                    <?php 
                    // Получаем текущие выбранные модули для логики раскрытия блоков
                    $currentMod = $_GET['mod'] ?? '';
                    $currentOp  = $_GET['op'] ?? '';

                    // Перебираем наш официальный вложенный манифест меню
                    foreach (SYSTEM_MENUS as $modKey => $menuInfo) {
                        // Строгий фильтр безопасности: если ключа нет в правах — полностью стираем блок
                        if (!in_array($modKey, $allowedMenuKeys, true)) {
                            continue;
                        }

                        // Вычисляем, должен ли этот блок быть раскрыт прямо сейчас
                        $isCurrentModule = ($currentMod === $modKey);
                        $collapseStateClass = $isCurrentModule ? 'show' : '';
                        $buttonCollapsedClass = $isCurrentModule ? '' : 'collapsed';
                        ?>
                        
                        <div class="accordion-item border-bottom-0">
                            <!-- Заголовок родительского раздела (Кнопка-триггер) -->
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
                            
                            <!-- Контейнер подменю (Коллапс) -->
                            <div id="collapse_<?php echo $modKey; ?>" 
                                 class="accordion-collapse collapse <?php echo $collapseStateClass; ?>" 
                                 aria-labelledby="heading_<?php echo $modKey; ?>" 
                                 data-bs-parent="#menuAccordion">
                                <div class="accordion-body p-0">
                                    <div class="list-group list-group-flush">
                                        <?php 
                                        if (!empty($menuInfo['submenu'])) {
                                            foreach ($menuInfo['submenu'] as $opKey => $subTitle) {
                                                // Подсвечиваем активную операцию
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
                    // Роутинг: если выбран модуль "ad", у нас есть на него права и файл контроллера существует
                    if ($currentMod === 'ad' && in_array('ad', $allowedMenuKeys, true)) {
                        require_once CONTROLLERS_PATH . 'AdminController.php';
                        // Запускаем исполняемый код контроллера
                        run_admin_controller($currentOp);
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
