<?php

namespace App\Services\Backup;

use App\Exceptions\BackupException;
use PDO;

/**
 * محرك تفريغ قاعدة البيانات (MySQL) إلى ملف SQL نصي:
 * - اتصال مستقل غير مُخزّن (streaming) دون تعطيل اتصال التطبيق.
 * - لقطة قراءة متسقة (REPEATABLE READ + WITH CONSISTENT SNAPSHOT) عند توفرها.
 * - بيانات SQL آمنة (quote/hex للـ Blob) مع دعم كامل لـ utf8mb4.
 * - إرجاع ملخص موثوق (sha256 + عدادات السجلات والحجم).
 */
class DatabaseDumpService
{
    public const ROWS_PER_INSERT = 300;

    /**
     * إنشاء التفريغ الكامل إلى المسار المطلوب.
     *
     * @throws BackupException
     */
    public function dump(string $targetPath): array
    {
        $connection = (string) config('database.default');
        $cfg = (array) config('database.connections.'.$connection);

        if (($cfg['driver'] ?? null) !== 'mysql') {
            throw new BackupException('النسخ الاحتياطي يدعم حالياً قواعد بيانات MySQL فقط.');
        }

        $pdo = $this->openConnection($cfg);
        $pdo->exec('SET NAMES utf8mb4');

        $consistent = $this->startSnapshot($pdo);
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $pdo->exec("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");

        $database = (string) ($cfg['database'] ?? '');
        $tableList = $this->tableList($pdo);
        $meta = $this->tableMeta($pdo);

        $targetDir = dirname($targetPath);
        if (! is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }
        if (! is_writable($targetDir)) {
            throw new BackupException('مجلد تفريغ قاعدة البيانات غير قابل للكتابة: '.$targetDir);
        }

        $handle = fopen($targetPath, 'wb');
        if ($handle === false) {
            throw new BackupException('تعذر فتح ملف التفريغ للكتابة: '.$targetPath);
        }

        $hashContext = hash_init('sha256');
        $sqlBytes = 0;

        $writer = function (string $sql) use ($handle, $hashContext, &$sqlBytes) {
            $written = fwrite($handle, $sql);
            $length = strlen($sql);
            $sqlBytes += $written === false ? $length : $written;
            hash_update($hashContext, $sql);
        };

        $header = "-- ========================================================\n"
            ."-- NetSales Database Dump (Automated Enterprise Backup)\n"
            .'-- Generation Time: '.date('Y-m-d H:i:s')."\n"
            .'-- Host: '.($cfg['host'] ?? '127.0.0.1').' Database: '.$database."\n"
            ."-- ========================================================\n\n"
            ."/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n"
            ."/*!40101 SET NAMES utf8mb4 */;\n"
            ."/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n"
            ."/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n\n";

        $writer($header);

        $tablesSummary = [];
        $viewsSummary = [];
        $totalRows = 0;

        foreach ($tableList as $name) {
            $type = $meta[$name]['type'] ?? 'BASE TABLE';
            if ($type === 'VIEW') {
                $viewsSummary[] = $name;
                $this->dumpView($pdo, $name, $writer);
            } else {
                $rowCount = $this->dumpTable($pdo, $name, $writer);
                $tablesSummary[$name] = [
                    'rows' => $rowCount,
                    'engine' => $meta[$name]['engine'] ?? 'InnoDB',
                ];
                $totalRows += $rowCount;
            }
        }

        $footer = "\n/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;\n"
            ."/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;\n"
            ."/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n"
            ."-- Dump completed successfully at ".date('Y-m-d H:i:s')."\n";

        $writer($footer);
        fclose($handle);

        $sha256 = hash_final($hashContext);
        $realBytes = is_file($targetPath) ? (int) filesize($targetPath) : $sqlBytes;

        return [
            'database' => $database,
            'driver' => 'mysql',
            'consistent_snapshot' => $consistent,
            'tables_count' => count($tablesSummary),
            'views_count' => count($viewsSummary),
            'total_rows' => $totalRows,
            'file_size_bytes' => $realBytes,
            'file_sha256' => $sha256,
            'tables' => $tablesSummary,
            'views' => $viewsSummary,
        ];
    }

    protected function openConnection(array $cfg): PDO
    {
        $host = $cfg['host'] ?? '127.0.0.1';
        $port = $cfg['port'] ?? 3306;
        $db = $cfg['database'] ?? '';
        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
        ];

        return new PDO($dsn, (string) ($cfg['username'] ?? ''), (string) ($cfg['password'] ?? ''), $options);
    }

    protected function startSnapshot(PDO $pdo): bool
    {
        try {
            $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function tableList(PDO $pdo): array
    {
        $stmt = $pdo->query('SHOW FULL TABLES');
        $tables = [];
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        return $tables;
    }

    protected function tableMeta(PDO $pdo): array
    {
        $stmt = $pdo->query('SHOW TABLE STATUS');
        $meta = [];
        while ($row = $stmt->fetch()) {
            $name = $row['Name'] ?? null;
            if ($name !== null) {
                $meta[$name] = [
                    'engine' => $row['Engine'] ?? null,
                    'type' => empty($row['Engine']) ? 'VIEW' : 'BASE TABLE',
                ];
            }
        }

        return $meta;
    }

    protected function dumpView(PDO $pdo, string $view, callable $writer): void
    {
        $writer("\n--\n-- Structure for view `{$view}`\n--\n\n");
        $writer("DROP VIEW IF EXISTS `{$view}`;\n");
        $stmt = $pdo->query("SHOW CREATE VIEW `{$view}`");
        $row = $stmt->fetch();
        if (! empty($row['Create View'])) {
            $writer($row['Create View'].";\n");
        }
    }

    protected function dumpTable(PDO $pdo, string $table, callable $writer): int
    {
        $writer("\n--\n-- Table structure and data for table `{$table}`\n--\n\n");
        $writer("DROP TABLE IF EXISTS `{$table}`;\n");

        $stmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
        $row = $stmt->fetch();
        if (! empty($row['Create Table'])) {
            $writer($row['Create Table'].";\n\n");
        }

        $colStmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        $columns = [];
        $binaryCols = [];
        while ($col = $colStmt->fetch()) {
            $colName = $col['Field'];
            $columns[] = $colName;
            $type = strtolower($col['Type'] ?? '');
            if (str_contains($type, 'blob') || str_contains($type, 'binary')) {
                $binaryCols[$colName] = true;
            }
        }

        if (empty($columns)) {
            return 0;
        }

        $quotedCols = array_map(fn ($c) => "`{$c}`", $columns);
        $insertPrefix = 'INSERT INTO `'.$table.'` ('.implode(', ', $quotedCols).') VALUES ';

        $dataStmt = $pdo->query("SELECT * FROM `{$table}`");
        $batch = [];
        $count = 0;

        while ($record = $dataStmt->fetch(PDO::FETCH_ASSOC)) {
            $count++;
            $values = [];
            foreach ($columns as $c) {
                $val = $record[$c] ?? null;
                if ($val === null) {
                    $values[] = 'NULL';
                } elseif (isset($binaryCols[$c])) {
                    $values[] = '0x'.bin2hex($val);
                } else {
                    $values[] = $pdo->quote((string) $val);
                }
            }
            $batch[] = '('.implode(', ', $values).')';

            if (count($batch) >= self::ROWS_PER_INSERT) {
                $writer($insertPrefix.implode(",\n", $batch).";\n");
                $batch = [];
            }
        }

        if (! empty($batch)) {
            $writer($insertPrefix.implode(",\n", $batch).";\n");
        }

        return $count;
    }
}
