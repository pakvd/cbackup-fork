# Production Deployment Guide

## ✅ Все исправления применены и готовы к продакшн

### Основные изменения для продакшн

#### 1. **Opcache включен по умолчанию**
- Opcache оптимизирован для продакшн с настройками производительности
- Можно отключить для разработки через `ENABLE_OPCACHE=false` в `.env`
- Настройки: 256MB памяти, 20000 файлов, валидация отключена для максимальной производительности

#### 2. **PHP-FPM оптимизирован**
- `pm.max_children = 20` (увеличено с 5)
- `pm.start_servers = 5`
- `pm.min_spare_servers = 3`
- `pm.max_spare_servers = 10`
- `request_terminate_timeout = 60s`

#### 3. **Обновлены зависимости**
- ✅ HTMLPurifier обновлен до v4.19.0 (совместим с PHP 8.1)
- ✅ Все зависимости актуальны
- ✅ Composer autoloader настроен правильно

#### 4. **Исправлены критические ошибки**
- ✅ Исправлена ошибка "Unable to determine the request URI" в MaintenanceBehavior
- ✅ Исправлена ошибка "Class 'yii\db\mysql\Schema' not found"
- ✅ Исправлена ошибка "Undefined array key 'constraint_name'" через кастомный MysqlSchema
- ✅ Исправлена ошибка 500 при сохранении настроек профиля (HTMLPurifier)

### Переменные окружения

Создайте файл `.env` на основе `.env.example`:

```bash
cp .env.example .env
```

**Важно для продакшн:**
1. Измените все пароли на сильные:
   ```env
   MYSQL_PASSWORD=your_very_secure_password_here
   MYSQL_ROOT_PASSWORD=your_very_secure_root_password_here
   ```
2. Установите правильный часовой пояс:
   ```env
   PHP_TIMEZONE=Europe/Moscow  # или ваш часовой пояс
   ```
3. Opcache включен по умолчанию (для отключения установите `ENABLE_OPCACHE=false`)

### Настройка для продакшн

#### 1. Изменение портов (если нужно)

```env
NGINX_PORT=80          # Используйте 80 для HTTP или 443 для HTTPS
NGINX_SSL_PORT=443     # Для HTTPS
MYSQL_PORT=3306        # Ограничьте доступ через firewall
```

#### 2. HTTPS (рекомендуется)

1. Поместите SSL сертификаты в `nginx/ssl/`
2. Обновите `nginx/default.conf` для поддержки HTTPS
3. Настройте редирект с HTTP на HTTPS

#### 3. Ограничение доступа к БД

Рекомендуется не публиковать порт MySQL напрямую или ограничить доступ через firewall:

```yaml
# В docker compose.yml уберите или закомментируйте:
# ports:
#   - "${MYSQL_PORT:-3306}:3306"
```

База данных будет доступна только внутри Docker сети.

### Мониторинг и логирование

#### Просмотр логов

```bash
# Все логи
docker compose logs -f

# Конкретный сервис
docker compose logs -f web
docker compose logs -f nginx
docker compose logs -f db
docker compose logs -f worker

# Последние 100 строк
docker compose logs --tail=100 web
```

#### Проверка статуса

```bash
# Статус всех сервисов
docker compose ps

# Использование ресурсов
docker stats

# Логи PHP-приложения (внутри контейнера)
docker compose exec web tail -f /var/www/html/runtime/logs/app.log
```

### Резервное копирование

#### Бэкап базы данных

```bash
# Создать бэкап
docker compose exec db mysqldump -u root -p${MYSQL_ROOT_PASSWORD} cbackup > backup_$(date +%Y%m%d_%H%M%S).sql

# Восстановить из бэкапа
docker compose exec -T db mysql -u root -p${MYSQL_ROOT_PASSWORD} cbackup < backup_20241202_120000.sql
```

#### Бэкап файлов приложения

```bash
# Бэкап runtime директории
docker compose exec web tar -czf runtime_backup.tar.gz /var/www/html/runtime

# Копирование с контейнера
docker cp cbackup_web:/var/www/html/runtime_backup.tar.gz ./
```

### Обновление в продакшн

```bash
# 1. Создайте бэкап БД
docker compose exec db mysqldump -u root -p${MYSQL_ROOT_PASSWORD} cbackup > backup_before_update.sql

# 2. Остановите контейнеры
docker compose down

# 3. Обновите код (если используете git)
git pull origin main

# 4. Пересоберите образы
docker compose build --no-cache

# 5. Запустите контейнеры
docker compose up -d

# 6. Проверьте статус
docker compose ps
docker compose logs --tail=50 web

# 7. При необходимости примените миграции
docker compose exec web php yii migrate
```

### Безопасность

#### ✅ Выполнено:

1. ✅ Устаревшие зависимости обновлены
2. ✅ HTMLPurifier обновлен (исправлены уязвимости PHP 8.1)
3. ✅ Opcache настроен безопасно
4. ✅ PHP-FPM настроен с правильными лимитами
5. ✅ Права доступа настроены правильно

#### ⚠️ Требует внимания:

1. **Измените все пароли** перед деплоем
2. **Настройте HTTPS** для продакшн
3. **Ограничьте доступ** к MySQL порту через firewall
4. **Регулярно обновляйте** зависимости: `composer update --no-dev`
5. **Настройте мониторинг** и алерты
6. **Регулярные бэкапы** базы данных

### Производительность

#### Оптимизации включены:

- ✅ Opcache включен и оптимизирован
- ✅ PHP-FPM пул увеличен (20 процессов)
- ✅ Composer autoloader оптимизирован
- ✅ Schema cache включен для БД

#### Дополнительные рекомендации:

1. Используйте SSD для Docker volumes
2. Настройте мониторинг производительности
3. При высокой нагрузке увеличьте `pm.max_children` в `docker-entrypoint.sh`
4. Рассмотрите использование Redis для кеширования

### Известные исправления

1. **MaintenanceBehavior** - исправлена ошибка с определением URI
2. **MysqlSchema** - кастомный класс для совместимости с MySQL 8.0
3. **HTMLPurifier** - обновлен до версии 4.19.0
4. **Composer autoload** - настроен для правильной загрузки классов
5. **Docker entrypoint** - автоматическая установка зависимостей

### Контакты и поддержка

- Официальный сайт: http://cbackup.me
- Документация: https://github.com/cBackup/main
- Исходный код: https://github.com/cBackup/main

---

**Версия**: 2024.12 (Production Ready)
**Последнее обновление**: Ноябрь 2025

