<?php
/**
 * kimgo003043 — 스타일 정의 (kimgo_style)
 *
 * V6 → V7 포팅 (2026-05):
 *   - 활성 코드 거의 없음 (CSS + 비활성 보일러플레이트). pageLoad 만 의미있게 포팅.
 *   - V6 jQuery/Kendo 셀렉터 의존 부분 (li[tabnumber] 등) 은 V7 에 미존재 → CSS 그대로 두되 효과 없음
 */

function pageLoad()
{
    global $actionFlag, $real_pid, $parent_idx, $idx, $misSessionUserId, $misSessionIsAdmin;

    // V6 의 li[tabnumber] / div[tabnumber] 는 V7 탭 시스템과 매칭 안 됨 → 효과 없지만 원본 보존
    $GLOBALS['_client_css'] = '
/* V6 잔재 — V7 의 탭/그리드 마크업과 불일치 */
li[tabnumber], div[tabnumber] { display: none !important; }
div[tabnumber="1"] { display: block !important; }
';

    /*
      [V6 원본 동작 요약]
        - thisPage_options_pdf: PDF 인쇄 시 페이지 옵션 (A4, 0.567배율, landscape false)
        - columns_templete: AutoGubun 셀에 [Go] [Source] 버튼 추가 (실제로 주석처리됨)
        - helpboxLogic_afterSelect: helpbox 선택 후처리 (빈 함수)
        - viewLogic_afterLoad: 빈 함수

      → V7 권장:
        - PDF 옵션 → DataGrid 의 print() 메서드 + CSS @page
        - 셀 커스텀 → list_json_load(&$data) 의 __html 주입
        - 다른 hook 들 → 모두 빈 함수라 포팅 불필요
    */
}
