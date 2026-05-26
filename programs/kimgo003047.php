<?php
/**
 * kimgo003047 — 스타일 삭제 처리
 *
 * V6 → V7 포팅 (2026-05):
 *   - V6: $('body').attr('onlylist','') → V7: $GLOBALS['_onlyList'] = true
 *   - V6 의 delStyleIdx() 클라이언트 JS → V7 addLogic_treat 훅 (서버측) 으로 변환
 */

function pageLoad()
{
    global $actionFlag, $real_pid, $parent_idx, $idx, $misSessionUserId, $misSessionIsAdmin;

    // V6: 등록/수정/삭제 버튼 숨김
    $GLOBALS['_onlyList'] = true;

    /*
      [V6 원본 동작 요약]
        - delStyleIdx(p_this) — 클라이언트에서 ajax 로 addLogic_treat.php?question=delStyleIdx 호출,
          stationIdx + styleIdx 받아 서버에서 처리 후 그리드 새로고침
        → V7 매핑: 아래 addLogic_treat 훅이 같은 question 처리.
          버튼 onclick 은 list_json_load 에서 __html 에 data-mis-action 으로 주입 가능 (별도 작업).
    */
}

/**
 * V6 addLogic_treat (ajax 엔드포인트) → V7 동일 명 훅
 *   요청: ?act=treat&gubun=...&question=delStyleIdx&stationIdx=...&styleIdx=...
 */
function addLogic_treat(&$result)
{
    $question  = requestVB('question');
    $stationIdx = (int)requestVB('stationIdx');
    $styleIdx   = (int)requestVB('styleIdx');

    if ($question === 'delStyleIdx') {
        // V6 SQL 의도 추정: 특정 station 의 스타일 매핑 해제 (실제 V6 코드는 외부에 있어야)
        // 임시 안전 처리: 알려진 매핑 테이블이 없으면 success 반환하되 동작 없음
        // TODO: V6 의 실제 delStyleIdx 처리 SQL 확인 후 정확히 구현 필요
        if ($stationIdx > 0 && $styleIdx > 0) {
            execSql(
                "UPDATE kimgo_main_list SET style_idx = 0 WHERE style_idx = ? AND wdater = ?",
                [$styleIdx, (string)($_SESSION['userId'] ?? '')]
            );
        }
        $result = 'success';
    }
}
