<?php

namespace App\Config;

/**
 * PDO subclass that auto-translates MariaDB SQL to PostgreSQL on the fly.
 *
 * Used only when DB_DRIVER=pgsql. Translation handles:
 * - Backtick identifiers `name` → "name"
 * - LIMIT N OFFSET M (already same)
 * - IFNULL → COALESCE (kept as IFNULL via compat fn)
 * - LEFT(s,n), RIGHT(s,n) — already same in PG
 * - DATE_FORMAT, FROM_UNIXTIME, UNIX_TIMESTAMP, etc — handled via compat fns
 */
class PgCompatPDO extends \PDO
{
    public function __construct(string $dsn, ?string $user = null, ?string $pass = null, ?array $options = null)
    {
        parent::__construct($dsn, $user, $pass, $options ?? []);
    }

    public static function translate(string $sql): string
    {
        // 1) backtick → double-quote.
        //    AS 절 alias 는 원본 케이스 유지 (PHP 가 결과 array key 를 그대로 읽기 때문).
        //    그 외(table/column ref) 는 lowercase (PG 스키마는 lowercase 임).
        //    구분: 직전이 'AS'(단어경계) 면 alias.
        $sql = preg_replace_callback(
            '/(\bAS\s+)?`([^`]+)`/i',
            static function (array $m): string {
                $isAlias = !empty($m[1]);
                $name    = $isAlias ? $m[2] : mb_strtolower($m[2], 'UTF-8');
                return ($m[1] ?? '') . '"' . $name . '"';
            },
            $sql,
        );

        // 2) SHOW COLUMNS FROM "tbl" → information_schema 변환 (Field/Type/Null/Key/Default/Extra 컬럼명 유지)
        $sql = preg_replace_callback(
            '/^\s*SHOW\s+COLUMNS\s+FROM\s+"([^"]+)"\s*;?\s*$/i',
            static fn(array $m): string =>
                "SELECT column_name AS \"Field\", data_type AS \"Type\", "
                . "is_nullable AS \"Null\", '' AS \"Key\", column_default AS \"Default\", "
                . "'' AS \"Extra\" FROM information_schema.columns "
                . "WHERE table_schema='public' AND table_name='" . $m[1] . "' ORDER BY ordinal_position",
            $sql,
        );

        // 3) MySQL specific keyword conversions
        $sql = preg_replace('/\bINSERT\s+IGNORE\s+INTO\b/i', 'INSERT INTO', $sql);
        // 4) REGEXP / NOT REGEXP / RLIKE → PG ~ / !~ (case-sensitive)
        $sql = preg_replace('/\bNOT\s+REGEXP\b/i', '!~', $sql);
        $sql = preg_replace('/\bREGEXP\b/i', '~', $sql);
        $sql = preg_replace('/\bRLIKE\b/i', '~', $sql);
        // 5) MySQL CAST(... AS UNSIGNED|SIGNED) → bigint
        $sql = preg_replace('/\bAS\s+UNSIGNED(\s+INTEGER)?\b/i', 'AS bigint', $sql);
        $sql = preg_replace('/\bAS\s+SIGNED(\s+INTEGER)?\b/i', 'AS bigint', $sql);
        // 6) MySQL CHAR(N) cast → varchar(N), CHAR cast (no length) → text
        $sql = preg_replace('/\bAS\s+CHAR\s*\((\d+)\)/i', 'AS varchar($1)', $sql);
        $sql = preg_replace('/\bAS\s+CHAR\b(?!\s*\()/i', 'AS text', $sql);
        // 7) MySQL DATETIME cast → timestamp
        $sql = preg_replace('/\bAS\s+DATETIME\b/i', 'AS timestamp', $sql);
        // 8) IF(cond, a, b) → CASE WHEN cond THEN a ELSE b END  (top-level commas, balanced parens, string-aware)
        $sql = self::translateIfToCase($sql);
        // 9) JSON_OBJECT(...) → jsonb_build_object(...)
        //    JSON_ARRAYAGG(...) → jsonb_agg(...)
        $sql = preg_replace('/\bJSON_OBJECT\s*\(/i', 'jsonb_build_object(', $sql);
        $sql = preg_replace('/\bJSON_ARRAYAGG\s*\(/i', 'jsonb_agg(', $sql);
        $sql = preg_replace('/\bJSON_ARRAY\s*\(/i', 'jsonb_build_array(', $sql);
        // JSON_VALID(x) → (x::jsonb IS NOT NULL) — best-effort, may fail at runtime; keep as call to public.json_valid
        return $sql;
    }

    private static function translateIfToCase(string $sql): string
    {
        $out = '';
        $n = strlen($sql);
        $i = 0;
        while ($i < $n) {
            // Look for the next IF( token
            if (preg_match('/\bif\s*\(/iA', substr($sql, $i), $m)) {
                $matchLen = strlen($m[0]);
                // The match ended just past '('
                $start = $i + $matchLen; // position right after '('
                // Now scan forward, splitting at top-level commas, until matching ')'
                $depth = 1;
                $j = $start;
                $inStr = false;
                $args = [];
                $cur = '';
                while ($j < $n && $depth > 0) {
                    $ch = $sql[$j];
                    if ($inStr) {
                        $cur .= $ch;
                        if ($ch === '\\' && $j + 1 < $n) { $cur .= $sql[$j+1]; $j += 2; continue; }
                        if ($ch === "'" && $j + 1 < $n && $sql[$j+1] === "'") { $cur .= "'"; $j += 2; continue; }
                        if ($ch === "'") $inStr = false;
                        $j++; continue;
                    }
                    if ($ch === "'") { $inStr = true; $cur .= $ch; $j++; continue; }
                    if ($ch === '(') { $depth++; $cur .= $ch; $j++; continue; }
                    if ($ch === ')') {
                        $depth--;
                        if ($depth === 0) { break; }
                        $cur .= $ch; $j++; continue;
                    }
                    if ($ch === ',' && $depth === 1) { $args[] = $cur; $cur = ''; $j++; continue; }
                    $cur .= $ch; $j++;
                }
                if ($depth !== 0) { // unmatched - bail out, write as-is
                    $out .= substr($sql, $i);
                    return $out;
                }
                $args[] = $cur;
                if (count($args) === 3) {
                    // Recurse on each arg
                    $a = self::translateIfToCase(trim($args[0]));
                    $b = self::translateIfToCase(trim($args[1]));
                    $c = self::translateIfToCase(trim($args[2]));
                    $out .= "(CASE WHEN {$a} THEN {$b} ELSE {$c} END)";
                } else {
                    // Not the 3-arg IF — write as-is
                    $out .= substr($sql, $i, ($j - $i + 1));
                }
                $i = $j + 1;
                continue;
            }
            $out .= $sql[$i];
            $i++;
        }
        return $out;
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        return parent::prepare(self::translate($query), $options);
    }

    public function exec(string $statement): int|false
    {
        return parent::exec(self::translate($statement));
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
    {
        $translated = self::translate($query);
        if ($fetchMode === null) {
            return parent::query($translated);
        }
        return parent::query($translated, $fetchMode, ...$fetchModeArgs);
    }
}
