<?php
/**
 * 창고 조회 (6039) — parts_storage 트리 (최대 10 레벨, sortG01~sortG10)
 *
 * v6 _mis_addLogic/carparts006039.php 포팅.
 * - 자동정렬 / 인쇄대기열 추가 두 가지 사용자 정의 액션
 * - 등록 시 부모(upidx)의 autogubun 으로부터 신규 행의 sortGxx / autogubun 자동 산출
 *
 * 알리아스 노트 (alias_name_old → v7 alias_name)
 *   - table_upidxQnStationName → table_upidxQnstorage_name
 *   - table_wdaterQnusername    → table_wdaterQnuser_name
 *   - table_lastupdaterQnusername → table_lastupdaterQnuser_name
 */

/**
 * v6 misMenuList_change 의 v7 대응 — base_filter 동적 적용.
 * v7 훅은 program-per-program 으로 로딩되므로, 이 코드 자체는 6039 접근 시에만 실행됨.
 * 6078 에서도 동일 동작이 필요하면 carparts006078.php 에 같은 before_query 를 넣을 것.
 */
function before_query(&$menu, &$fields, &$params) {
    global $real_pid, $misSessionUserId;

    if ($real_pid === 'carparts006078') {
        $temp1 = $misSessionUserId;
        if ($temp1 === 'admin' || $temp1 === 'gadmin') {
            $temp1 = '포천';
        }
        // 주의: 6078 의 table_m 은 v_parts_storage_tree (depth 컬럼 없음).
        //       vs.full_name LIKE '포천%' 로 root 행 (full_name='root') 자동 배제.
        $menu['base_filter'] = " and vs.full_name like '" . addslashes($temp1) . "%'";
    }
}

/** 사용자 정의 버튼 등록 — list_json_init 에서 $customAction 으로 감지 */
function pageLoad() {
    global $actionFlag;

    if ($actionFlag === 'list') {
        $GLOBALS['_client_buttons'] = [
            ['label' => '수정내역 반영',          'action' => 'auto_sort'],
            ['label' => '아래 내역을 인쇄대기열에 추가', 'action' => 'print_queue'],
        ];
    }
}

/** 행마다 부모창고명 앞에 깊이 표시용 ":" 반복 (v6 rowFunction_UserDefine) */
function list_json_load(&$data) {
    $ag        = (string)($data['autogubun'] ?? '');
    $upidxName = (string)($data['table_upidxQnstorage_name'] ?? '');
    if ($ag !== '' && $upidxName !== '') {
        $depthChars = max(8, mb_strlen($ag, 'UTF-8')) - 8;
        $data['__html']['table_upidxQnstorage_name'] =
            str_repeat(':', $depthChars)
          . htmlspecialchars($upidxName, ENT_QUOTES, 'UTF-8');
    }
}

/** customAction 처리 — 자동정렬 / 인쇄대기열 추가 */
function list_json_init() {
    global $customAction, $allFilter, $__pdo;

    // [임시 디버그] 모든 호출 기록 — 운영 확인 후 제거
    $logLine = date('[Y-m-d H:i:s]') . ' 6039.list_json_init '
        . 'customAction=' . var_export($customAction, true)
        . ' payload=' . json_encode($GLOBALS['customActionPayload'] ?? null, JSON_UNESCAPED_UNICODE)
        . ' allFilter=' . (string)$allFilter
        . "\n";
    @file_put_contents(__DIR__ . '/../logs/6039_debug.log', $logLine, FILE_APPEND);

    if ($customAction === 'auto_sort') {
        $cnt = (int)$__pdo->query(
            "SELECT COUNT(idx) FROM parts_storage WHERE sortG01 = 0 AND useflag = '1'"
        )->fetchColumn();

        if ($cnt !== 1) {
            $GLOBALS['_client_alert'] = '1레벨 정렬값 중 0 으로 된 최상위 부서는 하나만 존재해야 합니다.';
            return;
        }

        try {
            $__pdo->exec('CALL parts_storage_ordering_proc()');
            $GLOBALS['_client_toast'] = '자동정렬이 완료되었습니다.';
        } catch (\Throwable $e) {
            $GLOBALS['_client_alert'] = '자동정렬 처리가 실패하였습니다: ' . $e->getMessage();
        }
        return;
    }

    if ($customAction === 'print_queue') {
        $payload     = $GLOBALS['customActionPayload'] ?? [];
        $checkedIdxs = array_values(array_filter(
            array_map('intval', (array)($payload['checkedIdxs'] ?? [])),
            fn($v) => $v > 0
        ));

        $logFile = __DIR__ . '/../logs/6039_debug.log';
        $logSql  = function(string $tag, string $sql, array $binds = []) use ($logFile) {
            // 바인딩 값을 SQL 에 그대로 박은 가독성 버전
            $rendered = $sql;
            foreach ($binds as $b) {
                $rendered = preg_replace('/\?/', is_int($b) ? (string)$b : "'" . addslashes((string)$b) . "'", $rendered, 1);
            }
            @file_put_contents($logFile,
                date('[Y-m-d H:i:s]') . " 6039.print_queue {$tag} SQL>>>\n"
              . preg_replace('/\s+/', ' ', $rendered) . "\n"
              . "binds=" . json_encode($binds, JSON_UNESCAPED_UNICODE) . "\n",
                FILE_APPEND);
        };

        @file_put_contents($logFile,
            date('[Y-m-d H:i:s]') . " 6039.print_queue ENTERED checkedIdxs=" . json_encode($checkedIdxs) . "\n",
            FILE_APPEND);

        try {
            // 1) 체크된 행이 있으면 그 행들에만 적용 (건수 무관)
            if (count($checkedIdxs) > 0) {
                $ph = implode(',', array_fill(0, count($checkedIdxs), '?'));

                // BEFORE 상태 스냅샷
                $beforeStmt = $__pdo->prepare("SELECT idx, storage_name, print_request_time, print_response_time FROM parts_storage WHERE idx IN ({$ph})");
                $beforeStmt->execute($checkedIdxs);
                @file_put_contents($logFile,
                    date('[Y-m-d H:i:s]') . " 6039.print_queue BEFORE=" . json_encode($beforeStmt->fetchAll(\PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE) . "\n",
                    FILE_APPEND);

                $sql = "UPDATE parts_storage
                           SET print_request_time  = NOW(),
                               print_response_time = NULL
                         WHERE useflag = '1' AND idx IN ({$ph})";
                $logSql('exec', $sql, $checkedIdxs);

                $stmt = $__pdo->prepare($sql);
                $stmt->execute($checkedIdxs);

                // AFTER 상태 스냅샷
                $afterStmt = $__pdo->prepare("SELECT idx, storage_name, print_request_time, print_response_time FROM parts_storage WHERE idx IN ({$ph})");
                $afterStmt->execute($checkedIdxs);
                @file_put_contents($logFile,
                    date('[Y-m-d H:i:s]') . " 6039.print_queue rowCount=" . $stmt->rowCount()
                  . " AFTER=" . json_encode($afterStmt->fetchAll(\PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE) . "\n",
                    FILE_APPEND);

                $GLOBALS['_client_toast'] = "{$stmt->rowCount()}건이 인쇄대기열에 추가되었습니다.";
                return;
            }

            // 2) 체크 없음 — 현재 필터로 조회된 전체 50 건 이하일 때만 일괄 추가
            $filters = json_decode((string)$allFilter, true) ?: [];
            [$whereSql, $bindings] = _buildPartsStorageWhere($filters);
            $whereClause = $whereSql !== '' ? " AND ({$whereSql})" : '';

            $countSql  = "SELECT COUNT(*) FROM parts_storage WHERE useflag = '1'{$whereClause}";
            $logSql('count', $countSql, $bindings);
            $countStmt = $__pdo->prepare($countSql);
            $countStmt->execute($bindings);
            $targetCnt = (int)$countStmt->fetchColumn();
            @file_put_contents($logFile,
                date('[Y-m-d H:i:s]') . " 6039.print_queue targetCnt={$targetCnt}\n",
                FILE_APPEND);

            if ($targetCnt > 50) {
                $GLOBALS['_client_alert'] =
                    "대상이 {$targetCnt} 건입니다. 체크 없이 한 번에 추가하려면 50 건 이하여야 합니다. "
                  . "원하는 행을 체크하시거나 필터를 더 좁혀 주세요.";
                return;
            }

            $sql = "UPDATE parts_storage
                       SET print_request_time  = NOW(),
                           print_response_time = NULL
                     WHERE useflag = '1'{$whereClause}";
            $logSql('exec', $sql, $bindings);
            $stmt = $__pdo->prepare($sql);
            $stmt->execute($bindings);
            @file_put_contents($logFile,
                date('[Y-m-d H:i:s]') . " 6039.print_queue rowCount=" . $stmt->rowCount() . "\n",
                FILE_APPEND);
            $GLOBALS['_client_toast'] = "{$stmt->rowCount()}건이 인쇄대기열에 추가되었습니다.";
        } catch (\Throwable $e) {
            @file_put_contents($logFile,
                date('[Y-m-d H:i:s]') . " 6039.print_queue EXCEPTION=" . $e->getMessage() . "\n",
                FILE_APPEND);
            $GLOBALS['_client_alert'] = '인쇄대기열 추가 실패: ' . $e->getMessage();
        }
        return;
    }
}

/**
 * allFilter JSON → parts_storage 직접 컬럼 대상 WHERE 절
 * UPDATE 에서 사용하므로 JOIN 별칭(vs.full_name 등) 필터는 미리 제거.
 * v7 QueryBuilder 를 그대로 사용하여 toolbar_ 접두어 / between / in 등 모든 operator 일관 지원.
 * 반환: [where SQL (placeholders, "WHERE" 접두어 없음), bindings]
 */
function _buildPartsStorageWhere(array $filters): array {
    // parts_storage 직접 컬럼 + 가상 prefix 컬럼만 허용 — toolbar_ 접두어 떼고 검사
    static $allowed = [
        'idx', 'storage_name', 'autogubun', 'fixgubun', 'depth', 'remark',
        'wdater', 'lastupdater', 'wdate', 'lastupdate', 'useflag',
        'sortG01','sortG02','sortG03','sortG04','sortG05',
        'sortG06','sortG07','sortG08','sortG09','sortG10',
        'p4','p8','p12','p16','p20','p24','p28','p32','p36',
        'print_request_time', 'print_response_time',
    ];

    $kept = [];
    foreach ($filters as $f) {
        $field = trim((string)($f['field'] ?? ''));
        if ($field === '') continue;
        $bare = str_starts_with($field, 'toolbar_') ? substr($field, 8) : $field;
        if (!in_array($bare, $allowed, true)) continue;
        $kept[] = $f;
    }

    if (empty($kept)) return ['', []];

    $qb  = new \App\QueryBuilder();
    $res = $qb->buildWhere($kept);
    // buildWhere 는 "WHERE ..." 형태로 반환 → "WHERE " 접두어 제거
    $sql = preg_replace('/^\s*WHERE\s+/i', '', (string)($res['sql'] ?? '')) ?? '';
    return [$sql, (array)($res['bindings'] ?? [])];
}

/** 신규 등록 시 — 부모 autogubun 으로부터 자식의 autogubun / sortGxx 자동 산출 */
function save_writeBefore(&$updateList) {
    global $__pdo;

    $upidx = (int)($updateList['upidx'] ?? 0);
    if ($upidx <= 0) return;

    $st = $__pdo->prepare('SELECT autogubun FROM parts_storage WHERE idx = ? LIMIT 1');
    $st->execute([$upidx]);
    $autogubun = (string)$st->fetchColumn();

    // 모든 sortGxx 초기화
    for ($i = 1; $i <= 10; $i++) {
        $updateList['sortG' . str_pad((string)$i, 2, '0', STR_PAD_LEFT)] = 0;
    }

    $len = strlen($autogubun);

    // 신규는 부모 autogubun 뒤에 '9999' 를 붙여 임시 자리 차지 → ordering_proc 가 재정렬
    if ($autogubun === '00') {
        $updateList['autogubun'] = '9999';
        $updateList['sortG01']   = 9999;
    } elseif ($len > 0 && $len % 4 === 0) {
        // 4/8/12/16/.../36
        $updateList['autogubun'] = $autogubun . '9999';
        $level = intdiv($len, 4);                        // 1=깊이1
        // 부모 길이 4n → 자식 깊이 n+1 → sortG(n+1) = 9999
        for ($i = 1; $i <= $level; $i++) {
            $col = 'sortG' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
            $updateList[$col] = (int)substr($autogubun, ($i - 1) * 4, 4);
        }
        $sortKey = 'sortG' . str_pad((string)($level + 1), 2, '0', STR_PAD_LEFT);
        if ($level + 1 <= 10) {
            $updateList[$sortKey] = 9999;
        }
    }
}

/** 등록 후 — 자동정렬 프로시저 실행 */
function save_writeAfter($newIdx, &$afterScript) {
    global $__pdo;

    $cnt = (int)$__pdo->query(
        "SELECT COUNT(idx) FROM parts_storage WHERE sortG01 = 0 AND useflag = '1'"
    )->fetchColumn();

    if ($cnt !== 1) {
        $GLOBALS['_client_alert'] = '1레벨 정렬값 중 0 으로 된 최상위 부서는 하나만 존재해야 합니다.';
        return;
    }

    try {
        $__pdo->exec('CALL parts_storage_ordering_proc()');
        $GLOBALS['_client_toast'] = '자동정렬이 완료되었습니다.';
    } catch (\Throwable $e) {
        $GLOBALS['_client_alert'] = '자동정렬 처리가 실패하였습니다: ' . $e->getMessage();
    }
}
