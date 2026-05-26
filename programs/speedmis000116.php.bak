<?php

function pageLoad() {

    global $ActionFlag, $RealPid;
	global $MisSession_IsAdmin, $MisSession_UserID;
	global $send_admin_mail, $telegram_bot_name, $telegram_bot_token, $full_site, $kendoCulture;

/*
	//특정상황에서 페이지를 이동시키는 예제입니다.
	if($ActionFlag=="list" && $parent_RealPid=="speedmis000028") {
		$target_parent_gubun = RealPidIntoGubun("speedmis001071");
		$url = "index.php?RealPid=$RealPid&parent_gubun=$target_parent_gubun&parent_idx=$parent_idx";
		re_direct($url);
	}
*/

        ?>

		<style>

div.before_round_allPush_YN, div#round_allPush_YN {
	display: none;
}
div#zallimsangtae {
    color: red;
    font-weight: bold;
}				
		</style>

        <script>
function makeLink(userid,username) {

	<?php if($MisSession_IsAdmin=='Y') { ?>
	expireday = DateAdd('d',2,today10());    //오늘포함 3일간 유효.
	url = "addLogic_treat.php?RealPid=<?=$RealPid?>&userid="+userid+"&username="+username+"&expireday="+expireday;
	link = ajax_url_return(url);

	send_msg = `사이트를 다시 사용하시려면 다음 링크를 통하여 진행해주세요.

	URL : `+link+`

	본인의 ID / 성명 / 새비밀번호 / 새비밀번호확인이 정확히 입력되어야 합니다.

	이 링크의 유효기간은 `+expireday+` 까지입니다.
	`;
	msg = `
	아래와 같은 내용으로 자동복사 되었습니다. 해당 직원분께 전달해주세요! 사용자가 정상적으로 진행할 경우, 차단해제 및 암호가 변경됩니다.
	===============
	`+send_msg;

	copyStringToClipboard(send_msg);

	alert(msg);
	<?php } else { ?>
	alert('해당 클릭을 통해 관리자는 로그인이 차단된 사용자에게 비밀번호변경 목적으로 링크를 생성하여 전달할 수 있습니다. admin 전용입니다.');
	<?php } ?>
				
}
			
//웹소스 디테일에서 템플릿으로 체크한 항목에 대해 출력내용을 변경할 수 있습니다. 이때 목록 또는 본문내용에 동일하게 적용됩니다.
function columns_templete(p_dataItem, p_aliasName) {

    if(p_aliasName=="virtual_fieldQnpwReset") {
			var rValue = `<a href="javascript:;" onclick="makeLink('` + p_dataItem["UniqueNum"] + `','` + p_dataItem["UserName"] + `');" class='k-button'>링크생성</a>`;

			return rValue;
    } else if(p_aliasName=='zallimsangtae') {

		//웹소스디테일의 데이터타입을 number^^#,##0 라고 지정 및 템플릿을 Y 로 했을 경우, 아래와 같은 식으로 하면 number 포맷으로 출력할 수 있음.
		//rValue = rValue + columns_format(p_dataItem, p_aliasName);
		<?php 
		if($send_admin_mail=='' && $telegram_bot_name=='') {
			$msg = '사이트설정에 의해 알림기능이 제공되지 않습니다.';
		} else if($send_admin_mail!='' && $telegram_bot_name=='') {
			$msg = '사이트설정에 의해 이메일 알림기능을 사용하실 수 있습니다.';
		} else if($send_admin_mail=='' && $telegram_bot_name!='') {
			$msg = '사이트설정에 의해 텔레그램 알림기능을 사용하실 수 있습니다.';
		} else {
			$msg = '사이트설정에 의해 이메일 또는 텔레그램 알림기능을 모두 사용할 수 있습니다.';
		}
		?>
		rValue = '<?php echo $msg; ?>';
        return rValue;
    } else {
        return p_dataItem[p_aliasName];
    }

}

//아래의 함수는 목록에서만 해당되며, 템플릿으로 정의하지 않아도 특정항목의 값이나 태그를 추가할 수 있습니다. 
function rowFunction_UserDefine(p_this) {
    if(p_this.table_Station_NewNumQnAutoGubun!=null) {
        p_this.table_Station_NewNumQnStationName = ":".repeat(p_this.table_Station_NewNumQnAutoGubun.length-2) + p_this.table_Station_NewNumQnStationName; 
    }
}

<?php 
//아래는 해당프로그램의 관리자 권한을 가진 사용자가 목록 조회 시에 적용되는 내용입니다.			
if($MisSession_IsAdmin=="Y" && $ActionFlag=="list") { 
?>

//툴바 명령버튼에 btn_1 이라고 하는 예비버튼을 "적용" 이라는 기능을 넣고, 클릭 시, 그리드에 app="적용" 이라는 신호로 보냅니다.
//이때 app=="적용" 에 대한 처리는 list_json_init() 를 참조하세요.
function thisLogic_toolbar() {
/*
    $("a#btn_1").text("적용");
    $("li#btn_1_overflow").text("적용");
    $("#btn_1").css("background", "#88f");
    $("#btn_1").css("color", "#fff");
    $("#btn_1").click( function() {
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "적용";
        $("#grid").data("kendoGrid").dataSource.read();
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "";
    });
*/
}
<?php } ?>

//아래는 그리드의 로딩이 거의 끝난 후, 항목에 스타일 시트를 적용하는 예입니다. 스타일 등을 직접 변경하려면 이 함수를 이용해야 합니다.
function rowFunctionAfter_UserDefine(p_this) {
/*
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
*/
}
			
function viewLogic_afterLoad_continue() {
		
	if($('input#ActionFlag').val()=='modify') {
		if(Left(resultAll['d']['results'][0]['zAllPushReceiveGroup'],3)=='소속됨') {
			$('div.before_round_allPush_YN, div#round_allPush_YN').css('display', 'inline-block');
		} else {
			$('div.before_round_allPush_YN, div#round_allPush_YN').css('display', 'none');
		}
	}
}			
			
		
			
			
        </script>
        <?php 
}
//end pageLoad



function save_writeAfter() {

    global $base_root, $RealPid, $MisJoinPid, $logicPid, $parent_idx, $full_siteID;
    global $key_aliasName, $key_value, $saveList, $saveUploadList, $viewList, $deleteList;
    global $Grid_Default, $ActionFlag, $MisSession_UserID, $newIdx;
    global $afterScript, $MS_MJ_MY;

    if($MS_MJ_MY=='MY') {
        $appSql = "call MisUser_Authority_Proc ('" . $full_siteID . "','speedmis000001'); ";
    } else {
        $appSql = "exec MisUser_Authority_Proc '" . $full_siteID . "','speedmis000001' ";
    }
  	execSql($appSql);

	setcookie("newLogin", "Y", 0, "/");

}
//end save_writeAfter



function addLogic_treat() {

	global $MisSession_UserID,$full_site,$DbPW;
	
    //addLogic_treat 함수는 ajax 로 요청되어진(url 형식) 것에 대한 출력문입니다. echo 등으로 출력내용만 표시하면 됩니다.
	//아래는 url 에 동반된 파라메터의 예입니다.
	//해당 예제 TIP 의 기본폼에 보면 addLogic_treat 를 호출하는 코딩이 있습니다.

    $userid = requestVB("userid");
    $username = requestVB("username");
    $expireday = requestVB("expireday");

	$info = $userid . ';@;' . $username . ';@;' . $expireday;

	$info_encode = misEncrypt($info,$DbPW,$DbPW);
	$link = $full_site . '/_mis/login/new_auth.php?' . $info_encode;

	echo $link;

}
//end addLogic_treat

?>