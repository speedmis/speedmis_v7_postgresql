<?php

/*info

작성자 : 


주요기능 : 
*/

//선언문 영역
//$plist = ['John','Piter'];
//$list_numbering = '';		//리스트에서 No. 순번항목을 없앰.

$sql = "

WITH RankedStyle AS (
    SELECT 
        k.SortElement,
		k.midx,
        ROW_NUMBER() OVER (ORDER BY k.idx) AS RowNum
    FROM kimgoStyle_detail k
    WHERE k.midx = (select style_idx from kimgoMainList where idx=$parent_idx)
)
UPDATE m
SET 
    m.aliasName = 'freeField'+dbo.formatnums(r.RowNum,'00'),
    m.Grid_Columns_title = k.Grid_Columns_title,
    m.Grid_Columns_Width = k.Grid_Columns_Width,
    m.Grid_Select_Field = 'freeField'+dbo.formatnums(r.RowNum,'00'),
    m.Grid_Schema_Type = k.Grid_Schema_Type,
    m.Grid_Items = k.Grid_Items,
    m.Grid_Schema_Validation = k.Grid_Schema_Validation,
    m.Grid_Align = k.Grid_Align,
    m.Grid_MaxLength = k.Grid_MaxLength,
    m.Grid_Default = k.Grid_Default,
    m.Grid_CtlName = k.Grid_CtlName,
    m.Grid_View_Class = case when k.Grid_CtlName='textarea' then 'col-xs-6 row-3' else 'col-xs-6 row-1' end
FROM MisMenuList_Detail m
JOIN kimgoStyle_detail k
    ON (m.SortElement - 2) = k.SortElement
JOIN RankedStyle r
    ON r.SortElement = k.SortElement  AND k.midx = r.midx
WHERE m.RealPid = 'kimgo003045'
  AND m.Grid_Select_Field LIKE 'freeField%';



";
execSql($sql);

/*

특이사항 : 


참고url : 


info*/



function pageLoad() {

    global $ActionFlag, $idx, $parent_idx, $RealPid, $logicPid;;
	global $MisSession_IsAdmin;

/*
	//특정상황에서 페이지를 이동시키는 예제입니다.
	if($ActionFlag=='list' && $parent_RealPid=='speedmis000028') {
		$target_parent_gubun = RealPidIntoGubun('speedmis001071');
		$url = "index.php?RealPid=$RealPid&parent_gubun=$target_parent_gubun&parent_idx=$parent_idx";
		re_direct($url);
	}
*/


	$sql = "select (select styleName from kimgoStyle where idx=style_idx) from kimgoMainList where idx=$parent_idx";
	$title = onlyOnereturnSql($sql);
        ?>

<style>
/* 필요할 경우, 해당프로그램에 추가할 css 를 넣으세요 */
	
.k-tabstrip-top>.k-tabstrip-items-wrapper {
    display:none;
}
	
	
	
	
	
body .union.toolbar_round_brief_insertsql {
	xdisplay:none;
}
body[mmQnlanguage='levantine'] a#btn_1 {
	xdisplay:none;
}
div#example-search-wrapper {
	xdisplay:none;
}

.k-pager-wrap {
    xdisplay:none;
}
.k-grid tbody .k-button {
    xmin-width: auto;
    xmargin: 0;
    xpadding: 0 3px;
}
tr.k-filter-row {
    xdisplay: none;
}
body[isMainFrame="Y"] #exampleWrap {
    xheight: calc(100% - 1px)!important;
}
</style>


        <script>



//아래 한줄의 주석문을 풀면 리스트 상에서 내용조회나 수정으로 접근할 수 없습니다.
//$('body').attr('onlylist','');			


			
//웹소스 디테일에서 템플릿으로 체크한 항목에 대해 출력내용을 변경할 수 있습니다. 이때 목록 또는 본문내용에 동일하게 적용됩니다.
//row 갯수만큼 실행됩니다.
			
		
function columns_templete(p_dataItem, p_aliasName) {

    if(p_aliasName=='virtual_fieldQnwork_end') {
		var rValue = `<a role="button" href="javascript:;" class="k-button k-button-icontext">처리완료</a>`;
		
		rValue = rValue + columns_format(p_dataItem, p_aliasName);
        return rValue;
    } else {
        return p_dataItem[p_aliasName];
    }
}

			
			
//아래의 함수는 목록에서만 해당되며, 템플릿으로 정의하지 않아도 특정항목의 값이나 태그를 추가할 수 있습니다. 
//row 갯수만큼 실행됩니다.
function rowFunction_UserDefine(p_this) {
/*
	p_this.MenuName = p_this.depth + p_this.LanguageCode; 
    p_this.AutoGubun = 
    "<a href=index.asp?gubun=" + p_this.idx + "&isMenuIn=Y target=_blank>[Go]</a> <a id='aid_" + p_this.idx + "' href=index.asp?RealPid=speedmis000266&idx=" + p_this.idx + "&isMenuIn=Y target=_blank>[Source]</a>?" 
    + p_this.AutoGubun;
*/
}

			
//아래는 그리드의 로딩이 거의 끝난 후, 항목에 스타일 시트를 적용하는 예입니다. 스타일 등을 직접 변경하려면 이 함수를 이용해야 합니다.
//row 갯수만큼 실행됩니다.
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
			
<?php 
//해당프로그램의 관리자 권한을 가진 사용자는 $MisSession_IsAdmin=='Y' 입니다. 필요 시, 여러 부분에서 해당 조건을 넣어 사용하세요.
//if($MisSession_IsAdmin=='Y' && $ActionFlag=='list') { 
			
//}
?>
//사용자 정의버튼 생성 예제입니다.
//툴바 명령버튼에 btn_1 이라고 하는 예비버튼을 "적용" 이라는 기능을 넣고, 클릭 시, 그리드에 app="적용" 이라는 신호로 보냅니다.
//이때 app=="적용" 에 대한 처리는 개발Tip 의 list_json_init() 를 참조하세요.
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

//목록에서 grid 로드 후 한번만 실행됨, 이때 처리해야할 일반 스크립트를 삽입합니다.
function listLogic_afterLoad_once()	{
	//grid_remove_sort();    //그리드의 상단 정렬 기능 제거를 원할 경우.
	
}
			
//목록에서 grid 로드 후 데이터 로딩마다 실행됨, 이때 처리해야할 일반 스크립트를 삽입합니다.		
function listLogic_afterLoad_continue()	{
	
	
}

	
			
//내용조회 또는 수정/입력 페이지 로딩이 끝나는 순간 한번만 실행됨, 이때 처리해야할 일반 스크립트를 삽입합니다.
function viewLogic_afterLoad() {
	
	
	if(parent.$('li[tabrealpid="kimgo003045"] span.k-link').length==1) {
		
		if(InStr($('label[for="freeField01"]')[0].innerText,'스타일정의')>0) {
				parent.$('li[tabrealpid="kimgo003045"]').hide();
				parent.$('li[tabnumber="1"]').click();
				
				$('body').hide();
		} else {
				parent.$('li[tabrealpid="kimgo003045"]').show();
				parent.$('li[tabrealpid="kimgo003045"] span.k-link').text('<?php echo $title; ?>');
		}

	}
	//console.log(resultAll);
	//console.log(resultAll.d.results[0]);
	
	
	//각탭에 html 에디터를 통해 pdf 업로드나 pdf 링크가 href 가 1개만 있으면 뷰어로 변신
	//tab_pdf_into_viewer();

	//특정항목에 대한 내용을 바꾸는 코딩
    //$('div#table_mQmidx')[0].innerText = resultAll.d.results[0].table_mQmidx;

}			
//내용조회 또는 수정/입력 페이지 로딩이 끝난 후, 데이터 호출때마다 실행됨. 
function viewLogic_afterLoad_continue() {

	//debugger;
	//console.log(resultAll);
	//console.log(resultAll.d.results[0]);
	

}	
			
			
//사용자 정의 인쇄폼의 로딩이 완료될때 처리해야할 스크립트를 삽입합니다.
function viewLogic_afterLoad_viewPrint() {
	
	//아래는 특정이미지를 하단에 넣는 예제입니다.
	//$('div.viewPrint').append('<img style="position: absolute;bottom: 32px;left: 35px;width: 200px;" src="/_mis/img/speedmis_wide.png">');
	
}			
			
			
        </script>
        <?php 
}
//end pageLoad

?>