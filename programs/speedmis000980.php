<?php

function pageLoad() {

    global $ActionFlag,$ServerVariables_URL,$ServerVariables_QUERY_STRING;

	if($ActionFlag=="modify") {
		
		$url = replace($ServerVariables_URL . "?" . $ServerVariables_QUERY_STRING, "ActionFlag=modify", "ActionFlag=view");
		
		re_direct($url);
	}

        ?>
<style>
a#btn_kill, li#btn_kill_overflow {
    display: none!important;
}

a#btn_write, li#btn_write_overflow {
    display: none!important;
}
a#btn_modify, li#btn_modify_overflow {
    display: none!important;
}
</style>

        <script>


function columns_templete(p_dataItem, p_aliasName) {
    if(p_aliasName=="widx") {
        if(p_dataItem["parent_idx"]!="" && p_dataItem["parent_idx"]!=null) {
            return "<a href='index.php?gubun=" + p_dataItem["parent_gubun"] + "&idx=" + p_dataItem["parent_idx"] 
            + "&tabid=" + p_dataItem["RealPid"] + "&tabrealpid_idx=" + p_dataItem["widx"]
            + "&isMenuIn=" + getID("isMenuIn").value + "&fromGnb=Y' target=_blank>[열기]</a> " + p_dataItem["widx"];
        } else {
            return "<a href='index.php?RealPid=" + p_dataItem["RealPid"] 
            + "&idx=" + p_dataItem["widx"]
            + "&isMenuIn=" + getID("isMenuIn").value + "&fromGnb=Y' target=_blank>[열기]</a> " + p_dataItem["widx"];
        }
    } else {
        return p_dataItem[p_aliasName];
    }	
}
    
        </script>
        <?php 

}
//end pageLoad

?>