# Исправление проблемы с vendor/autoload.php

## Проблема

При запуске в production контейнеры работают, но при входе на веб-интерфейс установки получаете ошибку:

```
Warning: require(/var/www/html/web/../vendor/autoload.php): Failed to open stream: No such file or directory
Fatal error: Uncaught Error: Failed opening required '/var/www/html/web/../vendor/autoload.php'
```

## Причина

Volume mount `./core:/var/www/html` перезаписывает директорию `vendor` из образа. Если локально нет `vendor`, он не появится в контейнере.

## Решение

### Вариант 1: Использовать named volume для vendor (рекомендуется)

Я уже добавил named volume `web_vendor` в `docker-compose.yml`. Теперь нужно:

1. **Пересоздать контейнеры с новым volume:**

```bash
docker-compose down -v
docker-compose up -d
```

2. **Проверить, что vendor установился:**

```bash
docker-compose exec web ls -la /var/www/html/vendor | head -10
```

3. **Если vendor все еще отсутствует, установить вручную:**

```bash
docker-compose exec web composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-reqs
```

### Вариант 2: Установить зависимости локально

Если вы хотите использовать локальный vendor:

```bash
cd core
composer install --no-dev --optimize-autoloader --no-interaction --no-scripts
```

Затем перезапустите контейнеры:

```bash
docker-compose restart web
```

### Вариант 3: Проверить entrypoint script

Entrypoint script должен автоматически устанавливать зависимости при запуске контейнера. Проверьте логи:

```bash
docker-compose logs web | grep -i vendor
docker-compose logs web | grep -i composer
```

## Проверка

После исправления проверьте:

```bash
# Проверить наличие vendor
docker-compose exec web ls -la /var/www/html/vendor/autoload.php

# Проверить веб-интерфейс
curl http://localhost:8080
```

## Проблема с Schedules

Если Schedules не запускаются, проверьте:

1. **Worker работает:**
```bash
docker-compose ps worker
docker-compose logs worker
```

2. **Worker может связаться с веб-интерфейсом:**
```bash
docker-compose exec worker ping -c 2 web
```

3. **Проверьте application.properties в worker:**
```bash
docker-compose exec worker cat /app/application.properties
```

4. **Проверьте токен в базе данных:**
   - Токен должен совпадать в `application.properties` и в базе данных
   - Проверьте пользователя `javacore` в таблице `user`

5. **Пересоберите worker после изменений:**
```bash
docker-compose build worker
docker-compose up -d worker
```

6. **Проверьте логи worker на ошибки API:**
```bash
docker-compose logs worker | grep -i "api\|error\|exception"
```

### Исправления для Schedules

Я исправил код в `ApiCaller.java`, чтобы он правильно обрабатывал `cbackup.site` с path (например, `web/index.php`). Теперь:
- `cbackup.site=web/index.php` будет правильно разбираться на hostname `web` и path `/index.php`
- URL будет формироваться как `http://web/index.php?r=apiMethod`

Если проблемы остаются, проверьте:
- Worker может достучаться до веб-интерфейса через Docker network
- Токен правильный и совпадает в обоих местах
- Worker пересобран после изменений в коде

## Проблема с 404 при редактировании vendor

Nginx уже блокирует доступ к vendor (строка 74 в `nginx/default.conf`):

```nginx
location ~ ^/(LICENSE|README|CHANGELOG|composer\.json|composer\.lock|\.git|runtime|vendor|config) {
    deny all;
    return 403;
}
```

Это правильное поведение - vendor не должен быть доступен через веб-интерфейс по соображениям безопасности.

Если вы пытаетесь редактировать файлы в vendor через веб-интерфейс, это не поддерживается. Редактируйте файлы локально или через SSH в контейнер.

## Дополнительная информация

- Vendor directory теперь хранится в named volume `web_vendor`
- Это позволяет не перезаписывать vendor при bind mount
- Entrypoint script автоматически устанавливает зависимости при первом запуске
- Если проблемы остаются, проверьте права доступа и логи контейнеров

