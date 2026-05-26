<?php


function list_json_init() {
    global $actionFlag, $gubun, $misSessionUserId, $isFirstLoad;
    // 목록 데이터 로딩 전에 실행되는 초기화 로직
    //$GLOBALS['_client_alert'] = $misSessionUserId;
    //if($isFirstLoad === true ) {
    //  $GLOBALS['_client_openTab'] = [
    //      'gubun' => 314,
    //      'label' => '대시보드',
    //  ];
    //}
}

function list_json_load(&$data) {
    // $data: 목록 데이터 배열 (각 행을 수정 가능)
        //$data['gname'] = $data['gname'] . " | hahaha";
        //$data['__html']['gname'] = '<a href="https://naver.com" target="_blank">zzz' . $data['gname'] . '</a>';
}


function save_updateReady(&$saveList) {
      global $__pdo, $idx;

      // 값 검증
      if ($saveList['gname']=='바보') {
          $GLOBALS['_client_confirm'] = '바보라고요? 정말로 저장할까요?';
          return;
      }

}