# История изменений: Синхронизация application.properties

## Версия 1.0 (2024-11-02)

### Добавлено
- ✅ Автоматическая синхронизация `application.properties` с базой данных
- ✅ Создание файла автоматически при установке
- ✅ Кнопка "Синхронизировать application.properties" в веб-интерфейсе
- ✅ Консольная команда `yii sync-properties/check` для диагностики
- ✅ Автоматическое исправление прав доступа при старте контейнера

### Исправлено
- ✅ Предупреждение "Mismatched data" теперь не показывается после успешной синхронизации
- ✅ Правильная обработка пустых значений из базы данных
- ✅ Нормализация значений при сравнении (строки)
- ✅ Улучшенная обработка ошибок с понятными сообщениями

### Изменено
- ✅ Файл `application.properties` создается автоматически в `docker-entrypoint.sh`
- ✅ Файл синхронизируется при финализации установки в `install/index.php`
- ✅ Улучшена обработка прав доступа (автоматическое исправление владельца)

### Безопасность
- ✅ Использование `exec()` ограничено только исправлением прав доступа
- ✅ Проверка пользователя перед использованием `exec()` (только root/www-data)
- ✅ Все пути к файлам экранированы
- ✅ Удален отладочный код (console.log) из production

### Документация
- ✅ Добавлена секция в README.md о синхронизации
- ✅ Обновлен FIX_PERMISSIONS.md с актуальными инструкциями
- ✅ Обновлен PRODUCTION_CHECKLIST.md

## Технические детали

### Файлы, затронутые изменениями:
1. `core/models/Config.php` - основная логика синхронизации
2. `core/controllers/ConfigController.php` - AJAX endpoint для синхронизации
3. `core/views/config/index.php` - кнопка синхронизации
4. `core/web/js/config/script.js` - обработчик кнопки
5. `core/docker-entrypoint.sh` - автоматическое создание файла
6. `core/web/install/index.php` - создание при установке
7. `set-permissions.sh` - создание файла при установке прав
8. `core/commands/SyncPropertiesController.php` - консольная команда
9. `core/config/console.php` - регистрация команды

### Процесс синхронизации:

1. **Автоматическая синхронизация:**
   - При сохранении значений `javaScheduler*` в базе данных
   - При загрузке страницы конфигурации (проверка несоответствий)

2. **Ручная синхронизация:**
   - Через кнопку в веб-интерфейсе
   - Через консольную команду `yii sync-properties/check`

3. **Создание файла:**
   - При первом запуске контейнера (docker-entrypoint.sh)
   - При установке приложения (install/index.php)
   - При установке прав (set-permissions.sh)

### Соответствие ключей:

| База данных | application.properties |
|-------------|------------------------|
| `javaSchedulerPort` | `sshd.shell.port` |
| `javaSchedulerUsername` | `sshd.shell.username` |
| `javaSchedulerPassword` | `sshd.shell.password` |

### Права доступа:

- Директория `core/bin/`: 755 (drwxr-xr-x)
- Файл `application.properties`: 644 (-rw-r--r--)
- Владелец: `www-data:www-data` (PHP-FPM user)

## Рекомендации для продакшена

1. После обновления кода выполните:
   ```bash
   docker compose exec web php yii sync-properties/check
   ```

2. Проверьте права доступа:
   ```bash
   docker compose exec web ls -la /var/www/html/bin/
   ```

3. Если возникают проблемы с правами:
   ```bash
   docker compose exec web bash -c "chown -R www-data:www-data /var/www/html/bin && chmod 755 /var/www/html/bin && chmod 644 /var/www/html/bin/application.properties"
   docker compose restart web
   ```

## Известные ограничения

- `exec()` функция может быть отключена в некоторых конфигурациях PHP (используется только для автоматического исправления прав)
- Файл должен быть доступен для записи пользователю веб-сервера (www-data)

