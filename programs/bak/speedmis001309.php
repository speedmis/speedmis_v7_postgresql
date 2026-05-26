<?php

function spMisMenuListxxx_change() {

	//spMisMenuListxxx 테이블에 의한 설정값인 $result 를 바꾸는게 이 함수의 핵심기능
    global $actionFlag, $gubun, $parent_gubun, $parent_idx, $real_pid, $logicPid, $result;
	global $misSessionPositionCode, $flag;

    $담당자ID = "";
    if($parent_gubun!="") {
        
        $담당자ID = onlyOneReturnSql("select 계좌배분_직원ID목록 from spmoters_카드내역 where idx='$parent_gubun';");

        if(InStr($담당자ID,",")>0) {
            $search_index = array_search("damdangjaID", array_column($result, 'aliasName'));
            $result[$search_index]["Grid_MaxLength"] = "20";
            $result[$search_index]["Grid_Items"] = $담당자ID;
            $result[$search_index]["Grid_CtlName"] = "dropdownitem";
            $result[$search_index]["Grid_ListEdit"] = "Y";
        }
    };

}
//end spMisMenuListxxx_change



function pageLoad() {

    global $actionFlag;
	global $misSessionIsAdmin;


	if($actionFlag=="modify") {
        ?>
        <style>

        </style>
        <?php 
	}
}
//end pageLoad




?>