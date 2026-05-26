<?php
/**
 * 030213.프로그램별 등록건수 차트
 *
 * autogubun BETWEEN '030201' AND '030211' 이고 useflag='1' 이며 g12<>'Y' (= 아들메뉴 아님)
 * 인 메뉴들의 실시간 COUNT(*) 를 동적으로 산출.
 *
 * effective table = mis_menus.table_name 이 비어있으면 mis_join_pid 의 부모 메뉴 table_name 사용.
 * effective filter = "useflag='1' AND (base_filter)"  ('and ...' 로 시작하는 base_filter 는 prefix 제거)
 */

function pageLoad()
{
    // 리스트 전용 — 등록/삭제 버튼 숨김
    $GLOBALS['_onlyList'] = true;
}

/**
 * 메뉴명 prefix 의 └/─ 개수로 depth 결정.
 *  0: 영카트 부품관리 전체내역  (root)
 *  1: └1.승인대기 중...          (1단)
 *  2: └─1-1.가격비교 생성하기    (2단)
 */
function _speedmis006142_depth(string $menuName): int
{
    // 앞쪽 └/─ 글자 개수만큼 depth 증가 (mbstring 없는 환경 대응 — 정규식 사용)
    $n = ltrim($menuName);
    $d = 0;
    while ($n !== '' && preg_match('/^([└─])(.*)$/us', $n, $m)) {
        $d++;
        $n = $m[2];
    }
    return $d;
}

/**
 * 자식 결정 후 child_sum/composition 캐시 빌드.
 * 정렬된 순서 + stack 기반 트리 분석.
 */
function _speedmis006142_buildCache(array $rows): array
{
    foreach ($rows as $i => &$r) {
        $r['__depth']    = _speedmis006142_depth((string)$r['menu_name']);
        $r['__children'] = [];
    }
    unset($r);
    $stack = [];
    foreach ($rows as $i => $r) {
        while (!empty($stack) && $rows[end($stack)]['__depth'] >= $r['__depth']) array_pop($stack);
        if (!empty($stack)) $rows[end($stack)]['__children'][] = $i;
        $stack[] = $i;
    }
    foreach ($rows as $i => &$r) {
        if (!$r['__children']) continue;
        $sum = 0;
        $tags = [];
        foreach ($r['__children'] as $ci) {
            // '└0.품절된 내역(판매중지)'(carparts006164) 는 it_soldout=1 슬라이스 → 자식합/구성에서 제외
            if (($rows[$ci]['real_pid'] ?? '') === 'carparts006164') continue;
            $sum += (int)$rows[$ci]['cnt'];
            // 자식 menu_name 의 번호 prefix 추출 (예: "└─1-1.가격비교..." → "1-1.")
            $cm = preg_replace('/^[└─\s]+/u', '', (string)$rows[$ci]['menu_name']) ?? '';
            if (preg_match('/^([0-9.\-]+)\./', $cm, $m)) $tags[] = $m[1] . '.';
            else                                          $tags[] = substr($cm, 0, 18);
        }
        $r['__child_sum']   = $sum;
        $r['__composition'] = implode(' + ', $tags);
    }
    unset($r);
    // idx 기준 lookup map
    $byIdx = [];
    foreach ($rows as $r) $byIdx[(int)$r['idx']] = $r;
    return $byIdx;
}

function list_json_init()
{
    // SELECT 직전 — 우리 list_query 와 동일한 SQL 한번 더 실행해 트리 캐시.
    global $__pdo;
    $sel = ''; $cnt = '';
    list_query($sel, $cnt);
    if ($sel === '') { $GLOBALS['_speedmis006142_cache'] = []; return; }
    try {
        $rows = $__pdo->query($sel . ' ORDER BY table_m.autogubun ASC')->fetchAll(PDO::FETCH_ASSOC);
        $GLOBALS['_speedmis006142_cache'] = _speedmis006142_buildCache($rows);
    } catch (\Throwable $e) {
        $GLOBALS['_speedmis006142_cache'] = [];
    }
}

function list_json_load(&$data)
{
    $cache  = $GLOBALS['_speedmis006142_cache'][(int)$data['idx']] ?? null;
    $depth  = (int)($cache['__depth']       ?? 0);
    $cSum   = (int)($cache['__child_sum']   ?? 0);
    $compo  = (string)($cache['__composition'] ?? '');

    // ── menu_name 셀 — depth 별 들여쓰기 + 색상 + 굵기 (전체 글씨 ×2.2) ──
    // CSS 변수 사용 — 다크모드 자동 대응 (라이트/다크 모두 가독성 보장)
    $colors  = ['var(--color-text-1)', 'var(--color-text-2)', 'var(--color-text-3)']; // 0/1/2 단계
    $weights = ['800',     '600',     '500'];
    $sizes   = ['1.86em',  '2.2em',   '2.09em'];                     // d=0 만 80% 축소 (2.33 × 0.8)
    $color   = $colors[min($depth, 2)];
    $weight  = $weights[min($depth, 2)];
    $size    = $sizes[min($depth, 2)];
    $padL    = (int)($depth * 22);                                   // indent 도 비례 확대 (18 × 2.2 ≈ 22)
    $branch  = $depth >= 1 ? '<span style="color:var(--color-text-3);margin-right:4px">' . str_repeat('│ ', max(0, $depth - 1)) . '└─</span>' : '';
    // 메뉴명에서 기존 prefix(└/─) 떼고 직접 분기 글리프 부착
    $clean   = preg_replace('/^[└─\s]+/u', '', (string)$data['menu_name']) ?? (string)$data['menu_name'];
    $data['__html']['menu_name'] = sprintf(
        '<span style="display:inline-block;padding-left:%dpx;font-weight:%s;color:%s;font-size:%s">%s%s</span>',
        $padL, $weight, $color, $size, $branch, htmlspecialchars($clean, ENT_QUOTES)
    );

    // ── cnt 셀 — 부모면 자식합 + 차이(일치=success / 불일치=danger) ──
    $cntInt = (int)$data['cnt'];
    $main   = '<div style="font-weight:' . $weight . ';font-size:' . $size . '">' . number_format($cntInt) . '</div>';
    if ($cSum > 0) {
        $diff      = $cntInt - $cSum;
        $diffClr   = $diff === 0 ? 'var(--color-success)' : 'var(--color-danger)';
        $sign      = $diff > 0 ? '+' : '';
        $sub  = sprintf(
            '<div style="font-size:1.58em;color:var(--color-text-3);line-height:1.4">자식합 <b>%s</b> ',
            number_format($cSum)
        );
        if ($compo !== '') $sub .= '<span style="color:var(--color-text-3)">(' . htmlspecialchars($compo, ENT_QUOTES) . ')</span> ';
        $sub .= sprintf('<span style="color:%s;font-weight:600">%s%s</span></div>', $diffClr, $sign, number_format($diff));
        $data['__html']['cnt'] = '<div style="line-height:1.25;text-align:right">' . $main . $sub . '</div>';
    } elseif ($depth === 0) {
        $data['__html']['cnt'] = '<div style="text-align:right">' . $main . '</div>';
    }

    // ── idx 셀 — '프로그램번호' (= mis_menus.idx) + [연결] 버튼 ──
    // 클릭 시 mis:openTab (App.jsx 의 data-opentab 전역 위임) → 해당 프로그램 새 탭으로 오픈.
    // 글씨는 작게 (기본 크기) — 다른 셀의 ×2.2 영향 안 받도록 명시.
    $idxVal     = (int)($data['idx'] ?? 0);
    $realPidVal = (string)($data['real_pid'] ?? '');
    if ($idxVal > 0) {
        $labelClean = preg_replace('/^[└─\s]+/u', '', (string)$data['menu_name']) ?? (string)$idxVal;
        $opentab    = json_encode(['gubun' => $idxVal, 'label' => $labelClean, 'openFull' => true], JSON_UNESCAPED_UNICODE);
        $data['__html']['idx'] = sprintf(
            '<span style="font-size:0.9em;color:var(--color-text-3)">%d</span>'
          . ' <span class="btn-open" data-opentab=\'%s\' '
          . 'style="display:inline-block;margin-left:6px;padding:2px 8px;background:var(--color-primary);color:#fff;'
          . 'border-radius:4px;font-size:0.85em;font-weight:600;cursor:pointer;line-height:1.4">연결</span>',
            $idxVal,
            htmlspecialchars($opentab, ENT_QUOTES, 'UTF-8')
        );
    }
}

/**
 * list_query 훅 — selectQuery / countQuery 를 통째로 교체.
 * fields[*] 의 alias_name (idx/autogubun/real_pid/menu_name/cnt) 와 SELECT 의 alias 일치해야 함.
 */
function list_query(&$selectQuery, &$countQuery)
{
    global $__pdo;

    // 1) 대상 메뉴 + effective table_name / real_pid / base_filter
    //    v7 framework 와 동일하게 mis_join_pid 가 있고 자기 base_filter 가 비어있으면 부모 것 상속.
    //    add_url 도 같이 가져와 allFilter 자동 적용 (예: 6112 의 qq_malls_yn, 6125 의 steps='상신')
    $rows = $__pdo->query(
        "SELECT m.idx, m.real_pid, m.menu_name, m.autogubun, m.mis_join_pid, m.add_url,
                COALESCE(NULLIF(m.table_name,''),  p.table_name)  AS eff_table,
                COALESCE(NULLIF(m.base_filter,''), p.base_filter) AS eff_base_filter,
                COALESCE(NULLIF(m.mis_join_pid,''), m.real_pid)   AS eff_real_pid
           FROM mis_menus m
           LEFT JOIN mis_menus p ON p.real_pid = m.mis_join_pid
          WHERE m.autogubun BETWEEN '030201' AND '030211'
            AND m.useflag = '1'
            AND IFNULL(m.g12,'') <> 'Y'
            AND IFNULL(m.is_menu_hidden,'') <> 'Y'
          ORDER BY m.autogubun"
    )->fetchAll(PDO::FETCH_ASSOC);

    // 2) base_filter 가 참조하는 alias 의 JOIN 절 (mis_menu_fields.group_compute) 동적 수집
    $joinStmt = $__pdo->prepare(
        "SELECT DISTINCT db_table, group_compute
           FROM mis_menu_fields
          WHERE real_pid = ?
            AND useflag = '1'
            AND db_table NOT IN ('table_m','virtual_field','')
            AND IFNULL(group_compute,'') <> ''"
    );
    // 3) add_url 의 allFilter → SQL 변환용 fieldMap 빌더
    $fieldStmt = $__pdo->prepare(
        "SELECT alias_name, db_table, db_field
           FROM mis_menu_fields
          WHERE real_pid = ? AND useflag = '1'"
    );

    $unions = [];
    foreach ($rows as $r) {
        $tbl = trim((string)($r['eff_table'] ?? ''));
        if ($tbl === '') continue;
        $bf  = trim((string)$r['eff_base_filter']);
        $bf  = preg_replace('/^\s*(?:and|AND)\s+/', '', $bf) ?? $bf;

        // base_filter 에서 사용하는 alias 만 JOIN 추가
        $joinStmt->execute([(string)$r['eff_real_pid']]);
        $joinSql = '';
        foreach ($joinStmt->fetchAll(PDO::FETCH_ASSOC) as $j) {
            $alias = trim((string)$j['db_table']);
            // base_filter 가 그 alias 를 참조해야만 JOIN
            if ($alias !== '' && preg_match('/\b' . preg_quote($alias, '/') . '\./', $bf)) {
                $joinSql .= ' LEFT JOIN ' . $j['group_compute'];
            }
        }

        $where = "table_m.useflag='1'";
        if ($bf !== '') $where .= " AND ({$bf})";

        // add_url 의 allFilter → 추가 WHERE
        $afCond = _speedmis006142_buildAllFilterWhere(
            (string)$r['add_url'],
            (string)$r['eff_real_pid'],
            $fieldStmt,
            $__pdo
        );
        if ($afCond !== '') $where .= " AND ({$afCond})";

        $cnt = "(SELECT COUNT(*) FROM `" . str_replace('`', '', $tbl) . "` table_m{$joinSql} WHERE {$where})";

        $unions[] = sprintf(
            "SELECT %d AS idx, %s AS autogubun, %s AS real_pid, %s AS menu_name, %s AS cnt",
            (int)$r['idx'],
            $__pdo->quote((string)$r['autogubun']),
            $__pdo->quote((string)$r['real_pid']),
            $__pdo->quote((string)$r['menu_name']),
            $cnt
        );
    }

    if (!$unions) {
        // 대상이 비어있으면 빈 결과
        $selectQuery = "SELECT * FROM (SELECT 0 AS idx, '' AS autogubun, '' AS real_pid, '' AS menu_name, 0 AS cnt) table_m WHERE 1=0";
        $countQuery  = "SELECT 0";
        return;
    }
    // outer alias = table_m  (framework 가 'ORDER BY table_m.X' / 'LIMIT N' 을 뒤에 자동 부착)
    $base = "(" . implode("\n  UNION ALL\n  ", $unions) . ") table_m";
    $selectQuery = "SELECT table_m.* FROM {$base}";
    $countQuery  = "SELECT COUNT(*) FROM {$base}";
}

/**
 * mis_menus.add_url 의 allFilter 를 파싱해 WHERE 조건문자열 반환.
 * fieldMap = alias_name → SQL 표현식 (db_table='table_m' 이면 `table_m.field`,
 * 그 외엔 db_field 가 표현식이라 그대로 사용).
 *
 * 단순화: contains/eq/neq 등 자주 쓰는 op 만 inline literal 로 변환 (binding 불사용).
 * cron 의 일관된 union all 빌드 위해 모든 값을 quote 처리.
 */
function _speedmis006142_buildAllFilterWhere(string $addUrl, string $realPid, PDOStatement $fieldStmt, PDO $pdo): string
{
    if ($addUrl === '') return '';
    // add_url 형식: '&allFilter=[...]&orderby=...&recently=N'
    parse_str(ltrim($addUrl, '&?'), $params);
    $afJson = $params['allFilter'] ?? '';
    if ($afJson === '') return '';
    $filters = json_decode($afJson, true);
    if (!is_array($filters) || !$filters) return '';

    // fieldMap 빌드
    $fieldStmt->execute([$realPid]);
    $map = [];
    foreach ($fieldStmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
        $alias = $f['alias_name'];
        $tbl   = trim((string)$f['db_table']);
        $col   = trim((string)$f['db_field']);
        if ($alias === '' || $col === '') continue;
        if ($tbl === 'table_m' || $tbl === '') {
            // 단순 컬럼은 table_m prefix, 표현식(공백/괄호 포함)은 그대로 wrap
            if (preg_match('/^[A-Za-z0-9_]+$/', $col)) {
                $map[$alias] = "table_m.`{$col}`";
            } else {
                $map[$alias] = "({$col})";
            }
        } elseif ($tbl === 'virtual_field') {
            // virtual_field — db_field 가 표현식
            $map[$alias] = "({$col})";
        } else {
            // 조인 alias — JOIN 가 base_filter 빌드 시 자동 추가되지 않으므로 skip
            // (필요 시 alias.col 그대로 사용)
            $map[$alias] = preg_match('/^[A-Za-z0-9_]+$/', $col)
                ? "{$tbl}.`{$col}`"
                : "({$col})";
        }
    }

    $clauses = [];
    foreach ($filters as $cond) {
        $field = $cond['field'] ?? '';
        $op    = $cond['operator'] ?? '';
        $val   = $cond['value'] ?? '';
        if ($field === '') continue;
        if (str_starts_with($field, 'toolbar_')) $field = substr($field, 8);
        if (!isset($map[$field])) continue;
        $expr = $map[$field];
        switch ($op) {
            case 'eq':         $clauses[] = "{$expr} = " . $pdo->quote((string)$val); break;
            case 'neq':        $clauses[] = "{$expr} <> " . $pdo->quote((string)$val); break;
            case 'contains':   $clauses[] = "{$expr} LIKE " . $pdo->quote('%' . $val . '%'); break;
            case 'notContains':$clauses[] = "{$expr} NOT LIKE " . $pdo->quote('%' . $val . '%'); break;
            case 'startsWith': $clauses[] = "{$expr} LIKE " . $pdo->quote($val . '%'); break;
            case 'endsWith':   $clauses[] = "{$expr} LIKE " . $pdo->quote('%' . $val); break;
            case 'gt':         $clauses[] = "{$expr} > "  . $pdo->quote((string)$val); break;
            case 'gte':        $clauses[] = "{$expr} >= " . $pdo->quote((string)$val); break;
            case 'lt':         $clauses[] = "{$expr} < "  . $pdo->quote((string)$val); break;
            case 'lte':        $clauses[] = "{$expr} <= " . $pdo->quote((string)$val); break;
            case 'isNull':     $clauses[] = "{$expr} IS NULL"; break;
            case 'isNotNull':  $clauses[] = "{$expr} IS NOT NULL"; break;
        }
    }
    return $clauses ? implode(' AND ', $clauses) : '';
}