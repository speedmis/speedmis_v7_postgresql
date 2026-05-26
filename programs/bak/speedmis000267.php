<?php

function misMenuList_change() {

	//misMenuList 테이블에 의한 설정값인 $result 를 바꾸는게 이 함수의 핵심기능
    global $actionFlag, $gubun, $parent_idx, $real_pid, $logicPid, $result;
	global $misSessionPositionCode, $flag, $idx, $MS_MJ_MY, $base_db;
    
    global $addParam;
    $MisMenuList_Detail = 'MisMenuList_Detail';
    if($addParam=='pre') {
        $result[0]['g08'] = 'MisMenuList_Detail_pre';
    }

    $real_table = '';
    if($parent_idx!='') {
        $real_table = onlyOnereturnSql("select g08 from MisMenuList where RealPid='$parent_idx'");
    } else if($idx!='') {
        $real_table = onlyOnereturnSql("select g08 from MisMenuList m join $MisMenuList_Detail d on m.RealPid=d.RealPid where m.RealPid=d.RealPid and d.idx='$idx'");
    }


	$search_index = array_search("table_mQmGrid_Columns_Title", array_column($result, 'aliasName'));
    if(InStr($real_table,'.')>0) {
        if(Left($real_table,4)=='dbo.') {
            $real_table = Mid($real_table,5,50);
            $result[$search_index]["Grid_GroupCompute"] = "INFORMATION_SCHEMA.COLUMNS table_COLUMNS on table_COLUMNS.TABLE_CATALOG='$base_db' and table_COLUMNS.TABLE_NAME='$real_table' and table_m.Grid_Select_Field=table_COLUMNS.COLUMN_NAME";
        } else if(count(splitVB($real_table,'.'))==2) {
            $real_table = splitVB($real_table,'.')[1];
			if($MS_MJ_MY=='MY') {
				$result[$search_index]["Grid_GroupCompute"] = "INFORMATION_SCHEMA.COLUMNS table_COLUMNS on table_COLUMNS.TABLE_SCHEMA='$base_db' and table_COLUMNS.TABLE_NAME='$real_table' and table_m.Grid_Select_Field=table_COLUMNS.COLUMN_NAME";
			} else {
	            $result[$search_index]["Grid_GroupCompute"] = "INFORMATION_SCHEMA.COLUMNS table_COLUMNS on table_COLUMNS.TABLE_CATALOG='$base_db' and table_COLUMNS.TABLE_NAME='$real_table' and table_m.Grid_Select_Field=table_COLUMNS.COLUMN_NAME";
			}
        } else {
            $real_table = splitVB($real_table,'.')[0];
            //$real_table = splitVB($real_table,'.')[2];
			if($MS_MJ_MY=='MY') {
				$result[$search_index]["Grid_GroupCompute"] = "$real_table.INFORMATION_SCHEMA.COLUMNS table_COLUMNS on table_COLUMNS.TABLE_SCHEMA='$base_db' and table_COLUMNS.TABLE_NAME='$real_table' and table_m.Grid_Select_Field=table_COLUMNS.COLUMN_NAME";
			} else {
				$result[$search_index]["Grid_GroupCompute"] = "$real_table.INFORMATION_SCHEMA.COLUMNS table_COLUMNS on table_COLUMNS.TABLE_CATALOG='$base_db' and table_COLUMNS.TABLE_NAME='$real_table' and table_m.Grid_Select_Field=table_COLUMNS.COLUMN_NAME";
			}
        }
    } else {
			if($MS_MJ_MY=='MY') {
				$result[$search_index]["Grid_GroupCompute"] = "INFORMATION_SCHEMA.COLUMNS table_COLUMNS on table_COLUMNS.TABLE_SCHEMA='$base_db' and table_COLUMNS.TABLE_NAME='$real_table' and table_m.Grid_Select_Field=table_COLUMNS.COLUMN_NAME";
			} else {
	            $result[$search_index]["Grid_GroupCompute"] = "INFORMATION_SCHEMA.COLUMNS table_COLUMNS on table_COLUMNS.TABLE_CATALOG='$base_db' and table_COLUMNS.TABLE_NAME='$real_table' and table_m.Grid_Select_Field=table_COLUMNS.COLUMN_NAME";
			}
	}





    //아래는 speedmis001333.php / speedmis000267.php 와 같음.
    $search_index = array_search("zgwonjangkeullaeseu", array_column($result, 'aliasName'));

    if($MS_MJ_MY=='MY') {
        $result[$search_index]["Grid_Select_Field"] = "
        CONCAT(case 
        when table_next.Grid_CtlName='dropdownlist' then
            case 
            when table_next.Grid_Columns_Width >= 50 or replace(table_next.Grid_MaxLength,'!','')*1 >= 50 then 'col-xs-12 col-sm-6 col-md-6 col-lg-6'
            else 'col-xs-6 col-sm-3 col-md-3 col-lg-3'
            end
        when table_m.Grid_CtlName='dropdownitem' then
            case 
            when table_m.Grid_Columns_Width >= 50 or replace(table_m.Grid_MaxLength,'!','')*1 >= 50 then 'col-xs-12 col-sm-6 col-md-6 col-lg-6'
            else 'col-xs-6 col-sm-3 col-md-3 col-lg-3'
            end
        when table_m.Grid_Columns_Width<>-1 and INSTR(table_m.Grid_Columns_Title,':')>0 then
            'col-xs-12 col-sm-12 col-md-12 col-lg-12'
            
        when table_m.Grid_Schema_Validation='code' then
            'col-xs-12 col-sm-12 col-md-12 col-lg-12'
        when table_m.Grid_CtlName='textarea' then
            'col-xs-12 col-sm-12 col-md-12 col-lg-6'
        when table_m.Grid_Select_Field='' and table_m.Grid_Templete='Y' then
            'col-xs-12 col-sm-12 col-md-6 col-lg-6'
            
        when table_m.Grid_CtlName='textdecrypt2' then
            'col-xs-12 col-sm-12 col-md-6 col-lg-4'
        when table_m.Grid_CtlName='text' and (table_m.Grid_Schema_Validation='zipcode' or table_prev.Grid_Schema_Validation='zipcode' or table_prev2.Grid_Schema_Validation='zipcode') then
            case 
            when table_m.Grid_Schema_Validation<>'zipcode' and table_prev.Grid_Schema_Validation='zipcode' then 'col-xs-12 col-sm-8 col-md-5 col-lg-5'
            when table_m.Grid_Schema_Validation='zipcode' then 'col-xs-12 col-sm-4 col-md-3 col-lg-3'
            else 'col-xs-12 col-sm-12 col-md-4 col-lg-4'
            end
        
        when left(table_m.Grid_CtlName,4)='text' then
            case 
            when table_m.Grid_Columns_Width >= 500 or replace(table_m.Grid_MaxLength,'!','') >= 500 then 'col-xs-12 col-sm-12 col-md-12 col-lg-12'
            when table_m.Grid_Columns_Width >= 200 or replace(table_m.Grid_MaxLength,'!','') >= 100 then 'col-xs-12 col-sm-12 col-md-12 col-lg-6'
            when table_m.Grid_Columns_Width >= 100 or replace(table_m.Grid_MaxLength,'!','') >= 100 or length(ifnull(table_m.Grid_Alim,''))>20 then 'col-xs-12 col-sm-12 col-md-6 col-lg-6'
            when table_m.Grid_Columns_Width >= 50 or replace(table_m.Grid_MaxLength,'!','')*1 >= 50 then 'col-xs-12 col-sm-6 col-md-3 col-lg-3'
            else 'col-xs-6 col-sm-6 col-md-3 col-lg-3'
            end
            
        else
            
            case when table_m.Grid_Columns_Width >= 100 or replace(table_m.Grid_MaxLength,'!','') >= 100 then 'col-xs-12 col-sm-12 col-md-12 col-lg-12'
            when table_m.Grid_CtlName='attach' or table_m.Grid_Columns_Width >= 50 or replace(table_m.Grid_MaxLength,'!','')*1 >= 50 then 'col-xs-12 col-sm-6 col-md-6 col-lg-6'
            else 'col-xs-6 col-sm-3 col-md-3 col-lg-3'
            end
            
        end 
        ,
        case 
        when table_m.Grid_CtlName='attach' or table_m.Grid_CtlName='dropdowntree' then ' row-54'
        when table_m.Grid_Schema_Validation='code' then ' row-0'
        when table_m.Grid_CtlName='textarea' then ' row-3'
        when table_m.Grid_CtlName='html' then ' row-60'
        else ' row-1'
        end 
        ) 
        ";

    } else {
        $result[$search_index]["Grid_Select_Field"] = "
        case 
        when table_next.Grid_CtlName='dropdownlist' then
            case 
            when table_next.Grid_Columns_Width >= 50 or replace(table_next.Grid_MaxLength,'!','')*1 >= 50 then 'col-xs-12 col-sm-6 col-md-6 col-lg-6'
            else 'col-xs-6 col-sm-3 col-md-3 col-lg-3'
            end
        when table_m.Grid_CtlName='dropdownitem' then
            case 
            when table_m.Grid_Columns_Width >= 50 or replace(table_m.Grid_MaxLength,'!','')*1 >= 50 then 'col-xs-12 col-sm-6 col-md-6 col-lg-6'
            else 'col-xs-6 col-sm-3 col-md-3 col-lg-3'
            end
        when table_m.Grid_Columns_Width<>-1 and dbo.INSTR(1,table_m.Grid_Columns_Title,':')>0 then
            'col-xs-12 col-sm-12 col-md-12 col-lg-12'
        when table_m.Grid_Schema_Validation='code' then
            'col-xs-12 col-sm-12 col-md-12 col-lg-12'
        when table_m.Grid_CtlName='textarea' then
            'col-xs-12 col-sm-12 col-md-12 col-lg-6'
        when table_m.Grid_Select_Field='' and table_m.Grid_Templete='Y' then
            'col-xs-12 col-sm-12 col-md-6 col-lg-6'
        when table_m.Grid_CtlName='textdecrypt2' then
            'col-xs-12 col-sm-12 col-md-6 col-lg-4'
        when table_m.Grid_CtlName='text' and (table_m.Grid_Schema_Validation='zipcode' or table_prev.Grid_Schema_Validation='zipcode' or table_prev2.Grid_Schema_Validation='zipcode') then
            case 
            when table_m.Grid_Schema_Validation<>'zipcode' and table_prev.Grid_Schema_Validation='zipcode' then 'col-xs-12 col-sm-8 col-md-5 col-lg-5'
            when table_m.Grid_Schema_Validation='zipcode' then 'col-xs-12 col-sm-4 col-md-3 col-lg-3'
            else 'col-xs-12 col-sm-12 col-md-4 col-lg-4'
            end
        when left(table_m.Grid_CtlName,4)='text' then
            case 
            when table_m.Grid_Columns_Width >= 500 or replace(table_m.Grid_MaxLength,'!','') >= 500 then 'col-xs-12 col-sm-12 col-md-12 col-lg-12'
            when table_m.Grid_Columns_Width >= 200 or replace(table_m.Grid_MaxLength,'!','') >= 100 then 'col-xs-12 col-sm-12 col-md-12 col-lg-6'
            when table_m.Grid_Columns_Width >= 100 or replace(table_m.Grid_MaxLength,'!','') >= 100 or len(isnull(table_m.Grid_Alim,''))>20 then 'col-xs-12 col-sm-12 col-md-6 col-lg-6'
            when table_m.Grid_Columns_Width >= 50 or replace(table_m.Grid_MaxLength,'!','')*1 >= 50 then 'col-xs-12 col-sm-6 col-md-3 col-lg-3'
            else 'col-xs-6 col-sm-6 col-md-3 col-lg-3'
            end
        else
            case when table_m.Grid_Columns_Width >= 100 or replace(table_m.Grid_MaxLength,'!','') >= 100 then 'col-xs-12 col-sm-12 col-md-12 col-lg-12'
            when table_m.Grid_CtlName='attach' or table_m.Grid_Columns_Width >= 50 or replace(table_m.Grid_MaxLength,'!','')*1 >= 50 then 'col-xs-12 col-sm-6 col-md-6 col-lg-6'
            else 'col-xs-6 col-sm-3 col-md-3 col-lg-3'
            end
        end 
        +
        case 
        when table_m.Grid_CtlName='attach' or table_m.Grid_CtlName='dropdowntree' then ' row-54'
        when table_m.Grid_Schema_Validation='code' then ' row-0'
        when table_m.Grid_CtlName='textarea' then ' row-3'
        when table_m.Grid_CtlName='html' then ' row-60'
        else ' row-1'
        end  
        ";
    }
}
//end misMenuList_change



function pageLoad() {

    global $actionFlag,$addParam;



    if($actionFlag=="list") { 
        ?>


        <script>
//사용자 정의 함수 = 함수 이름은 변형하면 안됨. 내용만. 없어도 됨. ==============================
//데이타의 변형은 즉시 가능 = rowFunction
<?php if($addParam=='pre') { ?>
$('title')[0].innerText = 'TEST개발중:'+$('title')[0].innerText;	
<?php } ?>
			
			
function rowFunction_UserDefine(p_this) {
    //p_this.MenuName = p_this.depth + p_this.LanguageCode; 
    //p_this.AutoGubun = 
    //"<a href=index.asp?gubun=" + p_this.idx + "&isMenuIn=Y target=_blank>[Go]</a> <a id='aid_" + p_this.idx + "' href=index.asp?RealPid=speedmis000266&idx=" + p_this.idx + "&isMenuIn=Y target=_blank>[Source]</a>?" 
    //+ p_this.AutoGubun; 
}

function thisLogic_toolbar() {
    $("#btn_1").text("자동정렬");
    $("#btn_1")[0].title = '아래 상세내역의 번호를 정렬해주고, 내용페이지의 간격을 최적화시켜 줍니다.';
    $("#btn_1").css("background", "#88f");
    $("#btn_1").css("color", "#fff");
    $("#btn_1").click( function() {
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "자동정렬";
        $("#grid").data("kendoGrid").dataSource.read();
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "";
    });
}
//목록에서 grid 로드 후 한번만 실행됨, 이때 처리해야할 일반 스크립트를 삽입합니다.
function listLogic_afterLoad_once()	{
	//grid_remove_sort();    //그리드의 상단 정렬 기능 제거를 원할 경우.
	
}
			
//목록에서 grid 로드 후 데이터 로딩마다 실행됨, 이때 처리해야할 일반 스크립트를 삽입합니다.		
function listLogic_afterLoad_continue()	{
	
	
}


//스타일 등의 변형은 로딩후에 가능 = rowFunctionAfter
function rowFunctionAfter_UserDefine(p_this) {


}            
        </script>
        <?php 
    }
}
//end pageLoad



function list_json_init() {

    global $real_pid, $misJoinPid, $logicPid, $parent_idx, $isnull;
    global $MS_MJ_MY, $flag, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript;
    global $addParam;
    $MisMenuList_Detail = 'MisMenuList_Detail';
    if($addParam=='pre') {
        $result[0]['g08'] = 'MisMenuList_Detail_pre';
		$MisMenuList_Detail = 'MisMenuList_Detail_pre';
    }

    if(Left($flag,4)=="read") { 

        if($app=="자동정렬") {
            aliasN_update_RealPid($parent_idx);

            if($MS_MJ_MY=='MY') {
                $appSql = "
                SET @sortNum := 0;
				UPDATE $MisMenuList_Detail SET SortElement = 
				( SELECT @sortNum := @sortNum + 1 ), RealPidAliasName=concat(RealPid,'.',aliasName)
                where RealPid='$parent_idx' ORDER BY SortElement, idx;     

				UPDATE $MisMenuList_Detail SET RealPidAliasName=concat(RealPid,'.',aliasName)
                where RealPid IN (SELECT RealPid FROM MisMenuList WHERE useflag=1) AND aliasName<>'' 
				AND (IFNULL(RealPidAliasName,'')='' OR right(RealPidAliasName,1)='.');
				
				";
            } else {
                $appSql = "
                DECLARE @RealPid nvarchar(14)  
                DECLARE @sql nvarchar(max)  
                DECLARE @idx int
                DECLARE @SortElement int
                DECLARE @ii int
            
                set @sql=''
                set @RealPid='$parent_idx'
                set @ii=0
                
                DECLARE MisApp_CUR CURSOR FOR  
                select  idx,SortElement from $MisMenuList_Detail where RealPid=@RealPid order by SortElement, idx 
            
                Open MisApp_CUR
                FETCH NEXT FROM MisApp_CUR INTO @idx, @SortElement
                WHILE @@FETCH_STATUS = 0 
                BEGIN    
                    set @ii = @ii+1
                    set @sql = @sql + ' update $MisMenuList_Detail set RealPidAliasName=RealPid+''.''+aliasName,SortElement='+convert(nvarchar(5),@ii)+' where idx='+convert(nvarchar(5),@idx)
                
                FETCH NEXT FROM MisApp_CUR INTO @idx, @SortElement
                END
            
                exec (@sql)
            
                CLOSE MisApp_CUR
                DEALLOCATE MisApp_CUR      

				UPDATE $MisMenuList_Detail SET RealPidAliasName=RealPid+'.'+aliasName
                where RealPid IN (SELECT RealPid FROM MisMenuList WHERE useflag=1) AND isnull(aliasName,'')<>'' 
				AND (ISNULL(RealPidAliasName,'')='' OR right(RealPidAliasName,1)='.')
                ";
            }



            if(execSql($appSql)) {
                $resultCode = "success";
                $resultMessage = "자동정렬이 완료되었습니다.";
                //$_SESSION["newLogin"] = "Y";
                //$afterScript = "location.href = replaceAll(location.href, '#', '');";
            } else {
                $resultCode = "fail";
                $resultMessage = "처리가 실패되었습니다.";
            }

        }
   }
}
//end list_json_init



function list_query() {
    global $real_pid, $misJoinPid, $logicPid, $parent_idx, $actionFlag, $selField, $result;
    global $flag, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript;
    global $countQuery, $selectQuery, $idx_aliasName, $MS_MJ_MY;   //특정필드에 대한 검색이 있는 경우.

	global $addParam;


    if($idx_aliasName!="") { 
        $countQuery = $countQuery . " and table_m.RealPid='" . $_GET["wherePid"] . "'";
        $selectQuery = replace($selectQuery, ") aaa", " and table_m.RealPid='" . $_GET["wherePid"] . "') aaa");
   }

   if($MS_MJ_MY!='MY' && $flag=='read' && $selField=='') {
        
    
        $allDB = splitVB($countQuery, "and table_RealPid.useflag='1' and table_RealPid.MenuType = '01'")[0];

        $allDB = "
        select * from (
        " . replace($allDB, "select count(*) from", "select distinct case when dbo.INSTR(0,table_RealPid.g08,'.')=0 or dbo.INSTR(0,table_RealPid.g08,'dbo.')=1 then ''
        else rtrim(left(replace(isnull(table_RealPid.g08,''),'.',REPLICATE(' ',100)),50)) end as exDB
        from") . "
        ) aa where exDB<>''
        ";
        if(InStr($allDB,"table_RealPid on")==0) {
            $allDB = replace($allDB, "where 9=9", "left outer join MisMenuList table_RealPid on table_RealPid.RealPid = table_m.RealPid where 9=9");
        }



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
                if(InStr($joinSql0, $allDB[$i]['exDB'] . '.')==0) {
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

            }
            $selectQuery = replace($selectQuery, "{repeat}", "");
            $selectQuery = replace($selectQuery, $joinSql0, $joinSql);
            $selectQuery = replace($selectQuery, "case  else", "case when 1=2 then '' else");

            $countQuery = replace($countQuery, "{repeat}", "");
            $countQuery = replace($countQuery, $joinSql0, $joinSql);
        }
    }
											
	if($addParam=='pre') {
        $countQuery = replace($countQuery,'MisMenuList_Detail','MisMenuList_Detail_pre');
        $countQuery = replace($countQuery,'_pre_pre','_pre');
        $selectQuery = replace($selectQuery,'MisMenuList_Detail','MisMenuList_Detail_pre');
        $selectQuery = replace($selectQuery,'_pre_pre','_pre');
	}
											
}
//end list_query



function list_json_load() {
	
    global $base_root, $real_pid, $misJoinPid, $logicPid, $parent_idx, $top, $isnull;
    global $flag, $selField, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript;
    global $data, $key_aliasName, $child_alias, $selectQuery, $keyword, $menuName, $allFilter;
    global $MS_MJ_MY;
    global $addParam;
    $MisMenuList_Detail = 'MisMenuList_Detail';
    if($addParam=='pre') {
        $result[0]['g08'] = 'MisMenuList_Detail_pre';
    }
	//$flag 는 목록조회시 'read'   내용조회시 'view'    수정시 'modify'   입력시 'write'
	//$selField 는 필터링을 하는 순간 발생하는 필드alias 값.


	//아래는 조회 또는 수정 시, addLogic 이라는 항목에 대해 만약 파일로 존재하는 php 파일이 있다면 해당파일로 바꿔서 json 에 반영하는 로직입니다.
    if($app=="자동정렬") {

        //아래는 speedmis001333.php / speedmis000267.php 가 거의 같음 : 267 이 더 상세함!
        $r_data = json_decode($data);
        $r_count = count($r_data);
        $sql = '';
        $userdefined_cnt = 0;
        
        for($i=0;$i<$r_count;$i++) {
            $r_idx = $r_data[$i]->idx;
            $r_Grid_View_Fixed = $r_data[$i]->Grid_View_Fixed;
            $r_Grid_Schema_Validation = $r_data[$i]->Grid_Schema_Validation;
            $r_Grid_View_XS = $r_data[$i]->Grid_View_XS;
            $r_zgwonjangkeullaeseu = $r_data[$i]->zgwonjangkeullaeseu;      //권장class
            $r_cell = splitVB($r_zgwonjangkeullaeseu,' ');
            $r_XS = replace(Right($r_cell[0],2),'-','');
            $r_SM = replace(Right($r_cell[1],2),'-','');
            $r_MD = replace(Right($r_cell[2],2),'-','');
            $r_LG = replace(Right($r_cell[3],2),'-','');
            $r_Hight = replace(Right($r_cell[4],2),'-','');

            $r_Grid_Enter = $r_data[$i]->Grid_Enter;
            $r_table_COLUMNSQnDATA_TYPE = $r_data[$i]->table_COLUMNSQnDATA_TYPE;

			//267만의 로직 : table_COLUMNSQnDATA_TYPE 를 가져올 수 있다! -----
			if($r_table_COLUMNSQnDATA_TYPE=='text' && $r_Grid_View_Fixed!='1') {
				$r_XS = '12';
				$r_SM = '12';
				$r_MD = '12';
				$r_LG = '6';
				$r_Hight = '59';
			}
			//--------------------------------------------------------------
			
			$simple_tag = "col-xs-$r_XS" . iif($r_XS!=$r_SM, " col-sm-$r_SM", "") . iif($r_SM!=$r_MD, " col-md-$r_MD", "") . iif($r_MD!=$r_LG || $r_LG=='12', " col-lg-$r_LG", "") . " row-$r_Hight";
        
            $r_zseoljeongkeullaeseu = $r_data[$i]->zseoljeongkeullaeseu;    //설정 class
            $r_zseoljeongSIMPLE = $r_data[$i]->zseoljeongSIMPLE;    //설정 SIMPLE
            
            $r_Grid_View_Class = $r_data[$i]->Grid_View_Class;          //저장 class
            if($r_Grid_View_XS=='0' || $r_Grid_View_XS=='' || $app=='class_init') {
                $sql = $sql . "
                update $MisMenuList_Detail set 
                Grid_View_Class=N'$simple_tag'
                ,Grid_View_XS=$r_XS
                ,Grid_View_SM=$r_SM
                ,Grid_View_MD=$r_MD
                ,Grid_View_LG=$r_LG
                ,Grid_View_Hight=$r_Hight
                where idx=$r_idx and $isnull(Grid_View_Fixed,0)<>1;
                ";
                if($r_Grid_View_Fixed!='1') {
                    $r_data[$i]->Grid_View_XS = $r_XS;
                    $r_data[$i]->Grid_View_SM = $r_SM;
                    $r_data[$i]->Grid_View_MD = $r_MD;
                    $r_data[$i]->Grid_View_LG = $r_LG;
                    $r_data[$i]->Grid_View_Hight = $r_Hight;
                }

                if($r_Grid_Schema_Validation=='zipcode') {
                    //print_r($r_data[$i-1]);exit;
                    if($i<$r_count-1) {
                        $r_idx_next = $r_data[$i+1]->idx;   //우편번호가 두번째로 나오던 시절때문에 수습차원.
                        $sql = $sql . "
                        update $MisMenuList_Detail set 
                        Grid_Enter=0
                        where idx=$r_idx_next;
                        update $MisMenuList_Detail set 
                        Grid_Enter=1
                        where idx=$r_idx;
                        ";
                        $r_data[$i+1]->Grid_Enter = 0;
                        $r_data[$i]->Grid_Enter = 1;
                    }
                }
                


            } else if($r_zseoljeongSIMPLE!=$r_Grid_View_Class) {
                $sql = $sql . "
                update $MisMenuList_Detail set 
                Grid_View_Class=N'$r_zseoljeongSIMPLE'
                where idx=$r_idx;
                ";
            }
            if(InStr($allFilter,'"field":"toolbar_zpeurogeuraemseontaek"')>0) {
                if($r_zseoljeongSIMPLE!=$simple_tag) {
                    ++$userdefined_cnt;
                    $r_data[$i]->zuserdefined = '1';
                } else if($r_Grid_Enter==1) {
                    if($r_data[$i]->Grid_Schema_Validation!='zipcode') {
                        ++$userdefined_cnt;
                    }
                    $r_data[$i]->zuserdefined = '1';
                }
            }
        }
		
        if($sql!='') {
			execSql($sql);
        }
        
    }
	
}
//end list_json_load



function save_updateAfter() {

	global $updateList, $kendoCulture, $afterScript, $base_domain, $key_value, $full_site, $parent_idx, $base_root;

	//아래는 나의정보수정에서 언어를 변경하여 저장할 경우, 언어관련 쿠키값을 변경하고, 새로고침하는 로직입니다.


	$url = "$full_site/_mis/list_json.php?flag=readResult&RealPid=speedmis000267&app=자동정렬&parent_idx=$parent_idx";
	file_get_contents_new($url);

    //프로그램 변경에 따른 캐쉬파일 삭제 로직
    $parent_gubun = GubunIntoRealPid($parent_idx);
    $path_pattern = "$base_root/_mis_cache/*P.$parent_idx.*";
    delete_cache_files($path_pattern);
    $path_pattern = "$base_root/_mis_cache/*gubun.$parent_gubun.*";
    delete_cache_files($path_pattern);

}
//end save_updateAfter



function textUpdate_sql() {
    global $strsql, $real_pid, $parent_idx, $keyAlias, $keyValue, $thisValue, $oldText, $thisAlias, $resultCode, $resultMessage, $afterScript;
	global $full_site;
    global $addParam, $table_m, $base_root;
    $MisMenuList_Detail = 'MisMenuList_Detail';
    if($addParam=='pre') {
        $strsql = replace($strsql, 'MisMenuList_Detail', 'MisMenuList_Detail_pre');
	 	$table_m = 'MisMenuList_Detail_pre';
    }

    if(InStr(";aliasName;Grid_Columns_Title;Grid_Select_Field;Grid_Select_Tname;SortElement;",";" . $thisAlias . ";")>0) {
        execSql($strsql);
        
        $strsql = '';

        aliasN_update_RealPid($parent_idx);
        
        
        $resultCode = "success";
        $resultMessage = "aliasName 도 변경되었습니다.";

        //$afterScript = "$('#grid').data('kendoGrid').dataSource.read();";
        //$newAlias = updateAlias($keyValue, $thisAlias, $thisValue);
        //echo "---- $keyValue, $thisAlias, $thisValue, $newAlias ---";16055, Grid_Columns_Title, 영문성명, zyeongmunseongmyeong ---
        //if($newAlias!="") $afterScript = "setGridCellValue_idx('" . $keyValue . "', 'aliasName', '" . $newAlias . "');";
    
    } else if($thisAlias=='Grid_PrimeKey') {  
        //정상적인 참조쿼리일 경우, _mis_cache 내의 cache_table 캐쉬를 삭제해야 함.
        if(count(splitVB($thisValue,'#'))>=4) {
            $cache_table = splitVB($thisValue,'#')[1];
            $path_pattern = "$base_root/_mis_cache/*.table.$cache_table.*";
            delete_cache_files($path_pattern);
            
        }
    } else if(InStr(";SortElement;",";" . $thisAlias . ";")>0) {
        execSql($strsql);
		$strsql = '';

		//아래와 같이 다이렉트 접속일 경우 callback 가 없으면 readResult 로 해야함. 
		$url = "$full_site/_mis/list_json.php?flag=readResult&RealPid=speedmis000267&app=자동정렬&parent_idx=$keyValue";
		file_get_contents_new($url);
		$afterScript = "$('div#grid').data('kendoGrid').dataSource.read();";
    }
    
    //프로그램 변경에 따른 캐쉬파일 삭제 로직
    $path_pattern = "$base_root/_mis_cache/*P.$parent_idx.*";       //RealPid 뿐 아니라 MisJoinPid 도 포함되는 효과.
    delete_cache_files($path_pattern);


}
//end textUpdate_sql

?>