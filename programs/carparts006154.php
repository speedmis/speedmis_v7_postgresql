<?php
/**
 * carparts006154.php — 네이버 주문 동기화 현황
 *
 * 화면 동작:
 *  - 기본 리스트 — mis_naver_orders 표시 (주문시각 내림차순)
 *  - 상품명 컬럼 클릭 → 부자톡 상품 (carparts006083 / gubun=6083) 새 탭
 *  - 헤더 [지금 동기화] 버튼 → naver_order_sync.php 즉시 실행 → 토스트
 *
 * 본 화면은 읽기전용(g07='Y'). 직접 수정/삭제 안 됨.
 * 6152(쿠팡) / 6153(이베이) 와 동일 패턴.
 */

declare(strict_types=1);

function pageLoad() {
	// 등록 버튼 숨김 + 즉시 동기화 커스텀 버튼 노출
	$GLOBALS['_client_css'] = ($GLOBALS['_client_css'] ?? '')
		. ' #mis-btn-write { display: none !important; }';
	$GLOBALS['_client_buttons'] = [
		['label' => '🔄 지금 동기화', 'action' => 'syncNow'],
	];
}

/**
 * 커스텀 버튼 [지금 동기화] 핸들러 — naver_order_sync.php 즉시 호출.
 * cron 10분 사이에 즉시 확인하고 싶을 때 사용.
 */
function list_json_init() {
	global $customAction;

	if ($customAction !== 'syncNow') return;

	// 운영 도메인 기준 호출. SSRF 위험 없음 (고정 endpoint).
	// APP_URL 이 '/v7' 접미사를 포함할 수 있으므로 scheme+host 만 추출.
	$appUrl = (string)($_ENV['APP_URL'] ?? 'https://xn--or3b27p5mi.com');
	$parsed = parse_url($appUrl);
	$base   = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? 'xn--or3b27p5mi.com');
	$url    = $base . '/_naver/naver_order_sync.php';

	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT        => 60,
		CURLOPT_SSL_VERIFYPEER => false,
	]);
	$body = (string)curl_exec($ch);
	$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	$res = json_decode($body, true) ?: [];
	if ($http === 200 && !empty($res['success'])) {
		$msg = sprintf(
			'네이버 동기화 완료 — 수집%d / 신규%d / 차감%d / 환원%d (%dms)',
			$res['fetched']        ?? 0,
			$res['inserted']       ?? 0,
			$res['stock_adjusted'] ?? 0,
			$res['refunded']       ?? 0,
			$res['elapsed_ms']     ?? 0,
		);
		if (!empty($res['errors'])) {
			$msg .= ' / 에러 ' . count($res['errors']) . '건';
		}
		$GLOBALS['_client_toast'] = $msg;
	} else {
		$GLOBALS['_client_alert'] = "네이버 동기화 호출 실패 (HTTP {$http})\n" . substr($body, 0, 500);
	}
}

/**
 * 그리드 셀: 상품명(zit_name) 컬럼에 부자톡 상품 모달 열기 링크 부여.
 */
function list_json_load(&$data) {
	$itId = trim((string)($data['it_id'] ?? ''));
	if ($itId !== '' && isset($data['zit_name']) && $data['zit_name'] !== null) {
		$detail = json_encode(
			['gubun' => 6083, 'idx' => $itId, 'label' => '영카트 부품'],
			JSON_UNESCAPED_UNICODE
		);
		$label = htmlspecialchars((string)$data['zit_name'], ENT_QUOTES, 'UTF-8');
		$data['__html']['zit_name'] =
			'<button class="btn-open" data-opentab=\''
			. htmlspecialchars($detail, ENT_QUOTES, 'UTF-8')
			. '\'>' . $label . '</button>';
	}
}
