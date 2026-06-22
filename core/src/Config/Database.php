<?php

namespace App\Config;

class Database
{
    private static ?\PDO $instance = null;

    private function __construct() {}

    public static function getInstance(): \PDO
    {
        if (self::$instance === null) {
            $driver  = strtolower($_ENV['DB_DRIVER'] ?? 'mysql');
            $host    = $_ENV['DB_HOST']    ?? '127.0.0.1';
            $port    = $_ENV['DB_PORT']    ?? ($driver === 'pgsql' ? '5432' : '3306');
            $name    = $_ENV['DB_NAME']    ?? 'speedmis_v7';
            $user    = $_ENV['DB_USER']    ?? '';
            $pass    = $_ENV['DB_PASS']    ?? '';
            $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

            if ($driver === 'pgsql') {
                $dsn = "pgsql:host={$host};port={$port};dbname={$name}";
            } elseif ($driver === 'sqlsrv') {
                $dsn = "sqlsrv:Server={$host},{$port};Database={$name};TrustServerCertificate=true;Encrypt=no";
            } else {
                $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
            }

            $emulate = ($_ENV['DB_EMULATE_PREPARES'] ?? '0') === '1';
            $opts = [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => $emulate,
            ];
            if ($driver === 'pgsql') {
                self::$instance = new PgCompatPDO($dsn, $user, $pass, $opts);
            } elseif ($driver === 'sqlsrv') {
                // sqlsrv 는 ATTR_EMULATE_PREPARES 미지원 — opts 에서 제거
                $sqlOpts = $opts;
                unset($sqlOpts[\PDO::ATTR_EMULATE_PREPARES]);
                // 숫자 컬럼을 string 대신 int/float native PHP 타입으로 반환 (kimgo 와 동일 형식)
                if (defined('PDO::SQLSRV_ATTR_FETCHES_NUMERIC_TYPE')) {
                    $sqlOpts[\PDO::SQLSRV_ATTR_FETCHES_NUMERIC_TYPE] = true;
                }
                // 한글(NVARCHAR) 파라미터/결과가 ?로 손상되지 않도록 UTF-8 인코딩 강제
                if (defined('PDO::SQLSRV_ATTR_ENCODING') && defined('PDO::SQLSRV_ENCODING_UTF8')) {
                    $sqlOpts[\PDO::SQLSRV_ATTR_ENCODING] = \PDO::SQLSRV_ENCODING_UTF8;
                }
                self::$instance = new MssqlCompatPDO($dsn, $user, $pass, $sqlOpts);
            } else {
                self::$instance = new \PDO($dsn, $user, $pass, $opts);
            }

            if ($driver === 'pgsql') {
                self::$instance->exec("SET client_encoding TO 'UTF8'");
                self::$instance->exec("SET search_path TO public");
            } elseif ($driver === 'sqlsrv') {
                // MSSQL 은 default UTF-8 (Latin1_General_100_CI_AS_SC_UTF8) 또는 NVARCHAR. 별도 설정 없음.
            } else {
                self::$instance->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            }
        }
        return self::$instance;
    }

    public static function isPg(): bool
    {
        return strtolower($_ENV['DB_DRIVER'] ?? 'mysql') === 'pgsql';
    }

    public static function isMssql(): bool
    {
        return strtolower($_ENV['DB_DRIVER'] ?? 'mysql') === 'sqlsrv';
    }

    public static function reset(): void
    {
        self::$instance = null;
    }
}
