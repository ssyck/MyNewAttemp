<!-- app/views/ad/plugins_manager_form.php -->
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="text-dark font-weight-bold mb-0">Менеджер системных плагинов и инструментов</h5>
    <span class="badge bg-dark small px-2 py-1" style="font-size: 11px;">Модульное ядро v2.0</span>
</div>
<hr class="mt-1">

<!-- 1. ВЫВОД СИСТЕМНЫХ УВЕДОМЛЕНИЙ ЯДРА -->
<?php 
if (!empty($_SESSION['plugin_success'])) {
    echo '<div class="alert alert-success py-2 small shadow-sm border-0 mb-3">Успех: ' . $_SESSION['plugin_success'] . '</div>';
    unset($_SESSION['plugin_success']);
}
if (!empty($_SESSION['plugin_error'])) {
    echo '<div class="alert alert-danger py-2 small shadow-sm border-0 mb-3">Ошибка: ' . $_SESSION['plugin_error'] . '</div>';
    unset($_SESSION['plugin_error']);
}
?>

<p class="text-muted small">
    Интерфейс предназначен для бесшовного расширения интернет-магазина запчастей без изменения монолитного кода. Загружаемые архивы автоматически разкладываются по ящичкам и наследуют права доступа.
</p>

<!-- 2. БЛОК ИМПОРТА (ЗАГРУЗКА ПАКЕТА) -->
<div class="card border-0 bg-light p-3 shadow-sm mb-4">
    <h6 class="text-dark font-weight-bold mb-2 small text-uppercase" style="letter-spacing: 0.5px;">Импорт нового инструмента / плагина</h6>
    <form method="POST" action="?mod=ad&op=plugin_upload" enctype="multipart/form-data" class="row g-2 align-items-center">
        <div class="col-md-9">
            <div class="input-group input-group-sm">
                <label class="input-group-text bg-secondary text-white small" for="pluginZipFile">Пакет (ZIP)</label>
                <input type="file" name="plugin_zip" class="form-control" id="pluginZipFile" accept=".zip" required>
            </div>
            <div class="form-text text-muted mt-1" style="font-size: 11px;">Пакет должен содержать криптографический паспорт action.json с авторской цифровой подписью.</div>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-dark btn-sm w-100 shadow-sm font-weight-bold">Установить мод</button>
        </div>
    </form>
</div>

<!-- 3. ТАБЛИЦА УСТАНОВЛЕННЫХ ИНСТРУМЕНТОВ (РЕЕСТР ПЛАГИНОВ) -->
<div class="card shadow-sm border-0 p-3 bg-white">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="text-secondary font-weight-bold mb-0 small text-uppercase" style="letter-spacing: 0.5px;">Реестр установленных плагинов:</h6>
        <span class="text-muted small" style="font-size: 11px;">Всего компонентов: 3</span>
    </div>
    
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
            <thead class="table-dark small">
                <tr>
                    <th scope="col" style="width: 30%;">ID / Название плагина</th>
                    <th scope="col" style="width: 12%;">Версия</th>
                    <th scope="col" style="width: 25%;">Авторская подпись (SHA-256)</th>
                    <th scope="col" style="width: 13%;">Статус</th>
                    <th scope="col" class="text-end" style="width: 20%;">Управление</th>
                </tr>
            </thead>
            <tbody>
                
                <!-- КОМПОНЕНТ 1: Менеджер плагинов (Защищен флажком is_system) -->
                <tr>
                    <td>
                        <div class="font-weight-bold text-dark">plugins_manager</div>
                        <div class="text-muted" style="font-size: 11px;">Менеджер системных плагинов и инструментов ядра</div>
                    </td>
                    <td><span class="badge bg-dark font-monospace">2.0.0</span></td>
                    <td><span class="text-primary font-weight-bold" style="font-size: 11px;">Компонент ядра (Защищен)</span></td>
                    <td><span class="badge bg-success text-uppercase" style="font-size: 10px;">ЯДРО</span></td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <a href="?mod=ad&op=plugins&action=config&id=pm" class="btn btn-outline-dark py-1 px-2" style="font-size: 11px;">Настроить</a>
                            <button class="btn btn-outline-secondary py-1 px-2 disabled" style="font-size: 11px; opacity: 0.4;">Выключить</button>
                            <button class="btn btn-outline-secondary py-1 px-2 disabled" style="font-size: 11px; opacity: 0.4;">Удалить</button>
                        </div>
                    </td>
                </tr>

                <!-- КОМПОНЕНТ 2: Редактор ролей (Защищен флажком is_system) -->
                <tr>
                    <td>
                        <div class="font-weight-bold text-dark">admin_upgrade</div>
                        <div class="text-muted" style="font-size: 11px;">Редактор ролей и доступов сотрудников</div>
                    </td>
                    <td><span class="badge bg-dark font-monospace">2.0.0</span></td>
                    <td><span class="text-primary font-weight-bold" style="font-size: 11px;">Компонент ядра (Защищен)</span></td>
                    <td><span class="badge bg-success text-uppercase" style="font-size: 10px;">ЯДРО</span></td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <a href="?mod=ad&op=plugins&action=config&id=roles" class="btn btn-outline-dark py-1 px-2" style="font-size: 11px;">Настроить</a>
                            <button class="btn btn-outline-secondary py-1 px-2 disabled" style="font-size: 11px; opacity: 0.4;">Выключить</button>
                            <button class="btn btn-outline-secondary py-1 px-2 disabled" style="font-size: 11px; opacity: 0.4;">Удалить</button>
                        </div>
                    </td>
                </tr>
                
                <!-- КОМПОНЕНТ 3: Обычный навесной мод -->
                <tr>
                    <td>
                        <div class="font-weight-bold text-dark">front_search</div>
                        <div class="text-muted" style="font-size: 11px;">Умный живой поиск деталей на фронтенде</div>
                    </td>
                    <td><span class="badge bg-secondary font-monospace">1.0.0</span></td>
                    <td><span class="text-success font-weight-bold" style="font-size: 11px;">Подлинность подтверждена</span></td>
                    <td><span class="badge bg-secondary text-uppercase" style="font-size: 10px;">Выключен</span></td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <a href="?mod=ad&op=plugins&action=config&id=front_search" class="btn btn-outline-dark py-1 px-2" style="font-size: 11px;">Настроить</a>
                            <a href="?mod=ad&op=plugins&action=toggle&id=front_search" class="btn btn-outline-success py-1 px-2" style="font-size: 11px;">Включить</a>
                            <a href="?mod=ad&op=plugins&action=delete&id=front_search" class="btn btn-outline-danger py-1 px-2" style="font-size: 11px;">Удалить</a>
                        </div>
                    </td>
                </tr>

            </tbody>
        </table>
    </div>
</div>
