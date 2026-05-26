<?php

/*info

작성자 : 


주요기능 : 
*/

//선언문 영역
//$plist = ['John','Piter'];
//$list_numbering = '';		//리스트에서 No. 순번항목을 없앰.
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
use Firebase\JWT\JWT;

/*

특이사항 : 


참고url : 


info*/



function pageLoad() {

    global $actionFlag, $MS_MJ_MY, $full_siteID;

        ?>
        <script>

$('body').attr('onlylist','');
			
function update_RealPid(p_this, p_RealPid, p_updateVersion) {
	displayLoading();
	p_this.style.pointerEvents = "none";
	if(location.host=='www.speedmis.com') {
		url = "/_mis/addLogic_treat.php?RealPid="+$('#RealPid').val()+"&targetID="+p_RealPid;
		p_updateVersion = ajax_url_return2(url);
	}
	update_program_url = "misShare_models_save.php?RealPid="+p_RealPid+"&MisJoinPid=&key_aliasName=RealPid&updateVersion=<?php echo iif($MS_MJ_MY=='MY','MY_',''); ?>"+p_updateVersion;

	ifr_id = "ifr_"+Math.floor(Math.random()*10000000000000000);
	$('body').append('<iframe id="'+ifr_id+'" style="display:none;"></iframe>');
	$('iframe#'+ifr_id)[0].src = update_program_url;

}

function columns_templete(p_dataItem, p_aliasName) {
    if(p_aliasName=="zrokeolbeojeommicheopdeiteu") {
        localVersion = ajax_url_return2("/_mis_addLogic"+"/updateVersion/updateVersion_"+p_dataItem["RealPid"]+".txt");
		
		localVersion = replaceAll(localVersion,'MY_','');
        if(isNumeric(localVersion)==false) localVersion = 0;
        
		if(p_dataItem["updateVersion"] != localVersion) {
			if('<?php echo $full_siteID; ?>'=='speedmy' && p_dataItem["updateVersion"] < localVersion) {
				rValue = localVersion + '<a onclick="update_RealPid(this, \''+p_dataItem["RealPid"]+'\',\''+localVersion+'\');" style="margin-left:10px;" class="k-button">업데이트</a>';
			} else {
				rValue = localVersion + '<a onclick="update_RealPid(this, \''+p_dataItem["RealPid"]+'\',\''+p_dataItem["updateVersion"]+'\');" style="margin-left:10px;" class="k-button">업데이트</a>';
			}
		} else {
			rValue = localVersion + '<a onclick="update_RealPid(this, \''+p_dataItem["RealPid"]+'\',\''+p_dataItem["updateVersion"]+'\');" style="margin-left:10px;" class="k-button">완료됨</a>';
		}
		rValue = rValue + '<a target="_blank" href="index.php?RealPid='+p_dataItem['RealPid']+iif(getID('isMenuIn').value=='Y','&isMenuIn=Y','')+'" style="margin-left:10px;" class="k-button">연결</a>';
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
}
<?php if($actionFlag=='list') { ?>
function thisLogic_toolbar() {

	$("a#btn_1").text("일괄 업데이트 및 필수쿼리 반영");
    $("li#btn_1_overflow").text("일괄 업데이트 및 필수쿼리 반영");
    $("#btn_1").css("background", "#88f");
    $("#btn_1").css("color", "#fff");
    $("#btn_1").click( function() {

		$('div#grid a.k-button[onclick]:contains(업데이트)').each( function() {
			$(this).click();
			//DoEvents();
		});
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "일괄업데이트";
        $("#grid").data("kendoGrid").dataSource.read();
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "";
	});

	autoUpdate = ajax_url_return2('/_mis_addLogic/updateVersion/autoUpdate.txt');

	if(autoUpdate=='Y') {
		$("a#btn_2").text("자동업데이트 끄기");
		$("li#btn_2_overflow").text("자동업데이트 끄기");
		$("#btn_2").css("background", "#4aa");
		$("#btn_2").css("color", "#fff");
		$("#btn_2").click( function() {
			url = "addLogic_treat.php?RealPid="+$('#RealPid').val()+"&autoUpdate=N";
			ajax_url_touch(url);
			setTimeout( function() {
				location.href = location.href;
			},500);
		});
	} else {
		$("a#btn_2").text("자동업데이트 켜기");
		$("li#btn_2_overflow").text("자동업데이트 켜기");
		$("#btn_2").css("background", "#aa4");
		$("#btn_2").css("color", "#fff");
		$("#btn_2").click( function() {
			url = "addLogic_treat.php?RealPid="+$('#RealPid').val()+"&autoUpdate=Y";
			ajax_url_touch(url);
			setTimeout( function() {
				location.href = location.href;
			},500);
		});
	}



}


function listLogic_afterLoad_continue() {
	if(getUrlParameter('openUpdate')=='Y' && $("a#btn_1").attr('openUpdate')!='end') {
		$("a#btn_1").attr('openUpdate','end');
		$("a#btn_1").click();
	}
	speedmis_update();
}
<?php } ?>
        </script>
        <?php 
}
//end pageLoad



function list_json_init() {
    global $real_pid, $misJoinPid, $logicPid, $parent_idx, $full_site, $full_siteID, $MS_MJ_MY, $addDir;
    global $flag, $selField, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript;



	//$flag 는 목록조회시 'read'   내용조회시 'view'    수정시 'modify'   입력시 'write'
	//$selField 는 필터링을 하는 순간 발생하는 필드alias 값.

	//아래의 예제는 자동정렬이라는 명령을 받았을 경우, 목록이 생성되기 전에 숫자를 정렬하는 기능입니다.

    if($flag=='read' && $selField=='') { 
        if($app=='일괄업데이트') {

			if($MS_MJ_MY=='MY') $addDir = 'MY'; else $addDir = '';
			$update_check = file_get_contents_new($full_site . '/_mis_addLogic/updateVersion/updateVersion_' . 'last.txt');
			if(is_numeric($update_check)) {
				$presql_url = 'https://www.speedmis.com/_mis/misShare_presql_create.php?remote_MS_MJ_MY=' . $MS_MJ_MY . '&t=' . $update_check;
				$presql = Trim(file_get_contents_new($presql_url));

				if(ord(Left($presql,1))==239) $presql = Mid($presql, 2, 999999);
				//misShare_presql_create.php 에러가 안났을 경우 실행.
				if(InStr($presql,'misShare_presql_create')==0 && $presql!='') {
					$presql = JWT::decode($presql, 'presql', array('HS256'));
					$apply_full_siteID_YN = "N";
					if(function_exists("apply_full_siteID_YN")) {
						apply_full_siteID_YN();
					}

					if($apply_full_siteID_YN=="Y") {
						$presql = apply_full_siteID($presql);
					}

					if(Len($presql)>0) {
						for($i=0;$i<count(splitVB($presql,';;;'));$i++) {
							$sql = Trim(splitVB($presql,';;;')[$i]);
							if($sql!='') execSql($sql);
						}
					}
				}
			}
			if($MS_MJ_MY=='MY') {
                $appSql = "call MisUser_Authority_Proc('$full_siteID','speedmis000001');";
                
            } else {
                $appSql = "EXECUTE MisUser_Authority_Proc '$full_siteID','speedmis000001'; ";
                
            }
			execSql($appSql);

        }
    }

}
//end list_json_init



function addLogic_treat() {

	global $misSessionUserId, $base_root, $MS_MJ_MY, $addDir;
	
    //addLogic_treat 함수는 ajax 로 요청되어진(url 형식) 것에 대한 출력문입니다. echo 등으로 출력내용만 표시하면 됩니다.
	//아래는 url 에 동반된 파라메터의 예입니다.
	//해당 예제 TIP 의 기본폼에 보면 addLogic_treat 를 호출하는 코딩이 있습니다.

	$autoUpdate = requestVB("autoUpdate");
	$targetID = requestVB("targetID");

	if($autoUpdate!='') {
		$autoUpdate_destination = $base_root . "/_mis_addLogic/updateVersion/autoUpdate.txt";
		WriteTextFile($autoUpdate_destination, $autoUpdate);
		echo $autoUpdate;
	} else if($targetID!='') {
		
		if($MS_MJ_MY=='MY') {
			$sql = "select DATE_FORMAT(NOW(), '%Y%m%d%H%i');";
		} else {
			$sql = "select replace(replace(replace(convert(char(16), getdate(), 120),'-',''),':',''),' ','')";
		}
		$new_updateVersion = onlyOnereturnSql($sql);
		//아래는 값에 따라 mysql 서버를 통해 알맞는 값을 출력하여 보냅니다.
	
		$sql = " update MisShare set updateVersion='$new_updateVersion' where RealPid='$targetID' ";
		execSql($sql);
		echo $new_updateVersion;
	}
	


}
//end addLogic_treat



function jsonUrl_index() {

	global $jsonUrl, $MS_MJ_MY, $full_site;
	if($MS_MJ_MY=='MY') {
		//if($full_site=='http://mysql.speedmis.com') $jsonUrl = "http://mysql.speedmis.com/_mis/";
		$jsonUrl = "https://speedmismy.mycafe24.com/_mis/";
	} else {
		$jsonUrl = "https://www.speedmis.com/_mis/";
	}
}
//end jsonUrl_index

?>