<?php
// admin.php (Самый верх файла, Блок 1)
session_start(); // Включаем механизм сессий PHP

ini_set('display_errors', 0);
error_reporting(E_ALL);

// ... (здесь идет ваш код чтения конфига config.ini без изменений) ...

// Перехват оператора из Apache
$remoteUser = $_SERVER['REMOTE_USER'] ?? $_SERVER['REDIRECT_REMOTE_USER'] ?? null;

if (!$remoteUser) {
    die("Критическая ошибка безопасности...");
}

// Записываем имя авторизованного суперадмина в сессию, чтобы подпапки его не теряли!
$_SESSION['backend_auth_user'] = $remoteUser;

// ... (весь остальной PHP и HTML код файла admin.php остается без изменений) ...
?>

<?php
// --- БЛОК СЕРВЕРНОЙ БЕЗОПАСНОСТИ И РЕГИСТРАЦИИ «НА ЛЕТУ» ---
ini_set('display_errors', 0); // Отключаем вывод ошибок, чтобы не ломать верстку
error_reporting(E_ALL);

// 1. Чтение конфигурации из корня проекта
$configPath = __DIR__ . '/config.ini';
if (!file_exists($configPath)) {
    die("<div style='color:red; font-family:sans-serif; text-align:center; margin-top:50px; padding:20px; border:1px solid red; display:inline-block;'>
            <h3>Системная ошибка конфигурации</h3>
            <p>Отсутствует файл <strong>config.ini</strong> в корне.</p>
         </div>");
}
$config = parse_ini_file($configPath, true);

// Динамический корень проекта
$rootPath = !empty($config['paths']['ROOT_PATH']) ? $config['paths']['ROOT_PATH'] : __DIR__;
$registryPath = $rootPath . '/data/server/system/roles_registry.json';

// 2. Перехват оператора из Apache [source: 0.1.1, 1]
$remoteUser = $_SERVER['REMOTE_USER'] ?? $_SERVER['REDIRECT_REMOTE_USER'] ?? null;

if (!$remoteUser) {
    die("<div style='color:red; font-family:sans-serif; text-align:center; margin-top:50px; padding:20px; border:1px solid red; display:inline-block;'>
            <h3>Критическая ошибка безопасности</h3>
            <p>Вы не авторизованы на сервере Apache.</p>
         </div>");
}

// 3. Контроль доступа и авто-регистрация в реестре
$currentData = file_exists($registryPath) ? json_decode(file_get_contents($registryPath), true) : [];

if (!isset($currentData[$remoteUser])) {
    $currentData[$remoteUser] = [
        'role' => 'Новый сотрудник (Ожидает проверки)',
        'is_active' => false,
        'allowed_menus' => [] // По умолчанию закрыто всё
    ];
    file_put_contents($registryPath, json_encode($currentData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$userData = $currentData[$remoteUser];

// Проверка на ручную блокировку аккаунта (Zero-Trust)
if (!$userData['is_active']) {
    die("<div style='color:orange; font-family:sans-serif; text-align:center; margin-top:50px; padding:20px; border:1px solid orange; display:inline-block;'>
            <h3>Доступ временно ограничен</h3>
            <p>Ваша учетная запись [<strong>" . htmlspecialchars($remoteUser) . "</strong>] зарегистрирована «на лету».</p>
            <p>Обратитесь к администратору для активации аккаунта и назначения прав доступа.</p>
         </div>");
}

// Загружаем права из базы (для ssyck это будет полный массив, для остальных — то, что выберет админ)
$allowedMenus = $userData['allowed_menus'] ?? [];
$userRole = $userData['role'] ?? 'Сотрудник';

function hasAccess($menuId) {
    global $allowedMenus;
    return in_array($menuId, $allowedMenus);
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Инженерная Панель | Управление</title>
    <!-- Подключаем стили и иконки локально из вашей папки assets -->
    <link href="assets/css/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/icons/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; overflow-x: hidden; }
        .sidebar { min-height: 100vh; background-color: #212529; box-shadow: inset -1px 0 0 rgba(0,0,0,.1); }
        .sidebar .nav-link { color: #dee2e6; font-weight: 500; padding: 0.8rem 1rem; border-radius: 0.375rem; margin: 0.2rem 0.5rem; text-decoration: none; display: block; }
        .sidebar .nav-link:hover { color: #fff; background-color: rgba(255, 255, 255, .1); }
        .sidebar .nav-link.active { color: #fff; background-color: #0d6efd; }
        .sidebar .nav-link i { margin-right: 10px; font-size: 1.1rem; }
        .main-content { padding: 2rem; }
        .dashboard-card { border: none; border-radius: 0.5rem; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); }
        .hidden-section { display: none !important; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        
        <!-- БОКОВОЕ МЕНЮ (SIDEBAR) -->
        <nav class="col-md-3 col-lg-2 d-md-block sidebar p-0">
            <div class="position-sticky pt-3">
                <div class="px-3 mb-4 text-white">
                    <h5 class="fw-bold mb-0">MyNewAttemp</h5>
                    <small class="text-success fw-semibold"><?= htmlspecialchars($userRole) ?>: <?= htmlspecialchars($remoteUser) ?></small>
                </div>
                <hr class="text-muted mx-3">
                <ul class="nav flex-column" id="main-sidebar-menu">
                    
                    <?php if (hasAccess('admin_panel')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="switchSection('admin_panel', this)">
                            <i class="bi bi-shield-lock-fill"></i>Администрирование
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (hasAccess('import_pipeline')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="switchSection('import_pipeline', this)">
                            <i class="bi bi-box-seam-fill"></i>Конвейер импорта
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (hasAccess('tech_passport')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="switchSection('tech_passport', this)">
                            <i class="bi bi-wrench-adjustable-circle"></i>Паспорта деталей
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (hasAccess('accounting')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="switchSection('accounting', this)">
                            <i class="bi bi-cash-coin"></i>Бухгалтерия и Цены
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (hasAccess('sys_logs')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="switchSection('sys_logs', this)">
                            <i class="bi bi-terminal-fill"></i>Системный лог
                        </a>
                    </li>
                    <?php endif; ?>

                </ul>
            </div>
        </nav>

        <!-- ОСНОВНАЯ ЗОНА КОНТЕНТА -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            
            <!-- ВЕРХНЯЯ ИНФОРМАЦИОННАЯ ПАНЕЛЬ -->
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                <h1 class="h2" id="current-section-title">Инженерная Панель</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <span class="badge bg-success p-2">
                        <i class="bi bi-person-fill-check me-1"></i>Apache Authorized (<?= htmlspecialchars($remoteUser) ?>)
                    </span>
                </div>
            </div>

        <!-- СТАРТОВЫЙ БЛОК ПРИВЕТСТВИЯ (Виден только при входе) -->
        <section id="section-welcome_screen" class="dashboard-block">
            <div class="card dashboard-card p-5 text-center bg-white">
                <div class="mb-3 text-primary" style="font-size: 3rem;">
                    <i class="bi bi-speedometer2"></i>
                </div>
                <h3 class="fw-bold text-dark">Система управления складом инициализирована</h3>
                <p class="text-muted col-md-6 mx-auto">Для начала работы выберите необходимый инструмент на левой панели меню. Доступные модули сформированы на бэкенде «на лету» согласно вашей роли.</p>
            </div>
        </section>            

            <!-- РАЗДЕЛ 1: АДМИНИСТРИРОВАНИЕ -->
            <?php if (hasAccess('admin_panel')): ?>
            <section id="section-admin_panel" class="hidden-section">
                <div class="card dashboard-card p-4">
                    <h4 class="mb-4 text-dark fw-bold">Управление доступом сотрудников («На лету»)</h4>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th>Сотрудник</th>
                                    <th>Должность / Роль</th>
                                    <th>Статус</th>
                                    <th>Разрешенные разделы системы</th>
                                    <th>Действие</th>
                                </tr>
                            </thead>
                            <tbody id="rbac-table-body">
                                <!-- JS динамически загрузит строки сотрудников -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- РАЗДЕЛ 2: КОНВЕЙЕР ИМПОРТА -->
            <?php if (hasAccess('import_pipeline')): ?>
            <section id="section-import_pipeline" class="hidden-section">
                <div class="card dashboard-card p-4">
                    <h4 class="mb-3 text-dark fw-bold">Транзакционный импорт (.zip)</h4>
                    <p class="text-muted small">Выберите пакет товара, содержащий внутреннюю папку со складскими изображениями .webp и конфигурационным JSON-паспортом детали.</p>
                    <div class="mb-3 col-md-6">
                        <label class="form-label fw-semibold">Пакет товара</label>
                        <input class="form-control" type="file" id="zip_package">
                    </div>
                    <button class="btn btn-primary col-md-3">Запустить конвейер импорта</button>
                </div>
            </section>
            <?php endif; ?>

            <!-- РАЗДЕЛ 3: ПАСПОРТА ДЕТАЛЕЙ -->
            <?php if (hasAccess('tech_passport')): ?>
            <section id="section-tech_passport" class="hidden-section">
                <div class="card dashboard-card p-4">
                    <h4 class="fw-bold">Технические паспорта деталей</h4>
                    <p class="text-muted">Модуль находится в режиме проектирования (Инженерная зона моделей техники).</p>
                </div>
            </section>
            <?php endif; ?>

            <!-- РАЗДЕЛ 4: БУХГАЛТЕРИЯ -->
            <?php if (hasAccess('accounting')): ?>
            <section id="section-accounting" class="hidden-section">
                <div class="card dashboard-card p-4">
                    <h4 class="fw-bold">Финансовый контроль и Валидация цен</h4>
                    <p class="text-muted">Модуль находится в режиме проектирования (Зона Бухгалтерии).</p>
                </div>
            </section>
            <?php endif; ?>

            <!-- РАЗДЕЛ 5: СИСТЕМНЫЙ ЛОГ -->
            <?php if (hasAccess('sys_logs')): ?>
            <section id="section-sys_logs" class="hidden-section">
                <div class="card dashboard-card p-4 bg-dark text-success">
                    <h4 class="text-white fw-bold mb-3">Мониторинг системного лога</h4>
                    <div style="font-family: monospace; max-height: 400px; overflow-y: auto;" id="log-output-zone">
                        [ОЖИДАНИЕ] Запустите импорт пакета товара для мониторинга шагов...
                    </div>
                </div>
            </section>
            <?php endif; ?>

        </main>
    </div>
</div>

<!-- ПОДКЛЮЧАЕМ СКРИПТЫ ЛОКАЛЬНО ИЗ ВАШЕЙ ПАПКИ ASSETS -->
<script src="assets/js/bootstrap/bootstrap.bundle.min.js"></script>
<script>
    // Функция визуального переключения секций
    function switchSection(sectionId, element) {
        // Скрываем абсолютно все секции, включая стартовый экран приветствия
        document.querySelectorAll('main section').forEach(sec => sec.classList.add('hidden-section'));
        
        // Снимаем подсветку со всех кнопок бокового меню
        document.querySelectorAll('#main-sidebar-menu .nav-link').forEach(link => link.classList.remove('active'));
        
        // Отображаем выбранный блок
        const targetSection = document.getElementById(`section-${sectionId}`);
        if (targetSection) {
            targetSection.classList.remove('hidden-section');
        }
        
        // Подсвечиваем активный пункт в сайдбаре
        if (element) {
            element.classList.add('active');
            document.getElementById('current-section-title').innerText = element.innerText.trim();
        }
    }

    // При первой загрузке страницы просто оставляем экран приветствия активным
    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById('current-section-title').innerText = 'Главный дашборд';
    });
</script>
</body>
</html>
