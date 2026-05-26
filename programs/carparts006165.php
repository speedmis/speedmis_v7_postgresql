<?php

function pageLoad() {

    global $actionFlag;

    if($actionFlag=="list") { 
        ?>
        <script>

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
    $("a#btn_1").text("DB반영");
    $("li#btn_1_overflow").text("DB반영");
    $("#btn_1").css("background", "#88f");
    $("#btn_1").css("color", "#fff");
    $("#btn_1").click( function() {
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "DB반영";
        $("#grid").data("kendoGrid").dataSource.read();
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "";
    });
}


//스타일 등의 변형은 로딩후에 가능 = rowFunctionAfter
function rowFunctionAfter_UserDefine(p_this) {

    if(p_this.autogubun.length>=8) {
        $(getCellObj_idx(p_this[key_aliasName], "sort_g01")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "sort_g01")).css("color","transparent");
    }
    if(p_this.autogubun.length>=12) {
        $(getCellObj_idx(p_this[key_aliasName], "sort_g02")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "sort_g02")).css("color","transparent");
    }
    if(p_this.autogubun.length>=16) {
        $(getCellObj_idx(p_this[key_aliasName], "sort_g03")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "sort_g03")).css("color","transparent");
    }

}      
        </script>
        <?php 
    }
}
//end pageLoad



function list_json_init() {
    global $real_pid, $mis_join_pid, $logicPid, $parent_idx, $full_siteID, $MS_MJ_MY, $grid_load_once_event;
    global $flag, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript;
    if($flag=='read') { 
        if($app=='DB반영') {
            $appSql = "select count(idx) from parts_cate_a where sort_g01='0' and use_yn=1 ";
            $cnt = onlyOnereturnSql($appSql);
            if($cnt==1) {
                if($MS_MJ_MY=='MY') {
                    $appSql = "call parts_cate_a_ordering_proc();";
                } else {
                    $appSql = "EXECUTE parts_cate_a_ordering_proc";
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



function save_updateAfter() {

	global $updateList, $kendoCulture, $afterScript, $base_domain;

	$afterScript = "$('#btn_1').click();";

}
//end save_updateAfter



function save_writeBefore() {

    global $full_siteID, $base_root, $real_pid, $mis_join_pid, $logicPid, $parent_idx;
    global $key_aliasName, $key_value, $actionFlag, $updateList;

//print_r($updateList);
//exit;

	$autogubun = onlyOnereturnSql("select autogubun from parts_cate_a where idx='" . $updateList["upidx"] . "'");

	$updateList["sort_g01"] = 0;
	$updateList["sort_g02"] = 0;
	$updateList["sort_g03"] = 0;

	if($autogubun=="00") {
		$updateList["autogubun"] = "9999";
		$updateList["sort_g01"] = 9999;
	} else if(Len($autogubun)==4) {
		$updateList["autogubun"] = $autogubun . "9999";
		$updateList["sort_g01"] = (string)Left($autogubun,4)*1;
		$updateList["sort_g02"] = 9999;
	} else if(Len($autogubun)==8) {
		$updateList["autogubun"] = $autogubun . "9999";
		$updateList["sort_g01"] = (string)Left($autogubun,4)*1;
		$updateList["sort_g02"] = (string)Mid($autogubun,5,4)*1;
		$updateList["sort_g03"] = 9999;
	}

	//print_r($updateList);
	//exit;

}
//end save_writeBefore



function save_writeAfter() {

    global $base_root, $real_pid, $mis_join_pid, $logicPid, $parent_idx;
    global $key_aliasName, $key_value, $saveList, $saveUploadList, $viewList, $deleteList;
    global $Grid_Default, $actionFlag, $misSessionUserId, $newIdx;
    global $afterScript,$MS_MJ_MY;


	$appSql = "select count(idx) from parts_cate_a where sort_g01='0' and use_yn=1 ";
	$cnt = onlyOnereturnSql($appSql);

	if($cnt==1) {
        if($MS_MJ_MY=='MY') {
            $appSql = "call parts_cate_a_ordering_proc();";
        } else {
            $appSql = "EXECUTE parts_cate_a_ordering_proc";
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