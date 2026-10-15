<?php
// public/manager/admin.php
declare(strict_types=1);

// Подключаем защищенное ядро (когда бэкенд будет полностью активен)
// require_once __DIR__ . '/../../app/core/auth.php';
// $auth = new AuthManager();

// Заглушка функции для этапа верстки, чтобы скрипт не падал без auth.php
if (!function_exists('hasAccess')) {
    function hasAccess(string $permission): bool {
        return true; // Пока показываем всё для верстки дашборда
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Инженерная Панель | MyNewAttemp</title>
    
    <!-- СТРОГО ЛОКАЛЬНЫЕ СТИЛИ (Без внешних CDN) -->
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/sidebars.css" rel="stylesheet">
    
    <style>
        /* Фиксируем высоту панели под размер экрана */
        .sidebar-wrapper {
            min-height: 100vh;
            box-shadow: 4px 0 10px rgba(0,0,0,0.05);
        }
        /* Красивый эффект при наведении на пункты меню */
        .btn-toggle-nav a:hover {
            background-color: #f8f9fa;
            color: #0d6efd !important;
        }
    </style>
</head>
<body class="bg-light">

<!-- ГЛАВНЫЙ FLEXBOX КОНТЕЙНЕР (Связывает лево и право) -->
<div class="d-flex">

    <!-- ЛЕВАЯ ЧАСТЬ: БОКОВАЯ ПАНЕЛЬ ( sidebar ) -->
    <div class="flex-shrink-0 p-3 bg-white border-end sidebar-wrapper" style="width: 280px;">
        <a href="#" class="d-flex align-items-center pb-3 mb-3 link-dark text-decoration-none border-bottom">
            <!-- Заменили пустой тег на красивую локальную шестеренку инженеров -->
            <i class="bi bi-gear-fill text-primary fs-4 me-2"></i>
            <span class="fs-5 fw-bold text-dark">Control Panel</span>
        </a>
        
        <ul class="list-unstyled ps-0">
            
            <!-- РАЗДЕЛ 1: АДМИНИСТРИРОВАНИЕ -->
            <?php if (hasAccess('admin_panel')): ?>
            <li class="mb-1">
                <button class="btn btn-toggle d-flex align-items-center rounded collapsed w-100 text-start py-2 fw-medium" data-bs-toggle="collapse" data-bs-target="#section1-collapse" aria-expanded="true">
                    <i class="bi bi-shield-lock me-2 text-secondary"></i> Администратор
                </button>
                <div class="collapse show" id="section1-collapse">
                    <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small ps-4">
                        <li><a href="#" class="link-dark rounded d-block py-1 px-2 text-decoration-none">Сотрудники и Роли</a></li>
                        <li><a href="#" class="link-dark rounded d-block py-1 px-2 text-decoration-none">Системные Логи</a></li>
                    </ul>
                </div>
            </li>
            <?php endif; ?>

            <!-- РАЗДЕЛ 2: КОНВЕЙЕР ИМПОРТА -->
            <?php if (hasAccess('import_pipeline')): ?>
            <li class="mb-1">
                <button class="btn btn-toggle d-flex align-items-center rounded collapsed w-100 text-start py-2 fw-medium" data-bs-toggle="collapse" data-bs-target="#section2-collapse" aria-expanded="false">
                    <i class="bi bi-box-seam me-2 text-secondary"></i> Импорт и Спецификации
                </button>
                <div class="collapse" id="section2-collapse">
                    <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small ps-4">
                        <li><a href="#" class="link-dark rounded d-block py-1 px-2 text-decoration-none">Загрузка ZIP пакетов</a></li>
                        <li><a href="#" class="link-dark rounded d-block py-1 px-2 text-decoration-none">Парсер CSV схем</a></li>
                        <li><a href="#" class="link-dark rounded d-block py-1 px-2 text-decoration-none">Матрица «Ключ-Замок»</a></li>
                    </ul>
                </div>
            </li>
            <?php endif; ?>

            <!-- РАЗДЕЛ 3: КАТАЛОГ СКЛАДА -->
            <?php if (hasAccess('catalog_view')): ?>
            <li class="mb-1">
                <button class="btn btn-toggle d-flex align-items-center rounded collapsed w-100 text-start py-2 fw-medium" data-bs-toggle="collapse" data-bs-target="#section3-collapse" aria-expanded="false">
                    <i class="bi bi-wrench-adjustable me-2 text-secondary"></i> Каталог деталей
                </button>
                <div class="collapse" id="section3-collapse">
                    <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small ps-4">
                        <li><a href="#" class="link-dark rounded d-block py-1 px-2 text-decoration-none">Запчасти и Аксессуары</a></li>
                        <li><a href="#" class="link-dark rounded d-block py-1 px-2 text-decoration-none">Модели техники</a></li>
                    </ul>
                </div>
            </li>
            <?php endif; ?>

            <li class="border-top my-3"></li>
            
            <!-- УЧЕТНАЯ ЗАПИСЬ (Remote_User Apache) -->
            <li class="mb-1">
                <button class="btn btn-toggle d-flex align-items-center rounded collapsed w-100 text-start py-2 fw-medium" data-bs-toggle="collapse" data-bs-target="#account-collapse" aria-expanded="false">
                    <i class="bi bi-person-circle me-2 text-primary"></i> Профиль
                </button>
                <div class="collapse" id="account-collapse">
                    <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small ps-4">
                        <li><span class="text-muted px-2 d-block py-1">Вы вошли как: <strong>ssyck</strong></span></li>
                        <li><a href="#" class="link-dark rounded d-block py-1 px-2 text-decoration-none text-danger">Выйти</a></li>
                    </ul>
                </div>
            </li>
        </ul>
    </div>

    <!-- ПРАВАЯ ЧАСТЬ: РАБОЧАЯ ОБЛАСТЬ ДАШБОРДА (Сюда будут рендериться таблицы) -->
    <main class="flex-grow-1 p-4 bg-light" style="overflow-y: auto; height: 100vh;">
        
        <!-- Шапка дашборда -->
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-2 mb-3 border-bottom">
            <h1 class="h2 text-dark fw-bold">Панель управления складом</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <span class="badge bg-success p-2 d-flex align-items-center">
                    <i class="bi bi-cpu-fill me-1"></i> Контур безопасности: OK
                </span>
            </div>
        </div>

        <!-- Контентная карточка (Сюда мы выведем, например, будущую таблицу ножей/пылесосов) -->
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body p-4">
                <h5 class="card-title text-secondary mb-3">Добро пожаловать в MyNewAttemp Engine</h5>
                <p class="card-text text-muted">Выберите интересующий вас раздел в левом меню для начала инженерной работы со спецификациями и матрицами совместимости бытовой техники.</p>
            </div>
        </div>

    </main>

</div>

<!-- СТРОГО ЛОКАЛЬНЫЙ JS -->
<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>