<?php

function pageLoad() {

    global $ActionFlag,$ServerVariables_QUERY_STRING;
	global $MisSession_IsAdmin, $gubun;


	//특정상황에서 페이지를 이동시키는 예제입니다.
//if($ActionFlag!="list") {
		//$target_RealPid = onlyOneReturnSql("select RealPid from MisBoardArticle where idx='" . requestVB("idx") . "'");
		//$url = "index.php?" . replace($ServerVariables_QUERY_STRING, 'gubun=' . $gubun, 'RealPid=' . $target_RealPid);
		//re_direct($url);
//}


        ?>
<style>
	a#btn_write {
		display: none;
	}
	td > a.k-button {
		min-width: 40px!important;
	}
</style>
        <script>
$('body').attr('onlylist','');	
//웹소스 디테일에서 템플릿으로 체크한 항목에 대해 출력내용을 변경할 수 있습니다. 이때 목록 또는 본문내용에 동일하게 적용됩니다.
function columns_templete(p_dataItem, p_aliasName) {


    if(p_aliasName=="table_RealPidQnMenuName") {
		var rValue = "<a href='index.php?RealPid=" + p_dataItem["RealPid"] + "&idx=" + p_dataItem["idx"] + "&isAddURL=Y&isMenuIn=auto' target='_blank' class='k-button'>새창</a>"+p_dataItem[p_aliasName];
		return rValue;
    } else {
        return p_dataItem[p_aliasName];
    }

}
		
			
        </script>
        <?php 
}
//end pageLoad

?>