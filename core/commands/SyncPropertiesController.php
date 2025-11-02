<?php
/**
 * Console command to sync application.properties
 * Usage: php yii sync-properties/check
 */

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\helpers\ArrayHelper;
use app\models\Config;

/**
 * Sync properties console controller
 */
class SyncPropertiesController extends Controller
{
    /**
     * Check and sync application.properties
     */
    public function actionCheck()
    {
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
        $dir  = dirname($file);

        echo "Директория: {$dir}\n";
        echo "  Существует: " . (is_dir($dir) ? 'да' : 'нет') . "\n";
        echo "  Доступна для записи: " . (is_writable($dir) ? 'да' : 'нет') . "\n";
        if (is_dir($dir)) {
            echo "  Права: " . substr(sprintf('%o', fileperms($dir)), -4) . "\n";
        }

        echo "\nФайл: {$file}\n";
        echo "  Существует: " . (file_exists($file) ? 'да' : 'нет') . "\n";
        if (file_exists($file)) {
            echo "  Доступен для записи: " . (is_writable($file) ? 'да' : 'нет') . "\n";
            echo "  Права: " . substr(sprintf('%o', fileperms($file)), -4) . "\n";
        }

        if (file_exists($file)) {
            $props = @parse_ini_file($file, false, INI_SCANNER_RAW);

            if (!empty($props)) {
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
                    echo "\n✅ Все значения совпадают!\n";
                    return self::EXIT_CODE_NORMAL;
                } else {
                    echo "\n❌ Найдены несоответствия: " . implode(', ', $mismatches) . "\n";
                }
            }
        } else {
            echo "\n❌ Файл не существует\n";
        }

        echo "\nПопытка синхронизации...\n";
        
        // Попробовать исправить права
        if (is_dir($dir) && !is_writable($dir)) {
            @chmod($dir, 0755);
            echo "Права на директорию установлены: 755\n";
        }
        
        if (file_exists($file) && !is_writable($file)) {
            @chmod($file, 0644);
            echo "Права на файл установлены: 644\n";
        }
        
        $result = Config::syncApplicationProperties($data);
        
        if ($result && file_exists($file)) {
            echo "✅ Синхронизация выполнена успешно!\n";
            return self::EXIT_CODE_NORMAL;
        } else {
            echo "❌ Ошибка синхронизации.\n";
            echo "Требуется установить права вручную:\n";
            echo "  chmod 755 " . $dir . "\n";
            echo "  chmod 644 " . $file . "\n";
            return self::EXIT_CODE_ERROR;
        }
    }
}

