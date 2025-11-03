#!/bin/bash
# Скрипт для чистой установки cBackup с проверкой всех функций

set -e  # Прервать выполнение при ошибке

echo "========================================="
echo "   Чистая установка cBackup"
echo "========================================="
echo ""

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Функция для вывода успешного сообщения
success() {
    echo -e "${GREEN}✓${NC} $1"
}

# Функция для вывода предупреждения
warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

# Функция для вывода ошибки
error() {
    echo -e "${RED}✗${NC} $1"
}

# Шаг 1: Проверка Docker
echo "Шаг 1: Проверка Docker..."
if command -v docker &> /dev/null && command -v docker compose &> /dev/null; then
    success "Docker и Docker Compose установлены"
    docker --version
    docker compose version
else
    error "Docker или Docker Compose не установлены"
    exit 1
fi
echo ""

# Шаг 2: Очистка предыдущей установки
echo "Шаг 2: Очистка предыдущей установки..."
read -p "Удалить все существующие контейнеры и данные? (y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    warning "Останавливаем и удаляем контейнеры..."
    docker compose down -v 2>/dev/null || true
    success "Контейнеры удалены"
else
    warning "Пропущена очистка (контейнеры могут конфликтовать)"
fi
echo ""

# Шаг 3: Проверка .env файла
echo "Шаг 3: Проверка конфигурации..."
if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        warning ".env файл не найден, создаем из .env.example"
        cp .env.example .env
        warning "⚠️  ВАЖНО: Отредактируйте .env и измените пароли!"
        read -p "Нажмите Enter после редактирования .env файла..."
    else
        error ".env.example не найден!"
        exit 1
    fi
else
    success ".env файл существует"
fi
echo ""

# Шаг 4: Установка прав доступа
echo "Шаг 4: Установка прав доступа..."
if [ -f "set-permissions.sh" ]; then
    chmod +x set-permissions.sh
    ./set-permissions.sh
    success "Права установлены"
else
    warning "set-permissions.sh не найден, пропускаем"
fi
echo ""

# Шаг 5: Сборка и запуск контейнеров
echo "Шаг 5: Сборка и запуск контейнеров..."
echo "Это может занять несколько минут..."
docker compose build --no-cache 2>&1 | grep -E "(Step|Successfully)" || true
docker compose up -d

# Ждем запуска контейнеров
echo "Ожидание запуска контейнеров..."
sleep 10

# Проверка статуса
echo ""
echo "Проверка статуса контейнеров..."
docker compose ps

echo ""
success "Контейнеры запущены"
echo ""

# Шаг 6: Автоматическая установка зависимостей Composer
echo "Шаг 6: Установка зависимостей Composer..."
echo "Ожидание готовности контейнера..."
sleep 10  # Даем время на запуск entrypoint скрипта

# Проверяем, установлены ли зависимости
MAX_RETRIES=3
RETRY_COUNT=0
DEPS_INSTALLED=false

while [ $RETRY_COUNT -lt $MAX_RETRIES ]; do
    if docker compose exec -T web test -f /var/www/html/vendor/autoload.php 2>/dev/null; then
        success "Зависимости Composer установлены (entrypoint скрипт)"
        DEPS_INSTALLED=true
        break
    fi
    
    RETRY_COUNT=$((RETRY_COUNT + 1))
    if [ $RETRY_COUNT -lt $MAX_RETRIES ]; then
        echo "Попытка $RETRY_COUNT/$MAX_RETRIES: ожидание установки зависимостей..."
        sleep 5
    fi
done

# Если не установлены автоматически, устанавливаем вручную
if [ "$DEPS_INSTALLED" = false ]; then
    warning "Зависимости не установлены автоматически, устанавливаем вручную..."
    echo "Настройка Composer plugins..."
    docker compose exec -T web composer config allow-plugins.yiisoft/yii2-composer true 2>&1 || true
    echo "Попытка: composer install..."
    if docker compose exec -T web composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-reqs 2>&1 | tail -15; then
        if docker compose exec -T web test -f /var/www/html/vendor/autoload.php 2>/dev/null; then
            success "Зависимости установлены через composer install"
            DEPS_INSTALLED=true
        fi
    fi
    
    # Если install не сработал, пробуем update
    if [ "$DEPS_INSTALLED" = false ]; then
        echo "Попытка: composer update..."
        docker compose exec -T web composer config allow-plugins.yiisoft/yii2-composer true 2>&1 || true
        if docker compose exec -T web composer update --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-reqs 2>&1 | tail -15; then
            if docker compose exec -T web test -f /var/www/html/vendor/autoload.php 2>/dev/null; then
                success "Зависимости установлены через composer update"
                DEPS_INSTALLED=true
            fi
        fi
    fi
    
    # Финальная проверка
    if [ "$DEPS_INSTALLED" = false ]; then
        error "Не удалось установить зависимости автоматически"
        warning "Проверьте логи: docker compose logs web | grep -i composer"
        warning "Попробуйте вручную:"
        warning "  docker compose exec web composer config allow-plugins.yiisoft/yii2-composer true"
        warning "  docker compose exec web composer update --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-reqs"
    fi
fi
echo ""

# Шаг 7: Проверка application.properties
echo "Шаг 7: Проверка application.properties..."
sleep 2  # Даем время на создание файла

if docker compose exec -T web test -f /var/www/html/bin/application.properties 2>/dev/null; then
    success "Файл application.properties существует"
    
    # Проверка прав доступа
    PERMS=$(docker compose exec -T web stat -c "%a" /var/www/html/bin/application.properties 2>/dev/null || echo "unknown")
    if [ "$PERMS" = "644" ]; then
        success "Права доступа корректны (644)"
    else
        warning "Права доступа: $PERMS (ожидается 644)"
    fi
    
    # Показать содержимое
    echo ""
    echo "Содержимое application.properties:"
    docker compose exec -T web cat /var/www/html/bin/application.properties | head -10
else
    error "Файл application.properties не найден!"
    warning "Попытка создать вручную..."
    docker compose exec web bash -c "
        mkdir -p /var/www/html/bin
        cat > /var/www/html/bin/application.properties << 'EOF'
# SSH Daemon Shell Configuration
sshd.shell.port=8437
sshd.shell.enabled=false
sshd.shell.username=cbadmin
sshd.shell.password=
sshd.shell.host=localhost
sshd.shell.auth.authType=SIMPLE
sshd.shell.prompt.title=cbackup

# Spring Configuration
spring.main.banner-mode=off

# cBackup Configuration
cbackup.scheme=http
cbackup.site=http://web/index.php
cbackup.token=
EOF
        chown www-data:www-data /var/www/html/bin/application.properties
        chmod 644 /var/www/html/bin/application.properties
    "
    success "Файл создан вручную"
fi
echo ""

# Шаг 8: Проверка логов
echo "Шаг 8: Проверка логов (последние 10 строк)..."
docker compose logs --tail=10 | grep -E "(error|Error|ERROR|warning|Warning)" || success "Критических ошибок не найдено"
echo ""

# Финальные инструкции
echo "========================================="
echo "   Установка завершена!"
echo "========================================="
echo ""
echo "Следующие шаги:"
echo ""
if docker compose exec -T web test -f /var/www/html/vendor/autoload.php 2>/dev/null; then
    echo "✓ Зависимости Composer установлены автоматически"
    echo ""
    echo "1. Откройте веб-интерфейс:"
    echo "   http://localhost:8080"
    echo ""
    echo "2. Пройдите веб-установку:"
    echo "   - Настройка базы данных"
    echo "   - Создание администратора"
    echo "   - Завершение установки"
    echo ""
    echo "3. После установки проверьте синхронизацию:"
    echo "   docker compose exec web php yii sync-properties/check"
    echo ""
    echo "4. Проверьте страницу конфигурации:"
    echo "   http://localhost:8080/index.php?r=config"
    echo "   Найдите кнопку 'Синхронизировать application.properties'"
else
    echo "⚠️  Зависимости Composer не установлены автоматически"
    echo ""
    echo "1. Установите зависимости:"
    echo "   ./QUICK_FIX_COMPOSER.sh"
    echo "   или:"
    echo "   make install-composer"
    echo ""
    echo "2. После установки зависимостей откройте веб-интерфейс:"
    echo "   http://localhost:8080"
fi
echo ""
echo "Полезные команды:"
echo "  make logs          - Просмотр логов"
echo "  make status        - Статус контейнеров"
echo "  make restart       - Перезапуск"
echo "  make down          - Остановка"
echo ""
echo "Подробная документация:"
echo "  - INSTALLATION_GUIDE.md - Полное руководство по установке"
echo "  - README.md - Общая документация"
echo "  - FIX_PERMISSIONS.md - Исправление проблем с правами"
echo ""

