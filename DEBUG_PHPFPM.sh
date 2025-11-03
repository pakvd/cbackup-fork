#!/bin/bash
# Детальная диагностика PHP-FPM

echo "========================================="
echo "   Детальная диагностика PHP-FPM"
echo "========================================="
echo ""

echo "1. Проверка конфигурационных файлов PHP-FPM:"
docker compose exec -T web find /usr/local/etc -name "*fpm*.conf" -type f 2>/dev/null | head -5
echo ""

echo "2. Содержимое www.conf:"
docker compose exec -T web cat /usr/local/etc/php-fpm.d/www.conf | grep -E "(^listen|^\[www\]|^user|^group)" | head -10
echo ""

echo "3. Проверка всех директив listen в конфигурации:"
docker compose exec -T web grep -r "listen" /usr/local/etc/php-fpm* 2>/dev/null | grep -v "^;" | head -10
echo ""

echo "4. Проверка, какой конфигурационный файл использует PHP-FPM:"
docker compose exec -T web php-fpm -i 2>/dev/null | grep -i "fpm\|config" | head -10 || \
docker compose exec -T web ps aux | grep php-fpm | grep -v grep | head -1
echo ""

echo "5. Проверка процессов PHP-FPM и их аргументов:"
docker compose exec -T web ps auxww | grep php-fpm | head -3
echo ""

echo "6. Проверка прослушивания портов (все порты):"
docker compose exec -T web netstat -tlnp 2>/dev/null || docker compose exec -T web ss -tlnp 2>/dev/null
echo ""

echo "7. Попытка подключения к порту 9000 изнутри контейнера:"
docker compose exec -T web bash -c "timeout 2 bash -c '</dev/tcp/127.0.0.1/9000' 2>&1 && echo '✓ Порт 9000 доступен на 127.0.0.1'" || \
docker compose exec -T web bash -c "timeout 2 bash -c '</dev/tcp/0.0.0.0/9000' 2>&1 && echo '✓ Порт 9000 доступен на 0.0.0.0'" || \
echo "⚠️  Порт 9000 недоступен"
echo ""

echo "8. Проверка логов PHP-FPM (последние 30 строк):"
docker compose logs web --tail=30 | grep -E "(PHP-FPM|fpm|listen|error|Error|ERROR|Starting)" || docker compose logs web --tail=30
echo ""

echo "9. Проверка переменных окружения PHP_FPM_LISTEN:"
docker compose exec -T web env | grep PHP_FPM_LISTEN || echo "⚠️  PHP_FPM_LISTEN не установлена"
echo ""

echo "10. Проверка entrypoint скрипта (последние строки с listen):"
docker compose logs web 2>&1 | grep -i "listen" | tail -10
echo ""

echo "========================================="
echo "   Рекомендации"
echo "========================================="
echo ""
echo "Если конфигурация показывает 0.0.0.0:9000, но порт не прослушивается:"
echo "  1. Проверьте, что PHP-FPM действительно запущен"
echo "  2. Проверьте логи на ошибки: docker compose logs web"
echo "  3. Попробуйте пересобрать образ: docker compose build --no-cache web"
echo ""

