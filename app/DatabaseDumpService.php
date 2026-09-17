<?php

namespace App;

use PDO;
use RuntimeException;

/**
 * Creates a portable SQL backup of the current MySQL database.
 *
 * The dump is written to a temporary file so callers can send it as a download
 * and remove it immediately afterwards instead of retaining sensitive data on
 * the web server.
 */
final class DatabaseDumpService
{
    private const MAX_IMPORT_BYTES = 200 * 1024 * 1024;

    public function __construct(private PDO $connection)
    {
    }

    public function createTemporaryDump(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ndc-db-');
        if ($path === false) {
            throw new RuntimeException('Could not create a temporary database backup file.');
        }

        $stream = @fopen($path, 'wb');
        if ($stream === false) {
            @unlink($path);
            throw new RuntimeException('Could not open the temporary database backup file.');
        }

        try {
            $this->write($stream, '-- NDC Identity System database backup' . PHP_EOL);
            $this->write($stream, '-- Created: ' . date('c') . PHP_EOL . PHP_EOL);
            $this->write($stream, 'SET NAMES utf8mb4;' . PHP_EOL);
            $this->write($stream, 'SET FOREIGN_KEY_CHECKS = 0;' . PHP_EOL . PHP_EOL);

            foreach ($this->listTables() as $table) {
                $this->dumpTable($stream, $table);
            }

            $this->write($stream, 'SET FOREIGN_KEY_CHECKS = 1;' . PHP_EOL);
        } catch (\Throwable $exception) {
            fclose($stream);
            @unlink($path);
            throw $exception;
        }

        fclose($stream);

        return $path;
    }

    /**
     * Imports an uploaded MySQL SQL dump into the currently configured database.
     *
     * @param array<string, mixed> $file
     */
    public function importUploadedDump(array $file): int
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('The database backup upload failed.');
        }

        $filename = (string) ($file['name'] ?? '');
        if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'sql') {
            throw new RuntimeException('Database imports must be SQL (.sql) files.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size < 1) {
            throw new RuntimeException('The database backup file is empty.');
        }
        if ($size > self::MAX_IMPORT_BYTES) {
            throw new RuntimeException('Database backups must not exceed 200 MB.');
        }

        $path = (string) ($file['tmp_name'] ?? '');
        if ($path === '' || !is_uploaded_file($path)) {
            throw new RuntimeException('The uploaded database backup could not be read.');
        }

        return $this->importSqlFile($path);
    }

    public function importSqlFile(string $path): int
    {
        $stream = @fopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException('The database backup file could not be opened.');
        }

        $statement = '';
        $delimiter = ';';
        $quote = null;
        $escaped = false;
        $inBlockComment = false;
        $inLineComment = false;
        $executed = 0;
        $isFirstLine = true;

        try {
            while (($line = fgets($stream)) !== false) {
                if ($isFirstLine) {
                    $line = preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line;
                    $isFirstLine = false;
                }

                if ($quote === null && !$inBlockComment && trim($statement) === ''
                    && preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $line, $matches) === 1) {
                    $delimiter = $matches[1];
                    continue;
                }

                $length = strlen($line);
                for ($index = 0; $index < $length; $index++) {
                    $character = $line[$index];
                    $next = $index + 1 < $length ? $line[$index + 1] : '';

                    if ($inLineComment) {
                        if ($character === "\n" || $character === "\r") {
                            $inLineComment = false;
                            $statement .= $character;
                        }
                        continue;
                    }

                    if ($inBlockComment) {
                        if ($character === '*' && $next === '/') {
                            $inBlockComment = false;
                            $statement .= ' ';
                            $index++;
                        }
                        continue;
                    }

                    if ($quote !== null) {
                        $statement .= $character;
                        if ($escaped) {
                            $escaped = false;
                            continue;
                        }
                        if ($character === '\\' && $quote !== '`') {
                            $escaped = true;
                            continue;
                        }
                        if ($character === $quote) {
                            if ($next === $quote) {
                                $statement .= $next;
                                $index++;
                            } else {
                                $quote = null;
                            }
                        }
                        continue;
                    }

                    if ($character === '-' && $next === '-'
                        && ($index + 2 >= $length || ctype_space($line[$index + 2]))) {
                        $inLineComment = true;
                        $index++;
                        continue;
                    }
                    if ($character === '#') {
                        $inLineComment = true;
                        continue;
                    }
                    if ($character === '/' && $next === '*') {
                        $inBlockComment = true;
                        $index++;
                        continue;
                    }
                    if ($character === "'" || $character === '"' || $character === '`') {
                        $quote = $character;
                        $statement .= $character;
                        continue;
                    }
                    if ($delimiter !== '' && substr($line, $index, strlen($delimiter)) === $delimiter) {
                        $this->executeStatement($statement, $executed);
                        $statement = '';
                        $index += strlen($delimiter) - 1;
                        continue;
                    }

                    $statement .= $character;
                }
            }

            if ($quote !== null || $inBlockComment) {
                throw new RuntimeException('The SQL file ends inside a quoted value or comment.');
            }

            $this->executeStatement($statement, $executed);
        } finally {
            fclose($stream);
        }

        return $executed;
    }

    /**
     * @return array<int, string>
     */
    private function listTables(): array
    {
        $statement = $this->connection->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        $tables = [];

        while (($row = $statement->fetch(PDO::FETCH_NUM)) !== false) {
            if (isset($row[0]) && is_string($row[0])) {
                $tables[] = $row[0];
            }
        }

        return $tables;
    }

    /**
     * @param resource $stream
     */
    private function dumpTable($stream, string $table): void
    {
        $quotedTable = $this->quoteIdentifier($table);
        $createStatement = $this->connection->query('SHOW CREATE TABLE ' . $quotedTable);
        $createRow = $createStatement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($createRow)) {
            throw new RuntimeException('Could not read the structure of table ' . $table . '.');
        }

        $createSql = null;
        foreach ($createRow as $column => $value) {
            if (str_starts_with(strtolower((string) $column), 'create table')) {
                $createSql = (string) $value;
                break;
            }
        }
        if ($createSql === null) {
            $values = array_values($createRow);
            $createSql = isset($values[1]) ? (string) $values[1] : null;
        }
        if ($createSql === null || $createSql === '') {
            throw new RuntimeException('Could not create a schema backup for table ' . $table . '.');
        }

        $this->write($stream, '-- --------------------------------------------------------' . PHP_EOL);
        $this->write($stream, '-- Table: ' . $table . PHP_EOL);
        $this->write($stream, 'DROP TABLE IF EXISTS ' . $quotedTable . ';' . PHP_EOL);
        $this->write($stream, rtrim($createSql, "; \t\r\n") . ';' . PHP_EOL . PHP_EOL);

        $columns = $this->exportableColumns($table);
        if ($columns === []) {
            return;
        }

        $quotedColumns = implode(', ', array_map(fn (string $column): string => $this->quoteIdentifier($column), $columns));
        $dataStatement = $this->connection->query('SELECT ' . $quotedColumns . ' FROM ' . $quotedTable);
        $rows = [];

        while (($row = $dataStatement->fetch(PDO::FETCH_NUM)) !== false) {
            $rows[] = '(' . implode(', ', array_map(fn (mixed $value): string => $this->quoteValue($value), $row)) . ')';

            if (count($rows) === 100) {
                $this->writeInsertRows($stream, $quotedTable, $quotedColumns, $rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            $this->writeInsertRows($stream, $quotedTable, $quotedColumns, $rows);
        }

        $this->write($stream, PHP_EOL);
    }

    /**
     * @return array<int, string>
     */
    private function exportableColumns(string $table): array
    {
        $statement = $this->connection->query('SHOW COLUMNS FROM ' . $this->quoteIdentifier($table));
        $columns = [];

        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $name = (string) ($row['Field'] ?? '');
            $extra = strtoupper((string) ($row['Extra'] ?? ''));
            if ($name !== '' && !str_contains($extra, 'GENERATED')) {
                $columns[] = $name;
            }
        }

        return $columns;
    }

    /**
     * @param resource $stream
     * @param array<int, string> $rows
     */
    private function writeInsertRows($stream, string $quotedTable, string $quotedColumns, array $rows): void
    {
        $this->write(
            $stream,
            'INSERT INTO ' . $quotedTable . ' (' . $quotedColumns . ') VALUES' . PHP_EOL
            . implode(',' . PHP_EOL, $rows) . ';' . PHP_EOL
        );
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function quoteValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        $quoted = $this->connection->quote((string) $value);
        if ($quoted === false) {
            throw new RuntimeException('Could not encode a database value for backup.');
        }

        return $quoted;
    }

    private function executeStatement(string $statement, int &$executed): void
    {
        $statement = trim($statement);
        if ($statement === '') {
            return;
        }

        $this->connection->exec($statement);
        $executed++;
    }

    /**
     * @param resource $stream
     */
    private function write($stream, string $content): void
    {
        if (fwrite($stream, $content) === false) {
            throw new RuntimeException('Could not write the database backup file.');
        }
    }
}
