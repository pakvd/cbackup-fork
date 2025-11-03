# Быстрое исправление ошибки 502

## Проблема
Nginx не может подключиться к PHP-FPM (Connection refused).

## Быстрое решение

### 1. Проверить статус контейнеров

```bash
docker compose ps
```

Убедитесь, что контейнер `cbackup_web` в статусе `Up`.

### 2. Проверить PHP-FPM

```bash
# Запустить диагностический скрипт
./CHECK_PHPFPM.sh

# Или вручную проверить процессы
docker compose exec web ps aux | grep php-fpm

# Проверить прослушивание порта
docker compose exec web netstat -tlnp | grep 9000
# или
docker compose exec web ss -tlnp | grep 9000
```

### 3. Перезапустить контейнер web

```bash
docker compose restart web

# Подождать 5-10 секунд и проверить
sleep 5
docker compose logs web --tail=20
```

### 4. Если PHP-FPM не запускается

Проверить логи на ошибки:

```bash
docker compose logs web | grep -i "error\|fatal\|failed"
```

### 5. Проверить зависимости Composer

Если PHP-FPM падает из-за отсутствия `vendor/autoload.php`:

```bash
docker compose exec web test -f /var/www/html/vendor/autoload.php && echo "OK" || echo "ERROR - установите зависимости"
make install-composer
```

### 6. PHP-FPM слушает на 127.0.0.1 вместо 0.0.0.0

**Проблема:** PHP-FPM слушает на `127.0.0.1:9000`, что блокирует подключения из nginx контейнера.

**Решение:**
```bash
# Использовать скрипт для исправления
./FIX_PHPFPM_LISTEN.sh

# Или вручную:
docker compose exec web sed -i "s|^listen = 127.0.0.1:9000|listen = 0.0.0.0:9000|" /usr/local/etc/php-fpm.d/www.conf
docker compose restart web
```

### 7. Полный перезапуск

Если ничего не помогает:

```bash
docker compose down
docker compose build --no-cache web  # Пересобрать образ (исправления в entrypoint)
docker compose up -d

# Подождать 10-15 секунд для запуска всех контейнеров
sleep 10

# Проверить статус
docker compose ps
docker compose logs web --tail=30
```

## Типичные причины

1. **PHP-FPM не запустился** - проверьте логи `docker compose logs web`
2. **Зависимости Composer не установлены** - `make install-composer`
3. **Ошибки в конфигурации PHP** - проверьте `docker compose logs web | grep error`
4. **Контейнер web упал** - `docker compose ps` покажет статус

## Диагностика

```bash
# Полная диагностика
./CHECK_PHPFPM.sh

# Проверка сети
docker network inspect cbackup-fork_cbackup_network | grep -A 5 "web"

# Проверка подключения из nginx
docker compose exec nginx ping -c 2 web
docker compose exec nginx nc -zv web 9000
```

