<?php

function pageLoad() {
    $GLOBALS['_client_buttons'] = [
        ['label' => '권한적용', 'action' => '권한적용']
    ];
}

function list_json_init() {
    global $customAction;

    if ($customAction === '권한적용') {
        execSql("call mis_user_authority_proc('{$_ENV['SITE_ID']}');");
        $GLOBALS['_client_toast'] = ['msg' => '권한적용 완료', 'type' => 'success', 'duration' => 8000];
    }
}