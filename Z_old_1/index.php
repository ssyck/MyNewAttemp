<?php
// Включаем отображение ошибок для отладки в песочнице
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Пути к вашей закрытой базе данных на диске XAMPP
$rootPath = __DIR__; // Текущая директория корня
$registryPath = $rootPath . '/server/data/system/sku_registry.json';
$productsFolder = $rootPath . '/server/data/products';

$productsList = [];

// 1. Читаем системный реестр артикулов
if (file_exists($registryPath)) {
    $registryData = json_decode(file_get_contents($registryPath), true);
    
    if (json_last_error() === JSON_ERROR_NONE && is_array($registryData)) {
        // 2. Бежим по каждому зарегистрированному товару
        foreach ($registryData as $sku => $productFolder) {
            $jsonFilePath = $productsFolder . '/' . $productFolder . '/' . $productFolder . '.json';
            
            // Если файл описания товара существует, забираем его данные
            if (file_exists($jsonFilePath)) {
                $productRaw = file_get_contents($jsonFilePath);
                $productData = json_decode($productRaw, true);
                
                if (json_last_error() === JSON_ERROR_NONE) {
                    // Сохраняем данные для вывода на витрину
                    $productsList[] = [
                        'sku' => $sku,
                        'name' => $productData['name'] ?? 'Без названия',
                        'price' => $productData['price']['value'] ?? 0,
                        'uuid' => $productData['uuid'] ?? 'Не сгенерирован',
                        'folder' => $productFolder
                    ];
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Витрина магазина | Каталог товаров</title>
    <!-- Подключаем Bootstrap из вашей локальной автономной папки assets -->
    <link href="assets/css/bootstrap/bootstrap.min.css" rel="stylesheet">
    <style>
        .product-card img {
            height: 180px;
            object-fit: cover;
        }
    </style>
</head>
<body class="bg-light">

    <!-- Шапка сайта -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
    <div class="container">
        <a class="navbar-brand font-monospace" href="index.php">B2B СпецЗапчасть</a>
        <!-- Кнопка "Вход в Админку" полностью удалена -->
    </div>
</nav>

    <!-- Основной каталог -->
    <div class="container">
        <h2 class="mb-4 fw-bold text-secondary text-center text-sm-start">Каталог оборудования</h2>
        
        <!-- Сетка для вывода товаров (Bootstrap Grid) -->
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
            
            <?php if (empty($productsList)): ?>
                <!-- Если реестр пуст или файлов еще нет -->
                <div class="col-12 text-center py-5">
                    <div class="alert alert-secondary d-inline-block px-5">
                        В каталоге пока нет доступных товаров. Зайдите в админку и импортируйте первый пакет!
                    </div>
                </div>
            <?php else: ?>
                <!-- Цикл серверного рендеринга карточек -->
                <?php foreach ($productsList as $product): ?>
                    <?php 
                    // Ищем первую картинку в папке товара (так как картинки лежат в закрытой server/data)
                    // Для вывода картинок клиенту нам нужно будет сделать скрипт-прослойку, либо отдавать их через PHP.
                    // Пока временно ставим аккуратную заглушку.
                    $imageSrc = "https://placehold.co" . urlencode($product['sku']);
                    ?>
                    <div class="col">
                        <div class="card h-100 shadow-sm product-card border-0">
                            <img src="<?= $imageSrc ?>" class="card-img-top bg-secondary-subtle" alt="<?= htmlspecialchars($product['name']) ?>">
                            <div class="card-body d-flex flex-column">
                                <span class="badge bg-secondary mb-2 align-self-start font-monospace">SKU: <?= htmlspecialchars($product['sku']) ?></span>
                                <h5 class="card-title h6 fw-bold text-dark text-truncate" title="<?= htmlspecialchars($product['name']) ?>">
                                    <?= htmlspecialchars($product['name']) ?>
                                </h5>
                                <p class="text-muted small mb-3 text-truncate" style="font-size: 0.75rem;">
                                    UUID: <code><?= htmlspecialchars($product['uuid']) ?></code>
                                </p>
                                <div class="d-flex justify-content-between align-items-center mt-auto">
                                    <span class="fs-5 fw-bold text-primary"><?= number_format($product['price'], 0, '.', ' ') ?> ₽</span>
                                    <button class="btn btn-sm btn-primary px-3">Открыть</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </div>

    <!-- Локальный Bootstrap JS -->
    <script src="assets/js/bootstrap/bootstrap.bundle.min.js"></script>
</body>
</html>