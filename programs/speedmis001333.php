<?php


function pageLoad() {
    global $actionFlag, $gubun, $misSessionUserId, $misSessionIsAdmin;
    // 체크박스 컬럼 숨김 — DataGrid 가 isPopup/simple_list 모드에서 아예 안 그리도록 처리 (CSS collapse 불필요)
    $GLOBALS['_client_css'] = '';
/*
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  사용 가능한 전역변수 (global 선언 후 사용)                           │
 * ├──────────────────────────┬───────────────────────────────────────────┤
 * │ $actionFlag              │ 현재 액션 (list/view/modify/write/delete) │
 * │ $gubun                   │ 메뉴 idx (정수)                           │
 * │ $idx                     │ 레코드 idx (정수)                         │
 * │ $real_pid                │ 프로그램 real_pid (speedmis000036 형태)   │
 * │ $menu_name               │ 프로그램명                                │
 * │ $parent_idx              │ 마스터-디테일 상위 idx                    │
 * │ $misSessionUserId        │ 로그인 사용자 ID                         │
 * │ $misSessionIsAdmin       │ 관리자 여부 ('Y' 또는 '')                │
 * │ $misSessionPositionCode  │ 직급 코드                                │
 * │ $isFirstLoad             │ 프로그램 최초 로딩 여부 (bool)            │
 * │ $isListEdit              │ 목록편집(인라인) 저장 여부 (bool)         │
 * │ $listEditField           │ 목록편집 시 변경된 필드명 배열            │
 * │ $customAction            │ 사용자 정의 버튼 action 값               │
 * │ $allFilter               │ 필터 JSON 문자열                         │
 * │ $orderby                 │ 정렬 문자열                              │
 * │ $page                    │ 현재 페이지                              │
 * │ $pageSize                │ 페이지당 건수                            │
 * │ $__pdo                   │ PDO 인스턴스 (DB 직접 접근)              │
 * │ $full_site               │ 사이트 주소                              │
 * ├──────────────────────────┴───────────────────────────────────────────┤
 * │  클라이언트 제어 ($GLOBALS['...'] = 값)                              │
 * ├──────────────────────────┬───────────────────────────────────────────┤
 * │ _client_alert            │ alert() 팝업 표시                        │
 * │ _client_toast            │ 토스트 알림 표시                         │
 * │ _client_confirm          │ 저장 전 확인 (Yes→저장, No→취소)         │
 * │ _client_openTab          │ 새 탭 열기 {gubun, label, idx, openFull} │
 * │ _client_redirect         │ 현재 탭 교체 {gubun, label}              │
 * │ _client_css              │ CSS 주입 (문자열)                        │
 * │ _client_buttonText       │ 버튼 텍스트 변경 {write, reset}          │
 * │ _client_buttons          │ 사용자정의 버튼 [{label, action}]        │
 * │ _onlyList                │ 리스트전용 모드 (true)                   │
 * └──────────────────────────┴───────────────────────────────────────────┘
 *
 *  SQL 실행 헬퍼:
 *    $result = execSql("INSERT INTO t (name) VALUES (?)", ['홍길동']);
 *    $result = execSql("UPDATE a SET x=1; DELETE FROM b WHERE y=2");
 *    // 결과: resultCode, resultMessage, lastInsertId, rowCount
 */

    /*
     * ■ 리스트전용 프로그램 (조회만, 등록/수정 불가)
     * $GLOBALS['_onlyList'] = true;
     *
     * ■ 버튼 텍스트 변경
     * $GLOBALS['_client_buttonText'] = [
     *     'write' => '접수하기',     // +등록 → 접수하기
     *     'reset' => '전체보기',     // 초기화 → 전체보기
     * ];
     *
     * ■ 사용자 정의 버튼 추가 (list_json_init에서 $customAction으로 감지)
     * $GLOBALS['_client_buttons'] = [
     *     ['label' => '일괄적용', 'action' => 'apply'],
     *     ['label' => '마감처리', 'action' => 'close'],
     *     ['label' => '엑셀가져오기', 'action' => 'importExcel'],
     * ];
     *
     * ■ CSS 주입 (특정 요소 숨기기/스타일링)
     * $GLOBALS['_client_css'] = '
     *     #mis-btn-write { display: none; }
     *     #mis-btn-reset { background: #3182F6; color: #fff; }
     *     #mis-header { background: #f0f8ff; }
     * ';
     * // 주요 CSS ID: #mis-program, #mis-header, #mis-title,
     * //   #mis-header-actions, #mis-btn-write, #mis-btn-reset, #mis-btn-custom-0
     */

     $GLOBALS['_onlyList'] = true;

    // ── 뷰 디자이너가 팝업(iframe)으로 호출된 경우 ──────────────────────────
    $isPopup = ($_GET['isPopup'] ?? '') === 'Y';
    if ($isPopup) {
        // 1) 상단 탑바에는 브레이크포인트 버튼만. 폼폭 표시기 + 디자인적용은 React 헤더(프로그램명 우측)에 1회 주입
        $GLOBALS['_client_css'] = (string)($GLOBALS['_client_css'] ?? '') . '
            #mis-btn-reset,
            #mis-menu-insert,
            #mis-panel-more { display: none !important; }
            /* ── body 를 flex-column 으로: [topbar 고정높이] + [#root 나머지 전부] ── */
            html { height: 100vh; }
            body {
                height: 100vh; margin: 0; padding: 0;
                display: flex; flex-direction: column;
                overflow: hidden;
            }
            #mis-designer-topbar {
                flex: 0 0 42px;
                display: flex; gap: 8px; align-items: center;
                flex-wrap: nowrap; overflow-x: auto;
                box-sizing: border-box;
                padding: 6px 12px; border-bottom: 1px solid var(--color-border);
                background: var(--color-surface-2);
                z-index: 1000;
            }
            #mis-designer-topbar button {
                height: 28px; padding: 0 10px;
                border: 1px solid var(--color-border); border-radius: 6px;
                background: var(--color-surface); color: var(--color-text-1);
                font-size: 12px; font-weight: 600; cursor: pointer;
                transition: background .12s, color .12s, border-color .12s;
                flex-shrink: 0;
            }
            #mis-designer-topbar button:hover,
            #mis-designer-topbar button.active { background: var(--color-primary); color: #fff; border-color: var(--color-primary); }
            #root { flex: 1; min-height: 0; }
            #root > * { height: 100% !important; }
            /* ── React 헤더에 주입되는 폼폭/적용 박스 ── */
            .mis-designer-header-box {
                display: inline-flex; align-items: center; gap: 6px; margin-right: 8px;
            }
            .mis-designer-wpx-label {
                font-size:12px; font-weight:600; color:var(--color-text-2);
            }
            .mis-designer-wpx {
                padding:4px 10px; background:var(--color-primary); color:#fff;
                font-size:12px; font-weight:700; border-radius:4px;
            }
            #mis-designer-apply-btn {
                height:28px; padding:0 14px; margin-right:4px;
                border:1px solid var(--color-primary); border-radius:6px;
                background:var(--color-primary); color:#fff;
                font-size:12px; font-weight:700; cursor:pointer;
            }
            #mis-designer-apply-btn:hover { filter: brightness(1.1); }
        ';

        // 2) 디자이너 UI 주입
        //    - 상단 탑바 (body fixed): XL/LG/MD/SM/XS/<375 버튼만
        //    - React 헤더 (mis-header-actions): [현재 폼 폭] + [디자인적용] 1회 주입 (폴링)
        $GLOBALS['_client_js'] = <<<'JS'
(function () {
    // 전역 CSS(html/body/#root/#mis-designer-topbar)는 _client_css 자동 스코핑([data-real-pid] prefix)에
    // 걸려 body 직속 topbar 에 안 먹으므로, 스코핑되지 않는 _client_js 에서 직접 <style> 주입한다.
    if (!document.getElementById('mis-designer-gstyle')) {
        var gs = document.createElement('style');
        gs.id = 'mis-designer-gstyle';
        gs.textContent = `html{height:100vh}body{height:100vh;margin:0;padding:0;display:flex;flex-direction:column;overflow:hidden}`
          + `#mis-designer-topbar{flex:0 0 42px;display:flex;gap:8px;align-items:center;flex-wrap:nowrap;overflow-x:auto;box-sizing:border-box;padding:6px 12px;border-bottom:1px solid var(--color-border);background:var(--color-surface-2);z-index:1000}`
          + `#mis-designer-topbar button{height:28px;padding:0 10px;border:1px solid var(--color-border);border-radius:6px;background:var(--color-surface);color:var(--color-text-1);font-size:12px;font-weight:600;cursor:pointer;transition:background .12s,color .12s,border-color .12s;flex-shrink:0}`
          + `#mis-designer-topbar button:hover,#mis-designer-topbar button.active{background:var(--color-primary);color:#fff;border-color:var(--color-primary)}`
          + `#root{flex:1;min-height:0}#root>*{height:100%!important}`;
        document.head.appendChild(gs);
    }
    if (document.getElementById('mis-designer-topbar')) return;

    var bar = document.createElement('div');
    bar.id = 'mis-designer-topbar';

    // 폼 폭 표시용 참조 (나중에 React 헤더에 넣음)
    var wIndicator = document.createElement('span');
    wIndicator.id = 'mis-designer-wpx';
    wIndicator.className = 'mis-designer-wpx';
    wIndicator.textContent = '-';

    // Bootstrap 5 표준 브레이크포인트 — 콘텐츠 폭 기준
    // 래퍼(#mis-form-wrap) 폭 = 콘텐츠 목표 + CHROME (padding 32 + 스크롤바 ~15 보정)
    var CHROME = 50;
    // bp = 콘텐츠 폭 기준 Bootstrap 브레이크포인트 (panelW 와 직접 비교용)
    var widths = [
        { label: 'XL(≥1200)',  w: 1200 + CHROME, bp: 1200 },
        { label: 'LG(≥992)',   w:  992 + CHROME, bp:  992 },
        { label: 'MD(≥768)',   w:  768 + CHROME, bp:  768 },
        { label: 'SM(≥576)',   w:  576 + CHROME, bp:  576 },
        { label: 'XS(<576)',   w:  420,          bp:  375 },
        { label: '<375(100%)', w:  375,          bp:    0 }
    ];

    function applyWidth(px, btn) {
        try {
            var pwin = window.parent;
            var pdoc = pwin && pwin.document;
            if (!pdoc) return;
            var formWrap = pdoc.getElementById('mis-form-wrap');
            if (!formWrap) {
                alert('폼 영역이 열려있을 때만 사용할 수 있습니다.\n레코드를 먼저 선택하세요.');
                return;
            }
            // 활성 버튼 표시는 showWidth 가 일관 처리 (panelWidthChange 이벤트 수신 시)

            // '4'(full) 모드 가용 폭 = 폼 래퍼의 부모 폭
            var parentRow = formWrap.parentElement;
            var availableAtFull = parentRow ? parentRow.clientWidth : 0;

            // 요청 폭이 '4' 폭 이상 → '4' 버튼 클릭과 동일하게 처리
            if (availableAtFull && px >= availableAtFull) {
                if (typeof pwin.__misSetDesignerWidth === 'function') pwin.__misSetDesignerWidth(null);
                var btn4 = pdoc.getElementById('mis-panel-size-4');
                if (btn4) btn4.click();
                return;
            }

            // 부모 React state 로 경계선 이동 (인라인 !important 사용 안 함)
            if (typeof pwin.__misSetDesignerWidth === 'function') {
                pwin.__misSetDesignerWidth(px);
            }
        } catch (e) { console.error(e); }
    }

    widths.forEach(function (it) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = it.label;
        b.dataset.w  = String(it.w);
        b.dataset.bp = String(it.bp);
        b.addEventListener('click', function () { applyWidth(it.w, b); });
        bar.appendChild(b);
    });

    function showWidth(w) {
        var rounded = Math.round(w);
        wIndicator.textContent = rounded + 'px';
        var matched = null;
        var matchedBp = -1;
        bar.querySelectorAll('button[data-bp]').forEach(function (b) {
            var bp = parseInt(b.dataset.bp, 10);
            if (rounded >= bp && bp > matchedBp) { matched = b; matchedBp = bp; }
            b.classList.remove('active');
        });
        if (matched) matched.classList.add('active');
    }

    try {
        var pwin = window.parent;
        if (pwin && pwin !== window) {
            pwin.addEventListener('mis:panelWidthChange', function (e) {
                if (e && e.detail && typeof e.detail.width === 'number') showWidth(e.detail.width);
            });
        }
    } catch (e) {}

    // 초기 폭 표시 — 부모창 form-wrap 직접 측정 (clientWidth>0 까지 폴링)
    function pollInitialWidth(attempts) {
        attempts = attempts || 0;
        try {
            var pwin2 = window.parent;
            var pdoc2 = pwin2 && pwin2.document;
            var formWrap = pdoc2 && pdoc2.getElementById('mis-form-wrap');
            var w = formWrap ? formWrap.clientWidth : 0;
            if (w > 0) { showWidth(w); return; }
        } catch (e) {}
        if (attempts < 60) setTimeout(function(){ pollInitialWidth(attempts + 1); }, 50);
    }
    pollInitialWidth();

    // 브레이크포인트 탑바를 body 의 첫 번째 자식으로 삽입 (body flex-column: [topbar][#root])
    document.body.insertBefore(bar, document.body.firstChild);

    // ── React 헤더 (#mis-header-actions) 에 [현재 폼 폭] + [디자인적용] 1회 주입 (폴링, observer 없음) ──
    function tryInjectHeaderBox(attempts) {
        attempts = attempts || 0;
        var hdr = document.getElementById('mis-header-actions');
        if (!hdr) {
            if (attempts < 40) setTimeout(function(){ tryInjectHeaderBox(attempts + 1); }, 50);
            return;
        }
        if (hdr.querySelector('#mis-designer-apply-btn')) return;

        var box = document.createElement('span');
        box.className = 'mis-designer-header-box';

        var wLabel = document.createElement('span');
        wLabel.className = 'mis-designer-wpx-label';
        wLabel.textContent = '현재 폼 폭';
        box.appendChild(wLabel);
        box.appendChild(wIndicator);

        var applyBtn = document.createElement('button');
        applyBtn.id = 'mis-designer-apply-btn';
        applyBtn.type = 'button';
        applyBtn.textContent = '디자인적용';
        applyBtn.addEventListener('click', handleApplyClick);

        // ··· 더보기 버튼 좌측에 삽입 (없으면 맨 앞에 추가)
        var moreBtn = hdr.querySelector('button[title="더보기"]');
        var anchor = moreBtn;
        while (anchor && anchor.parentElement !== hdr) anchor = anchor.parentElement;
        if (anchor) {
            hdr.insertBefore(box, anchor);
            hdr.insertBefore(applyBtn, anchor);
        } else {
            hdr.appendChild(box);
            hdr.appendChild(applyBtn);
        }
    }
    tryInjectHeaderBox();

    function handleApplyClick() {
        var btn = this;
        (async function () {
            var rp = '';
            try {
                var af = JSON.parse(new URLSearchParams(window.location.search).get('allFilter') || '[]');
                af.forEach(function (x) {
                    if (!rp && (x.field || '').indexOf('real_pid') >= 0) rp = x.value;
                });
            } catch (e) {}
            if (!rp) { alert('대상 real_pid를 찾을 수 없습니다.'); return; }

            var m = document.cookie.match(/(?:^|;\s*)csrf_token=([^;]+)/);
            var csrf = m ? decodeURIComponent(m[1]) : '';
            if (!csrf) {
                try {
                    var rr = await fetch((window.__APP_CONFIG__ && window.__APP_CONFIG__.basePath || '') + '/api.php?act=csrf', { credentials: 'include' });
                    var dd = await rr.json();
                    csrf = dd.csrf_token || '';
                } catch (e) {}
            }

            try {
                btn.disabled = true;
                btn.textContent = '적용중...';
                var res = await fetch((window.__APP_CONFIG__ && window.__APP_CONFIG__.basePath || '') + '/api.php?act=treat&gubun=1333', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                    body: JSON.stringify({ action: 'applyDesign', real_pid: rp })
                });
                var data = await res.json();
                if (!data.success || !(data.data && data.data.ok)) {
                    alert((data.message || (data.data && data.data.message)) || '디자인 적용 실패');
                    return;
                }
                try {
                    if (typeof window.parent.__misRefreshProgram === 'function') {
                        window.parent.__misRefreshProgram();
                    } else {
                        window.parent.location.reload();
                    }
                } catch (e) {}
            } catch (e) {
                alert('오류: ' + e.message);
            } finally {
                btn.disabled = false;
                btn.textContent = '디자인적용';
            }
        })();
    }
})();
JS;
    }
}

/**
 * 디자인 적용: grid_view_class 가 비어있는 필드에 반응형 클래스 + 높이를 자동 설정
 *
 * Bootstrap 5 표준 BP 기준: XS<576 / SM≥576 / MD≥768 / LG≥992 / XL≥1200
 * grid_view_class 형식: "col-sm-N col-md-N col-lg-N col-xl-N row-N"
 *  - html(Quill):           col-sm-12 col-md-12 col-lg-12 col-xl-12 row-60  (52+9, max-height 9)
 *  - 첨부/이미지/textarea:  col-sm-12 col-md-12 col-lg-12 col-xl-12 row-4
 *  - 일반 입력:              col-sm-12 col-md-6  col-lg-4  col-xl-3  row-1
 */
function addLogic_treat(&$result) {
    global $__pdo;

    $action = $result['action'] ?? '';
    if ($action !== 'applyDesign') return;

    $realPid = trim((string)($result['real_pid'] ?? ''));
    if ($realPid === '') {
        $result['ok'] = false;
        $result['message'] = 'real_pid 필수';
        return;
    }

    try {
        // grid_view_class 가 비어있는 필드만 대상
        $stmt = $__pdo->prepare(
            "SELECT idx, grid_ctl_name, schema_type
               FROM mis_menu_fields
              WHERE real_pid = ?
                AND (grid_view_class IS NULL OR grid_view_class = '')
                AND useflag = '1'"
        );
        $stmt->execute([$realPid]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $upd = $__pdo->prepare(
            "UPDATE mis_menu_fields
                SET grid_view_class = ?
              WHERE idx = ?"
        );

        $count = 0;
        foreach ($rows as $r) {
            $gridCtl  = (string)($r['grid_ctl_name'] ?? '');
            $schema   = (string)($r['schema_type']   ?? '');

            $isAttach   = ($gridCtl === 'attach' || $gridCtl === 'image');
            $isTextarea = ($gridCtl === 'textarea' || $schema === 'textarea');
            $isHtml     = ($gridCtl === 'html' || $schema === 'html');

            if ($isHtml) {
                $cls = 'col-sm-12 col-md-12 col-lg-12 col-xl-12 row-60';
            } elseif ($isAttach || $isTextarea) {
                $cls = 'col-sm-12 col-md-12 col-lg-12 col-xl-12 row-4';
            } else {
                $cls = 'col-sm-12 col-md-6 col-lg-4 col-xl-3 row-1';
            }

            $upd->execute([$cls, $r['idx']]);
            $count++;
        }

        // 캐시 무효화 (대상 프로그램의 목록/뷰 캐시)
        try {
            $cache = new \App\MisCache();
            $cache->invalidateByRealPid($realPid);
        } catch (\Throwable $e) {}

        $result['ok']      = true;
        $result['count']   = $count;
        $result['message'] = "{$count}건 적용됨";
    } catch (\Throwable $e) {
        $result['ok']      = false;
        $result['message'] = '예외: ' . $e->getMessage();
    }
}