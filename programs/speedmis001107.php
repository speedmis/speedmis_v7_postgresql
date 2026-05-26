<?php

function pageLoad() {

    global $ActionFlag, $MS_MJ_MY;
    global $MisSession_IsAdmin;
    


?>


<script>

//웹소스 디테일에서 템플릿으로 체크한 항목에 대해 출력내용을 변경할 수 있습니다. 이때 목록 또는 본문내용에 동일하게 적용됩니다.
function columns_templete(p_dataItem, p_aliasName) {
	
    if(p_aliasName=="attachUrl") {
		var attachLength = ajax_url_return(p_dataItem["attachUrl"]).length;
		if(attachLength>0) var rValue = "<a href='" + p_dataItem["attachUrl"] + "' target='_blank' class='k-button'>열기</a>";
		else rValue = "<a href='" + p_dataItem["attachUrl"] + "' target='_blank' class='k-button'>존재안함</a>";
		rValue = rValue + " " + p_dataItem["attachUrl"];
        return rValue;
    } else {
        return p_dataItem[p_aliasName];
    }

}
	
	<?php 
//아래는 해당프로그램의 관리자 권한을 가진 사용자가 목록 조회 시에 적용되는 내용입니다.			
if($MisSession_IsAdmin=='Y' && $ActionFlag=='list') { 
?>

//툴바 명령버튼에 btn_1 이라고 하는 예비버튼을 "적용" 이라는 기능을 넣고, 클릭 시, 그리드에 app="적용" 이라는 신호로 보냅니다.
//이때 app=="적용" 에 대한 처리는 list_json_init() 를 참조하세요.
function thisLogic_toolbar() {

    $("a#btn_1").text("파일내역 반영하기");
    $("li#btn_1_overflow").text("파일내역 반영하기");
    $("#btn_1").css("background", "#88f");
    $("#btn_1").css("color", "#fff");
    $("#btn_1").click( function() {
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "적용";
        $("#grid").data("kendoGrid").dataSource.read();
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "";
    });

}
<?php } ?>
	
</script>
        <?php 
}
//end pageLoad



function list_json_init() {
    global $RealPid, $MisJoinPid, $logicPid, $parent_idx, $full_siteID, $MS_MJ_MY, $dbalias;
    global $flag, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript;

	//아래의 예제는 자동정렬이라는 명령을 받았을 경우, 목록이 생성되기 전에 숫자를 정렬하는 기능입니다.
	
    if($flag=="read") { 
        if($app=="적용") {
			if($MS_MJ_MY=='MY') {





				$appSql = 'call MisAttachList_refresh_Proc();';
			} else {
/*
$sql = "

CREATE Proc MisAttachList_refresh_Proc


as begin



DECLARE @sql nvarchar(1000)
DECLARE @idx int
DECLARE @grid_fieldname nvarchar(100)
DECLARE @table_m nvarchar(100)
DECLARE @excel_idxname nvarchar(100)
DECLARE @idxnum nvarchar(100)
            
set @sql=''
                
DECLARE MisApp_CUR CURSOR FOR  
select idx, grid_fieldname, table_m, excel_idxname, idxnum from MisAttachList where isnull(attachName,'')=''
            
Open MisApp_CUR
FETCH NEXT FROM MisApp_CUR INTO @idx, @grid_fieldname, @table_m, @excel_idxname, @idxnum
WHILE @@FETCH_STATUS = 0 
BEGIN    
    set @sql = ' update MisAttachList set attachName=(select '+@grid_fieldname+' from '+@table_m+' where '+@excel_idxname+'='''+@idxnum+''') where idx='+convert(nvarchar(5),@idx)
    exec(@sql)       
FETCH NEXT FROM MisApp_CUR INTO @idx, @grid_fieldname, @table_m, @excel_idxname, @idxnum
END
CLOSE MisApp_CUR
DEALLOCATE MisApp_CUR
  

	DECLARE @RESULT NVARCHAR(100) ='';
	DECLARE @SQL_VAR NVARCHAR(200) ='';
	SET @SQL_VAR = N'@V_RESULT NVARCHAR(100) OUTPUT';  
	
	DECLARE @midx nvarchar(8)
	
	update MisAttachList set 필드값검증='0'
		
	DECLARE MisApp_CUR CURSOR FOR  
	select midx, table_m, Grid_FieldName, excel_idxname, idxnum from dbo.MisAttachList 
	where useflag=1 and idx=midx and (필드값검증='0' or isnull(필드값검증,'')=midx)

	order by midx desc

	Open MisApp_CUR

	FETCH NEXT FROM MisApp_CUR INTO @midx, @table_m, @Grid_FieldName, @excel_idxname, @idxnum

	WHILE @@FETCH_STATUS = 0 

	BEGIN    

    

		set @sql = '
		select top 1 @V_RESULT='+@Grid_FieldName+'_midx'+' from '+@table_m+' where '+@excel_idxname+'=N'''+@idxnum+''' 
		'
		EXEC SP_EXECUTESQL @SQL, @SQL_VAR,     -- 설정한 SQL, Variable 
		--print @RESULT
		@V_RESULT =  @RESULT OUTPUT; -- Variable 값을 반환 받음 (Variable -> 지역변수)
		update MisAttachList set 필드값검증=@RESULT where midx=@midx
		


	FETCH NEXT FROM MisApp_CUR INTO @midx, @table_m, @Grid_FieldName, @excel_idxname, @idxnum
	END 

	CLOSE MisApp_CUR

	DEALLOCATE MisApp_CUR
		
  
End
";
execSql($sql);
				
$sql = "

ALTER Proc MisAttachList_refresh_Proc


as begin



DECLARE @sql nvarchar(1000)
DECLARE @idx int
DECLARE @grid_fieldname nvarchar(100)
DECLARE @table_m nvarchar(100)
DECLARE @excel_idxname nvarchar(100)
DECLARE @idxnum nvarchar(100)
            
set @sql=''
                
DECLARE MisApp_CUR CURSOR FOR  
select idx, grid_fieldname, table_m, excel_idxname, idxnum from MisAttachList where isnull(attachName,'')=''
            
Open MisApp_CUR
FETCH NEXT FROM MisApp_CUR INTO @idx, @grid_fieldname, @table_m, @excel_idxname, @idxnum
WHILE @@FETCH_STATUS = 0 
BEGIN    
    set @sql = ' update MisAttachList set attachName=(select '+@grid_fieldname+' from '+@table_m+' where '+@excel_idxname+'='''+@idxnum+''') where idx='+convert(nvarchar(5),@idx)
    exec(@sql)       
FETCH NEXT FROM MisApp_CUR INTO @idx, @grid_fieldname, @table_m, @excel_idxname, @idxnum
END
CLOSE MisApp_CUR
DEALLOCATE MisApp_CUR
  

	DECLARE @RESULT NVARCHAR(100) ='';
	DECLARE @SQL_VAR NVARCHAR(200) ='';
	SET @SQL_VAR = N'@V_RESULT NVARCHAR(100) OUTPUT';  
	
	DECLARE @midx nvarchar(8)
	
	update MisAttachList set 필드값검증='0'
		
	DECLARE MisApp_CUR CURSOR FOR  
	select midx, table_m, Grid_FieldName, excel_idxname, idxnum from dbo.MisAttachList 
	where useflag=1 and idx=midx and (필드값검증='0' or isnull(필드값검증,'')=midx)

	order by midx desc

	Open MisApp_CUR

	FETCH NEXT FROM MisApp_CUR INTO @midx, @table_m, @Grid_FieldName, @excel_idxname, @idxnum

	WHILE @@FETCH_STATUS = 0 

	BEGIN    

    

		set @sql = '
		select top 1 @V_RESULT='+@Grid_FieldName+'_midx'+' from '+@table_m+' where '+@excel_idxname+'=N'''+@idxnum+''' 
		'
		EXEC SP_EXECUTESQL @SQL, @SQL_VAR,     -- 설정한 SQL, Variable 
		--print @RESULT
		@V_RESULT =  @RESULT OUTPUT; -- Variable 값을 반환 받음 (Variable -> 지역변수)
		update MisAttachList set 필드값검증=@RESULT where midx=@midx
		


	FETCH NEXT FROM MisApp_CUR INTO @midx, @table_m, @Grid_FieldName, @excel_idxname, @idxnum
	END 

	CLOSE MisApp_CUR

	DEALLOCATE MisApp_CUR
		
  
End
";
execSql($sql);

*/
				$appSql = 'exec MisAttachList_refresh_Proc';
			}
			
			if(execSql_gate($appSql, $dbalias)) {
				$resultCode = "success";
				$resultMessage = "처리가 완료되었습니다. ";
			} else {
				$resultCode = "fail";
				$resultMessage = "처리가 실패하였습니다.";
			}
            

        }
    }
	
}
//end list_json_init

?>