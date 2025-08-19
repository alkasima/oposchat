<?php

require_once 'vendor/autoload.php';

// Load Laravel app
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Get database path
$dbPath = database_path('database.sqlite');

if (!file_exists($dbPath)) {
    echo "Database file not found at: $dbPath\n";
    exit(1);
}

// Create SQLite connection
$pdo = new PDO("sqlite:$dbPath");

// Get all table names
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);

$dump = "-- SQLite Database Dump\n";
$dump .= "-- Generated on " . date('Y-m-d H:i:s') . "\n\n";

// Export schema and data for each table
foreach ($tables as $table) {
    // Get CREATE TABLE statement
    $createStmt = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='$table'")->fetchColumn();
    $dump .= "$createStmt;\n\n";
    
    // Get all data from table
    $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($rows)) {
        foreach ($rows as $row) {
            $columns = array_keys($row);
            $values = array_map(function($value) use ($pdo) {
                return $value === null ? 'NULL' : $pdo->quote($value);
            }, array_values($row));
            
            $dump .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";
        }
        $dump .= "\n";
    }
}

// Write dump to file
file_put_contents('local_database_export.sql', $dump);
echo "Database exported to local_database_export.sql\n";
echo "File size: " . number_format(filesize('local_database_export.sql')) . " bytes\n";