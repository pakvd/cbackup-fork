# Устранение ошибки 502 Bad Gateway

## Проблема
Nginx не может подключиться к PHP-FPM, возвращается ошибка 502.

## Возможные причины и решения

### 1. PHP-FPM еще не запустился

**Проблема:** Nginx пытается подключиться до того, как PHP-FPM готов.

**Решение:** Убедитесь, что контейнер `web` полностью запущен:

```bash
# Проверить статус
docker compose ps

# Проверить логи PHP-FPM
docker compose logs web | grep -i "fpm is running"

# Должно быть:
# [04-Nov-2025 00:44:12] NOTICE: fpm is running, pid 1
```

Если PHP-FPM не запустился, проверьте логи:
```bash
docker compose logs web
```

### 2. PHP-FPM не может подключиться к базе данных

**Проблема:** PHP-FPM падает при попытке подключиться к БД.

**Решение:** Проверьте, что контейнер `db` запущен и доступен:

```bash
# Проверить статус БД
docker compose ps db

# Проверить логи БД
docker compose logs db | tail -20

# Проверить подключение из контейнера web
docker compose exec web ping -c 2 db
```

### 3. Проблемы с зависимостями Composer

**Проблема:** PHP-FPM запускается, но не может загрузить приложение из-за отсутствия `vendor/autoload.php`.

**Решение:** Убедитесь, что зависимости установлены:

```bash
# Проверить наличие vendor
docker compose exec web test -f /var/www/html/vendor/autoload.php && echo "OK" || echo "ERROR"

# Если отсутствует, установить
make install-composer
```

### 4. Проблемы с правами доступа

**Проблема:** PHP-FPM не может читать файлы приложения.

**Решение:** Проверить права доступа:

```bash
# Проверить права на основные директории
docker compose exec web ls -la /var/www/html/ | head -10

# Исправить права (если нужно)
make set-permissions
```

### 5. Ошибки в конфигурации PHP/приложения

**Проблема:** PHP-FPM падает из-за ошибок в конфигурации или коде.

**Решение:** Проверить логи PHP-FPM:

```bash
# Полные логи PHP-FPM
docker compose logs web | grep -i "error\|fatal\|warning"

# Проверить конфигурацию PHP
docker compose exec web php -v
docker compose exec web php -m | grep -E "(pdo|mysqli|redis)"
```

### 6. Проверка сетевого подключения

**Проблема:** Nginx не может достичь PHP-FPM через Docker сеть.

**Решение:** Проверить подключение:

```bash
# Из контейнера nginx попробовать подключиться к web
docker compose exec nginx wget -O- http://web:9000 2>&1 | head -5

# Или проверить DNS резолвинг
docker compose exec nginx nslookup web
```

### 7. PHP-FPM слушает на неправильном адресе (127.0.0.1 вместо 0.0.0.0)

**Проблема:** PHP-FPM слушает на `127.0.0.1:9000`, что не позволяет подключиться из другого контейнера (nginx).

**Решение:** Entrypoint скрипт автоматически исправляет это при запуске контейнера. Если проблема сохраняется:

```bash
# Проверить текущую конфигурацию
docker compose exec web grep "^listen = " /usr/local/etc/php-fpm.d/www.conf

# Если показывает 127.0.0.1:9000, исправить вручную
docker compose exec web sed -i "s|^listen = 127.0.0.1:9000|listen = 0.0.0.0:9000|" /usr/local/etc/php-fpm.d/www.conf

# Перезапустить контейнер
docker compose restart web

# Или использовать скрипт
./FIX_PHPFPM_LISTEN.sh
```

**Примечание:** В Docker сети контейнеры могут подключаться друг к другу, но PHP-FPM должен слушать на `0.0.0.0:9000`, а не на `127.0.0.1:9000`, чтобы принимать соединения из других контейнеров.

### 8. Проверка конфигурации Nginx

**Проблема:** Неправильная конфигурация `fastcgi_pass`.

**Решение:** Убедитесь, что в `nginx/default.conf` указано:

```nginx
fastcgi_pass web:9000;
```

И проверить, что контейнеры в одной сети (в `docker-compose.yml` оба должны быть в `cbackup_network`).

## Диагностика

### Быстрая проверка всех компонентов:

```bash
# 1. Статус всех контейнеров
docker compose ps

# 2. Логи web контейнера (PHP-FPM)
docker compose logs web --tail=50

# 3. Логи nginx
docker compose logs nginx --tail=50

# 4. Проверка подключения
docker compose exec web php -r "echo 'PHP works';"
docker compose exec web test -f /var/www/html/vendor/autoload.php && echo "Composer OK" || echo "Composer FAIL"

# 5. Проверка сети
docker network inspect cbackup-fork_cbackup_network | grep -A 5 "web\|nginx"
```

## Типичное решение

В большинстве случаев проблема решается перезапуском контейнеров:

```bash
# Перезапустить все контейнеры
docker compose restart

# Или перезапустить только web
docker compose restart web

# Подождать несколько секунд и проверить
sleep 5
docker compose ps
```

## Если ничего не помогает

1. Полностью пересобрать контейнеры:
```bash
docker compose down
docker compose build --no-cache
docker compose up -d
```

2. Проверить, что все зависимости установлены:
```bash
make install-composer
```

3. Проверить логи полностью:
```bash
docker compose logs > all_logs.txt
cat all_logs.txt | grep -i error
```

