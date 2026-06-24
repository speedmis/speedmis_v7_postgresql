<?php
/**
 * 공지사항 모듈 (speedmis000749) — mis_board_article. 공지이므로 답변(threaded) 없음.
 * 목록 최신순: add_url 의 orderby=-idx (DB add_url 설정). 새 글에 real_pid 만 채움.
 */

function save_writeBefore(&$updateList)
{
    global $real_pid;
    $updateList['real_pid'] = $real_pid;
}
