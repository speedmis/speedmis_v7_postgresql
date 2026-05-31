<?php

namespace App;

use Psr\Log\LoggerInterface;

/**
 * CRUD 핵심 엔진
 * v6의 list_json.php + index.php 저장 로직을 PHP 8.3 + Slim 4 방식으로 재작성
 *
 * programs/{real_pid}.php 에서 정의된 훅 함수를 호출
 */
class DataHandler
{
    private array $loadedPrograms = [];

    public function __construct(
        private \PDO            $pdo,
        private QueryBuilder    $qb,
        private MisCache        $cache,
        private LoggerInterface $logger,
        private FileManager     $fileManager
    ) {}

    // 동일 요청 내 메모이즈 — file 캐시 환경(APCu 미설치) 에서 syscall 절감
    private array $menuMemo = [];
    private array $fieldsMemo = [];

    /**
     * dev_mode 표시용 SQL 을 현재 DB 드라이버에 맞춰 변환.
     * 백틱 → "double-quote" (PG) / [bracket] (MSSQL). MySQL/MariaDB 는 원형 유지.
     */
    private function translateDisplaySql(string $sql): string
    {
        if ($sql === '') return $sql;
        try {
            if (\App\Config\Database::isPg()) {
                return \App\Config\PgCompatPDO::translate($sql);
            }
            if (\App\Config\Database::isMssql()) {
                return \App\Config\MssqlCompatPDO::translate($sql);
            }
        } catch (\Throwable) {}
        return $sql;
    }

    // =========================================================================
    // 목록 (act=list)
    // =========================================================================
    public function list(array $params, object $user): array
    {
        // _backup: 백업 JSON 파일을 데이터소스로 사용 (읽기전용)
        if (trim((string)($params['_backup'] ?? '')) !== '') {
            return $this->loadBackupAsList($params, $user);
        }

        $gubun     = (int)($params['gubun']    ?? 0);
        $page      = (int)($params['page']     ?? 1);
        $hasExplicitPageSize = isset($params['pageSize']) || isset($params['psize']);
        $pageSize  = (int)($params['pageSize'] ?? $params['psize'] ?? DEFAULT_PAGE_SIZE);
        $allFilter = $params['allFilter'] ?? '[]';
        $orderby   = $params['orderby']   ?? '';
        $aggregate = (string)($params['aggregate'] ?? '');
        $recentlyParam = (string)($params['recently'] ?? '');

        if ($pageSize === 999999) $pageSize = MAX_PAGE_SIZE;

        // aggregate: 최근순이 아닐 때만 활성화
        // 유효 모드: auto, sum, simple.auto, simple.sum
        $validAggModes = ['auto', 'sum', 'simple.auto', 'simple.sum'];
        $aggregateActive = in_array($aggregate, $validAggModes, true) && ($recentlyParam !== 'Y');
        if ($aggregateActive) {
            $page = 1;
            // psize/pageSize가 URL에 명시되지 않았으면 기본 1000
            if (!$hasExplicitPageSize) {
                $pageSize = 1000;
            }
        }

        $menu   = $this->getMenu($gubun);
        $fields = $this->getFields($gubun, $menu, $user);

        // 접근 권한 체크 (read 필수)
        $userId0 = (string)($user->uid ?? '');
        $globalAdmin0 = ($user->is_admin ?? '') === 'Y' ? 'Y' : '';
        $access = $this->checkMenuAccess($menu, $userId0, $globalAdmin0);
        if (!$access['read']) {
            return ['success' => false, 'message' => '읽기권한이 없습니다.', '_access' => $access];
        }
        $GLOBALS['_menuAccess'] = $access;
        if ($access['admin']) $GLOBALS['misSessionIsAdmin'] = 'Y';

        $listFlag = ($params['actionFlag'] ?? '') ?: 'list';
        $GLOBALS['_onlyList'] = false;
        $GLOBALS['_client_viewPref'] = null; // 'list' 또는 'auto'
        $GLOBALS['_client_css'] = null;
        $GLOBALS['_client_js'] = null;
        $GLOBALS['_client_buttonText'] = null;
        $GLOBALS['_client_buttons'] = null;
        $GLOBALS['_client_fields'] = null;
        $GLOBALS['_client_redirect'] = null;
        $this->setGlobals($params, $user, $menu, $listFlag);
        $this->loadProgram($menu['real_pid'] ?? '', $menu);

        // 쿼리 빌드 전 초기화 훅
        $this->callHook('before_query', $menu, $fields, $params);

        // mis_menus.table_name = 주 테이블명
        $mainTable = $this->resolveTable(trim($menu['table_name'] ?? ''));
        $userId    = (string)($user->uid ?? '');

        // fields → SELECT 컬럼 + JOIN 절 + fieldMap(WHERE/ORDER용) 빌드
        $selectColTitles = [];
        try {
            [$selectCols, $joinClauses, $fieldMap, $aliasToTable, $selectColTitles] = $this->buildSelectFromFields($fields, $userId, $mainTable);
        } catch (\Throwable $e) {
            $this->logger->warning('buildSelectFromFields failed', ['gubun' => $gubun, 'err' => $e->getMessage()]);
            [$selectCols, $joinClauses, $fieldMap, $aliasToTable] = [[], [], [], ['table_m' => $mainTable]];
        }

        $joinStr   = $joinClauses ? ' ' . implode(' ', $joinClauses) : '';
        $selectStr = $selectCols  ? implode(', ', $selectCols) : 'table_m.*';

        // 읽기전용 조건 — read_only_cond SQL 식을 __readonly 가상 컬럼으로 주입
        $readOnlyCondList = trim($menu['read_only_cond'] ?? '');
        if ($readOnlyCondList !== '') {
            $resolved = $this->resolveExpression($readOnlyCondList, $aliasToTable);
            $selectStr .= ", ({$resolved}) AS __readonly";
        }

        $where    = $this->qb->buildWhere($allFilter, '', $fieldMap);
        $bindings = $where['bindings'];

        // use_condition: 레코드 표시 조건
        // 비어있거나 placeholder (1=1, 9=9, 111=111) 이면 useflag 컬럼 존재 시 자동 적용
        $useCond = trim($menu['use_condition'] ?? '');
        $isPlaceholder = preg_match('/^\s*(\d+)\s*=\s*\1\s*$/', $useCond) === 1;
        if ($useCond !== '' && !$isPlaceholder) {
            $useCond = $this->resolveExpression($useCond, $aliasToTable);
        } else {
            try {
                $cols = $mainTable !== '' ? $this->getTableColumnSet($mainTable) : [];
                $useCond = isset($cols['useflag']) ? "table_m.useflag = '1'" : '1=1';
            } catch (\Throwable) {
                $useCond = '1=1';
            }
        }
        // 삭제내역 조회 모드: delete_query (예: "useflag=0") 를 WHERE 조건으로 변환해 기본 use_condition 대체
        if (($params['deleted'] ?? '') === '1') {
            $delCond = $this->deleteQueryToCondition(trim($menu['delete_query'] ?? ''));
            if ($delCond !== '') $useCond = $delCond;
        }
        $useCondSql = " AND ({$useCond})";

        // base_filter: 프로그램 기본 WHERE 조건
        // 값 자체에 "and"/"where" 가 앞에 붙어있는 경우 제거
        $baseFilter = trim($menu['base_filter'] ?? '');
        $baseFilter = (string)(preg_replace('/^\s*(and|where)\s+/i', '', $baseFilter) ?? $baseFilter);
        $baseFilter = $this->resolveBaseFilter($baseFilter, $aliasToTable);
        $baseFilterSql = $baseFilter !== '' ? " AND ({$baseFilter})" : '';

        // 마스터-디테일: parent_idx → FK 필드로 자동 필터
        // sort_order 기준 두 번째 필드가 FK (첫 번째가 PK — col_width=-1 숨김이어도 동일)
        $parentIdxRaw = trim($params['parent_idx'] ?? '');
        if ($parentIdxRaw !== '' && count($fields) >= 2) {
            $sorted = $fields;
            usort($sorted, fn($a, $b) => (int)($a['sort_order'] ?? 0) <=> (int)($b['sort_order'] ?? 0));
            $fkAlias = $sorted[1]['alias_name'] ?? '';
            if ($fkAlias !== '' && isset($fieldMap[$fkAlias])) {
                $baseFilterSql .= ' AND ' . $fieldMap[$fkAlias] . ' = ?';
                $bindings[]     = $parentIdxRaw;  // 항상 문자열 바인딩
            }
        }

        $fromSql = $mainTable ? "`{$mainTable}` table_m{$joinStr}" : '';
        $whereFull = ($where['sql'] ?: 'WHERE 1=1') . $useCondSql . $baseFilterSql;

        $selectSql = $fromSql ? "SELECT {$selectStr} FROM {$fromSql} {$whereFull}" : '';

        // COUNT 쿼리 최적화: WHERE 에서 참조하는 JOIN 만 포함
        if ($mainTable && $joinClauses) {
            $neededJoins = [];
            foreach ($joinClauses as $jc) {
                // "LEFT JOIN xxx alias ON ..." 에서 alias 추출
                if (preg_match('/JOIN\s+\S+\s+(\w+)\s+ON/i', $jc, $m)) {
                    $alias = $m[1];
                    // WHERE 절에서 이 alias 를 참조하는지 확인
                    if (str_contains($whereFull, "{$alias}.")) {
                        $neededJoins[] = $jc;
                    }
                }
            }
            $countJoinStr = $neededJoins ? ' ' . implode(' ', $neededJoins) : '';
            $countFromSql = "`{$mainTable}` table_m{$countJoinStr}";
        } else {
            $countFromSql = $fromSql;
        }
        $countSql = $countFromSql ? "SELECT COUNT(*) FROM {$countFromSql} {$whereFull}" : '';

        // list_query 훅: 쿼리 직접 교체 가능
        $preHookSelect = $selectSql;
        $preHookCount  = $countSql;
        $this->callHook('list_query', $selectSql, $countSql);
        $hookModified  = ($selectSql !== $preHookSelect) || ($countSql !== $preHookCount);

        // 캐시 확인
        $recently  = $recentlyParam;
        $cacheKey = $this->cache->makeKey(
            $menu['real_pid'] ?? "g{$gubun}",
            (string)($user->uid ?? ''),
            $allFilter . $orderby . $page . $pageSize . $recently . $parentIdxRaw . ($aggregateActive ? '|agg' : '')
        );

        // 정렬: recently=Y → PK DESC 강제 / orderby → 사용자 지정 / 기본 정렬
        // (aggregate 훅이 캐시 히트 시에도 orderby alias를 쓸 수 있도록 선행 계산)
        if ($recently === 'Y') {
            $firstField = $fields[0] ?? null;
            $rt = ($firstField['db_table'] ?? '') ?: 'table_m';
            $rf = ($firstField['db_field'] ?? '') ?: 'idx';
            $effectiveOrderby = "__recently__{$rt}.{$rf}";
        } elseif ($orderby !== '') {
            $effectiveOrderby = $orderby;
        } else {
            $effectiveOrderby = $this->buildDefaultOrderBy($fields);
        }

        // 개발자 모드 / _simple(fast xls) / _xlsStream(서버 스트리밍 xls) 모드는 캐시 bypass
        //   - dev_mode: SQL 디버그 정보 반환 위해
        //   - _simple: 캐시 hit 경로가 list_json_load 를 다시 호출 (= 느림). raw 만 필요한 fast 다운로드는 우회
        //   - _xlsStream: 서버에서 HTML 직접 echo. fetchAll 안 함 → 메모리/캐시 무관
        if (
            ($params['dev_mode']    ?? '') !== '1' &&
            ($params['_simple']     ?? '') !== '1' &&
            ($params['_xlsStream']  ?? '') !== '1' &&
            ($params['customAction'] ?? '') === ''   // 커스텀버튼 액션은 list_json_init 에서 데이터를 바꿀 수 있어 캐시 우회(=fresh)
        ) {
            if ($cached = $this->cache->get($cacheKey)) {
                $GLOBALS['_client_alert'] = null;
                $GLOBALS['_client_toast'] = null;
                $GLOBALS['_client_openTab'] = null;
                $GLOBALS['_client_confirm'] = null;
                // _client_redirect, _client_css, _client_buttonText, _client_buttons 는 pageLoad()에서 이미 설정됨 → 보존
                $GLOBALS['_onlyList'] = false;
        $GLOBALS['_client_viewPref'] = null; // 'list' 또는 'auto'
                $this->callHook('list_json_init');
                if (function_exists('list_json_load') && !empty($cached['data'])) {
                    for ($__i = 0, $__len = count($cached['data']); $__i < $__len; $__i++) {
                        $cached['data'][$__i]['__html'] = [];
                        list_json_load($cached['data'][$__i]);
                        if (empty($cached['data'][$__i]['__html'])) {
                            unset($cached['data'][$__i]['__html']);
                        }
                    }
                }
                // row_buttons 훅 — list 셀 prefix 로 버튼 주입 (view 폼과 공유)
                if (!empty($cached['data'])) {
                    for ($__i = 0, $__len = count($cached['data']); $__i < $__len; $__i++) {
                        $btnMap = self::_renderRowButtons($cached['data'][$__i]);
                        if (!empty($btnMap)) {
                            if (!isset($cached['data'][$__i]['__html'])) $cached['data'][$__i]['__html'] = [];
                            foreach ($btnMap as $alias => $btnHtml) {
                                $existing = $cached['data'][$__i]['__html'][$alias] ?? null;
                                if ($existing === null) {
                                    $rawVal = $cached['data'][$__i][$alias] ?? '';
                                    $existing = htmlspecialchars((string)$rawVal, ENT_QUOTES, 'UTF-8');
                                }
                                $cached['data'][$__i]['__html'][$alias] = $existing . $btnHtml;
                            }
                        }
                    }
                }
                if ($GLOBALS['_client_alert'] !== null) $cached['_client_alert'] = $GLOBALS['_client_alert'];
                if ($GLOBALS['_client_toast'] !== null) $cached['_client_toast'] = $GLOBALS['_client_toast'];
                if ($GLOBALS['_client_openTab'] !== null) $cached['_client_openTab'] = $GLOBALS['_client_openTab'];
                if ($GLOBALS['_client_confirm'] !== null) $cached['_client_confirm'] = $GLOBALS['_client_confirm'];
                if ($GLOBALS['_client_redirect'] !== null) $cached['_client_redirect'] = $GLOBALS['_client_redirect'];
                if (!empty($GLOBALS['_onlyList'])) $cached['_onlyList'] = true;
                if ($GLOBALS['_client_viewPref'] !== null) $cached['_client_viewPref'] = $GLOBALS['_client_viewPref'];
                if ($GLOBALS['_client_css'] !== null) $cached['_client_css'] = $GLOBALS['_client_css'];
                if ($GLOBALS['_client_js'] !== null) $cached['_client_js'] = $GLOBALS['_client_js'];
                if ($GLOBALS['_client_buttonText'] !== null) $cached['_client_buttonText'] = $GLOBALS['_client_buttonText'];
                if (!empty($GLOBALS['_client_buttons'])) $cached['_client_buttons'] = $GLOBALS['_client_buttons'];
                if (!empty($GLOBALS['_client_saveAndNew'])) $cached['_client_saveAndNew'] = true;
                if (!empty($GLOBALS['_client_disableSort'])) $cached['_client_disableSort'] = true;
                if (!empty($GLOBALS['_client_fieldTitle']) && is_array($GLOBALS['_client_fieldTitle'])) {
                    foreach ($cached['fields'] as &$_f) {
                        $alias = $_f['alias_name'] ?? '';
                        if (isset($GLOBALS['_client_fieldTitle'][$alias])) {
                            $_f['col_title'] = $GLOBALS['_client_fieldTitle'][$alias];
                        }
                    }
                    unset($_f);
                }
                if ($aggregateActive) {
                    $cached['data'] = $this->buildAggregateRows($cached['data'] ?? [], $cached['fields'] ?? $fields, $effectiveOrderby, $aggregate);
                    $cached['_aggregate'] = $aggregate;
                }
                // 캐시된 _access 는 과거 상태일 수 있으므로 현재 요청에서 새로 계산한 값으로 덮어쓰기
                $cached['_access'] = $access;
                return $cached;
            }
        }

        $orderSql = $this->qb->buildOrderBy($effectiveOrderby, $fieldMap);
        $limitSql = $this->qb->buildPagination($page, $pageSize);

        // _xlsStream: 서버에서 HTML 직접 stream + exit. SELECT 결과를 메모리에 누적 안 함 (PDO unbuffered).
        // v6 의 빠른 xls 다운로드 방식과 동일 — fetchAll 메모리 한도/PHP-FPM 타임아웃 회피.
        if (($params['_xlsStream'] ?? '') === '1') {
            $this->callHook('list_json_init'); // 사용자 SQL 변형/사이드이펙트는 계속 적용
            $this->streamXlsRows($selectSql, $orderSql, $bindings, $fields, $menu, $mainTable, $whereFull);
            exit;
        }

        // list_json_init 훅 — v6 의미("목록 생성 전 초기화")에 맞춰 COUNT/SELECT 직전에 실행.
        // 이곳에서 UPDATE/INSERT 같은 사이드 이펙트를 수행하면 이어지는 SELECT 가 갱신된 값을 읽음.
        $GLOBALS['_client_alert']    = null;
        $GLOBALS['_client_toast']    = null;
        $GLOBALS['_client_openTab']  = null;
        $GLOBALS['_client_confirm']  = null;
        // _client_redirect 는 pageLoad() 에서 설정 가능 (리다이렉트 전용 프로그램) → 리셋하지 않음
        $this->callHook('list_json_init');

        $total = 0;
        if ($countSql) {
            try {
                $stmt = $this->pdo->prepare($countSql);
                $stmt->execute($bindings);
                $total = (int)$stmt->fetchColumn();
            } catch (\Throwable $e) {
                // JOIN/컬럼 오류 → 단순 COUNT fallback
                $this->logger->warning('count query failed, fallback', ['err' => $e->getMessage(), 'sql' => $countSql]);
                $fbCount = $mainTable ? "SELECT COUNT(*) FROM `{$mainTable}` table_m {$whereFull}" : '';
                if ($fbCount) {
                    try {
                        $stmt = $this->pdo->prepare($fbCount);
                        $stmt->execute($bindings);
                        $total = (int)$stmt->fetchColumn();
                    } catch (\Throwable $e2) {
                        $this->logger->warning('count fallback also failed', ['err' => $e2->getMessage()]);
                    }
                }
            }
        }

        $sqlError = null; // 개발자모드용 쿼리 에러 메시지
        $data = [];
        if ($selectSql) {
            try {
                $stmt = $this->pdo->prepare("{$selectSql} {$orderSql} {$limitSql}");
                $stmt->execute($bindings);
                $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                // JOIN/컬럼 오류 → SELECT table_m.* fallback. 단 WHERE 가 다른 alias 를 참조하면 그 JOIN 은 유지.
                $sqlError = $e->getMessage();
                $this->logger->warning('select query failed, fallback to SELECT *', ['err' => $e->getMessage(), 'sql' => $selectSql]);
                if ($mainTable) {
                    // ORDER BY 가 JOIN 컬럼을 참조하면 fallback 에서도 실패 → table_m 컬럼만 허용
                    $fbOrderSql = preg_match('/ORDER BY\s+table_m\.\w+/i', $orderSql)
                        ? $orderSql
                        : (str_contains($orderSql, '.') ? 'ORDER BY table_m.idx DESC' : $orderSql);
                    // WHERE 절에서 참조하는 JOIN alias 만 추려 fallback FROM 에 포함 (column-not-found 방지)
                    $fbJoinSql = '';
                    if (!empty($joinClauses)) {
                        $fbJoins = [];
                        foreach ($joinClauses as $jc) {
                            if (preg_match('/JOIN\s+\S+\s+(\w+)\s+ON/i', $jc, $jm)) {
                                $a = $jm[1];
                                if (str_contains($whereFull, "{$a}.")) $fbJoins[] = $jc;
                            }
                        }
                        if ($fbJoins) $fbJoinSql = ' ' . implode(' ', $fbJoins);
                    }
                    $fbSelect = "SELECT table_m.* FROM `{$mainTable}` table_m{$fbJoinSql} {$whereFull} {$fbOrderSql} {$limitSql}";
                    try {
                        $stmt = $this->pdo->prepare($fbSelect);
                        $stmt->execute($bindings);
                        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                        // 개발자모드(dev_mode=1) 에서는 fallback 발동 사실 + 원래 에러 응답에 노출 — 디버깅 가시성
                        if (($params['dev_mode'] ?? '') === '1') {
                            $sqlError = '[FALLBACK] primary failed: ' . $sqlError;
                        } else {
                            $sqlError = null; // 일반 모드는 silent fallback
                        }
                    } catch (\Throwable $e2) {
                        $sqlError = $e2->getMessage();
                        $this->logger->warning('select fallback also failed', ['err' => $e2->getMessage()]);
                    }
                }
            }
        }

        // 첨부(attach/image) 필드: 각 행에 {alias}_midx 보강 — 목록 인라인 첨부(FileAttach)가
        // 기존 파일을 로드할 수 있게 함. attach 필드가 있을 때만 1쿼리 배치로 처리.
        if (!empty($data) && $mainTable) {
            $attachMidxCols = [];
            foreach ($fields as $f) {
                $ctl = $f['grid_ctl_name'] ?? '';
                if ($ctl !== 'attach' && $ctl !== 'image') continue;
                $aa = trim($f['alias_name'] ?? '');
                if ($aa === '') continue;
                $attachMidxCols[$aa] = $this->resolveColumn($mainTable, trim($f['db_field'] ?? $aa)) . '_midx';
            }
            if (!empty($attachMidxCols)) {
                $pkF0    = $fields[0] ?? [];
                $pkAlias = trim((string)($pkF0['alias_name'] ?? 'idx')) ?: 'idx';
                $pkCol   = $this->resolveColumn($mainTable, trim((string)($pkF0['db_field'] ?? 'idx')) ?: 'idx');
                $pks = [];
                foreach ($data as $rrow) { if (isset($rrow[$pkAlias]) && $rrow[$pkAlias] !== '') $pks[] = $rrow[$pkAlias]; }
                $pks = array_values(array_unique($pks));
                if (!empty($pks)) {
                    try {
                        $ph     = implode(',', array_fill(0, count($pks), '?'));
                        $colSel = implode(',', array_map(fn($c) => "`{$c}`", array_values($attachMidxCols)));
                        $stmt = $this->pdo->prepare("SELECT `{$pkCol}` AS __pk, {$colSel} FROM `{$mainTable}` WHERE `{$pkCol}` IN ({$ph})");
                        $stmt->execute($pks);
                        $midxMap = [];
                        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $mr) { $midxMap[(string)$mr['__pk']] = $mr; }
                        foreach ($data as &$rrow) {
                            $mr = $midxMap[(string)($rrow[$pkAlias] ?? '')] ?? null;
                            if (!$mr) continue;
                            foreach ($attachMidxCols as $aa => $col) { $rrow[$aa . '_midx'] = (int)($mr[$col] ?? 0); }
                        }
                        unset($rrow);
                    } catch (\Throwable) {}
                }
            }
        }

        // 캐시에는 훅 적용 전 원본 저장
        $result = [
            'success'  => true,
            'total'    => $total,
            'page'     => $page,
            'pageSize' => $pageSize,
            'data'     => $data,
            'fields'   => $fields,
            '_access'  => $access,
        ];

        $this->cache->set($cacheKey, $result);

        // _simple 모드: 행단위 훅(list_json_load) / row_buttons / aggregate / __html 모두 건너뛰고
        // raw 데이터 즉시 반환. fast xls 다운로드 전용 — 클라이언트가 raw 값으로 HTML 테이블 빌드.
        // before_query / list_query / list_json_init 같은 쿼리빌드 훅은 이미 적용된 상태.
        if (($params['_simple'] ?? '') === '1') {
            return $result;
        }

        // 행 단위 __html 주입 (list_json_init 은 이미 SELECT 전에 실행됨)
        // _client_css, _client_buttonText, _client_buttons 는 pageLoad()에서 설정 가능 → 여기서 초기화 안 함
        if (function_exists('list_json_load')) {
            $hasHtml = false;
            for ($__i = 0, $__len = count($result['data']); $__i < $__len; $__i++) {
                $result['data'][$__i]['__html'] = [];
                list_json_load($result['data'][$__i]);
                if (!empty($result['data'][$__i]['__html'])) {
                    $hasHtml = true;
                } else {
                    unset($result['data'][$__i]['__html']);
                }
            }
        }
        // row_buttons 훅 — list 셀 prefix 로 버튼 주입 (view 폼과 공유)
        for ($__i = 0, $__len = count($result['data']); $__i < $__len; $__i++) {
            $btnMap = self::_renderRowButtons($result['data'][$__i]);
            if (!empty($btnMap)) {
                if (!isset($result['data'][$__i]['__html'])) $result['data'][$__i]['__html'] = [];
                foreach ($btnMap as $alias => $btnHtml) {
                    $existing = $result['data'][$__i]['__html'][$alias] ?? null;
                    if ($existing === null) {
                        $rawVal = $result['data'][$__i][$alias] ?? '';
                        $existing = htmlspecialchars((string)$rawVal, ENT_QUOTES, 'UTF-8');
                    }
                    $result['data'][$__i]['__html'][$alias] = $existing . $btnHtml;
                }
            }
        }

        // aggregate — 부분합/총합 주입 (list_json_load 이후)
        if ($aggregateActive) {
            $result['data'] = $this->buildAggregateRows($result['data'], $result['fields'], $effectiveOrderby, $aggregate);
            $result['_aggregate'] = $aggregate;
        }

        if ($GLOBALS['_client_alert'] !== null) $result['_client_alert'] = $GLOBALS['_client_alert'];
        if ($GLOBALS['_client_toast'] !== null) $result['_client_toast'] = $GLOBALS['_client_toast'];
        if ($GLOBALS['_client_openTab'] !== null) $result['_client_openTab'] = $GLOBALS['_client_openTab'];
        if ($GLOBALS['_client_confirm'] !== null) $result['_client_confirm'] = $GLOBALS['_client_confirm'];
        if ($GLOBALS['_client_redirect'] !== null) $result['_client_redirect'] = $GLOBALS['_client_redirect'];
        if (!empty($GLOBALS['_onlyList'])) $result['_onlyList'] = true;
        if ($GLOBALS['_client_viewPref'] !== null) $result['_client_viewPref'] = $GLOBALS['_client_viewPref'];
        if ($GLOBALS['_client_css'] !== null) $result['_client_css'] = $GLOBALS['_client_css'];
        if ($GLOBALS['_client_js'] !== null) $result['_client_js'] = $GLOBALS['_client_js'];
        if ($GLOBALS['_client_buttonText'] !== null) $result['_client_buttonText'] = $GLOBALS['_client_buttonText'];
        if (!empty($GLOBALS['_client_buttons'])) $result['_client_buttons'] = $GLOBALS['_client_buttons'];
        if (!empty($GLOBALS['_client_saveAndNew'])) $result['_client_saveAndNew'] = true;
        if (!empty($GLOBALS['_client_disableSort'])) $result['_client_disableSort'] = true;
        if (!empty($GLOBALS['_client_alwaysModify'])) $result['_client_alwaysModify'] = true;

        // 필드 속성 동적 변경 (pageLoad/list_json_init에서 설정)
        // $GLOBALS['_client_fields'] = ['alias명' => ['col_title'=>'비고', 'grid_list_edit'=>'Y', ...]]
        if (!empty($GLOBALS['_client_fields']) && is_array($GLOBALS['_client_fields'])) {
            foreach ($result['fields'] as &$_f) {
                $alias = $_f['alias_name'] ?? '';
                if (isset($GLOBALS['_client_fields'][$alias])) {
                    $_f = array_merge($_f, $GLOBALS['_client_fields'][$alias]);
                }
            }
            unset($_f);
        }

        // 개발자 모드: SQL 디버그 정보 (캐시에는 저장 안 함)
        if (($params['dev_mode'] ?? '') === '1') {
            // 메뉴명 + 컬럼명 주석이 포함된 가독성 높은 SELECT SQL 빌드
            $menuName = $menu['menu_name'] ?? '';
            if ($fromSql && $selectCols) {
                $annotatedParts = [];
                foreach ($selectCols as $i => $colExpr) {
                    $title = $selectColTitles[$i] ?? '';
                    $prefix = $i === 0 ? '  ' : ', ';
                    $annotatedParts[] = ($title !== '' ? "  -- {$title}\n{$prefix}" : $prefix) . $colExpr;
                }
                $displayFrom = "`{$mainTable}` table_m";
                if ($joinClauses) $displayFrom .= "\n" . implode("\n", $joinClauses);
                $annotatedSelect  = "-- {$menuName}\n\nSELECT\n" . implode("\n", $annotatedParts);
                $annotatedSelect .= "\nFROM {$displayFrom}\n{$whereFull}";
                $displaySql = trim("{$annotatedSelect}\n{$orderSql}\n{$limitSql}");
                // list_query 훅이 실행된 경우 동일한 변환을 표시용 SQL 에도 적용
                if ($hookModified) {
                    $throwawayCount = '';
                    $this->callHook('list_query', $displaySql, $throwawayCount);
                }
            } else {
                $displaySql = trim("{$selectSql} {$orderSql} {$limitSql}");
            }
            // DB 드라이버별 SQL 변환 (실제 실행되는 형태로 표시) — backtick → ", [bracket] 등
            $displaySql = $this->translateDisplaySql($displaySql);
            $displayCountSql = $this->translateDisplaySql(trim($countSql));
            $result['_sql']       = $displaySql;
            $result['_count_sql'] = $displayCountSql;
            $result['_bindings']  = $bindings;
            if ($sqlError !== null) $result['_sql_error'] = $sqlError;
        }

        if (!empty($GLOBALS['_execSql_log']) && ($params['dev_mode'] ?? '') === '1') {
            $result['_execSql'] = $GLOBALS['_execSql_log'];
        }

        return $result;
    }

    // =========================================================================
    // 필터 selectbox 동적 항목 (act=filterItems)
    // grid_is_handle='s' 이고 items 가 비어있는 필드의 distinct 값 조회
    // =========================================================================
    // =========================================================================
    // 폼 레이아웃 저장 (act=saveFormLayout) — 관리자 전용
    // =========================================================================
    public function saveFormLayout(array $params, array $body, object $user): array
    {
        if (($user->is_admin ?? '') !== 'Y') {
            return ['success' => false, 'message' => '관리자만 사용할 수 있습니다.'];
        }

        $gubun = (int)($params['gubun'] ?? 0);
        if (!$gubun) return ['success' => false, 'message' => 'gubun 필수'];

        $items = $body['items'] ?? [];
        if (!is_array($items) || !count($items)) {
            return ['success' => false, 'message' => 'items 필수'];
        }

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE mis_menu_fields
                    SET grid_x = ?, grid_y = ?, grid_w = ?, grid_h = ?,
                        form_layout_responsive = ?
                  WHERE idx = ?'
            );
            $this->pdo->beginTransaction();
            foreach ($items as $item) {
                $lg = $item['lg'] ?? [];
                // 하위 브레이크포인트(sm, xs): null이면 lg 복사, 빈 배열이면 null 저장
                $responsive = [];
                foreach (['md', 'sm', 'xs'] as $bp) {
                    if (!empty($item[$bp])) $responsive[$bp] = $item[$bp];
                }
                $stmt->execute([
                    (int)($lg['x'] ?? -1),
                    (int)($lg['y'] ?? -1),
                    max(1, (int)($lg['w'] ?? 6)),
                    max(1, (int)($lg['h'] ?? 1)),
                    $responsive ? json_encode($responsive, JSON_UNESCAPED_UNICODE) : null,
                    (int)($item['idx'] ?? 0),
                ]);
            }
            $this->pdo->commit();

            // 캐시 무효화 (MIS Join이면 조인 대상 real_pid도 함께 무효화)
            // mis_menu_fields 직접 UPDATE → 스키마 캐시도 강제 flush
            $menu = $this->getMenu($gubun);
            $this->postWriteInvalidate($menu, $gubun, true);

            return ['success' => true];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $this->logger->error('saveFormLayout failed', ['err' => $e->getMessage()]);
            return ['success' => false, 'message' => 'DB 오류'];
        }
    }

    public function dropdownItems(array $params, object $user): array
    {
        $gubun = (int)($params['gubun'] ?? 0);
        $alias = trim($params['alias'] ?? '');

        if (!$gubun || $alias === '') {
            return ['success' => false, 'message' => 'gubun, alias 필수'];
        }

        // items 값 조회
        $stmt = $this->pdo->prepare(
            'SELECT f.items FROM mis_menu_fields f
               JOIN mis_menus m ON m.real_pid = f.real_pid
              WHERE m.idx = ? AND f.alias_name = ? AND f.useflag = \'1\'
              LIMIT 1'
        );
        $stmt->execute([$gubun, $alias]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) return ['success' => true, 'data' => []];

        $items = trim($row['items'] ?? '');
        if ($items === '') return ['success' => true, 'data' => []];

        // SQL 쿼리인 경우 실행 — items 에 v6 PascalCase (RealCid 등) 가 남아있을 수 있어 v6→v7 컬럼명 변환 적용
        if (preg_match('/^\s*select\s+/i', $items)) {
            $itemsSql = $this->resolveExpression($items, []);
            try {
                $stmt2 = $this->pdo->query($itemsSql);
                $rows  = $stmt2->fetchAll(\PDO::FETCH_ASSOC);
                $data  = array_map(fn($r) => [
                    'value' => (string)($r['value'] ?? ''),
                    'text'  => (string)($r['text']  ?? $r['value'] ?? ''),
                ], $rows);
                return ['success' => true, 'data' => $data];
            } catch (\Throwable $e) {
                return ['success' => false, 'message' => $e->getMessage(), '_sql' => $itemsSql];
            }
        }

        // JSON 배열인 경우 파싱
        $parsed = json_decode($items, true);
        if (is_array($parsed)) {
            $data = array_map(fn($o) => [
                'value' => (string)($o['value'] ?? ''),
                'text'  => (string)($o['text']  ?? $o['value'] ?? ''),
            ], $parsed);
            return ['success' => true, 'data' => $data];
        }

        // 쉼표 구분 문자열
        $data = array_map(fn($v) => ['value' => $v, 'text' => $v],
                          array_filter(array_map('trim', explode(',', $items))));
        return ['success' => true, 'data' => array_values($data)];
    }

    public function filterItems(array $params, object $user): array
    {
        $gubun = (int)($params['gubun'] ?? 0);
        $field = trim($params['field'] ?? '');

        if (!$gubun || $field === '') {
            return ['success' => false, 'message' => 'gubun, field 필수'];
        }

        $menu      = $this->getMenu($gubun);
        $fields    = $this->getFields($gubun, $menu, $user);
        $mainTable = $this->resolveTable(trim($menu['table_name'] ?? ''));
        $userId    = (string)($user->uid ?? '');

        [$selectCols, $joinClauses, $fieldMap, $aliasToTable] = $this->buildSelectFromFields($fields, $userId, $mainTable);

        $fieldExpr = $fieldMap[$field] ?? null;
        if (!$fieldExpr || !$mainTable) {
            return ['success' => true, 'data' => []];
        }

        $joinStr = $joinClauses ? ' ' . implode(' ', $joinClauses) : '';

        $baseFilter    = preg_replace('/^\s*(and|where)\s+/i', '', trim($menu['base_filter'] ?? ''));
        $baseFilter    = $this->resolveBaseFilter($baseFilter, $aliasToTable);
        $baseFilterSql = $baseFilter !== '' ? " AND ({$baseFilter})" : '';

        $useCond    = trim($menu['use_condition'] ?? '');
        $useCond    = $useCond !== '' ? $useCond : "table_m.useflag = '1'";
        $useCondSql = " AND ({$useCond})";

        $sql = "SELECT DISTINCT {$fieldExpr} AS v"
             . " FROM `{$mainTable}` table_m{$joinStr}"
             . " WHERE 1=1{$useCondSql}{$baseFilterSql}"
             . " ORDER BY v LIMIT 300";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([]);
            $items = array_filter(
                array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'v'),
                fn($v) => $v !== null && $v !== ''
            );
            return ['success' => true, 'data' => array_values($items)];
        } catch (\Throwable $e) {
            return ['success' => true, 'data' => []];
        }
    }

    // =========================================================================
    // prime_key 드롭다운 항목 (act=primeKeyItems)
    // prime_key 포맷: 표시필드#테이블명#정렬#값필드#추가조건
    // 표시필드는 단순 컬럼명 또는 concat(a,' ',b) 같은 복합 표현식 가능
    // 추가조건에서 @outer_tbname 은 실제 테이블 별칭으로 치환됨
    // =========================================================================
    public function primeKeyItems(array $params, object $user): array
    {
        $gubun = (int)($params['gubun'] ?? 0);
        $field = trim($params['field'] ?? '');  // alias_name

        if (!$gubun || $field === '') {
            return ['success' => false, 'message' => 'gubun, field 필수'];
        }

        // MisJoin (menu_type='06') 인 경우 fields 는 mis_join_pid 의 real_pid 기준으로 등록됨
        // → 효과 real_pid 로 prime_key 조회 (getMenu 가 _fields_real_pid 세팅)
        $menu = $this->getMenu($gubun);
        $effectiveRealPid = trim((string)($menu['_fields_real_pid'] ?? '')) ?: trim((string)($menu['real_pid'] ?? ''));
        if ($effectiveRealPid === '') {
            return ['success' => false, 'message' => '메뉴 정보 없음'];
        }

        $stmt = $this->pdo->prepare(
            'SELECT prime_key, alias_name FROM mis_menu_fields
              WHERE real_pid = ? AND alias_name = ? AND useflag = \'1\'
              LIMIT 1'
        );
        $stmt->execute([$effectiveRealPid, $field]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row || empty($row['prime_key'])) {
            return ['success' => true, 'data' => []];
        }

        $primeKey = trim($row['prime_key']);
        $parts    = array_map('trim', explode('#', $primeKey));

        // parts: [displayField, tableName, sortOrder, valueField, condition?]
        if (count($parts) < 4) {
            return ['success' => true, 'data' => []];
        }

        $rawDisplayField = $parts[0];
        $tableName       = $this->resolveTable($parts[1]);  // v6 PascalCase → v7 snake_case
        $rawSortOrder    = $parts[2] !== '' ? $parts[2] : '1';
        $rawValueField   = $parts[3];
        $rawCondition    = $parts[4] ?? '';

        $alias = $row['alias_name'];

        // 테이블 별칭: table_ + alias_name (snake/camel 모두 그대로 사용)
        // resolveExpression 이 db_field/group_compute 안의 alias 참조를 collapsed 매칭으로 재정렬해주므로
        // 표기 통일 (예: snake alias → table_com_g1) 이 가능.
        $tblAlias = 'table_' . $alias;

        // aliasToTable 맵 (resolveExpression 에서 컬럼명 v6→v7 변환에 사용)
        $aliasToTable = [$tblAlias => $tableName];

        // valueField: v6 컬럼명 → v7
        $valueField = $this->resolveColumn($tableName, $rawValueField);

        // sortOrder: 숫자면 그대로, 컬럼명이면 v6→v7 변환.
        // v6 'group!col2!col3' 표기 — 첫 컬럼은 select box 의 optgroup 라벨,
        // 나머지는 그룹 내 정렬용. SQL ORDER BY 는 모두 사용해 같은 그룹끼리 모이게 함.
        $groupCol = '';
        if (is_numeric($rawSortOrder)) {
            $sortOrder = $rawSortOrder;
        } else {
            $sortCols = array_filter(array_map('trim', explode('!', $rawSortOrder)), fn($c) => $c !== '');
            $sortCols = array_map(fn($c) => $this->resolveColumn($tableName, $c), $sortCols);
            if ($sortCols) {
                $groupCol  = $sortCols[0];
                $sortOrder = implode(', ', $sortCols);
            } else {
                $sortOrder = '1';
            }
        }

        // displayField 가 v6 helplist 형식 (col1;col2;col3 — 톱레벨 ';' 구분) 이면 CONCAT 으로 합쳐 표시.
        $dispCols = $this->splitTopLevelSemi($rawDisplayField);
        if (count($dispCols) > 1) {
            // 각 컬럼을 안전하게 CONCAT — NULL 도 ''로 변환 (CONCAT 의 NULL 처리는 DB마다 다름)
            $wrapped = array_map(fn($c) => 'COALESCE((' . $c . "),'')", $dispCols);
            $rawDisplayField = 'CONCAT(' . implode(", ' | ', ", $wrapped) . ')';
        }
        // displayField 표현식: @outer_tbname 치환 후 v6→v7 컬럼명 변환
        $displayExpr = str_replace('@outer_tbname', $tblAlias, $rawDisplayField);
        $displayExpr = $this->resolveExpression($displayExpr, $aliasToTable);

        // 조건: @outer_tbname / @idx 치환 + ctx_<col> 캐스케이드 오버라이드 + v6→v7 컬럼명 변환
        // @idx 규칙: view/modify (params['idx'] 전달) → 실제 idx 치환 / 그 외 → 조건 건너뜀
        // ctx_<col> 캐스케이드: prime_key 안의 `(select <col> from <table> where it_id=@idx)` 패턴을
        //   클라이언트 form 의 현재값으로 치환 (DB 저장값 무시) → 부모 dropdown 변경 시 자식 dropdown 즉시 재필터
        $condSql       = '';
        $dependsOnCols = []; // 응답에 실어줄 캐스케이드 의존 컬럼 목록
        if ($rawCondition !== '') {
            $skipExtra = false;
            if (str_contains($rawCondition, '@idx')) {
                $ctxIdx = trim((string)($params['idx'] ?? ''));
                if ($ctxIdx === '') {
                    $skipExtra = true;
                } else {
                    $safeIdx     = preg_replace('/[^0-9A-Za-z_\-]/', '', $ctxIdx);
                    $rawCondition = str_replace('@idx', $safeIdx, $rawCondition);
                }
            }
            if (!$skipExtra) {
                // ctx_<col> 오버라이드: `(select <col> from <anyTable> where it_id=...)` 패턴을 form 값으로 치환
                if (preg_match_all('/\(\s*select\s+(\w+)\s+from\s+\w+\s+where\s+it_id\s*=\s*[^\)]+\)/i', $rawCondition, $mm)) {
                    foreach (array_unique($mm[1]) as $depCol) {
                        $dependsOnCols[] = $depCol;
                        $ctxKey  = 'ctx_' . $depCol;
                        $hasCtx  = isset($params[$ctxKey]) && $params[$ctxKey] !== '';
                        // ctx 있으면 그 값으로, 없으면 NULL 로 치환 (it_id 컬럼 미존재로 인한 fallback SQL 에러 방지)
                        $replacement = $hasCtx
                            ? preg_replace('/[^0-9A-Za-z_\-]/', '', (string)$params[$ctxKey])
                            : 'NULL';
                        $rawCondition = preg_replace(
                            '/\(\s*select\s+' . preg_quote($depCol, '/') . '\s+from\s+\w+\s+where\s+it_id\s*=\s*[^\)]+\)/i',
                            $replacement,
                            $rawCondition
                        );
                    }
                }
                $cond    = str_replace('@outer_tbname', $tblAlias, $rawCondition);
                $cond    = $this->resolveExpression($cond, $aliasToTable);
                $condSql = " AND ({$cond})";
            }
        }

        // 표시값 AS 별칭: tblAlias + 'Qn' + displayField의 마지막 단순 식별자(PascalCase)
        // concat(a,' ',menuname) → 마지막 식별자 menuname → resolveColumn → menu_name → MenuName
        preg_match_all('/\b([A-Za-z_][A-Za-z0-9_]*)\b(?!\s*\()/', $rawDisplayField, $idMatches);
        $lastRawId      = end($idMatches[1]) ?: $rawDisplayField;
        $lastV7Col      = $this->resolveColumn($tableName, $lastRawId);
        $displaySuffix  = str_replace(' ', '', ucwords(str_replace('_', ' ', $lastV7Col)));
        $displayAlias   = $tblAlias . 'Qn' . $displaySuffix;

        // groupCol 이 displayField 와 다르면 별도 SELECT 컬럼으로 추가 (optgroup 라벨용)
        $groupSelect = '';
        $groupReadKey = '';
        if ($groupCol !== '' && $groupCol !== $valueField) {
            $groupReadKey = '__group';
            $groupSelect  = ", {$tblAlias}.{$groupCol} AS `{$groupReadKey}`";
        }

        $sql = "SELECT {$tblAlias}.{$valueField} AS `{$alias}`, {$displayExpr} AS `{$displayAlias}`{$groupSelect}"
             . " FROM `{$tableName}` {$tblAlias}"
             . " WHERE 111=111{$condSql}"
             . " ORDER BY {$sortOrder}";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([]);
            $rows  = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $items = array_map(function($r) use ($alias, $displayAlias, $groupReadKey) {
                $item = [
                    'value' => (string)($r[$alias]        ?? ''),
                    'text'  => (string)($r[$displayAlias] ?? ''),
                ];
                if ($groupReadKey !== '' && isset($r[$groupReadKey]) && $r[$groupReadKey] !== '') {
                    $item['group'] = (string)$r[$groupReadKey];
                }
                return $item;
            }, $rows);
            $result = ['success' => true, 'data' => $items];
            // 캐스케이드 의존 컬럼: 클라이언트가 ctx_<col> 로 form 현재값 보내야 자식 dropdown 이 정확히 필터됨
            if (!empty($dependsOnCols)) $result['depends_on'] = array_values(array_unique($dependsOnCols));
            if (($params['debug'] ?? '') === '1') $result['_sql'] = $sql;
            return $result;
        } catch (\Throwable $e) {
            $this->logger->warning('primeKeyItems query failed', [
                'gubun' => $gubun,
                'field' => $field,
                'sql'   => $sql,
                'err'   => $e->getMessage(),
            ]);
            $result = ['success' => false, 'data' => [], 'message' => $e->getMessage()];
            if (!empty($dependsOnCols)) $result['depends_on'] = array_values(array_unique($dependsOnCols));
            if (($params['debug'] ?? '') === '1') $result['_sql'] = $sql;
            return $result;
        }
    }

    // =========================================================================
    // QR 프린터 전용 엔드포인트 (act=qrPrint) — 외부 프린터(ps1 등) 가 호출.
    // 인증 우회 (AuthMiddleware::PUBLIC_ACTS 에 'qrPrint' 등록됨).
    // 동작: 일반 list 와 동일하게 첫 매칭 행을 가져온 뒤, 사용자로직의 qr_print(&$row)
    //       훅을 호출. 훅은 raw 텍스트(ZPL 등) 를 echo 하고 exit 처리.
    //       v6 의 /_mis/list_json.php?flag=readResult&addParam=print 와 같은 역할.
    // =========================================================================
    public function qrPrint(array $params): void
    {
        $gubun = (int)($params['gubun'] ?? 0);
        if (!$gubun) { http_response_code(400); echo ''; exit; }

        $menu = $this->getMenu($gubun);
        if (!$menu) { echo ''; exit; }

        // 가짜 admin user — 외부 프린터는 JWT 가 없음. 권한체크 우회용.
        $pseudoUser = (object)['uid' => 'qrprint', 'name' => 'qrprint', 'is_admin' => 'Y'];

        // dev_mode=1 → cache 우회 (프린터는 매번 최신 pending row 필요)
        $params['dev_mode'] = '1';
        $params['pageSize'] = 1;
        $params['page']     = 1;

        $listResult = $this->list($params, $pseudoUser);
        if (empty($listResult['success']) || empty($listResult['data'])) {
            // 빈 응답 — ps1 은 이걸 "더 이상 인쇄할 항목 없음" 신호로 해석
            header('Content-Type: text/plain; charset=utf-8');
            echo '';
            exit;
        }

        $row = $listResult['data'][0];

        // pageLoad / list_json_load 가 이미 loadProgram 단계에서 require 됨.
        // 사용자로직이 qr_print(&$row) 를 정의했으면 호출 (출력 + exit 는 훅 책임).
        if (function_exists('qr_print')) {
            header('Content-Type: text/plain; charset=utf-8');
            qr_print($row);
            exit;
        }

        // 훅 미정의 — 빈 응답
        header('Content-Type: text/plain; charset=utf-8');
        echo '';
        exit;
    }

    // =========================================================================
    // 단건 (act=view)
    // =========================================================================
    public function view(array $params, object $user): array
    {
        $gubun    = (int)($params['gubun'] ?? 0);
        $idxParam = trim((string)($params['idx'] ?? ''));

        $menu   = $this->getMenu($gubun);
        $fields = $this->getFields($gubun, $menu, $user);
        $actionFlag = ($params['actionFlag'] ?? '') === 'modify' ? 'modify' : 'view';

        // 접근 권한 체크
        $userIdV = (string)($user->uid ?? '');
        $globalAdminV = ($user->is_admin ?? '') === 'Y' ? 'Y' : '';
        $accessV = $this->checkMenuAccess($menu, $userIdV, $globalAdminV);
        if (!$accessV['read']) {
            return ['success' => false, 'data' => null, 'message' => '읽기권한이 없습니다.', '_access' => $accessV];
        }
        if ($actionFlag === 'modify' && !$accessV['write']) {
            return ['success' => false, 'data' => null, 'message' => '수정권한이 없습니다.', '_access' => $accessV];
        }
        $GLOBALS['_menuAccess'] = $accessV;
        if ($accessV['admin']) $GLOBALS['misSessionIsAdmin'] = 'Y';

        $this->setGlobals($params, $user, $menu, $actionFlag);
        $this->loadProgram($menu['real_pid'] ?? '', $menu);

        // 쿼리 빌드 전 초기화 훅
        $this->callHook('before_query', $menu, $fields, $params);

        $table = $this->resolveTable(trim($menu['table_name'] ?? ''));
        if (!$table || $idxParam === '') {
            return ['success' => false, 'data' => null, 'message' => '잘못된 요청입니다.'];
        }

        $userId = (string)($user->uid ?? '');
        // view 컨텍스트: prime_key 의 @idx 플레이스홀더를 URL idx 로 치환할 수 있게 idx 전달
        [$selectCols, $joinClauses, , $aliasToTable, $selectColTitles] = $this->buildSelectFromFields($fields, $userId, $table, $idxParam);

        $joinStr   = $joinClauses ? ' ' . implode(' ', $joinClauses) : '';
        $selectStr = $selectCols  ? implode(', ', $selectCols) : 'table_m.*';

        // 읽기전용 조건 — view 에도 __readonly 가상 컬럼 포함
        $readOnlyCondV = trim($menu['read_only_cond'] ?? '');
        if ($readOnlyCondV !== '') {
            $resolvedV = $this->resolveExpression($readOnlyCondV, $aliasToTable);
            $selectStr .= ", ({$resolvedV}) AS __readonly";
        }

        // PK 결정 — fields[0] (sort_order=1) 의 db_field 가 PK 컬럼
        $sortedFields = $fields;
        usort($sortedFields, fn($a, $b) => (int)($a['sort_order'] ?? 0) <=> (int)($b['sort_order'] ?? 0));
        $pkField   = $sortedFields[0] ?? [];
        $pkDbField = trim($pkField['db_field'] ?? 'idx');
        $pkCw      = (int)($pkField['col_width'] ?? 0);

        if ($pkCw === -1 || $pkCw === -2) {
            // PK 숨김 → URL idx 는 첫 번째 visible 필드값 → 그 필드로 조회.
            // col_width=0 (그리드 숨김/폼 표시) 도 lookup 후보 — PK 숨김 마커(-1, -2) 만 skip.
            $firstVisible = null;
            foreach ($sortedFields as $f) {
                $w = (int)($f['col_width'] ?? 0);
                if ($w !== -1 && $w !== -2) { $firstVisible = $f; break; }
            }
            if (!$firstVisible) {
                return ['success' => false, 'data' => null, 'message' => '잘못된 요청입니다.'];
            }
            $fvAlias   = $firstVisible['db_table'] ?? 'table_m';
            $fvDbField = $firstVisible['db_field']  ?? '';
            $v7t       = $aliasToTable[$fvAlias] ?? $table;
            $fvCol     = $this->resolveColumn($v7t, $fvDbField);
            $whereClause = "`{$fvAlias}`.`{$fvCol}` = ?";
            $whereValue  = $idxParam;
        } else {
            // 일반: fields[0] 의 db_field 를 실제 컬럼명으로 해석해 WHERE 생성
            $pkCol = $this->resolveColumn($table, $pkDbField ?: 'idx');
            $whereClause = "table_m.`{$pkCol}` = ?";
            $whereValue  = $idxParam;
        }

        // base_filter 적용 — 세션 플레이스홀더($misSessionUserId 등) + v6 PascalCase 컬럼명 (RealCid → real_cid) 모두 변환
        $baseFilterV = trim($menu['base_filter'] ?? '');
        if ($baseFilterV !== '') {
            // 선행 공백 / "and " 제거 — 사용자 정의가 "and ..." 로 시작할 수 있음
            $baseFilterV = preg_replace('/^\s*(?:and|AND)\s+/', '', $baseFilterV) ?? $baseFilterV;
            // 세션 변수 치환 (view 스코프의 $menu/$user 로 정확한 값 사용)
            $resolvedBF = $this->resolveSessionPlaceholders($baseFilterV, $menu, $user);
            // v6→v7 컬럼명 변환
            $resolvedBF = $this->resolveExpression($resolvedBF, $aliasToTable);
            if ($resolvedBF !== '') {
                $whereClause .= " AND ({$resolvedBF})";
            }
        }

        $viewSqlError = null;
        try {
            $viewSql = "SELECT {$selectStr} FROM `{$table}` table_m{$joinStr} WHERE {$whereClause} LIMIT 1";
            $this->callHook('view_query', $viewSql);
            $stmt = $this->pdo->prepare($viewSql);
            $stmt->execute([$whereValue]);
            $row = $stmt->fetch();
        } catch (\Throwable $e) {
            $viewSqlError = $e->getMessage();
            $this->logger->warning('view query failed, fallback', ['err' => $viewSqlError, 'gubun' => $gubun]);
            // 단순 fallback: 실제 PK 컬럼(resolveColumn 결과)로 재시도
            $fbPkCol = $this->resolveColumn($table, $pkDbField ?: 'idx');
            $row = false;
            try {
                $stmt = $this->pdo->prepare("SELECT * FROM `{$table}` WHERE `{$fbPkCol}` = ? LIMIT 1");
                $stmt->execute([$idxParam]);
                $row = $stmt->fetch();
            } catch (\Throwable) {}
        }

        // hit 카운트 +1 — 테이블에 hit 컬럼 있을 때, 브라우저 세션당 같은 레코드 1회만.
        // 행이 실제로 로드된 경우에만 카운트 (조회 실패한 idx 는 무시).
        if ($row && is_array($row)) {
            $tblCols   = $this->getTableColumnSet($table);
            $hitPkCol  = $this->resolveColumn($table, $pkDbField ?: 'idx');
            $hitPkVal  = $row[$hitPkCol] ?? $row['idx'] ?? null;
            $this->bumpHit($table, $tblCols, $hitPkCol, $hitPkVal, $menu['real_pid'] ?? '');
        }

        $template = function_exists('view_templete') ? view_templete() : null;

        // 인쇄양식: is_use_print=1 이고 템플릿 파일이 있으면 렌더링
        $printHtml = null;
        if (($menu['is_use_print'] ?? '') == '1' && $row) {
            $printFile = PROGRAMS_PATH . '/' . ($menu['real_pid'] ?? '') . '_print.html';
            if (file_exists($printFile)) {
                try {
                    $tplContent = file_get_contents($printFile);
                    $renderer = new \App\PrintRenderer($this->pdo);
                    $printHtml = $renderer->render($tplContent, $row, $fields, $row['idx'] ?? 0);
                } catch (\Throwable $e) {
                    $printHtml = '<p style="color:red">인쇄양식 오류: ' . htmlspecialchars($e->getMessage()) . '</p>';
                }
            }
        }

        // 첨부파일 필드의 _midx 컬럼 보강 (form 에 노출된 attach 필드만)
        // → FileAttach 가 midx 로 기존 파일 목록을 로드할 수 있게 함
        if ($row && is_array($row)) {
            $attachMidxCols = [];
            foreach ($fields as $f) {
                $ctl = $f['grid_ctl_name'] ?? '';
                if ($ctl !== 'attach' && $ctl !== 'image') continue;
                $aa = trim($f['alias_name'] ?? '');
                if ($aa === '' || array_key_exists($aa . '_midx', $row)) continue;
                $fCol = $this->resolveColumn($table, trim($f['db_field'] ?? $aa));
                $midxCol = $fCol . '_midx';
                $attachMidxCols[$aa . '_midx'] = $midxCol;
            }
            if (!empty($attachMidxCols)) {
                try {
                    $cols = implode(',', array_map(fn($c) => "`{$c}`", array_values($attachMidxCols)));
                    // PK 컬럼 — view 본 쿼리에서 사용한 동일 PK (g5_shop_item 의 it_id 등 idx 가 아닌 케이스 대응)
                    $realPkCol  = $this->resolveColumn($table, $pkDbField ?: 'idx');
                    $pkAliasNm  = trim((string)($pkField['alias_name'] ?? '')) ?: 'idx';
                    $pkVal      = $row[$pkAliasNm] ?? $row[$realPkCol] ?? $row['idx'] ?? null;
                    if ($pkVal !== null && $pkVal !== '') {
                        $stmt = $this->pdo->prepare("SELECT {$cols} FROM `{$table}` WHERE `{$realPkCol}` = ? LIMIT 1");
                        $stmt->execute([$pkVal]);
                        $extra = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
                        foreach ($attachMidxCols as $alias => $col) {
                            $row[$alias] = (int)($extra[$col] ?? 0);
                        }
                    }
                } catch (\Throwable) {}
            }
        }

        // textdecrypt2: 암호화 필드 값은 클라이언트에 노출하지 않음 (빈 문자열 치환)
        if ($row && is_array($row)) {
            foreach ($fields as $f) {
                if (($f['grid_ctl_name'] ?? '') !== 'textdecrypt2') continue;
                $a = trim($f['alias_name'] ?? '');
                if ($a !== '' && array_key_exists($a, $row)) $row[$a] = '';
            }
        }

        // 클라이언트 메시지 초기화
        $GLOBALS['_client_alert'] = null;
        $GLOBALS['_client_toast'] = null;
        $GLOBALS['_client_openTab'] = null;
        $GLOBALS['_client_formButtons'] = null;
        $GLOBALS['_client_saveAndNew'] = null;
        $GLOBALS['_client_belowForm'] = null;     // 폼 하단 패널: ['type'=>'siblingList', 'title'=>..., 'currentIdx'=>..., 'rows'=>[{idx,...}]]
        $this->callHook('view_load', $row);

        // row_buttons 훅 — view 폼 필드 라벨 옆에 버튼 주입 (list 셀과 공유)
        $fieldButtons = self::_renderRowButtons($row ?: []);

        // fields 도 함께 반환 — DataForm 이 별도 act=list 호출(pageSize=1) 없이도 폼을 그릴 수 있게.
        $viewResult = ['success' => true, 'data' => $row ?: null, 'fields' => $fields, 'template' => $template, 'printHtml' => $printHtml, '_access' => $accessV];
        if (!empty($fieldButtons)) $viewResult['_field_buttons'] = $fieldButtons;
        // SQL 에러가 있으면 항상 노출 (fallback 성공해도 경고 표시). 결과 없으면 실패로 처리.
        if ($viewSqlError !== null) {
            $viewResult['_sql_error'] = $viewSqlError;
            if (!$row) {
                $viewResult['success'] = false;
                $viewResult['message'] = '조회 쿼리 실행 실패: ' . $viewSqlError;
            }
        }
        if ($GLOBALS['_client_alert'] !== null) $viewResult['_client_alert'] = $GLOBALS['_client_alert'];
        if ($GLOBALS['_client_toast'] !== null) $viewResult['_client_toast'] = $GLOBALS['_client_toast'];
        if ($GLOBALS['_client_openTab'] !== null) $viewResult['_client_openTab'] = $GLOBALS['_client_openTab'];
        if (!empty($GLOBALS['_client_formButtons'])) $viewResult['_client_formButtons'] = $GLOBALS['_client_formButtons'];
        if (!empty($GLOBALS['_client_saveAndNew'])) $viewResult['_client_saveAndNew'] = true;
        if (!empty($GLOBALS['_client_belowForm'])) $viewResult['_client_belowForm'] = $GLOBALS['_client_belowForm'];
        if (($params['dev_mode'] ?? '') === '1') {
            $menuName = $menu['menu_name'] ?? '';
            $fromSql  = "`{$table}` table_m{$joinStr}";
            if ($selectCols) {
                $annotatedParts = [];
                foreach ($selectCols as $i => $colExpr) {
                    $title  = $selectColTitles[$i] ?? '';
                    $prefix = $i === 0 ? '  ' : ', ';
                    $annotatedParts[] = ($title !== '' ? "  -- {$title}\n{$prefix}" : $prefix) . $colExpr;
                }
                $displayFrom = "`{$table}` table_m";
                if ($joinClauses) $displayFrom .= "\n" . implode("\n", $joinClauses);
                $annotatedSelect = "-- {$menuName}\n\nSELECT\n" . implode("\n", $annotatedParts);
                $annotatedSelect .= "\nFROM {$displayFrom}\nWHERE {$whereClause}";
                // 실제 실행된 SQL 과 동일하게 보이도록 view_query 훅을 한 번 더 적용 (훅은 idempotent 이어야 함)
                $this->callHook('view_query', $annotatedSelect);
                $viewResult['_sql'] = $this->translateDisplaySql($annotatedSelect);
            } else {
                $displaySql = "SELECT {$selectStr} FROM {$fromSql} WHERE {$whereClause}";
                $this->callHook('view_query', $displaySql);
                $viewResult['_sql'] = $this->translateDisplaySql($displaySql);
            }
            $viewResult['_bindings'] = [$whereValue];
            if ($viewSqlError !== null) $viewResult['_sql_error'] = $viewSqlError;
        }
        if (!empty($GLOBALS['_execSql_log']) && ($params['dev_mode'] ?? '') === '1') {
            $viewResult['_execSql'] = $GLOBALS['_execSql_log'];
        }
        return $viewResult;
    }

    // =========================================================================
    // 저장 (act=save)
    // =========================================================================
    public function save(array $params, array $body, object $user): array
    {
        $gubun  = (int)($params['gubun'] ?? $body['gubun'] ?? 0);
        $idxRaw = trim($params['idx'] ?? $body['idx'] ?? '');

        $menu   = $this->getMenu($gubun);
        $fields = $this->getFields($gubun, $menu, $user);

        // 접근 권한 체크 (write 필수)
        $userIdS = (string)($user->uid ?? '');
        $globalAdminS = ($user->is_admin ?? '') === 'Y' ? 'Y' : '';
        $accessS = $this->checkMenuAccess($menu, $userIdS, $globalAdminS);
        if (!$accessS['write']) {
            return ['success' => false, 'message' => '저장/수정 권한이 없습니다.', '_access' => $accessS];
        }
        $GLOBALS['_menuAccess'] = $accessS;
        if ($accessS['admin']) $GLOBALS['misSessionIsAdmin'] = 'Y';

        // sort_order=1 필드 → PK 컬럼 결정 (WHERE 조건)
        usort($fields, fn($a, $b) => (int)($a['sort_order'] ?? 0) <=> (int)($b['sort_order'] ?? 0));
        $pkField   = $fields[0] ?? [];
        $pkAlias   = trim($pkField['alias_name'] ?? 'idx');
        $pkDbField = trim($pkField['db_field']  ?? 'idx');
        $pk0cw     = (int)($pkField['col_width'] ?? 0);

        // PK 값 결정: col_width=-1(숨김 PK)이면 body에서 실제 식별 필드로 기존 레코드 조회
        $isUpdate = false;
        $pkVal    = null;
        if ($idxRaw !== '' && $idxRaw !== '0') {
            if (ctype_digit($idxRaw)) {
                // 숫자 → 일반 idx PK
                $pkVal    = (int)$idxRaw;
                $isUpdate = true;
            } elseif ($pk0cw === -1 || $pk0cw === -2) {
                // 문자열 idx + 숨김 PK → 두 번째 필드(visible key)로 기존 레코드의 실제 PK 조회
                $table = $this->resolveTable(trim($menu['table_name'] ?? ''));
                if ($table) {
                    $visibleKey = $fields[1]['alias_name'] ?? '';
                    $visibleDbField = trim($fields[1]['db_field'] ?? '');
                    $visibleCol = $visibleDbField ? $this->resolveColumn($table, $visibleDbField) : '';
                    if ($visibleCol !== '') {
                        $lookupPkCol = $this->resolveColumn($table, $pkDbField ?: 'idx');
                        $lookStmt = $this->pdo->prepare("SELECT `{$lookupPkCol}` FROM `{$table}` WHERE `{$visibleCol}` = ? LIMIT 1");
                        $lookStmt->execute([$idxRaw]);
                        $foundPk = $lookStmt->fetchColumn();
                        if ($foundPk !== false) {
                            $pkVal    = $foundPk;
                            $isUpdate = true;
                        }
                    }
                }
            }
        }
        if ($pkVal === null) $pkVal = 0;
        $idx = is_int($pkVal) ? $pkVal : $pkVal;

        // read_only_cond 체크 — 수정 대상 행이 읽기전용이면 차단.
        // 단, max_length 가 ! 로 끝나는 필드(override) 가 하나라도 있으면 그 필드만 갱신 허용.
        // (첨부/이미지는 ! 가 multi-attach 의미라 override 대상 아님)
        $rowIsReadOnly = $isUpdate && $this->isRowReadOnly($menu, (int)$idx, $pkCol);
        if ($rowIsReadOnly) {
            $hasOverride = false;
            foreach ($fields as $f) {
                if (($f['db_table'] ?? '') !== 'table_m') continue;
                $ml  = (string)($f['max_length'] ?? '');
                if ($ml === '' || !str_ends_with($ml, '!')) continue;
                $ctl = $f['grid_ctl_name'] ?? '';
                if ($ctl === 'attach' || $ctl === 'image') continue;
                $hasOverride = true;
                break;
            }
            if (!$hasOverride) {
                return ['success' => false, 'message' => '이 행은 읽기전용입니다. 수정할 수 없습니다.'];
            }
        }

        $GLOBALS['isListEdit'] = !empty($body['_listEdit']);
        $GLOBALS['listEditField'] = $GLOBALS['isListEdit']
            ? array_keys(array_diff_key($body, array_flip(['_listEdit','_confirmed','idx','gubun','act','_csrf'])))
            : [];
        $this->setGlobals($params, $user, $menu, $isUpdate ? 'modify' : 'write');
        $this->loadProgram($menu['real_pid'] ?? '', $menu);

        // 쿼리 빌드 전 초기화 훅
        $this->callHook('before_query', $menu, $fields, $params);

        $table = $this->resolveTable(trim($menu['table_name'] ?? ''));
        if (!$table) return ['success' => false, 'message' => '테이블 정보가 없습니다.'];

        $pkCol = $this->resolveColumn($table, $pkDbField ?: 'idx');

        // 첨부파일(grid_ctl_name=attach/image) 필드 추출:
        //   - body['_tempAttach'] = { field_alias: [token1, token2, ...] }
        //   - filterData 전에 attach 컬럼 본체를 body 에서 제거 (post-insert 에서 UPDATE)
        $attachFields = [];
        foreach ($fields as $f) {
            $ctl = $f['grid_ctl_name'] ?? '';
            if ($ctl === 'attach' || $ctl === 'image') {
                $aa = trim($f['alias_name'] ?? '');
                if ($aa !== '') $attachFields[$aa] = $f;
            }
        }
        $tempAttach = $body['_tempAttach'] ?? [];
        if (!is_array($tempAttach)) $tempAttach = [];
        unset($body['_tempAttach']);
        foreach ($attachFields as $aa => $f) {
            // attach 본체 / _midx 컬럼은 finalize 시 서버에서 설정
            unset($body[$aa], $body[$aa . '_midx']);
        }

        $saveList   = $this->filterData($body, $fields, $table, $pkAlias);
        $updateList = $saveList;
        $afterScript = '';

        // rowReadOnly 행: override(! suffix) 컬럼만 갱신 허용
        if ($rowIsReadOnly) {
            $overrideCols = [];
            foreach ($fields as $f) {
                if (($f['db_table'] ?? '') !== 'table_m') continue;
                $ml  = (string)($f['max_length'] ?? '');
                if ($ml === '' || !str_ends_with($ml, '!')) continue;
                $ctl = $f['grid_ctl_name'] ?? '';
                if ($ctl === 'attach' || $ctl === 'image') continue;
                $dbField = trim($f['db_field'] ?? '');
                if ($dbField === '' || preg_match('/[\s(\'"]/', $dbField)) continue;
                $overrideCols[$this->resolveColumn($table, $dbField)] = true;
            }
            foreach (array_keys($updateList) as $col) {
                if (!isset($overrideCols[$col])) unset($updateList[$col]);
            }
            $saveList = $updateList; // 훅에 전달되는 사본도 동기화
            if (empty($updateList)) {
                // 변경 사항 없음 — 성공 처리 (사용자에게 차단 알림 대신 정상 종료)
                return ['success' => true, 'idx' => $idx, 'message' => '변경 사항 없음'];
            }
        }

        $GLOBALS['_client_confirm'] = null;
        $this->callHook('save_updateReady', $saveList);

        // confirm 요청: 저장 중단 + 확인 메시지 반환 (_confirmed 플래그 없을 때만)
        if ($GLOBALS['_client_confirm'] !== null && empty($body['_confirmed'])) {
            return ['success' => false, '_confirm' => $GLOBALS['_client_confirm']];
        }

        // textdecrypt2(암호화) 컬럼 목록 — buildUpdate/buildInsert 에서 AES_ENCRYPT 래핑
        $encCols = $this->collectEncryptCols($fields, $table);
        $pwdKey  = $_ENV['APP_PWD_KEY'] ?? 'secret';

        if ($isUpdate) {
            $this->callHook('save_updateBefore', $updateList);
            [$sql, $binds] = $this->buildUpdate($table, $updateList, $pkCol, $pkVal, $encCols, $pwdKey);
            $this->callHook('save_updateQueryBefore', $sql, $binds);
            $this->pdo->prepare($sql)->execute($binds);
        } else {
            $this->callHook('save_writeBefore', $updateList);
            [$sql, $binds] = $this->buildInsert($table, $updateList, $encCols, $pwdKey);
            $this->callHook('save_writeQueryBefore', $sql, $binds);
            $this->pdo->prepare($sql)->execute($binds);
            $idx = (int)$this->pdo->lastInsertId();
            $GLOBALS['newIdx'] = $idx;
        }

        // ── 첨부파일 finalize ────────────────────────────────────────────────
        // INSERT/UPDATE 후 $idx 가 확정된 시점에 temp → final 이동 + mis_attach_list 등록
        // 이후 UPDATE {table} SET {field}='names', {field}_midx=N WHERE pk=idx
        // ⚠ 순서: save_*After 훅보다 앞에 배치 — 그래야 After 훅이 mis_attach_list 의 새 행 + 갱신된 _midx 컬럼을
        //         이미 본 상태에서 후속 정규화(예: it_img1..20, it_explan)를 수행할 수 있음.
        if (!empty($attachFields) && $idx > 0) {
            $uid = (string)($user->uid ?? '');
            foreach ($attachFields as $aa => $f) {
                $tokens = $tempAttach[$aa] ?? null;
                if (!is_array($tokens) || empty($tokens)) continue;

                $fDbField = trim($f['db_field'] ?? $aa);
                $fCol     = $this->resolveColumn($table, $fDbField);
                $midxCol  = $fCol . '_midx';

                // UPDATE 인 경우 기존 midx 를 가져와서 같은 그룹에 합류
                $existingMidx = 0;
                if ($isUpdate) {
                    try {
                        $stmt = $this->pdo->prepare("SELECT `{$midxCol}` FROM `{$table}` WHERE `{$pkCol}` = ? LIMIT 1");
                        $stmt->execute([$pkVal]);
                        $existingMidx = (int)($stmt->fetchColumn() ?: 0);
                    } catch (\Throwable) {}
                }

                // 커스텀 경로: default_value 가 있으면 경로 템플릿으로 사용
                $customPath = null;
                $defaultVal = trim($f['default_value'] ?? '');
                if ($defaultVal !== '') {
                    // {alias} → 레코드 값 치환
                    $allVals = array_merge($body, $updateList);
                    // idx 는 방금 확정된 값
                    $allVals['idx'] = $idx;
                    // PK 컬럼명이 idx 가 아니면 (예: g5_shop_item.it_id) 그 이름으로도 노출
                    // 안 그러면 default_value 의 `{it_id}` 같은 토큰이 매칭 안 돼 리터럴로 남아 경로가 깨짐.
                    if ($pkCol !== '' && $pkCol !== 'idx') {
                        $allVals[$pkCol] = $idx;
                    }
                    $customPath = preg_replace_callback('/\{(\w+)\}/', function ($m) use ($allVals) {
                        return $allVals[$m[1]] ?? $m[0];
                    }, $defaultVal);
                }

                $fin = $this->fileManager->finalize($uid, $table, $fCol, $pkCol, $idx, $tokens, $existingMidx, $customPath);
                if (!empty($fin['success']) && $fin['count'] > 0) {
                    $upSql = "UPDATE `{$table}` SET `{$fCol}` = ?, `{$midxCol}` = ? WHERE `{$pkCol}` = ?";
                    try {
                        $this->pdo->prepare($upSql)->execute([$fin['file_names'], $fin['midx'], $idx]);
                    } catch (\Throwable $e) {
                        $this->logger->warning('attach field update failed', ['field' => $fCol, 'err' => $e->getMessage()]);
                    }
                }
            }
        }

        // ── save_*After 훅 — 첨부 finalize 완료 후 호출 ──────────────────────
        if ($isUpdate) {
            $this->callHook('save_updateAfter', $idx, $afterScript);
        } else {
            $this->callHook('save_writeAfter', $idx, $afterScript);
        }

        $this->postWriteInvalidate($menu, $gubun);
        // 작성/수정 이력 → mis_read_history (815 메뉴). mis_activity_logs(649) 는 로그인 관련 전용.
        $this->logReadHistory($isUpdate ? '수정' : '작성', $menu, $idx, $user);

        // PK 숨김(col_width=-1/-2) 메뉴는 URL idx 가 visible-key 값(예: real_pid='speedmis000631')이므로,
        // 저장 응답의 idx 도 동일한 visible-key 값으로 돌려줘야 FE 의 후속 view 재조회가 동일 행을 찾는다.
        $returnIdx = $idx;
        if ($pk0cw === -1 || $pk0cw === -2) {
            $visibleAlias   = $fields[1]['alias_name'] ?? '';
            $visibleDbField = trim($fields[1]['db_field'] ?? '');
            $visibleCol     = $visibleDbField ? $this->resolveColumn($table, $visibleDbField) : '';
            // INSERT 인 경우 본문(updateList)에 visible-key 가 없을 수 있으므로 DB 에서 직접 조회
            if ($visibleCol !== '') {
                try {
                    $vs = $this->pdo->prepare("SELECT `{$visibleCol}` FROM `{$table}` WHERE `{$pkCol}` = ? LIMIT 1");
                    $vs->execute([$idx]);
                    $vk = $vs->fetchColumn();
                    if ($vk !== false && $vk !== null && (string)$vk !== '') {
                        $returnIdx = (string)$vk;
                    }
                } catch (\Throwable) { /* keep $returnIdx as numeric PK */ }
            }
        }

        $result = ['success' => true, 'idx' => $returnIdx, 'afterScript' => $afterScript, 'message' => '저장되었습니다.'];
        if (!empty($GLOBALS['_client_alert'])) $result['_client_alert'] = $GLOBALS['_client_alert'];
        if (!empty($GLOBALS['_client_toast'])) $result['_client_toast'] = $GLOBALS['_client_toast'];
        if (!empty($GLOBALS['_client_openTab'])) $result['_client_openTab'] = $GLOBALS['_client_openTab'];
        // 훅이 edited 행 이외 값도 바꿀 수 있음을 알리는 신호 — 클라이언트가 해당 행 재조회하도록
        if (!empty($GLOBALS['_listEditReload'])) $result['_listEditReload'] = true;
        // 다른 행까지 영향받았을 때 — 클라이언트가 전체 목록 reload 하도록 (예: 267 aliasFix)
        if (!empty($GLOBALS['_listFullReload'])) $result['_listFullReload'] = true;
        // 저장 후 modify 폼 유지 (view 전환 안 함)
        if (!empty($GLOBALS['_client_stayOnModify'])) $result['_stayOnModify'] = true;

        if (($params['dev_mode'] ?? '') === '1') {
            $result['_sql']      = $sql;
            $result['_bindings'] = $binds;
            if (!empty($GLOBALS['_execSql_log'])) {
                $result['_execSql'] = $GLOBALS['_execSql_log'];
            }
        }

        return $result;
    }

    // =========================================================================
    // 삭제 (act=delete)
    // =========================================================================
    // =========================================================================
    // 간편추가 (act=briefInsert)
    // =========================================================================
    public function briefInsert(array $params, array $body, object $user): array
    {
        $gubun = (int)($params['gubun'] ?? $body['gubun'] ?? 0);
        $count = max(1, min(50, (int)($body['count'] ?? 1)));

        $menu = $this->getMenu($gubun);
        $table = $this->resolveTable(trim($menu['table_name'] ?? ''));
        $tpl = trim($menu['brief_insert_sql'] ?? '');
        if (!$table || !$tpl) return ['success' => false, 'message' => '간편추가 설정이 없습니다.'];

        $userId = (string)($user->uid ?? '');
        $parentIdx = trim($body['parent_idx'] ?? $params['parent_idx'] ?? '');

        // 변수 치환
        // 1) 세션 플레이스홀더 ($/@ 양쪽 표준 모두 지원) — 공통 헬퍼 사용
        $tpl = $this->resolveSessionPlaceholders($tpl, $menu, $user);
        // 2) parent_idx — 기존 패턴 호환 ($/@/PascalCase 모두 지원)
        $tpl = str_replace(
            ['$parentIdx', '$parent_idx', '@parentIdx', '@parent_idx'],
            $parentIdx,
            $tpl
        );

        // rep_ 접두어 처리: rep_XXX → $body['XXX'] 또는 $parentIdx
        $tpl = preg_replace_callback('/rep_(\w+)/i', function($m) use ($body, $parentIdx) {
            return $body[$m[1]] ?? $parentIdx;
        }, $tpl);

        // Rep_RealCid 등 특수 치환
        $tpl = str_replace('Rep_RealCid', $parentIdx, $tpl);

        $sql = "INSERT INTO `{$table}` {$tpl}";
        $insertedIds = [];

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare($sql);
            for ($i = 0; $i < $count; $i++) {
                $stmt->execute();
                $insertedIds[] = (int)$this->pdo->lastInsertId();
            }
            $this->pdo->commit();

            $this->postWriteInvalidate($menu, $gubun);

            // 삽입된 데이터 조회 (idx ASC)
            if (!empty($insertedIds)) {
                $placeholders = implode(',', array_fill(0, count($insertedIds), '?'));

                // 필드 정의로 SELECT 빌드
                $fields = $this->getFields($gubun, $menu, $user);
                $selectParts = [];
                foreach ($fields as $f) {
                    $alias = $f['alias_name'] ?? '';
                    $dbTable = trim($f['db_table'] ?? '');
                    $dbField = trim($f['db_field'] ?? '');
                    if ($alias && $dbTable === 'table_m' && $dbField) {
                        $col = $this->resolveColumn($table, $dbField);
                        $selectParts[] = "`{$col}` AS `{$alias}`";
                    }
                }
                $selectStr = !empty($selectParts) ? implode(', ', $selectParts) : '*';

                $dataSql = "SELECT {$selectStr} FROM `{$table}` WHERE idx IN ({$placeholders}) ORDER BY idx ASC";
                $dataStmt = $this->pdo->prepare($dataSql);
                $dataStmt->execute($insertedIds);
                $rows = $dataStmt->fetchAll(\PDO::FETCH_ASSOC);
            } else {
                $rows = [];
            }

            return [
                'success' => true,
                'message' => "{$count}건 추가 완료",
                'count'   => $count,
                'ids'     => $insertedIds,
                'data'    => $rows,
                'fields'  => $fields ?? [],
                '_sql'    => $sql,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return ['success' => false, 'message' => '간편추가 실패: ' . $e->getMessage(), '_sql' => $sql];
        }
    }

    public function delete(array $params, object $user): array
    {
        $gubun = (int)($params['gubun'] ?? 0);
        // PK 가 user_id 등 문자열인 메뉴(116번 'ID 관리' 등) 도 지원하려면 string 보존.
        // resolveIdxColumn 으로 결정된 PK 컬럼이 INT auto_increment 라면 PDO 가 적절히 처리.
        $idx   = trim((string)($params['idx'] ?? ''));

        if ($idx === '') return ['success' => false, 'message' => '삭제할 항목을 선택해주세요.'];

        $menu = $this->getMenu($gubun);

        // 접근 권한 체크 (write 필수)
        $userIdD = (string)($user->uid ?? '');
        $globalAdminD = ($user->is_admin ?? '') === 'Y' ? 'Y' : '';
        $accessD = $this->checkMenuAccess($menu, $userIdD, $globalAdminD);
        if (!$accessD['write']) {
            return ['success' => false, 'message' => '삭제 권한이 없습니다.', '_access' => $accessD];
        }
        $GLOBALS['_menuAccess'] = $accessD;
        if ($accessD['admin']) $GLOBALS['misSessionIsAdmin'] = 'Y';

        $this->setGlobals($params, $user, $menu, 'delete');
        $this->loadProgram($menu['real_pid'] ?? '', $menu);

        $table = $this->resolveTable(trim($menu['table_name'] ?? ''));
        if (!$table) return ['success' => false, 'message' => '테이블 정보가 없습니다.'];
        $fields = $this->getFields($gubun, $menu, $user);
        $pkCol  = $this->resolveIdxColumn($table, $fields);

        // read_only_cond 체크
        if ($this->isRowReadOnly($menu, $idx, $pkCol)) {
            return ['success' => false, 'message' => '이 행은 읽기전용입니다. 삭제할 수 없습니다.'];
        }

        $cancelDelete = false;
        $afterScript  = '';
        // hook 실행 전 client 메시지 채널 초기화 (이전 요청 잔존 방지)
        $GLOBALS['_client_alert'] = null;
        $GLOBALS['_client_toast'] = null;

        $this->callHook('save_deleteBefore', $idx, $cancelDelete);
        if ($cancelDelete) {
            return [
                'success'       => false,
                'message'       => $GLOBALS['_client_alert'] ?? $GLOBALS['_client_toast'] ?? '삭제가 취소되었습니다.',
                '_client_alert' => $GLOBALS['_client_alert'] ?? null,
                '_client_toast' => $GLOBALS['_client_toast'] ?? null,
            ];
        }

        $deleteQuery = trim($menu['delete_query'] ?? '');
        $pkCol = $this->resolveIdxColumn($table, $fields);
        if ($deleteQuery !== '') {
            // 삭제쿼리가 있으면 UPDATE 처리 (예: useflag=0, delchk='D')
            $this->pdo->prepare("UPDATE `{$table}` SET {$deleteQuery} WHERE `{$pkCol}` = ?")->execute([$idx]);
        } else {
            $this->pdo->prepare("DELETE FROM `{$table}` WHERE `{$pkCol}` = ?")->execute([$idx]);
        }

        $this->callHook('save_deleteAfter', $idx, $afterScript);
        $this->postWriteInvalidate($menu, $gubun);
        $this->logReadHistory('삭제', $menu, $idx, $user);

        return ['success' => true, 'afterScript' => $afterScript, 'message' => '삭제되었습니다.'];
    }

    // =========================================================================
    // 일괄 삭제 (act=bulkDelete)
    // =========================================================================
    public function bulkDelete(array $params, array $body, object $user): array
    {
        $gubun   = (int)($params['gubun'] ?? 0);
        $idxList = $body['idxList'] ?? [];
        if (!is_array($idxList) || empty($idxList)) {
            return ['success' => false, 'message' => '삭제할 항목을 선택해주세요.'];
        }
        // 빈값/0 만 제거. INT 캐스팅 X (한글 user_id 등 string PK 도 허용).
        $idxList = array_values(array_filter(
            array_map(fn($v) => is_string($v) ? trim($v) : $v, $idxList),
            fn($v) => $v !== '' && $v !== null && $v !== 0 && $v !== '0'
        ));
        if (empty($idxList)) return ['success' => false, 'message' => '유효한 항목이 없습니다.'];

        $menu = $this->getMenu($gubun);
        $this->setGlobals($params, $user, $menu, 'delete');
        $this->loadProgram($menu['real_pid'] ?? '', $menu);

        $table = $this->resolveTable(trim($menu['table_name'] ?? ''));
        if (!$table) return ['success' => false, 'message' => '테이블 정보가 없습니다.'];
        $fields = $this->getFields($gubun, $menu, $user);
        $pkCol  = $this->resolveIdxColumn($table, $fields);

        // save_bulkDeleteBefore 훅: 전체 idxList를 검증/수정 가능
        $cancelDelete = false;
        $this->callHook('save_bulkDeleteBefore', $idxList, $cancelDelete);
        if ($cancelDelete) {
            // 사용자로직이 toast 를 설정한 경우: 자체 처리(UPDATE 등) 후 framework DELETE 만 차단한 패턴 — success=true 로 간주.
            // 이렇게 하면 클라이언트가 catch 분기로 가지 않고 정상 success 분기에서 _client_toast 표시 + 목록 자동 갱신.
            $userHandled = !empty($GLOBALS['_client_toast']);
            if ($userHandled) {
                $this->postWriteInvalidate($menu, $gubun);
            }
            return [
                'success'       => $userHandled,
                'deleted'       => 0,
                'message'       => $GLOBALS['_client_alert'] ?? $GLOBALS['_client_toast'] ?? '삭제가 취소되었습니다.',
                '_client_alert' => $GLOBALS['_client_alert'] ?? null,
                '_client_toast' => $GLOBALS['_client_toast'] ?? null,
            ];
        }

        $deleted = 0;
        $skipped = 0;
        $afterScript = '';
        foreach ($idxList as $idx) {
            // 개별 deleteBefore 훅도 호출
            $cancelOne = false;
            $this->callHook('save_deleteBefore', $idx, $cancelOne);
            if ($cancelOne) continue;

            // read_only_cond 체크 — readonly 행은 건너뜀
            if ($this->isRowReadOnly($menu, $idx, $pkCol)) { $skipped++; continue; }

            // PK 컬럼: resolveIdxColumn 결과(=실제 테이블 PK 컬럼명) 를 그대로 사용.
            // 단건 delete() 와 동일한 처리. 'idx' 컬럼이 없는 테이블(g5/carparts 등)에서도 정상 동작.
            $deleteQuery = trim($menu['delete_query'] ?? '');
            if ($deleteQuery !== '') {
                $stmt = $this->pdo->prepare("UPDATE `{$table}` SET {$deleteQuery} WHERE `{$pkCol}` = ?");
            } else {
                $stmt = $this->pdo->prepare("DELETE FROM `{$table}` WHERE `{$pkCol}` = ?");
            }
            $stmt->execute([$idx]);
            // 실제 영향 받은 행만 카운트 (PK 매칭 실패 시 응답 부풀리지 않도록)
            if ($stmt->rowCount() <= 0) { continue; }

            $this->callHook('save_deleteAfter', $idx, $afterScript);
            $this->logReadHistory('삭제', $menu, $idx, $user);
            $deleted++;
        }

        // save_bulkDeleteAfter 훅: 삭제 완료 후 후처리
        $this->callHook('save_bulkDeleteAfter', $idxList, $deleted);

        $this->postWriteInvalidate($menu, $gubun);

        $msg = "{$deleted}건 삭제 완료";
        if ($skipped > 0) $msg .= " / 읽기전용 {$skipped}건 제외";
        return [
            'success'       => true,
            'deleted'       => $deleted,
            'skipped'       => $skipped,
            'total'         => count($idxList),
            'message'       => $msg,
            'afterScript'   => $afterScript,
            '_client_alert' => $GLOBALS['_client_alert'] ?? null,
            '_client_toast' => $GLOBALS['_client_toast'] ?? null,
        ];
    }

    // =========================================================================
    // 셀 범위 붙여넣기 일괄저장 (act=bulkListSave)
    //   body: { edits: [{ idx, <alias>: value, ... }, ...] }
    //   grid_list_edit='Y' + db_table='table_m' + schema_type='text' 만 허용
    //   컬럼별 CASE idx WHEN... 단일 UPDATE
    // =========================================================================
    public function bulkListSave(array $params, array $body, object $user): array
    {
        $gubun = (int)($params['gubun'] ?? 0);
        $edits = $body['edits'] ?? [];
        if (!is_array($edits) || !$edits) {
            return ['success' => false, 'message' => '수정할 내용이 없습니다.'];
        }
        $menu  = $this->getMenu($gubun);
        $userId = (string)($user->uid ?? '');
        $globalAdmin = ($user->is_admin ?? '') === 'Y' ? 'Y' : '';
        $access = $this->checkMenuAccess($menu, $userId, $globalAdmin);
        if (empty($access['admin'])) {
            return ['success' => false, 'message' => '해당 프로그램의 admin 권한이 필요합니다.'];
        }

        $this->setGlobals($params, $user, $menu, 'save');
        $table = $this->resolveTable(trim($menu['table_name'] ?? ''));
        if (!$table) return ['success' => false, 'message' => '테이블 정보가 없습니다.'];

        $fields = $this->getFields($gubun, $menu, $user);
        $aliasToCol = [];
        foreach ($fields as $f) {
            if (($f['grid_list_edit'] ?? '') !== 'Y') continue;
            if (($f['db_table'] ?? '') !== 'table_m') continue;
            // schema_type: 클라이언트와 동일하게 '', 'text', 'number*' 허용 (select/date/datetime/attach/image/html 등 제외)
            $st = trim((string)($f['schema_type'] ?? ''));
            $ok = ($st === '' || $st === 'text' || strncmp($st, 'number', 6) === 0);
            if (!$ok) continue;
            $alias = trim($f['alias_name'] ?? '');
            $col   = trim($f['db_field']   ?? '');
            if ($alias === '' || $col === '') continue;
            $aliasToCol[$alias] = $this->resolveColumn($table, $col);
        }
        if (!$aliasToCol) {
            return ['success' => false, 'message' => '편집 가능한 텍스트 필드가 없습니다.'];
        }

        $changes = [];
        $idxSet  = [];
        foreach ($edits as $row) {
            $idx = (int)($row['idx'] ?? 0);
            if ($idx <= 0) continue;
            foreach ($row as $alias => $value) {
                if ($alias === 'idx') continue;
                if (!isset($aliasToCol[$alias])) continue;
                $col = $aliasToCol[$alias];
                $changes[$col][$idx] = ($value === null ? null : (string)$value);
                $idxSet[$idx] = true;
            }
        }
        if (!$changes) return ['success' => false, 'message' => '변경 내용이 없습니다.'];

        $idxList = array_keys($idxSet);
        try {
            $this->pdo->beginTransaction();
            $totalAffected = 0;
            foreach ($changes as $col => $map) {
                $caseParts = [];
                $binds     = [];
                $idxs      = [];
                foreach ($map as $idx => $val) {
                    $caseParts[] = "WHEN ? THEN ?";
                    $binds[]     = $idx;
                    $binds[]     = $val;
                    $idxs[]      = $idx;
                }
                $inPh = implode(',', array_fill(0, count($idxs), '?'));
                $sql  = "UPDATE `{$table}` SET `{$col}` = CASE idx " . implode(' ', $caseParts)
                      . " ELSE `{$col}` END WHERE idx IN ({$inPh})";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(array_merge($binds, $idxs));
                $totalAffected += $stmt->rowCount();
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return ['success' => false, 'message' => '일괄저장 실패: ' . $e->getMessage()];
        }

        $this->postWriteInvalidate($menu, $gubun);
        foreach ($idxList as $idx) $this->logReadHistory('수정', $menu, $idx, $user);

        return [
            'success'  => true,
            'rows'     => count($idxList),
            'columns'  => count($changes),
            'affected' => $totalAffected,
            'message'  => count($idxList) . '건 일괄저장 완료',
        ];
    }

    // =========================================================================
    // 일괄 복원 (act=bulkRestore) — delete_query 반대값(=1)으로 되돌림
    // =========================================================================
    public function bulkRestore(array $params, array $body, object $user): array
    {
        [$gubun, $idxList, $menu, $table, $err] = $this->prepareBulkDeletedOp($params, $body, $user);
        if ($err) return $err;

        // delete_query 에서 컬럼명 추출 후 = '1' 로 복원
        $deleteQuery = trim($menu['delete_query'] ?? '');
        if ($deleteQuery === '') return ['success' => false, 'message' => '이 프로그램은 삭제 플래그가 정의되지 않았습니다.'];
        if (!preg_match('/^\s*(\w+)\s*=/', $deleteQuery, $m)) {
            return ['success' => false, 'message' => "delete_query 형식 오류: {$deleteQuery}"];
        }
        $col = $m[1];

        $fieldsR = $this->getFields($gubun, $menu, $user);
        $pkColR  = $this->resolveIdxColumn($table, $fieldsR);
        $ph = implode(',', array_fill(0, count($idxList), '?'));
        $sql = "UPDATE `{$table}` SET `{$col}` = '1' WHERE `{$pkColR}` IN ({$ph})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($idxList);
        $affected = $stmt->rowCount();

        $this->postWriteInvalidate($menu, $gubun);
        return ['success' => true, 'affected' => $affected, 'message' => "{$affected}건 복원 완료"];
    }

    // =========================================================================
    // 완전삭제 (act=bulkPermanentDelete) — 실제 DELETE (복구 불가)
    // =========================================================================
    public function bulkPermanentDelete(array $params, array $body, object $user): array
    {
        [$gubun, $idxList, $menu, $table, $err] = $this->prepareBulkDeletedOp($params, $body, $user);
        if ($err) return $err;

        $fieldsP = $this->getFields($gubun, $menu, $user);
        $pkColP  = $this->resolveIdxColumn($table, $fieldsP);
        $ph = implode(',', array_fill(0, count($idxList), '?'));
        $sql = "DELETE FROM `{$table}` WHERE `{$pkColP}` IN ({$ph})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($idxList);
        $affected = $stmt->rowCount();

        $this->postWriteInvalidate($menu, $gubun);
        foreach ($idxList as $idx) $this->logReadHistory('삭제', $menu, $idx, $user);
        return ['success' => true, 'affected' => $affected, 'message' => "{$affected}건 완전삭제 완료"];
    }

    /**
     * bulkRestore / bulkPermanentDelete 공통 전처리
     * @return array [$gubun, $idxList, $menu, $table, $errOrNull]
     */
    private function prepareBulkDeletedOp(array $params, array $body, object $user): array
    {
        $gubun   = (int)($params['gubun'] ?? 0);
        $idxList = $body['idxList'] ?? [];
        if (!is_array($idxList) || !$idxList) {
            return [$gubun, [], [], '', ['success' => false, 'message' => '항목을 선택하세요.']];
        }
        $idxList = array_values(array_filter(array_map('intval', $idxList), fn($v) => $v > 0));
        if (!$idxList) return [$gubun, [], [], '', ['success' => false, 'message' => '유효한 항목이 없습니다.']];

        $menu = $this->getMenu($gubun);
        if ((($user->is_admin ?? '') !== 'Y')) {
            return [$gubun, $idxList, $menu, '', ['success' => false, 'message' => '관리자 권한이 필요합니다.']];
        }
        $table = $this->resolveTable(trim($menu['table_name'] ?? ''));
        if (!$table) return [$gubun, $idxList, $menu, '', ['success' => false, 'message' => '테이블 정보가 없습니다.']];

        return [$gubun, $idxList, $menu, $table, null];
    }

    /**
     * 특정 idx 가 read_only_cond 평가 결과 '1' 인지 확인 (읽기전용 여부)
     * 읽기전용이면 true → 수정/삭제 차단
     */
    private function isRowReadOnly(array $menu, int|string $idx, ?string $pkCol = null): bool
    {
        $cond = trim($menu['read_only_cond'] ?? '');
        if ($cond === '') return false;
        $table = $this->resolveTable(trim($menu['table_name'] ?? ''));
        if (!$table) return false;
        try {
            // read_only_cond 는 "table_m" alias 를 참조할 수 있으므로 그대로 감싸 평가
            // pkCol 미지정 시 'idx' 폴백 (호출처가 모르는 경우)
            $col = $pkCol !== null && $pkCol !== '' ? $pkCol : 'idx';
            $sql = "SELECT ({$cond}) AS ro FROM `{$table}` table_m WHERE table_m.`{$col}` = ? LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$idx]);
            $v = $stmt->fetchColumn();
            return (int)$v === 1;
        } catch (\Throwable $e) {
            $this->logger->warning('isRowReadOnly eval failed', ['err' => $e->getMessage(), 'idx' => $idx]);
            return false;
        }
    }

    /**
     * delete_query ("useflag=0" 형태) → WHERE 조건 문자열 ("table_m.useflag = '0'")
     */
    private function deleteQueryToCondition(string $deleteQuery): string
    {
        if ($deleteQuery === '') return '';
        if (!preg_match('/^\s*(\w+)\s*=\s*[\'"]?([^\'"]*)[\'"]?\s*$/', $deleteQuery, $m)) return '';
        $col = $m[1];
        $val = $m[2];
        return "table_m.`{$col}` = '" . addslashes($val) . "'";
    }

    // =========================================================================
    // PK 컬럼 결정 — fields[0] (sort_order=1) 의 db_field 를 실제 컬럼명으로 해석
    // 6083 처럼 g5_shop_item.it_id 같이 idx 가 아닌 PK 를 가진 테이블 대응
    // =========================================================================
    private function resolveIdxColumn(string $table, array $fields): string
    {
        $sortedFields = $fields;
        usort($sortedFields, fn($a, $b) => (int)($a['sort_order'] ?? 0) <=> (int)($b['sort_order'] ?? 0));
        $pkField   = $sortedFields[0] ?? [];
        $pkDbField = trim($pkField['db_field'] ?? '');
        $pkCw      = (int)($pkField['col_width'] ?? 0);

        // PK 숨김(-1/-2) 인 경우: URL idx 는 첫 번째 visible 필드값 → 그 필드의 db_field 가 lookup 컬럼
        if ($pkCw === -1 || $pkCw === -2) {
            foreach ($sortedFields as $f) {
                $w = (int)($f['col_width'] ?? 0);
                if ($w !== -1 && $w !== -2) {
                    // table_m 컬럼만 대상 (조인 컬럼은 PK 가 될 수 없음)
                    $fvAlias = $f['db_table'] ?? 'table_m';
                    if ($fvAlias === 'table_m' || $fvAlias === '') {
                        return $this->resolveColumn($table, trim($f['db_field'] ?? '') ?: 'idx');
                    }
                }
            }
        }
        return $this->resolveColumn($table, $pkDbField ?: 'idx');
    }

    // =========================================================================
    // treat 훅 (act=treat)
    // =========================================================================
    public function treat(array $params, array $body, object $user): array
    {
        $gubun = (int)($params['gubun'] ?? 0);
        $menu  = $this->getMenu($gubun);
        $this->setGlobals($params, $user, $menu, 'treat');
        $this->loadProgram($menu['real_pid'] ?? '', $menu);

        $result = array_merge($params, $body);
        $this->callHook('addLogic_treat', $result);

        // 훅이 데이터를 변경하고 목록/뷰 리로드를 요청하면(reloadList/reloadView),
        // 직후의 mis:reloadGrid → list() 가 stale 캐시를 받지 않도록 이 프로그램 캐시를 무효화.
        // (정상 save 흐름과 달리 treat 훅의 직접 INSERT/UPDATE/DELETE 는 자동 무효화가 없어
        //  새로 추가한 행이 캐시 만료 전까지 안 보이는 레이스를 유발했음)
        if (!empty($result['reloadList']) || !empty($result['reloadView'])) {
            $rp = (string)($menu['real_pid'] ?? '');
            try { $this->cache->invalidateByRealPid($rp !== '' ? $rp : "g{$gubun}"); } catch (\Throwable) {}
        }

        return ['success' => true, 'data' => $result];
    }

    // =========================================================================
    // 내부 헬퍼
    // =========================================================================

    /**
     * row_buttons 훅 호출 → alias 별 버튼 HTML 맵
     * 사용자정의:
     *   function row_buttons(&$row, array &$buttons): void {
     *       // 내부 탭 열기 (gubun/real_pid 기반)
     *       $buttons['it_name'][] = ['label' => '연결', 'real_pid' => 'speedmis000xxx', 'idx' => $row['idx']];
     *       // 외부 URL 링크
     *       $buttons['it_name'][] = ['label' => '프론트', 'url' => 'https://...', 'target' => '_blank'];
     *       // 원시 HTML 직접 주입
     *       $buttons['it_name'][] = '<a href="...">사용자정의</a>';
     *   }
     * @return array ['alias_name' => '<button>...</button>...', ...]  (빈 결과면 [])
     */
    private static function _renderRowButtons(array $row): array
    {
        if (!function_exists('row_buttons')) return [];
        $buttons = [];
        try { row_buttons($row, $buttons); } catch (\Throwable $e) { return []; }
        if (!is_array($buttons) || empty($buttons)) return [];

        $html = [];
        foreach ($buttons as $alias => $btnList) {
            if (!is_array($btnList) || empty($btnList)) continue;
            $s = '';
            foreach ($btnList as $btn) {
                if (is_string($btn)) { $s .= $btn; continue; }
                if (!is_array($btn)) continue;
                $label = (string)($btn['label'] ?? '실행');
                if (!empty($btn['url'])) {
                    $url    = htmlspecialchars((string)$btn['url'], ENT_QUOTES, 'UTF-8');
                    $target = htmlspecialchars((string)($btn['target'] ?? '_blank'), ENT_QUOTES, 'UTF-8');
                    $cls    = htmlspecialchars((string)($btn['class'] ?? 'btn-open'), ENT_QUOTES, 'UTF-8');
                    $s .= '<a class="' . $cls . '" href="' . $url . '" target="' . $target . '">' . htmlspecialchars($label) . '</a>';
                } else {
                    $opts = $btn;
                    unset($opts['label']);
                    if (isset($opts['realPid']))  { $opts['real_pid']  = $opts['realPid'];  unset($opts['realPid']); }
                    if (isset($opts['openFull'])) { $opts['open_full'] = $opts['openFull']; unset($opts['openFull']); }
                    if (isset($opts['linkVal']))  { $opts['link_val']  = $opts['linkVal'];  unset($opts['linkVal']); }
                    $s .= openTabBtn($label, $opts);
                }
            }
            if ($s !== '') $html[$alias] = $s;
        }
        return $html;
    }

    /**
     * 쓰기 작업(save/delete/bulk*) 후 캐시 무효화 공통 처리
     * - 데이터 캐시: real_pid 기준 flush + MIS Join 대상도 함께
     *               + 같은 테이블 또는 그 테이블을 참조하는 뷰를 사용하는 다른 메뉴들 (예: 6062 저장 → 6038 도 함께 invalidate)
     * - 스키마 캐시: 수정된 테이블이 mis_menus / mis_menu_fields 이면 전역 flush
     *               $forceSchema=true 면 table_name 무관하게 스키마 flush (saveFormLayout 등)
     */
    private function postWriteInvalidate(array $menu, int $gubun, bool $forceSchema = false): void
    {
        $realPid = $menu['real_pid'] ?? "g{$gubun}";
        $this->cache->invalidateByRealPid($realPid);
        if (!empty($menu['_fields_real_pid'])) {
            $this->cache->invalidateByRealPid($menu['_fields_real_pid']);
        }

        $tableName = trim($menu['table_name'] ?? '');
        $tbl       = strtolower($tableName);

        // 같은 테이블을 직접/뷰를 통해 사용하는 형제 메뉴 캐시도 함께 무효화
        if ($tableName !== '' && $tbl !== 'mis_menus' && $tbl !== 'mis_menu_fields') {
            $this->invalidateSiblingMenusByTable($tableName, $realPid);
        }

        if ($forceSchema || $tbl === 'mis_menus' || $tbl === 'mis_menu_fields') {
            $this->cache->invalidateSchemaCache();
        }
    }

    /**
     * 주어진 테이블/뷰 를 사용하는 다른 메뉴들 캐시 무효화.
     * 직접 사용 (mis_menus.table_name = X) + 간접 사용 (X 기반 뷰를 table_name 으로 가진 메뉴) 모두 처리.
     */
    private function invalidateSiblingMenusByTable(string $tableName, string $excludeRealPid): void
    {
        try {
            // 1) 직접 같은 table_name 사용
            $stmt = $this->pdo->prepare(
                "SELECT real_pid FROM mis_menus
                  WHERE table_name = ? AND real_pid <> ? AND use_yn = '1'"
            );
            $stmt->execute([$tableName, $excludeRealPid]);
            $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            // 2) 이 테이블을 참조하는 뷰들 → 그 뷰를 사용하는 메뉴
            //    INFORMATION_SCHEMA.VIEW_TABLE_USAGE 는 MariaDB 10.5+ 에서 표준 제공
            $stmt = $this->pdo->prepare(
                "SELECT m.real_pid
                   FROM INFORMATION_SCHEMA.VIEW_TABLE_USAGE v
                   JOIN mis_menus m ON m.table_name = v.VIEW_NAME
                  WHERE v.TABLE_SCHEMA = DATABASE()
                    AND v.TABLE_NAME = ?
                    AND m.real_pid <> ?
                    AND m.useflag = '1'"
            );
            $stmt->execute([$tableName, $excludeRealPid]);
            $rows = array_merge($rows, $stmt->fetchAll(\PDO::FETCH_COLUMN));

            foreach (array_unique(array_filter($rows)) as $rp) {
                $this->cache->invalidateByRealPid((string)$rp);
            }
        } catch (\Throwable $e) {
            // VIEW_TABLE_USAGE 미지원 / 권한 부족 등 — 직접 사용 메뉴만 정리
            $this->logger->warning('invalidateSiblingMenusByTable failed', ['err' => $e->getMessage()]);
        }
    }

    /**
     * 백업 JSON 파일을 list 응답으로 변환 (읽기전용 보기).
     * 클라이언트가 [열기] 클릭 시 ?_backup=<jsonname> 파라미터로 진입 → 정상 list 대신 이 메서드가 응답.
     */
    private function loadBackupAsList(array $params, object $user): array
    {
        $gubun    = (int)($params['gubun'] ?? 0);
        $jsonname = trim((string)($params['_backup'] ?? ''));
        if ($gubun <= 0 || $jsonname === '') {
            return ['success' => false, 'message' => '잘못된 백업 요청 (gubun/_backup 누락)'];
        }
        // 보안: 경로 탈출 차단
        if (str_contains($jsonname, '..') || str_contains($jsonname, '/') || str_contains($jsonname, '\\')) {
            return ['success' => false, 'message' => '잘못된 파일명'];
        }

        $menu    = $this->getMenu($gubun);
        $realPid = (string)($menu['real_pid'] ?? '');
        if ($realPid === '') {
            return ['success' => false, 'message' => '메뉴 정보 없음'];
        }

        // 권한: 해당 프로그램 read 권한 (보는 것은 일반 사용자도 가능)
        $userId = (string)($user->uid ?? '');
        $globalAdmin = ($user->is_admin ?? '') === 'Y' ? 'Y' : '';
        $access = $this->checkMenuAccess($menu, $userId, $globalAdmin);
        if (empty($access['read'])) {
            return ['success' => false, 'message' => '읽기권한이 없습니다.'];
        }

        $fpath = __DIR__ . '/../../uploadFiles/backups/' . $realPid . '/' . $jsonname;
        if (!file_exists($fpath)) {
            return ['success' => false, 'message' => '백업 파일 없음: ' . $jsonname];
        }
        $content = @file_get_contents($fpath);
        if ($content === false) {
            return ['success' => false, 'message' => '백업 파일 읽기 실패'];
        }
        $payload = json_decode($content, true);
        if (!is_array($payload) || !isset($payload['data']) || !isset($payload['fields'])) {
            return ['success' => false, 'message' => '백업 파일 형식 오류'];
        }

        $rows   = is_array($payload['data'])   ? $payload['data']   : [];
        $fields = is_array($payload['fields']) ? $payload['fields'] : [];
        $meta   = is_array($payload['_meta'] ?? null) ? $payload['_meta'] : [];

        // 페이징: URL 의 page/pageSize 따라 슬라이스 (그리드 하단 페이저와 일치하도록)
        $total    = count($rows);
        $page     = max(1, (int)($params['page'] ?? 1));
        $pageSize = (int)($params['pageSize'] ?? $params['psize'] ?? 25);
        if ($pageSize <= 0) $pageSize = 25;
        $offset = ($page - 1) * $pageSize;
        $pagedRows = array_slice($rows, $offset, $pageSize);

        // jsonname 에서 타임스탬프 추출 — {userid}_{YYYYMMDD_HHmmss}.json → 20260505_061743
        $tsLabel = '';
        if (preg_match('/_(\d{8}_\d{6})\.json$/', $jsonname, $m)) {
            $tsLabel = $m[1];
        }
        $badgeText = $tsLabel !== '' ? '(백업 ' . $tsLabel . ')' : '(백업)';

        // 백업 보기 UI 정돈 CSS — 모든 룰을 [data-backup-view='1'] 로 스코프 → 다른 탭에 영향 안 감.
        // (다중 탭 환경에서 <style> 은 전역이지만 selector 가 백업 컨테이너 내부 요소만 매칭)
        $hideCss = '
[data-backup-view="1"] .grid-toolbar { display: none !important; }
/* 헤더 액션 우측 영역: ...메뉴(#mis-more-menu) 만 남기고 나머지 버튼 숨김 */
[data-backup-view="1"] #mis-header-actions > *:not(#mis-more-menu) { display: none !important; }
/* ...드롭다운 — 엑셀다운로드 + URL복사 외 모든 항목/구분선 숨김 */
[data-backup-view="1"] #mis-more-menu .modal-box > *:not([data-menu-key="excel"]):not([data-menu-key="urlcopy"]) {
  display: none !important;
}
/* No 컬럼 필터 토글 아이콘 숨김 */
[data-backup-view="1"] .mis-no-filter-icon { display: none !important; }
[data-backup-view="1"] .mis-no-col { cursor: default !important; }
';

        // 백업 보기는 무조건 읽기전용 — write/admin 모두 false
        return [
            'success'   => true,
            'total'     => $total,
            'page'      => $page,
            'pageSize'  => $pageSize,
            'data'      => $pagedRows,
            'fields'    => $fields,
            '_access'   => ['read' => true, 'write' => false, 'admin' => false, 'level' => 'read'],
            '_onlyList' => true, // 등록/삭제 버튼 숨김
            '_client_disableSort' => true,
            '_client_css'         => $hideCss,
            '_client_toast'       => '백업 보기 (읽기전용) — ' . ($meta['wdate'] ?? '백업시각 알 수 없음'),
            '_isBackupView'       => true,
            '_backupBadgeText'    => $badgeText,
            '_backupMeta'         => $meta,
            '_backupJsonname'     => $jsonname,
        ];
    }

    /**
     * 나의백업에 추가 — 현재 리스트 데이터를 JSON 으로 파일 저장 + mis_backup_list 에 행 INSERT.
     *
     * 권한: 해당 프로그램의 admin (또는 글로벌 admin) 만 가능.
     * 부모정보: parent_gubun + parent_idx 가 있으면 child 프로그램 백업으로 처리 (982 [열기] 시 부모 탭으로 복귀 가능).
     */
    public function backupList(array $params, object $user): array
    {
        $gubun = (int)($params['gubun'] ?? 0);
        if ($gubun <= 0) return ['success' => false, 'message' => 'gubun 누락'];

        $menu = $this->getMenu($gubun);
        if (empty($menu)) return ['success' => false, 'message' => '메뉴 정보 없음 (gubun=' . $gubun . ')'];

        // 권한: 해당 프로그램 admin 만
        $userId = (string)($user->uid ?? '');
        $globalAdmin = ($user->is_admin ?? '') === 'Y' ? 'Y' : '';
        $access = $this->checkMenuAccess($menu, $userId, $globalAdmin);
        if (empty($access['admin'])) {
            return ['success' => false, 'message' => '백업은 해당 프로그램의 관리자(admin) 만 가능합니다'];
        }

        $realPid = (string)($menu['real_pid'] ?? "g{$gubun}");

        // 부모 정보 (자식 프로그램에서 호출 시)
        $parentGubun   = (int)($params['parent_gubun'] ?? 0);
        $parentIdx     = trim((string)($params['parent_idx'] ?? ''));
        $parentRealPid = '';
        if ($parentGubun > 0) {
            $parentMenu    = $this->getMenu($parentGubun);
            $parentRealPid = trim((string)($parentMenu['real_pid'] ?? ''));
        }
        if ($parentRealPid === '' && trim((string)($params['parent_real_pid'] ?? '')) !== '') {
            $parentRealPid = trim((string)$params['parent_real_pid']);
        }

        // 데이터 가져오기 — list() 의 _simple 모드로 행단위 훅 우회 (raw)
        $listParams = [
            'gubun'     => $gubun,
            'page'      => 1,
            'pageSize'  => 100000,
            'orderby'   => $params['orderby']   ?? '',
            'allFilter' => $params['allFilter'] ?? '[]',
            'recently'  => $params['recently']  ?? 'N',
            '_simple'   => '1',
        ];
        if ($parentIdx !== '') $listParams['parent_idx'] = $parentIdx;

        $listResult = $this->list($listParams, $user);
        if (empty($listResult['success'])) return $listResult;
        $data   = $listResult['data']   ?? [];
        $fields = $listResult['fields'] ?? [];

        // 파일 저장
        $baseDir = __DIR__ . '/../../uploadFiles/backups/' . $realPid;
        if (!is_dir($baseDir)) {
            if (!@mkdir($baseDir, 0755, true) && !is_dir($baseDir)) {
                return ['success' => false, 'message' => '백업 디렉토리 생성 실패: ' . $baseDir];
            }
        }
        $now   = date('Ymd_His');
        $safeUser = preg_replace('/[^A-Za-z0-9._\-]/', '_', $userId) ?: 'anon';
        $fname = $safeUser . '_' . $now . '.json';
        $fpath = $baseDir . '/' . $fname;

        $payload = [
            '_meta' => [
                'real_pid'        => $realPid,
                'menu_idx'        => $gubun,
                'menu_name'       => (string)($menu['menu_name'] ?? ''),
                'parent_real_pid' => $parentRealPid,
                'parent_idx'      => $parentIdx,
                'wdater'          => $userId,
                'wdate'           => date('Y-m-d H:i:s'),
                'orderby'         => (string)($listParams['orderby']   ?? ''),
                'allFilter'       => (string)($listParams['allFilter'] ?? '[]'),
                'recently'        => (string)($listParams['recently']  ?? 'N'),
                'row_count'       => count($data),
            ],
            'fields' => $fields,
            'data'   => $data,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false) {
            return ['success' => false, 'message' => 'JSON 인코딩 실패'];
        }
        if (file_put_contents($fpath, $json) === false) {
            return ['success' => false, 'message' => '파일 저장 실패: ' . $fpath];
        }

        // mis_backup_list 에 INSERT
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO mis_backup_list
                    (real_pid, parent_real_pid, parent_idx, jsonname, useflag, ip, wdater, wdate, lastupdater, lastupdate)
                 VALUES (?, ?, ?, ?, '1', ?, ?, NOW(), ?, NOW())"
            );
            $stmt->execute([
                $realPid,
                $parentRealPid !== '' ? $parentRealPid : null,
                $parentIdx     !== '' ? $parentIdx     : null,
                $fname,
                $ip,
                $userId,
                $userId,
            ]);
            $newIdx = (int)$this->pdo->lastInsertId();
        } catch (\Throwable $e) {
            // 파일은 저장된 상태 — DB 만 실패
            return ['success' => false, 'message' => 'DB 기록 실패: ' . $e->getMessage(), 'jsonname' => $fname];
        }

        // 982 메뉴 캐시 무효화 (즉시 반영되도록)
        try { $this->cache->invalidateByRealPid('speedmis000982'); } catch (\Throwable) {}

        return [
            'success'         => true,
            'message'         => '백업 추가됨 (' . count($data) . '건) — gubun=982 에서 확인',
            'idx'             => $newIdx,
            'jsonname'        => $fname,
            'row_count'       => count($data),
            'real_pid'        => $realPid,
            'parent_real_pid' => $parentRealPid,
            'parent_idx'      => $parentIdx,
        ];
    }

    /**
     * xls 빠른 다운로드 — SELECT 결과를 HTML 테이블로 직접 echo (PDO unbuffered).
     *
     * 메모리에 전체 행을 누적하지 않으므로 수만 행 / 큰 텍스트 컬럼도 OOM 없이 처리.
     * Excel 이 HTML 테이블을 그대로 인식 (.xls 확장자 + application/vnd.ms-excel).
     * v6 의 "select 통째로 → replace → xls" 패턴과 동일.
     */
    private function streamXlsRows(string $selectSql, string $orderSql, array $bindings, array $fields, array $menu, string $mainTable = '', string $whereFull = ''): void
    {
        $listFields = array_values(array_filter($fields, function ($f) {
            $w = (int)($f['col_width'] ?? 0);
            return $w !== 0 && $w !== -1 && $w !== -2;
        }));

        $name  = (string)($menu['menu_name'] ?? 'export');
        $fname = preg_replace('/[\\\\\/:*?"<>|]/', '_', $name) . '_' . date('Ymd_His') . '.xls';

        // 출력 버퍼 정리
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . rawurlencode($fname) . '"; filename*=UTF-8\'\'' . rawurlencode($fname));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        // 시간 / 메모리 한도 완화 (스트리밍이라 큰 문제 없음)
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');

        echo "\xEF\xBB\xBF"; // UTF-8 BOM (Excel 한글 인식)
        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
        echo '<head><meta charset="utf-8"><title>' . htmlspecialchars($name, ENT_QUOTES) . '</title>';
        echo '<style>table{border-collapse:collapse}th,td{border:1px solid #CBD0DB;padding:2px 6px;font-family:맑은 고딕,Arial;font-size:11pt}</style>';
        echo '</head><body><table>';

        // ── 헤더 ──
        echo '<thead><tr>';
        echo '<th style="background:#4F6EF7;color:#fff;text-align:center">No</th>';
        $alignFor = function ($f) {
            $a = strtolower((string)($f['grid_align'] ?? ''));
            if ($a === 'center' || $a === 'right' || $a === 'left') return $a;
            $st = (string)($f['schema_type'] ?? '');
            return str_starts_with($st, 'number') ? 'right' : 'left';
        };
        foreach ($listFields as $f) {
            $title = (string)($f['col_title'] ?? $f['alias_name'] ?? '');
            $ci = strpos($title, ',');
            if ($ci !== false) $title = substr($title, $ci + 1) ?: substr($title, 0, $ci);
            echo '<th style="background:#4F6EF7;color:#fff;text-align:' . $alignFor($f) . '">' . htmlspecialchars($title, ENT_QUOTES) . '</th>';
        }
        echo '</tr></thead><tbody>';

        // PDO unbuffered — 한 행씩 가져오며 echo (메모리 누적 X)
        $prevBuffered = null;
        try { $prevBuffered = $this->pdo->getAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY); } catch (\Throwable) {}
        try { $this->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false); } catch (\Throwable) {}

        // 한 행 echo 헬퍼 (primary / fallback 공유)
        $emitRows = function (\PDOStatement $stmt) use (&$i, $listFields, $alignFor) {
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $i++;
                echo '<tr><td style="text-align:center;color:#888">' . $i . '</td>';
                foreach ($listFields as $f) {
                    $alias = $f['alias_name'] ?? '';
                    $val   = $row[$alias] ?? '';
                    $align = $alignFor($f);
                    $sval  = (string)$val;
                    if (preg_match('/^\d{8,}$/', $sval)) {
                        echo '<td style="text-align:' . $align . ';mso-number-format:\'\\@\'">' . htmlspecialchars($sval, ENT_QUOTES) . '</td>';
                    } else {
                        echo '<td style="text-align:' . $align . '">' . htmlspecialchars($sval, ENT_QUOTES) . '</td>';
                    }
                }
                echo "</tr>\n";
                if ($i % 500 === 0) flush();
            }
        };

        $i = 0;
        try {
            $stmt = $this->pdo->prepare(trim("{$selectSql} {$orderSql}"));
            $stmt->execute($bindings);
            $emitRows($stmt);
        } catch (\Throwable $e) {
            // primary SELECT 실패 (보통 JOIN 미정의 컬럼 참조) — list() 와 동일한 table_m.* 폴백
            $this->logger->warning('xls stream primary select failed, fallback to table_m.*', ['err' => $e->getMessage()]);
            if ($mainTable !== '') {
                $fbOrderSql = preg_match('/ORDER BY\s+table_m\.\w+/i', $orderSql)
                    ? $orderSql
                    : (str_contains($orderSql, '.') ? 'ORDER BY table_m.idx DESC' : $orderSql);
                $fbSelect = "SELECT table_m.* FROM `{$mainTable}` table_m " . ($whereFull !== '' ? $whereFull : '');
                try {
                    $stmt = $this->pdo->prepare(trim("{$fbSelect} {$fbOrderSql}"));
                    $stmt->execute($bindings);
                    $emitRows($stmt);
                } catch (\Throwable $e2) {
                    $this->logger->warning('xls stream fallback also failed', ['err' => $e2->getMessage()]);
                    echo '<tr><td colspan="' . (count($listFields) + 1) . '" style="color:red">에러: '
                       . htmlspecialchars($e2->getMessage(), ENT_QUOTES) . '</td></tr>';
                }
            } else {
                echo '<tr><td colspan="' . (count($listFields) + 1) . '" style="color:red">에러: '
                   . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</td></tr>';
            }
        } finally {
            if ($prevBuffered !== null) {
                try { $this->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $prevBuffered); } catch (\Throwable) {}
            }
        }

        echo '</tbody></table></body></html>';
        flush();
    }

    private function getMenu(int $gubun): array
    {
        if ($gubun <= 0) return [];
        if (isset($this->menuMemo[$gubun])) return $this->menuMemo[$gubun];
        $cached = $this->cache->getSchema('menu', (string)$gubun);
        if (is_array($cached)) return $this->menuMemo[$gubun] = $cached;
        try {
            $stmt = $this->pdo->prepare(
                'SELECT idx, real_pid, menu_name, menu_type, mis_join_pid,
                        up_real_pid, auth_code, add_logic,
                        table_name, base_filter, use_condition, delete_query,
                        read_only_cond, brief_insert_sql,
                        is_use_print, g01, g02, g03, g07
                   FROM mis_menus
                  WHERE idx = ? LIMIT 1'
            );
            $stmt->execute([$gubun]);
            $menu = $stmt->fetch() ?: [];

            // menu_type='06' (MIS Join): mis_join_pid 메뉴에서 필드/프로그램 + 표시속성 상속
            if ($menu && ($menu['menu_type'] ?? '') === '06') {
                $joinRealPid = trim($menu['mis_join_pid'] ?? '');
                if ($joinRealPid !== '') {
                    $menu['_fields_real_pid'] = $joinRealPid; // getFields/loadProgram 대상

                    // 조인 메뉴에서 비어있는 속성 상속 — table_name / base_filter / g01 / g02 / g03 / g07
                    // (예: 6140 = MisJoin of 631 → 631 의 g03='Y' 상속하여 recently=OFF 동작)
                    $js = $this->pdo->prepare(
                        'SELECT table_name, base_filter, g01, g02, g03, g07
                           FROM mis_menus WHERE real_pid = ? LIMIT 1'
                    );
                    $js->execute([$joinRealPid]);
                    $joinMenu = $js->fetch() ?: [];

                    foreach (['table_name', 'base_filter', 'g01', 'g02', 'g03', 'g07'] as $col) {
                        $cur = trim((string)($menu[$col] ?? ''));
                        $par = trim((string)($joinMenu[$col] ?? ''));
                        if ($cur === '' && $par !== '') {
                            $menu[$col] = $joinMenu[$col];
                        }
                    }
                }
            }

            if ($menu) {
                $this->cache->setSchema('menu', (string)$gubun, $menu);
                $this->menuMemo[$gubun] = $menu;
            }
            return $menu;
        } catch (\Throwable $e) {
            $this->logger->warning('getMenu failed', ['gubun' => $gubun, 'err' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * 필드 목록 조회
     * menu_type='06' 이면 $menu['_fields_real_pid'] 가 세팅되어 있으므로 그 real_pid 사용
     */
    private function getFields(int $gubun, array $menu = [], ?object $user = null): array
    {
        try {
            $realPid = trim($menu['_fields_real_pid'] ?? '');
            $cacheKey = $realPid !== '' ? "rp:{$realPid}" : "gb:{$gubun}";
            // 동일 요청 내 메모이즈 — 사용자/메뉴 별 default_value 치환은 사용자에 따라 다르므로
            // 메모이즈 키에 user uid 포함
            $memoKey = $cacheKey . '|' . (string)($user->uid ?? '');
            if (isset($this->fieldsMemo[$memoKey])) return $this->fieldsMemo[$memoKey];
            $rows = $this->cache->getSchema('fields', $cacheKey);
            if (!is_array($rows)) {
                if ($realPid !== '') {
                    // MIS Join: real_pid 직접 사용
                    $stmt = $this->pdo->prepare(
                        'SELECT f.alias_name, f.col_title, f.col_width, f.db_table, f.db_field,
                                f.group_compute, f.grid_orderby, f.sort_order,
                                f.schema_type, f.items, f.default_value, f.required,
                                f.form_group, f.max_length, f.grid_align, f.grid_is_handle,
                                f.grid_list_edit, f.grid_ctl_name, f.prime_key,
                                f.schema_validation, f.grid_templete, f.useflag,
                                f.grid_view_class, f.grid_view_hight, (f.grid_enter + 0) AS grid_enter,
                                f.grid_view_sm, f.grid_view_md, f.grid_view_lg, f.grid_view_xl,
                                f.grid_view_fixed,
                                f.grid_alim, f.idx, f.real_pid AS field_real_pid
                           FROM mis_menu_fields f
                          WHERE f.real_pid = ? AND f.useflag = \'1\'
                          ORDER BY f.sort_order ASC'
                    );
                    $stmt->execute([$realPid]);
                } else {
                    $stmt = $this->pdo->prepare(
                        'SELECT f.alias_name, f.col_title, f.col_width, f.db_table, f.db_field,
                                f.group_compute, f.grid_orderby, f.sort_order,
                                f.schema_type, f.items, f.default_value, f.required,
                                f.form_group, f.max_length, f.grid_align, f.grid_is_handle,
                                f.grid_list_edit, f.grid_ctl_name, f.prime_key,
                                f.schema_validation, f.grid_templete, f.useflag,
                                f.grid_view_class, f.grid_view_hight, (f.grid_enter + 0) AS grid_enter,
                                f.grid_view_sm, f.grid_view_md, f.grid_view_lg, f.grid_view_xl,
                                f.grid_view_fixed,
                                f.grid_alim, f.idx, f.real_pid AS field_real_pid
                           FROM mis_menu_fields f
                           JOIN mis_menus m ON m.real_pid = f.real_pid
                          WHERE m.idx = ? AND f.useflag = \'1\'
                          ORDER BY f.sort_order ASC'
                    );
                    $stmt->execute([$gubun]);
                }
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $this->cache->setSchema('fields', $cacheKey, $rows);
            }

            // default_value 에 포함된 @ / $ 세션 플레이스홀더 서버 측 치환 (사용자별, 캐시 후 처리)
            foreach ($rows as &$r) {
                $dv = (string)($r['default_value'] ?? '');
                if ($dv !== '' && (str_contains($dv, '@') || str_contains($dv, '$'))) {
                    $r['default_value'] = $this->resolveSessionPlaceholders($dv, $menu, $user);
                }
            }
            unset($r);

            $this->fieldsMemo[$memoKey] = $rows;
            return $rows;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @session/$session/@realPid 등 서버 측 플레이스홀더 치환
     * base_filter, default_value, prime_key extra 등 다양한 곳에서 공통 사용
     * v7 표준: $misSessionUserId, v6 호환: @misSessionUserId / @MisSession_UserID
     */
    private function resolveSessionPlaceholders(string $s, array $menu = [], ?object $user = null): string
    {
        if ($s === '' || (!str_contains($s, '@') && !str_contains($s, '$'))) return $s;

        $uid      = $user->uid            ?? ($GLOBALS['misSessionUserId']       ?? '');
        $isAdmin  = (($user->is_admin    ?? ($GLOBALS['misSessionIsAdmin']       ?? '')) === 'Y') ? 'Y' : '';
        $posCode  = $user->position_code  ?? ($GLOBALS['misSessionPositionCode'] ?? '');
        $stationN = $user->station_idx    ?? ($GLOBALS['misSessionStationNum']   ?? '');
        $realPid  = $menu['real_pid']     ?? ($GLOBALS['real_pid']               ?? '');
        $parentRp = $GLOBALS['parentRealPid'] ?? '';

        // 부서명 — station_idx → mis_stations.station_name 1회 조회 (필요 시)
        $stationName = '';
        if ((str_contains($s, 'StationName') || str_contains($s, 'stationName')) && $stationN) {
            try {
                $st = $this->pdo->prepare('SELECT station_name FROM mis_stations WHERE idx = ? LIMIT 1');
                $st->execute([(int)$stationN]);
                $stationName = (string)($st->fetchColumn() ?: '');
            } catch (\Throwable) { $stationName = ''; }
        }

        // 현재 시각
        $today    = date('Y-m-d');
        $now      = date('Y-m-d H:i:s');
        $time     = date('H:i:s');

        return str_replace([
            // v7 표준 ($ 접두사)
            '$misSessionUserId', '$misSessionIsAdmin', '$misSessionPositionCode',
            '$misSessionStationNum', '$misSessionStationName', '$realPid', '$parentRealPid',
            '$date', '$datetime', '$now', '$time',
            // 구 표준 (@ 접두사)
            '@misSessionUserId', '@misSessionIsAdmin', '@misSessionPositionCode',
            '@misSessionStationNum', '@misSessionStationName', '@realPid', '@parentRealPid',
            '@date', '@datetime', '@now', '@time',
            // v6 호환
            '@MisSession_UserID', '@MisSession_IsAdmin', '@MisSession_PositionCode',
            '@MisSession_StationNum', '@MisSession_StationName', '@RealPid', '@parent_RealPid',
        ], [
            $uid, $isAdmin, $posCode, $stationN, $stationName, $realPid, $parentRp,
            $today, $now, $now, $time,
            $uid, $isAdmin, $posCode, $stationN, $stationName, $realPid, $parentRp,
            $today, $now, $now, $time,
            $uid, $isAdmin, $posCode, $stationN, $stationName, $realPid, $parentRp,
        ], $s);
    }

    /**
     * 프로그램 훅 파일 로드
     * menu_type='06'이면 mis_join_pid real_pid 우선, 없으면 자신의 real_pid
     */
    private function loadProgram(string $real_pid, array $menu = []): void
    {
        $target = trim($menu['_fields_real_pid'] ?? '') ?: $real_pid;
        if ($target === '' || isset($this->loadedPrograms[$target])) return;
        $this->loadedPrograms[$target] = true;

        // ── 공통로직 로드 (최초 1회) ──
        if (!isset($this->loadedPrograms['__common'])) {
            $this->loadedPrograms['__common'] = true;
            // 1순위: SpeedMIS 기본 공통
            $commonPath = PROGRAMS_PATH . '/_common.php';
            if (file_exists($commonPath)) { ob_start(); include_once $commonPath; ob_end_clean(); }
            // 2순위: 고객사 전용 공통
            $userPath = PROGRAMS_PATH . '/_common_udef.php';
            if (file_exists($userPath)) { ob_start(); include_once $userPath; ob_end_clean(); }
        }

        $path = PROGRAMS_PATH . "/{$target}.php";
        if (file_exists($path)) {
            ob_start();
            include_once $path;
            ob_end_clean();
        } elseif (!empty(trim($menu['add_logic'] ?? ''))) {
            // 파일이 없으면 DB add_logic → 임시 파일 생성 후 로드
            $tmpPath = PROGRAMS_PATH . "/{$target}.php";
            $code = $menu['add_logic'];
            if (stripos(ltrim($code), '<?php') !== 0) $code = "<?php\n" . $code;
            @file_put_contents($tmpPath, $code);
            if (file_exists($tmpPath)) {
                ob_start();
                include_once $tmpPath;
                ob_end_clean();
            }
        }

        if (function_exists('common_pageLoad') || function_exists('user_pageLoad') || function_exists('pageLoad')) {
            ob_start();
            if (function_exists('common_pageLoad')) common_pageLoad();
            if (function_exists('user_pageLoad')) user_pageLoad();
            if (function_exists('pageLoad')) pageLoad();
            ob_end_clean();
        }
    }

    /**
     * 공통훅(common_) + 개별훅 순서로 호출
     * 예: callHook('before_query', $menu, $fields, $params)
     *   → common_before_query($menu, $fields, $params) + before_query($menu, $fields, $params)
     */
    /**
     * 공통(common_) → 고객사(user_) → 개별 순서로 훅 호출
     */
    private function callHook(string $name, mixed &...$args): void
    {
        $common = 'common_' . $name;
        $user   = 'user_' . $name;
        if (function_exists($common)) $common(...$args);
        if (function_exists($user))   $user(...$args);
        if (function_exists($name))   $name(...$args);
    }

    private function setGlobals(array $params, object $user, array $menu, string $flag): void
    {
        // mis_menus 트리거가 history.changed_by 에 사용. 매 요청 시작 시 세션변수 set.
        try {
            $uidForLog = (string)($user->uid ?? 'anon');
            $this->pdo->exec("SET @mis_session_user = " . $this->pdo->quote($uidForLog));
        } catch (\Throwable) { /* trigger 미설치 환경에서도 영향 없도록 swallow */ }

        $GLOBALS['actionFlag']              = $flag;
        $GLOBALS['gubun']                   = (int)($params['gubun'] ?? 0);
        $GLOBALS['idx']                     = (int)($params['idx']   ?? 0);
        $GLOBALS['real_pid']                = $menu['real_pid']  ?? '';
        $GLOBALS['menu_name']               = $menu['menu_name'] ?? '';
        $GLOBALS['full_site']               = rtrim($_ENV['APP_URL'] ?? '', '/');
        $GLOBALS['parent_idx']              = (int)($params['parent_idx'] ?? 0);
        $GLOBALS['parent_gubun']            = (int)($params['parent_gubun'] ?? 0);
        $GLOBALS['allFilter']               = $params['allFilter']  ?? '[]';
        $GLOBALS['orderby']                 = $params['orderby']    ?? '';
        $GLOBALS['page']                    = (int)($params['page'] ?? 1);
        $GLOBALS['pageSize']                = (int)($params['pageSize'] ?? DEFAULT_PAGE_SIZE);
        $GLOBALS['isMenuIn']                = $params['isMenuIn']   ?? 'Y';
        $GLOBALS['isFirstLoad']             = ($params['first_load'] ?? '') === '1';
        $GLOBALS['customAction']            = $params['customAction'] ?? '';
        // customActionPayload: 클라이언트 커스텀 버튼이 함께 전달한 부가 데이터 (JSON 문자열)
        // 예: { checkedIdxs: [1,2,3] } — 6039 인쇄대기열 추가 등에서 사용
        $rawPayload = $params['customActionPayload'] ?? '';
        $GLOBALS['customActionPayload'] = is_string($rawPayload) && $rawPayload !== ''
            ? (json_decode($rawPayload, true) ?: [])
            : [];
        $GLOBALS['misSessionUserId']        = $user->uid            ?? '';
        $GLOBALS['misSessionIsAdmin']       = ($user->is_admin ?? '') === 'Y' ? 'Y' : '';
        $GLOBALS['misSessionPositionCode']  = $user->position_code  ?? '';
        $GLOBALS['misSessionIsDev']         = ($user->is_dev ?? '') === 'Y' ? 'Y' : '';
        $GLOBALS['__pdo']                   = $this->pdo;
        // v6 호환 별칭
        $GLOBALS['ActionFlag']              = &$GLOBALS['actionFlag'];
        $GLOBALS['MisSession_UserID']       = &$GLOBALS['misSessionUserId'];
        $GLOBALS['MisSession_IsAdmin']      = &$GLOBALS['misSessionIsAdmin'];
        $GLOBALS['MisSession_PositionCode'] = &$GLOBALS['misSessionPositionCode'];
    }

    /**
     * 메뉴 접근 권한 계산 — v6 로직 포팅
     *
     * 반환: ['level' => 'admin'|'write'|'read'|'deny', 'read' => bool, 'write' => bool, 'admin' => bool]
     *
     * 규칙 (mis_menus.auth_code + mis_menu_auth.authority_level 조합):
     *   - auth_code='02' & level<2 : read (멤버만 열람)
     *   - level=3                  : admin (R/W 모두 + admin 플래그)
     *   - auth_code∈{'','01'} & level=1 : read (auth_code='' 이면 write 도 허용)
     *   - level>1 또는 new_gidx=0  : write
     *   - 그 외                    : deny
     *
     * 글로벌 admin (GLOBAL_ADMIN_UIDS 상수에 포함된 user_id) 은 무조건 full access.
     * 최종적으로 authority_level=3 이면 $GLOBALS['misSessionIsAdmin'] 을 'Y' 로 올려 프로그램 훅에서 admin 동작 가능.
     *
     * g07='Y' (메뉴 읽기전용) 은 데이터 write 만 차단 — admin 역할(도움말 작성, 디자이너 등) 자체는 유지.
     */
    private function checkMenuAccess(array $menu, string $userId, string $isGlobalAdmin): array
    {
        // 메뉴 자체 읽기전용 플래그 (g07='Y') — 데이터 write 차단. admin 권한은 유지.
        $menuReadOnly = (string)($menu['g07'] ?? '') === 'Y';

        // 글로벌 admin: write 만 g07 따라 제한, admin/read 는 항상 true
        $full = [
            'level' => $menuReadOnly ? 'read' : 'admin',
            'read'  => true,
            'write' => !$menuReadOnly,
            'admin' => true,
        ];
        if ($isGlobalAdmin === 'Y') return $full;

        $realPid = (string)($menu['real_pid'] ?? '');
        if ($realPid === '' || $userId === '') {
            return ['level' => 'deny', 'read' => false, 'write' => false, 'admin' => false];
        }

        $stmt = $this->pdo->prepare(
            "SELECT table_m.idx,
                    IFNULL(table_m.new_gidx, 0) AS new_gidx,
                    IFNULL(table_m.auth_code, '') AS auth_code,
                    IFNULL(table_member.authority_level, 1) AS authority_level
               FROM mis_menus table_m
               LEFT JOIN mis_menu_auth table_member
                 ON table_member.real_pid = table_m.real_pid
                AND table_member.user_id = ?
              WHERE table_m.useflag = '1'
                AND (IFNULL(table_m.auth_code, '') <> '02' OR table_member.user_id = ?)
                AND table_m.real_pid = ?
              ORDER BY table_member.authority_level DESC
              LIMIT 1"
        );
        try {
            $stmt->execute([$userId, $userId, $realPid]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            $row = false;
        }

        $read = false; $write = false; $admin = false;
        if ($row) {
            $ac  = (string)$row['auth_code'];
            $al  = (int)$row['authority_level'];
            $ngi = (int)$row['new_gidx'];

            if ($ac === '02' && $al < 2) {
                $read = true;
            } elseif ($al === 3) {
                $read = true; $write = true; $admin = true;
            } elseif (($ac === '01' || $ac === '') && $al === 1) {
                $read  = true;
                $write = ($ac === '');
            } elseif ($al > 1 || $ngi === 0) {
                $read = true; $write = true;
            }
        }

        // 메뉴 자체 읽기전용 (g07='Y') → 데이터 write 만 차단.
        // admin 역할(도움말 작성/디자이너 등 admin 전용 UI) 은 유지 — read-only 는 data write 와 별개 개념.
        if ($menuReadOnly) {
            $write = false;
        }

        $level = $admin ? 'admin' : ($write ? 'write' : ($read ? 'read' : 'deny'));
        return ['level' => $level, 'read' => $read, 'write' => $write, 'admin' => $admin];
    }

    /**
     * body → 실제 DB 컬럼명 매핑 (저장 가능한 필드만)
     *
     * 저장 제외 조건:
     * - db_table != 'table_m'  (JOIN 테이블, Qn display, 빈 값)
     * - db_field 가 단순 컬럼명이 아님 (subquery, CASE WHEN, 빈 문자열 등)
     * - PK 컬럼 (pkAlias)
     * - 시스템 자동값: wdate/wdater, lastupdate/lastupdater (buildInsert/buildUpdate 에서 자동 처리)
     */
    private function filterData(array $body, array $fields, string $mainTable = '', string $pkAlias = ''): array
    {
        if (empty($fields)) {
            $data = $body;
            unset($data['idx'], $data['gubun'], $data['act'], $data['_csrf'], $data['_listEdit'], $data['_confirmed']);
            return $data;
        }

        // 시스템 자동 컬럼 — buildInsert/buildUpdate 에서 별도 처리 (.env AUDIT_*_COLS 후보 전체)
        $systemCols = array_map('strtolower', array_merge(
            $this->auditCandidates('creator'),
            $this->auditCandidates('created'),
            $this->auditCandidates('updater'),
            $this->auditCandidates('updated'),
        ));

        $out = [];
        foreach ($fields as $f) {
            $alias   = trim($f['alias_name'] ?? '');
            $dbTable = $f['db_table'] ?? '';
            $dbField = trim($f['db_field'] ?? '');

            // ① 메인 테이블 필드만
            if ($dbTable !== 'table_m') continue;

            // ② 단순 컬럼명이어야 함 (subquery, 빈 문자열 '' 리터럴, CASE WHEN 등 제외)
            // 공백·괄호·따옴표가 포함되어 있거나 비어있으면 skip
            if ($dbField === '' || preg_match('/[\s(\'"]/', $dbField)) continue;

            // ③ 시스템 자동값 skip
            if (in_array(strtolower($dbField), $systemCols, true)) continue;

            // ④ PK 컬럼 skip
            if ($alias === $pkAlias) continue;

            // ⑤ body 에 해당 alias 가 없으면 skip
            if ($alias === '' || !array_key_exists($alias, $body)) continue;

            // ⑥ v6→v7 컬럼명 변환
            $col = $mainTable !== '' ? $this->resolveColumn($mainTable, $dbField) : $dbField;

            $out[$col] = $body[$alias];
        }
        return $out;
    }

    /**
     * 감사(audit) 컬럼 후보 목록을 .env 에서 읽어와 배열로 반환
     * @param string $kind 'creator' | 'created' | 'updater' | 'updated'
     */
    private function auditCandidates(string $kind): array
    {
        $defaults = [
            'creator' => 'wdater',
            'created' => 'wdate',
            'updater' => 'lastupdater',
            'updated' => 'lastupdate',
        ];
        $envKey = [
            'creator' => 'AUDIT_CREATOR_COLS',
            'created' => 'AUDIT_CREATED_COLS',
            'updater' => 'AUDIT_UPDATER_COLS',
            'updated' => 'AUDIT_UPDATED_COLS',
        ][$kind] ?? '';
        $raw = trim((string)($_ENV[$envKey] ?? ''));
        if ($raw === '') $raw = $defaults[$kind] ?? '';
        $out = [];
        foreach (explode(',', $raw) as $c) {
            $c = trim($c);
            if ($c !== '') $out[] = $c;
        }
        return $out;
    }

    /**
     * 후보 중 실제 테이블에 존재하는 **모든** 컬럼을 반환 (없으면 빈 배열). 대소문자 무시 매칭.
     * 정책: .env 의 AUDIT_*_COLS 에 나열된 후보 중 일치하는 것 전부 자동 채움.
     * (이전엔 "첫 일치 1개만" 정책이라 lastupdate / it_update_time 같이 여러 후보가 동시 존재하는
     *  테이블에서 일부 컬럼이 채워지지 않는 문제 → 사용자가 .env 에 나열한 의도와 맞춰 모두 채우게 변경)
     * @param array $cols getTableColumnSet() 결과 (키: 컬럼명 — 테이블 정의의 원본 케이스)
     * @return string[] 매칭된 실제 컬럼명들 (테이블 정의의 원본 케이스 보존)
     */
    private function resolveAuditCols(array $cols, string $kind): array
    {
        $colsByLower = [];
        foreach (array_keys($cols) as $name) {
            $colsByLower[strtolower($name)] = $name; // 원본 케이스 보존
        }
        $out = [];
        foreach ($this->auditCandidates($kind) as $c) {
            $lk = strtolower($c);
            if (isset($colsByLower[$lk])) $out[] = $colsByLower[$lk];
        }
        // 중복 제거 (같은 컬럼이 후보에 중복 명시된 경우)
        return array_values(array_unique($out));
    }

    private function buildInsert(string $table, array $data, array $encCols = [], string $pwdKey = ''): array
    {
        $userId = $GLOBALS['misSessionUserId'] ?? '';
        $cols = $this->getTableColumnSet($table);
        $sysCols = [];
        $sysPh   = [];
        $sysVals = [];
        // 입력자 ID — .env AUDIT_CREATOR_COLS 후보 중 테이블에 실재하는 모든 컬럼
        foreach ($this->resolveAuditCols($cols, 'creator') as $c) {
            $sysCols[] = $c; $sysPh[] = '?'; $sysVals[] = $userId;
        }
        // 입력일시 — .env AUDIT_CREATED_COLS 후보 중 테이블에 실재하는 모든 컬럼
        foreach ($this->resolveAuditCols($cols, 'created') as $c) {
            $sysCols[] = $c; $sysPh[] = 'NOW()';
        }
        // 접속자 IP 자동저장 — 테이블에 ip / ip_address 컬럼이 있으면 visitor IP 기록 (data 에 명시값 없을 때만)
        $ipCol = isset($cols['ip']) ? 'ip' : (isset($cols['ip_address']) ? 'ip_address' : null);
        if ($ipCol !== null && !array_key_exists($ipCol, $data)) {
            $sysCols[] = $ipCol; $sysPh[] = '?'; $sysVals[] = $this->getClientIp();
        }

        // 암호화(textdecrypt2) 컬럼: HEX(AES_ENCRYPT(?, ?)) — 값 바인딩 + 키 바인딩
        $encData = [];
        foreach ($data as $col => $val) {
            if (isset($encCols[$col])) $encData[$col] = (string)$val;
        }
        $dataNoEnc = array_diff_key($data, $encData);

        // bit 컬럼은 SQL 리터럴로 직접 삽입 — 'Y'/'N', true/false, '1'/'0' 모두 수용
        $bitCols = [];
        foreach ($dataNoEnc as $col => $val) {
            if (isset($cols[$col]) && str_starts_with($cols[$col], 'bit')) {
                $bitCols[$col] = $this->toBit($val);
            }
        }
        $dataNoBit = array_diff_key($dataNoEnc, $bitCols);

        // PG 분기: 숫자형/날짜형 컬럼 빈 문자열 → NULL (mysql 묵시적 0 변환과 동등 효과 보존)
        if (\App\Config\Database::isPg()) {
            foreach ($dataNoBit as $col => $val) {
                if ($val !== '') continue;
                $t = strtolower((string)($cols[$col] ?? ''));
                if (preg_match('/^(integer|bigint|smallint|numeric|decimal|double|real|date|timestamp|time|boolean)/', $t)) {
                    $dataNoBit[$col] = null;
                }
            }
        }

        if (empty($dataNoBit) && empty($bitCols) && empty($encData) && empty($sysCols)) return ["INSERT INTO `{$table}` () VALUES ()", []];
        $allCols = array_merge(
            array_map(fn($c) => "`{$c}`", array_keys($dataNoBit)),
            array_map(fn($c) => "`{$c}`", array_keys($bitCols)),
            array_map(fn($c) => "`{$c}`", array_keys($encData)),
            $sysCols
        );
        $allPh = array_merge(
            array_fill(0, count($dataNoBit), '?'),
            array_map(fn($v) => (string)$v, array_values($bitCols)),
            array_fill(0, count($encData), 'HEX(AES_ENCRYPT(?, ?))'),
            $sysPh
        );
        $encBinds = [];
        foreach ($encData as $val) { $encBinds[] = $val; $encBinds[] = $pwdKey; }
        return [
            "INSERT INTO `{$table}` (" . implode(', ', $allCols) . ") VALUES (" . implode(', ', $allPh) . ")",
            [...array_values($dataNoBit), ...$encBinds, ...$sysVals],
        ];
    }

    /**
     * @param string $pkCol  WHERE 조건 컬럼명 (sort_order=1 db_field → v7 변환)
     * @param mixed  $pkVal  WHERE 값
     */
    private function buildUpdate(string $table, array $data, string $pkCol, mixed $pkVal, array $encCols = [], string $pwdKey = ''): array
    {
        $userId = $GLOBALS['misSessionUserId'] ?? '';
        $cols = $this->getTableColumnSet($table);
        $sysSets = [];
        $sysVals = [];
        // 수정자 ID — .env AUDIT_UPDATER_COLS 후보 중 테이블에 실재하는 모든 컬럼
        foreach ($this->resolveAuditCols($cols, 'updater') as $c) {
            $sysSets[] = "`{$c}`=?"; $sysVals[] = $userId;
        }
        // 수정일시 — .env AUDIT_UPDATED_COLS 후보 중 테이블에 실재하는 모든 컬럼
        foreach ($this->resolveAuditCols($cols, 'updated') as $c) {
            $sysSets[] = "`{$c}`=NOW()";
        }

        // 암호화(textdecrypt2) 컬럼: HEX(AES_ENCRYPT(?, ?))
        $encData = [];
        foreach ($data as $col => $val) {
            if (isset($encCols[$col])) $encData[$col] = (string)$val;
        }
        $dataNoEnc = array_diff_key($data, $encData);

        // bit 컬럼은 바인딩이 아닌 SQL 리터럴로 직접 삽입 (PDO bit(1) 바인딩 버그 회피)
        // 'Y'/'N', true/false, '1'/'0' 모두 수용
        $bitCols = [];
        foreach ($dataNoEnc as $col => $val) {
            if (isset($cols[$col]) && str_starts_with($cols[$col], 'bit')) {
                $bitCols[$col] = $this->toBit($val);
            }
        }
        $dataNoBit = array_diff_key($dataNoEnc, $bitCols);

        // PG 분기: 숫자형/날짜형 컬럼에 빈 문자열 들어오면 NULL 로 (MariaDB 는 묵시적 0/0000-00-00 으로 변환하지만 PG 는 strict)
        if (\App\Config\Database::isPg()) {
            foreach ($dataNoBit as $col => $val) {
                if ($val !== '') continue;
                $t = strtolower((string)($cols[$col] ?? ''));
                if (preg_match('/^(integer|bigint|smallint|numeric|decimal|double|real|date|timestamp|time|boolean)/', $t)) {
                    $dataNoBit[$col] = null;
                }
            }
        }

        $setParts = array_map(fn($c) => "`{$c}` = ?", array_keys($dataNoBit));
        foreach ($bitCols as $col => $v) {
            $setParts[] = "`{$col}` = {$v}";
        }
        $encBinds = [];
        foreach ($encData as $col => $val) {
            $setParts[] = "`{$col}` = HEX(AES_ENCRYPT(?, ?))";
            $encBinds[] = $val;
            $encBinds[] = $pwdKey;
        }
        $sets = implode(', ', array_merge($setParts, $sysSets));
        if ($sets === '') $sets = '`' . $pkCol . '`=`' . $pkCol . '`';
        return [
            "UPDATE `{$table}` SET {$sets} WHERE `{$pkCol}`=?",
            [...array_values($dataNoBit), ...$encBinds, ...$sysVals, $pkVal],
        ];
    }

    /**
     * 접속자(클라이언트) IP 추출.
     * 프록시/CDN 헤더 우선 (CF-Connecting-IP, X-Forwarded-For, X-Real-IP) → 실패 시 REMOTE_ADDR.
     * X-Forwarded-For 는 comma-separated 면 첫 번째(원본 클라이언트) 만 사용.
     * 컬럼 길이 안전성 위해 45자(IPv6 최대)로 자른다.
     */
    private function getClientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $k) {
            $v = $_SERVER[$k] ?? '';
            if ($v === '') continue;
            $ip = trim(explode(',', $v)[0]);
            if ($ip !== '') return substr($ip, 0, 45);
        }
        return '';
    }

    /**
     * 조회 시 hit 컬럼 +1 (테이블에 hit 컬럼 있을 때만).
     * 같은 브라우저 세션에서 같은 레코드 재조회는 1회만 카운트 — PHP session 으로 추적.
     * (PHPSESSID 는 기본 session 쿠키 → 브라우저 종료 시 자동 만료 → 재시작 시 다시 +1)
     */
    private function bumpHit(string $table, array $cols, string $pkCol, mixed $pkVal, string $realPid): void
    {
        if (!isset($cols['hit'])) return;
        if ($pkVal === null || $pkVal === '' || $pkVal === 0) return;

        if (session_status() !== PHP_SESSION_ACTIVE) {
            // 빠른 헤더 직접 출력 케이스(예: 다른 핸들러)에 영향 안 가도록 silent
            @session_start();
        }
        $key = $realPid . ':' . (string)$pkVal;
        if (!empty($_SESSION['_mis_hit_seen'][$key])) return;

        try {
            $this->pdo->prepare("UPDATE `{$table}` SET `hit` = IFNULL(`hit`, 0) + 1 WHERE `{$pkCol}` = ?")
                      ->execute([$pkVal]);
            $_SESSION['_mis_hit_seen'][$key] = true;
        } catch (\Throwable) { /* hit 카운트 실패는 무시 — 본 조회에는 영향 없도록 */ }
    }

    /** 다양한 입력 값을 bit(1) 리터럴 0/1 로 정규화 */
    private function toBit(mixed $val): int
    {
        if ($val === true || $val === 1)      return 1;
        if ($val === false || $val === 0 || $val === null) return 0;
        if (is_string($val)) {
            $s = strtolower(trim($val));
            if (in_array($s, ['1', 'y', 'yes', 'true', 'on', 't'], true)) return 1;
            return 0;
        }
        return (int)(bool)$val;
    }

    /** $fields 에서 grid_ctl_name='textdecrypt2' 필드의 DB 컬럼 map 추출 */
    private function collectEncryptCols(array $fields, string $table): array
    {
        $out = [];
        foreach ($fields as $f) {
            if (($f['grid_ctl_name'] ?? '') !== 'textdecrypt2') continue;
            if (($f['db_table'] ?? '') !== 'table_m') continue;
            $dbField = trim($f['db_field'] ?? '');
            if ($dbField === '' || preg_match('/[\s(\'"]/', $dbField)) continue;
            $col = $table !== '' ? $this->resolveColumn($table, $dbField) : $dbField;
            $out[$col] = true;
        }
        return $out;
    }

    /** 테이블 컬럼명 set (캐시) — 시스템 컬럼 존재 여부 + 타입 확인용 */
    private array $tableColumnCache = [];
    private function getTableColumnSet(string $table): array
    {
        if (isset($this->tableColumnCache[$table])) return $this->tableColumnCache[$table];
        $stmt = $this->pdo->query("SHOW COLUMNS FROM `{$table}`");
        $set = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $set[$row['Field']] = $row['Type'];
        }
        return $this->tableColumnCache[$table] = $set;
    }

    /**
     * grid_orderby 값으로 기본 ORDER BY 문자열 생성
     * 1a=1순위 ASC, 1d=1순위 DESC, 2a=2순위 ASC, 2d=2순위 DESC
     * 없으면 첫 번째 alias DESC
     */
    private function buildDefaultOrderBy(array $fields): string
    {
        $slots = ['1a' => '', '1d' => '', '2a' => '', '2d' => ''];
        foreach ($fields as $f) {
            $ob    = $f['grid_orderby'] ?? '';
            $alias = $f['alias_name']    ?? '';
            if ($ob !== '' && $alias !== '' && isset($slots[$ob]) && $slots[$ob] === '') {
                $slots[$ob] = $alias;
            }
        }

        $parts = [];
        foreach (['1a', '1d', '2a', '2d'] as $key) {
            if ($slots[$key] === '') continue;
            $parts[] = str_ends_with($key, 'd') ? "-{$slots[$key]}" : $slots[$key];
            if (count($parts) >= 2) break;
        }

        if (!empty($parts)) return implode(',', $parts);

        // fallback: 첫 alias DESC
        foreach ($fields as $f) {
            $alias = $f['alias_name'] ?? '';
            if ($alias !== '') return "-{$alias}";
        }
        return '';
    }

    /**
     * aggregate 모드: orderby 필드 기준으로 그룹 부분합(subtotal) + 총합(total) 행 주입
     * 모드:
     *  - 'auto'        : 실데이터 + 부분합/총합 (숫자=합계, 그 외=건수)
     *  - 'sum'         : 실데이터 + 부분합/총합 (숫자만 합계, 그 외는 공란)
     *  - 'simple.auto' : 실데이터 숨김, 부분합/총합만 (숫자=합계, 그 외=건수)
     *  - 'simple.sum'  : 실데이터 숨김, 부분합/총합만 (숫자만 합계, 그 외는 공란)
     * simple 모드는 각 부분합 행에 __agg_rows(소속 실데이터 배열)을 첨부 → 클릭 시 팝업 표시용.
     */
    private function buildAggregateRows(array $data, array $fields, string $effectiveOrderby, string $mode): array
    {
        if (empty($data)) return $data;

        $simple    = str_starts_with($mode, 'simple.');
        $rawMode   = $simple ? substr($mode, 7) : $mode;
        $showCount = ($rawMode === 'auto'); // auto: 숫자외=건수, sum: 숫자외=공란

        // orderby alias 파싱 (부호 제거, __recently__ 제외)
        $orderAliases = [];
        if ($effectiveOrderby !== '' && !str_starts_with($effectiveOrderby, '__recently__')) {
            foreach (explode(',', $effectiveOrderby) as $token) {
                $token = trim($token);
                if ($token === '') continue;
                if (str_starts_with($token, '-')) $token = substr($token, 1);
                if ($token !== '') $orderAliases[] = $token;
            }
        }

        // 필드 alias + schema_type 맵
        $aliasList = [];
        $schemaByAlias = [];
        foreach ($fields as $f) {
            $a = $f['alias_name'] ?? '';
            if ($a === '') continue;
            $aliasList[] = $a;
            $schemaByAlias[$a] = (string)($f['schema_type'] ?? '');
        }
        $isNumber  = fn(string $a): bool => str_starts_with($schemaByAlias[$a] ?? '', 'number');
        $parseNum  = fn($v): float => is_numeric($v) ? (float)$v : (is_string($v) ? (float)str_replace(',', '', $v) : 0.0);
        $firstVisible = $aliasList[0] ?? '';

        $formatNum = function($v): string {
            if ($v === 0 || $v === 0.0 || $v === '') return '0';
            if (is_float($v) && floor($v) != $v) return number_format($v, 2);
            return number_format((float)$v);
        };

        $makeAggRow = function(array $agg, string $aggType, array $groupRows) use ($aliasList, $orderAliases, $isNumber, $showCount, $formatNum, $firstVisible, $simple) {
            $row = [];
            $html = [];
            $isTotal = ($aggType === 'total');
            $labelPrefix = $isTotal ? '총합' : '소계';
            $labelShown  = false;
            // 소계: 그룹의 첫 행 값을 정렬컬럼에 표시 (해당 그룹 식별용)
            $refRow = (!$isTotal && !empty($groupRows)) ? $groupRows[0] : null;

            foreach ($aliasList as $a) {
                $isOrder = in_array($a, $orderAliases, true);
                if ($isOrder) {
                    if ($isTotal) {
                        // 총합: 첫 orderby 컬럼에만 '총합' 라벨, 나머지는 공란
                        $row[$a] = '';
                        if (!$labelShown) {
                            $html[$a] = '<span class="font-bold text-primary">' . $labelPrefix . '</span>';
                            $labelShown = true;
                        } else {
                            $html[$a] = '';
                        }
                    } else {
                        // 소계: 해당 그룹의 정렬값 그대로 표시 (bold)
                        $val = $refRow[$a] ?? '';
                        $row[$a] = $val;
                        $disp = $val === '' || $val === null ? '' : htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
                        $html[$a] = $disp !== '' ? '<span class="font-bold text-primary">' . $disp . '</span>' : '';
                    }
                } elseif ($isNumber($a)) {
                    $row[$a] = $agg[$a] ?? 0;
                    $html[$a] = '<span class="font-bold text-primary">' . htmlspecialchars($formatNum($row[$a]), ENT_QUOTES, 'UTF-8') . '</span>';
                } else {
                    if ($showCount) {
                        // 필드별 건수: 값이 비어있지 않은 행만 카운트
                        $row[$a] = $agg['__count_' . $a] ?? 0;
                        $html[$a] = '<span class="italic font-normal text-secondary">' . htmlspecialchars($formatNum($row[$a]), ENT_QUOTES, 'UTF-8') . '</span>';
                    } else {
                        $row[$a] = '';
                        $html[$a] = '';
                    }
                }
            }

            // orderby가 없을 경우, 첫 visible 컬럼에 라벨
            if (empty($orderAliases) && $firstVisible !== '' && !$labelShown) {
                $html[$firstVisible] = '<span class="font-bold text-primary">' . $labelPrefix . '</span>';
            }

            $row['__html']     = $html;
            $row['__agg_type'] = $aggType;
            $row['__count']    = $agg['__count'] ?? 0;  // 그룹/전체 건수 (차트 Y=건수 모드용)
            // 집계 행 클릭 시 팝업용 원본 데이터 — 모든 aggregate 모드에서 첨부
            if (!empty($groupRows)) {
                $row['__agg_rows'] = $groupRows;
            }
            return $row;
        };

        $result = [];
        $grandAgg = ['__count' => 0];
        $groupAgg = ['__count' => 0];
        $groupRows = [];
        $allRows = [];
        $currentKey = null;

        foreach ($data as $row) {
            // 그룹 키 계산
            $key = '';
            foreach ($orderAliases as $a) {
                $key .= ($row[$a] ?? '') . "\x1F";
            }

            // 그룹 변경 시 이전 그룹 subtotal 주입
            if (!empty($orderAliases) && $currentKey !== null && $key !== $currentKey) {
                $result[] = $makeAggRow($groupAgg, 'subtotal', $groupRows);
                $groupAgg = ['__count' => 0];
                $groupRows = [];
            }
            $currentKey = $key;

            // 누적
            $groupAgg['__count']++;
            $grandAgg['__count']++;
            foreach ($aliasList as $a) {
                if ($isNumber($a)) {
                    $n = $parseNum($row[$a] ?? 0);
                    $groupAgg[$a] = ($groupAgg[$a] ?? 0) + $n;
                    $grandAgg[$a] = ($grandAgg[$a] ?? 0) + $n;
                } else {
                    // 필드별 건수: 값이 존재(null/빈문자 제외)하는 행만 카운트
                    $v = $row[$a] ?? null;
                    if ($v !== null && $v !== '') {
                        $groupAgg['__count_' . $a] = ($groupAgg['__count_' . $a] ?? 0) + 1;
                        $grandAgg['__count_' . $a] = ($grandAgg['__count_' . $a] ?? 0) + 1;
                    }
                }
            }

            // 모든 모드에서 클릭 팝업용 원본 데이터 누적
            $groupRows[] = $row;
            $allRows[]   = $row;
            if (!$simple) {
                $result[] = $row;
            }
        }

        // 마지막 그룹 subtotal
        if (!empty($orderAliases) && $groupAgg['__count'] > 0) {
            $result[] = $makeAggRow($groupAgg, 'subtotal', $groupRows);
        }

        // 총합은 전체 행 첨부
        $result[] = $makeAggRow($grandAgg, 'total', $allRows);

        return $result;
    }

    /**
     * v6 PascalCase 테이블명 → v7 snake_case 매핑
     * 매핑에 없으면 원본 반환 (애플리케이션 테이블은 그대로 사용)
     */
    private function resolveTable(string $tableName): string
    {
        static $map = [
            'MisMenuList'        => 'mis_menus',
            'MisMenuList_Detail' => 'mis_menu_fields',
            'MisMenuList_Member' => 'mis_menu_auth',
            'MisUser'            => 'mis_users',
            'MisGroup_Master'    => 'mis_groups',
            'MisGroup_Detail'    => 'mis_group_rules',
            'MisGroup_Member'    => 'mis_group_members',
            'MisStation'         => 'mis_stations',
            'MisLog'             => 'mis_activity_logs',
            'MisComments'        => 'mis_comments',
            'MisFavoriteMenu'    => 'mis_favorite_menus',
            'MisHelp'            => 'mis_help',
            'MisReadList'        => 'mis_read_history',
            'MisShare'           => 'mis_shares',
            'MisCommonTable'       => 'mis_common_data',
            'MisCompanyMgt'        => 'mis_companies',
            'MisMenuList_UserAuth' => 'mis_menu_user_auth',
        ];
        return $map[$tableName] ?? $tableName;
    }

    /**
     * v6 컬럼명 → v7 snake_case 매핑 (케이스-인센시티브)
     * 매핑에 없으면 원본 반환 (애플리케이션 테이블 컬럼은 그대로)
     */
    private function resolveColumn(string $v7table, string $col): string
    {
        // 전역 공통 (모든 테이블) — useflag 는 레거시 g5_shop_* 등 비-mis 테이블에도 적용
        static $globalCommon = [
            'useflag' => 'useflag',
        ];
        // mis_* 전용 공통 — v6→v7 마이그레이션된 43쌍 테이블에만 적용
        // (레거시 g5_shop_* 등은 실제 컬럼이 lastupdate 그대로라 건드리면 안 됨)
        static $misCommon = [
            'lastupdate'      => 'lastupdate',
            'lastupdater'     => 'lastupdater',
            'filelastupdate'  => 'file_last_update',
            'filelastupdater' => 'file_last_updater',
        ];
        $common = str_starts_with($v7table, 'mis_')
            ? ($globalCommon + $misCommon)
            : $globalCommon;
        // 테이블별 — lowercase 키
        static $byTable = [
            'mis_users' => [
                'num'             => 'idx',
                'uniquenum'       => 'user_id',
                'username'        => 'user_name',
                'useralias'       => 'user_alias',
                'positionnum'     => 'position_code',
                'passwddecrypt'   => 'passwd_decrypt',
                'station_newnum'  => 'station_idx',
                'handphone'       => 'hand_phone',
                'isstop'          => 'is_stop',
                'isrest'          => 'is_rest',
                'usrphone'        => 'usr_phone',
                'intraphone'      => 'intra_phone',
                'zipcode'         => 'zip_code',
                'usraddress'      => 'usr_address',
                'lastaddress'     => 'last_address',
                'lastcollege'     => 'last_college',
                'collegesubject'  => 'college_subject',
                'bankname'        => 'bank_name',
                'bankbooknum'     => 'bank_book_num',
                'bankinsertman'   => 'bank_insert_man',
                'delchk'          => 'del_chk',
            ],
            'mis_menu_auth' => [
                'realpid'        => 'real_pid',
                'authoritylevel' => 'authority_level',
            ],
            'mis_stations' => [
                'num'         => 'idx',
                'stationname' => 'station_name',
                'autogubun'   => 'autogubun',
                'sortg2'      => 'sort_g2',
                'sortg4'      => 'sort_g4',
                'sortg6'      => 'sort_g6',
                'sortg8'      => 'sort_g8',
                'sortg10'     => 'sort_g10',
            ],
            'mis_menus' => [
                'realpid'           => 'real_pid',
                'menuname'          => 'menu_name',
                'brieftitle'        => 'brief_title',
                'ismenuhidden'      => 'is_menu_hidden',
                'authcode'          => 'auth_code',
                'menutype'          => 'menu_type',
                'uprealpid'         => 'up_real_pid',
                'addurl'            => 'add_url',
                'autogubun'         => 'autogubun',
                'sortg2'            => 'sort_g2',
                'sortg4'            => 'sort_g4',
                'sortg6'            => 'sort_g6',
                'misjoinpid'        => 'mis_join_pid',
                'misjoinlist'       => 'mis_join_list',
                'iscoreprogram'     => 'is_core_program',
                'useflag'           => 'useflag',
                'lastupdate'        => 'lastupdate',
                'lastupdater'       => 'lastupdater',
                // g0x → 실제 컬럼명
                'g04'               => 'read_only_cond',
                'g05'               => 'brief_insert_sql',
                'g08'               => 'table_name',
                'g09'               => 'base_filter',
                'g10'               => 'use_condition',
                'g11'               => 'delete_query',
                // 기타 v6 PascalCase → v7 snake_case
                'addlogic'          => 'add_logic',
                'addlogic_treat'    => 'add_logic_treat',
                'addlogic_print'    => 'add_logic_print',
                'isuseprint'        => 'is_use_print',
                'isuseform'         => 'is_use_form',
                'newgidx'           => 'new_gidx',
                'filelastupdate'    => 'file_last_update',
                'filelastupdater'   => 'file_last_updater',
                'compileddate'      => 'compile_date',
                'helpupdatedeny'    => 'help_update_deny',
                'helptitle'         => 'help_title',
                'helpcontents'      => 'help_contents',
                'exceldata'         => 'excel_data',
            ],
            'mis_menu_fields' => [
                'realpid'                   => 'real_pid',
                'sortelement'               => 'sort_order',
                'grid_select_field'         => 'db_field',
                'grid_select_tname'         => 'db_table',
                'aliasname'                 => 'alias_name',
                'grid_columns_title'        => 'col_title',
                'grid_columns_width'        => 'col_width',
                'grid_schema_type'          => 'schema_type',
                'grid_items'                => 'items',
                'grid_schema_validation'    => 'schema_validation',
                'grid_maxlength'            => 'max_length',
                'grid_default'              => 'default_value',
                'grid_pil'                  => 'required',
                'grid_formgroup'            => 'form_group',
                'grid_groupcompute'         => 'group_compute',
                'grid_primekey'             => 'prime_key',
                'grid_align'                => 'grid_align',
                'grid_orderby'              => 'grid_orderby',
                'grid_ctlname'              => 'grid_ctl_name',
                'grid_listedit'             => 'grid_list_edit',
                'grid_view_fixed'           => 'grid_view_fixed',
                'grid_view_sm'              => 'grid_view_sm',
                'grid_view_md'              => 'grid_view_md',
                'grid_view_lg'              => 'grid_view_lg',
                'grid_view_xl'              => 'grid_view_xl',
                'grid_view_hight'           => 'grid_view_hight',
                'grid_view_class'           => 'grid_view_class',
                'grid_enter'                => 'grid_enter',
                'grid_ishandle'             => 'grid_is_handle',
                'grid_templete'             => 'grid_templete',
                'grid_alim'                 => 'grid_alim',
                'useflag'                   => 'useflag',
                'lastupdate'                => 'lastupdate',
                'lastupdater'               => 'lastupdater',
            ],
            'mis_common_data' => [
                'num'     => 'idx',
                'realcid' => 'real_cid',
                'kname'   => 'kname',    // same
                'kname2'  => 'kname2',   // same
                'docitem' => 'doc_item',
            ],
            'mis_groups' => [
                'num'       => 'idx',
                'groupname' => 'group_name',
            ],
            'mis_group_members' => [
                'gidx'      => 'group_idx',
                'uniquenum' => 'user_id',
                'userid'    => 'user_id',
                'isadmins'  => 'is_admin_s',
            ],
            'mis_group_rules' => [
                'gidx'          => 'group_idx',
                'fieldname'     => 'field_name',
                'fieldvalue'    => 'field_value',
                'setnewstation' => 'set_new_station',
                'setposition'   => 'set_position',
                'wherecode2'    => 'where_code2',
                'setuserid'     => 'set_userid',
                'isadmins'      => 'is_admin_s',
            ],
            'mis_shares' => [
                'realpid'    => 'real_pid',
                'menuidx'    => 'menu_idx',
                'uniquenum'  => 'user_id',
                'shareuniq'  => 'share_uniq',
            ],
            'mis_activity_logs' => [
                'logtype'    => 'log_type',
                'menuidx'    => 'menu_idx',
                'linkresult' => 'link_result',
            ],
            'mis_comments' => [
                'menuidx'   => 'menu_idx',
                'parentidx' => 'parent_idx',
                'contents'  => 'contents',
                'uniquenum' => 'user_id',
                'realpid'   => 'real_pid',
            ],
            // 982 (나의 백업현황) 의 JOIN 정의가 v6 camelCase 인 parent_RealPid 를 참조 → v7 컬럼 parent_real_pid 매핑.
            // 이 매핑이 없으면 SELECT 가 'Unknown column parent_RealPid' 로 실패 → fallback 이 모든 JOIN 을 떼고 → 메뉴명 빈 칸.
            'mis_backup_list' => [
                'realpid'        => 'real_pid',
                'parent_realpid' => 'parent_real_pid',
                'parentidx'      => 'parent_idx',
            ],
            'mis_favorite_menus' => [
                'realpid'         => 'real_pid',
                'ispublic'        => 'is_public',
                'ismain'          => 'is_main',
                'isnotrecently'   => 'is_not_recently',
                'issendmail'      => 'is_send_mail',
                'addurl'          => 'add_url',
            ],
            'mis_read_history' => [
                'realpid'         => 'real_pid',
                'readdate'        => 'read_date',
                'push_devicenums' => 'push_device_nums',
                'userid'          => 'userid',   // already snake_case in v7
            ],
            'mis_menu_user_auth' => [
                'userid'       => 'user_id',
                'realpid'      => 'real_pid',
                'menuauthcode' => 'menu_auth_code',
                'useflag'      => 'useflag',
                'lastupdate'   => 'lastupdate',
                'lastupdater'  => 'lastupdater',
            ],
        ];
        $lc = strtolower($col);
        return $byTable[$v7table][$lc] ?? $common[$lc] ?? $col;
    }

    /**
     * SQL 표현식/ON 절 안의 v6 참조를 v7 으로 치환
     *
     * ① table alias 참조: "alias.col" → "alias.resolved_col"  (aliasToTable 기반)
     * ② 독립 테이블 참조: "MisMenuList m" → "mis_menus m" 등
     */
    private function resolveExpression(string $expr, array $aliasToTable): string
    {
        if ($expr === '') return $expr;

        // ⓪ T-SQL CONVERT(char(n), expr) → MySQL CONVERT(expr, CHAR(n))
        // SQL Server 스타일: convert(char(2), value) → MariaDB: CONVERT(value, CHAR(2))
        $expr = preg_replace_callback(
            '/\bconvert\s*\(\s*char\s*\((\d+)\)\s*,\s*([^)]+)\)/i',
            fn($m) => "CONVERT(" . trim($m[2]) . ", CHAR({$m[1]}))",
            $expr
        ) ?? $expr;

        // ① 서브쿼리 인라인 alias 프리스캔: "FROM|JOIN v6Table table_alias" 형태 수집
        //    예) "from MisUser table_Station_NewNum" → table_Station_NewNum → mis_users
        preg_replace_callback(
            '/(?:FROM|JOIN)\s+(\S+)\s+(table_\w+)/i',
            function (array $m) use (&$aliasToTable): string {
                $resolved = $this->resolveTable($m[1]);
                if ($resolved !== '') $aliasToTable[$m[2]] = $resolved;
                return $m[0];
            },
            $expr
        );

        // ② "alias.col" 패턴 — alias 가 aliasToTable 에 있을 때만 변환
        // alias 가 정확히 일치하지 않으면, 언더스코어/대소문자 차이만 있는 후보를 collapsed 매칭으로 찾아
        // 실제 JOIN 별칭 (예: table_RealPid) 으로 재작성 → snake/Pascal 혼용 레거시 group_compute 호환
        $expr = preg_replace_callback(
            '/\b(table_\w+)\.([\p{L}_][\p{L}\p{N}_]*)/u',
            function (array $m) use ($aliasToTable): string {
                $alias   = $m[1];
                $col     = $m[2];
                $v7table = $aliasToTable[$alias] ?? '';
                if ($v7table === '') {
                    // collapsed 매칭: table_real_pid ↔ table_RealPid ↔ table_com_g1 ↔ table_ComG1
                    $collapsed = str_replace('_', '', strtolower($alias));
                    foreach ($aliasToTable as $cand => $tbl) {
                        if (str_replace('_', '', strtolower($cand)) === $collapsed) {
                            $alias   = $cand;
                            $v7table = $tbl;
                            break;
                        }
                    }
                    if ($v7table === '') return $m[0];
                }
                return "{$alias}." . $this->resolveColumn($v7table, $col);
            },
            $expr
        ) ?? $expr;

        // ② 독립 테이블명 (FROM/JOIN 절 안) — 단어 경계 치환
        static $tableMap = [
            'MisMenuList'        => 'mis_menus',
            'MisMenuList_Detail' => 'mis_menu_fields',
            'MisMenuList_Member' => 'mis_menu_auth',
            'MisUser'            => 'mis_users',
            'MisGroup_Master'    => 'mis_groups',
            'MisGroup_Detail'    => 'mis_group_rules',
            'MisGroup_Member'    => 'mis_group_members',
            'MisStation'         => 'mis_stations',
            'MisLog'             => 'mis_activity_logs',
            'MisComments'        => 'mis_comments',
            'MisFavoriteMenu'    => 'mis_favorite_menus',
            'MisHelp'            => 'mis_help',
            'MisReadList'        => 'mis_read_history',
            'MisShare'           => 'mis_shares',
            'MisCommonTable'     => 'mis_common_data',
            'MisCompanyMgt'      => 'mis_companies',
        ];
        foreach ($tableMap as $v6 => $v7) {
            $expr = preg_replace('/\b' . preg_quote($v6, '/') . '\b/', $v7, $expr);
        }

        // ③ 단순 별칭(1~4글자).col 참조 — 예: "m.AutoGubun", "m.useflag" in subquery
        static $globalCommon = [
            'autogubun'      => 'autogubun',
            'menuname'       => 'menu_name',
            'useflag'        => 'useflag',
            'lastupdate'     => 'lastupdate',
            'lastupdater'    => 'lastupdater',
            'realpid'        => 'real_pid',
            'misjoinpid'     => 'mis_join_pid',
            'misjoinlist'    => 'mis_join_list',
            'menutype'       => 'menu_type',
            'uprealpid'      => 'up_real_pid',
            'realcid'        => 'real_cid',
            'uniquenum'      => 'user_id',
            'docitem'        => 'doc_item',
            'stationname'    => 'station_name',
            'groupname'      => 'group_name',
            'sortg2'         => 'sort_g2',
            'sortg4'         => 'sort_g4',
            'sortg6'         => 'sort_g6',
            'addlogic'       => 'add_logic',
            'addlogic_treat' => 'add_logic_treat',
            'addlogic_print' => 'add_logic_print',
            'isuseprint'     => 'is_use_print',
            'isuseform'      => 'is_use_form',
            'iscoreprogram'  => 'is_core_program',
            'ismenuhidden'   => 'is_menu_hidden',
            'authcode'       => 'auth_code',
            'addurl'         => 'add_url',
            'newgidx'        => 'new_gidx',
            'filelastupdate'  => 'file_last_update',
            'filelastupdater' => 'file_last_updater',
            'username'        => 'user_name',
            'setnewstation'   => 'set_new_station',
            'setposition'     => 'set_position',
            'wherecode2'      => 'where_code2',
            'setuserid'       => 'set_userid',
            'isadmins'        => 'is_admin_s',
            'groupidx'        => 'group_idx',
            'fieldname'       => 'field_name',
            'fieldvalue'      => 'field_value',
            'shareuniq'       => 'share_uniq',
            'ispublic'        => 'is_public',
            'ismain'          => 'is_main',
            'isnotrecently'   => 'is_not_recently',
            'ismenuin'        => 'is_menu_in',
            'readdate'        => 'read_date',
        ];
        $expr = preg_replace_callback(
            '/\b([a-zA-Z]\w{0,3})\.([A-Za-z_][A-Za-z0-9_]*)/',
            function (array $m) use ($globalCommon): string {
                if (str_starts_with($m[1], 'table_')) return $m[0];
                $resolved = $globalCommon[strtolower($m[2])] ?? $m[2];
                return "{$m[1]}.{$resolved}";
            },
            $expr
        ) ?? $expr;

        // ④ bare identifier (alias 없음) — 서브쿼리의 SELECT/WHERE 안 v6 컬럼명
        // SQL 키워드와 충돌 없도록 globalCommon 매핑에 있는 것만 치환
        $expr = preg_replace_callback(
            '/(?<![.\w])([A-Za-z][A-Za-z0-9_]*)(?![.\w(])/',
            function (array $m) use ($globalCommon): string {
                return $globalCommon[strtolower($m[1])] ?? $m[1];
            },
            $expr
        ) ?? $expr;

        // ⑤ `FROM|JOIN <v7table>` (alias 없음) 스코프 내 bare identifier → byTable 해석
        // 예: (select is_admin_s from mis_group_members where userid=... and gidx=...)
        //     → where user_id=... and group_idx=...
        $expr = preg_replace_callback(
            '/\b(from|join)\s+(mis_\w+)\b(?!\s+(?:table_\w+|[A-Za-z]\w*\s+on\b))((?:(?!\b(?:from|join)\s+)[\s\S])*?)(?=\)|\bunion\b|$)/i',
            function (array $m): string {
                $lead  = $m[1];
                $table = $m[2];
                $body  = $m[3];
                static $sqlKw = [
                    'and','or','not','in','is','null','true','false','like','between','exists',
                    'where','from','join','on','left','right','inner','outer','cross','full',
                    'group','by','having','order','asc','desc','limit','offset','as',
                    'case','when','then','else','end','select','distinct','union','all',
                ];
                $newBody = preg_replace_callback(
                    '/(?<![.\w\'"`])([A-Za-z_][A-Za-z0-9_]*)(?![\w.(\'"`])/',
                    function (array $mi) use ($table, $sqlKw): string {
                        $col = $mi[1];
                        if (in_array(strtolower($col), $sqlKw, true)) return $col;
                        return $this->resolveColumn($table, $col);
                    },
                    $body
                ) ?? $body;
                return "{$lead} {$table}{$newBody}";
            },
            $expr
        ) ?? $expr;

        return $expr;
    }

    /**
     * base_filter 문자열 안의 v6 컬럼명을 v7 snake_case 로 치환
     * aliasToTable: buildSelectFromFields 가 반환한 전체 alias→v7table 맵
     */
    private function resolveBaseFilter(string $filter, array $aliasToTable): string
    {
        if ($filter === '') return $filter;

        // @세션변수 → 실제 값 치환
        $filter = $this->resolveSessionPlaceholders($filter);
        return $this->resolveExpression($filter, $aliasToTable);
    }

    /**
     * mis_menu_fields 배열 → SELECT 컬럼 목록 + LEFT JOIN 절 목록 + fieldMap 반환
     *
     * fieldMap: alias_name → 'table_alias.db_field' (WHERE/ORDER BY 에서 사용)
     *
     * group_compute 컬럼 포맷:
     *   "RealTableName alias ON condition..."  (alias는 임의 식별자, table_ 접두어 불필요)
     */
    private function buildSelectFromFields(array $fields, string $userId, string $mainTable = '', ?string $contextIdx = null): array
    {
        $selectCols      = [];
        $selectColTitles = [];
        $joinClauses     = [];
        $joinedAliases = [];
        $fieldMap      = [];
        // JOIN alias → 실제 v7 테이블명 (resolveColumn 에 사용)
        $aliasToTable  = ['table_m' => $mainTable];

        // ── Pass 1: aliasToTable 선행 수집 ─────────────────────────────────
        // display 필드가 FK 필드보다 sort_order 상 앞에 오기 때문에,
        // 단일 패스에서는 display 필드 처리 시 aliasToTable 미등록 상태가 됨.
        // 먼저 모든 group_compute + prime_key 를 스캔해 별칭→테이블 매핑 수집.
        $prevDbTableScan = '';
        foreach ($fields as $f) {
            $rawDbTable   = $f['db_table'];
            $dbTable      = $rawDbTable !== null ? trim($rawDbTable) : '';
            // null db_table + 단순 식별자 → table_m 기본, null + 복합 표현식 → '' 유지
            if ($rawDbTable === null) {
                $tmpField = trim($f['db_field'] ?? '');
                if ($tmpField !== '' && !str_contains($tmpField, ' ') && !str_contains($tmpField, '(')) {
                    $dbTable = 'table_m';
                }
            }
            $groupCompute = trim($f['group_compute'] ?? '');
            $primeKey     = trim($f['prime_key']     ?? '');

            if ($groupCompute !== '') {
                $gc = preg_replace('/\bdbo\./i', '', $groupCompute);
                if (preg_match('/^(\S+)\s+(\w+)\s+on\s+/is', trim($gc), $m)) {
                    $aliasToTable[$m[2]] = $this->resolveTable($m[1]);
                }
            }
            if ($primeKey !== '' && $prevDbTableScan !== '' && $prevDbTableScan !== 'table_m') {
                $parts = explode('#', $primeKey);
                if (count($parts) >= 4) {
                    $aliasToTable[$prevDbTableScan] = $this->resolveTable(trim($parts[1]));
                }
            }
            $prevDbTableScan = $dbTable;
        }

        // ── Pass 2: SELECT / JOIN 생성 ─────────────────────────────────────
        $prevDbTable = '';
        $prevDbField = '';

        foreach ($fields as $f) {
            $alias        = $f['alias_name']     ?? '';
            $rawDbTable   = $f['db_table'];
            $dbTable      = $rawDbTable !== null ? trim($rawDbTable) : '';
            $dbField      = trim($f['db_field']  ?? '');
            // null db_table + 단순 식별자 → table_m 기본, null + 복합 표현식 → '' (raw SQL)
            if ($rawDbTable === null && $dbField !== ''
                && !str_contains($dbField, ' ') && !str_contains($dbField, '(')) {
                $dbTable = 'table_m';
            }
            $groupCompute = trim($f['group_compute'] ?? '');
            $primeKey     = trim($f['prime_key']     ?? '');

            // ── JOIN 수집: group_compute ──────────────────────────────────
            // alias가 없는 숨김 필드(col_width<0)라도 group_compute JOIN은 반드시 반영
            if ($groupCompute !== '') {
                $join = $this->parseJoinDef($groupCompute, $userId, $aliasToTable);
                if ($join && !isset($joinedAliases[$join['alias']])) {
                    $joinClauses[]                 = "LEFT JOIN {$join['table']} {$join['alias']} ON {$join['on']}";
                    $joinedAliases[$join['alias']] = true;
                }
            }

            if ($alias === '') { $prevDbTable = $dbTable; $prevDbField = $dbField; continue; }

            // db_table 이 실제 JOIN 된 alias 와 언더스코어 불일치 — prime_key/SELECT 전에 정규화
            //   (마이그레이션 아티팩트: db_table='table_real_pid' vs JOIN alias='table_realpid')
            if ($dbTable !== '' && $dbTable !== 'table_m' && !isset($aliasToTable[$dbTable])) {
                $collapsed = str_replace('_', '', strtolower($dbTable));
                foreach (array_keys($aliasToTable) as $cand) {
                    if (str_replace('_', '', strtolower($cand)) === $collapsed) { $dbTable = $cand; break; }
                }
            }

            // ── JOIN 수집: prime_key ────────────────────────────────────────
            if ($primeKey !== '' && $prevDbTable !== '' && $prevDbTable !== 'table_m') {
                // FK 필드의 컬럼명 v7 변환 (ON 절에 사용)
                $v7curTable     = $aliasToTable[$dbTable] ?? '';
                $resolvedCurCol = $v7curTable !== '' ? $this->resolveColumn($v7curTable, $dbField) : $dbField;
                $join = $this->parsePrimeKeyJoin($primeKey, $prevDbTable, $dbTable, $resolvedCurCol, $userId, $aliasToTable, $contextIdx);
                if ($join && !isset($joinedAliases[$join['alias']])) {
                    $joinClauses[]                 = "LEFT JOIN {$join['table']} {$join['alias']} ON {$join['on']}";
                    $joinedAliases[$join['alias']] = true;
                }
            }

            // ── SELECT 표현식 생성 ──────────────────────────────────────────
            if ($dbTable === 'virtual_field') {
                // 가상 필드: DB 컬럼 없이 빈 문자열로 SELECT.
                // fieldMap 등록 안 함 — WHERE/ORDER BY 에서 가상필드는 무의미하므로
                // QueryBuilder 가 알 모르는 alias 로 보고 silent skip 하게 둠.
                // 사용자로직(before_query)이 가상필드 필터를 가로채 base_filter 로 변환할 수 있음.
                $selectCols[]       = "'' AS `{$alias}`";
                $selectColTitles[]  = $f['col_title'] ?? $alias;
            } elseif ($dbTable !== '' && $dbField !== '') {
                // v6 컬럼명 → v7 변환
                $v7table  = $aliasToTable[$dbTable] ?? '';
                $resolved = $v7table !== '' ? $this->resolveColumn($v7table, $dbField) : $dbField;
                $expr             = "{$dbTable}.{$resolved}";
                $selectCols[]       = "{$expr} AS `{$alias}`";
                $selectColTitles[]  = $f['col_title'] ?? $alias;
                $fieldMap[$alias] = $expr;
            } elseif ($dbField !== '') {
                // 순수 SQL 표현식 (CASE WHEN, subquery, concat 등) — 내부 참조도 치환
                $resolvedExpr     = $this->resolveExpression($dbField, $aliasToTable);
                $selectCols[]       = "({$resolvedExpr}) AS `{$alias}`";
                $selectColTitles[]  = $f['col_title'] ?? $alias;
                $fieldMap[$alias] = "({$resolvedExpr})";
            }

            // virtual_field는 실제 DB 테이블이 아니므로 prevDbTable 체인 초기화
            $prevDbTable = ($dbTable === 'virtual_field') ? '' : $dbTable;
            $prevDbField = $dbField;
        }

        return [$selectCols, $joinClauses, $fieldMap, $aliasToTable, $selectColTitles];
    }

    /**
     * prime_key JOIN 생성
     *
     * prime_key 포맷: display#RealTable#codeField#joinField[#extra[#extra2...]]
     *
     * 결과: LEFT JOIN {RealTable} {joinAlias} ON {joinAlias}.{parts[3]} = {curDbTable}.{curDbField}
     *        [AND {joinAlias}.{extra} | AND ({extra_with_@outer_tbname})]
     *
     * @param string $joinAlias   직전 필드의 db_table (JOIN 별칭)
     * @param string $curDbTable  현재 FK 필드의 db_table
     * @param string $curDbField  현재 FK 필드의 db_field
     */
    private function parsePrimeKeyJoin(
        string  $primeKey,
        string  $joinAlias,
        string  $curDbTable,
        string  $curDbField,
        string  $userId,
        array   $aliasToTable = [],
        ?string $contextIdx = null
    ): ?array {
        $parts = explode('#', $primeKey);
        if (count($parts) < 4) return null;

        $tableName = $this->resolveTable(trim($parts[1]));
        $joinField = $this->resolveColumn($tableName, trim($parts[3]));   // 조인 테이블 측 키 컬럼 (v6→v7 매핑)

        $safeId = preg_replace('/[^a-zA-Z0-9_\-@.]/', '', $userId);

        // aliasToTable 에 joinAlias → tableName 추가 (extra 조건 resolveExpression 용)
        $localAliasMap = array_merge($aliasToTable, [$joinAlias => $tableName]);

        $on = "{$joinAlias}.{$joinField} = {$curDbTable}.{$curDbField}";

        // parts[4], parts[5] ... 추가 조건
        for ($i = 4; $i < count($parts); $i++) {
            $extra = trim($parts[$i]);
            if ($extra === '') continue;

            // @idx 처리 — 현재 레코드 idx 가 있어야 의미있는 조건 (v6 호환):
            //   - view/modify 컨텍스트 ($contextIdx 설정됨): @idx → 실제 idx 치환
            //   - list/filter 컨텍스트 ($contextIdx 미설정): 행마다 다른 idx 라 의미없음 → 해당 extra 통째로 건너뜀
            if (str_contains($extra, '@idx')) {
                if ($contextIdx === null || $contextIdx === '') continue;
                $safeIdx = preg_replace('/[^0-9A-Za-z_\-]/', '', $contextIdx);
                $extra = str_replace('@idx', $safeIdx, $extra);
            }

            // (select COL from TABLE where it_id=N) 패턴 — primeKeyItems 의 ctx 매칭 가상 토큰.
            // list/view 의 LEFT JOIN ON 절에서는 실제 PK 컬럼 idx 로 변환해야 SQL 실행 가능.
            // 단, g5_* / v_parts_* / vv_parts_* / mis_parts_* 등 외부 테이블/뷰는 it_id 가 실제 PK 컬럼이라
            // 변환하지 않고 원본 보존 (예: g5_shop_item.it_id, v_parts_storage_tree 등 외부 데이터 소스).
            $extra = preg_replace_callback(
                '/(\(\s*select\s+\w+\s+from\s+(\w+)\s+where\s+)it_id(\s*=)/i',
                function (array $m): string {
                    $t = strtolower($m[2]);
                    if (str_starts_with($t, 'g5_')
                        || str_starts_with($t, 'mis_parts_')
                        || str_starts_with($t, 'v_parts_')
                        || str_starts_with($t, 'vv_parts_')
                        || str_starts_with($t, 'v_mis_parts_')
                        || str_starts_with($t, 'vv_mis_parts_')) {
                        return $m[0]; // 외부 테이블 — it_id 보존
                    }
                    return $m[1] . 'idx' . $m[3];
                },
                $extra
            ) ?? $extra;

            $extra = str_replace('@outer_tbname', $joinAlias, $extra);
            // 세션 플레이스홀더 — v7 표준($) + 구표준(@) + v6 호환 모두 지원
            $extra = str_replace([
                '$misSessionUserId', '@misSessionUserId', '@MisSession_UserID',
            ], $safeId, $extra);

            // v6 컬럼명 치환 (table_ 접두어 있는 참조)
            $extra = $this->resolveExpression($extra, $localAliasMap);

            if (str_starts_with($extra, '(')) {
                $on .= " AND {$extra}";
            } elseif (str_contains($extra, '=') || str_contains($extra, '<') || str_contains($extra, '>')) {
                // 점(.) 없는 bare 컬럼 참조 → joinAlias 로 자격 부여 (ambiguous 방지)
                if (!str_contains($extra, '.')) {
                    $extra = preg_replace('/\b([A-Za-z_]\w*)(?=\s*[=<>!])/', "{$joinAlias}.$1", $extra) ?? $extra;
                }
                $on .= " AND ({$extra})";
            } else {
                $on .= " AND {$joinAlias}.{$extra}";
            }
        }

        return ['table' => $tableName, 'alias' => $joinAlias, 'on' => $on];
    }

    /**
     * group_compute → JOIN 파싱
     * 포맷: "[dbo.]RealTableName alias ON condition"  (alias는 임의 식별자)
     * @MisSession_UserID 치환
     */
    /**
     * helplist 팝업 데이터 (act=helplistItems).
     *  - prime_key 의 displayCols(';' 분할) 와 schema_validation 의 helplist 라벨 매칭
     *  - 컬럼별 LIKE 필터 + 페이징
     * @return array
     */
    public function helplistItems(array $params, object $user): array
    {
        $gubun = (int)($params['gubun'] ?? 0);
        $field = trim($params['field'] ?? '');
        $page  = max(1, (int)($params['page'] ?? 1));
        $size  = max(1, min(200, (int)($params['size'] ?? 20)));

        if (!$gubun || $field === '') return ['success'=>false, 'message'=>'gubun, field 필수'];

        $menu = $this->getMenu($gubun);
        $effectiveRealPid = trim((string)($menu['_fields_real_pid'] ?? '')) ?: trim((string)($menu['real_pid'] ?? ''));

        $st = $this->pdo->prepare(
            "SELECT prime_key, schema_validation, alias_name FROM mis_menu_fields
              WHERE real_pid = ? AND alias_name = ? AND useflag = '1' "
        );
        $st->execute([$effectiveRealPid, $field]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$row || empty($row['prime_key'])) {
            return ['success'=>true, 'columns'=>[], 'rows'=>[], 'total'=>0];
        }

        $helplist = $this->parseHelplist((string)($row['schema_validation'] ?? ''));
        if (!$helplist) return ['success'=>false, 'message'=>'helplist 미설정'];

        $parts = array_map('trim', explode('#', trim($row['prime_key'])));
        if (count($parts) < 4) return ['success'=>false, 'message'=>'prime_key 형식 오류'];

        $rawDisplay = $parts[0];
        $tableName  = $this->resolveTable($parts[1]);
        $rawSort    = $parts[2] !== '' ? $parts[2] : '1';
        $valueField = $this->resolveColumn($tableName, $parts[3]);
        $rawCond    = $parts[4] ?? '';

        $displayCols = $this->splitTopLevelSemi($rawDisplay);
        if (count($displayCols) !== count($helplist)) {
            return ['success'=>false, 'message'=>'helplist/prime_key 컬럼수 불일치'];
        }

        $alias    = $row['alias_name'];
        $tblAlias = 'table_' . $alias;
        $aliasToTable = [$tblAlias => $tableName];

        // SELECT 컬럼들
        $selectCols = [];
        $colMeta    = [];
        foreach ($displayCols as $i => $col) {
            $expr = str_replace('@outer_tbname', $tblAlias, $col);
            $expr = $this->resolveExpression($expr, $aliasToTable);
            // helplist 라벨: "name" 또는 "name.N" (N = 폭)
            $label = $helplist[$i];
            $width = 0;
            if (preg_match('/^(.+?)\.(\d+)$/', $label, $m)) { $label = $m[1]; $width = (int)$m[2]; }
            $key = "c{$i}";
            $selectCols[] = "({$expr}) AS `{$key}`";
            $colMeta[] = ['label'=>$label, 'key'=>$key, 'width'=>$width];
        }
        // value 필드도 추가 — 행 선택 시 form 의 코드칸에 들어감
        $selectCols[] = "{$tblAlias}.{$valueField} AS `_value`";

        // WHERE
        $bindings = [];
        $cond = '';
        if ($rawCond !== '') {
            $rc = str_replace('@outer_tbname', $tblAlias, $rawCond);
            $rc = $this->resolveExpression($rc, $aliasToTable);
            $cond .= " AND ({$rc})";
        }
        // 컬럼별 필터
        $filters = $params['filters'] ?? [];
        if (is_string($filters)) $filters = json_decode($filters, true) ?: [];
        if (!is_array($filters)) $filters = [];
        foreach ($colMeta as $i => $cm) {
            $fv = trim((string)($filters[$cm['key']] ?? $filters[$cm['label']] ?? ''));
            if ($fv === '') continue;
            $expr = str_replace('@outer_tbname', $tblAlias, $displayCols[$i]);
            $expr = $this->resolveExpression($expr, $aliasToTable);
            $cond .= " AND ({$expr}) LIKE ?";
            $bindings[] = '%' . $fv . '%';
        }

        $sortClause = is_numeric($rawSort) ? "ORDER BY {$rawSort}" : "ORDER BY 1";
        $offset = ($page - 1) * $size;

        $sql = "SELECT " . implode(', ', $selectCols)
             . " FROM `{$tableName}` {$tblAlias}"
             . " WHERE 1=1 {$cond}"
             . " {$sortClause}"
             . " LIMIT {$size} OFFSET {$offset}";

        $countSql = "SELECT COUNT(*) FROM `{$tableName}` {$tblAlias} WHERE 1=1 {$cond}";

        try {
            $st = $this->pdo->prepare($sql);
            $st->execute($bindings);
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC);
            $cs = $this->pdo->prepare($countSql);
            $cs->execute($bindings);
            $total = (int)$cs->fetchColumn();
            $resp = [
                'success'=>true, 'columns'=>$colMeta, 'rows'=>$rows,
                'total'=>$total, 'page'=>$page, 'size'=>$size,
                'value_key'=>'_value',
            ];
            if (($params['debug'] ?? '') === '1') $resp['_sql'] = $sql;
            return $resp;
        } catch (\Throwable $e) {
            return ['success'=>false, 'message'=>$e->getMessage(), '_sql'=>$sql];
        }
    }

    /** schema_validation 의 "helplist": [...] 추출 */
    private function parseHelplist(string $sv): array
    {
        $sv = trim($sv);
        if ($sv === '') return [];
        if (preg_match('/"helplist"\s*:\s*(\[[^\]]*\])/', $sv, $m)) {
            $arr = json_decode($m[1], true);
            if (is_array($arr)) return $arr;
        }
        // 전체를 객체로 시도
        $obj = json_decode($sv, true);
        if (is_array($obj) && isset($obj['helplist']) && is_array($obj['helplist'])) return $obj['helplist'];
        // {} 감싸기 시도
        $obj = json_decode('{' . trim($sv, ", \t\n{}") . '}', true);
        if (is_array($obj) && isset($obj['helplist']) && is_array($obj['helplist'])) return $obj['helplist'];
        return [];
    }

    /** 톱레벨 ';' 분할 — 괄호 안의 ';' 는 무시 */
    private function splitTopLevelSemi(string $expr): array
    {
        $parts = []; $cur = ''; $depth = 0;
        for ($i = 0, $n = strlen($expr); $i < $n; $i++) {
            $c = $expr[$i];
            if ($c === '(') $depth++;
            elseif ($c === ')') $depth = max(0, $depth - 1);
            if ($c === ';' && $depth === 0) {
                $t = trim($cur);
                if ($t !== '') $parts[] = $t;
                $cur = '';
            } else {
                $cur .= $c;
            }
        }
        $t = trim($cur);
        if ($t !== '') $parts[] = $t;
        return $parts;
    }

    private function parseJoinDef(string $groupCompute, string $userId, array $aliasToTable = []): ?array
    {
        $safeId = preg_replace('/[^a-zA-Z0-9_\-@.]/', '', $userId);

        // dbo. 스키마 접두어 제거 (MS SQL → MariaDB)
        $gc = preg_replace('/\bdbo\./i', '', $groupCompute);
        // 세션 플레이스홀더 치환 — v7 표준 $misSessionUserId, v6 호환 @misSessionUserId / @MisSession_UserID
        $gc = str_replace(
            ['$misSessionUserId', '@misSessionUserId', '@MisSession_UserID'],
            $safeId,
            $gc
        );

        // 포맷: TableName alias ON condition  (alias는 임의 식별자 — 한글 포함 허용)
        if (!preg_match('/^(\S+)\s+(\S+)\s+on\s+(.+)$/is', trim($gc), $m)) {
            return null;
        }

        $v7table = $this->resolveTable($m[1]);
        $alias   = $m[2];

        // ON 절의 v6 컬럼명 치환
        $localAliasMap = array_merge($aliasToTable, [$alias => $v7table]);
        $onCond = $this->resolveExpression(trim($m[3]), $localAliasMap);

        return ['table' => $v7table, 'alias' => $alias, 'on' => $onCond];
    }

    private function log(string $action, int $gubun, $idx, object $user): void
    {
        try {
            // user_id 까지 함께 기록 (이전엔 wdater 누락으로 NULL 만 남았음)
            $uid = (string)($user->uid ?? '');
            $this->pdo->prepare(
                'INSERT INTO mis_activity_logs (log_type, menu_idx, link_result, ip, wdate, wdater, useflag)
                 VALUES (?,?,?,?,NOW(),?,"1")'
            )->execute([
                $action,
                $gubun,
                (string)$idx,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $uid,
            ]);
        } catch (\Throwable) {}
    }

    /**
     * mis_read_history INSERT — 815 메뉴('게시물 작성 및 조회 이력')의 데이터 소스.
     * V6 에선 게시물 작성/수정/삭제 시 자동 기록되었으나 V7 마이그 시 누락 → 여기서 보강.
     * @param string $jagyeok '작성' / '수정' / '삭제' (mis_read_history.자격)
     */
    private function logReadHistory(string $jagyeok, array $menu, $idx, object $user): void
    {
        try {
            $rp  = trim((string)($menu['real_pid'] ?? ''));
            $uid = (string)($user->uid ?? '');
            if ($rp === '' || $uid === '') return;
            $this->pdo->prepare(
                'INSERT INTO mis_read_history
                    (real_pid, widx, user_id, 자격, wdate, wdater, read_date, hit, useflag)
                 VALUES (?, ?, ?, ?, NOW(), ?, NOW(), 1, "1")'
            )->execute([
                $rp,
                (string)$idx,
                $uid,
                $jagyeok,
                $uid,
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('logReadHistory failed', ['err' => $e->getMessage()]);
        }
    }
}
