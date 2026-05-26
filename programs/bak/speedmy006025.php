<?php

function MisMenuList_change() {
//////////////////////////////////////////
	//MisMenuList 테이블에 의한 설정값인 $result 를 바꾸는게 이 함수의 핵심기능
    global $actionFlag, $gubun, $parent_idx, $real_pid, $logicPid, $result;
	global $misSessionPositionCode, $flag;

///////////////////////////////////////////////
	if($real_pid=='speedmis001169') {
		$result[0]['g09'] = "and rtrim(left(replace(ltrim(concat(ifnull(table_m.현장우편주소,''),' ',ifnull(table_m.현장주소,''))),' ',LPAD('',10,' ')),10))='서울'";

		$search_index = array_search("yeollakcheo", array_column($result, 'aliasName'));
		$result[$search_index]["Grid_ListEdit"] = "Y";

		$search_index = array_search("bangmunggyeongnokodeu", array_column($result, 'aliasName'));
		$result[$search_index]["Grid_ListEdit"] = "Y";
	}


	//아래는 필드명 자체를 특정 값으로 변경하는 예제임
    //$result[$search_index]["Grid_Select_Tname"] = "";
    //$result[$search_index]["Grid_Select_Field"] = "'.....'";

}
//end MisMenuList_change



function pageLoad() {

    global $actionFlag;
	global $misSessionIsAdmin;

/*
	//특정상황에서 페이지를 이동시키는 예제입니다.
	if($actionFlag=="list" && $parent_RealPid=="speedmis000028") {
		$target_parent_gubun = RealPidIntoGubun("speedmis001071");
		$url = "index.php?RealPid=$real_pid&parent_gubun=$target_parent_gubun&parent_idx=$parent_idx";
		Redirect($url);
	}
*/

        ?>
        <script>

			
				
//웹소스 디테일에서 템플릿으로 체크한 항목에 대해 출력내용을 변경할 수 있습니다. 이때 목록 또는 본문내용에 동일하게 적용됩니다.
function columns_templete(p_dataItem, p_aliasName) {



    if(p_aliasName=="zsangdamsangse") {
		var rValue = '<a href="javascript:;" onclick="parent_popup_jquery(\'index.php?RealPid=speedmis000738&parent_RealPid=speedmis000741&parent_idx=' 
			+ p_dataItem['idx']+'\',\'상담상세내역\');" class="k-button">'+p_dataItem[p_aliasName]+'회 내역</a>';
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
    "<a href=index.asp?gubun=" + p_this.idx + "&isMenuIn=Y target=_blank>[Go]</a> <a id='aid_" + p_this.idx + "' href=index.asp?RealPid=speedmis000266&idx=" + p_this.idx + "&isMenuIn=Y target=_blank>[Source]</a> " 
    + p_this.AutoGubun;
*/
}

<?php 
//아래는 해당프로그램의 관리자 권한을 가진 사용자가 목록 조회 시에 적용되는 내용입니다.			
if($misSessionIsAdmin=="Y" && $actionFlag=="list") { 
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
			
			
			
//내용조회 또는 수정/입력 페이지 로딩이 끝나는 순간, 처리해야할 일반 스크립트를 삽입합니다.
function viewLogic_afterLoad() {

			//각탭에 pdf href 가 1개만 있으면 뷰어로 변신
			tab_pdf_into_viewer();

}			
			
			
        </script>
        <?php 
}
//end pageLoad

?>