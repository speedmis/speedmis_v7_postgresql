<?php
/**
 * carparts006163 — 앗세이 주문내역 (parts_cate_assy_order_list)
 * 부모: carparts006161 앗세이 관리 (child FK = midx).
 * carparts006162 의 '주문실행' 으로 생성된 주문 내역을 표시하는 리스트 전용 화면.
 *
 * 리스트 전용: +등록/간편추가 없음, 행 클릭 시 조회/수정폼 진입 없음.
 *   (이전엔 무관한 로직이 들어있었으나 이 메뉴와 맞지 않아 제거함)
 */

/** 리스트 전용 — +등록/간편추가 숨김 + 조회/수정폼 진입 차단 */
function pageLoad() {
    $GLOBALS['_onlyList'] = true;
}

/**
 * 사진(dQnpicture) 셀 — 파일명 텍스트 대신 실제 이미지(썸네일) 노출.
 *  - 파일 경로: /uploadFiles/parts_cate_assy_detail/picture/{assy_detail_idx}/{파일명}
 *  - 그리드엔 thumbnail.php(w=120) 썸네일, 클릭 시 원본을 새 탭으로.
 */
function list_json_load(&$data) {
    $file = trim((string)($data['dQnpicture'] ?? ''));
    if ($file === '') return;                          // 사진 없으면 그대로(빈값)
    $detIdx = (int)($data['assy_detail_idx'] ?? 0);
    if ($detIdx <= 0) return;

    $base    = defined('URL_BASE_PATH') ? URL_BASE_PATH : '';   // 운영='/v7'
    $path    = '/uploadFiles/parts_cate_assy_detail/picture/' . $detIdx . '/' . $file;
    $encPath = str_replace('%2F', '/', rawurlencode($path));    // 슬래시는 유지, 나머지만 인코딩
    $thumb   = $base . '/tools/thumbnail.php?w=120&'  . $encPath;   // 그리드 표시용 소형
    $big     = $base . '/tools/thumbnail.php?w=1200&' . $encPath;   // 라이트박스용 대형

    $alt = htmlspecialchars($file, ENT_QUOTES, 'UTF-8');
    // 클릭 시 새 탭 대신 현재 페이지 라이트박스(data-mis-img → App.jsx → mis:imageView)
    $data['__html']['dQnpicture'] =
        '<img src="' . htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8') . '"'
      . ' data-mis-img="' . htmlspecialchars($big, ENT_QUOTES, 'UTF-8') . '"'
      . ' data-mis-img-name="' . $alt . '"'
      . ' alt="' . $alt . '" loading="lazy" title="클릭하면 크게 보기"'
      . ' style="height:40px;width:auto;max-width:140px;object-fit:cover;border-radius:4px;display:block;cursor:zoom-in;" />';
}
