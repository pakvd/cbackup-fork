# Исправление прав доступа для application.properties

## Проблема

При синхронизации `application.properties` может появиться сообщение:
```
Failed to synchronize. Please fix file permissions manually...
```

Это означает, что директория `core/bin/` и/или файл `core/bin/application.properties` не доступны для записи веб-серверу (www-data).

## Решение

### Вариант 1: Исправить права через Docker (рекомендуется)

Выполните одну из следующих команд на сервере:

```bash
# Полное исправление прав (владелец + права доступа)
docker compose exec web bash -c "chown -R www-data:www-data /var/www/html/bin && chmod 755 /var/www/html/bin && chmod 644 /var/www/html/bin/application.properties"

# Или по отдельности:
docker compose exec web chown www-data:www-data /var/www/html/bin
docker compose exec web chown www-data:www-data /var/www/html/bin/application.properties
docker compose exec web chmod 755 /var/www/html/bin
docker compose exec web chmod 644 /var/www/html/bin/application.properties
```

### Вариант 2: Исправить права на хосте

Если вы работаете на хосте (не в Docker):

```bash
cd /путь/к/cbackup-fork
chmod 755 core/bin
chmod 644 core/bin/application.properties

# Убедитесь, что владелец - пользователь веб-сервера
# Для Apache/Nginx обычно www-data или nginx
sudo chown www-data:www-data core/bin core/bin/application.properties
```

### Вариант 3: Использовать консольную команду для диагностики

```bash
# Проверить текущее состояние
docker compose exec web php yii sync-properties/check
```

Эта команда:
1. Покажет текущие значения в БД и файле
2. Покажет права доступа
3. Попытается исправить права автоматически
4. Выполнит синхронизацию

**Важно:** После исправления прав перезапустите контейнер web, чтобы изменения вступили в силу:
```bash
docker compose restart web
```

## Проверка

После исправления прав:

1. Откройте страницу конфигурации: `http://ваш-сервер:8080/index.php?r=config`
2. Нажмите кнопку "Синхронизировать application.properties"
3. Предупреждение должно исчезнуть

## Если проблема остается

1. Проверьте логи веб-сервера:
   ```bash
   docker compose logs web | tail -50
   ```

2. Проверьте, что файл существует и доступен:
   ```bash
   docker compose exec web ls -la /var/www/html/bin/
   docker compose exec web cat /var/www/html/bin/application.properties
   ```

3. Проверьте пользователя, от имени которого работает PHP:
   ```bash
   docker compose exec web whoami
   docker compose exec web id
   ```

4. Установите права напрямую:
   ```bash
   docker compose exec web chmod -R 755 /var/www/html/bin
   docker compose exec web chown -R www-data:www-data /var/www/html/bin
   ```

