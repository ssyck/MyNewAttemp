<?php
// app/controllers/ad/PluginsController.php
declare(strict_types=1);

function run_plugins_controller(string $op, string $currentUsername): void 
{
    // --- ОБРАБОТКА POST: ЗАГРУЗКА И ВАЛИДАЦИЯ АРХИВА ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $op === 'plugin_upload') {
        
        if (!isset($_FILES['plugin_zip']) || $_FILES['plugin_zip']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['plugin_error'] = "Файл не передан или превышен допустимый размер.";
            header('Location: ?mod=ad&op=pm');
            exit();
        }

        $tmpPath = $_FILES['plugin_zip']['tmp_name'];

        require_once PRIVATE_PATH . 'core/PluginValidator.php';
        $errorOutput = [];
        $manifest = PluginValidator::validatePackage($tmpPath, $errorOutput);

        if ($manifest === null) {
            $errorCode = $errorOutput['code'] ?? 'VALIDATION_ERROR';
            $errorMsg  = $errorOutput['msg']  ?? 'Ошибка безопасности.';
            $_SESSION['plugin_error'] = "[{$errorCode}]: {$errorMsg}";
            header('Location: ?mod=ad&op=pm');
            exit();
        }

        // Тут в будущем будет физическая Zip-распаковка!
        $_SESSION['plugin_success'] = "Плагин '" . htmlspecialchars($manifest['title']) . "' успешно прошел проверку подписи SHA-256.";
        header('Location: ?mod=ad&op=pm');
        exit();
    }

    // --- ОБРАБОТКА GET: ПОДКЛЮЧЕНИЕ ЧИСТОЙ ФОРМЫ ---
    $viewPath = VIEWS_PATH . 'ad/plugins_manager_form.php';
    if (file_exists($viewPath)) {
        require_once $viewPath;
    } else {
        echo "<div class='alert alert-danger small'>Ошибка: Форма менеджера плагинов не найдена.</div>";
    }
}
