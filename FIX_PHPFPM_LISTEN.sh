#!/bin/bash
# Скрипт для исправления PHP-FPM listen адреса в запущенном контейнере

echo "========================================="
echo "   Исправление PHP-FPM listen адреса"
echo "========================================="
echo ""

# Проверка, запущен ли контейнер
if ! docker compose ps | grep -q "cbackup_web.*Up"; then
    echo "✗ Контейнер web не запущен!"
    echo "Запустите контейнеры: docker compose up -d"
    exit 1
fi

echo "1. Текущая конфигурация PHP-FPM:"
docker compose exec -T web grep "^listen = " /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || echo "⚠️  Конфигурация не найдена"
echo ""

echo "2. Изменение listen на 0.0.0.0:9000..."
docker compose exec -T web sed -i "s|^listen = 127.0.0.1:9000|listen = 0.0.0.0:9000|" /usr/local/etc/php-fpm.d/www.conf 2>/dev/null
docker compose exec -T web sed -i "s|^listen = /run/php/php-fpm.sock|listen = 0.0.0.0:9000|" /usr/local/etc/php-fpm.d/www.conf 2>/dev/null
docker compose exec -T web sed -i "s|^listen = /var/run/php/php-fpm.sock|listen = 0.0.0.0:9000|" /usr/local/etc/php-fpm.d/www.conf 2>/dev/null

echo "3. Проверка изменений:"
docker compose exec -T web grep "^listen = " /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || echo "⚠️  Конфигурация не найдена"
echo ""

echo "4. Перезапуск PHP-FPM внутри контейнера..."
# Отправить сигнал USR2 для плавной перезагрузки
docker compose exec -T web pkill -USR2 php-fpm 2>/dev/null || echo "⚠️  Не удалось перезагрузить через USR2"

# Если не получилось, перезапустить контейнер
echo "5. Перезапуск контейнера web..."
docker compose restart web

echo ""
echo "Подождите 10 секунд для запуска PHP-FPM..."
sleep 10

echo ""
echo "6. Проверка новой конфигурации:"
docker compose exec -T web grep "^listen = " /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || echo "⚠️  Конфигурация не найдена"

echo ""
echo "7. Проверка прослушивания порта:"
docker compose exec -T web netstat -tlnp 2>/dev/null | grep 9000 || \
docker compose exec -T web ss -tlnp 2>/dev/null | grep 9000 || \
echo "⚠️  Порт не прослушивается (может потребоваться еще несколько секунд)"

echo ""
echo "========================================="
echo "   Готово!"
echo "========================================="
echo ""
echo "Если порт все еще не прослушивается на 0.0.0.0:9000,"
echo "попробуйте полностью пересобрать образ:"
echo "  docker compose build --no-cache web"
echo "  docker compose up -d web"
echo ""

