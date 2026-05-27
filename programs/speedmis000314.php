<?php


  function pageLoad() {
     

      $GLOBALS['_client_buttons'] = [
          ['label' => '권한적용', 'action' => '권한적용']
      ];
  }


function list_json_init() {
    global $isFirstLoad, $customAction, $__pdo;

    if ($customAction === '권한적용') {
        // 일괄승인 로직
        execSql("call mis_user_authority_proc('{$_ENV['SITE_ID']}');");
        $GLOBALS['_client_toast'] = ['msg' => '권한적용 완료', 'type' => 'success', 'duration' => 8000];
    }

    // depth=1 각 행에 대해 자신+하위(useflag=1) 개수 사전 집계 → list_json_load 에서 사용
    if ($__pdo) {
        $rows = $__pdo->query("
            WITH RECURSIVE tree AS (
                SELECT idx, real_pid, up_real_pid, useflag, real_pid AS root_rp
                FROM mis_menus WHERE depth = 1
                UNION ALL
                SELECT c.idx, c.real_pid, c.up_real_pid, c.useflag, t.root_rp
                FROM mis_menus c JOIN tree t ON c.up_real_pid = t.real_pid
            )
            SELECT root_rp, SUM(CASE WHEN useflag='1' THEN 1 ELSE 0 END) AS cnt
            FROM tree GROUP BY root_rp
        ")->fetchAll(\PDO::FETCH_KEY_PAIR);
        $GLOBALS['_314_descCounts'] = $rows ?: [];

        // 자물쇠 판정용 — useflag='1' 인 자식이 1개라도 있는 부모 real_pid 집합 (직속 자식만)
        // 이 집합에 들어있고 본인도 useflag='1' 인 행은 list 에서 자물쇠 표시 + 삭제 거부
        $locked = $__pdo->query("
            SELECT DISTINCT up_real_pid AS rp
              FROM mis_menus
             WHERE useflag = '1'
               AND up_real_pid IS NOT NULL
               AND up_real_pid <> ''
        ")->fetchAll(\PDO::FETCH_COLUMN);
        $GLOBALS['_314_lockedRPs'] = array_flip($locked ?: []);

        // 314 의 mis_menu_fields 에 useflag alias 가 없어 $data['useflag'] 가 늘 undefined → list_json_load 에서
        // 직접 판단 못 함. 그래서 real_pid → useflag 맵을 미리 캐싱.
        $ufMap = $__pdo->query("SELECT real_pid, useflag FROM mis_menus")->fetchAll(\PDO::FETCH_KEY_PAIR);
        $GLOBALS['_314_useflagByRP'] = $ufMap ?: [];
    }
}

// 그리드 각 행에 자물쇠 표시 — useflag='1' + 자식(useflag='1') 존재 → menu_name 셀에 🔒 prefix
function list_json_load(&$data) {
    $rp = (string)($data['real_pid'] ?? '');
    if ($rp === '') return;
    $ufMap  = $GLOBALS['_314_useflagByRP'] ?? [];
    $locked = $GLOBALS['_314_lockedRPs']   ?? [];
    // 본인 useflag='1' + 자식(useflag='1') 존재할 때만 자물쇠
    if (($ufMap[$rp] ?? '') !== '1') return;
    if (!isset($locked[$rp])) return;
    $name = htmlspecialchars((string)($data['menu_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $data['__html']['menu_name'] = '<span title="하위 메뉴가 있어 삭제 불가" style="margin-right:4px">🔒</span>' . $name;
}

// 삭제 검증 — useflag='1' 행은 자식(useflag='1') 없을 때만 삭제 허용
//   - useflag='0' (이미 삭제처리됨) 은 진짜 DELETE — 자물쇠 없음 → 그대로 통과
//   - useflag='1' + 자식(useflag='1') 1개 이상 → 거부
function save_deleteBefore($idx, &$cancelDelete) {
    global $__pdo;
    if (!$__pdo || $idx <= 0) return;

    $st = $__pdo->prepare('SELECT real_pid, menu_name, useflag FROM mis_menus WHERE idx = ? LIMIT 1');
    $st->execute([(int)$idx]);
    $row = $st->fetch(\PDO::FETCH_ASSOC);
    if (!$row) return;

    if (($row['useflag'] ?? '') !== '1') return;              // useflag=0 → 통과 (실삭제)

    $rp = (string)($row['real_pid'] ?? '');
    if ($rp === '') return;

    $cs = $__pdo->prepare(
        "SELECT COUNT(*) FROM mis_menus WHERE up_real_pid = ? AND useflag = '1'"
    );
    $cs->execute([$rp]);
    $cnt = (int)$cs->fetchColumn();
    if ($cnt > 0) {
        $cancelDelete = true;
        $name = (string)($row['menu_name'] ?? $rp);
        $GLOBALS['_client_alert'] = "[{$name}] 메뉴는 하위 메뉴 {$cnt}개가 사용중(useflag=1)이라 삭제할 수 없습니다.\n하위부터 먼저 삭제하세요.";
    }
}
  function save_writeAfter($newIdx, &$afterScript) {
      global $__pdo;
      $siteId = $_ENV['SITE_ID'] ?? 'speedmis';
      $realPid = $siteId . str_pad($newIdx, 6, '0', STR_PAD_LEFT);
      $__pdo->prepare("UPDATE mis_menus SET real_pid = ?, useflag = '1' WHERE idx = ? AND (real_pid IS NULL OR real_pid = '')")
            ->execute([$realPid, $newIdx]);
  }

  // 통합 버튼 훅 — list 셀 + view 폼 양쪽에 동일 노출
  function row_buttons(&$row, array &$buttons): void {
      global $__pdo;

      $rp     = (string)($row['real_pid'] ?? '');
      $rowIdx = (int)($row['idx'] ?? 0);

      // real_pid 셀/필드 — 연결 / 추가 / 소스 (depth=1 + menu_type=01 만)
      if ($rp !== '') {
          $rpEsc = htmlspecialchars($rp, ENT_QUOTES, 'UTF-8');
          $buttons['real_pid'][] = '<span class="btn-open" data-opentab=\'{"realPid":"' . $rpEsc . '"}\'>연결</span>';
          $buttons['real_pid'][] = '<span class="btn-open" data-menu-add="' . $rowIdx . '">추가</span>';
          if (($row['menu_type'] ?? '') === '01') {
              $buttons['real_pid'][] = '<span class="btn-open" data-opentab=\'{"gubun":266,"idx":"' . $rpEsc . '"}\'>소스</span>';
          }
      }

      // depth=1 (autogubun 2자리) / depth=2 (autogubun 4자리) 행에만 권한전달 [적용(N)] 버튼
      $depth = (int)($row['depth'] ?? 0);
      if (($depth === 1 || $depth === 2) && $rp !== '') {
          // 하위 메뉴 카운트: list 모드면 list_json_init 이 미리 채운 _314_descCounts 사용,
          // view 모드면 직접 SQL 1회 실행 (단건)
          $counts = $GLOBALS['_314_descCounts'] ?? null;
          if (is_array($counts) && isset($counts[$rp])) {
              $cnt = (int)$counts[$rp];
          } elseif ($__pdo) {
              $st = $__pdo->prepare("
                  WITH RECURSIVE tree AS (
                      SELECT idx, real_pid, up_real_pid, useflag FROM mis_menus WHERE real_pid = :rp
                      UNION ALL
                      SELECT c.idx, c.real_pid, c.up_real_pid, c.useflag FROM mis_menus c JOIN tree t ON c.up_real_pid = t.real_pid
                  )
                  SELECT SUM(CASE WHEN useflag='1' THEN 1 ELSE 0 END) FROM tree
              ");
              $st->execute([':rp' => $rp]);
              $cnt = (int)($st->fetchColumn() ?: 0);
          } else {
              $cnt = 0;
          }

          $menuName = $row['menu_name'] ?? '';
          $newGidx  = (int)($row['new_gidx'] ?? 0);
          $authCode = (string)($row['auth_code'] ?? '');

          $gname = '(없음)';
          if ($newGidx > 0 && $__pdo) {
              $st = $__pdo->prepare('SELECT gname FROM mis_groups WHERE idx = ? LIMIT 1');
              $st->execute([$newGidx]);
              $gname = (string)($st->fetchColumn() ?: '(없음)');
          }
          $aname = $authCode !== '' ? $authCode : '(없음)';
          if ($authCode !== '' && $__pdo) {
              $st = $__pdo->prepare("
                  SELECT m.kname FROM mis_common_data m
                   JOIN mis_common_data p ON m.gcode = p.real_cid
                  WHERE p.kname = '메뉴권한선택' AND m.kcode = ? AND m.useflag='1' LIMIT 1
              ");
              $st->execute([$authCode]);
              $k = $st->fetchColumn();
              if ($k) $aname = "{$authCode} {$k}";
          }

          $confirmMsg = "[{$menuName}] 메뉴 하위의 {$cnt}개 메뉴의 권한도 {$gname} 이름, {$aname} 이름 으로 바꾸시겠습니까?";

          // mis_menu_fields 의 권한전달 컬럼 alias 는 'zgwonhanjeondal' (Korean romanization)
          $buttons['zgwonhanjeondal'][] =
                '<span class="btn-open"'
              . ' data-mis-action="applyAuth"'
              . ' data-mis-gubun="314"'
              . ' data-mis-idx="' . $rowIdx . '"'
              . ' data-mis-confirm="' . htmlspecialchars($confirmMsg, ENT_QUOTES, 'UTF-8') . '"'
              . '>적용(' . $cnt . ')</span>';
      }
  }

  function addLogic_treat(&$result) {
      if (($result['action'] ?? '') !== 'applyAuth') return;

      $pdo = $GLOBALS['__pdo'] ?? null;
      if (!$pdo) { $result['success'] = false; $result['_client_alert'] = 'DB 없음'; return; }

      $idx = (int)($result['idx'] ?? 0);
      if ($idx <= 0) { $result['success'] = false; $result['_client_alert'] = 'idx 누락'; return; }

      // 원본 행 조회
      $st = $pdo->prepare('SELECT real_pid, new_gidx, auth_code FROM mis_menus WHERE idx = ? LIMIT 1');
      $st->execute([$idx]);
      $row = $st->fetch(\PDO::FETCH_ASSOC);
      if (!$row) { $result['success'] = false; $result['_client_alert'] = '행 없음'; return; }

      $rp       = $row['real_pid'];
      $newGidx  = $row['new_gidx'];
      $authCode = $row['auth_code'];

      try {
          // 1) 재귀 CTE 로 자기 자신 포함 모든 하위 idx 수집
          $sel = $pdo->prepare("
              WITH RECURSIVE tree AS (
                  SELECT idx, real_pid FROM mis_menus WHERE real_pid = :rp
                  UNION ALL
                  SELECT c.idx, c.real_pid FROM mis_menus c JOIN tree t ON c.up_real_pid = t.real_pid
              )
              SELECT idx FROM tree
          ");
          $sel->execute([':rp' => $rp]);
          $idxs = $sel->fetchAll(\PDO::FETCH_COLUMN);
          if (!$idxs) {
              $result['success'] = false;
              $result['_client_alert'] = '대상 행이 없습니다.';
              return;
          }

          // 2) IN (...) 로 일괄 UPDATE
          $ph = implode(',', array_fill(0, count($idxs), '?'));
          $up = $pdo->prepare("UPDATE mis_menus SET new_gidx = ?, auth_code = ? WHERE idx IN ({$ph})");
          $up->execute(array_merge([$newGidx, $authCode], $idxs));
          $affected = $up->rowCount();

          // 3) 권한 재계산 프로시저 호출
          $siteId = $_ENV['SITE_ID'] ?? 'speedmis';
          $pdo->prepare("call mis_user_authority_proc(?)")->execute([$siteId]);

          $result['success']       = true;
          $result['affected']      = $affected;
          $result['reloadList']    = true;
          $result['_client_toast'] = "권한 전달 완료 ({$affected}건)";
      } catch (\Throwable $e) {
          $result['success']       = false;
          $result['_client_alert'] = '실행 실패: ' . $e->getMessage();
      }
  }

  function view_load(&$row) {
      $rp = $row['real_pid'] ?? '';
      if ($rp) {
          $GLOBALS['_client_formButtons'] = [
              ['label' => '연결', 'realPid' => $rp, 'openFull' => true]
          ];
      }
  }