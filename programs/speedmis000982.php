<?php
/**
 * 982 — 나의 백업현황
 *
 * '열기' 컬럼: jsonname 텍스트 대신 [열기] 버튼만 표시.
 * 클릭 시 v7 내부 탭으로 백업 데이터를 읽기전용 표시.
 *
 * 탭 라벨: "(백업) {원본 메뉴명}"
 * URL: ?gubun=<menu_idx>&_backup=<jsonname>&isMenuIn=Y
 *   _backup 파라미터를 DataHandler::list 가 감지 → loadBackupAsList 로 분기 → JSON 파일에서 데이터 로드.
 *   응답 _access.write/admin=false / _onlyList=true 로 등록·수정·삭제·인라인편집 모두 차단.
 */

function pageLoad()
{
    $GLOBALS['_onlyList'] = true; // 등록/삭제 버튼 숨김 (백업은 다른 프로그램에서 자동 생성)
}

function list_json_load(&$data)
{
    global $__pdo;

    $jsonname = trim((string)($data['jsonname'] ?? ''));
    if ($jsonname === '') return;

    $realPid = trim((string)($data['real_pid'] ?? ''));
    if ($realPid === '') return;

    // real_pid → (idx, menu_name) 룩업 (런타임 캐시)
    static $cache = [];
    $resolveMenu = function (string $rp) use ($__pdo, &$cache): array {
        if ($rp === '' || isset($cache[$rp])) return $cache[$rp] ?? ['idx' => 0, 'name' => ''];
        try {
            $stmt = $__pdo->prepare('SELECT idx, menu_name FROM mis_menus WHERE real_pid = ? LIMIT 1');
            $stmt->execute([$rp]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['idx' => 0, 'menu_name' => ''];
            $cache[$rp] = ['idx' => (int)$row['idx'], 'name' => (string)$row['menu_name']];
        } catch (\Throwable) {
            $cache[$rp] = ['idx' => 0, 'name' => ''];
        }
        return $cache[$rp];
    };

    $m = $resolveMenu($realPid);
    if ($m['idx'] <= 0) return; // 원본 메뉴가 사라진 경우 — 버튼 숨김

    // 탭 라벨: "(백업) {메뉴명}"
    $label = '(백업) ' . ($m['name'] !== '' ? $m['name'] : $realPid);

    // data-opentab 페이로드 — Layout.jsx 의 mis:openTab 핸들러가 받아 처리.
    //   gubun + addUrl 로 _backup 파라미터를 URL 에 병합 → DataGrid 가 list API 에 자동 전달
    //   → DataHandler::list 가 _backup 감지 시 loadBackupAsList 로 분기 (읽기전용 데이터 반환)
    $opentab = [
        'gubun'    => $m['idx'],
        'label'    => $label,
        'addUrl'   => '&_backup=' . rawurlencode($jsonname),
        'openFull' => true,
    ];

    $data['__html']['jsonname'] = sprintf(
        '<span class="btn-open-backup" data-opentab=\'%s\' '
      . 'style="display:inline-block;padding:3px 14px;background:#4F6EF7;color:#fff;'
      . 'border-radius:4px;font-size:0.9em;font-weight:600;cursor:pointer;user-select:none">'
      . '열기</span>',
        htmlspecialchars(json_encode($opentab, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8')
    );
}
