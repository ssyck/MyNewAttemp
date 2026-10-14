// assets/js/dashboard.js - Фронтенд-контроллер инженерной панели

// 1. ФУНКЦИЯ ЗАГРУЗКИ И РЕНДЕРИНГА ТАБЛИЦЫ СОТРУДНИКОВ
async function загрузитьРеестрСотрудников() {
    try {
        const tbody = document.getElementById('rbac-table-body');
        if (!tbody) return;
        
        // Включаем визуальный индикатор загрузки
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">🔄 Чтение матрицы ролей с диска...</td></tr>';
        
        // Делаем прямой запрос к контроллеру
        const response = await fetch('controllers/admin_control.php');
        const result = await response.json();
        
        if (!result.success) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">⚠️ ${result.message}</td></tr>`;
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
        alert(result.message);
        
        if (result.success) {
            загрузитьРеестрСотрудников(); // Перерисовываем актуальные данные
        }
    } catch (error) {
        console.error("Критический сбой AJAX при сохранении прав:", error);
        alert("Не удалось сохранить права.");
    }
}