<?php

namespace App;

use PDO;

/**
 * 새 메뉴(프로그램) 생성 로직 — 314번 "추가" 버튼 전용
 *
 * body 구조:
 *   srcIdx          : int   — 추가 위치 기준 행 idx
 *   position        : int   — 1~6 (1~4: 같은 레벨, 5~6: 하위)
 *                      1=맨 위, 2=바로 위, 3=바로 아래, 4=맨 아래, 5=하위 맨 위, 6=하위 맨 아래
 *   menuName        : string
 *   menuType        : string (00/01/06/11/12/13/22)
 *   addUrl          : string  (쿼리스트링 또는 URL)
 *   misJoinPid      : string  (menuType=06 일 때)
 *   sourceRealPid   : string  (menuType=01 일 때, 복제 원본)
 */
class MenuCreator
{
    public static function create(array $body, object $user, PDO $pdo): array
    {
        $srcIdx    = (int)($body['srcIdx'] ?? 0);
        $position  = (int)($body['position'] ?? 0);
        $menuName  = trim((string)($body['menuName'] ?? ''));
        $menuType  = (string)($body['menuType'] ?? '');
        $addUrl    = trim((string)($body['addUrl'] ?? ''));
        $joinPid   = trim((string)($body['misJoinPid'] ?? ''));
        $sourceRp  = trim((string)($body['sourceRealPid'] ?? ''));
        // 업무용MIS 서브옵션 — 'clone' (기본) 또는 'table' (테이블 기반 자동생성)
        $subOption = trim((string)($body['subOption'] ?? 'clone'));
        $tableName = trim((string)($body['tableName'] ?? ''));
        $dbAlias   = trim((string)($body['dbAlias'] ?? ''));

        if ($srcIdx <= 0)            return self::fail('기준 행이 없습니다.');
        if ($position < 1 || $position > 6) return self::fail('추가 위치가 올바르지 않습니다.');
        if ($menuName === '')        return self::fail('메뉴명을 입력하세요.');
        if (!in_array($menuType, ['00','01','06','11','12','13','22'], true)) {
            return self::fail('메뉴 타입이 올바르지 않습니다.');
        }
        if ($menuType === '06' && $joinPid === '') {
            return self::fail('MIS Join 타입은 mis_join_pid 값이 필요합니다.');
        }
        if ($menuType === '01') {
            if ($subOption === 'table') {
                if ($tableName === '') return self::fail('테이블/뷰 이름을 입력하세요.');
            } elseif ($sourceRp === '') {
                return self::fail('업무용MIS 는 복제할 원본 프로그램을 선택해야 합니다.');
            }
        }

        // 기준 행 조회
        $st = $pdo->prepare('SELECT * FROM mis_menus WHERE idx = ? LIMIT 1');
        $st->execute([$srcIdx]);
        $src = $st->fetch(PDO::FETCH_ASSOC);
        if (!$src) return self::fail('기준 행이 없습니다.');

        $thisAutoGubun = (string)($src['autogubun'] ?? '');
        $thisRealPid   = $src['real_pid'];
        $len           = strlen($thisAutoGubun);

        // 허용 규칙 체크 (v6 로직 포팅)
        if ($len === 6 && $position >= 5)              return self::fail('선택한 경로에서는 더이상 하위메뉴로 추가할 수 없습니다.');
        if ($len === 4 && $position >= 5 && $menuType === '00') return self::fail('선택한 경로에서는 하위메뉴를 메뉴표시용으로 추가할 수 없습니다.');
        if ($len === 6 && $position <= 4 && $menuType === '00') return self::fail('선택한 경로에서는 메뉴표시용으로 추가할 수 없습니다.');

        // full_siteID = real_pid 접두어
        if (!preg_match('/^(.+?)(\d+)$/', $thisRealPid, $m)) {
            return self::fail("real_pid 형식 오류: {$thisRealPid}");
        }
        $siteId  = $m[1];
        $userId  = (string)($user->uid ?? 'system');
        $now     = date('Y-m-d H:i:s');

        // AutoGubun / SortG2/4/6 계산
        [$newAutoGubun, $sortG2, $sortG4, $sortG6, $upRealPid] =
            self::calcSort($thisAutoGubun, $thisRealPid, $src['up_real_pid'] ?? '', $position);

        try {
            $pdo->beginTransaction();

            // 1) 임시 real_pid 로 INSERT → lastInsertId 로 실제 idx 확정 → real_pid 갱신
            //    new_gidx=83, auth_code='02' 기본값 (개발자 전용 권한)
            $tmpRp = $siteId . '__NEW__' . uniqid();
            $insSql = "INSERT INTO mis_menus
                (real_pid, menu_name, menu_type, up_real_pid, autogubun,
                 sort_g2, sort_g4, sort_g6, depth, useflag,
                 mis_join_pid, add_url, new_gidx, auth_code, wdater, wdate)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, '1', ?, ?, 83, '02', ?, ?)";
            $depth = intval($len / 2) + ($position >= 5 ? 1 : 0);
            $pdo->prepare($insSql)->execute([
                $tmpRp, $menuName, $menuType, $upRealPid, $newAutoGubun,
                $sortG2, $sortG4, $sortG6, $depth,
                ($menuType === '06' ? $joinPid : ''),
                $addUrl, $userId, $now,
            ]);
            $newIdx     = (int)$pdo->lastInsertId();
            $newRealPid = $siteId . str_pad((string)$newIdx, 6, '0', STR_PAD_LEFT);
            $pdo->prepare('UPDATE mis_menus SET real_pid = ? WHERE idx = ?')
                ->execute([$newRealPid, $newIdx]);

            // 2) 업무용MIS — 'clone' (복제) 또는 'table' (테이블 기반 자동생성)
            if ($menuType === '01') {
                if ($subOption === 'table') {
                    self::createFromTable($pdo, $newRealPid, $tableName, $dbAlias, $userId, $now);
                } else {
                    self::cloneFromSource($pdo, $sourceRp, $newRealPid, $userId, $now);
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return self::fail('생성 실패: ' . $e->getMessage());
        }

        // sort_g2/g4/g6 소수값(1.1 등)을 ROW_NUMBER() 기반 자연수로 재정렬 + 권한 재계산
        try {
            $pdo->prepare('CALL mis_user_authority_proc(?)')->execute([$siteId]);
        } catch (\Throwable $e) { /* ignore */ }

        // 캐시 무효화
        try {
            $cache = new MisCache();
            $cache->invalidateByRealPid($newRealPid);
            $cache->invalidateByRealPid($thisRealPid);
        } catch (\Throwable $e) { /* ignore */ }

        return [
            'success'    => true,
            'newIdx'     => $newIdx,
            'newRealPid' => $newRealPid,
            'message'    => "생성 완료: {$newRealPid}",
        ];
    }

    /**
     * v6 save_writeBefore 의 AutoGubun / SortG2/4/6 계산 로직 포팅
     * return [newAutoGubun, sortG2, sortG4, sortG6, upRealPid]
     */
    private static function calcSort(string $thisAutoGubun, string $thisRealPid, string $thisUpRealPid, int $position): array
    {
        $len = strlen($thisAutoGubun);
        $l2  = substr($thisAutoGubun, 0, 2);
        $l4  = $len >= 4 ? substr($thisAutoGubun, 2, 2) : '';
        $l6  = $len >= 6 ? substr($thisAutoGubun, 4, 2) : '';

        if ($position >= 5) {
            // 하위로 추가
            $newAuto = $thisAutoGubun . '99';
            $g2 = $l2;
            if ($len === 2) {
                $g4 = ($position === 5) ? 0.1 : 99;
                $g6 = 0;
            } else { // len === 4
                $g4 = $l4;
                $g6 = ($position === 5) ? 0.1 : 99;
            }
            $up = $thisRealPid;
        } else {
            // 같은 레벨
            $newAuto = $thisAutoGubun;
            if ($len === 2) {
                $l2n = (float)$l2;
                $g2 = match ($position) { 1 => 0.1, 2 => $l2n - 0.1, 3 => $l2n + 0.1, 4 => 99 };
                $g4 = 0; $g6 = 0;
            } elseif ($len === 4) {
                $g2 = $l2;
                $l4n = (float)$l4;
                $g4 = match ($position) { 1 => 0.1, 2 => $l4n - 0.1, 3 => $l4n + 0.1, 4 => 99 };
                $g6 = 0;
            } else { // len === 6
                $g2 = $l2;
                $g4 = $l4;
                $l6n = (float)$l6;
                $g6 = match ($position) { 1 => 0.1, 2 => $l6n - 0.1, 3 => $l6n + 0.1, 4 => 99 };
            }
            $up = $thisUpRealPid;
        }

        return [$newAuto, $g2, $g4, $g6, $up];
    }

    /**
     * 업무용MIS: 소스 프로그램 속성/필드/권한/파일 복제 (266 완전복제와 동일 패턴)
     */
    private static function cloneFromSource(PDO $pdo, string $srcRp, string $newRp, string $userId, string $now): void
    {
        // 원본 menu 조회
        $st = $pdo->prepare('SELECT * FROM mis_menus WHERE real_pid = ? LIMIT 1');
        $st->execute([$srcRp]);
        $origin = $st->fetch(PDO::FETCH_ASSOC);
        if (!$origin) throw new \RuntimeException("원본 프로그램 없음: {$srcRp}");

        // 파일 내용 우선
        $srcPhp   = PROGRAMS_PATH . "/{$srcRp}.php";
        $srcPrint = PROGRAMS_PATH . "/{$srcRp}_print.html";
        $fileLogic = file_exists($srcPhp)   ? file_get_contents($srcPhp)   : null;
        $filePrint = file_exists($srcPrint) ? file_get_contents($srcPrint) : null;

        // 치환 대상 필드(텍스트/SQL)
        $replaceFields = ['add_logic','add_logic_treat','add_logic_print',
                          'brief_insert_sql','base_filter','use_condition',
                          'delete_query','read_only_cond'];

        // 새 menu 행의 특정 컬럼을 소스에서 덮어쓰기 (이미 INSERT 된 행에 UPDATE)
        $updateCols = [];
        $updateVals = [];
        $copyTargets = ['g01','g02','g03','g07','table_name','base_filter','use_condition',
                        'delete_query','read_only_cond','brief_insert_sql','add_logic',
                        'add_logic_treat','add_logic_print','is_use_print','is_use_form',
                        'language_code','dbalias'];
        foreach ($copyTargets as $c) {
            if (!array_key_exists($c, $origin)) continue;
            $v = $origin[$c];
            if (is_string($v) && $v !== '' && strpos($v, $srcRp) !== false) {
                $v = str_replace($srcRp, $newRp, $v);
            }
            $updateCols[] = "`$c` = ?";
            $updateVals[] = $v;
        }
        // 파일 내용이 있으면 그것으로 덮어쓰기 (치환 포함)
        if ($fileLogic !== null) {
            $v = strpos($fileLogic, $srcRp) !== false ? str_replace($srcRp, $newRp, $fileLogic) : $fileLogic;
            $updateCols[] = "`add_logic` = ?";
            $updateVals[] = $v;
            $fileLogic = $v;
        }
        if ($filePrint !== null) {
            $v = strpos($filePrint, $srcRp) !== false ? str_replace($srcRp, $newRp, $filePrint) : $filePrint;
            $updateCols[] = "`add_logic_print` = ?";
            $updateVals[] = $v;
            $filePrint = $v;
        }
        if ($updateCols) {
            $updateVals[] = $newRp;
            $pdo->prepare('UPDATE mis_menus SET ' . implode(',', $updateCols) . ' WHERE real_pid = ?')
                ->execute($updateVals);
        }

        // mis_menu_fields 복사 — INSERT...SELECT 로 서버측 직접 복사
        //   → bit(1) 컬럼 PDO 바인딩 버그 회피 + grid_x/y/w/h, form_layout_responsive 등 뷰디자이너 필드 무손실 복사
        self::copyFieldsTable($pdo, 'mis_menu_fields', $srcRp, $newRp, $userId, $now,
            excludeCols: ['idx','real_pid','wdate','lastupdate','wdater','lastupdater',
                          'hit','ip','comment_count','check_result'],
            replaceTextCols: ['items','schema_validation','default_value','grid_templete',
                              'group_compute','prime_key','grid_relation','form_layout_responsive']
        );

        // mis_menu_auth 는 복사 안 함 — new_gidx=83/auth_code='02' 기본값 + mis_user_authority_proc 가 재계산

        // programs/*.php / _print.html 복사
        if ($fileLogic !== null) {
            @file_put_contents(PROGRAMS_PATH . "/{$newRp}.php", $fileLogic);
        }
        if ($filePrint !== null) {
            @file_put_contents(PROGRAMS_PATH . "/{$newRp}_print.html", $filePrint);
        }
    }

    /**
     * 업무용MIS — 테이블/뷰 이름 기반 자동 생성
     *   - mis_menus 의 table_name, g01='simple_list', dbalias 갱신
     *   - INFORMATION_SCHEMA.COLUMNS 조회 → mis_menu_fields 자동 INSERT
     *   - g07(메뉴 읽기전용) 은 자동 설정 안 함 — 권한(auth_code/authority_level)으로 결정.
     *     뷰 기반 메뉴라도 사용자로직(save_*Before 훅)으로 INSERT/UPDATE 가능하므로
     *     자동으로 읽기전용으로 묶지 않는다.
     */
    private static function createFromTable(PDO $pdo, string $newRp, string $tableInput, string $dbAlias, string $userId, string $now): void
    {
        // schema.table 형태 분리
        $schema = '';
        $tableName = $tableInput;
        if (strpos($tableInput, '.') !== false) {
            [$schema, $tableName] = explode('.', $tableInput, 2);
        }
        $checkSchema = $schema !== '' ? $schema : ($_ENV['DB_NAME'] ?? 'speedmis_v7');

        // 존재 여부 검증
        $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?");
        $st->execute([$checkSchema, $tableName]);
        if ((int)$st->fetchColumn() === 0) {
            throw new \RuntimeException("테이블/뷰 '{$checkSchema}.{$tableName}' 이 존재하지 않습니다.");
        }

        // mis_menus 의 컬럼 갱신 (이미 INSERT 된 행에)
        $cols = [
            'table_name' => $tableName,
            'g01'        => 'simple_list',
            'language_code' => 'ko',
        ];
        if ($dbAlias !== '' && $dbAlias !== 'default') $cols['dbalias'] = $dbAlias;

        $sets = [];
        $vals = [];
        foreach ($cols as $k => $v) { $sets[] = "`$k` = ?"; $vals[] = $v; }
        $vals[] = $newRp;
        $pdo->prepare('UPDATE mis_menus SET ' . implode(', ', $sets) . ' WHERE real_pid = ?')->execute($vals);

        // 컬럼 정보 조회
        $st = $pdo->prepare("
            SELECT COLUMN_NAME, ORDINAL_POSITION, DATA_TYPE,
                   CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE, COLUMN_DEFAULT,
                   COLUMN_COMMENT, COLUMN_KEY
              FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
               AND COLUMN_NAME NOT LIKE '%:%'
               AND COLUMN_NAME NOT LIKE '%-%'
               AND COLUMN_NAME NOT LIKE '%=%'
             ORDER BY ORDINAL_POSITION
        ");
        $st->execute([$checkSchema, $tableName]);
        $colsInfo = $st->fetchAll(PDO::FETCH_ASSOC);
        if (empty($colsInfo)) return;

        // 멱등성 — 기존 fields 정리
        $pdo->prepare('DELETE FROM mis_menu_fields WHERE real_pid = ?')->execute([$newRp]);

        $ins = $pdo->prepare("
            INSERT INTO mis_menu_fields
              (real_pid, sort_order, db_field, db_table, alias_name, col_title,
               col_width, schema_type, max_length, useflag, wdate, wdater)
            VALUES
              (?, ?, ?, 'table_m', ?, ?, ?, ?, ?, '1', ?, ?)
        ");

        foreach ($colsInfo as $c) {
            $colName = $c['COLUMN_NAME'];
            $type    = strtolower($c['DATA_TYPE']);
            $maxLen  = $c['CHARACTER_MAXIMUM_LENGTH'] !== null ? (int)$c['CHARACTER_MAXIMUM_LENGTH'] : null;
            $title   = trim((string)$c['COLUMN_COMMENT']) !== '' ? $c['COLUMN_COMMENT'] : $colName;
            $isPK    = $c['COLUMN_KEY'] === 'PRI';

            // schema_type 매핑
            $schemaType = '';
            if      ($type === 'date')                                   $schemaType = 'date';
            elseif  (in_array($type, ['datetime', 'timestamp']))         $schemaType = 'datetime';
            elseif  (in_array($colName, ['useflag', 'use_yn']))          $schemaType = 'boolean';

            // col_width 매핑
            $colWidth = 12;
            if      ($isPK || in_array($colName, ['idx', 'num']))                            $colWidth = 5;
            elseif  (in_array($colName, ['useflag','use_yn','HIT','hit','IP','ip']))         $colWidth = -1;
            elseif  (in_array($colName, ['wdate','lastupdate','last_update']))               $colWidth = 10;
            elseif  (in_array($colName, ['wdater','lastupdater','last_updater']))            $colWidth = 8;
            elseif  (in_array($type, ['text','longtext','mediumtext','json']))               $colWidth = 30;
            elseif  ($type === 'varchar' && $maxLen !== null && $maxLen >= 200)              $colWidth = 25;
            elseif  ($type === 'varchar')                                                    $colWidth = 15;
            elseif  (in_array($type, ['int','bigint','smallint','tinyint','mediumint']))     $colWidth = 8;
            elseif  (in_array($type, ['decimal','float','double']))                          $colWidth = 10;

            $ins->execute([
                $newRp,
                (int)$c['ORDINAL_POSITION'],
                $colName,
                $colName,
                $title,
                $colWidth,
                $schemaType,
                $maxLen,
                $now,
                $userId,
            ]);
        }

        // 모든 필드 INSERT 완료 후 자동 폼 디자인 적용
        self::applyAutoDesign($pdo, $newRp);
    }

    /**
     * 자동 폼 디자인 적용
     *   - 보호 필드 (protection=1 또는 grid_view_fixed=1) 는 skip
     *   - col_width / schema_type / grid_ctl_name 기반으로 grid_view_xl/lg/md/sm/hight/class 자동 설정
     * @return array{updated:int, skipped:int, total:int}
     */
    public static function applyAutoDesign(PDO $pdo, string $realPid): array
    {
        $st = $pdo->prepare("
            SELECT idx, alias_name, schema_type, grid_ctl_name, col_width, max_length,
                   COALESCE(protection, 0)       AS _protection,
                   COALESCE(grid_view_fixed, 0)  AS _fixed
              FROM mis_menu_fields
             WHERE real_pid = ?
               AND col_width >= 0
             ORDER BY sort_order, idx
        ");
        $st->execute([$realPid]);
        $fields = $st->fetchAll(PDO::FETCH_ASSOC);

        $upd = $pdo->prepare("
            UPDATE mis_menu_fields
               SET grid_view_xl = ?, grid_view_lg = ?, grid_view_md = ?, grid_view_sm = ?,
                   grid_view_hight = ?, grid_view_class = ?
             WHERE idx = ?
        ");

        $updated = 0; $skipped = 0;
        foreach ($fields as $f) {
            // 보호 필드 skip
            if ((int)$f['_protection'] === 1 || (int)$f['_fixed'] === 1) { $skipped++; continue; }

            $cw   = (int)($f['col_width'] ?? 12);
            $type = strtolower((string)($f['schema_type']    ?? ''));
            $ctl  = strtolower((string)($f['grid_ctl_name']  ?? ''));

            // 기본 폭 — col_width 기반
            if      ($cw <= 5)   { $xl = 1;  $lg = 2;  $md = 3;  $sm = 3;  }
            elseif  ($cw <= 15)  { $xl = 3;  $lg = 3;  $md = 6;  $sm = 6;  }
            elseif  ($cw <= 30)  { $xl = 6;  $lg = 6;  $md = 12; $sm = 12; }
            else                 { $xl = 12; $lg = 12; $md = 12; $sm = 12; }

            $hight = 1;

            // 컨트롤별 보정
            if (in_array($ctl, ['dropdownitem','dropdownlist','select'], true) || $type === 'dropdownitem') {
                // 셀렉트박스는 너무 좁으면 안 좋음
                $xl = max($xl, 3); $lg = max($lg, 3); $md = max($md, 6); $sm = max($sm, 6);
            } elseif ($ctl === 'textarea' || $type === 'textarea') {
                $hight = 4;
                $xl = max($xl, 6); $lg = max($lg, 6); $md = 12; $sm = 12;
            } elseif ($ctl === 'html' || $type === 'html') {
                $hight = 58;
                $xl = 12; $lg = 12; $md = 12; $sm = 12;
            } elseif (in_array($ctl, ['attach', 'image', 'file'], true)) {
                $xl = 12; $lg = 12; $md = 12; $sm = 12;
            }

            // Bootstrap 호환 클래스 (기존 314 패턴 따름)
            $cls = "col-xs-{$sm} col-sm-{$md} col-md-{$lg} col-lg-{$xl} row-{$hight}";

            $upd->execute([$xl, $lg, $md, $sm, $hight, $cls, (int)$f['idx']]);
            $updated++;
        }

        return ['updated' => $updated, 'skipped' => $skipped, 'total' => count($fields)];
    }

    private static function fail(string $msg): array
    {
        return ['success' => false, 'message' => $msg];
    }

    /**
     * INSERT...SELECT 로 real_pid 기반 행들을 서버측에서 복사
     *   - bit(1) 등 PDO 바인딩에 민감한 컬럼까지 정확히 복사
     *   - 텍스트 컬럼(replaceTextCols)에 포함된 원본 real_pid 문자열은 사후 UPDATE 로 새 real_pid 치환
     *
     * @param string[] $excludeCols     제외 컬럼 (복사 안 함)
     * @param string[] $replaceTextCols 복사 후 REPLACE 대상 텍스트 컬럼
     */
    public static function copyFieldsTable(
        PDO $pdo,
        string $tableName,
        string $srcRp,
        string $newRp,
        string $userId,
        string $now,
        array $excludeCols,
        array $replaceTextCols
    ): void {
        $allCols = $pdo->query("SHOW COLUMNS FROM `{$tableName}`")->fetchAll(PDO::FETCH_COLUMN);
        $copyCols = array_values(array_diff($allCols, $excludeCols));

        // INSERT 컬럼 리스트: real_pid + wdater + wdate + copyCols
        $insertCols = array_merge(['real_pid', 'wdater', 'wdate'], $copyCols);
        $colList    = implode(',', array_map(fn($c) => "`$c`", $insertCols));

        // SELECT 표현식: 고정값 3개 + 원본 컬럼 그대로
        $selectParts = ['?', '?', '?'];
        foreach ($copyCols as $c) $selectParts[] = "`$c`";
        $selectList = implode(',', $selectParts);

        $orderBy = in_array('sort_order', $allCols, true) ? 'ORDER BY sort_order, idx' : 'ORDER BY idx';

        $sql = "INSERT INTO `{$tableName}` ({$colList})
                SELECT {$selectList} FROM `{$tableName}` WHERE real_pid = ? {$orderBy}";
        $pdo->prepare($sql)->execute([$newRp, $userId, $now, $srcRp]);

        // 텍스트 컬럼에 원본 real_pid 가 들어있으면 새 real_pid 로 치환
        $setParts = [];
        foreach ($replaceTextCols as $c) {
            if (in_array($c, $copyCols, true)) {
                $setParts[] = "`$c` = REPLACE(`$c`, ?, ?)";
            }
        }
        if ($setParts) {
            $vals = [];
            foreach ($setParts as $_) { $vals[] = $srcRp; $vals[] = $newRp; }
            $vals[] = $newRp;
            $pdo->prepare("UPDATE `{$tableName}` SET " . implode(', ', $setParts) . " WHERE real_pid = ?")
                ->execute($vals);
        }
    }
}
