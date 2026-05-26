<?php

function misMenuList_change() {

	//misMenuList 테이블에 의한 설정값인 $result 를 바꾸는게 이 함수의 핵심기능
    global $ActionFlag, $gubun, $parent_idx, $RealPid, $logicPid, $result, $MisSession_UserID;
	global $MisSession_PositionCode, $flag, $list_check_hidden;

	if($RealPid=='carparts006078') {
		$temp1 = $MisSession_UserID;
		if($temp1=='admin' || $temp1=='gadmin') {
			$temp1 = '포천';
		}
		$result[0]['g09'] = " and table_m.depth>=1 and vs.full_name like '$temp1%'";
	}


}
//end misMenuList_change



function pageLoad() {

    global $ActionFlag;

    if($ActionFlag=="list") { 
        ?>
        <script>
//$('body').attr('onlylist','');	
//사용자 정의 함수 = 함수 이름은 변형하면 안됨. 내용만. 없어도 됨. ==============================
//데이타의 변형은 즉시 가능 = rowFunction

function rowFunction_UserDefine(p_this) {
	if(p_this.autogubun) {
        p_this.table_upidxQnStationName = ":".repeat(iif(p_this.autogubun.length<8,8,p_this.autogubun.length)-8) + p_this.table_upidxQnStationName; 
	}
    //p_this.autogubun = 
    //"<a href=index.asp?gubun=" + p_this.idx + "&isMenuIn=Y target=_blank>[Go]</a> <a id='aid_" + p_this.idx + "' href=index.asp?RealPid=speedmis000266&idx=" + p_this.idx + "&isMenuIn=Y target=_blank>[Source]</a>?" 
    //+ p_this.autogubun; 
}

function thisLogic_toolbar() {
    $("a#btn_1").text("수정내역 반영");
    $("li#btn_1_overflow").text("수정내역 반영");
    $("#btn_1").css("background", "#88f");
    $("#btn_1").css("color", "#fff");
    $("#btn_1").click( function() {
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "자동정렬";
        $("#grid").data("kendoGrid").dataSource.read();
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "";
    });
    $("a#btn_2").text("아래 내역을 인쇄대기열에 추가");
    $("#btn_2").css("background", "#f88");
    $("#btn_2").css("color", "#fff");
    $("#btn_2").click( function() {
		if($('div#grid').data('kendoGrid').dataSource._filter.filters.length==0) {
			alert('최소한의 필터검색 후 진행하세요! 레벨값을 넣어도 좋습니다.');	
			return false;
		}
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "인쇄";
        $("#grid").data("kendoGrid").dataSource.read();
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "";
    });
}


//스타일 등의 변형은 로딩후에 가능 = rowFunctionAfter
function rowFunctionAfter_UserDefine(p_this) {

    if(p_this.autogubun.length>=8) {
        $(getCellObj_idx(p_this[key_aliasName], "sortG01")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "sortG01")).css("color","transparent");
    }
    if(p_this.autogubun.length>=12) {
        $(getCellObj_idx(p_this[key_aliasName], "sortG02")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "sortG02")).css("color","transparent");
    }
    if(p_this.autogubun.length>=16) {
        $(getCellObj_idx(p_this[key_aliasName], "sortG03")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "sortG03")).css("color","transparent");
    }
    if(p_this.autogubun.length>=20) {
        $(getCellObj_idx(p_this[key_aliasName], "sortG04")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "sortG04")).css("color","transparent");
    }
    if(p_this.autogubun.length>=24) {
        $(getCellObj_idx(p_this[key_aliasName], "sortG05")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "sortG05")).css("color","transparent");
    }
    if(p_this.autogubun.length>=28) {
        $(getCellObj_idx(p_this[key_aliasName], "sortG06")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "sortG06")).css("color","transparent");
    }
    if(p_this.autogubun.length>=32) {
        $(getCellObj_idx(p_this[key_aliasName], "sortG07")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "sortG07")).css("color","transparent");
    }
    if(p_this.autogubun.length>=36) {
        $(getCellObj_idx(p_this[key_aliasName], "sortG08")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "sortG08")).css("color","transparent");
    }
    if(p_this.autogubun.length>=40) {
        $(getCellObj_idx(p_this[key_aliasName], "sortG09")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "sortG09")).css("color","transparent");
    }

}      
        </script>
        <?php 
    }
}
//end pageLoad



function list_json_init() {
    global $RealPid, $MisJoinPid, $logicPid, $parent_idx, $full_siteID, $MS_MJ_MY, $grid_load_once_event;
    global $flag, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript;
    if($flag=='read') { 
        if($app=='자동정렬') {
            $appSql = "select count(idx) from mis_parts_storage where sortG01='0' and useflag=1 ";
            $cnt = onlyOnereturnSql($appSql);
            if($cnt==1) {
                if($MS_MJ_MY=='MY') {
                    $appSql = "call mis_parts_storage_Ordering_Proc();";
                } else {
                    $appSql = "EXECUTE mis_parts_storage_Ordering_Proc";
                }
                if(execSql($appSql)) {
                    $resultCode = "success";
                    $resultMessage = "자동정렬이 완료되었습니다. ";
                } else {
                    $resultCode = "fail";
                    $resultMessage = "자동정렬 처리가 실패하였습니다.";
                }
            } else {
                $resultCode = "fail";
                $resultMessage = "1레벨정렬값 중 0 으로 된 최상위 부서는 하나만 존재해야 합니다.";
            }

        }
    }
}
//end list_json_init



function list_query() {

    global $RealPid, $MisJoinPid, $logicPid, $parent_idx;
    global $flag, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript;
    global $countQuery, $selectQuery, $idx_aliasName;

	//아래는 어떤 특정한 상황에 대한 적용예입니다.
    if($app=='인쇄') { 
		$appSql2 = "  UPDATE mis_parts_storage table_m  -- (1) UPDATE 직후 별칭 지정
LEFT OUTER JOIN vv_mis_parts_storage_tree vs 
  ON vs.idx = table_m.idx
SET table_m.print_request_time = NOW(), 
    table_m.print_response_time = NULL
		where " . splitVB($countQuery, "where 9=9  and")[1];
		//echo $appSql2;
		execSql($appSql2);
		
		$resultMessage = '정상적으로 인쇄대기열에 추가되었습니다.';
		$resultCode = 'fail';
   }

}
//end list_query



function save_updateAfter() {

	global $updateList, $kendoCulture, $afterScript, $base_domain;

	$afterScript = "$('#btn_1').click();";

}
//end save_updateAfter



function save_writeBefore() {

    global $full_siteID, $base_root, $RealPid, $MisJoinPid, $logicPid, $parent_idx;
    global $key_aliasName, $key_value, $ActionFlag, $updateList;

//print_r($updateList);
//exit;

	$autogubun = onlyOnereturnSql("select autogubun from mis_parts_storage where idx='" . $updateList["upidx"] . "'");

	$updateList["sortG01"] = 0;
	$updateList["sortG02"] = 0;
	$updateList["sortG03"] = 0;
	$updateList["sortG04"] = 0;
	$updateList["sortG05"] = 0;
	$updateList["sortG06"] = 0;
	$updateList["sortG07"] = 0;
	$updateList["sortG08"] = 0;
	$updateList["sortG09"] = 0;
	$updateList["sortG10"] = 0;

	if($autogubun=="00") {
		$updateList["autogubun"] = "9999";
		$updateList["sortG01"] = 9999;
	} else if(Len($autogubun)==4) {
		$updateList["autogubun"] = $autogubun . "9999";
		$updateList["sortG01"] = (string)Left($autogubun,4)*1;
		$updateList["sortG02"] = 9999;
	} else if(Len($autogubun)==8) {
		$updateList["autogubun"] = $autogubun . "9999";
		$updateList["sortG01"] = (string)Left($autogubun,4)*1;
		$updateList["sortG02"] = (string)Mid($autogubun,5,4)*1;
		$updateList["sortG03"] = 9999;
	} else if(Len($autogubun)==12) {
		$updateList["autogubun"] = $autogubun . "9999";
		$updateList["sortG01"] = Left($autogubun,4)*1;
		$updateList["sortG02"] = Mid($autogubun,5,4)*1;
		$updateList["sortG03"] = Mid($autogubun,9,4)*1;
		$updateList["sortG04"] = 9999;
	} else if(Len($autogubun)==16) {
		$updateList["autogubun"] = $autogubun . "9999";
		$updateList["sortG01"] = Left($autogubun,4)*1;
		$updateList["sortG02"] = Mid($autogubun,5,4)*1;
		$updateList["sortG03"] = Mid($autogubun,9,4)*1;
		$updateList["sortG04"] = Mid($autogubun,13,4)*1;
		$updateList["sortG05"] = 9999;
	} else if(Len($autogubun)==20) {
        $updateList["autogubun"] = $autogubun . "9999";
        $updateList["sortG01"] = Left($autogubun,4)*1;
        $updateList["sortG02"] = Mid($autogubun,5,4)*1;
        $updateList["sortG03"] = Mid($autogubun,9,4)*1;
        $updateList["sortG04"] = Mid($autogubun,13,4)*1;
        $updateList["sortG05"] = Mid($autogubun,17,4)*1;
        $updateList["sortG06"] = 9999;
    } else if(Len($autogubun)==24) {
        $updateList["autogubun"] = $autogubun . "9999";
        $updateList["sortG01"] = Left($autogubun,4)*1;
        $updateList["sortG02"] = Mid($autogubun,5,4)*1;
        $updateList["sortG03"] = Mid($autogubun,9,4)*1;
        $updateList["sortG04"] = Mid($autogubun,13,4)*1;
        $updateList["sortG05"] = Mid($autogubun,17,4)*1;
        $updateList["sortG06"] = Mid($autogubun,21,4)*1;
        $updateList["sortG07"] = 9999;
    } else if(Len($autogubun)==28) {
        $updateList["autogubun"] = $autogubun . "9999";
        $updateList["sortG01"] = Left($autogubun,4)*1;
        $updateList["sortG02"] = Mid($autogubun,5,4)*1;
        $updateList["sortG03"] = Mid($autogubun,9,4)*1;
        $updateList["sortG04"] = Mid($autogubun,13,4)*1;
        $updateList["sortG05"] = Mid($autogubun,17,4)*1;
        $updateList["sortG06"] = Mid($autogubun,21,4)*1;
        $updateList["sortG07"] = Mid($autogubun,25,4)*1;
        $updateList["sortG08"] = 9999;
    } else if(Len($autogubun)==32) {
        $updateList["autogubun"] = $autogubun . "9999";
        $updateList["sortG01"] = Left($autogubun,4)*1;
        $updateList["sortG02"] = Mid($autogubun,5,4)*1;
        $updateList["sortG03"] = Mid($autogubun,9,4)*1;
        $updateList["sortG04"] = Mid($autogubun,13,4)*1;
        $updateList["sortG05"] = Mid($autogubun,17,4)*1;
        $updateList["sortG06"] = Mid($autogubun,21,4)*1;
        $updateList["sortG07"] = Mid($autogubun,25,4)*1;
        $updateList["sortG08"] = Mid($autogubun,29,4)*1;
        $updateList["sortG09"] = 9999;
    } else if(Len($autogubun)==36) {
        $updateList["autogubun"] = $autogubun . "9999";
        $updateList["sortG01"] = Left($autogubun,4)*1;
        $updateList["sortG02"] = Mid($autogubun,5,4)*1;
        $updateList["sortG03"] = Mid($autogubun,9,4)*1;
        $updateList["sortG04"] = Mid($autogubun,13,4)*1;
        $updateList["sortG05"] = Mid($autogubun,17,4)*1;
        $updateList["sortG06"] = Mid($autogubun,21,4)*1;
        $updateList["sortG07"] = Mid($autogubun,25,4)*1;
        $updateList["sortG08"] = Mid($autogubun,29,4)*1;
        $updateList["sortG09"] = Mid($autogubun,33,4)*1;
        $updateList["sortG10"] = 9999;
    }

	//print_r($updateList);
	//exit;

}
//end save_writeBefore



function save_writeAfter() {

    global $base_root, $RealPid, $MisJoinPid, $logicPid, $parent_idx;
    global $key_aliasName, $key_value, $saveList, $saveUploadList, $viewList, $deleteList;
    global $Grid_Default, $ActionFlag, $MisSession_UserID, $newIdx;
    global $afterScript,$MS_MJ_MY;


	$appSql = "select count(idx) from mis_parts_storage where sortG01='0' and useflag=1 ";
	$cnt = onlyOnereturnSql($appSql);

	if($cnt==1) {
        if($MS_MJ_MY=='MY') {
            $appSql = "call mis_parts_storage_Ordering_Proc();";
        } else {
            $appSql = "EXECUTE mis_parts_storage_Ordering_Proc";
        }
		if(execSql($appSql)) {
			$resultCode = "success";
			$resultMessage = "자동정렬이 완료되었습니다. ";
		} else {
			$resultCode = "fail";
			$resultMessage = "자동정렬 처리가 실패하였습니다.";
		}
	} else {
		$resultCode = "fail";
		$resultMessage = "1레벨정렬값 중 0 으로 된 최상위 부서는 하나만 존재해야 합니다.";
	}

	//입력 처리 후, 임의의 url 로 보내는 처리문입니다. 
    $afterScript = "alert('$resultMessage'); $('#btn_1').click();";
   
}
//end save_writeAfter



function textUpdate_sql() {

    global $strsql, $keyAlias, $keyValue, $thisValue, $oldText, $thisAlias, $resultCode, $resultMessage, $afterScript;

	$afterScript = "$('#btn_1').click();";

}
//end textUpdate_sql

?>