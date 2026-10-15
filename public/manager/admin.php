<?php
// public/manager/admin.php
declare(strict_types=1);

// Вычисляем абсолютные пути прямо здесь (без лишних файлов конфигурации)
$calculatedRoot = dirname(__DIR__, 2) . '/';
$normalizedRoot = str_replace('\\', '/', $calculatedRoot);
define('DATA_PATH', $normalizedRoot . 'app/data/');

// 1. ПЕРЕХВАТ АВТОРИЗАЦИИ APACHE
$remoteUser = $_SERVER['REMOTE_USER'] ?? $_SERVER['REDIRECT_REMOTE_USER'] ?? null;

if (!$remoteUser) {
    header('HTTP/1.1 403 Forbidden');
    die("Критическая ошибка безопасности: Вы не авторизованы на сервере Apache.");
}

// 2. ЧТЕНИЕ МАТРИЦЫ И АВТО-РЕГИСТРАЦИЯ «НА ЛЕТУ»
$rolesFile = DATA_PATH . 'system/roles.json';
$registry = file_exists($rolesFile) ? json_decode(file_get_contents($rolesFile), true) : [];

// Если вошел ssyck — принудительно даем ему права, если файла не было
if ($remoteUser === 'ssyck' && !isset($registry['staff_registry']['ssyck'])) {
    $registry['menu_manifest'] = [
        "ad" => "Администрирование",
        "im" => "Конвейер разбора (CSV/ZIP)",
        "ca" => "Каталог деталей и моделей",
        "ac" => "Бухгалтерия и цены"
    ];
    $registry['staff_registry']['ssyck'] = [
        'role' => 'Главный Суперадмин',
        'is_active' => true,
        'allowed_menus' => ["ad", "im", "ca", "ac"]
    ];
    file_put_contents($rolesFile, json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Новый сотрудник регистрируется вслепую (Zero-Trust)
if (!isset($registry['staff_registry'][$remoteUser])) {
    $registry['staff_registry'][$remoteUser] = [
        'role' => 'Новый сотрудник (Ожидает проверки)',
        'is_active' => false,
        'allowed_menus' => []
    ];
    file_put_contents($rolesFile, json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$userData = $registry['staff_registry'][$remoteUser];

// Проверка активности
if (!(bool)($userData['is_active'] ?? false)) {
    header('Content-Type: text/html; charset=utf-8');
    die("Доступ ограничен. Учетная запись [<strong>" . htmlspecialchars($remoteUser) . "</strong>] ожидает активации.");
}

$userRole = $userData['role'] ?? 'Сотрудник';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Панель управления</title>
    <style>
        /* Спартанский стиль для верхней строки */
        .top-bar {
            font-family: monospace;
            font-size: 14px;
            padding: 10px 20px;
            background: #fff;
            border-bottom: 1px solid #ccc;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logout-btn {
            background: #dc3545;
            color: #fff;
            border: 1px solid #dc3545;
            padding: 4px 12px;
            cursor: pointer;
            font-family: sans-serif;
            font-weight: bold;
        }
        .logout-btn:hover { background: #bd2130; }
    </style>
</head>
<body style="margin:0; background:#f5f5f5;">

    <!-- ВЕРХНЯЯ СТРОКА С ИНФОРМАЦИЕЙ (Ровно то, что просили) -->
    <div class="top-bar">
        <div>
            <strong><?= date('Y-m-d') ?></strong> 
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            Вы зашли как: <strong><?= htmlspecialchars($remoteUser) ?></strong> (<?= htmlspecialchars($userRole) ?>)
        </div>
        <div>
            <!-- Кнопка выйти, которая ОБЯЗАТЕЛЬНО сбросит сессию Apache -->
            <button class="logout-btn" onclick="apacheLogout()">ВЫЙТИ</button>
        </div>
    </div>

<script>
/**
 * Функция гарантированного разлогина в Apache Basic Auth без закрытия браузера
 */
function apacheLogout() {
    const ajax = new XMLHttpRequest();
    // Отправляем запрос с заведомо ложными логином и паролем
    ajax.open("GET", window.location.href, true, "logout", "logout");
    ajax.send();
    ajax.onreadystatechange = function() {
        if (ajax.readyState === 4) {
            // Перезагружаем страницу — Apache снова потребует ввести пароль
            window.location.reload();
        }
    };
}
</script>
</body>
</html>
