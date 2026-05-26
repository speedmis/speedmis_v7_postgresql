<?php

namespace App\Config;

/**
 * PDO subclass that translates MariaDB SQL → MSSQL on the fly.
 *
 * Used when DB_DRIVER=sqlsrv. Translation handles:
 * - Backtick identifiers `name` → [name]
 * - LIMIT N OFFSET M → OFFSET M ROWS FETCH NEXT N ROWS ONLY
 * - IFNULL → ISNULL  (CONCAT remains; '+' supported by MSSQL too)
 * - REGEXP / RLIKE → LIKE (best-effort fallback) or PATINDEX
 * - IF(cond, a, b) → CASE WHEN cond THEN a ELSE b END
 * - SHOW COLUMNS FROM tbl → INFORMATION_SCHEMA.COLUMNS query (Field/Type/Null/Key/Default/Extra aliases)
 * - DATE_FORMAT(d, '%Y-%m-%d') → CONVERT(varchar, d, 23) — partial
 * - Identifier case preserved on AS aliases (matching PHP $row[key]); other refs lowercased
 *   (the MariaDB→MSSQL schema converter normalizes table/column names to lowercase already.)
 */
class MssqlCompatPDO extends \PDO
{
    public function __construct(string $dsn, ?string $user = null, ?string $pass = null, ?array $options = null)
    {
        parent::__construct($dsn, $user, $pass, $options ?? []);
    }

    public static function translate(string $sql): string
    {
        // 1) backtick → [bracket]. AS-alias 의 케이스는 보존, 그 외 lowercase.
        $sql = preg_replace_callback(
            '/(\bAS\s+)?`([^`]+)`/i',
            static function (array $m): string {
                $isAlias = !empty($m[1]);
                $name    = $isAlias ? $m[2] : mb_strtolower($m[2], 'UTF-8');
                return ($m[1] ?? '') . '[' . $name . ']';
            },
            $sql,
        );

        // 2) SHOW COLUMNS FROM [tbl] → INFORMATION_SCHEMA.COLUMNS
        $sql = preg_replace_callback(
            '/^\s*SHOW\s+COLUMNS\s+FROM\s+\[([^\]]+)\]\s*;?\s*$/i',
            static fn(array $m): string =>
                "SELECT COLUMN_NAME AS [Field], DATA_TYPE AS [Type], "
                . "IS_NULLABLE AS [Null], '' AS [Key], COLUMN_DEFAULT AS [Default], "
                . "'' AS [Extra] FROM INFORMATION_SCHEMA.COLUMNS "
                . "WHERE TABLE_NAME='" . $m[1] . "' ORDER BY ORDINAL_POSITION",
            $sql,
        );

        // 3) IFNULL → ISNULL
        $sql = preg_replace('/\bIFNULL\s*\(/i', 'ISNULL(', $sql);

        // 4a) Scalar UDF qualifier — MSSQL requires schema prefix on UDFs.
        //     (?<!dbo\.) — 이미 dbo. 가 붙어 있으면 다시 안 붙임 (멱등 — dbo.dbo. 방지).
        //     ※ curdate/curtime 은 wrap 리스트에 두면 안 됨 — 아래 4b 의 CURDATE→CAST(GETDATE()...) 치환과
        //       이중 변환되어 'dbo.CAST(GETDATE() AS date)' 같은 잘못된 구문이 만들어짐.
        foreach (['unhex', 'hex', 'aes_decrypt', 'aes_encrypt', 'mariadb_aes_key',
                  'date_format', 'substring_index', 'mis_first_photo',
                  'from_unixtime', 'unix_timestamp', 'lpad', 'rpad',
                  'ucase', 'lcase'] as $fn) {
            $sql = preg_replace('/(?<!dbo\.)\b' . $fn . '\s*\(/i', 'dbo.' . $fn . '(', $sql);
        }
        // datediff(a, b) — MariaDB 2-arg → MSSQL needs 3-arg DATEDIFF(day, b, a). Use compat fn.
        $sql = preg_replace('/(?<!dbo\.)\bdatediff\s*\(/i', 'dbo.datediff_days(', $sql);
        // ltrim — built-in in MSSQL (case-insensitive) but data sometimes has lTrim
        // No translation needed.
        // 4b) MySQL functions → MSSQL equivalents
        $sql = preg_replace('/\bNOW\s*\(\s*\)/i', 'GETDATE()', $sql);
        $sql = preg_replace('/\bCURDATE\s*\(\s*\)/i', 'CAST(GETDATE() AS date)', $sql);
        $sql = preg_replace('/\bCURTIME\s*\(\s*\)/i', 'CAST(GETDATE() AS time)', $sql);
        $sql = preg_replace('/\bCURRENT_TIMESTAMP\s*\(\s*\)/i', 'GETDATE()', $sql);
        $sql = preg_replace('/\bCHAR_LENGTH\s*\(/i', 'LEN(', $sql);
        $sql = preg_replace('/\bLENGTH\s*\(/i', 'LEN(', $sql);
        $sql = preg_replace('/\boctet_length\s*\(/i', 'DATALENGTH(', $sql);
        $sql = preg_replace('/\bSTR_TO_DATE\s*\(/i', 'CONVERT(datetime2,', $sql);
        // CONCAT in MSSQL 2012+ exists. Keep.
        $sql = preg_replace('/\bUCASE\s*\(/i', 'UPPER(', $sql);
        $sql = preg_replace('/\bLCASE\s*\(/i', 'LOWER(', $sql);

        // 5) REGEXP → LIKE (best-effort; full regex support requires PATINDEX or CLR)
        // not perfect but allows simple patterns to work
        $sql = preg_replace('/\bNOT\s+REGEXP\s+/i', ' NOT LIKE ', $sql);
        $sql = preg_replace('/\bREGEXP\s+/i', ' LIKE ', $sql);
        $sql = preg_replace('/\bRLIKE\s+/i', ' LIKE ', $sql);

        // 6) CAST(... AS UNSIGNED|SIGNED) → bigint
        $sql = preg_replace('/\bAS\s+UNSIGNED(\s+INTEGER)?\b/i', 'AS bigint', $sql);
        $sql = preg_replace('/\bAS\s+SIGNED(\s+INTEGER)?\b/i', 'AS bigint', $sql);
        $sql = preg_replace('/\bAS\s+CHAR\s*\((\d+)\)/i', 'AS varchar($1)', $sql);
        $sql = preg_replace('/\bAS\s+CHAR\b(?!\s*\()/i', 'AS varchar(8000)', $sql);
        $sql = preg_replace('/\bAS\s+DATETIME\b/i', 'AS datetime', $sql);

        // 7) IF(cond, a, b) → (CASE WHEN cond THEN a ELSE b END)
        $sql = self::translateIfToCase($sql);

        // 8a) JSON_ARRAYAGG(...) — MSSQL 미지원. 정확한 등가(FOR JSON)는 구조가 달라 자동변환 불가.
        //     집계함수 성질(여러 행 → 1행)을 유지해야 스칼라 서브쿼리가 깨지지 않으므로
        //     MAX('[]') 로 중화 — 에러는 회피하고 값은 빈 JSON 배열. (msgo 검증 인스턴스 한정 영향)
        $sql = self::neutralizeJsonArrayAgg($sql);

        // 8b) JSON_OBJECT(k1, v1, k2, v2) [MariaDB] → JSON_OBJECT(k1:v1, k2:v2) [MSSQL 2022]
        $sql = self::convertJsonObject($sql);

        // 9) LIMIT 변환 (3 패턴):
        //    a) ... LIMIT N OFFSET M (문장 끝) → OFFSET M ROWS FETCH NEXT N ROWS ONLY
        //    b) ... LIMIT N (문장 끝, offset 없음) → SELECT 앞에 TOP (N)
        //    c) (SELECT ... LIMIT N) (서브쿼리 내) → (SELECT TOP (N) ...)
        if (preg_match('/\bLIMIT\s+(\d+)\s+OFFSET\s+(\d+)\s*$/i', $sql, $mm)) {
            $sql = preg_replace('/\bLIMIT\s+\d+\s+OFFSET\s+\d+\s*$/i',
                "OFFSET {$mm[2]} ROWS FETCH NEXT {$mm[1]} ROWS ONLY", $sql);
        } elseif (preg_match('/\bLIMIT\s+(\d+)\s*$/i', $sql, $mm)) {
            $n = (int)$mm[1];
            $sql = preg_replace('/\bLIMIT\s+\d+\s*$/i', '', $sql);
            if (!preg_match('/^\s*SELECT\s+TOP\b/i', $sql)) {
                $sql = preg_replace('/^(\s*SELECT\s+)(?!DISTINCT\s+TOP\b)(DISTINCT\s+)?/i',
                    '$1$2TOP (' . $n . ') ', $sql, 1);
            }
        }
        // c) Subquery LIMIT N — find "(... SELECT ... LIMIT N)" pattern.
        //    Replace by inserting TOP(N) right after the SELECT keyword inside the subquery
        //    AND removing the LIMIT N before the closing paren.
        $sql = self::convertSubqueryLimit($sql);
        // LIMIT can also appear at end of subquery; after OFFSET FETCH MSSQL needs ORDER BY → ensure
        // We can't fix that transparently; MSSQL requires ORDER BY for OFFSET. Trust caller.

        // 10) AUTO_INCREMENT — already handled by IDENTITY in DDL (this is for DDL only, runtime SQL doesn't use it)

        // 11) INSERT IGNORE → INSERT (lose IGNORE semantics; rely on caller catching)
        $sql = preg_replace('/\bINSERT\s+IGNORE\s+INTO\b/i', 'INSERT INTO', $sql);

        // 12) WITH RECURSIVE cte AS → WITH cte AS (MSSQL detects recursion automatically)
        $sql = preg_replace('/\bWITH\s+RECURSIVE\s+/i', 'WITH ', $sql);

        // 13) (EXISTS(...)) AS alias → (CASE WHEN EXISTS(...) THEN 1 ELSE 0 END) AS alias
        $sql = self::wrapExistsInCase($sql);

        // 14) CALL proc() / CALL proc(args) → EXEC proc / EXEC proc args
        //     MariaDB / PG: CALL proc(arg)
        //     MSSQL:        EXEC proc arg  (no parens for no-arg, comma-sep args)
        $sql = preg_replace_callback(
            '/\bCALL\s+(\w+)\s*\(([^)]*)\)/i',
            static function (array $m): string {
                $name = $m[1];
                $args = trim($m[2]);
                return $args === '' ? "EXEC {$name}" : "EXEC {$name} {$args}";
            },
            $sql
        );

        // 15) 한글/비-ASCII SQL 문자열 리터럴에 N 접두사 자동 부착.
        //     MSSQL 은 기본 '...' 를 VARCHAR (코드페이지 charset) 로 처리 → 한글이 '???' (0x3f) 로 깎임.
        //     N'...' 가 NVARCHAR (UTF-16) 라 한글 안전. 비-ASCII 가 들어있는 리터럴만 골라 prefix.
        //     이미 N 이 붙어있거나 (N'...'), N 외 문자/식별자에 인접해 식별자 분간 어려운 케이스는 제외.
        //     SQL 표준 doubled-quote 이스케이프 ('') 도 처리. 백슬래시 이스케이프(\')는 MariaDB-only 라 무시.
        $sql = preg_replace_callback(
            "/(?<![A-Za-z0-9_])'((?:[^']|'')*)'/u",
            static function (array $m): string {
                // 비-ASCII 바이트가 있을 때만 N 접두사 부착
                return preg_match('/[^\x00-\x7F]/', $m[1]) ? "N'" . $m[1] . "'" : $m[0];
            },
            $sql
        ) ?? $sql;

        return $sql;
    }

    /**
     * 서브쿼리 안의 "LIMIT N" 패턴을 SELECT TOP (N) 으로 변환.
     * 예: (SELECT col FROM t WHERE x=1 LIMIT 1) → (SELECT TOP (1) col FROM t WHERE x=1)
     * 패턴: 가까운 닫는 괄호 직전의 LIMIT N — 단순 정규식으로 처리 (중첩 깊지 않은 케이스).
     */
    private static function convertSubqueryLimit(string $sql): string
    {
        // 반복적으로 적용 (한 번에 한 매치씩)
        $prev = '';
        $iter = 0;
        while ($prev !== $sql && $iter < 20) {
            $prev = $sql;
            $sql = preg_replace_callback(
                '/\(\s*(SELECT\b)((?:[^()]|\([^()]*\))*?)\bLIMIT\s+(\d+)\s*\)/is',
                static function (array $m): string {
                    return '(' . $m[1] . ' TOP (' . (int)$m[3] . ') ' . $m[2] . ')';
                },
                $sql
            );
            $iter++;
        }
        return $sql;
    }

    /**
     * JSON_ARRAYAGG( ... ) 호출 전체를 MAX('[]') 로 치환.
     * MSSQL 에 JSON_ARRAYAGG 가 없음 → 그대로 두면 "COUNT field incorrect or syntax error".
     * 집계함수라 (SELECT JSON_ARRAYAGG(..) FROM t WHERE ..) 는 항상 1행을 반환하므로,
     * 함수만 다른 비집계 식으로 바꾸면 여러 행이 되어 스칼라 서브쿼리가 깨진다.
     * → 동일하게 집계함수인 MAX 로 치환해 1행 성질을 유지 (값은 빈 배열 '[]').
     */
    private static function neutralizeJsonArrayAgg(string $sql): string
    {
        $out = '';
        $i = 0; $n = strlen($sql);
        while ($i < $n) {
            if (preg_match('/\bJSON_ARRAYAGG\s*\(/iA', substr($sql, $i), $m)) {
                $depth = 1; $j = $i + strlen($m[0]); $inStr = false;
                while ($j < $n && $depth > 0) {
                    $ch = $sql[$j];
                    if ($inStr) {
                        if ($ch === '\\' && $j + 1 < $n) { $j += 2; continue; }
                        if ($ch === "'" && $j + 1 < $n && $sql[$j+1] === "'") { $j += 2; continue; }
                        if ($ch === "'") $inStr = false;
                        $j++; continue;
                    }
                    if ($ch === "'") { $inStr = true; $j++; continue; }
                    if ($ch === '(') { $depth++; $j++; continue; }
                    if ($ch === ')') { $depth--; if ($depth === 0) break; $j++; continue; }
                    $j++;
                }
                if ($depth !== 0) { $out .= substr($sql, $i); return $out; }
                $out .= "MAX('[]')";
                $i = $j + 1;
                continue;
            }
            $out .= $sql[$i];
            $i++;
        }
        return $out;
    }

    /**
     * MariaDB JSON_OBJECT(k1, v1, k2, v2) → MSSQL 2022 JSON_OBJECT(k1:v1, k2:v2).
     * Pairs (key, value) separated by commas → key:value pairs separated by commas.
     */
    private static function convertJsonObject(string $sql): string
    {
        $out = '';
        $i = 0; $n = strlen($sql);
        while ($i < $n) {
            if (preg_match('/\bJSON_OBJECT\s*\(/iA', substr($sql, $i), $m)) {
                $matchLen = strlen($m[0]);
                $start = $i + $matchLen;  // after the (
                // find matching )
                $depth = 1; $j = $start; $inStr = false; $args = []; $cur = '';
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
                    if ($ch === ')') { $depth--; if ($depth === 0) break; $cur .= $ch; $j++; continue; }
                    if ($ch === ',' && $depth === 1) { $args[] = $cur; $cur = ''; $j++; continue; }
                    $cur .= $ch; $j++;
                }
                if ($depth !== 0) { $out .= substr($sql, $i); return $out; }
                $args[] = $cur;
                // Pair up: (k1, v1, k2, v2) → k1:v1, k2:v2
                $pairs = [];
                for ($k = 0; $k + 1 < count($args); $k += 2) {
                    $pairs[] = trim($args[$k]) . ':' . trim($args[$k+1]);
                }
                $out .= 'JSON_OBJECT(' . implode(', ', $pairs) . ')';
                $i = $j + 1;
                continue;
            }
            $out .= $sql[$i];
            $i++;
        }
        return $out;
    }

    private static function wrapExistsInCase(string $sql): string
    {
        // 패턴: ( EXISTS ( ... ) ) — 외부 괄호로 감싸진 EXISTS 만 변환 (값 자리 식별)
        $out = '';
        $i = 0; $n = strlen($sql);
        while ($i < $n) {
            // Look for "(EXISTS(" or "(EXISTS ("
            if (preg_match('/\(\s*EXISTS\s*\(/iA', substr($sql, $i), $m)) {
                $matchLen = strlen($m[0]);
                // Position is after the inner opening paren of EXISTS(
                $depth = 1;  // we're inside EXISTS(
                $j = $i + $matchLen;
                $inStr = false;
                while ($j < $n && $depth > 0) {
                    $ch = $sql[$j];
                    if ($inStr) {
                        if ($ch === "'" && ($j + 1 >= $n || $sql[$j+1] !== "'")) $inStr = false;
                        elseif ($ch === "'") $j++;
                    } elseif ($ch === "'") $inStr = true;
                    elseif ($ch === '(') $depth++;
                    elseif ($ch === ')') {
                        $depth--;
                        if ($depth === 0) break;
                    }
                    $j++;
                }
                // After EXISTS(...), expect outer ')'
                if ($j < $n && $sql[$j] === ')') {
                    $k = $j + 1;
                    // skip whitespace, expect outer ')'
                    while ($k < $n && ctype_space($sql[$k])) $k++;
                    if ($k < $n && $sql[$k] === ')') {
                        // Replace: ( EXISTS(...) ) → (CASE WHEN EXISTS(...) THEN 1 ELSE 0 END)
                        $existsBody = substr($sql, $i + $matchLen, $j - ($i + $matchLen)); // content inside EXISTS()
                        $out .= "(CASE WHEN EXISTS({$existsBody}) THEN 1 ELSE 0 END)";
                        $i = $k + 1;
                        continue;
                    }
                }
            }
            $out .= $sql[$i];
            $i++;
        }
        return $out;
    }

    private static function translateIfToCase(string $sql): string
    {
        $out = '';
        $n = strlen($sql);
        $i = 0;
        while ($i < $n) {
            if (preg_match('/\bif\s*\(/iA', substr($sql, $i), $m)) {
                $matchLen = strlen($m[0]);
                $start = $i + $matchLen;
                $depth = 1; $j = $start; $inStr = false; $args = []; $cur = '';
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
                    if ($ch === ')') { $depth--; if ($depth === 0) { break; } $cur .= $ch; $j++; continue; }
                    if ($ch === ',' && $depth === 1) { $args[] = $cur; $cur = ''; $j++; continue; }
                    $cur .= $ch; $j++;
                }
                if ($depth !== 0) { $out .= substr($sql, $i); return $out; }
                $args[] = $cur;
                if (count($args) === 3) {
                    $a = self::translateIfToCase(trim($args[0]));
                    $b = self::translateIfToCase(trim($args[1]));
                    $c = self::translateIfToCase(trim($args[2]));
                    $out .= "(CASE WHEN {$a} THEN {$b} ELSE {$c} END)";
                } else {
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
