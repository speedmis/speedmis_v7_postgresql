<?php 

function list_json_init() {
    global $real_pid, $misJoinPid, $logicPid, $parent_idx, $full_siteID;
    global $flag, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript;
    if($flag=="read") { 
        if($app=="적용") {
            $appSql = "exec MisUser_Authority_Proc '" . $full_siteID . "','speedmis000001'; ";

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



function pageLoad() {

    global $actionFlag;
	global $misSessionIsAdmin;


        ?>
        <script>


function columns_templete(p_dataItem, p_aliasName) {
    if(p_aliasName=="AutoGubun") {
		var rValue = "<a href='index.php?RealPid=" + p_dataItem["RealPid"] + "&isMenuIn=Y' target='_blank' class='k-button'>Go</a>";
		if(p_dataItem["MenuType"]=="01") {
			rValue = rValue + "<a id='aid_" + p_dataItem["idx"] + "' href='index.php?RealPid=speedmis000266&idx=" 
				+ p_dataItem["RealPid"] + "&isMenuIn=Y' target='_blank' class='k-button'>Source</a>";
		} else if(p_dataItem["MenuType"]=="22") {
			rValue = rValue + "<a id='aid_" + p_dataItem["idx"] + "' href='index.php?RealPid=speedmis000989&idx=" 
				+ p_dataItem["RealPid"] + "&isMenuIn=Y' target='_blank' class='k-button'>Source</a>";
		}
		rValue = rValue + p_dataItem["AutoGubun"];
        return rValue;
    } else if(p_aliasName=="zhangmoksugeumaek") {
		rValue = p_dataItem["zhangmoksu"]*1000;
        return rValue;
    } else if(p_aliasName=="zseobeorojigyongnyang") {
		var url = "addLogicByte.php?RealPid="+p_dataItem["RealPid"]+"&MisJoinPid="+p_dataItem["MisJoinPid"];
		rValue = ajax_url_return(url);
        return rValue;
    } else if(p_aliasName=="zseobeorojikgeumaek") {
		var url = "addLogicByte.php?RealPid="+p_dataItem["RealPid"]+"&MisJoinPid="+p_dataItem["MisJoinPid"];
		rValue = ajax_url_return(url)*100;
        return rValue;
    } else if(p_aliasName=="zinswaeyongnyang") {
		var url = "addLogic_printByte.php?RealPid="+p_dataItem["RealPid"]+"&MisJoinPid="+p_dataItem["MisJoinPid"];
		rValue = ajax_url_return(url);
        return rValue;
    } else if(p_aliasName=="zinswaeyongnyanggeumaek") {
		var url = "addLogic_printByte.php?RealPid="+p_dataItem["RealPid"]+"&MisJoinPid="+p_dataItem["MisJoinPid"];
		rValue = ajax_url_return(url)*0.5;
        return rValue;
    } else {
        return p_dataItem[p_aliasName];
    }	
}

//사용자 정의 함수 = 함수 이름은 변형하면 안됨. 내용만. 없어도 됨. ==============================
//데이타의 변형은 즉시 가능 = rowFunction

function rowFunction_UserDefine(p_this) {
    //p_this.zseobeorojikgeumaek = p_this.zseobeorojigyongnyang * 1000; 
    //p_this.AutoGubun = 
    //"<a href=index.asp?gubun=" + p_this.idx + "&isMenuIn=Y target=_blank>[Go]</a> <a id='aid_" + p_this.idx + "' href=index.asp?RealPid=speedmis000266&idx=" + p_this.idx + "&isMenuIn=Y target=_blank>[Source]</a> " 
    //+ p_this.AutoGubun; 
}


//스타일 등의 변형은 로딩후에 가능 = rowFunctionAfter
function rowFunctionAfter_UserDefine(p_this) {


	//$(getCellObj_idx(p_this[key_aliasName], "zseobeorojikgeumaek"))[0].innerText = 999;


    /*
    $(getCellObj_idx(p_this[key_aliasName], "AutoGubun"))[0].innerHTML = 
    "<a href=index.php?RealPid=" + p_this.RealPid + "&isMenuIn=Y target=_blank>[Go]</a>"+iif(p_this.MenuType=="01", "<a id='aid_" + p_this.idx + "' href=index.php?RealPid=speedmis000266&idx=" 
    + p_this.RealPid + "&isMenuIn=Y target=_blank>[Source]</a>","")
    + " " + p_this.AutoGubun; 
    */

}      
        </script>
        <?php 
}
//end pageLoad


?>