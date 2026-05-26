<?php

function pageLoad() {

    global $ActionFlag;
	global $MisSession_IsAdmin;


        ?>
        <script>


function columns_templete(p_dataItem, p_aliasName) {
    if(p_aliasName=="RealPid") {
		var rValue = "<a href='index.php?RealPid=" + p_dataItem["RealPid"] + "&isMenuIn=Y' target='_blank' class='k-button'>열기</a>";

		rValue = p_dataItem["RealPid"] + " " + rValue;
        return rValue;
    } else {
        return p_dataItem[p_aliasName];
    }	
}

//사용자 정의 함수 = 함수 이름은 변형하면 안됨. 내용만. 없어도 됨. ==============================
//데이타의 변형은 즉시 가능 = rowFunction

function rowFunction_UserDefine(p_this) {
    //p_this.MenuName = p_this.depth + p_this.LanguageCode; 
    //p_this.AutoGubun = 
    //"<a href=index.asp?gubun=" + p_this.idx + "&isMenuIn=Y target=_blank>[Go]</a> <a id='aid_" + p_this.idx + "' href=index.asp?RealPid=speedmis000266&idx=" + p_this.idx + "&isMenuIn=Y target=_blank>[Source]</a>?" 
    //+ p_this.AutoGubun; 
}<?php if($MisSession_IsAdmin=="Y" && $ActionFlag=="list") { ?>
function thisLogic_toolbar() {
	
    $("a#btn_1").text("메뉴명반영");
    $("li#btn_1_overflow").text("메뉴명반영");
    $("#btn_1").css("background", "#88f");
    $("#btn_1").css("color", "#fff");
    $("#btn_1").click( function() {
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "적용";
        $("#grid").data("kendoGrid").dataSource.read();
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "";
    });
}
<?php } ?>

//스타일 등의 변형은 로딩후에 가능 = rowFunctionAfter
function rowFunctionAfter_UserDefine(p_this) {

    if(p_this.AutoGubun.length==6) {
        $(getCellObj_idx(p_this[key_aliasName], "SortG2")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "SortG2")).css("color","transparent");
        $(getCellObj_idx(p_this[key_aliasName], "SortG4")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "SortG4")).css("color","transparent");
    } else if(p_this.AutoGubun.length==4) {
        $(getCellObj_idx(p_this[key_aliasName], "SortG2")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "SortG2")).css("color","transparent");
        $(getCellObj_idx(p_this[key_aliasName], "SortG6")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "SortG6")).css("color","transparent");
    } else if(p_this.AutoGubun.length==2) {
        $(getCellObj_idx(p_this[key_aliasName], "SortG4")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "SortG4")).css("color","transparent");
        $(getCellObj_idx(p_this[key_aliasName], "SortG6")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "SortG6")).css("color","transparent");
    }

    /*
    $(getCellObj_idx(p_this[key_aliasName], "AutoGubun"))[0].innerHTML = 
    "<a href=index.php?RealPid=" + p_this.RealPid + "&isMenuIn=Y target=_blank>[Go]</a>"+iif(p_this.MenuType=="01", "<a id='aid_" + p_this.idx + "' href=index.php?RealPid=speedmis000266&idx=" 
    + p_this.RealPid + "&isMenuIn=Y target=_blank>[Source]</a>","")
    + "?" + p_this.AutoGubun; 
    */

}      
        </script>
        <?php 
}
//end pageLoad



function list_json_init() {
    global $RealPid, $MisJoinPid, $logicPid, $parent_idx, $full_siteID, $MS_MJ_MY;
    global $flag, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript;
    if($flag=="read") { 
        if($app=="적용") {
            if($MS_MJ_MY=='MY') {
                $appSql = "call MisUser_Authority_Proc('" . $full_siteID . "','speedmis000001'); ";
            } else {
                $appSql = "exec MisUser_Authority_Proc '" . $full_siteID . "','speedmis000001'; ";
            }

            if(execSql($appSql)) {
                $resultCode = "success";
                $resultMessage = "권한 및 메뉴변경사항의 적용이 완료되었습니다. 새로고침됩니다.";
                $afterScript = "document.treatForm.nmCommand.value = 'menuRefresh';document.treatForm.submit();";
            } else {
                $resultCode = "fail";
                $resultMessage = "권한적용 처리가 실패하였습니다.";
            }
        }
    }
}
//end list_json_init

?>