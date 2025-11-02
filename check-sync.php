<?php
/**
 * Временный скрипт для проверки синхронизации application.properties
 * Запустить: php check-sync.php
 */

require_once(__DIR__ . '/core/vendor/autoload.php');
require_once(__DIR__ . '/core/vendor/yiisoft/yii2/Yii.php');
require_once(__DIR__ . '/core/helpers/Y.php');

$config = require(__DIR__ . '/core/config/console.php');
$app = new yii\console\Application($config);

use app\models\Config;
use yii\helpers\ArrayHelper;

echo "=== Проверка синхронизации application.properties ===\n\n";

// Получить данные из БД
$data = ArrayHelper::map(Config::find()->asArray()->all(), 'key', 'value');

echo "Значения из базы данных:\n";
foreach (['javaSchedulerPort', 'javaSchedulerUsername', 'javaSchedulerPassword'] as $key) {
    $value = isset($data[$key]) ? $data[$key] : '(не установлено)';
    echo "  {$key} = {$value}\n";
}

echo "\n";

// Проверить файл
$file = Yii::$app->basePath . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'application.properties';

if (file_exists($file)) {
    echo "Файл существует: {$file}\n";
    $props = parse_ini_file($file, false, INI_SCANNER_RAW);
    
    echo "\nЗначения из application.properties:\n";
    foreach (['sshd.shell.port', 'sshd.shell.username', 'sshd.shell.password'] as $key) {
        $value = isset($props[$key]) ? $props[$key] : '(не найдено)';
        echo "  {$key} = {$value}\n";
    }
    
    echo "\nСравнение:\n";
    $mismatches = [];
    
    if (isset($data['javaSchedulerPort'])) {
        $dbVal = (string)$data['javaSchedulerPort'];
        $fileVal = isset($props['sshd.shell.port']) ? (string)$props['sshd.shell.port'] : '';
        if ($dbVal !== $fileVal) {
            echo "  ❌ PORT: БД='{$dbVal}' != Файл='{$fileVal}'\n";
            $mismatches[] = 'port';
        } else {
            echo "  ✅ PORT: БД='{$dbVal}' == Файл='{$fileVal}'\n";
        }
    }
    
    if (isset($data['javaSchedulerUsername'])) {
        $dbVal = (string)$data['javaSchedulerUsername'];
        $fileVal = isset($props['sshd.shell.username']) ? (string)$props['sshd.shell.username'] : '';
        if ($dbVal !== $fileVal) {
            echo "  ❌ USERNAME: БД='{$dbVal}' != Файл='{$fileVal}'\n";
            $mismatches[] = 'username';
        } else {
            echo "  ✅ USERNAME: БД='{$dbVal}' == Файл='{$fileVal}'\n";
        }
    }
    
    if (isset($data['javaSchedulerPassword'])) {
        $dbVal = (string)$data['javaSchedulerPassword'];
        $fileVal = isset($props['sshd.shell.password']) ? (string)$props['sshd.shell.password'] : '';
        if ($dbVal !== $fileVal) {
            echo "  ❌ PASSWORD: БД='{$dbVal}' != Файл='{$fileVal}'\n";
            $mismatches[] = 'password';
        } else {
            echo "  ✅ PASSWORD: БД='{$dbVal}' == Файл='{$fileVal}'\n";
        }
    }
    
    if (empty($mismatches)) {
        echo "\n✅ Все значения совпадают! Синхронизация не требуется.\n";
    } else {
        echo "\n❌ Найдены несоответствия: " . implode(', ', $mismatches) . "\n";
        echo "\nПопытка синхронизации...\n";
        $result = Config::syncApplicationProperties($data);
        if ($result) {
            echo "✅ Синхронизация выполнена успешно!\n";
        } else {
            echo "❌ Ошибка синхронизации. Проверьте права доступа.\n";
        }
    }
} else {
    echo "❌ Файл не существует: {$file}\n";
    echo "Создание файла...\n";
    $result = Config::syncApplicationProperties($data);
    if ($result && file_exists($file)) {
        echo "✅ Файл создан успешно!\n";
    } else {
        echo "❌ Не удалось создать файл. Проверьте права доступа.\n";
    }
}

echo "\n";

