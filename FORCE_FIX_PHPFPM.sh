#!/bin/bash
# Агрессивное исправление PHP-FPM listen адреса

echo "========================================="
echo "   Принудительное исправление PHP-FPM"
echo "========================================="
echo ""

# Остановить контейнер
echo "1. Остановка контейнера web..."
docker compose stop web

# Изменить конфигурацию в остановленном контейнере (если возможно)
echo "2. Изменение конфигурации..."
docker compose run --rm web sed -i "s|^listen = 127.0.0.1:9000|listen = 0.0.0.0:9000|" /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || \
docker compose run --rm web sed -i "s|^listen = /run/php/php-fpm.sock|listen = 0.0.0.0:9000|" /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || \
docker compose run --rm web sed -i "s|^listen = /var/run/php/php-fpm.sock|listen = 0.0.0.0:9000|" /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || \
echo "⚠️  Не удалось изменить конфигурацию в остановленном контейнере"

# Запустить контейнер
echo "3. Запуск контейнера web..."
docker compose up -d web

echo ""
echo "4. Ожидание запуска PHP-FPM (15 секунд)..."
sleep 15

echo ""
echo "5. Проверка конфигурации:"
docker compose exec -T web grep "^listen = " /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || echo "⚠️  Конфигурация не найдена"

echo ""
echo "6. Проверка процессов PHP-FPM:"
docker compose exec -T web ps aux | grep php-fpm | head -3 || echo "⚠️  PHP-FPM не запущен"

echo ""
echo "7. Проверка прослушивания порта:"
docker compose exec -T web netstat -tlnp 2>/dev/null | grep 9000 || \
docker compose exec -T web ss -tlnp 2>/dev/null | grep 9000 || \
echo "⚠️  Порт не прослушивается"

echo ""
echo "8. Логи PHP-FPM (последние 10 строк):"
docker compose logs web --tail=10 | grep -E "(PHP-FPM|fpm|listen|Starting)" || docker compose logs web --tail=10

echo ""
echo "========================================="
echo "   Если порт все еще не прослушивается:"
echo "========================================="
echo ""
echo "Пересоберите образ:"
echo "  docker compose build --no-cache web"
echo "  docker compose up -d web"
echo ""
echo "Или проверьте логи:"
echo "  docker compose logs web"
echo ""

