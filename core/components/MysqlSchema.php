<?php
/**
 * Custom MySQL Schema class to fix MySQL 8.0 compatibility issues
 * Fixes "Undefined array key 'constraint_name'" error in Yii2 2.0.14
 */

namespace app\components;

use yii\db\mysql\Schema as BaseSchema;
use yii\db\TableSchema;

/**
 * Custom MySQL Schema with MySQL 8.0 compatibility fixes
 * 
 * This class fixes the issue where Yii2 2.0.14 throws "Undefined array key 'constraint_name'"
 * when loading table schemas from MySQL 8.0. The problem occurs because some records
 * in information_schema.KEY_COLUMN_USAGE may not have a CONSTRAINT_NAME for
 * non-foreign-key constraints (like primary keys or regular indexes).
 */
class MysqlSchema extends BaseSchema
{
    /**
     * Returns the metadata for the specified table.
     * Override to check cache for null values and validate table existence.
     * 
     * @param string $name table name
     * @param bool $refresh whether to reload the table schema even if it is found in cache.
     * @return TableSchema|null driver dependent table metadata. Null if the table does not exist.
     */
    public function getTableSchema($name, $refresh = false)
    {
        // If refresh is requested, bypass cache
        if ($refresh) {
            return $this->loadTableSchema($name);
        }
        
        // Check cache first
        if ($this->db->enableSchemaCache && $this->db->schemaCache !== null) {
            $cache = is_string($this->db->schemaCache) ? \Yii::$app->get($this->db->schemaCache) : $this->db->schemaCache;
            if ($cache instanceof \yii\caching\Cache) {
                $cacheKey = $this->getCacheKey('table:' . $name);
                $table = $cache->get($cacheKey);
                
                // cache->get() returns false on cache miss, null if null was cached
                // If we have a cached table schema, return it
                if ($table !== false && $table !== null) {
                    return $table;
                }
                
                // If cache miss (false) or cached null, verify table exists
                // Cache miss means we haven't checked yet, cached null might be wrong
                // Use faster method: try to query table directly instead of information_schema
                try {
                    // Faster check: try to query table directly (LIMIT 1 is very fast)
                    // This avoids slow information_schema queries
                    try {
                        $this->db->createCommand("SELECT 1 FROM `{$name}` LIMIT 1")->queryScalar();
                        // Table exists - clear potentially incorrect null cache and load schema
                        if ($table === null) {
                            $cache->delete($cacheKey);
                        }
                        return $this->loadTableSchema($name);
                    } catch (\yii\db\Exception $tableEx) {
                        // If query fails, table might not exist or have wrong name
                        // Fallback to information_schema check only if it's a "table doesn't exist" error
                        if (strpos($tableEx->getMessage(), "doesn't exist") !== false || 
                            strpos($tableEx->getMessage(), "Unknown table") !== false ||
                            $tableEx->getCode() == '42S02') {
                            // Table doesn't exist
                            return null;
                        }
                        
                        // Other error (permissions, connection, etc) - try information_schema
                        $dbName = $this->db->createCommand('SELECT DATABASE()')->queryScalar();
                        $tableExists = $this->db->createCommand()
                            ->select('COUNT(*)')
                            ->from('information_schema.tables')
                            ->where([
                                'table_schema' => $dbName,
                                'table_name' => $name
                            ])
                            ->queryScalar() > 0;
                        
                        if ($tableExists) {
                            if ($table === null) {
                                $cache->delete($cacheKey);
                            }
                            return $this->loadTableSchema($name);
                        }
                        
                        return null;
                    }
                } catch (\Throwable $e) {
                    // If all checks fail, try to load schema normally
                    // This handles connection issues gracefully
                    return $this->loadTableSchema($name);
                }
            }
        }
        
        // No cache or cache disabled, load schema directly
        return $this->loadTableSchema($name);
    }
    
    /**
     * Loads the metadata for the specified table.
     * 
     * Override to catch constraint_name errors and handle them gracefully.
     * 
     * @param string $name table name
     * @return TableSchema|null driver dependent table metadata. Null if the table does not exist.
     */
    public function loadTableSchema($name)
    {
        // Set up error handler to catch "Undefined array key" errors silently
        $constraintNameError = false;
        
        set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$constraintNameError) {
            if (strpos($errstr, 'constraint_name') !== false || 
                strpos($errstr, 'Undefined array key') !== false) {
                $constraintNameError = true;
                return true; // Suppress error
            }
            return false; // Let other errors through
        }, E_ALL & ~E_DEPRECATED);
        
        try {
            // Try to load table using parent method
            $table = parent::loadTableSchema($name);
            restore_error_handler();
            
            // If we got a table, reload foreign keys safely to fix any constraint_name issues
            if ($table !== null) {
                try {
                    $table->foreignKeys = $this->loadTableForeignKeysSafe($table);
                } catch (\Exception $e) {
                    // If foreign keys loading fails, set empty array and continue
                    $table->foreignKeys = [];
                }
                return $table;
            }
            
            // If parent returned null, ALWAYS check if table actually exists
            // This is crucial because parent may return null due to constraint_name errors
            // even when the table exists
            try {
                $dbName = $this->db->createCommand('SELECT DATABASE()')->queryScalar();
                $tableExists = $this->db->createCommand()
                    ->select('COUNT(*)')
                    ->from('information_schema.tables')
                    ->where([
                        'table_schema' => $dbName,
                        'table_name' => $name
                    ])
                    ->queryScalar() > 0;
                
                if ($tableExists) {
                    // Clear schema cache for this table if enabled, as it may contain incorrect null value
                    try {
                        if ($this->db->enableSchemaCache && $this->db->schemaCache !== null) {
                            $cache = is_string($this->db->schemaCache) ? \Yii::$app->get($this->db->schemaCache) : $this->db->schemaCache;
                            if ($cache instanceof \yii\caching\Cache) {
                                $cacheKey = $this->getCacheKey('table:' . $name);
                                $cache->delete($cacheKey);
                            }
                        }
                    } catch (\Throwable $cacheEx) {
                        // Ignore cache clearing errors
                    }
                    
                    // Table exists but schema loading failed - try again with full error suppression
                    set_error_handler(function() { return true; }, E_ALL);
                    try {
                        $table = parent::loadTableSchema($name);
                    } catch (\Throwable $e) {
                        $table = null;
                    }
                    restore_error_handler();
                    
                    if ($table !== null) {
                        // Load foreign keys safely
                        try {
                            $table->foreignKeys = $this->loadTableForeignKeysSafe($table);
                        } catch (\Exception $fkEx) {
                            $table->foreignKeys = [];
                        }
                        return $table;
                    } else {
                        // Table exists but we can't load schema - try to use findColumns method
                        // This uses Yii2's built-in method which properly handles column loading
                        try {
                            $table = new TableSchema();
                            $table->fullName = $name;
                            $table->name = $name;
                            $table->foreignKeys = [];
                            
                            // Use findColumns method which properly loads column schema
                            $columns = $this->findColumns($table);
                            if (!empty($columns)) {
                                $table->columns = $columns;
                            }
                            
                            // Load primary key
                            try {
                                $table->primaryKey = $this->findPrimaryKey($table);
                            } catch (\Throwable $pkEx) {
                                // Ignore if can't find primary key
                            }
                            
                            // Load foreign keys safely
                            try {
                                $table->foreignKeys = $this->loadTableForeignKeysSafe($table);
                            } catch (\Exception $fkEx) {
                                $table->foreignKeys = [];
                            }
                            
                            return $table;
                        } catch (\Throwable $manualEx) {
                            // If manual loading also fails, return minimal schema
                            // At least return non-null so application knows table exists
                            $table = new TableSchema();
                            $table->fullName = $name;
                            $table->name = $name;
                            $table->foreignKeys = [];
                            $table->columns = [];
                            return $table;
                        }
                    }
                }
            } catch (\Throwable $checkEx) {
                // If check fails, continue to return null
                // This means we couldn't verify if table exists
            }
            
            // Table doesn't exist or we can't load it
            return null;
            
        } catch (\Exception $e) {
            restore_error_handler();
            
            // If error contains constraint_name, try to handle it
            if ($constraintNameError || 
                strpos($e->getMessage(), 'constraint_name') !== false || 
                strpos($e->getMessage(), 'Undefined array key') !== false) {
                
                // Try one more time with error suppression
                try {
                    set_error_handler(function() { return true; }, E_ALL);
                    $table = parent::loadTableSchema($name);
                    restore_error_handler();
                    
                    if ($table !== null) {
                        // Load foreign keys safely
                        try {
                            $table->foreignKeys = $this->loadTableForeignKeysSafe($table);
                        } catch (\Exception $fkEx) {
                            $table->foreignKeys = [];
                        }
                        return $table;
                    }
                } catch (\Throwable $e2) {
                    // If still fails, return null (table might not exist)
                    return null;
                }
            }
            
            throw $e;
        } catch (\Error $e) {
            restore_error_handler();
            
            // PHP 8.0+ throws Error for "Undefined array key", not Exception
            if ($constraintNameError || 
                strpos($e->getMessage(), 'constraint_name') !== false || 
                strpos($e->getMessage(), 'Undefined array key') !== false) {
                
                // Check if table exists first
                try {
                    $dbName = $this->db->createCommand('SELECT DATABASE()')->queryScalar();
                    $tableExists = $this->db->createCommand()
                        ->select('COUNT(*)')
                        ->from('information_schema.tables')
                        ->where([
                            'table_schema' => $dbName,
                            'table_name' => $name
                        ])
                        ->queryScalar() > 0;
                    
                    if ($tableExists) {
                        // Try one more time with error suppression
                        set_error_handler(function() { return true; }, E_ALL);
                        $table = parent::loadTableSchema($name);
                        restore_error_handler();
                        
                        if ($table !== null) {
                            // Load foreign keys safely
                            try {
                                $table->foreignKeys = $this->loadTableForeignKeysSafe($table);
                            } catch (\Exception $fkEx) {
                                $table->foreignKeys = [];
                            }
                            return $table;
                        }
                    }
                } catch (\Throwable $checkEx) {
                    // Ignore check errors
                }
            }
            
            throw $e;
        }
    }
    
    /**
     * Loads constraints for the table.
     * 
     * Override to fix constraint_name issue with MySQL 8.0.
     * The parent method may access constraint_name without checking if it exists.
     * 
     * @param TableSchema $table
     * @return array
     */
    protected function loadTableConstraints($table, $type)
    {
        // Override to safely handle constraint_name errors
        // Use error handler to catch "Undefined array key" warnings
        $errorOccurred = false;
        
        set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$errorOccurred) {
            if (strpos($errstr, 'constraint_name') !== false || 
                strpos($errstr, 'Undefined array key') !== false) {
                $errorOccurred = true;
                return true; // Suppress error
            }
            return false;
        }, E_WARNING | E_NOTICE);
        
        try {
            $result = parent::loadTableConstraints($table, $type);
            restore_error_handler();
            
            // If there was an error, return empty array
            if ($errorOccurred) {
                return [];
            }
            
            return $result;
        } catch (\Exception $e) {
            restore_error_handler();
            
            // If error contains constraint_name, return empty array
            if ($errorOccurred || 
                strpos($e->getMessage(), 'constraint_name') !== false || 
                strpos($e->getMessage(), 'Undefined array key') !== false) {
                return [];
            }
            throw $e;
        }
    }
    
    /**
     * Loads foreign keys for the table.
     * 
     * Override to fix constraint_name issue with MySQL 8.0.
     * Uses safe method directly to avoid constraint_name errors.
     * 
     * @param TableSchema $table
     * @return array
     */
    protected function loadTableForeignKeys($table)
    {
        // Use safe method directly to avoid constraint_name errors in MySQL 8.0
        // The parent method may access constraint_name without checking if it exists
        return $this->loadTableForeignKeysSafe($table);
    }
    
    /**
     * Safely load foreign keys with proper constraint_name handling
     * 
     * @param TableSchema $table
     * @return array
     */
    protected function loadTableForeignKeysSafe($table)
    {
        $sql = <<<SQL
SELECT
    kcu.CONSTRAINT_NAME,
    kcu.COLUMN_NAME,
    kcu.REFERENCED_TABLE_NAME,
    kcu.REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE kcu
WHERE kcu.TABLE_SCHEMA = DATABASE()
    AND kcu.TABLE_NAME = :tableName
    AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
    AND kcu.CONSTRAINT_NAME IS NOT NULL
    AND kcu.CONSTRAINT_NAME != ''
SQL;

        try {
            $rows = $this->db->createCommand($sql, [':tableName' => $table->name])->queryAll();
            $foreignKeys = [];
            
            // Group by constraint to handle composite foreign keys
            $constraints = [];
            
            foreach ($rows as $row) {
                // Safely access constraint_name with proper checking
                // Use both uppercase and lowercase keys as MySQL may return either
                $constraintName = $row['CONSTRAINT_NAME'] ?? $row['constraint_name'] ?? null;
                
                if (empty($constraintName)) {
                    continue; // Skip rows without constraint_name
                }
                
                $columnName = $row['COLUMN_NAME'] ?? $row['column_name'] ?? null;
                $referencedTable = $row['REFERENCED_TABLE_NAME'] ?? $row['referenced_table_name'] ?? null;
                $referencedColumn = $row['REFERENCED_COLUMN_NAME'] ?? $row['referenced_column_name'] ?? null;
                
                if ($columnName && $referencedTable && $referencedColumn) {
                    // Group columns by constraint name for composite foreign keys
                    if (!isset($constraints[$constraintName])) {
                        $constraints[$constraintName] = [
                            'table' => $referencedTable,
                            'columns' => []
                        ];
                    }
                    $constraints[$constraintName]['columns'][$referencedColumn] = $columnName;
                }
            }
            
            // Convert to Yii2 format: [referencedTable, referencedColumn => localColumn, ...]
            foreach ($constraints as $constraint) {
                $fk = [$constraint['table']];
                foreach ($constraint['columns'] as $refCol => $localCol) {
                    $fk[$refCol] = $localCol;
                }
                $foreignKeys[] = $fk;
            }
            
            return $foreignKeys;
        } catch (\Exception $e) {
            // If query fails, return empty array
            return [];
        }
    }
}

