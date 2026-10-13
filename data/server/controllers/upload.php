<?php
ini_set('display_errors', 0); // Отключаем вывод HTML-ошибок, чтобы они не ломали JSON
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// Глобальный массив для пошагового лога транзакции
$importLog = [];

function addLog($message) {
    global $importLog;
    $importLog[] = '[' . date('H:i:s') . '] ' . $message;
}

function generateUUIDv4() {
    $data = random_bytes(16);
    $data = chr(ord($data) & 0x0f | 0x40);
    $data = chr(ord($data) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
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

// Настройка путей
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

// --- ШАГ 3: ГИБКАЯ ИНЖЕНЕРНАЯ ВАЛИДАЦИЯ ---
addLog('Шаг 3: Запуск гибкого валидатора полей верхнего уровня (EAV)...');
if (!isset($productData['sku']) || empty(trim($productData['sku']))) {
    rollbackClear($tempExtractPath); addLog('Ошибка валидации: Артикул (sku) отсутствует.'); sendFinalResponse(false, 'Ошибка: в JSON отсутствует или пуст артикул (sku).');
}
if (!isset($productData['name']) || empty(trim($productData['name']))) {
    rollbackClear($tempExtractPath); addLog('Ошибка валидации: Наименование (name) отсутствует.'); sendFinalResponse(false, 'Ошибка: в JSON отсутствует или пусто наименование (name).');
}
if (!isset($productData['category']) || empty(trim($productData['category']))) {
    rollbackClear($tempExtractPath); addLog('Ошибка валидации: Категория (category) отсутствует.'); sendFinalResponse(false, 'Ошибка: в JSON отсутствует или пуста категория (category).');
}
if (!isset($productData['price']['value']) || !is_numeric($productData['price']['value'])) {
    rollbackClear($tempExtractPath); addLog('Ошибка валидации: Некорректный формат цены.'); sendFinalResponse(false, 'Ошибка: поле price.value должно быть числом.');
}
if (!isset($productData['parameters']) || !is_array($productData['parameters'])) {
    rollbackClear($tempExtractPath); addLog('Ошибка валидации: Блок [parameters] отсутствует или не является массивом.'); sendFinalResponse(false, 'Ошибка: блок параметров [parameters] должен быть массивом.');
}

$currentSku = trim($productData['sku']);
addLog('Успех: Валидация пройдена. Товар определен как артикул: ' . $currentSku);

// --- ШАГ 4: ГЕНЕРАЦИЯ UUID И МОДИФИКАЦИЯ JSON ---
addLog('Шаг 4: Инициализация генератора идентификаторов. Вызов generateUUIDv4()...');
$generatedUuid = generateUUIDv4();
$productData['uuid'] = $generatedUuid;
$productData['system_meta'] = [
    'imported_at' => date('d.m.Y H:i:s'),
    'operator' => 'ssyck'
];
addLog('Успех: Детали присвоен постоянный UUID: ' . $generatedUuid);

addLog('Перезапись модифицированного паспорта во временную папку...');
$jsonResult = json_encode($productData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if (file_put_contents($jsonFilePath, $jsonResult) === false) {
    rollbackClear($tempExtractPath);
    addLog('Критическая ошибка: Не удалось записать изменения в файл на диске.');
    sendFinalResponse(false, 'Не удалось внедрить UUID в JSON-паспорт товара.');
}

addLog('Проверка и обновление сквозного реестра артикулов sku_registry.json...');
if (!file_exists($registryPath)) {
    file_put_contents($registryPath, '{}');
}

$registryData = json_decode(file_get_contents($registryPath), true);
if (json_last_error() !== JSON_ERROR_NONE) $registryData = [];

$registryData[$currentSku] = $productFolder;
if (file_put_contents($registryPath, json_encode($registryData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
    rollbackClear($tempExtractPath);
    addLog('Критическая ошибка: Не удалось обновить реестр sku_registry.json');
    sendFinalResponse(false, 'Не удалось обновить реестр артикулов.');
}
addLog('Успех: Связка SKU -> Папка зафиксирована в системном реестре.');

// --- ШАГ 5: ТРАНЗАКЦИОННЫЙ ПЕРЕНЕС ПАПКИ ---
addLog('Шаг 5: Перенос скомпилированной папки товара в боевую базу /products/...');
$finalProductPath = $productsFolder . '/' . $productFolder;

if (is_dir($finalProductPath)) {
    addLog('Внимание: Обнаружена старая версия папки товара. Запуск очистки старых данных...');
    rollbackClear($finalProductPath);
}

customCopyDir($targetProductDir, $finalProductPath);
addLog('Успех: Все файлы и изображения успешно разложены по полочкам.');

addLog('Запуск финальной очистки временного хранилища /tmp/...');
rollbackClear($tempExtractPath);
addLog('Конвейер транзакции успешно закрыт. Подготовка успешного ответа...');

// Полный итоговый успех
sendFinalResponse(true, 'Товар успешно импортирован!', [
    'sku' => $currentSku,
    'name' => $productData['name'],
    'uuid' => $generatedUuid
]);