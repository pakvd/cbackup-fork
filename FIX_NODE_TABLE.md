# Решение проблемы "The table does not exist: {{%node}}"

## Проблема
Таблица `node` отсутствует в базе данных, хотя должна быть создана при установке.

## Решение 1: Переустановка через установщик (рекомендуется)

### Шаг 1: Удалите install.lock файлы

```bash
# Удалите lock файлы
docker compose exec web rm -f /var/www/html/install.lock /var/www/html/runtime/install.lock
```

### Шаг 2: Откройте установщик в браузере

Откройте: **http://localhost:8080/install/index.php**

### Шаг 3: Выполните миграции

1. На шаге 1 нажмите кнопку **"Run Database Migrations"**
2. Дождитесь завершения создания таблиц
3. Продолжите установку

## Решение 2: Выполнение миграций вручную

Если установщик недоступен, выполните миграции вручную:

```bash
# Проверьте, какие таблицы существуют
docker compose exec db mysql -u cbackup -pcbackup_password cbackup -e "SHOW TABLES;"

# Если таблицы отсутствуют, создайте их через установщик или SQL файл
docker compose exec web php yii migrate --interactive=0
```

## Решение 3: Создание таблицы node вручную через SQL

Если таблица `node` не была создана, выполните SQL напрямую:

```bash
docker compose exec db mysql -u cbackup -pcbackup_password cbackup << 'EOF'
CREATE TABLE IF NOT EXISTS `node` (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `ip` VARCHAR(15) NOT NULL UNIQUE,
    `network_id` INT(11) DEFAULT NULL,
    `credential_id` INT(11) DEFAULT NULL,
    `device_id` INT(11) NOT NULL,
    `auth_template_name` VARCHAR(64) DEFAULT NULL,
    `mac` VARCHAR(12) DEFAULT NULL,
    `created` DATETIME DEFAULT NULL,
    `modified` DATETIME DEFAULT NULL,
    `last_seen` DATETIME DEFAULT NULL,
    `manual` INT(11) DEFAULT 0,
    `hostname` VARCHAR(255) DEFAULT NULL,
    `serial` VARCHAR(45) DEFAULT NULL,
    `prepend_location` VARCHAR(255) DEFAULT NULL,
    `location` VARCHAR(255) DEFAULT NULL,
    `contact` VARCHAR(255) DEFAULT NULL,
    `sys_description` VARCHAR(1024) DEFAULT NULL,
    `protected` INT(11) DEFAULT 0,
    CONSTRAINT `fk_node_network` FOREIGN KEY (`network_id`) REFERENCES `network` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_node_credential` FOREIGN KEY (`credential_id`) REFERENCES `credential` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_node_device` FOREIGN KEY (`device_id`) REFERENCES `device` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    KEY `idx_node_network` (`network_id`),
    KEY `idx_node_credential` (`credential_id`),
    KEY `idx_node_device` (`device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
EOF
```

**Внимание**: Таблица `node` зависит от таблиц `network`, `credential` и `device`. Убедитесь, что они существуют перед созданием `node`.

## Проверка после исправления

```bash
# Проверьте, что таблица создана
docker compose exec db mysql -u cbackup -pcbackup_password cbackup -e "SHOW TABLES LIKE 'node';"

# Проверьте структуру таблицы
docker compose exec db mysql -u cbackup -pcbackup_password cbackup -e "DESCRIBE node;"
```

## Если проблема сохраняется

1. Проверьте логи установщика на наличие ошибок SQL
2. Убедитесь, что все зависимые таблицы созданы (`network`, `credential`, `device`)
3. Проверьте права доступа к базе данных
4. Выполните полную переустановку через установщик

