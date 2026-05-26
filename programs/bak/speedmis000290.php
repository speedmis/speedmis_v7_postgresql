<?php

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

    if(p_aliasName=="table_RealPidQnidx") {
		var rValue = "<a href='index.php?gubun=" + p_dataItem["table_RealPidQnidx"] + "&isMenuIn=Y' target='_blank' class='k-button'>Go</a>";
		rValue = rValue + "<a id='aid_" + p_dataItem["table_RealPidQnidx"] + "' href='index.php?RealPid=speedmis000266&idx=" 
			+ p_dataItem["table_RealPidQnRealPid"] + "&isMenuIn=Y' target='_blank' class='k-button'>소스</a>";
		rValue = rValue + p_dataItem["table_RealPidQnidx"];
        return rValue;
    } else {
        return p_dataItem[p_aliasName];
    }

}

//아래의 함수는 목록에서만 해당되며, 템플릿으로 정의하지 않아도 특정항목의 값이나 태그를 추가할 수 있습니다. 
function rowFunction_UserDefine(p_this) {
/*
	p_this.MenuName = p_this.depth + p_this.LanguageCode; 
    p_this.AutoGubun = 
    "<a href=index.asp?gubun=" + p_this.idx + "&isMenuIn=Y target=_blank>[Go]</a> <a id='aid_" + p_this.idx + "' href=index.asp?RealPid=speedmis000266&idx=" + p_this.idx + "&isMenuIn=Y target=_blank>[Source]</a>?" 
    + p_this.AutoGubun;
*/
}

<?php 
//아래는 해당프로그램의 관리자 권한을 가진 사용자가 목록 조회 시에 적용되는 내용입니다.			
if($misSessionIsAdmin=="Y" && $actionFlag=="list") { 
?>
alert('ADMIN 권한의 경우, 목록에 나타나는 각 프로그램에 대해 쉽게 편집이 가능하므로 각별히 주의하세요!'); 
	  
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
			
			
		
			
			
        </script>
        <?php 
}
//end pageLoad



function list_query() {

    global $real_pid, $misJoinPid, $logicPid, $parent_idx, $result, $selField;
    global $flag, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript;
    global $countQuery, $selectQuery, $idx_aliasName, $MS_MJ_MY, $whereSql  ;

	//아래는 어떤 특정한 상황에 대한 적용예입니다.
    if($MS_MJ_MY!='MY' && $flag=='read' && $selField=='') {

        $allDB = splitVB($countQuery, "and table_RealPid.useflag='1' and table_RealPid.MenuType = '01'")[0];

        $allDB = "
        select * from (
		select case when replace(exDB,' ','')='dbo' then '' else exDB end as exDB from (
        " . replace($allDB, "select count(*) from", "select distinct case when dbo.INSTR(0,table_RealPid.g08,'.')=0 or dbo.INSTR(0,table_RealPid.g08,'dbo.')=1 then ''
        else rtrim(left(replace(isnull(table_RealPid.g08,''),'.',REPLICATE(' ',100)),50)) end as exDB
        from") . "
        and table_RealPid.useflag='1') aa where exDB<>''
		) bb where exDB<>''
        ";

			//echo " ----  $allDB ----";exit;
        $allDB = allreturnSql($allDB);
        if(count($allDB)>0) {

            $search_index = array_search("table_mQmGrid_Columns_Title", array_column($result, 'aliasName'));
            $joinSql = $result[$search_index]["Grid_GroupCompute"];
            $joinSql0 = $joinSql;

            $selectQuery = replace($selectQuery, ",table_COLUMNS.DATA_TYPE as", ",case {repeat} else table_COLUMNS.DATA_TYPE end as");
            $selectQuery = replace($selectQuery, "and isnull(table_COLUMNS.DATA_TYPE,'')", "and case {repeat} else table_COLUMNS.DATA_TYPE end");
            $countQuery = replace($countQuery, "and isnull(table_COLUMNS.DATA_TYPE,'')", "and case {repeat} else table_COLUMNS.DATA_TYPE end");

            $selectQuery = replace($selectQuery, ",table_COLUMNS.CHARACTER_MAXIMUM_LENGTH as", ",case {repeat} else table_COLUMNS.CHARACTER_MAXIMUM_LENGTH end as");
            $selectQuery = replace($selectQuery, "and isnull(table_COLUMNS.CHARACTER_MAXIMUM_LENGTH,'')", "and case {repeat} else table_COLUMNS.CHARACTER_MAXIMUM_LENGTH end");
            $countQuery = replace($countQuery, "and isnull(table_COLUMNS.CHARACTER_MAXIMUM_LENGTH,'')", "and case {repeat} else table_COLUMNS.CHARACTER_MAXIMUM_LENGTH end");

            $selectQuery = replace($selectQuery, ",table_COLUMNS.COLUMN_DEFAULT as", ",case {repeat} else table_COLUMNS.COLUMN_DEFAULT end as");
            $selectQuery = replace($selectQuery, "and isnull(table_COLUMNS.COLUMN_DEFAULT,'')", "and case {repeat} else table_COLUMNS.COLUMN_DEFAULT end");
            $countQuery = replace($countQuery, "and isnull(table_COLUMNS.COLUMN_DEFAULT,'')", "and case {repeat} else table_COLUMNS.COLUMN_DEFAULT end");

            $selectQuery = replace($selectQuery, ",table_COLUMNS.IS_NULLABLE as", ",case {repeat} else table_COLUMNS.IS_NULLABLE end as");
            $selectQuery = replace($selectQuery, "and isnull(table_COLUMNS.IS_NULLABLE,'')", "and case {repeat} else table_COLUMNS.IS_NULLABLE end");
            $countQuery = replace($countQuery, "and isnull(table_COLUMNS.IS_NULLABLE,'')", "and case {repeat} else table_COLUMNS.IS_NULLABLE end");

            

            for($i=0;$i<count($allDB);$i++) {
                $joinSql = $joinSql . "\nleft outer join {exDB}." . replace($joinSql0, "table_COLUMNS", "table_COLUMNS" . ($i+1));

                $selectQuery = replace($selectQuery, "{repeat} else table_COLUMNS.DATA_TYPE", "when dbo.INSTR(0,table_RealPid.g08,'{exDB}.')>0 then table_COLUMNS{i}.DATA_TYPE {repeat} else table_COLUMNS.DATA_TYPE");
                $selectQuery = replace($selectQuery, "{repeat} else table_COLUMNS.CHARACTER_MAXIMUM_LENGTH", "when dbo.INSTR(0,table_RealPid.g08,'{exDB}.')>0 then table_COLUMNS{i}.CHARACTER_MAXIMUM_LENGTH {repeat} else table_COLUMNS.CHARACTER_MAXIMUM_LENGTH");
                $selectQuery = replace($selectQuery, "{repeat} else table_COLUMNS.COLUMN_DEFAULT", "when dbo.INSTR(0,table_RealPid.g08,'{exDB}.')>0 then table_COLUMNS{i}.COLUMN_DEFAULT {repeat} else table_COLUMNS.COLUMN_DEFAULT");
                $selectQuery = replace($selectQuery, "{repeat} else table_COLUMNS.IS_NULLABLE", "when dbo.INSTR(0,table_RealPid.g08,'{exDB}.')>0 then table_COLUMNS{i}.IS_NULLABLE {repeat} else table_COLUMNS.IS_NULLABLE");

                $countQuery = replace($countQuery, "{repeat} else table_COLUMNS.DATA_TYPE", "when dbo.INSTR(0,table_RealPid.g08,'{exDB}.')>0 then table_COLUMNS{i}.DATA_TYPE {repeat} else table_COLUMNS.DATA_TYPE");
                $countQuery = replace($countQuery, "{repeat} else table_COLUMNS.CHARACTER_MAXIMUM_LENGTH", "when dbo.INSTR(0,table_RealPid.g08,'{exDB}.')>0 then table_COLUMNS{i}.CHARACTER_MAXIMUM_LENGTH {repeat} else table_COLUMNS.CHARACTER_MAXIMUM_LENGTH");
                $countQuery = replace($countQuery, "{repeat} else table_COLUMNS.COLUMN_DEFAULT", "when dbo.INSTR(0,table_RealPid.g08,'{exDB}.')>0 then table_COLUMNS{i}.COLUMN_DEFAULT {repeat} else table_COLUMNS.COLUMN_DEFAULT");
                $countQuery = replace($countQuery, "{repeat} else table_COLUMNS.IS_NULLABLE", "when dbo.INSTR(0,table_RealPid.g08,'{exDB}.')>0 then table_COLUMNS{i}.IS_NULLABLE {repeat} else table_COLUMNS.IS_NULLABLE");

                $joinSql = replace($joinSql, "{exDB}", $allDB[$i]['exDB']);

                $selectQuery = replace($selectQuery, "{exDB}", $allDB[$i]['exDB']);
                $selectQuery = replace($selectQuery, "{i}", $i+1);

                $countQuery = replace($countQuery, "{exDB}", $allDB[$i]['exDB']);
                $countQuery = replace($countQuery, "{i}", $i+1);

            }
            $selectQuery = replace($selectQuery, "{repeat}", "");
            $selectQuery = replace($selectQuery, $joinSql0, $joinSql);

            $countQuery = replace($countQuery, "{repeat}", "");
            $countQuery = replace($countQuery, $joinSql0, $joinSql);
        }
    }

}
//end list_query



function textUpdate_sql() {
    global $strsql, $real_pid, $parent_idx, $keyAlias, $keyValue, $thisValue, $oldText, $thisAlias, $resultCode, $resultMessage, $afterScript, $base_site;
	global $full_site;

    if(InStr(";aliasName;Grid_Columns_Title;Grid_Select_Field;Grid_Select_Tname;SortElement;",";" . $thisAlias . ";")>0) {



        execSql($strsql);
        
		$sel_RealPid = onlyOnereturnSql("select RealPid from MisMenuList_Detail where idx=$keyValue");
        $strsql = '';

        aliasN_update_RealPid($sel_RealPid);
        
        
        $resultCode = "success";
        $resultMessage = "aliasName 도 변경되었습니다.";

        //$afterScript = "$('#grid').data('kendoGrid').dataSource.read();";
        //$newAlias = updateAlias($keyValue, $thisAlias, $thisValue);
        //echo "---- $keyValue, $thisAlias, $thisValue, $newAlias ---";16055, Grid_Columns_Title, 영문성명, zyeongmunseongmyeong ---
        //if($newAlias!="") $afterScript = "setGridCellValue_idx('" . $keyValue . "', 'aliasName', '" . $newAlias . "');";
    
    }

	if(InStr(";SortElement;",";" . $thisAlias . ";")>0) {
        execSql($strsql);
		$strsql = '';
		$sel_RealPid = onlyOnereturnSql("select RealPid from MisMenuList_Detail where idx=$keyValue");

		//아래와 같이 다이렉트 접속일 경우 callback 가 없으면 readResult 로 해야함. 
		$url = "$full_site/_mis/list_json.php?flag=readResult&RealPid=speedmis000267&app=자동정렬&parent_idx=$sel_RealPid";


		file_get_contents_new($url);
		$afterScript = "$('div#grid').data('kendoGrid').dataSource.read();";
    }


}
//end textUpdate_sql



function addLogic_treat() {
	
	global $misSessionUserId;
	
    //addLogic_treat 함수는 ajax 로 요청되어진(url 형식) 것에 대한 출력문입니다. echo 등으로 출력내용만 표시하면 됩니다.
	//아래는 url 에 동반된 파라메터의 예입니다.
	//해당 예제 TIP 의 기본폼에 보면 addLogic_treat 를 호출하는 코딩이 있습니다.

    $question = requestVB("question");
    $RealPidAliasName = requestVB("RealPidAliasName");

	//아래는 값에 따라 mysql 서버를 통해 알맞는 값을 출력하여 보냅니다.
    if($question=="RealPidAliasName") {
		
		$sql = " 
select table_RealPid.MenuName as `table_RealPidQnMenuName`
,table_RealPid.g08 as `table_RealPidQng08`
,table_m.SortElement as `SortElement`
,table_m.Grid_Columns_Title as `Grid_Columns_Title`
,table_m.Grid_FormGroup as `Grid_FormGroup`
,table_m.Grid_Columns_Width as `Grid_Columns_Width`
,table_m.Grid_Align as `Grid_Align`
,table_m.Grid_Orderby as `Grid_Orderby`
,table_m.Grid_MaxLength as `Grid_MaxLength`
,table_m.Grid_Items as `Grid_Items`
,table_m.Grid_Default as `Grid_Default`
,table_m.Grid_Select_Tname as `Grid_Select_Tname`
,table_m.Grid_CtlName as `Grid_CtlName`
,table_m.Grid_Schema_Type as `Grid_Schema_Type`
,table_m.Grid_IsHandle as `Grid_IsHandle`
,table_m.Grid_ListEdit as `Grid_ListEdit`
,table_m.Grid_Templete as `Grid_Templete`
,table_m.Grid_Schema_Validation as `Grid_Schema_Validation`
,table_m.Grid_Alim as `Grid_Alim`
,table_m.Grid_Pil as `Grid_Pil`
from MisMenuList_Detail table_m
left outer join MisMenuList table_RealPid on table_RealPid.RealPid = table_m.RealPid
where 9=9  and table_m.useflag='1' 
and table_RealPid.useflag='1' and table_m.RealPidAliasName='$RealPidAliasName'
";
		echo jsonReturnSql($sql);
		
    }

}
//end addLogic_treat

?>