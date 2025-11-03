#!/bin/bash
# Скрипт для проверки состояния PHP-FPM

echo "========================================="
echo "   Проверка PHP-FPM"
echo "========================================="
echo ""

# Проверка статуса контейнера
echo "1. Статус контейнера web:"
docker compose ps web
echo ""

# Проверка процессов PHP-FPM
echo "2. Процессы PHP-FPM в контейнере:"
docker compose exec -T web ps aux | grep -i php-fpm || echo "⚠️  PHP-FPM процессы не найдены"
echo ""

# Проверка прослушивания порта 9000
echo "3. Прослушивание порта 9000:"
docker compose exec -T web netstat -tlnp 2>/dev/null | grep 9000 || \
docker compose exec -T web ss -tlnp 2>/dev/null | grep 9000 || \
echo "⚠️  Порт 9000 не прослушивается"
echo ""

# Проверка конфигурации PHP-FPM
echo "4. Конфигурация PHP-FPM listen:"
docker compose exec -T web grep "^listen = " /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || echo "⚠️  Конфигурация не найдена"
echo ""

# Последние логи PHP-FPM
echo "5. Последние 20 строк логов PHP-FPM:"
docker compose logs web --tail=20 | grep -E "(PHP-FPM|fpm|Starting|Started|ERROR|error|Fatal)" || docker compose logs web --tail=20
echo ""

# Проверка подключения из nginx контейнера
echo "6. Проверка подключения из nginx к web:9000:"
docker compose exec -T nginx wget -O- http://web:9000 2>&1 | head -5 || \
docker compose exec -T nginx nc -zv web 9000 2>&1 || \
echo "⚠️  Не удалось проверить подключение"
echo ""

# Проверка сетевого подключения
echo "7. Проверка DNS резолвинга web в nginx:"
docker compose exec -T nginx nslookup web 2>&1 || echo "⚠️  nslookup не доступен"
echo ""

# Проверка, что контейнеры в одной сети
echo "8. Проверка Docker сети:"
docker network inspect cbackup-fork_cbackup_network 2>/dev/null | grep -A 3 "web\|nginx" || \
docker network inspect cbackup_network 2>/dev/null | grep -A 3 "web\|nginx" || \
echo "⚠️  Не удалось проверить сеть"
echo ""

echo "========================================="
echo "   Рекомендации:"
echo "========================================="
echo ""
echo "Если PHP-FPM не запущен:"
echo "  docker compose restart web"
echo ""
echo "Если проблемы с сетью:"
echo "  docker compose down"
echo "  docker compose up -d"
echo ""
echo "Проверить логи полностью:"
echo "  docker compose logs web"
echo ""

