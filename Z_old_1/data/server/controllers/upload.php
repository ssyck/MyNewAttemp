<?php
ini_set('display_errors', 0); // Отключаем вывод HTML-ошибок, чтобы они не ломали JSON response
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// Глобальный массив для пошагового лога транзакции
$importLog = [];

function addLog($message) {
    global $importLog;
    $logLine = '[' . date('H:i:s') . '] ' . $message;
    $importLog[] = $logLine;
    
    // Дублируем лог на диск для контроля домашних аномалий Windows
    file_put_contents(__DIR__ . '/debug_import.log', $logLine . PHP_EOL, FILE_APPEND);
}

function generateUUIDv4() {
    // Автономный генератор UUIDv4, стабильный на любой Windows/XAMPP
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x40, // версия 4
        mt_rand(0, 0x3fff) | 0x80, // вариант bits 6-7 в 10
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function rollbackClear($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? rollbackClear("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
}

function customCopyDir($src, $dst) {
    $dir = opendir($src);
    @mkdir($dst, 0755, true);
    while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            if (is_dir($src . '/' . $file)) {
                customCopyDir($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

function sendFinalResponse($success, $message, $data = []) {
    global $importLog;
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'log' => $importLog,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Стартовый контроль запроса
addLog('Инициализация конвейера. Проверка метода запроса...');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    addLog('Критическая ошибка: Метод запроса не является POST.');
    sendFinalResponse(false, 'Неверный метод запроса.');
}

if (!isset($_FILES['zip_package'])) {
    addLog('Критическая ошибка: Файл zip_package не найден в глобальном массиве $_FILES.');
    sendFinalResponse(false, 'Архив товара не получен сервером.');
}

$file = $_FILES['zip_package'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    addLog('Критическая ошибка загрузки PHP. Код ошибки: ' . $file['error']);
    sendFinalResponse(false, 'Ошибка загрузки PHP: ' . $file['error']);
}

// Настройка путей (Принудительно строчный регистр для совместимости с Linux-хостингом)
$rootPath = "C:/xampp/htdocs/MyNewAttemp";
$systemFolder = $rootPath . '/data/server/system';
$productsFolder = $rootPath . '/data/server/products';
$registryPath = $systemFolder . '/sku_registry.json';
$tmpRoot = $rootPath . '/tmp';

addLog('Проверка и подготовка системных директорий на диске XAMPP...');
if (!is_dir($systemFolder)) @mkdir($systemFolder, 0755, true);
if (!is_dir($productsFolder)) @mkdir($productsFolder, 0755, true);
if (!is_dir($tmpRoot)) @mkdir($tmpRoot, 0755, true);

$timestamp = time();
$tempFolderName = 'import_' . $timestamp . '_' . uniqid();
$tempExtractPath = $tmpRoot . '/' . $tempFolderName;

if (!mkdir($tempExtractPath, 0755, true)) {
    addLog('Критическая ошибка: Не удалось создать изолированную временную папку в ' . $tmpRoot);
    sendFinalResponse(false, 'Бэкенд не смог создать временную папку.');
}

// --- ШАГ 1: РАСПАКОВКА ZIP В TMP ---
addLog('Шаг 1: Запуск модуля ZipArchive. Открытие архива...');
$zip = new ZipArchive;
if ($zip->open($file['tmp_name']) === true) {
    $zip->extractTo($tempExtractPath);
    $zip->close();
    addLog('Успех: Архив распакован во временную директорию: ' . $tempFolderName);
} else {
    rollbackClear($tempExtractPath);
    addLog('Критическая ошибка: Класс ZipArchive вернул false при открытии файла.');
    sendFinalResponse(false, 'Не удалось распаковать ZIP-архив.');
}

// Сканирование папки товара
addLog('Сканирование извлеченной структуры на наличие папки товара...');
$items = array_diff(scandir($tempExtractPath), ['.', '..']);
$productFolder = '';
foreach ($items as $item) {
    if (is_dir($tempExtractPath . '/' . $item)) {
        if ($item === '__MACOSX') continue; // Игнорируем технический мусор macOS
        $productFolder = $item;
        break;
    }
}

if (empty($productFolder)) {
    rollbackClear($tempExtractPath);
    addLog('Критическая ошибка структуры: В корне архива не найдена папка товара.');
    sendFinalResponse(false, 'Внутри архива не найдена папка товара.');
}
addLog('Успех: Обнаружена целевая папка товара: [' . $productFolder . ']');

$targetProductDir = $tempExtractPath . '/' . $productFolder;
$jsonFilePath = $targetProductDir . '/' . $productFolder . '.json';

// --- ШАГ 2: ПОИСК И АНАЛИЗ JSON-ПАСПОРТА ---
addLog('Шаг 2: Поиск конфигурационного JSON-паспорта детали...');
if (!file_exists($jsonFilePath)) {
    rollbackClear($tempExtractPath);
    addLog('Критическая ошибка: Файл [' . $productFolder . '.json] отсутствует во вложенной папке.');
    sendFinalResponse(false, "Отсутствует файл описания [{$productFolder}.json] во вложенной папке.");
}

addLog('Чтение сырых данных JSON и синтаксический анализ...');
$jsonRawData = file_get_contents($jsonFilePath);
$productData = json_decode($jsonRawData, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    rollbackClear($tempExtractPath);
    addLog('Критическая ошибка: Синтаксис JSON нарушен. Ошибка: ' . json_last_error_msg());
    sendFinalResponse(false, 'Файл JSON содержит ошибки синтаксиса.');
}
addLog('Успех: Системный паспорт детали успешно десериализован в массив.');

// --- ШАГ 3: ГИБКАЯ ИНЖЕНЕРНАЯ ВАЛИДАЦИЯ (МАНИФЕСТ ИНЖЕНЕРА) ---
addLog('Шаг 3: Запуск умного валидатора полей по минимальному шаблону...');

// 1. Обязательное поле: Артикул (Загрузка СТРОГО один раз)
if (!isset($productData['sku']) || empty(trim($productData['sku']))) {
    rollbackClear($tempExtractPath); 
    addLog('Ошибка валидации: Артикул (sku) отсутствует.'); 
    sendFinalResponse(false, 'Ошибка: в JSON отсутствует или пуст артикул (sku).');
}
$currentSku = trim($productData['sku']);

// 2. Обязательное поле: Наименование
if (!isset($productData['name']) || empty(trim($productData['name']))) {
    rollbackClear($tempExtractPath); 
    addLog('Ошибка валидации: Наименование (name) отсутствует.'); 
    sendFinalResponse(false, 'Ошибка: в JSON отсутствует или пусто наименование (name).');
}

// 3. Категория ("Без категории" тоже категория)
if (!isset($productData['category']) || empty(trim($productData['category']))) {
    $productData['category'] = "Без категории";
    addLog('Внимание: Категория не указана. Присвоен дефолт: [Без категории]');
}

// 4. Обработка цены (разрешаем отсутствие или пустые значения -> ставим null)
if (!isset($productData['price']['value']) || $productData['price']['value'] === '' || $productData['price']['value'] === null) {
    $productData['price']['value'] = null;
    addLog('Внимание: Цена товара отсутствует. Присвоено значение null (Цена по запросу).');
} else {
    $productData['price']['value'] = (float)$productData['price']['value'];
}

// Валюта по умолчанию — руб
if (!isset($productData['price']['currency']) || empty(trim($productData['price']['currency']))) {
    $productData['price']['currency'] = "руб";
}

// 5. Контроль блока параметров (Свободный EAV, набор не регламентируем)
if (!isset($productData['parameters'])) {
    $productData['parameters'] = [];
}

$finalProductPath = $productsFolder . '/' . $productFolder;

// ЖЕСТКИЙ ЗАМОК УНИКАЛЬНОСТИ SKU: Никаких затираний и перезаписей!
if (is_dir($finalProductPath) || (file_exists($registryPath) && isset(json_decode(file_get_contents($registryPath), true)[$currentSku]))) {
    rollbackClear($tempExtractPath);
    addLog("КРИТИЧЕСКИЙ ОТКАЗ: Артикул [{$currentSku}] уже существует в базе! Перезапись через импорт заблокирована.");
    sendFinalResponse(false, "Товар с артикулом {$currentSku} уже инициализирован.");
}

// Бинарное сканирование файлов папки (Проверка картинок на вшивость)
$allowedImgExt = ['webp'];
$maxImgSize = 10 * 1024 * 1024; // 10 МБ
$detectedImages = [];
$videoUrl = '';

$innerItems = array_diff(scandir($targetProductDir), ['.', '..']);

foreach ($innerItems as $innerItem) {
    $currentFilePath = $targetProductDir . '/' . $innerItem;
    if (is_dir($currentFilePath)) continue;

    $fileExtension = strtolower(pathinfo($innerItem, PATHINFO_EXTENSION));

    // Очистка системного мусора Windows/macOS
    if ($innerItem === '.DS_Store' || $innerItem === 'Thumbs.db' || $innerItem === $productFolder . '.json') {
        continue;
    }

    // Обработка опционального файла видео-указателя
    if ($innerItem === 'video.txt') {
        if (filesize($currentFilePath) > 2048) {
            rollbackClear($tempExtractPath);
            addLog("Ошибка безопасности: Файл video.txt превышает 2 КБ.");
            sendFinalResponse(false, "Файл video.txt слишком большой.");
        }
        $videoUrl = trim(file_get_contents($currentFilePath));
        addLog("Успех: Обнаружен и валидирован указатель видео video.txt");
        continue;
    }

    // Проверка медиафайлов (СТРОГО МОНОФОРМАТ WEBP)
    if (in_array($fileExtension, $allowedImgExt)) {
        if (filesize($currentFilePath) > $maxImgSize) {
            rollbackClear($tempExtractPath);
            addLog("Ошибка безопасности: Изображение {$innerItem} превышает лимит размера.");
            sendFinalResponse(false, "Файл {$innerItem} слишком большой.");
        }

        // Проверка на вшивость по бинарным Magic Numbers
        $realMime = @mime_content_type($currentFilePath);
        if ($realMime !== 'image/webp') {
            rollbackClear($tempExtractPath);
            addLog("КРИТИЧЕСКАЯ АТАКА: Файл {$innerItem} косит под WebP, но бинарную проверку провалил!");
            sendFinalResponse(false, "Файл {$innerItem} содержит некорректную бинарную структуру.");
        }
        $detectedImages[] = $innerItem;
        addLog("Успех: Изображение {$innerItem} прошло бинарную проверку.");
        continue;
    }

    // Любой посторонний формат (jpg, png) вызывает откат транзакции
        rollbackClear($tempExtractPath);
        addLog("ОТКАЗ ИМПОРТА: В архиве обнаружен запрещенный формат файла: [{$innerItem}]");
        sendFinalResponse(false, "Стандарт нарушен! Файл {$innerItem} отклонен. Медиабаза склада должна быть строго в формате .webp");
}
        sort($detectedImages); // Алфавитная стабилизация массива
        addLog('Успех: Все этапы бинарной проверки пройдены. Найдено WebP-изображений: ' . count($detectedImages));

// --- ШАГ 4: СБОРКА, ОЧИСТКА И ПЕРЕЗАПИСЬ ПАСПОРТА ---
addLog('Шаг 4: Сборка финального паспорта, генерация постоянного UUID и очистка meta...');
$generatedUuid = generateUUIDv4();
$productData['uuid'] = $generatedUuid;
// Формируем структуру медиа. Если картинок нет — оставляем пустую строку под фронтенд-заглушку в data/assets/img/no-photo.webp
$productData['media'] = [
'main_image' => (!empty($detectedImages) ? $detectedImages[0] : ''),
'all_images' => $detectedImages,
'video_url'  => $videoUrl
];
// Очистка пустых meta-тегов (если они пустые — не дописываем блок в итоговый JSON)
if (isset($productData['meta'])) {
$hasMetaContent = false;
foreach ($productData['meta'] as $key => $val) {
if (!empty(trim($val))) {
$hasMetaContent = true;
}
}
if (!$hasMetaContent) {
unset($productData['meta']);
}
}
$productData['system_meta'] = [
'imported_at' => date('d.m.Y H:i:s'),
'operator' => 'ssyck',
'lifecycle' => 'initialized'
];
addLog('Перезапись модифицированного паспорта во временную папку...');
$jsonResult = json_encode($productData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if (file_put_contents($jsonFilePath, $jsonResult) === false) {
rollbackClear($tempExtractPath);
addLog('Критическая ошибка: Не удалось записать изменения в файл на диске.');
sendFinalResponse(false, 'Не удалось сохранить JSON-паспорт товара.');
}
// Запись в сквозной системный реестр артикулов
$registryData = file_exists($registryPath) ? json_decode(file_get_contents($registryPath), true) : [];
if (json_last_error() !== JSON_ERROR_NONE) $registryData = [];
$registryData[$currentSku] = $productFolder;
file_put_contents($registryPath, json_encode($registryData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
addLog('Успех: Новая связка SKU -> Папка зафиксирована в системном реестре.');

// --- ШАГ 5: ТРАНЗАКЦИОННЫЙ ПЕРЕНЕС ПАПКИ ---
addLog('Шаг 5: Перенос скомпилированной папки товара в боевую базу /products/...');
customCopyDir($targetProductDir, $finalProductPath);
addLog('Успех: Все файлы нового товара успешно разложены по полочкам.');
addLog('Запуск финальной очистки временного хранилища /tmp/...');
rollbackClear($tempExtractPath);
addLog('Конвейер транзакции успешно закрыт. Подготовка успешного ответа...');
// Полный итоговый успех инициализации нового товара
sendFinalResponse(true, 'Новый товар успешно проинициализирован в базе витрины!', [
'sku' => $currentSku,
'name' => $productData['name'],
'uuid' => $generatedUuid
]);