<?php

function pageLoad() {

    global $actionFlag,$paidKey_ucount,$full_siteID;
	global $misSessionIsAdmin, $real_pid, $MenuType, $idx;

        ?>
<style>
	
	body[actionflag="list"] .k-grid tbody .k-button {
        min-width: auto;
	}
    a#btn_write,a.depth0,a.depth3,a.zhawiAPP0 {
        display: none;
    }
    <?php if($actionFlag=="modify" || $actionFlag=="view") { ?>
    a#btn_delete {
        display: none;
    }
    <?php } ?>

</style>
        <script>
			/*
			if($('a.TK-TLRK-Logo')[0]) {
				tt = new Date().getTime();
				rnd_color = 'rgb('+(tt % 255)+','+((tt % 3)*120)+','+(255 - tt % 254)+')';
				$('a.TK-TLRK-Logo').css('border','1px solid '+rnd_color);
				$('a.TK-TLRK-Logo')[0].title = '좌측메뉴을 열때마다 로고테두리 색상이 바뀌지 않으면 SPA 기술이 적용된 것입니다';
			}
			*/
function applyDownAuth(p_this, p_idx) {
    if(!confirm(getGridCellValue_idx(p_idx,'MenuName')+' 메뉴 하위의 '+getGridCellValue_idx(p_idx,'zhawiAPP')
    +'개 메뉴의 권한도 \n'+getGridCellValue_idx(p_idx,'table_new_gidxQngname')+' | '+getGridCellValue_idx(p_idx,'table_AuthCodeQnkname')
    +'\n로 바꾸시겠습니까?')) return false;
    $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "권한전달."+p_idx;
    $("#grid").data("kendoGrid").dataSource.read();
    $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "";
}

function addMenu(p_this) {
    url = 'index.php?RealPid=speedmis001170&idx='+$(p_this).attr('idx')+'&ActionFlag=write';
	if(document.getElementById('ActionFlag').value=='list') {
    	parent_popup_jquery(url, getGridCellValue_idx($(p_this).attr('idx'),'MenuName') + ' 에 대한 ', 850, 600);
	} else {
    	parent_popup_jquery(url, $('#MenuName[data-bind]').smartVal() + ' 에 대한 ', 850, 600);
	}
}
function columns_templete(p_dataItem, p_aliasName) {
    
    if(p_aliasName=="RealPid") {
		var rValue = "<a href='index.php?RealPid=" + p_dataItem["RealPid"] + "&isMenuIn=Y' target='_blank' class='k-button'>연결</a>";
		if(p_dataItem["MenuType"]=="01") {
			rValue = rValue + "<a id='aid_" + p_dataItem["idx"] + "' href='index.php?RealPid=speedmis000266&idx=" 
				+ p_dataItem["RealPid"] + "&isMenuIn=Y' target='_blank' class='k-button'>소스</a>";
		} else if(p_dataItem["MenuType"]=="22") {
			rValue = rValue + "<a id='aid_" + p_dataItem["idx"] + "' href='index.php?RealPid=speedmis000989&idx=" 
				+ p_dataItem["RealPid"] + "&isMenuIn=Y' target='_blank' class='k-button'>소스</a>";
        }
        if(InStr(p_dataItem['zsangwimenyubyeolbogi'],'Global Admin')==0) {
            rValue = rValue + "<a href='javascript:;' onclick='addMenu(this);' idx='"+p_dataItem["idx"]+"' class='k-button'>추가</a>";
        }
		rValue = rValue + p_dataItem["RealPid"];
        return rValue;
    } else if(p_aliasName=="zgwonhanjeondal") {
		var rValue = "<a href='javascript:;' onclick='applyDownAuth(this,"+p_dataItem["idx"] + ");' class='k-button depth"+p_dataItem["depth"]+" zhawiAPP"+p_dataItem["zhawiAPP"]+"'>적용"+p_dataItem["zhawiAPP"]+"</a>";
		return rValue;
    } else {
        return p_dataItem[p_aliasName];
    }
}

//사용자 정의 함수 = 함수 이름은 변형하면 안됨. 내용만. 없어도 됨. ==============================
//데이타의 변형은 즉시 가능 = rowFunction

			
function listLogic_afterLoad_once()	{
	$('a#btn_menuName')[0].title = $('a#btn_menuName')[0].title + ', MIS Ver.'+$('script#script_user_define')[0].src.split('?')[1];
}			

function rowFunction_UserDefine(p_this) {
	if(p_this.AutoGubun!=null) {
		p_this.table_upRealPidQnMenuName = Left(p_this.AutoGubun, p_this.AutoGubun.length-2) + " " + p_this.table_upRealPidQnMenuName;
    }
    //p_this.MenuName = p_this.depth + p_this.MenuName; 
    //alert(p_this.MenuName)
    //p_this.AutoGubun = 
    //"<a href=index.php?gubun=" + p_this.idx + "&isMenuIn=Y target=_blank>[Go]</a> <a id='aid_" + p_this.idx + "' href=index.php?RealPid=speedmis000266&idx=" + p_this.idx + "&isMenuIn=Y target=_blank>[Source]</a>?" 
    //+ p_this.AutoGubun; 
}
<?php if($misSessionIsAdmin=="Y" && $actionFlag=="list") { ?>
    function thisLogic_toolbar() {
	
    $("a#btn_1").text("권한 및 메뉴적용");
    $("li#btn_1_overflow").text("권한 및 메뉴적용");
    $("#btn_1").css("background", "#88f");
    $("#btn_1").css("color", "#fff");
    $("#btn_1").click( function() {
		<?php if($paidKey_ucount*1<5) { ?>
			if(!confirm("비구매 고객의 경우, gadmin / admin 포함하여 20개 유저 외에는 삭제될 수 있으며, 웹소스관리에 추가된 20개 프로그램 외에는 삭제될 수 있습니다. 진행할까요?")) return false; 
		<?php } else if($paidKey_ucount*1<500) { 
			$cnt_user = onlyOneReturnSql("select COUNT(*) from MisUser where delchk<>'D'");
			$cnt_app = (int)onlyOneReturnSql("select COUNT(*) from MisMenuList where useflag=1 and MenuType='01' and idx>=1312 and RealPid not like 'speedmis%' and idx not in (select top 20 idx from MisMenuList where useflag=1 and MenuType='01' and idx>=1312 and RealPid not like 'speedmis%' order by idx)");

			if($cnt_user>100 || $cnt_app>100) {
			?>
			if(!confirm("스탠다드버전의 경우, gadmin / admin 포함하여 100개 유저 외에는 삭제될 수 있으며, 웹소스관리에 추가된 100개 프로그램 외에는 삭제될 수 있습니다. 진행할까요?")) return false; 
		<?php 
			} else {
			?>
			if(!confirm("스탠다드버전 사용 고객님, 현재 유저수 <?php echo $cnt_user; ?>/100개, 프로그램 <?php echo $cnt_app; ?>/100개 사용중입니다. 확인을 누르시면 권한이 적용됩니다.")) return false; 
		<?php 
			}
			
		} ?>
        $('a#btn_1').css('pointer-events', 'none');
        $('a#btn_1').css('opacity', '0.5');
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "적용";
        $("#grid").data("kendoGrid").dataSource.read();
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "";
    });
}
<?php } ?>

function thisLogic_view_after_resultAll(p_resultAll) {
<?php if($actionFlag=="modify") { ?>
  if('<?php echo $full_siteID; ?>'=='speedmis.com' || (Left(p_resultAll.d.results[0].RealPid,8)+p_resultAll.d.results[0].MenuType!='speedmis01' && Left(p_resultAll.d.results[0].RealPid,8)+p_resultAll.d.results[0].MenuType!='speedmis22')) {
	$('div#round_help_update_deny').css('display','none');   
  } else {
	$('div#round_help_update_deny').css('display','block');   
  }
<?php } ?>
<?php if($actionFlag=="modify" || $actionFlag=="view") { ?>
    
    setTimeout( function() {
        if(InStr($('div#round_zsangwimenyubyeolbogi')[0].innerText,'Global Admin')>0) {
                $('a#btn_delete').css('display','none');
        } else {
            $('a#btn_delete').css('display','inline-block');
        }
    },0);
    
<?php } ?>
}			

			
			
//스타일 등의 변형은 로딩후에 가능 = rowFunctionAfter
function rowFunctionAfter_UserDefine(p_this) {

	if(p_this.AutoGubun==null) {
		
	} else if(p_this.AutoGubun.length==6) {
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

    if(InStr(p_this.zsangwimenyubyeolbogi,'Global Admin')>0) {
        $('td[keyidx="'+p_this.idx+'"] > input')[0].disabled = true;
    }

}      
        </script>
        <?php 
}
//end pageLoad



function list_json_init() {
	global $real_pid, $misJoinPid, $logicPid, $parent_idx, $full_siteID, $MS_MJ_MY;
    global $flag, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript, $base_domain;

    if($flag=="read") { 
        if($app=="적용") {

            if($MS_MJ_MY=='MY') {
                $appSql = "call MisUser_Authority_Proc('$full_siteID','speedmis000001');";
                
            } else {
                $appSql = "EXECUTE MisUser_Authority_Proc '$full_siteID','speedmis000001'; ";
                
            }
            
            if(execSql($appSql)) {
                $resultCode = "success";
                $resultMessage = "권한 및 메뉴변경사항의 적용이 완료되었습니다.";
                $afterScript = "document.treatForm.nmCommand.value = 'menuRefresh';document.treatForm.submit();";
            } else {
                $resultCode = "fail";
                $resultMessage = "권한적용 처리가 실패하였습니다.";
            }
        }

        if(Left($app,4)=="권한전달") {
            $p_idx = splitVB($app,'.')[1];

            if($MS_MJ_MY=='MY') {
                $appSql = "
        select @AutoGubun:=AutoGubun, @new_gidx:=new_gidx, @AuthCode:=AuthCode from MisMenuList where idx=$p_idx;
        update MisMenuList set new_gidx=@new_gidx, AuthCode=@AuthCode where length(AutoGubun)>length(@AutoGubun) and left(AutoGubun, length(@AutoGubun))=@AutoGubun and useflag=1;
        call MisUser_Authority_Proc ('$full_siteID','speedmis000001');
                ";
            } else {
                $appSql = "
                declare @AutoGubun nvarchar(10), @new_gidx int, @AuthCode nvarchar(10)
        select @AutoGubun=AutoGubun, @new_gidx=new_gidx, @AuthCode=AuthCode from MisMenuList where idx=$p_idx
        update MisMenuList set new_gidx=@new_gidx, AuthCode=@AuthCode where len(AutoGubun)>len(@AutoGubun) and left(AutoGubun, len(@AutoGubun))=@AutoGubun and useflag=1
        exec MisUser_Authority_Proc '$full_siteID','speedmis000001' 
                ";
            }
            if(execSql($appSql)) {
                $resultCode = "success";
                $resultMessage = "하위메뉴도 동일하게 권한이 적용되었습니다.";
            } else {
                $resultCode = "fail";
                $resultMessage = "하위메뉴 권한처리가 실패하였습니다.";
            }
        }
    }
}
//end list_json_init



function save_updateQueryBefore() {

	global $sql, $sql_prev, $sql_next, $key_value;
	global $result, $updateList, $upload_idx, $MS_MJ_MY;

	//아래는 업데이트 쿼리에 특정쿼리를 더 추가합니다.

	if($MS_MJ_MY=='MY') {
	} else {
		//spa 이슈로 인해 수정만 되어도 메뉴새로고침해야함.(추가쿼리때문)
		$sql = $sql . " 
if exists (select * from sysobjects where name='MisUser' and xtype='U') begin
	exec('update dbo.MisUser set menuRefresh=''Y'', menuRefreshApp=''Y''')
end
		";
	}
	


}
//end save_updateQueryBefore



function save_writeBefore() {

    global $full_siteID, $base_root, $real_pid, $misJoinPid, $logicPid, $parent_idx;
    global $key_aliasName, $key_value, $actionFlag, $updateList;

	$NewRealPid = onlyOnereturnSql("select '" . $full_siteID . "' + dbo.formatnums(IDENT_CURRENT('MisMenuList') + 1, '000000') ");
    $updateList["RealPid"] = $NewRealPid;

	$AutoGubun = onlyOnereturnSql("select AutoGubun from MisMenuList where RealPid='" . $updateList["upRealPid"] . "'");
    $updateList["AutoGubun"] = $AutoGubun . "99";
    $updateList["SortG2"] = Left($AutoGubun,2);

	if(Len($AutoGubun)==2) {
		$updateList["SortG4"] = 99;
		$updateList["SortG6"] = 0;
	} else {
		$updateList["SortG4"] = Mid($AutoGubun,3,2);
		$updateList["SortG6"] = 99;
    }

	//print_r($updateList);
	//exit;

}
//end save_writeBefore



function save_writeAfter() {

    global $base_root, $real_pid, $misJoinPid, $logicPid, $parent_idx, $full_siteID, $MS_MJ_MY;
    global $key_aliasName, $key_value, $saveList, $saveUploadList, $viewList, $deleteList;
    global $Grid_Default, $actionFlag, $misSessionUserId, $newIdx;
    global $afterScript;

    if($MS_MJ_MY=='MY') {
        $appSql = "call MisUser_Authority_Proc ('$full_siteID','speedmis000001') ";
    } else {
        $appSql = "exec MisUser_Authority_Proc '$full_siteID','speedmis000001' ";
    }
  	execSql($appSql);

	setcookie("newLogin", "Y", 0, "/");

}
//end save_writeAfter



function save_deleteBefore() {

    global $full_siteID, $base_root, $real_pid, $misJoinPid, $logicPid, $parent_idx;
    global $key_aliasName, $key_value, $actionFlag, $sql_prev, $sql, $sql_next, $deleteList, $MS_MJ_MY;

    if($MS_MJ_MY=='MY') {
        $sql_next = "call MisUser_Authority_Proc ('$full_siteID','speedmis000001') ";
    } else {
        $sql_next = "exec MisUser_Authority_Proc '$full_siteID','speedmis000001' ";
    }
    
    setcookie("newLogin", "Y", 0, "/");
}
//end save_deleteBefore



function textUpdate_sql() {

    global $strsql, $keyAlias, $keyValue, $thisValue, $oldText, $thisAlias, $resultCode, $resultMessage, $afterScript, $MS_MJ_MY;

	//아래는 특정항목을 수정할 경우, 해당항목이 정의된 리스트에 포함되었을 경우, 관련 업데이트문을 추가하고, 처리메세지를 브라우저로 전달하는 로직입니다.  
    if($thisAlias=='AddURL') {
		if($MS_MJ_MY=='MY') {
		} else {
			//spa 이슈로 인해 수정만 되어도 메뉴새로고침해야함.(추가쿼리때문)
			$strsql = $strsql . " 
	if exists (select * from sysobjects where name='MisUser' and xtype='U') begin
		exec('update dbo.MisUser set menuRefresh=''Y'', menuRefreshApp=''Y''')
	end
			";
		}
    }

}
//end textUpdate_sql



function addLogic_treat() {
	
    global $misSessionUserId;
    
    //addLogic_treat 함수는 ajax 로 요청되어진(url 형식) 것에 대한 출력문입니다. echo 등으로 출력내용만 표시하면 됩니다.
    //아래는 url 에 동반된 파라메터의 예입니다.
    //해당 예제 TIP 의 기본폼에 보면 addLogic_treat 를 호출하는 코딩이 있습니다.

    $question = requestVB("question");
    $v = requestVB("v");
    

    
    if($question=="pid_name") {
        $sql = " select menuName from MisMenuList where RealPid='$v' ";
        echo onlyOnereturnSql($sql);
    }

}
//end addLogic_treat

?>