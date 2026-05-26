<?php
/**
 * kimgo003044 — 스타일 정의 (kimgo_style 리스트뷰)
 *
 * V6 → V7 포팅 (2026-05):
 *   - V6: $('body').attr('onlylist','') → V7: $GLOBALS['_onlyList'] = true
 *   - 나머지 V6 클라이언트 JS hook 들은 모두 빈 함수 (boilerplate)
 */

function pageLoad()
{
    global $actionFlag, $real_pid, $parent_idx, $idx, $misSessionUserId, $misSessionIsAdmin;

    // V6: $('body').attr('onlylist','')   →   V7: 등록/수정/삭제 버튼 숨김
    $GLOBALS['_onlyList'] = true;

    // V6 의 .themechooser 숨김 등 V7 마크업과 불일치 — 효과 없지만 원본 의도 보존
    $GLOBALS['_client_css'] = '
.themechooser { display: none; }
div#grid div.k-grid-content.k-auto-scrollable { display: contents; }
div#exampleWrap { display: contents; }
';

    /*
      [V6 원본 동작 요약]
        - thisPage_options_pdf, columns_templete, rowFunction_*, thisLogic_toolbar,
          helpboxLogic_*, viewLogic_*, addLogic_zipcode — 모두 빈 함수 또는 주석
        → 실질 포팅 불필요. 본 hook 만 의미.
    */
}
