<?php
/**
 * DB – schlanker PDO-Wrapper für SQLite (Standard) und MySQL/MariaDB.
 *
 * Alle Zeitstempel werden als Text 'Y-m-d H:i:s' gespeichert; damit
 * funktionieren Vergleiche und Sortierung in beiden Datenbanken gleich.
 * Alle Geldbeträge sind Ganzzahlen in Cent – Fließkomma auf Preisen erzeugt
 * genau die Rundungsfehler, die der Kunde auf der Rechnung sieht.
 */
final class DB
{
    private static ?PDO $pdo = null;
    private static string $driver = 'sqlite';
    private static int $tiefe = 0;

    public static function init(?array $cfg = null): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $cfg    = $cfg ?? (array) Config::get('db', []);
        $driver = (string) ($cfg['driver'] ?? 'sqlite');
        self::$driver = $driver === 'mysql' ? 'mysql' : 'sqlite';

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        if (self::$driver === 'mysql') {
            $host = (string) ($cfg['host'] ?? 'localhost');
            $port = (int) ($cfg['port'] ?? 3306);
            $name = (string) ($cfg['name'] ?? '');
            $dsn  = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
            self::$pdo = new PDO($dsn, (string) ($cfg['user'] ?? ''), (string) ($cfg['pass'] ?? ''), $options);
            self::$pdo->exec("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
        } else {
            $path = (string) ($cfg['path'] ?? (SHOP_ROOT . '/data/shop.sqlite'));
            $dir  = dirname($path);
            if (!is_dir($dir)) {
                @mkdir($dir, 0750, true);
            }
            self::$pdo = new PDO('sqlite:' . $path, null, null, $options);
            self::$pdo->exec('PRAGMA journal_mode = WAL');
            self::$pdo->exec('PRAGMA busy_timeout = 10000');
            self::$pdo->exec('PRAGMA foreign_keys = ON');
        }
        return self::$pdo;
    }

    public static function pdo(): PDO
    {
        return self::$pdo ?? self::init();
    }

    public static function driver(): string
    {
        return self::$driver;
    }

    public static function isSqlite(): bool
    {
        return self::$driver === 'sqlite';
    }

    /* ------------------------------------------------------------ Abfragen */

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public static function row(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** Erste Spalte der ersten Zeile. */
    public static function value(string $sql, array $params = [], $default = null)
    {
        $value = self::run($sql, $params)->fetchColumn();
        return $value === false ? $default : $value;
    }

    /** @return array<int,mixed> Erste Spalte aller Zeilen. */
    public static function column(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    /* ---------------------------------------------------------- Schreiben */

    /** Baut INSERT aus einem Feld-Array und liefert die neue ID. */
    public static function insert(string $table, array $data): int
    {
        $cols   = array_keys($data);
        $marks  = array_map(static fn($c) => ':' . $c, $cols);
        $sql    = 'INSERT INTO ' . $table . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $marks) . ')';
        self::run($sql, self::bindable($data));
        return (int) self::pdo()->lastInsertId();
    }

    /** Baut UPDATE … WHERE id = ? und liefert die Zahl geänderter Zeilen. */
    public static function update(string $table, int $id, array $data, string $idColumn = 'id'): int
    {
        if ($data === []) {
            return 0;
        }
        $sets = [];
        foreach (array_keys($data) as $col) {
            $sets[] = $col . ' = :' . $col;
        }
        $params       = self::bindable($data);
        $params['id'] = $id;
        $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $sets) . ' WHERE ' . $idColumn . ' = :id';
        return self::run($sql, $params)->rowCount();
    }

    public static function delete(string $table, int $id, string $idColumn = 'id'): int
    {
        return self::run('DELETE FROM ' . $table . ' WHERE ' . $idColumn . ' = ?', [$id])->rowCount();
    }

    /**
     * PDO bindet nur Skalare. Booleans und Arrays kommen im Anwendungscode
     * aber ständig vor – deshalb wandeln wir sie an genau einer Stelle um
     * statt an hundert Aufrufstellen.
     */
    private static function bindable(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 1 : 0;
            } elseif (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $out[$key] = $value;
        }
        return $out;
    }

    /* ------------------------------------------------------- Transaktionen */

    /**
     * Führt $fn in einer Transaktion aus. Verschachtelte Aufrufe teilen sich
     * die äußere Transaktion, damit sich Bibliotheken gefahrlos gegenseitig
     * aufrufen können.
     *
     * @template T
     * @param callable():T $fn
     * @return T
     */
    public static function transaction(callable $fn)
    {
        if (self::$tiefe > 0) {
            return $fn();
        }
        self::pdo()->beginTransaction();
        self::$tiefe++;
        try {
            $result = $fn();
            self::pdo()->commit();
            return $result;
        } catch (Throwable $e) {
            try {
                self::pdo()->rollBack();
            } catch (Throwable $ignored) {
                // Ein fehlgeschlagener Rollback darf den Originalfehler nicht verdecken.
            }
            throw $e;
        } finally {
            self::$tiefe--;
        }
    }

    /** Setzt die Verbindung zurück – nur für Tests. */
    public static function reset(): void
    {
        self::$pdo   = null;
        self::$tiefe = 0;
    }
}
