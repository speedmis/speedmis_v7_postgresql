<?php

function pageLoad() {

    global $actionFlag;
    global $real_pid, $misJoinPid, $logicPid, $parent_gubun, $parent_RealPid, $parent_idx;
    global $isAuthW, $isAuthR, $misSessionUserId, $misSessionIsAdmin, $ServerVariables_URL, $dbalias;


    if($actionFlag=="list" || $actionFlag=="view") {
        //아래 두 줄은 2022년 상반기까지만 필요한 쿼리.
        $sql = "
        update MisComments set sel_like=0 where sel_like is null;
        update MisComments set sel_hate=0 where sel_hate is null;
        ";
        execSql_gate($sql,$dbalias);
		$url = replace($ServerVariables_URL, "gubun=".RealPidIntoGubun($real_pid), "gubun=".RealPidIntoGubun("speedmis000990"));
		re_direct($url);
    }


    if($actionFlag!="list") { ?>
<style>
ul.k-tabstrip-items.k-reset {
    display: none;
}
body[isMenuIn="N"] div.k-content.k-state-active {
    height: calc(100vh - 82px);
    background-image: none;
}
body[isMenuIn="Y"] div.k-content.k-state-active {
    height: calc(100vh - 142px);
    background-image: none;
}
table.k-editor {
    min-height: 400px;
}
a#btn_view, li#btn_view_overflow {
    display: none!important;
}<?php if($actionFlag=="modify") { ?>
a#btn_save, li#btn_save_overflow {
    xdisplay: none!important;
}
<?php } ?>

</style>




    <?php 
    }
    if($actionFlag!="view") { ?>
<style>
form .k-editor-inline.k-editor {
    max-height: inherit;
}
.k-widget.k-header.k-tabstrip.k-floatwrap.k-tabstrip-top {
    border-top: 0;
    margin-top: 0px;
    padding-top: 4px;
}
body[isMenuIn="N"] div.k-content.k-state-active {
    height: calc(100vh - 82px);
}

</style>
    <?php 
    }
    if($actionFlag=="view" || $actionFlag=="modify") { ?>
    <script>
    function thisLogic_toolbar() {
        $("#btn_refWrite").css("display", "none");
    }
    </script>
    <?php }

    if($actionFlag=="list") { ?>
    <style>
body[ismainframe="Y"] td[comment="Y"] {
    background-image: none;
}

              
html .k-grid tr:hover {
  background: transparent;
}
 
html .k-grid tr.k-alt:hover {
  background: #f1f1f1;
}      

.k-grid > table > tbody > tr:hover,
.k-grid-content > table > tbody > tr:hover
{
        background: none!important;
}

.k-grid td.k-state-selected:hover, .k-grid tr.k-state-selected:hover {
    background-color: none!important;
    background-image: none!important;
}

.k-grid tr:hover {
    background-color: none!important;
    background-image: none!important;
}
.k-grid tr.k-state-selected:hover {
    background-color: none!important;
    background-image: none!important;
}
.k-state-selected {
    background-image: none!important;
    color: none!important;
    background-color: none!important;
    border-color: none!important;
}

.u_cbox, .u_cbox input, .u_cbox textarea, .u_cbox select, .u_cbox button, .u_cbox table {
    font-size: 12px;
    line-height: 1.25em;
}

.u_cbox ul, .u_cbox ol {
    list-style: none;
    margin: 0;
    padding: 0;
    font-family: AppleSDGothicNeo,'돋움',dotum,Helvetica,sans-serif;

}
.u_cbox .u_cbox_comment {
    overflow-anchor: none;
    list-style: none;
    display: list-item;
    text-align: -webkit-match-parent;

}
.u_cbox .u_cbox_comment_box {
    position: relative;
    border-bottom: 0px solid #e2e2e2;
}
.u_cbox .u_cbox_comment_box .u_cbox_area {
    padding: 5px 0;
    max-width: 1000px;
}
.u_cbox .u_cbox_comment_box .u_cbox_info {
    padding-bottom: 1px;
    height: 25px;
}
.u_cbox .u_cbox_name_area {
    font-size: 15px;
    line-height: 25px;
    font-weight: normal;
    vertical-align: top;
}
.u_cbox .u_cbox_name .u_cbox_nick_area {
    display: inline-block;
    overflow: hidden;
    float: left;
    max-width: 50%;
    white-space: nowrap;
    text-overflow: ellipsis;
}
.u_cbox .u_cbox_comment .u_cbox_text_wrap {
    overflow: hidden;
    font-size: 15px;
    line-height: 18px;
    word-break: break-all;
    word-wrap: break-word;
    margin-top: 6px;
    padding-top: 10px;
    margin-bottom: 6px;
    padding-bottom: 10px;
}
.u_cbox .u_cbox_info_base {
    position: relative;
    padding: 2px 0 0 0;
}
.u_cbox .u_cbox_date {
    display: block;
    font-size: 13px;
    font-family: tahoma,helvetica,sans-serif;
}
.u_cbox .u_cbox_attach {
    display: block;
    font-size: 13px;
    font-family: tahoma,helvetica,sans-serif;
    margin-top:5px;
    cursor: pointer;
}

.u_cbox a, .u_cbox a:link {
    background-color: transparent;
}
.u_cbox .u_cbox_btn_report, .u_cbox .u_cbox_btn_unhide {
    float: left;
    padding-top: 2px;
    vertical-align: top;
}
.u_cbox a {
    text-decoration: none;
}
.u_cbox .u_cbox_info_base .u_cbox_ico_bar {
    display: inline-block;
    float: left;
    position: relative;
    top: 1px;
    left: auto;
    height: 12px;
    margin: 0 7px 0 9px;
    vertical-align: top;
}
.u_cbox .u_cbox_info_base:after {
    display: block;
    clear: both;
    content: '';
}

.k-alt, .k-pivot-layout>tbody>tr:first-child>td:first-child, .k-resource.k-alt, .k-separator {
    background-color: inherit;
}
.k-draghandle.k-state-selected:hover, .k-ghost-splitbar-horizontal, .k-ghost-splitbar-vertical, .k-list>.k-state-highlight, .k-list>.k-state-selected, .k-marquee-color, .k-panel>.k-state-selected, .k-scheduler .k-scheduler-toolbar .k-state-selected, .k-scheduler .k-today.k-state-selected, .k-state-selected, .k-state-selected:link, .k-state-selected:visited, .k-tool.k-state-selected {
    background-color: inherit;
    border-color: inherit;
}
.k-grid td.k-state-selected:hover, .k-grid tr.k-state-selected:hover td, .k-state-selected {
    background-color: none!important;
    background: none!important;
}
.u_cbox .u_cbox_comment_box .u_cbox_area {
    border-bottom: 1px dotted #999;
}

    </style>

<script id="list-mobile-template" type="text/x-kendo-tmpl">
<div class="u_cbox">
<ul class="u_cbox_list"><li class="u_cbox_comment"><div class="u_cbox_comment_box"><div class="u_cbox_area">
<div class="u_cbox_info">
<span class="u_cbox_info_main">
<span class="u_cbox_name">
<span class="u_cbox_name_area">
<span class="u_cbox_nick_area">
<span class="k-state-hover">{table_wdaterQnusername} | {table_Station_NewNumQnstationname}
</span>
</span>
</span>
</span>
</span>
<span class="u_cbox_info_sub">
</span></div><div class="u_cbox_text_wrap">
<span class="u_cbox_contents name" data-lang="ko">
    {contents}
</span></div><div class="u_cbox_info_base">
<span class="u_cbox_date">{wdate}
</span>
<span class="u_cbox_attach" onclick="alert(1);$('a#btn_view').click();">zzz{boardattach1}
</span>
</div>

</div></div></li></ul>
</div>


  
</script>


    <script>

    //$('div#grid').before(getID('commentWrite').value);


    //스타일 등의 변형은 로딩후에 가능 = rowFunctionAfter

    function rowFunctionAfter_UserDefine(p_this) {
        <?php if($isAuthW=="Y" && $misSessionIsAdmin=="N") { ?>
        if(p_this.wdater=="<?php echo $misSessionUserId; ?>") {
            //쓰기 라인임.
        } else {
            $(getGridRowObj_idx(p_this[key_aliasName])).find("td.editorStyle").attr("stopEdit","true");
            $(getGridRowObj_idx(p_this[key_aliasName])).find("td.editorStyle").css("color","#08a");
        }
        <?php } ?>
    }
    </script>
    <?php }
    
    ?>
    <script>

    function listLogic_afterLoad_continue() {
        $('td[comment="Y"]').off('click');
    }


    function viewLogic_afterLoad_continue() { //ok
        $('a#btn_saveView').text('저장');
        if(getID("ActionFlag").value=="write") {
            $("form#frm input#RealPid").val("<?php echo $parent_RealPid; ?>");
        }
    }
    </script>
    <?php

}
//end pageLoad



function save_updateBefore() {
    global $base_root, $real_pid, $misJoinPid, $logicPid, $parent_idx;
    global $key_value, $table_m, $actionFlag, $saveList, $viewList, $deleteList, $updateList;
    global $upload_idx, $key_aliasName, $upload_key, $key_value;    //key_value 는 순수 post 값 = 입력시 공백또는 0. upload_idx 는 입력시 예상값
    if($actionFlag=="write") {

		$tempSql = "select case when MenuType='01' then g08 else (select m.g08 from MisMenuList m where m.RealPid=table_m.MisJoinPid) end 
		from MisMenuList table_m where RealPid='". $updateList["RealPid"] . "'";

        $updateList["table_m"] = onlyOneReturnSql($tempSql);
        $updateList["excel_idxname"] = $upload_key;
//print_r($tempSql);
//print_r($updateList);
//exit;

    }

}
//end save_updateBefore



function save_writeBefore() {

    global $full_siteID, $base_root, $real_pid, $misJoinPid, $logicPid, $parent_gubun, $parent_idx, $MS_MJ_MY, $MS_MJ_MY2, $externalDB;
    global $key_aliasName, $key_value, $actionFlag, $updateList, $sql, $sql_prev, $sql_next, $newIdx, $dbalias;
    
    //댓글의 parent_idx 에 대한 db 가 중요.
    if($MS_MJ_MY=='MY') {
        $sql = "select ifnull(dbalias,'') from MisMenuList where RealPid='" . get_logicPid($parent_idx) . "'";
    } else {
        $sql = "select isnull(dbalias,'') from MisMenuList where RealPid='" . get_logicPid(GubunIntoRealPid($parent_gubun)) . "'";
    }
    

    $parent_dbalias = onlyOnereturnSql($sql);
    
    if($parent_dbalias=='default') $parent_dbalias = '';
    else if($parent_dbalias=='' && $MS_MJ_MY=='MY') {
        $parent_dbalias = '1st';
    }
    if($parent_dbalias=='') $parent_MS_MJ_MY = $MS_MJ_MY;
    else {
        $temp = splitVB($externalDB[$parent_dbalias], "(@)");
        $parent_MS_MJ_MY = $temp[0];
    }
    
    if($MS_MJ_MY=='MY') {
        //검증필요
        $parent_field = onlyOnereturnSql("select Grid_Select_Field from MisMenuList_Detail where RealPid=
        (select case when MenuType='01' then RealPid else MisJoinPid end from MisMenuList 
        where RealPid='" . $updateList["RealPid"] . "')
        and (SortElement=1 and Grid_Columns_Width>-1 or SortElement>=2) order by SortElement limit 1");

        $sql_next = " 
        update MisComments set refidx=idx where idx=$newIdx or refidx is null;
        ";
        if($parent_MS_MJ_MY=='MY') $sql_next = $sql_next . " 
        select @cnt=count(*) from MisComments 
        where RealPid='" . $updateList["RealPid"] . "' and midx='" . $updateList["midx"] . "' and useflag=1;
        update " . $updateList["table_m"] . " set comment_count=@cnt from " . $updateList["table_m"] . " where $parent_field='" . $updateList["midx"] . "';
        ";
    } else {
        $parent_field = onlyOnereturnSql("select top 1 Grid_Select_Field from MisMenuList_Detail where RealPid=
        (select case when MenuType='01' then RealPid else MisJoinPid end from MisMenuList 
        where RealPid='" . $updateList["RealPid"] . "')
        and (SortElement=1 and Grid_Columns_Width>-1 or SortElement>=2) order by SortElement");

        $sql_next = " 
        update MisComments set refidx=idx where idx=$newIdx or refidx is null
        ";
        if($parent_MS_MJ_MY=='MS' || $parent_MS_MJ_MY=='MJ') $sql_next = $sql_next . " 
        declare @cnt int select @cnt=count(*) from MisComments 
        where RealPid='" . $updateList["RealPid"] . "' and midx='" . $updateList["midx"] . "' and useflag=1 
        update " . $updateList["table_m"] . " set comment_count=@cnt from " . $updateList["table_m"] . " table_m where $parent_field='" . $updateList["midx"] . "'
        ";

        
    }

//print_r($updateList);
//exit;
}
//end save_writeBefore



function save_writeAfter() {
    global $base_root, $real_pid, $misJoinPid, $logicPid, $parent_idx;
    global $key_aliasName, $key_value, $saveList, $saveUploadList, $viewList, $deleteList;
    global $Grid_Default, $actionFlag, $misSessionUserId, $newIdx;
    global $afterScript;

    $afterScript = 'location.href = replaceAll(replaceAll(replaceAll(replaceAll(location.href, "&idx=0", ""), "?idx=0&", "?"), "&ActionFlag=write", ""), "?ActionFlag=write&", "?")';
    
}
//end save_writeAfter



function save_deleteBefore() {

    global $full_siteID, $base_root, $real_pid, $misJoinPid, $logicPid, $parent_idx, $MS_MJ_MY;
    global $key_aliasName, $key_value, $actionFlag, $sql_prev, $sql, $sql_next, $deleteList, $parent_gubun;


    if($MS_MJ_MY=='MY') {
        $parent_field = onlyOnereturnSql("select Grid_Select_Field from MisMenuList_Detail where RealPid=
        (select case when MenuType='01' then RealPid else MisJoinPid end from MisMenuList 
        where RealPid='" . GubunIntoRealPid($parent_gubun) . "')
        and Grid_Columns_Width>-1 order by SortElement limit 1");

        $parent_table = onlyOnereturnSql("select g08 from MisMenuList where RealPid=
        (select case when MenuType='01' then RealPid else MisJoinPid end from MisMenuList 
        where RealPid='" . GubunIntoRealPid($parent_gubun) . "')");

        $sql_next = " 
        update MisComments set useflag=0 from MisComments m, (
            select refidx from MisComments where refidx in 
            (select refidx from MisComments where idx=refidx and useflag=0 and concat(midx,RealPid)=(select concat(midx,RealPid) 
            from MisComments where idx in ($deleteList)))
            ) d where m.refidx=d.refidx;

        select @cnt=COUNT(*) from MisComments where useflag=1 and midx = (
         select midx from  MisComments  where idx in ($deleteList));
        update $parent_table set comment_count=@cnt where $parent_field=(select midx from MisComments where idx in ($deleteList));
        ";
    } else {
        $parent_field = onlyOnereturnSql("select top 1 Grid_Select_Field from MisMenuList_Detail where RealPid=
        (select case when MenuType='01' then RealPid else MisJoinPid end from MisMenuList 
        where RealPid='" . GubunIntoRealPid($parent_gubun) . "')
        and Grid_Columns_Width>-1 order by SortElement");

        $parent_table = onlyOnereturnSql("select g08 from MisMenuList where RealPid=
        (select case when MenuType='01' then RealPid else MisJoinPid end from MisMenuList 
        where RealPid='" . GubunIntoRealPid($parent_gubun) . "')");

        $sql_next = " 
		update MisComments set useflag=0 from MisComments m join (
            select refidx from MisComments where refidx in 
            (select refidx from MisComments where idx=refidx and useflag=0 and convert(varchar(5),midx)+RealPid=(select convert(varchar(5),midx)+RealPid 
            from MisComments where idx in ($deleteList)))
            ) d on m.refidx=d.refidx where m.refidx=d.refidx
            
        declare @cnt int select @cnt=COUNT(*) from MisComments where useflag=1 and midx = (
         select midx from  MisComments  where idx in ($deleteList))
        update $parent_table set comment_count=@cnt where $parent_field=(select midx from MisComments where idx in ($deleteList))
        ";
        
    }
}
//end save_deleteBefore

?>