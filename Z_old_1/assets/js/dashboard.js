// assets/js/dashboard.js - Фронтенд-контроллер инженерной панели

// 1. ФУНКЦИЯ ЗАГРУЗКИ И РЕНДЕРИНГА ТАБЛИЦЫ СОТРУДНИКОВ
async function загрузитьРеестрСотрудников() {
    try {
        const tbody = document.getElementById('rbac-table-body');
        if (!tbody) return;
        
        // Включаем визуальный индикатор загрузки
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">🔄 Чтение матрицы ролей с диска...</td></tr>';
        
        // Делаем прямой запрос к контроллеру
        const response = await fetch('data/server/controllers/admin_control.php'); // Проверьте этот путь!
        const result = await response.json();

        if (!result.success) {
        // Если бэкенд вернул отказ — выводим его жирным текстом прямо в таблицу
        tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger fw-bold">⚠️ Отказ бэкенда: ${result.message}</td></tr>`;
        return;
        }
        
        tbody.innerHTML = ''; // Очищаем индикатор загрузки
        
        // Карта разделов системы для генерации чекбоксов
        const доступныеМеню = {
            'admin_panel': '🛡️ Админка',
            'import_pipeline': '📦 Импорт пакетов',
            'tech_passport': '🛠️ Паспорта деталей',
            'accounting': '📊 Бухгалтерия и Цены',
            'sys_logs': '📜 Системный лог'
        };

        // Запускаем перебор всех сотрудников из базы без лишних ограничений
        for (let username in result.users) {
            if (username === 'ssyck') continue; // Суперадмина не даем редактировать
            
            const user = result.users[username];
            let checkboxesHTML = '';
            
            // Генерируем чекбоксы
            for (let menuId in доступныеМеню) {
                const checked = user.allowed_menus && user.allowed_menus.includes(menuId) ? 'checked' : '';
                checkboxesHTML += `
                    <div class="form-check form-check-inline m-1">
                        <input class="form-check-input policy-${username}" type="checkbox" value="${menuId}" id="chk-${username}-${menuId}" ${checked}>
                        <label class="form-check-label small" for="chk-${username}-${menuId}">${доступныеМеню[menuId]}</label>
                    </div>
                `;
            }

            // Формируем чистую строку для таблицы
            const rowHTML = `
                <tr>
                    <td><strong class="text-dark">${username}</strong></td>
                    <td>
                        <input type="text" id="role-${username}" class="form-control form-control-sm" value="${user.role || 'Сотрудник'}">
                    </td>
                    <td>
                        <select id="status-${username}" class="form-select form-select-sm">
                            <option value="1" ${user.is_active ? 'selected' : ''}>🟢 Активен</option>
                            <option value="0" ${!user.is_active ? 'selected' : ''}>🔴 Заблокирован</option>
                        </select>
                    </td>
                    <td>${checkboxesHTML}</td>
                    <td>
                        <button onclick="сохранитьПраваСотрудника('${username}')" class="btn btn-success btn-sm fw-bold">
                            <i class="bi bi-floppy-fill me-1"></i>Сохранить
                        </button>
                    </td>
                </tr>
            `;
            tbody.insertAdjacentHTML('beforeend', rowHTML);
        }
    } catch (error) {
        console.error("Критический сбой AJAX при чтении ролей:", error);
    }
}

// 2. ФУНКЦИЯ ОТПРАВКИ ИЗМЕНЕННЫХ ПРАВ НА СЕРВЕР «НА ЛЕТУ»
async function сохранитьПраваСотрудника(username) {
    try {
        const roleInput = document.getElementById(`role-${username}`);
        const roleValue = roleInput ? roleInput.value.trim() : 'Сотрудник';
        
        const statusSelect = document.getElementById(`status-${username}`);
        const isActive = statusSelect ? statusSelect.value === '1' : false;
        
        const checkedBoxes = document.querySelectorAll(`.policy-${username}:checked`);
        const allowedMenus = Array.from(checkedBoxes).map(cb => cb.value);
        
        if (allowedMenus.length === 0 && isActive) {
            if (!confirm(`Вы не выбрали ни одного раздела для ${username}. Сотрудник увидит пустую панель. Продолжить?`)) {
                return;
            }
        }

        const response = await fetch('controllers/admin_control.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                username: username,
                role: roleValue,
                is_active: isActive,
                allowed_menus: allowedMenus
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // ИСПРАВЛЕНО: Вместо alert вызываем красивое модальное окно Bootstrap 5
            if (result.generated_password) {
                // Заполняем поля внутри модального окна данными
                document.getElementById('modal-display-username').value = username;
                document.getElementById('modal-display-password').value = result.generated_password;
                
                // Возвращаем исходный текст кнопке копирования
                const copyBtn = document.getElementById('btn-copy-password');
                if (copyBtn) copyBtn.innerHTML = '<i class="bi bi-clipboard-plus"></i> Копировать';

                // Инициализируем и открываем модалку через нативный Bootstrap API
                const passwordModal = new bootstrap.Modal(document.getElementById('passwordCredentialsModal'));
                passwordModal.show();
            } else {
                // Если пароль не генерировался (просто обновили права), показываем обычную нотификацию
                alert(result.message);
            }
            
            загрузитьРеестрСотрудников(); // Перерисовываем таблицу ролей
        } else {
            alert(`❌ Ошибка бэкенда: ${result.message}`);
        }

    } catch (error) {
        console.error("Критический сбой AJAX при сохранении прав:", error);
        alert("Не удалось сохранить права.");
    }
}

// 3. ФУНКЦИЯ ЗАПУСКА ИМПОРТА ТОВАРА (ZIP)
async function запуститьИмпортТовара() {
    try {
        const fileInput = document.getElementById('zip_package');
        const logZone = document.getElementById('log-output-zone');
        
        // 1. Проверяем, выбрал ли инженер файл
        if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
            alert("Пожалуйста, сначала выберите .zip пакет товара для импорта.");
            return;
        }

        // Берем строго первый выбранный файл из инпута
        const zipFile = fileInput.files[0];
        
        // Упаковываем файл в бинарный поток FormData для передачи через AJAX
        const formData = new FormData();
        formData.append('zip_package', zipFile);

        // Переключаем текст в консоли логов, сигнализируя о старте
        if (logZone) {
            logZone.innerHTML = `[${new Date().toLocaleTimeString()}] 🚀 Отправка ZIP-пакета на сервер XAMPP...<br>`;
        }

        // 2. Делаем AJAX-запрос к нашему бэкенд-контроллеру
        const response = await fetch('data/server/controllers/upload.php', {
            method: 'POST',
            body: formData
        });

        // Если сервер упал или выдал ошибку доступа (например, 404 или 500)
        if (!response.ok) {
            throw new Error(`Сервер вернул статус ошибки: ${response.status}`);
        }

        // Десериализуем JSON-ответ от upload.php
        const result = await response.json();

        // 3. Выводим пошаговый транзакционный лог в темную консоль «Системный лог»
        if (logZone && result.log) {
            logZone.innerHTML = result.log.join('<br>');
            logZone.scrollTop = logZone.scrollHeight; // Автоскролл консоли вниз
        }

        // 4. Показываем финальный результат во всплывающем окне
        if (result.success) {
            alert(`🎉 Успех!\n${result.message}\n\nДеталь: ${result.data.name}\nАртикул (SKU): ${result.data.sku}\nUUID системы: ${result.data.uuid}`);
            fileInput.value = ''; // Сбрасываем поле выбора файла после успешной загрузки
        } else {
            alert(`❌ Критическая остановка конвейера:\n${result.message}`);
        }

    } catch (error) {
        console.error("Критический сбой AJAX при импорте пакета:", error);
        alert("Не удалось запустить конвейер импорта. Проверьте вкладку Console (F12) в браузере.");
    }
}

// 4. ВСПОМОГАТЕЛЬНАЯ ФУНКЦИЯ КОПИРОВАНИЯ ПАРОЛЯ В БУФЕР ОБМЕНА
function скопироватьПарольИзМодалки() {
    const passwordInput = document.getElementById('modal-display-password');
    const copyBtn = document.getElementById('btn-copy-password');
    
    if (!passwordInput) return;
    
    // Выделяем текст и копируем через современный Clipboard API
    passwordInput.select();
    passwordInput.setSelectionRange(0, 99999); // Для мобильных устройств
    
    navigator.clipboard.writeText(passwordInput.value).then(() => {
        // Визуально меняем состояние кнопки для фидбека суперадмину
        if (copyBtn) {
            copyBtn.innerHTML = '<i class="bi bi-check-lg"></i> Скопировано!';
            copyBtn.classList.replace('btn-outline-danger', 'btn-success');
            setTimeout(() => {
                copyBtn.innerHTML = '<i class="bi bi-clipboard-plus"></i> Копировать';
                copyBtn.classList.replace('btn-success', 'btn-outline-danger');
            }, 2000);
        }
    }).catch(err => {
        console.error('Не удалось скопировать текст: ', err);
    });
}