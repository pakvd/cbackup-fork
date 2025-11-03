#!/bin/bash
# Быстрое исправление проблемы с зависимостями Composer

echo "========================================="
echo "   Установка зависимостей Composer"
echo "========================================="
echo ""

# Цвета
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

# Проверка, запущен ли контейнер
if ! docker compose ps | grep -q "cbackup_web.*Up"; then
    echo -e "${RED}✗${NC} Контейнер web не запущен!"
    echo "Запустите контейнеры: docker compose up -d"
    exit 1
fi

echo "Проверка наличия vendor директории..."
if docker compose exec -T web test -f /var/www/html/vendor/autoload.php 2>/dev/null; then
    echo -e "${GREEN}✓${NC} Зависимости уже установлены"
    exit 0
fi

echo -e "${YELLOW}⚠${NC} Зависимости не найдены, устанавливаем..."
echo ""

# Настройка разрешения плагинов
echo "Настройка Composer plugins..."
docker compose exec -T web composer config allow-plugins.yiisoft/yii2-composer true 2>&1 || true

# Попытка 1: composer install
echo "Попытка 1: composer install..."
if docker compose exec -T web composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-reqs 2>&1 | tail -20; then
    if docker compose exec -T web test -f /var/www/html/vendor/autoload.php 2>/dev/null; then
        echo ""
        echo -e "${GREEN}✓${NC} Зависимости установлены успешно!"
        exit 0
    fi
fi

echo ""
echo -e "${YELLOW}⚠${NC} composer install не сработал, пробуем composer update..."
echo ""

# Попытка 2: composer update
echo "Попытка 2: composer update..."
docker compose exec -T web composer config allow-plugins.yiisoft/yii2-composer true 2>&1 || true
if docker compose exec -T web composer update --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-reqs 2>&1 | tail -20; then
    if docker compose exec -T web test -f /var/www/html/vendor/autoload.php 2>/dev/null; then
        echo ""
        echo -e "${GREEN}✓${NC} Зависимости установлены успешно!"
        exit 0
    fi
fi

echo ""
echo -e "${RED}✗${NC} Не удалось установить зависимости автоматически"
echo ""
echo "Попробуйте вручную:"
echo "  docker compose exec web composer config allow-plugins.yiisoft/yii2-composer true"
echo "  docker compose exec web composer update --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-reqs"
echo ""
echo "Или посмотрите полные логи:"
echo "  docker compose logs web | grep -i composer"
echo ""
exit 1

