<?php
/**
 * 6119 — 가격비교상세 (가격비교 입력하기 6118 / 가격비교 검수하기 6125 의 child)
 *
 * v6 의 misMenuList_change / list_json_init / addLogic_treat / columns_templete 를 v7 훅으로 포팅.
 * parent_gubun 으로 6118(입력) / 6125(검수) 모드 분기.
 */

// =================================================================================
//  pageLoad — 목록전용 모드 + parent_gubun 별 toolbar 버튼
// =================================================================================
function pageLoad() {
    global $actionFlag, $real_pid, $parent_idx, $idx, $parent_gubun;

    // 목록에서 view 진입 차단 — 인라인 편집 + 채택적용 만 사용
    $GLOBALS['_onlyList'] = true;

    if ($actionFlag === 'list') {
        if ((int)$parent_gubun === 6125) {
            // 검수 모드 (6125 부모)
            $GLOBALS['_client_buttons'] = [
                ['label' => '검수완료', 'action' => '검수완료'],
                ['label' => '검수기각', 'action' => '기각'],
            ];
        } else {
            // 입력 모드 (6118 또는 그 외)
            $GLOBALS['_client_buttons'] = [
                ['label' => '입력완료 및 상신', 'action' => '상신'],
                ['label' => '비교불가',         'action' => '비교불가'],
            ];
        }
    }
}

// =================================================================================
//  before_query — parent_gubun=6118 일 때 sales_price 필드 그리드 숨김
// =================================================================================
function before_query(&$menu, &$fields, &$params) {
    $parentGubun = (int)($params['parent_gubun'] ?? 0);
    if ($parentGubun === 6118) {
        // sales_price 컬럼 그리드 숨김 (col_width=0): 입력 모드에서는 사이트별가만 보이고
        // 채택가 컬럼은 검수 모드에서만 노출.
        foreach ($fields as &$f) {
            if (($f['alias_name'] ?? '') === 'sales_price') {
                $f['col_width'] = 0;
                break;
            }
        }
        unset($f);
    }
}

// =================================================================================
//  list_json_init — customAction (상신/검수완료/기각/비교불가) 처리
// =================================================================================
function list_json_init() {
    global $customAction, $parent_idx, $__pdo;
    if (!$__pdo || $customAction === '') return;

    $parentItId = (int)$parent_idx;
    if ($parentItId <= 0) {
        $GLOBALS['_client_alert'] = '대상 it_id 가 식별되지 않았습니다.';
        return;
    }

    if ($customAction === '상신') {
        // 1) 사이트별가 입력 행 1건 이상
        $st = $__pdo->prepare("SELECT COUNT(*) FROM g5_shop_item_compare WHERE it_id=? AND sales_price_site>0");
        $st->execute([$parentItId]);
        if ((int)$st->fetchColumn() < 1) {
            $GLOBALS['_client_alert'] = '최소 2건 이상의 가격이 입력되어야 합니다.';
            return;
        }
        // 2) 점검완료 체크 3건 이상
        $st = $__pdo->prepare("SELECT COUNT(*) FROM g5_shop_item_compare WHERE it_id=? AND isCheckedYn='Y'");
        $st->execute([$parentItId]);
        if ((int)$st->fetchColumn() < 3) {
            $GLOBALS['_client_alert'] = '3개 사이트의 점검완료가 체크되어야 합니다.';
            return;
        }
        // 3) 달러 사이트(이베이/기타$) 가격 1건 이상
        $st = $__pdo->prepare("SELECT COUNT(*) FROM g5_shop_item_compare WHERE it_id=? AND sales_price_site>0 AND site_id IN ('01.이베이','13.기타\$')");
        $st->execute([$parentItId]);
        if ((int)$st->fetchColumn() === 0) {
            $GLOBALS['_client_alert'] = '최소 1건 이상의 달러 가격이 입력되어야 합니다.';
            return;
        }
        // 통과 → steps='상신'
        $upd = $__pdo->prepare("UPDATE g5_shop_item_compare SET steps='상신' WHERE it_id=?");
        $upd->execute([$parentItId]);
        $GLOBALS['_client_alert']    = '상신되었습니다.';
        $GLOBALS['_client_redirect'] = ['gubun' => 6123];
        return;
    }

    if ($customAction === '검수완료') {
        $st = $__pdo->prepare("SELECT COUNT(*) FROM g5_shop_item_compare WHERE it_id=? AND select_site_id<>''");
        $st->execute([$parentItId]);
        if ((int)$st->fetchColumn() === 0) {
            $GLOBALS['_client_alert'] = '적용할 원화 가격에 대한 채택적용을 클릭하세요.';
            return;
        }
        // (v6 의 ebay 채택 체크는 111==222 비활성화 조건이라 포팅 생략)
        try {
            $__pdo->prepare("UPDATE g5_shop_item SET it_use=1, it_update_time=NOW() WHERE it_id=?")
                  ->execute([$parentItId]);
            $__pdo->prepare("UPDATE g5_shop_item_compare SET steps='검수완료' WHERE it_id=?")
                  ->execute([$parentItId]);
        } catch (\Throwable $e) {
            $GLOBALS['_client_alert'] = '검수완료 처리 실패: ' . $e->getMessage();
            return;
        }
        $GLOBALS['_client_alert']    = '검수완료되었습니다.';
        $GLOBALS['_listEditReload']  = true;
        return;
    }

    if ($customAction === '기각') {
        $upd = $__pdo->prepare("UPDATE g5_shop_item_compare SET steps='기각' WHERE it_id=?");
        $upd->execute([$parentItId]);
        $GLOBALS['_client_alert']    = '기각되었습니다.';
        $GLOBALS['_listEditReload']  = true;
        return;
    }

    if ($customAction === '비교불가') {
        $st = $__pdo->prepare("SELECT COUNT(*) FROM g5_shop_item_compare WHERE it_id=? AND isCheckedYn='Y'");
        $st->execute([$parentItId]);
        if ((int)$st->fetchColumn() < 9) {
            $GLOBALS['_client_alert'] = '9개 사이트의 점검완료가 체크되어야 합니다.';
            return;
        }
        $upd = $__pdo->prepare("UPDATE g5_shop_item_compare SET steps='비교불가' WHERE it_id=?");
        $upd->execute([$parentItId]);
        $GLOBALS['_client_alert']    = '비교불가 처리되었습니다.';
        $GLOBALS['_listEditReload']  = true;
        return;
    }
}

// =================================================================================
//  list_json_load — 셀별 커스텀 HTML (홈 / url / 적용 / 환율표시)
// =================================================================================
function list_json_load(&$data) {
    global $parent_gubun;
    $rowIdx  = (int)($data['idx'] ?? 0);
    $siteId  = (string)($data['site_id'] ?? '');
    if ($rowIdx <= 0) return;

    // site_id 셀 — 사이트 홈페이지 열기 버튼 (10번 이하 사이트만)
    if ($siteId !== '') {
        $homeUrl = _carparts006119_site_home_url($siteId);
        if ($homeUrl !== '') {
            $btn = '<a class="btn-open" href="' . htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8')
                 . '" target="_blank">홈</a> ';
            $data['__html']['site_id'] = $btn . htmlspecialchars($siteId, ENT_QUOTES, 'UTF-8');
        }
    }

    // 달러 환산 표시 — zhwapye='$' 인 행의 sales_price/sales_price_site 셀에 환산원화 부기
    if (((string)($data['zhwapye'] ?? '')) === '$') {
        $rate = (float)($data['qq_whanRate'] ?? 0);
        foreach (['sales_price_site', 'sales_price'] as $alias) {
            $val = (float)($data[$alias] ?? 0);
            if ($val > 0 && $rate > 0) {
                $krw = number_format(round($val * $rate));
                $data['__html'][$alias] = htmlspecialchars((string)$data[$alias], ENT_QUOTES, 'UTF-8')
                    . '<br><span style="color:blue;font-size:9px;">' . $krw . '원</span>';
            }
        }
    }

    // select_site_id 셀 — url 버튼 + (검수 모드일 때만) 적용 버튼
    $rowUrl = (string)($data['url'] ?? '');
    $urlBtn = $rowUrl !== ''
        ? '<a class="btn-open" href="' . htmlspecialchars($rowUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank">url</a>'
        : '';
    $applyBtn = '';
    if ((int)$parent_gubun !== 6118) {
        // 채택적용 — addLogic_treat 의 select_site action 호출
        $applyBtn = '<button class="btn-open" data-mis-action="select_site" data-mis-gubun="6119" data-mis-idx="' . $rowIdx . '">적용</button>';
    }
    $combo = trim($urlBtn . ' ' . $applyBtn);
    if ($combo !== '') {
        $data['__html']['select_site_id'] = $combo;
    }

    // 기타 사이트 — isCheckedYn 체크박스 숨김 (셀 raw HTML 통째로 비움)
    if (mb_strpos($siteId, '기타') !== false) {
        $data['__html']['isCheckedYn'] = '';
    }
}

// 사이트 홈 URL 매핑 (v6 open_site_home 로직과 동일)
function _carparts006119_site_home_url(string $siteId): string {
    $prefix = substr($siteId, 0, 2);
    if (!ctype_digit($prefix) || (int)$prefix > 10) return '';
    static $map = [
        '00.부자톡'      => 'https://xn--or3b27p5mi.com/',
        '01.이베이'      => 'https://www.ebay.com',
        '02.번개장터'    => 'https://www.bunjang.co.kr',
        '03.ok파츠'      => 'https://www.okparts.co.kr',
        '04.GK파츠'      => 'https://www.gkparts.co.kr',
        '05.파츠핏'      => 'https://www.partsfit.co.kr',
        '06.G파츠'       => 'https://www.gparts.co.kr',
        '07.쿠팡'        => 'https://www.coupang.com',
        '08.네이버스토어' => 'https://smartstore.naver.com',
        '09.중고나라'    => 'https://www.joongna.com',
        '10.구글'        => 'https://www.google.com',
    ];
    return $map[$siteId] ?? '';
}

// =================================================================================
//  addLogic_treat — '적용' 버튼: select_site 액션으로 채택적용
//  data-mis-action="select_site" data-mis-idx={g5_shop_item_compare.idx}
// =================================================================================
function addLogic_treat(&$result) {
    global $misSessionUserId, $__pdo;
    $action = (string)($result['action'] ?? '');
    if ($action !== 'select_site') return;

    $idx = (int)($result['idx'] ?? 0);
    if ($idx <= 0) {
        $result['success'] = false;
        $result['_client_toast'] = '잘못된 idx';
        return;
    }

    try {
        // 0) 기준 row (sales_price >= 50, url 비어있지 않음)
        $sql = "SELECT it_id, site_id, url, sales_price, sales_price_site
               FROM g5_shop_item_compare
              WHERE idx = ? AND sales_price >= 50 AND url IS NOT NULL AND url <> ''
              LIMIT 1";
        $st = $__pdo->prepare(
            $sql
        );
        $st->execute([$idx]);
        $base = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$base) {
            $result['success'] = false;
            $result['_client_toast'] = '적용할 가격 50원 이상 + url 입력된 행만 채택 가능';
            return;
        }
        $itId            = (int)$base['it_id'];
        $siteId          = (string)$base['site_id'];
        $url             = (string)$base['url'];
        $salesPrice      = (float)$base['sales_price'];
        $salesPriceSite  = (float)$base['sales_price_site'];
        $isDollar        = in_array($siteId, ['01.이베이', '13.기타$'], true);

        // 1) g5_shop_item 직전 가격 (이력용)
        $st2 = $__pdo->prepare("SELECT it_price, it_price_ebay FROM g5_shop_item WHERE it_id=? LIMIT 1");
        $st2->execute([$itId]);
        $prev = $st2->fetch(\PDO::FETCH_ASSOC) ?: ['it_price' => 0, 'it_price_ebay' => 0];
        $prevPrice = $isDollar ? (float)$prev['it_price_ebay'] : (float)$prev['it_price'];

        // 2) g5_shop_item_compare 채택 정보 갱신 (원화/달러 컬럼 분기)
        if ($isDollar) {
            $__pdo->prepare(
                "UPDATE g5_shop_item_compare
                    SET select_site_id_ebay = ?, select_url_ebay = ?, select_sales_price_ebay = ?
                  WHERE it_id = ?"
            )->execute([$siteId, $url, $salesPrice, $itId]);
        } else {
            $__pdo->prepare(
                "UPDATE g5_shop_item_compare
                    SET select_site_id = ?, select_url = ?, select_sales_price = ?
                  WHERE it_id = ?"
            )->execute([$siteId, $url, $salesPrice, $itId]);
        }

        // 3) 이력 INSERT
        $__pdo->prepare(
            "INSERT INTO g5_shop_item_compare_log
                (it_id, select_site_id, select_url, select_sales_price_pre, select_sales_price, wdater)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([$itId, $siteId, $url, $prevPrice, $salesPrice, (string)$misSessionUserId]);

        // 4) g5_shop_item 가격 반영
        if ($isDollar) {
            $__pdo->prepare("UPDATE g5_shop_item SET it_price_ebay = ? WHERE it_id = ?")
                  ->execute([$salesPrice, $itId]);
            if ($salesPriceSite > 0) {
                $__pdo->prepare("UPDATE g5_shop_item SET it_cust_price_ebay = ? WHERE it_id = ?")
                      ->execute([$salesPriceSite, $itId]);
            }
        } else {
            $__pdo->prepare("UPDATE g5_shop_item SET it_price = ? WHERE it_id = ?")
                  ->execute([$salesPrice, $itId]);
            if ($salesPriceSite > 0) {
                $__pdo->prepare("UPDATE g5_shop_item SET it_cust_price = ? WHERE it_id = ?")
                      ->execute([$salesPriceSite, $itId]);
            }
        }

        $result['success']       = true;
        $result['_client_toast'] = '채택 적용되었습니다.';
        $result['reloadList']    = true;
    } catch (\Throwable $e) {
        $result['success']       = false;
        $result['_client_toast'] = '실패: ' . $e->getMessage();
    }
}