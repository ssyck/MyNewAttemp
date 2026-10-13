<?php
ini_set('display_errors', 0); // Отключаем вывод HTML-ошибок, чтобы они не ломали JSON
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// Глобальный массив для пошагового лога транзакции
$importLog = [];

function addLog($message) {
    global $importLog;
    $logLine = '[' . date('H:i:s') . '] ' . $message;
    $importLog[] = $logLine;
    
    // ПРИНУДИТЕЛЬНАЯ ЗАПИСЬ НА ДИСК ДЛЯ ОТЛАДКИ ДОМАШНИХ ЧУДЕС
    // Файл создастся прямо в папке контроллеров рядом с upload.php
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
        // Игнорируем технические папки macOS, которые могут создаться при архивации
        if ($item === '__MACOSX') continue;
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

// --- СТРОГАЯ БИНАРНАЯ ВАЛИДАЦИЯ ФАЙЛОВОЙ СТРУКТУРЫ И МЕДИА ---
addLog('Запуск модуля безопасности. Бинарное сканирование файлов внутри папки товара...');
$allowedImgExt = ['jpg', 'jpeg', 'png', 'webp'];
$maxImgSize = 10 * 1024 * 1024; // Лимит 10 МБ на одну фотографию

$innerItems = array_diff(scandir($targetProductDir), ['.', '..']);

foreach ($innerItems as $innerItem) {
    $currentFilePath = $targetProductDir . '/' . $innerItem;
    
    // Пропускаем папки, если они есть внутри (наша структура должна быть плоской)
    if (is_dir($currentFilePath)) {
        rollbackClear($tempExtractPath);
        addLog("Ошибка безопасности: Обнаружена вложенная директория [{$innerItem}]. Структура должна быть плоской.");
        sendFinalResponse(false, 'Архив содержит недопустимую структуру вложенных папок.');
    }

    $fileExtension = strtolower(pathinfo($innerItem, PATHINFO_EXTENSION));

    // 1. Очистка системного мусора ОС
    if ($innerItem === '.DS_Store' || $innerItem === 'Thumbs.db') {
        @unlink($currentFilePath);
        addLog("Система: Удален служебный файл метаданных: {$innerItem}");
        continue;
    }

    // 2. Игнорируем сам файл паспорта (мы его уже проверили)
    if ($innerItem === $productFolder . '.json') {
        continue;
    }

    // 3. Валидация картинок
    if (in_array($fileExtension, $allowedImgExt)) {
        // Проверка веса файла
        if (filesize($currentFilePath) > $maxImgSize) {
            rollbackClear($tempExtractPath);
            addLog("Ошибка безопасности: Изображение {$innerItem} превышает лимит размера.");
            sendFinalResponse(false, "Файл {$innerItem} слишком большой (макс. 10 МБ).");
        }

        // Глубокий бинарный анализ сигнатуры изображения
        $imageCheck = @getimagesize($currentFilePath);
        if ($imageCheck === false) {
            rollbackClear($tempExtractPath);
            addLog("КРИТИЧЕСКАЯ АТАКА: Файл {$innerItem} имеет расширение картинки, но бинарный заголовок фальшивый!");
            sendFinalResponse(false, "Файл {$innerItem} заблокирован системой безопасности.");
        }
        
        addLog("Успех: Файл изображения {$innerItem} успешно прошел бинарную валидацию.");
        continue;
    }

    // 4. Если попался любой другой файл (например, .php, .html, .js, .exe)
    rollbackClear($tempExtractPath);
    addLog("Ошибка безопасности: В архиве обнаружен запрещенный тип файла: [{$innerItem}]");
    sendFinalResponse(false, "Запрещено загружать файлы с расширением .{$fileExtension}");
}

$currentSku = trim($productData['sku']);
addLog('Успех: Все этапы валидации пройдены. Товар определен как артикул: ' . $currentSku);

// --- ШАГ 4: ГЕНЕРАЦИЯ UUID И МОДИФИКАЦИЯ JSON ---
addLog('Шаг 4: Инициализация генератора идентификаторов. Вызов generateUUIDv4()...');
$generatedUuid = generateUUIDv4();
$productData['uuid'] = $generatedUuid;

// Сбор всех успешно валидированных картинок для будущего модуля модерации
$detectedImages = [];
foreach ($innerItems as $innerItem) {
    $fileExtension = strtolower(pathinfo($innerItem, PATHINFO_EXTENSION));
    if (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'webp']) && $innerItem !== '.DS_Store' && $innerItem !== 'Thumbs.db') {
        $detectedImages[] = $innerItem;
    }
}

// Формируем гибкую структуру медиа-модуля
// Если инженер уже указал main_image и этот файл реально есть — оставляем его.
// Если там пусто или файла нет — автопилот ставит первую картинку главной.
$existingMain = $productData['media']['main_image'] ?? '';
$finalMainImage = (!empty($existingMain) && in_array($existingMain, $detectedImages)) ? $existingMain : ($detectedImages[0] ?? '');

$productData['media'] = [
    'main_image' => $finalMainImage,  // Будущая "галочка" в интерфейсе запишет сюда выбранный файл
    'all_images' => $detectedImages   // Список для рендеринга галереи в админке
];

$productData['system_meta'] = [
    'imported_at' => date('d.m.Y H:i:s'),
    'operator' => 'ssyck'
];
addLog('Успех: Детали присвоен постоянный UUID: ' . $generatedUuid . '. Сформирован медиа-паспорт из ' . count($detectedImages) . ' изображений.');


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
