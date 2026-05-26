<?php

function pageLoad() {

    global $actionFlag;
	global $misSessionIsAdmin;

    if($actionFlag=="list") { 
        ?>
    <style>
/*
label#toolbar_zgwallihaljigwonseongmyeongID_label {
    background: blue;
    color: yellow;
}

span[aria-owns="toolbar_zgwallihaljigwonseongmyeongID_listbox"] span.k-input {
    background: yellow;
}
span[aria-owns="toolbar_zgwallihaljigwonseongmyeongID_listbox"] {
    display: inline-block;
    min-width: 130px;
}
*/

    </style>        
        <script>

//사용자 정의 함수 = 함수 이름은 변형하면 안됨. 내용만. 없어도 됨. ==============================
//데이타의 변형은 즉시 가능 = rowFunction

function rowFunction_UserDefine(p_this) {
    //p_this.MenuName = p_this.depth + p_this.LanguageCode; 
    //p_this.AutoGubun = 
    //"<a href=index.asp?gubun=" + p_this.idx + "&isMenuIn=Y target=_blank>[Go]</a> <a id='aid_" + p_this.idx + "' href=index.asp?RealPid=speedmis000266&idx=" + p_this.idx + "&isMenuIn=Y target=_blank>[Source]</a>?" 
    //+ p_this.AutoGubun; 
}<?php if($misSessionIsAdmin=="Y") { ?>
function thisLogic_toolbar() {

    $("a#btn_1").text("적용");
    $("li#btn_1_overflow").text("적용");
    $("#btn_1").css("background", "#88f");
    $("#btn_1").css("color", "#fff");
    $("#btn_1").click( function() {
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "적용";
        $("#grid").data("kendoGrid").dataSource.read();
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "";
    });

	$("#btn_alert").css("display", "block");
	$("#btn_alert").css("background", "yellow");
    $("#btn_alert").click( function() {
        toastr.error("수정권한 항목을 바꾸면 즉시 적용됩니다.<br>권한 관리할 직원을 먼저 선택하시면 편리합니다.", "", {timeOut: 10000, positionClass: "toast-top-right"});
    });

    /*
    $("a#btn_1").text("권한적용");
    $("li#btn_1_overflow").text("권한적용");
    $("#btn_1").css("background", "#88f");
    $("#btn_1").css("color", "#fff");
    $("#btn_1").click( function() {
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "권한적용";
        $("#grid").data("kendoGrid").dataSource.read();
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "";
    });
    */
}
<?php } ?>

//스타일 등의 변형은 로딩후에 가능 = rowFunctionAfter
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

    $(getCellObj_idx(p_this[key_aliasName], "AutoGubun"))[0].innerHTML = 
    "<a href=index.php?gubun=" + p_this.idx + "&isMenuIn=Y target=_blank>[Go]</a>"+iif(p_this.MenuType=="01", "<a id='aid_" + p_this.idx + "' href=index.php?RealPid=speedmis000266&idx=" + p_this.idx + "&isMenuIn=Y target=_blank>[Source]</a>","")
    + "?" + p_this.AutoGubun; 
*/
}      
        </script>
        <?php 
    }
}
//end pageLoad



function list_json_init() {
    global $real_pid, $misJoinPid, $logicPid, $parent_idx, $full_siteID, $MS_MJ_MY;
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
                $resultMessage = "권한 및 메뉴변경사항의 적용이 완료되었습니다.";
                $afterScript = "document.treatForm.nmCommand.value = 'menuRefresh';document.treatForm.submit();";
            } else {
                $resultCode = "fail";
                $resultMessage = "권한적용 처리가 실패하였습니다.";
            }
        }
    }
}
//end list_json_init



function list_query() {
    global $real_pid, $misJoinPid, $logicPid, $parent_idx, $MS_MJ_MY;
    global $flag, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript;
    global $countQuery, $selectQuery, $idx_aliasName;   //특정필드에 대한 검색이 있는 경우.
	global $selField, $selValue, $filter;
	
    if($selField=="zgwallihaljigwonseongmyeongID") { 
        //$countQuery = $countQuery . " and table_m.RealPid='" . $_GET["wherePid"] . "'";
        //$selectQuery = replace($selectQuery, ") aaa", " and table_m.RealPid='" . $_GET["wherePid"] . "') aaa");
		
		$whereSql = "";

        if($MS_MJ_MY=='MY') {
            if($selValue!="") {
                //$whereSql = " and concat(table_m.UserName,'/',table_m.UniqueNum) like N'%" . sqlValueReplace($selValue) . "%'";
            }
            $countQuery =  " select count(*)
    from MisUser table_m   
    left outer join MisStation table_Station_NewNum on table_Station_NewNum.Num = table_m.Station_NewNum    and table_Station_NewNum.useflag='1'   
    where table_m.delchk<>'D' " . $whereSql;
            $selectQuery =  " select concat(table_m.UserName,'/',table_m.UniqueNum) as 'zgwallihaljigwonseongmyeongID'
    from MisUser table_m   
    left outer join MisStation table_Station_NewNum on table_Station_NewNum.Num = table_m.Station_NewNum    and table_Station_NewNum.useflag='1'   
    where table_m.delchk<>'D' " . $whereSql . " order by 1; ";
        } else {
            if($selValue!="") {
                //$whereSql = " and table_m.UserName+'/'+table_m.UniqueNum like N'%" . sqlValueReplace($selValue) . "%'";
            }
            $countQuery =  " select count(*)
    from MisUser table_m   
    left outer join MisStation table_Station_NewNum on table_Station_NewNum.Num = table_m.Station_NewNum    and table_Station_NewNum.useflag='1'   
    where table_m.delchk<>'D' " . $whereSql;
            $selectQuery =  " select table_m.UserName+'/'+table_m.UniqueNum as 'zgwallihaljigwonseongmyeongID'
    from MisUser table_m   
    left outer join MisStation table_Station_NewNum on table_Station_NewNum.Num = table_m.Station_NewNum    and table_Station_NewNum.useflag='1'   
    where table_m.delchk<>'D' " . $whereSql . " order by 1 ";
        }

    } else if(InStr($filter, "toolbar_zgwallihaljigwonseongmyeongID eq")>0 && InStr($filter, " and ")==0) { 
        
        $userID =  splitVB(splitVB($filter,"'")[1],"/")[1];
        if($userID!="") {

            if($MS_MJ_MY=='MY') {
                $sql = "

set @userID = '$userID';
delete from MisMenuList_Member where userID not in (select uniquenum from MisUser where delchk<>'D')
or RealPid not in (select RealPid from MisMenuList where useflag=1);

delete from MisMenuList_UserAuth where userID not in (select uniquenum from MisUser where delchk<>'D')
or RealPid not in (select RealPid from MisMenuList where useflag=1);

select @cnt1:=count(table_m.idx)  FROM MisMenuList table_m
WHERE table_m.useflag='1';

select @cnt2:=count(*) from MisMenuList_UserAuth where userID=@userID and useflag=1;

delete from MisMenuList_UserAuth where userID=@userID and (useflag=0 or RealPid not in (
select table_m.RealPid  FROM MisMenuList table_m
WHERE table_m.useflag='1'
)) and @cnt1 <> @cnt2;

insert into MisMenuList_UserAuth (userID, RealPid, wdater)
select @userID,table_m.RealPid,'admin'  FROM MisMenuList table_m
WHERE table_m.useflag='1'
and table_m.RealPid not in (
select RealPid from MisMenuList_UserAuth where userID=@userID and useflag=1
) and @cnt1 <> @cnt2;

            ";
            } else {
                $sql = "
declare @cnt1 int
declare @cnt2 int
declare @userID nvarchar(50)
set @userID = '$userID'
delete MisMenuList_Member where userID not in (select uniquenum from MisUser where delchk<>'D')
or RealPid not in (select RealPid from MisMenuList where useflag=1)

delete MisMenuList_UserAuth where userID not in (select uniquenum from MisUser where delchk<>'D')
or RealPid not in (select RealPid from MisMenuList where useflag=1)

select @cnt1=count(table_m.idx)  FROM MisMenuList table_m
WHERE table_m.useflag='1'
select @cnt2=count(*) from MisMenuList_UserAuth where userID=@userID and useflag=1
if(@cnt1 <> @cnt2) begin

delete MisMenuList_UserAuth where userID=@userID and (useflag=0 or RealPid not in (
select table_m.RealPid  FROM MisMenuList table_m
WHERE table_m.useflag='1'
))

insert into MisMenuList_UserAuth (userID, RealPid, wdater)
select @userID,table_m.RealPid,'admin'  FROM MisMenuList table_m
WHERE table_m.useflag='1'
and table_m.RealPid not in (
select RealPid from MisMenuList_UserAuth where userID=@userID and useflag=1
)

end
            ";
            }


            
            execSql($sql);
        }

    }

}
//end list_query

?>