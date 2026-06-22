<?php

namespace App;

/**
 * allFilter JSON → SQL WHERE / ORDER BY / LIMIT 변환
 * v6와 동일한 파라미터 방식 유지
 */
class QueryBuilder
{
    private const OPERATORS = [
        'eq', 'neq', 'contains', 'notContains', 'startsWith', 'endsWith',
        'gt', 'gte', 'lt', 'lte', 'between', 'in', 'isNull', 'isNotNull',
    ];

    /**
     * @param string|array $allFilter  JSON 문자열 또는 배열
     * @param string       $tableAlias 단순 단일 테이블용 prefix (JOIN 시엔 fieldMap 사용)
     * @param array        $fieldMap   alias_name → 'table_alias.field' 매핑 (JOIN 모드)
     */
    public function buildWhere(string|array $allFilter, string $tableAlias = '', array $fieldMap = []): array
    {
        $filters = is_string($allFilter) ? (json_decode($allFilter, true) ?: []) : $allFilter;

        if (empty($filters)) {
            return ['sql' => '', 'bindings' => []];
        }

        $clauses  = [];
        $bindings = [];

        foreach ($filters as $cond) {
            if (empty($cond['field']) || empty($cond['operator'])) continue;
            if (!in_array($cond['operator'], self::OPERATORS, true)) continue;

            $rawField = $cond['field'];

            // toolbar_ 접두어 제거 (toolbar_zsomefield → zsomefield)
            if (str_starts_with($rawField, 'toolbar_')) {
                $rawField = substr($rawField, 8);
            }

            // fieldMap 우선 (JOIN 모드): alias → 'table.field'
            if (isset($fieldMap[$rawField])) {
                $colExpr = $fieldMap[$rawField];
            } else {
                // fieldMap 이 비어있지 않은데 (= 메뉴 fields 기반 빌드 모드) 해당 필드가 없으면
                // 다른 메뉴의 alias 가 잘못 흘러든 것 — 무시 (raw column 시도하면 SQL 에러로 빠짐)
                // 예: 634 (parent) 의 table_ggcodeQmKname 필터가 633 (child) 쿼리에 잔존 → 알리아스 unknown
                if (!empty($fieldMap)) continue;

                $field = $this->sanitizeField($rawField);
                if ($field === '') continue;
                $prefix  = $tableAlias ? "`{$tableAlias}`." : '';
                $colExpr = $prefix . "`{$field}`";
            }

            [$clause, $vals] = $this->parseOp($colExpr, $cond['operator'], $cond['value'] ?? null);
            $clauses[]  = $clause;
            $bindings   = array_merge($bindings, $vals);
        }

        if (empty($clauses)) return ['sql' => '', 'bindings' => []];

        return ['sql' => 'WHERE ' . implode(' AND ', $clauses), 'bindings' => $bindings];
    }

    public function buildOrderBy(string $orderby, array $fieldMap = []): string
    {
        if ($orderby === '') return '';
        if (str_starts_with($orderby, '__recently__')) {
            $col = substr($orderby, 12); // __recently__ 이후 db_table.db_field
            return $col !== '' ? "ORDER BY {$col} DESC" : 'ORDER BY table_m.idx DESC';
        }

        $parts = [];
        foreach (explode(',', $orderby) as $token) {
            $token = trim($token);
            if ($token === '') continue;

            if (str_starts_with($token, '-')) {
                $raw = substr($token, 1);
                $dir = 'DESC';
            } elseif (preg_match('/^(.+?)\s+(asc|desc)\s*$/i', $token, $m)) {
                // "field desc" / "field asc" 형태도 허용
                $raw = $m[1];
                $dir = strtoupper($m[2]);
            } else {
                $raw = $token;
                $dir = 'ASC';
            }

            if (isset($fieldMap[$raw])) {
                $parts[] = "{$fieldMap[$raw]} {$dir}";
            } else {
                $col = $this->sanitizeField($raw);
                if ($col !== '') $parts[] = "`{$col}` {$dir}";
            }
        }

        return empty($parts) ? '' : 'ORDER BY ' . implode(', ', $parts);
    }

    public function buildPagination(int $page, int $pageSize): string
    {
        $pageSize = min(max(1, $pageSize), MAX_PAGE_SIZE);
        $offset   = (max(1, $page) - 1) * $pageSize;
        return "LIMIT {$pageSize} OFFSET {$offset}";
    }

    // -------------------------------------------------------------------------

    private function parseOp(string $col, string $op, mixed $val): array
    {
        return match ($op) {
            // eq 값이 빈문자열/null 이면 NULL 도 함께 매칭 (빈 카테고리 등) — '' 저장행과 NULL 저장행 모두 포함
            'eq'          => ($val === '' || $val === null)
                                 ? ["({$col} = '' OR {$col} IS NULL)", []]
                                 : ["{$col} = ?",         [$val]],
            // 부정(neq/notContains)은 NULL 도 포함해야 함 — SQL 에서 NULL != 'x' 는 false 라 빠지므로 OR IS NULL.
            //   (예: 전체 190 - eq 1 = neq 189 가 맞음. NULL 제목 행이 누락되던 버그)
            //   단, neq 값이 빈문자열/null 이면 '값이 있는 것'만 (NULL·'' 제외) — eq '' 의 대칭 보수.
            //   (neq '' 는 "비어있지 않은 것"이라 NULL 은 빠져야 함)
            'neq'         => ($val === '' || $val === null)
                                 ? ["({$col} IS NOT NULL AND {$col} != '')", []]
                                 : ["({$col} != ? OR {$col} IS NULL)",      [$val]],
            'contains'    => $this->symbolPrefix($col, $val) ?? ["{$col} LIKE ?", ["%{$val}%"]],
            'notContains' => ["({$col} NOT LIKE ? OR {$col} IS NULL)", ["%{$val}%"]],
            'startsWith'  => ["{$col} LIKE ?",       ["{$val}%"]],
            'endsWith'    => ["{$col} LIKE ?",       ["%{$val}"]],
            'gt'          => ["{$col} > ?",          [$val]],
            'gte'         => ["{$col} >= ?",         [$val]],
            'lt'          => ["{$col} < ?",          [$val]],
            'lte'         => ["{$col} <= ?",         [$val]],
            'isNull'      => ["({$col} IS NULL OR {$col} = '')", []],
            'isNotNull'   => ["({$col} IS NOT NULL AND {$col} != '')", []],
            'between'     => $this->parseBetween($col, $val),
            'in'          => $this->parseIn($col, $val),
            default       => ['1=1', []],
        };
    }

    /**
     * contains 값 앞에 비교기호가 있으면 LIKE 대신 그 비교를 존중.
     *   "=2" → col = '2',  ">3" → col > '3',  "<=5" → col <= '5',  "<>2"/"!=2" → col != '2'
     * 기호가 없으면 null 반환(기본 LIKE 사용). 기호만 있고 값이 없으면도 null.
     */
    private function symbolPrefix(string $col, mixed $val): ?array
    {
        if (!is_string($val)) return null;
        $v = ltrim($val);
        // 2글자 기호를 1글자보다 먼저 검사
        $map = ['>=' => '>=', '<=' => '<=', '<>' => '!=', '!=' => '!=', '=' => '=', '>' => '>', '<' => '<'];
        foreach ($map as $sym => $sqlOp) {
            if (str_starts_with($v, $sym)) {
                $rest = trim(substr($v, strlen($sym)));
                if ($rest === '') return null;
                // 부정(!=)은 NULL 도 포함 (NULL != 'x' 는 false 라 빠지므로)
                if ($sqlOp === '!=') return ["({$col} != ? OR {$col} IS NULL)", [$rest]];
                return ["{$col} {$sqlOp} ?", [$rest]];
            }
        }
        return null;
    }

    private function parseBetween(string $col, mixed $val): array
    {
        $parts = is_array($val) ? array_values($val) : explode(',,', (string)$val, 2);
        $from  = isset($parts[0]) ? (string)$parts[0] : '';
        $to    = isset($parts[1]) ? (string)$parts[1] : '';
        $fromEmpty = ($from === '' || $from === null);
        $toEmpty   = ($to   === '' || $to   === null);
        if ($fromEmpty && $toEmpty) return ['1=1', []];
        if ($fromEmpty)              return ["{$col} <= ?",          [$to]];
        if ($toEmpty)                return ["{$col} >= ?",          [$from]];
        return ["{$col} BETWEEN ? AND ?", [$from, $to]];
    }

    private function parseIn(string $col, mixed $val): array
    {
        $items = is_array($val)
            ? array_values($val)
            : array_filter(explode(',,', (string)$val), fn($v) => $v !== '');
        if (empty($items)) return ['1=1', []];
        $ph = implode(',', array_fill(0, count($items), '?'));
        return ["{$col} IN ({$ph})", array_values($items)];
    }

    public function sanitizeField(string $field): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $field) ?? '';
    }
}
