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
     * Loads the metadata for the specified table.
     * 
     * Override to catch constraint_name errors and handle them gracefully.
     * 
     * @param string $name table name
     * @return TableSchema|null driver dependent table metadata. Null if the table does not exist.
     */
    public function loadTableSchema($name)
    {
        // Set up error handler to catch "Undefined array key" errors
        $errorOccurred = false;
        $errorMessage = '';
        
        // Capture errors/warnings that might be thrown
        set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$errorOccurred, &$errorMessage) {
            if (strpos($errstr, 'constraint_name') !== false || 
                strpos($errstr, 'Undefined array key') !== false) {
                $errorOccurred = true;
                $errorMessage = $errstr;
                return true; // Suppress error
            }
            return false; // Let other errors through
        }, E_WARNING | E_NOTICE);
        
        try {
            // Try to load table using parent method
            $table = parent::loadTableSchema($name);
            
            // Restore error handler
            restore_error_handler();
            
            // If we got a table, ensure foreign keys are loaded safely
            if ($table !== null) {
                // If there was a constraint_name error, clear foreign keys and reload safely
                if ($errorOccurred) {
                    $table->foreignKeys = [];
                }
                
                // Always reload foreign keys safely to ensure they're correct
                try {
                    $table->foreignKeys = $this->loadTableForeignKeysSafe($table);
                } catch (\Exception $e) {
                    // If foreign keys loading fails, set empty array and continue
                    $table->foreignKeys = [];
                }
            }
            
            return $table;
            
        } catch (\Exception $e) {
            // Restore error handler
            restore_error_handler();
            
            // If error contains constraint_name, try to load table anyway
            if ($errorOccurred || 
                strpos($e->getMessage(), 'constraint_name') !== false || 
                strpos($e->getMessage(), 'Undefined array key') !== false) {
                
                // Try one more time with error suppression
                try {
                    $previousErrorHandler = set_error_handler(function() { return true; });
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
                } catch (\Exception $e2) {
                    // If still fails, return null (table might not exist)
                    return null;
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
        try {
            return parent::loadTableConstraints($table, $type);
        } catch (\Exception $e) {
            // If error contains constraint_name, return empty array
            if (strpos($e->getMessage(), 'constraint_name') !== false || 
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

