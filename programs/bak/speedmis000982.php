<?php

function pageLoad() {

    global $actionFlag;


        ?>

        <script>


function columns_templete(p_dataItem, p_aliasName) {
    if(p_aliasName=="jsonname") {
        if(p_dataItem["parent_idx"]!="" && p_dataItem["parent_idx"]!=null) {
            return "<a href='index.php?gubun=" + p_dataItem["table_parent_RealPidQnidx"] + "&idx=" + p_dataItem["parent_idx"] 
            + "&tabid=" + p_dataItem["RealPid"] + "&tabjsonname=" + p_dataItem["jsonname"]
            + "&isMenuIn=" + getID("isMenuIn").value + "' target=_blank>[열기]</a>";
        } else {
            return "<a href='index.php?RealPid=" + p_dataItem["RealPid"] 
            + "&jsonname=" + p_dataItem["jsonname"]
            + "&isMenuIn=" + getID("isMenuIn").value + "' target=_blank>[열기]</a>";
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