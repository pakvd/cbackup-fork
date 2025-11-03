# Установка зависимостей Composer

Если при запуске контейнера возникает ошибка `vendor/autoload.php not found`, выполните следующие шаги:

## Быстрое решение

```bash
# Войти в контейнер и установить зависимости
docker compose exec web composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-reqs

# Если не работает, попробуйте обновить зависимости
docker compose exec web composer update --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-reqs
```

## Если проблема с bower-asset пакетами

Если Composer не может найти пакеты `bower-asset/*`, это означает, что нужно использовать `asset-packagist.org`. 

В `composer.json` уже добавлен репозиторий `asset-packagist.org`, но если `composer.lock` устарел, нужно обновить его:

```bash
# Удалить старый composer.lock (если он есть)
docker compose exec web rm -f /var/www/html/composer.lock

# Установить зависимости заново (создаст новый lock файл)
docker compose exec web composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-reqs
```

## Проверка установки

```bash
# Проверить, что vendor директория создана
docker compose exec web ls -la /var/www/html/vendor | head -10

# Проверить, что autoload.php существует
docker compose exec web test -f /var/www/html/vendor/autoload.php && echo "OK" || echo "ERROR"
```

## Автоматическая установка при старте контейнера

Entrypoint скрипт (`docker-entrypoint.sh`) автоматически проверяет наличие директории `vendor` и устанавливает зависимости при старте контейнера.

Если это не работает, перезапустите контейнер:

```bash
docker compose restart web
docker compose logs web | grep -i composer
```

## Решение проблем

### Проблема: "composer.lock is not up to date"

**Решение:** Обновите зависимости:
```bash
docker compose exec web composer update --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-reqs
```

### Проблема: "bower-asset packages not found"

**Решение:** Убедитесь, что в `composer.json` есть репозиторий `asset-packagist.org`:
```json
"repositories": [
  {
    "type": "composer",
    "url": "https://asset-packagist.org"
  }
]
```

Затем обновите зависимости:
```bash
docker compose exec web composer update --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-reqs
```

### Проблема: "Memory limit exceeded"

**Решение:** Увеличьте лимит памяти для PHP:
```bash
docker compose exec web php -d memory_limit=512M /usr/bin/composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-reqs
```

### Проблема: Зависимости не устанавливаются после перезапуска

**Причина:** Volume mount перезаписывает директорию `vendor` из образа.

**Решение:** 
1. Убедитесь, что entrypoint скрипт запускается (проверьте логи)
2. Установите зависимости вручную (см. "Быстрое решение")
3. Или пересоберите образ с установленными зависимостями:
```bash
docker compose build --no-cache web
docker compose up -d web
```

