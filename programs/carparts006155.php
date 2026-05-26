<?php
/**
 * carparts006155.php — 네이버 카테고리 매핑
 *
 * 부자톡 카테고리(g5_shop_category.ca_id) → 네이버 leaf 카테고리 매핑 관리.
 * naver_item_sync.php 가 상품 전송 시 이 매핑을 ca_id prefix 로 거슬러 올라가며 조회.
 *
 * 화면 동작:
 *  - 일반 CRUD — bujatok_ca_id / naver_category_id 입력
 *  - 부자톡/네이버 카테고리명은 가상필드라 입력값 저장 즉시 경로가 표시됨
 *    (네이버 경로가 빈칸이면 = ID 가 leaf 가 아니거나 존재하지 않음 → 다시 확인)
 *  - 헤더 [카테고리 동기화] 버튼 → naver_category_sync.php 실행 (네이버 카테고리 트리 갱신)
 *  - 네이버 leaf ID 는 "네이버 카테고리 목록"(carparts006156) 메뉴에서 검색
 */

declare(strict_types=1);

function pageLoad() {
	$GLOBALS['_client_buttons'] = [
		['label' => '🔄 카테고리 동기화', 'action' => 'syncCategories'],
	];
}

/**
 * 커스텀 버튼 [카테고리 동기화] — naver_category_sync.php 호출.
 * 네이버 카테고리 트리를 mis_naver_categories 로 다시 적재.
 */
function list_json_init() {
	global $customAction;

	if ($customAction !== 'syncCategories') return;

	$appUrl = (string)($_ENV['APP_URL'] ?? 'https://xn--or3b27p5mi.com');
	$parsed = parse_url($appUrl);
	$base   = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? 'xn--or3b27p5mi.com');
	$url    = $base . '/_naver/naver_category_sync.php';

	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT        => 120,
		CURLOPT_SSL_VERIFYPEER => false,
	]);
	$body = (string)curl_exec($ch);
	$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	if ($http === 200 && str_contains($body, '동기화 완료')) {
		// "✅ 네이버 카테고리 N개 동기화 완료 (leaf M개)" 에서 숫자만 추출
		if (preg_match('/카테고리\s*([\d,]+)개 동기화 완료 \(leaf\s*([\d,]+)/u', $body, $m)) {
			$GLOBALS['_client_toast'] = "네이버 카테고리 {$m[1]}개 동기화 완료 (leaf {$m[2]}개)";
		} else {
			$GLOBALS['_client_toast'] = '네이버 카테고리 동기화 완료';
		}
	} else {
		$GLOBALS['_client_alert'] = "카테고리 동기화 실패 (HTTP {$http})\n" . strip_tags(substr($body, 0, 500));
	}
}
