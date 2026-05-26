<?php
/**
 * carparts006118 — 가격비교 입력하기
 *
 * 동작 (v7 hooks):
 *  - list_json_load: 리스트 각 행의 si.it_img1 / si.it_explan 을 절대경로 이미지 / 절대경로 HTML 로 렌더링
 *  - view_load:
 *      1) view/modify 진입 시 g5_shop_item_compare 에 14개 site_id 기준행이 없으면 자동 보강 (idempotent)
 *      2) 대표이미지(siQnit_img1) → view 모드에서만 IMG 태그
 *      3) 상품설명(siQmit_explan) 상대 src="/data/" → https 절대 URL 로 치환
 *
 * v6 잔재 제거:
 *  - misMenuList_change() — v6 hook (6125 메뉴에 대한 조건, 본 프로그램과 무관)
 *  - pageLoad() 안의 JS heredoc — v7 SPA 에서 의미 없음 (React 가 UI 담당)
 *  - list_json_init() 의 raw SQL 자동 INSERT — view_load 로 통합 (DML 트리거 1회만 필요)
 */

// 가격비교 14개 기본 site_id (정렬 prefix 포함, 원본 그대로)
const _P6118_SITES = [
    '00.부자톡', '01.이베이', '02.번개장터', '03.ok파츠', '04.GK파츠',
    '05.파츠핏', '06.G파츠', '07.쿠팡', '08.네이버스토어', '09.중고나라',
    '10.구글',   '11.기타원', '12.기타원', '13.기타$',
];

// ── 리스트 행 ─────────────────────────────────────────────────────────────
// 원본 값은 보존(편집 가능), schema_type='html' 컬럼은 __html 로 렌더링만 교체.
function list_json_load(&$data) {
    // 대표이미지
    $img = trim((string)($data['siQnit_img1'] ?? ''));
    if ($img !== '') {
        $src = URL_BASE_PATH . '/tools/thumbnail.php?/data/item/' . rawurlencode($img);
        $data['__html']['siQnit_img1'] =
            '<img src="' . htmlspecialchars($src, ENT_QUOTES) . '" alt="" loading="lazy" '
          . 'style="max-width:200px;max-height:120px;object-fit:contain;display:block" />';
    }

    // 상품설명 — 상대 /data/ → 부자톡 절대 URL
    $explan = (string)($data['siQmit_explan'] ?? '');
    if ($explan !== '' && stripos($explan, 'src="/data/') !== false) {
        $data['__html']['siQmit_explan'] = str_ireplace(
            'src="/data/',
            'src="https://xn--or3b27p5mi.com/data/',
            $explan
        );
    }
}

// ── view SELECT 직전 — 14사이트 기본행 자동 보강 ────────────────────────────
// view_load 가 SELECT '후' 호출되어 INSERT 해도 같은 응답에 반영 안 됨 (새로고침 필요).
// view_query 는 SELECT '전' 호출되므로 여기서 INSERT 하면 그 SELECT 가 새 행을 fetch.
// URL idx (= g5_shop_item.it_id) 검증 후 멱등 INSERT.
function view_query(&$viewSql) {
    global $__pdo, $idx;
    if (!($__pdo instanceof \PDO) || (int)$idx <= 0) return;

    try {
        $vchk = $__pdo->prepare("SELECT it_id FROM g5_shop_item WHERE it_id = ? LIMIT 1");
        $vchk->execute([(int)$idx]);
        $itId = (string)($vchk->fetchColumn() ?: '');
        if ($itId === '') return;

        $cnt = $__pdo->prepare("SELECT COUNT(*) FROM g5_shop_item_compare WHERE it_id = ?");
        $cnt->execute([$itId]);
        if ((int)$cnt->fetchColumn() === 0) {
            $ins = $__pdo->prepare(
                "INSERT INTO g5_shop_item_compare (it_id, site_id, wdater, useflag) VALUES (?, ?, 'gadmin', '1')"
            );
            foreach (_P6118_SITES as $sid) {
                try { $ins->execute([$itId, $sid]); } catch (\Throwable) {}
            }
        }
    } catch (\Throwable) { /* 자동 보강 실패는 페이지 로딩 중단 사유 아님 */ }
}

// ── 뷰/수정 진입 (이미지·HTML 표시 변환) ──────────────────────────────────
function view_load(&$row) {
    global $actionFlag;
    if (!is_array($row)) return;

    // 1) 뷰 모드 한정 — 대표이미지를 IMG 태그로 (수정 시엔 파일명 편집 가능하게 원본 유지)
    if ($actionFlag === 'view') {
        $img = trim((string)($row['siQnit_img1'] ?? ''));
        if ($img !== '') {
            $src = URL_BASE_PATH . '/tools/thumbnail.php?/data/item/' . rawurlencode($img);
            $row['siQnit_img1'] =
                '<img src="' . htmlspecialchars($src, ENT_QUOTES) . '" alt="" loading="lazy" '
              . 'style="max-width:400px;max-height:300px;object-fit:contain;display:block" />';
        }
    }

    // 3) 상품설명 — 상대 src="/data/" → 부자톡 절대 URL (뷰/수정 공통)
    $explan = (string)($row['siQmit_explan'] ?? '');
    if ($explan !== '' && stripos($explan, 'src="/data/') !== false) {
        $row['siQmit_explan'] = str_ireplace(
            'src="/data/',
            'src="https://xn--or3b27p5mi.com/data/',
            $explan
        );
    }
}
