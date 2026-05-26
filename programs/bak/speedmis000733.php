<?php

function pageLoad() {

    global $actionFlag;

    if($actionFlag=="list") { 
        ?>
        <script>

//사용자 정의 함수 = 함수 이름은 변형하면 안됨. 내용만. 없어도 됨. ==============================
//데이타의 변형은 즉시 가능 = rowFunction

function rowFunction_UserDefine(p_this) {
	if(p_this.AutoGubun) {
        p_this.table_upidxQnStationName = ":".repeat(iif(p_this.AutoGubun.length<8,8,p_this.AutoGubun.length)-8) + p_this.table_upidxQnStationName; 
	}
    //p_this.AutoGubun = 
    //"<a href=index.asp?gubun=" + p_this.idx + "&isMenuIn=Y target=_blank>[Go]</a> <a id='aid_" + p_this.idx + "' href=index.asp?RealPid=speedmis000266&idx=" + p_this.idx + "&isMenuIn=Y target=_blank>[Source]</a>?" 
    //+ p_this.AutoGubun; 
}

function thisLogic_toolbar() {
    $("a#btn_1").text("자동정렬");
    $("li#btn_1_overflow").text("자동정렬");
    $("#btn_1").css("background", "#88f");
    $("#btn_1").css("color", "#fff");
    $("#btn_1").click( function() {
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "자동정렬";
        $("#grid").data("kendoGrid").dataSource.read();
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "";
    });
}


//스타일 등의 변형은 로딩후에 가능 = rowFunctionAfter
function rowFunctionAfter_UserDefine(p_this) {

    if(p_this.AutoGubun.length==20) {
        $(getCellObj_idx(p_this[key_aliasName], "SortG2")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "SortG2")).css("color","transparent");
        $(getCellObj_idx(p_this[key_aliasName], "SortG4")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "SortG4")).css("color","transparent");
        $(getCellObj_idx(p_this[key_aliasName], "SortG6")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "SortG6")).css("color","transparent");
        $(getCellObj_idx(p_this[key_aliasName], "SortG8")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "SortG8")).css("color","transparent");
    } else if(p_this.AutoGubun.length==16) {
        $(getCellObj_idx(p_this[key_aliasName], "SortG2")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "SortG2")).css("color","transparent");
        $(getCellObj_idx(p_this[key_aliasName], "SortG4")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "SortG4")).css("color","transparent");
        $(getCellObj_idx(p_this[key_aliasName], "SortG6")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "SortG6")).css("color","transparent");
        $(getCellObj_idx(p_this[key_aliasName], "SortG10")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "SortG10")).css("color","transparent");
    } else if(p_this.AutoGubun.length==12) {
        $(getCellObj_idx(p_this[key_aliasName], "SortG2")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "SortG2")).css("color","transparent");
        $(getCellObj_idx(p_this[key_aliasName], "SortG4")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "SortG4")).css("color","transparent");
        $(getCellObj_idx(p_this[key_aliasName], "SortG8")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "SortG8")).css("color","transparent");
        $(getCellObj_idx(p_this[key_aliasName], "SortG10")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "SortG10")).css("color","transparent");
    } else if(p_this.AutoGubun.length==8) {
        $(getCellObj_idx(p_this[key_aliasName], "SortG2")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "SortG2")).css("color","transparent");
        $(getCellObj_idx(p_this[key_aliasName], "SortG6")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "SortG6")).css("color","transparent");
        $(getCellObj_idx(p_this[key_aliasName], "SortG8")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "SortG8")).css("color","transparent");
        $(getCellObj_idx(p_this[key_aliasName], "SortG10")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "SortG10")).css("color","transparent");
    } else if(p_this.AutoGubun.length==4) {
        $(getCellObj_idx(p_this[key_aliasName], "SortG4")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "SortG4")).css("color","transparent");
        $(getCellObj_idx(p_this[key_aliasName], "SortG6")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "SortG6")).css("color","transparent");
        $(getCellObj_idx(p_this[key_aliasName], "SortG8")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "SortG8")).css("color","transparent");
        $(getCellObj_idx(p_this[key_aliasName], "SortG10")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "SortG10")).css("color","transparent");
    }


}      
        </script>
        <?php 
    }
}
//end pageLoad



function list_json_init() {
    global $real_pid, $misJoinPid, $logicPid, $parent_idx, $full_siteID, $MS_MJ_MY, $grid_load_once_event;
    global $flag, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript;
    if($flag=='read') { 
        if($app=='자동정렬' || $grid_load_once_event=='xxxN') {
            $appSql = "select count(num) from MisCompanyMgt where sortG2='0' and useflag=1 ";
            $cnt = onlyOnereturnSql($appSql);
            if($cnt==1) {
                if($MS_MJ_MY=='MY') {
                    $appSql = "call MisCompanyMgt_Ordering_Proc();";
                } else {
                    $appSql = "EXECUTE MisCompanyMgt_Ordering_Proc";
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

    global $full_siteID, $base_root, $real_pid, $misJoinPid, $logicPid, $parent_idx;
    global $key_aliasName, $key_value, $actionFlag, $updateList;

//print_r($updateList);
//exit;

	$AutoGubun = onlyOnereturnSql("select AutoGubun from MisCompanyMgt where num='" . $updateList["upidx"] . "'");

	$updateList["SortG4"] = 0;
	$updateList["SortG6"] = 0;
	$updateList["SortG8"] = 0;
	$updateList["SortG10"] = 0;

	if($AutoGubun=="00") {
		$updateList["AutoGubun"] = "9999";
		$updateList["SortG2"] = 9999;
	} else if(Len($AutoGubun)==4) {
		$updateList["AutoGubun"] = $AutoGubun . "9999";
		$updateList["SortG2"] = Left($AutoGubun,4)*1;
		$updateList["SortG4"] = 9999;
	} else if(Len($AutoGubun)==8) {
		$updateList["AutoGubun"] = $AutoGubun . "9999";
		$updateList["SortG2"] = Left($AutoGubun,4)*1;
		$updateList["SortG4"] = Mid($AutoGubun,5,4)*1;
		$updateList["SortG6"] = 9999;
	} else if(Len($AutoGubun)==12) {
		$updateList["AutoGubun"] = $AutoGubun . "9999";
		$updateList["SortG2"] = Left($AutoGubun,4)*1;
		$updateList["SortG4"] = Mid($AutoGubun,5,4)*1;
		$updateList["SortG6"] = Mid($AutoGubun,9,4)*1;
		$updateList["SortG8"] = 9999;
	} else if(Len($AutoGubun)==16) {
		$updateList["AutoGubun"] = $AutoGubun . "9999";
		$updateList["SortG2"] = Left($AutoGubun,4)*1;
		$updateList["SortG4"] = Mid($AutoGubun,5,4)*1;
		$updateList["SortG6"] = Mid($AutoGubun,9,4)*1;
		$updateList["SortG8"] = Mid($AutoGubun,13,4)*1;
		$updateList["SortG10"] = 9999;
	}

	//print_r($updateList);
	//exit;

}
//end save_writeBefore



function save_writeAfter() {

    global $base_root, $real_pid, $misJoinPid, $logicPid, $parent_idx;
    global $key_aliasName, $key_value, $saveList, $saveUploadList, $viewList, $deleteList;
    global $Grid_Default, $actionFlag, $misSessionUserId, $newIdx;
    global $afterScript,$MS_MJ_MY;


	$appSql = "select count(num) from MisCompanyMgt where sortG2='0' and useflag=1 ";
	$cnt = onlyOnereturnSql($appSql);

	if($cnt==1) {
        if($MS_MJ_MY=='MY') {
            $appSql = "call MisCompany_Ordering_Proc();";
        } else {
            $appSql = "EXECUTE MisCompany_Ordering_Proc";
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