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
     * Override to ensure foreign keys are loaded safely.
     * 
     * @param string $name table name
     * @return TableSchema|null driver dependent table metadata. Null if the table does not exist.
     */
    public function loadTableSchema($name)
    {
        try {
            $table = parent::loadTableSchema($name);
            if ($table !== null) {
                // Ensure foreign keys are loaded safely
                try {
                    $table->foreignKeys = $this->loadTableForeignKeysSafe($table);
                } catch (\Exception $e) {
                    // If foreign keys loading fails, set empty array
                    $table->foreignKeys = [];
                }
            }
            return $table;
        } catch (\Exception $e) {
            // If error contains constraint_name, try to work around it
            if (strpos($e->getMessage(), 'constraint_name') !== false || 
                strpos($e->getMessage(), 'Undefined array key') !== false) {
                // Try loading without foreign keys first (cache will be cleared automatically if needed)
                return $this->loadTableSchemaSafe($name);
            }
            throw $e;
        }
    }
    
    /**
     * Safely load table schema without constraint_name errors
     * 
     * @param string $name table name
     * @return TableSchema|null
     */
    protected function loadTableSchemaSafe($name)
    {
        try {
            $table = parent::loadTableSchema($name);
            if ($table !== null) {
                // Load foreign keys safely
                $table->foreignKeys = $this->loadTableForeignKeysSafe($table);
            }
            return $table;
        } catch (\Exception $e) {
            // If still fails, return null
            return null;
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

