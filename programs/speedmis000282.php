<?php

function pageLoad() {

    global $ActionFlag,$ServerVariables_QUERY_STRING;
	global $MisSession_IsAdmin, $gubun;



//특정상황에서 페이지를 이동시킨다.
	if($ActionFlag!="list") {

//index.php?gubun=282&idx=1&isAddURL=Y&isIframe=Y
//index.php?gubun=318&idx=218&isAddURL=Y&isIframe=Y
//echo $ServerVariables_QUERY_STRING;

		$target_v = onlyOneReturnSql("select RealPid+'.'+convert(varchar(5),midx) from MisComments where idx='" . requestVB("idx") . "'");
		$url = "index.php?" . replace(replace($ServerVariables_QUERY_STRING, 'gubun=' . $gubun, 'RealPid=' . splitVB($target_v,'.')[0]), 'idx=' . requestVB("idx"), 'idx=' . splitVB($target_v,'.')[1]);

//echo $url
//exit;

		re_direct($url);
	}



        ?>
<style>
	a#btn_write {
		display: none;
	}
	td > a.k-button {
		min-width: 40px!important;
	}
	.editorUpload > img {
		max-width: 300px;
		max-height: 150px;
	}
	.editorUpload {
		display: inline-block;
	}
</style>
        <script>

//웹소스 디테일에서 템플릿으로 체크한 항목에 대해 출력내용을 변경할 수 있습니다. 이때 목록 또는 본문내용에 동일하게 적용됩니다.
function columns_templete(p_dataItem, p_aliasName) {


    if(p_aliasName=="zdaetgeullaeyong") {
		var rValue = "<a href='index.php?RealPid=" + p_dataItem["RealPid"] + "&idx=" + p_dataItem["midx"] + "&tabid=speedmis000974&isAddURL=Y&isMenuIn=auto' target='_blank' class='k-button'>새창</a>"+p_dataItem[p_aliasName];
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