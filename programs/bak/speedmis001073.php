<?php

function pageLoad() {

    global $actionFlag, $misSessionIsAdmin;


    if($actionFlag!="view") { ?>
<style>
form .k-editor-inline.k-editor {
    max-height: inherit;
}
	div#round_b_ref { display: none; }
</style>
    <?php 
    }
    if($misSessionIsAdmin=="Y" && ($actionFlag=="view" || $actionFlag=="modify")) { ?>
    <script>
    function thisLogic_toolbar() {
        $("#btn_refWrite").text("답변");
        $("#btn_refWrite").css("background", "#88f");
        $("#btn_refWrite").css("color", "#fff");
    }
    </script>
    <?php }

?>
    <script>

function columns_templete(p_dataItem, p_aliasName) {
    if(p_aliasName=="title") {
        if(p_dataItem["b_level"]*1 > 0) {
            return "<span style='margin-left:"+(p_dataItem["b_level"] * 15)+"px;'></span>" + "┗" + p_dataItem[p_aliasName];
        } else return p_dataItem[p_aliasName];
    } else {
        return p_dataItem[p_aliasName];
    }	
}


    function viewLogic_afterLoad_continue() {
        if(getID("ActionFlag").value=="write" && getID("idx").value!="0" && getID("idx").value!="") {
            $("form#frm input#title").val("RE: "+$("form#frm input#title").val());
        }

        if(getID("ActionFlag").value=="write") {


            var obj = $('div#virtual_fieldQnmisPildokMem').data('kendoTreeView').dataSource;
            d1 = obj._data.length;
            for(i=0;i<d1;i++) {
                d2 = obj._data[i].items.length;
                for(j=0;j<d2;j++) {
                    if($('#MisSession_UserID')[0].value=='gadmin' && InStr(obj._data[i].items[j].text,"최고관리자")==0 
                    || $('#MisSession_UserID')[0].value!='gadmin' && InStr(obj._data[i].items[j].text,"최고관리자")>0) {
                        $('input#_'+obj._data[i].items[j].uid).click();
                    }
                }
            }



        }
    }
    </script>
    <?php

}
//end pageLoad



function change_pildok_member() {

	global $pildok_sql, $result, $resultCnt, $misSessionUserId;

	$pildok_sql = replace($pildok_sql, "where table_m.delchk<>'D'", "where table_m.delchk<>'D' and table_m.uniqueNum in ('gadmin','$misSessionUserId')");
	$pildok_sql = replace($pildok_sql, "table_m.UniqueNum not in ('gadmin')", "table_m.UniqueNum not in ('xxxgadmin')");
	
	//$result[$resultCnt]["Grid_FormGroup"] = "필독멤버-대표ID";
    //$result[$resultCnt]["Grid_Alim"] = "단계가 무소속인 경우 제외되며, 대표ID 만 표시됩니다.";
    //echo $pildok_sql;
}
//end change_pildok_member



function save_updateBefore() {
    global $base_root, $real_pid, $misJoinPid, $logicPid, $parent_idx, $MS_MJ_MY;
    global $key_value, $table_m, $actionFlag, $saveList, $viewList, $deleteList, $updateList;
    global $upload_idx, $key_aliasName, $key_value;    //key_value 는 순수 post 값 = 입력시 공백또는 0. upload_idx 는 입력시 예상값
    //echo "JJJ --- ;;; [ { ---" .  "IDENT_CURRENT('" . $key_value . "')";
    //exit;
    if($actionFlag=="write") {
        if($MS_MJ_MY=='MY') {
            if($key_value=="" || $key_value=="0") {
                //새로입력
                $sql = "SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_NAME = '$table_m'";
                $updateList["b_ref"] = onlyOnereturnSql($sql);
            } else {
                //답변일 경우.
                $sql = "select max(b_step)+1 from " . $table_m . " where b_ref='" . $updateList["b_ref"] . "'";
                $updateList["b_step"] = onlyOnereturnSql($sql);
                $sql = "select b_level+1 from " . $table_m . " where idx='" . $key_value . "'";
                $updateList["b_level"] = onlyOnereturnSql($sql);
                //echo "JJJ --- ;;; [ { ---" .  "IDENT_CURRENT('" . $sql . "')";
                //exit;
            }
        } else {
            if($key_value=="" || $key_value=="0") {
                //새로입력
                $sql = "select case when IDENT_CURRENT('$table_m')=1 then (select count(*) from $table_m) else IDENT_CURRENT('$table_m') end+1";
                $updateList["b_ref"] = onlyOnereturnSql($sql);
            } else {
                //답변일 경우.
                $sql = "select max(b_step)+1 from " . $table_m . " where b_ref='" . $updateList["b_ref"] . "'";
                $updateList["b_step"] = onlyOnereturnSql($sql);
                $sql = "select b_level+1 from " . $table_m . " where idx='" . $key_value . "'";
                $updateList["b_level"] = onlyOnereturnSql($sql);
                //echo "JJJ --- ;;; [ { ---" .  "IDENT_CURRENT('" . $sql . "')";
                //exit;
            }
        }
        $updateList["RealPid"] = $real_pid;
    }

}
//end save_updateBefore

?>