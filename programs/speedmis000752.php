<?php
/**
 * 일반게시판 모듈 (speedmis000752) — mis_board_article, 답변(threaded) 지원. v7 포팅+보강.
 *
 * 스레드 구조(전통 한국형 게시판):
 *   b_ref   = 스레드 그룹(원글 idx). 원글은 b_ref=자기 idx.
 *   b_step  = 스레드 내 정렬 순서(작을수록 위). 답변 삽입 시 뒤 형제 +1 밀고 그 자리에.
 *   b_level = 답변 깊이(들여쓰기). 답변의 답변의 답변 = 1→2→3...
 *
 * 목록 정렬: add_url 의 orderby=-b_ref,b_step (최신 스레드 위 + 스레드내 순서, DB add_url 설정).
 * 답변: 조회폼 '답변' 버튼(_client_formButtons action=boardReply) → 부모 b_ref/b_step/b_level
 *        프리필 후 write 모드 진입(referInsert 메커니즘). 저장 시 아래 훅이 스레드 계산.
 */

// 목록 — 답변(b_level>0)은 깊이만큼 들여쓰기 + '└' 마커로 답변임을 표시.
function list_json_load(&$data)
{
    $level = (int)($data['b_level'] ?? 0);
    if ($level > 0 && array_key_exists('title', $data)) {
        $indent = str_repeat('&nbsp;', $level * 4);
        $title  = htmlspecialchars((string)($data['title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $data['__html']['title'] =
            '<span style="color:var(--color-text-3)">' . $indent . '└&nbsp;</span>'
            . '<span style="color:var(--color-text-2)">' . $title . '</span>';
    }
}

// 조회폼 — '답변' 버튼 노출.
function view_load(&$row)
{
    $GLOBALS['_client_formButtons'] = [
        ['label' => '답변', 'action' => 'boardReply'],
    ];
}

// INSERT 직전 — 답변이면 스레드 계산, 새 글이면 b_step/b_level=0(b_ref 는 저장 후).
function save_writeBefore(&$updateList)
{
    global $real_pid;
    $updateList['real_pid'] = $real_pid;

    $bRef = (int)($updateList['b_ref'] ?? 0);
    if ($bRef > 0) {
        // 답변 — 프리필된 b_step = 부모 b_step. 부모 뒤 형제들을 한 칸씩 밀고 그 자리에 삽입.
        $pStep = (int)($updateList['b_step'] ?? 0);
        execSql("UPDATE mis_board_article SET b_step = b_step + 1 WHERE b_ref = ? AND b_step > ?", [$bRef, $pStep]);
        $updateList['b_step'] = $pStep + 1;
        if (!isset($updateList['b_level'])) $updateList['b_level'] = 1; // 클라이언트가 부모+1 로 프리필
    } else {
        // 새 원글
        $updateList['b_step']  = 0;
        $updateList['b_level'] = 0;
    }
}

// INSERT 직후 — 새 원글이면 b_ref = 자기 idx.
function save_writeAfter($newIdx, &$afterScript)
{
    execSql("UPDATE mis_board_article SET b_ref = ? WHERE idx = ? AND (b_ref IS NULL OR b_ref = 0)", [(int)$newIdx, (int)$newIdx]);
}
