<?php

function list_json_load() {

    global $base_root, $real_pid, $misJoinPid, $logicPid, $parent_idx,$addParam;
    global $flag, $selField, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript;
    global $data, $key_aliasName, $child_alias, $selectQuery, $keyword, $menuName;
	global $_count;  //총갯수
	//$flag 는 목록조회시 'read'   내용조회시 'view'    수정시 'modify'   입력시 'write'
	//$selField 는 필터링을 하는 순간 발생하는 필드alias 값.

    if($flag=='readResult' && $addParam=='print') { 
        $p_data = json_decode($data, true);
		$count = count($p_data);
        if($count>0) {
			gzecho($p_data[0]['id']);
			exit;
		} else {
			gzecho('333');exit;
		}
    }

}
//end list_json_load

?>