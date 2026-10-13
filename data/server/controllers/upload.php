<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

if (!isset($_SESSION['is_engineer_authorized']) || $_SESSION['is_engineer_authorized'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Неавторизованный доступ.'], JSON_UNESCAPED_UNICODE);
    exit;
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

function sendResponse($success, $message, $data = []) {
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendResponse(false, 'Неверный метод запроса.');
if (!isset($_FILES['zip_package'])) sendResponse(false, 'Архив товара не получен сервером.');

$file = $_FILES['zip_package'];
if ($file['error'] !== UPLOAD_ERR_OK) sendResponse(false, 'Ошибка загрузки PHP: ' . $file['error']);

// Жесткие абсолютные пути для XAMPP
$rootPath = "C:/xampp/htdocs/MyNewAttemp";
$systemFolder = $rootPath . '/data/server/system';
$productsFolder = $rootPath . '/data/server/products';
$registryPath = $systemFolder . '/sku_registry.json';
$tmpRoot = $rootPath . '/tmp';

if (!is_dir($tmpRoot)) {
    @mkdir($tmpRoot, 0755, true);
}

$timestamp = time();
$tempFolderName = 'import_' . $timestamp . '_' . uniqid();
$tempExtractPath = $tmpRoot . '/' . $tempFolderName;

if (!mkdir($tempExtractPath, 0755, true)) {
    sendResponse(false, 'Бэкенд не смог создать временную папку.');
}

$zip = new ZipArchive;
if ($zip->open($file['tmp_name']) === true) {
    $zip->extractTo($tempExtractPath);
    $zip->close();
} else {
    rollbackClear($tempExtractPath);
    sendResponse(false, 'Не удалось распаковать ZIP-архив.');
}

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
    sendResponse(false, 'Внутри архива не найдена папка товара.');
}

$targetProductDir = $tempExtractPath . '/' . $productFolder;
$jsonFilePath = $targetProductDir . '/' . $productFolder . '.json';

if (!file_exists($jsonFilePath)) {
    rollbackClear($tempExtractPath);
    sendResponse(false, "Отсутствует файл описания [{$productFolder}.json] во вложенной папке.");
}

$jsonRawData = file_get_contents($jsonFilePath);
$productData = json_decode($jsonRawData, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    rollbackClear($tempExtractPath);
    sendResponse(false, 'Файл JSON содержит ошибки синтаксиса.');
}

// ВАЛИДАЦИЯ СТРОГО ПОД ВАШУ СТРУКТУРУ JSON (kn-006.json)
if (!isset($productData['sku']) || empty(trim($productData['sku']))) {
    rollbackClear($tempExtractPath); sendResponse(false, 'Ошибка: в JSON отсутствует или пуст артикул (sku).');
}
if (!isset($productData['name']) || empty(trim($productData['name']))) {
    rollbackClear($tempExtractPath); sendResponse(false, 'Ошибка: в JSON отсутствует или пусто наименование (name).');
}
if (!isset($productData['price']['value']) || !is_numeric($productData['price']['value'])) {
    rollbackClear($tempExtractPath); sendResponse(false, 'Ошибка: поле price.value должно быть числом.');
}

$currentSku = trim($productData['sku']);
$generatedUuid = generateUUIDv4();

// Внедряем UUID в корень JSON
$productData['uuid'] = $generatedUuid;
$productData['system_meta'] = [
    'imported_at' => date('d.10.Y H:i:s'),
    'operator' => 'ssyck'
];

// Сохраняем обновленный JSON во временную папку
$jsonResult = json_encode($productData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if (file_put_contents($jsonFilePath, $jsonResult) === false) {
    rollbackClear($tempExtractPath);
    sendResponse(false, 'Не удалось внедрить UUID в JSON-паспорт товара.');
}

// Обновление системного реестра артикулов
if (!file_exists($registryPath)) {
    @mkdir($systemFolder, 0755, true);
    file_put_contents($registryPath, '{}');
}

$registryData = json_decode(file_get_contents($registryPath), true);
if (json_last_error() !== JSON_ERROR_NONE) $registryData = [];

$registryData[$currentSku] = $productFolder;
file_put_contents($registryPath, json_encode($registryData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Финальный перенос папки товара в боевую базу /products/
$finalProductPath = $productsFolder . '/' . $productFolder;

if (is_dir($finalProductPath)) {
    rollbackClear($finalProductPath);
}

// Копируем модифицированную папку из tmp в боевую
customCopyDir($targetProductDir, $finalProductPath);

// Полностью очищаем временный мусор в /tmp/
rollbackClear($tempExtractPath);

// ПОЛНЫЙ ИТОГОВЫЙ УСПЕХ КОНВЕЙЕРА
sendResponse(true, 'Товар успешно прошел конвейер, получил UUID и сохранен!', [
    'sku' => $currentSku,
    'name' => $productData['name'],
    'uuid' => $generatedUuid
]);