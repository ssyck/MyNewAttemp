<?php
// app/config/config.php
declare(strict_types=1);

// 1. Вычисление абсолютных путей на диске (без хардкода дисков C:/)
define('PRIVATE_PATH', dirname(__DIR__) . '/');                 // Папка app/
define('ROOT_PATH', dirname(PRIVATE_PATH) . '/');               // Корень MyNewAttemp/
define('PUBLIC_PATH', ROOT_PATH . 'public/');                   // Папка public/

// 2. Чтение глобальных тумблеров из вашего config.ini
$iniPath = ROOT_PATH . 'config.ini';
$configIni = file_exists($iniPath) ? parse_ini_file($iniPath, true) : [];

// Установка системного часового пояса из секции [system] вашего конфига
date_default_timezone_set($configIni['system']['timezone'] ?? 'Europe/Moscow');

// Управление отображением ошибок бэкенда на основе debug_mode
if (intval($configIni['system']['debug_mode'] ?? 1) === 1) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// 🔥 ДОБАВЛЕНО: Единая системная валюта из секции [system] вашего config.ini
define('SYSTEM_CURRENCY', trim($configIni['system']['CURRENCY'] ?? 'руб'));

// 3. Абсолютные пути к папкам данных и контроллеров закрытого контура
define('CONTROLLERS_PATH', PRIVATE_PATH . 'controllers/');
define('DATA_PATH', PRIVATE_PATH . 'data/');
define('PRODUCTS_DATA', DATA_PATH . 'products/');
define('MODELS_DATA', DATA_PATH . 'models/');
define('ORDERS_DATA', DATA_PATH . 'orders/');
define('SYSTEM_DATA', DATA_PATH . 'system/');

// 🔥 ДОБАВЛЕНО: Прямой путь к единому реестру товаров (согласно Манифесту 2.0)
define('REGISTRY_PATH', PRODUCTS_DATA . 'registry.json');

// 4. Веб-пути для браузера (URL-адреса ресурсов)
define('URL_BASE', '/MyNewAttemp/public/');
define('URL_ASSETS', URL_BASE . 'assets/');
define('URL_MANAGER_CSS', URL_ASSETS . 'css/manager.css');

// Пути к Bootstrap 5 строго по вашей новой структуре папок
define('URL_BOOTSTRAP_CSS', URL_ASSETS . 'css/bootstrap5/bootstrap.min.css');
define('URL_BOOTSTRAP_JS', URL_ASSETS . 'js/bootstrap5/bootstrap.bundle.min.js');

// 5. ОФИЦИАЛЬНЫЙ МАНИФЕСТ МЕНЮ (Чистый текст, короткие ключи, без иконок)
define('SYSTEM_MENUS', [
    'ad' => [
        'title' => 'Администрирование',
        'submenu' => [
            'roles' => 'Редактор ролей'
        ]
    ],
    'im' => [
        'title' => 'Конвейер разбора (CSV/ZIP)',
        'submenu' => [
            'upload' => 'Загрузка пакета'
        ]
    ],
    'ca' => [
        'title' => 'Каталог деталей и моделей',
        'submenu' => [
            'parts' => 'База запчастей'
        ]
    ],
    'ac' => [
        'title' => 'Бухгалтерия и цены',
        'submenu' => [
            'prices' => 'Управление ценами'
        ]
    ]
]);
