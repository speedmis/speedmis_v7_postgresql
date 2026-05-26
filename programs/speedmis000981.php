<?php 


function pageLoad() {

    global $ActionFlag;


        ?>
        <script>


function columns_templete(p_dataItem, p_aliasName) {
    if(p_aliasName=="widx") {
        if(p_dataItem["parent_idx"]!="" && p_dataItem["parent_idx"]!=null) {
            return "<a href='index.php?gubun=" + p_dataItem["parent_gubun"] + "&idx=" + p_dataItem["parent_idx"] 
            + "&tabid=" + p_dataItem["RealPid"] + "&tabrealpid_idx=" + p_dataItem["widx"]
            + "&isMenuIn=" + getID("isMenuIn").value + "' target=_blank>[열기]</a>?" + p_dataItem["widx"];
        } else {
            return "<a href='index.php?RealPid=" + p_dataItem["RealPid"] 
            + "&idx=" + p_dataItem["widx"]
            + "&isMenuIn=" + getID("isMenuIn").value + "' target=_blank>[열기]</a>?" + p_dataItem["widx"];
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