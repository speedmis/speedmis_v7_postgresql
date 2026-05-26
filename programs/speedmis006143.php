<?php
/**
 * 6143 — mis_menus 변경이력 조회.
 * trigger 가 자동 기록한 mis_menus_history 의 list-only 화면.
 * change_kind 색상 + diff_json 정리 표시.
 */

function pageLoad()
{
    $GLOBALS['_onlyList'] = true;  // 등록/삭제 버튼 숨김 (이력은 자동 기록만)
    // 헤더 +등록 버튼 CSS 로 숨김
    $GLOBALS['_client_css'] = '#mis-btn-write { display:none !important; }';
}

function list_json_load(&$data)
{
    // change_kind 색상 라벨
    $kind = (string)($data['change_kind'] ?? '');
    $color = ['I' => '#10b981', 'U' => '#3B82F6', 'D' => '#ef4444'][$kind] ?? '#888';
    $label = ['I' => '신규', 'U' => '수정', 'D' => '삭제'][$kind] ?? $kind;
    $data['__html']['change_kind'] = sprintf(
        '<span style="display:inline-block;padding:2px 8px;background:%s;color:#fff;border-radius:4px;font-size:0.85em;font-weight:600">%s</span>',
        $color, htmlspecialchars($label, ENT_QUOTES)
    );

    // diff_json 정리 — {"col":["before","after"]} 형식 → 가독성
    $raw = (string)($data['diff_json'] ?? '');
    if ($raw !== '' && $raw !== 'null') {
        $j = json_decode($raw, true);
        if (is_array($j) && $j) {
            $parts = [];
            foreach ($j as $col => $pair) {
                if (!is_array($pair) || count($pair) !== 2) continue;
                [$old, $new] = $pair;
                $oldS = $old === null ? '<i style="color:#999">NULL</i>' : htmlspecialchars((string)$old, ENT_QUOTES);
                $newS = $new === null ? '<i style="color:#999">NULL</i>' : htmlspecialchars((string)$new, ENT_QUOTES);
                $parts[] = sprintf(
                    '<div style="margin:1px 0"><b style="color:#3B4663">%s</b>: '
                    . '<span style="color:#ef4444;text-decoration:line-through">%s</span> '
                    . '→ <span style="color:#10b981">%s</span></div>',
                    htmlspecialchars($col, ENT_QUOTES),
                    mb_strimwidth($oldS, 0, 80, '…'),
                    mb_strimwidth($newS, 0, 80, '…')
                );
            }
            $data['__html']['diff_json'] = '<div style="font-size:0.85em;line-height:1.45">' . implode('', $parts) . '</div>';
        }
    }

    // menu_idx 셀에 [연결] 버튼 — 해당 메뉴 새 탭 열기
    $menuIdx = (int)($data['menu_idx'] ?? 0);
    if ($menuIdx > 0) {
        $opentab = json_encode(['gubun' => $menuIdx, 'label' => (string)($data['menu_name'] ?? ''), 'openFull' => true], JSON_UNESCAPED_UNICODE);
        $data['__html']['menu_idx'] = htmlspecialchars((string)$menuIdx, ENT_QUOTES)
            . ' <span class="btn-open" data-opentab=\'' . htmlspecialchars($opentab, ENT_QUOTES) . '\' '
            . 'style="display:inline-block;padding:1px 7px;background:#4F6EF7;color:#fff;border-radius:4px;'
            . 'font-size:0.8em;cursor:pointer;margin-left:4px">연결</span>';
    }
}

if (!function_exists('mb_strimwidth')) {
    // mbstring 미설치 환경 폴백
    function mb_strimwidth(string $str, int $start, int $width, string $trimmarker = '') {
        return strlen($str) > $width ? substr($str, $start, $width) . $trimmarker : $str;
    }
}