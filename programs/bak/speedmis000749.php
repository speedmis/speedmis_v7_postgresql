<?php

function pageLoad() {

    global $actionFlag;


    if($actionFlag!="view") { ?>
<style>
form .k-editor-inline.k-editor {
    max-height: inherit;
}
</style>
    <?php 
    }
    if($actionFlag=="view" || $actionFlag=="modify") { ?>
    <script>
    function thisLogic_toolbar() {
        //$("#btn_refWrite").text("답변");
        //$("#btn_refWrite").css("background", "#88f");
        //$("#btn_refWrite").css("color", "#fff");
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


    </script>
    <?php 
    
    ?>
    <script>
    function viewLogic_afterLoad() { //ok
        if(getID("ActionFlag").value=="write" && getID("idx").value!="0" && getID("idx").value!="") {
            $("form#frm input#title").val("RE: "+$("form#frm input#title").val());
        }
    }
    </script>
    <?php

}
//end pageLoad



function list_json_load() {
	
    global $base_root, $real_pid, $misJoinPid, $logicPid, $parent_idx;
    global $flag, $selField, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript;
    global $data, $key_aliasName, $child_alias, $selectQuery, $keyword, $menuName;
	global $_count;  //총갯수
	//$flag 는 목록조회시 'read'   내용조회시 'view'    수정시 'modify'   입력시 'write'
	//$selField 는 필터링을 하는 순간 발생하는 필드alias 값.

/*
	if($idx!='' && $flag!='modify') {
		$new_data = json_decode($data, true);
		$new_data[0]['contents'] = "JJJJ" . replace($new_data[0]['contents'],'저희','ㅋㅋ') . '11....<br>.....';
		$data = json_encode($new_data,JSON_UNESCAPED_UNICODE);
	}
*/
	
}
//end list_json_load



function save_updateBefore() {
    global $base_root, $real_pid, $misJoinPid, $logicPid, $parent_idx, $MS_MJ_MY;
    global $key_value, $table_m, $actionFlag, $saveList, $viewList, $deleteList, $updateList;
    global $upload_idx, $key_aliasName, $key_value;    //key_value 는 순수 post 값 = 입력시 공백또는 0. upload_idx 는 입력시 예상값
    //echo "JJJ --- ;;; [ { ---" .  "IDENT_CURRENT('" . $key_value . "')";
    //exit;
    //echo '<script>if(confirm("할까요?")) alert("zzz");</script>';
    //exit;
    if($actionFlag=="write") {
        if($key_value=="" || $key_value=="0") {
            //새로입력
            if($MS_MJ_MY=='MY') {
                $sql = "SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_NAME = '$table_m'";
            } else {
                $sql = "select case when IDENT_CURRENT('$table_m')=1 then (select count(*) from $table_m) else IDENT_CURRENT('$table_m') end+1";
            }                    
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
        $updateList["RealPid"] = $real_pid;
    }

}
//end save_updateBefore

?>