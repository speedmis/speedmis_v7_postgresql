<?php


function list_json_init() {
    global $real_pid, $misJoinPid, $logicPid, $parent_idx, $isnull;
    global $MS_MJ_MY, $flag, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript;
    if($flag=="read") { 

        if($app=="번호정렬") {
            
                $appSql = "
                DECLARE @heading nvarchar(200) 
                DECLARE @pre_heading nvarchar(200)  
                DECLARE @idx int
                DECLARE @sortnum int
                DECLARE @ii int
            
                set @pre_heading = ''
                DECLARE MisApp_CUR CURSOR FOR  
                select table_m.idx
,table_m.heading
,table_m.sortnum
from MisHelp table_m
where table_m.useflag='1'
order by heading, sortnum, title, idx
            
                Open MisApp_CUR
                FETCH NEXT FROM MisApp_CUR INTO @idx, @heading, @sortnum
                WHILE @@FETCH_STATUS = 0 
                BEGIN    
                    set @ii = @ii+1
                    if(@pre_heading <> @heading) set @ii = 1
                    update MisHelp set sortnum=@ii where idx=@idx
                
                    set @pre_heading = @heading
                FETCH NEXT FROM MisApp_CUR INTO @idx, @heading, @sortnum
                END
            
                CLOSE MisApp_CUR
                DEALLOCATE MisApp_CUR      

                ";
  

            if(execSql($appSql)) {
                $resultCode = "success";
                $resultMessage = "번호정렬이 완료되었습니다.";
                //$_SESSION["newLogin"] = "Y";
                //$afterScript = "location.href = replaceAll(location.href, '#', '');";
            } else {
                $resultCode = "fail";
                $resultMessage = "처리가 실패하였습니다.";
            }

        }
   }
}
//end list_json_init


function pageLoad() {

    global $actionFlag;
	global $misSessionIsAdmin;

/*
	//특정상황에서 페이지를 이동시키는 예제입니다.
	if($actionFlag=="list" && $parent_RealPid=="speedmis000028") {
		$target_parent_gubun = RealPidIntoGubun("speedmis001071");
		$url = "index.php?RealPid=$real_pid&parent_gubun=$target_parent_gubun&parent_idx=$parent_idx";
		re_direct($url);
	}
*/

        ?>
        <script>

//웹소스 디테일에서 템플릿으로 체크한 항목에 대해 출력내용을 변경할 수 있습니다. 이때 목록 또는 본문내용에 동일하게 적용됩니다.
function columns_templete(p_dataItem, p_aliasName) {

    if(p_aliasName=="table_mQmyoutubeCode") {
		if(p_dataItem['youtubeCode']!='' && p_dataItem['youtubeCode']!=undefined) {
			var rValue = "<a href='https://www.youtube.com/watch?v=" + p_dataItem["youtubeCode"] + "' target='_blank' class='k-button'>Go</a>";
			return rValue;
		} else return '';
    } else {
        return p_dataItem[p_aliasName];
    }

}

//아래의 함수는 목록에서만 해당되며, 템플릿으로 정의하지 않아도 특정항목의 값이나 태그를 추가할 수 있습니다. 
function rowFunction_UserDefine(p_this) {
/*
	p_this.MenuName = p_this.depth + p_this.LanguageCode; 
    p_this.AutoGubun = 
    "<a href=index.asp?gubun=" + p_this.idx + "&isMenuIn=Y target=_blank>[Go]</a> <a id='aid_" + p_this.idx + "' href=index.asp?RealPid=speedmis000266&idx=" + p_this.idx + "&isMenuIn=Y target=_blank>[Source]</a> " 
    + p_this.AutoGubun;
*/
}

<?php 
//아래는 해당프로그램의 관리자 권한을 가진 사용자가 목록 조회 시에 적용되는 내용입니다.			
if($misSessionIsAdmin=="Y" && $actionFlag=="list") { 
?>

function thisLogic_toolbar() {
    $("#btn_1").text("번호정렬");
    $("#btn_1").css("background", "#88f");
    $("#btn_1").css("color", "#fff");
    $("#btn_1").click( function() {
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "번호정렬";
        $("#grid").data("kendoGrid").dataSource.read();
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "";
    });
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
			
			
			
		
			
			
        </script>
        <?php 
}
//end pageLoad

?>