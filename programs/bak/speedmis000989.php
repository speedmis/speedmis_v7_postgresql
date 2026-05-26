<?php

function pageLoad() {

    global $actionFlag, $misSessionIsAdmin;

    if(111==111) { 
        ?>
        <script>
        //사용자 정의 함수 = 함수 이름은 변형하면 안됨. 내용만. 없어도 됨. ==============================
        //데이타의 변형은 즉시 가능 = rowFunction
        
        function rowFunction_UserDefine(p_this) {
            //p_this.MenuName = p_this.depth + p_this.LanguageCode; 
            //p_this.AutoGubun = 
            //"<a href=index.asp?gubun=" + p_this.idx + "&isMenuIn=Y target=_blank>[Go]</a> <a id='aid_" + p_this.idx + "' href=index.asp?RealPid=speedmis000266&idx=" + p_this.idx + "&isMenuIn=Y target=_blank>[Source]</a>?" 
            //+ p_this.AutoGubun; 
        }
        
        
        function columns_templete(p_dataItem, p_aliasName) {
            if(p_aliasName=="MenuName") {
                return "<a class='k-button' href='index.php?gubun=" + p_dataItem["idx"] + "&isMenuIn=Y' target='_blank'>연결</a> " + p_dataItem[p_aliasName]; 

            } else {
                return p_dataItem[p_aliasName];
            }	
        }
        </script>
        <?php 
    }




    if($misSessionIsAdmin=="Y" && ($actionFlag=="view" || $actionFlag=="modify")) { 
    ?>
<script>
function thisLogic_toolbar() {
    $("#btn_1").text("완전복제");
    $("#btn_1").css("background", "#88f");
    $("#btn_1").css("color", "#fff");
    $("#btn_1").click( function() {
        getID("app").value = "완전복제";
        read_idx(getID("idx").value);
        getID("app").value = "";
    });
}
</script>
    <?php 
    }
}
//end pageLoad



function list_json_init() {
    global $real_pid, $misJoinPid, $logicPid, $parent_idx;
    global $flag, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript;
    global $full_siteID, $base_root, $MS_MJ_MY, $isnull, $addDir;
    global $misSessionUserId, $base_db;
    

    if($flag=="view" || $flag=="modify") { 

        if($app=="완전복제") {
            if($MS_MJ_MY=='MY') {

                $appSql = "

                set @full_siteID := '" . $full_siteID . "';
                set @RealPid := '" . $idx . "';
                set @newRealPid := concat(@full_siteID, formatnums((SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_NAME = 'MisMenuList' AND TABLE_SCHEMA='$base_db'), '000000'));
				set @newRealPid2 := concat(@full_siteID, formatnums((SELECT MAX(idx) FROM MisMenuList)+1, '000000'));
				set @newRealPid = if(@newRealPid<@newRealPid2,@newRealPid2,@newRealPid);
                set @wdater := '" . $misSessionUserId . "';
                
                insert into MisMenuList (RealPid, MenuName, isMenuHidden, new_gidx, AuthCode, gidx, wgidx, agidx, AllListMember, wAllListMember, MenuType, upRealPid, AddURL, AutoGubun, SortG2, SortG4, SortG6
, wdater, isUsePrint, isUseForm, g01, g02, g03, g04, g05, g06, g07, g08, g09, g10, g11, g12, g14, dbalias, addLogic, addLogic_treat, LanguageCode, depth, MisJoinPid)
select @newRealPid, concat(MenuName,' 사본'), isMenuHidden, new_gidx, AuthCode, gidx, wgidx, agidx, AllListMember, wAllListMember, MenuType, upRealPid, AddURL, AutoGubun, SortG2, SortG4, SortG6
, @wdater, isUsePrint, isUseForm, g01, g02, g03, g04, g05, g06, g07, g08, g09, g10, g11, g12, g14, dbalias, addLogic, addLogic_treat, LanguageCode, depth, MisJoinPid
from MisMenuList where RealPid=@RealPid ;
                
                
                
insert into MisMenuList_Detail (RealPid, SortElement, Grid_Select_Field, Grid_Select_Tname, aliasName, Grid_View_Fixed, Grid_Enter, Grid_View_XS, Grid_View_SM, Grid_View_MD, Grid_View_LG, Grid_View_Hight, Grid_View_Class, Grid_Columns_Title, Grid_Columns_Width, Grid_Schema_Type
, Grid_Items, Grid_Schema_Validation
, Grid_Align, Grid_Orderby, Grid_MaxLength, Grid_Templete, Grid_Default, Grid_GroupCompute, Grid_CtlName, Grid_IsHandle, Grid_ListEdit, Grid_PrimeKey, Grid_Alim, Grid_Pil, Grid_FormGroup, wdater)
SELECT @newRealPid, SortElement, Grid_Select_Field, Grid_Select_Tname, aliasName, Grid_View_Fixed, Grid_Enter, Grid_View_XS, Grid_View_SM, Grid_View_MD, Grid_View_LG, Grid_View_Hight, Grid_View_Class, Grid_Columns_Title, Grid_Columns_Width, Grid_Schema_Type
, Grid_Items, Grid_Schema_Validation
, Grid_Align, Grid_Orderby, Grid_MaxLength, Grid_Templete, Grid_Default, Grid_GroupCompute, Grid_CtlName, Grid_IsHandle, Grid_ListEdit, Grid_PrimeKey, Grid_Alim, Grid_Pil, Grid_FormGroup, @wdater
  FROM MisMenuList_Detail where RealPid=@RealPid order by SortElement, idx;
                  ";

                execSql($appSql);

                $appSql = "call MisUser_Authority_Proc ('" . $full_siteID . "','speedmis000001'); ";
                execSql($appSql);
                
                //  echo $appSql;
                $sql = "select RealPid from MisMenuList order by idx desc limit 1;";
                $newRealPid = onlyOnereturnSql($sql);

            } else {
                $appSql = "

declare @full_siteID nvarchar(8)
declare @RealPid nvarchar(20)
declare @newRealPid nvarchar(20)
declare @wdater nvarchar(50)
set @full_siteID = '" . $full_siteID . "'
set @RealPid='" . $idx . "'
set @newRealPid = @full_siteID + dbo.formatnums(IDENT_CURRENT('MisMenuList') + 1, '000000')
set @wdater = '" . $misSessionUserId . "'

insert into MisMenuList (RealPid, MenuName, isMenuHidden, new_gidx, AuthCode, gidx, wgidx, agidx, AllListMember, wAllListMember, MenuType, upRealPid, AddURL, AutoGubun, SortG2, SortG4, SortG6
, wdater, isUsePrint, isUseForm, g01, g02, g03, g04, g05, g06, g07, g08, g09, g10, g11, g12, g14, dbalias, addLogic, addLogic_treat, LanguageCode, depth, MisJoinPid)
select @newRealPid, MenuName+' 사본', isMenuHidden, new_gidx, AuthCode, gidx, wgidx, agidx, AllListMember, wAllListMember, MenuType, upRealPid, AddURL, AutoGubun, SortG2, SortG4, SortG6
, @wdater, isUsePrint, isUseForm, g01, g02, g03, g04, g05, g06, g07, g08, g09, g10, g11, g12, g14, dbalias, addLogic, addLogic_treat, LanguageCode, depth, MisJoinPid
from MisMenuList where RealPid=@RealPid 



insert into MisMenuList_Detail (RealPid, SortElement, Grid_Select_Field, Grid_Select_Tname, aliasName, Grid_View_Fixed, Grid_Enter, Grid_View_XS, Grid_View_SM, Grid_View_MD, Grid_View_LG, Grid_View_Hight, Grid_View_Class, Grid_Columns_Title, Grid_Columns_Width, Grid_Schema_Type
, Grid_Items, Grid_Schema_Validation
, Grid_Align, Grid_Orderby, Grid_MaxLength, Grid_Templete, Grid_Default, Grid_GroupCompute, Grid_CtlName, Grid_IsHandle, Grid_ListEdit, Grid_PrimeKey, Grid_Alim, Grid_Pil, Grid_FormGroup, wdater)
SELECT @newRealPid, SortElement, Grid_Select_Field, Grid_Select_Tname, aliasName, Grid_View_Fixed, Grid_Enter, Grid_View_XS, Grid_View_SM, Grid_View_MD, Grid_View_LG, Grid_View_Hight, Grid_View_Class, Grid_Columns_Title, Grid_Columns_Width, Grid_Schema_Type
, Grid_Items, Grid_Schema_Validation
, Grid_Align, Grid_Orderby, Grid_MaxLength, Grid_Templete, Grid_Default, Grid_GroupCompute, Grid_CtlName, Grid_IsHandle, Grid_ListEdit, Grid_PrimeKey, Grid_Alim, Grid_Pil, Grid_FormGroup, @wdater
  FROM MisMenuList_Detail where RealPid=@RealPid order by SortElement, idx
  ";
                execSql($appSql);

                $appSql = "exec MisUser_Authority_Proc '" . $full_siteID . "','speedmis000001' ";
                execSql($appSql);


                $sql = "select top 1 RealPid from MisMenuList order by idx desc";
                $newRealPid = onlyOnereturnSql($sql);
            }
            
            $nowRealPid = $idx;
            $destination = $base_root . "/_mis_addLogic/" . $nowRealPid . ".php";
            $newDestination = $base_root . "/_mis_addLogic/" . $newRealPid . ".php";
            $addLogic = onlyOnereturnSql("select $isnull(addLogic,'') from MisMenuList where RealPid=N'" .  $newRealPid . "'");

            $destination_treat = $base_root . "/_mis_addLogic/" . $nowRealPid . "_treat.php";
            $newDestination_treat = $base_root . "/_mis_addLogic/" . $newRealPid . "_treat.php";
            $addLogic_treat = onlyOnereturnSql("select $isnull(addLogic_treat,'') from MisMenuList where RealPid=N'" .  $newRealPid . "'");

            if (file_exists($newDestination)) unlink($newDestination);
            if (file_exists($newDestination_treat)) unlink($newDestination_treat);
            //echo '$destination=' . $destination;
            //echo '$newDestination=' . $newDestination;
            //exit;

            if(file_exists($destination)) {
                copy($destination, $newDestination);
            } else if($addLogic!="") {
                $addLogic = replace($addLogic, '@_' . 'q;', '?');
                WriteTextFile($newDestination, $addLogic);
            }
            if(file_exists($destination_treat)) {
                copy($destination_treat, $newDestination_treat);
            } else if($addLogic_treat!="") {
                $addLogic_treat = replace($addLogic_treat, '@_' . 'q;', '?');
                WriteTextFile($newDestination_treat, $addLogic_treat);
            }
            

            $resultCode = "success";
            $resultMessage = "프로그램 복제가 완료되었습니다.";
            setcookie("newLogin", "Y", 0, "/");
            $afterScript = "parent.location.href = replaceAll(replaceAll(parent.location.href, '#', ''), '&idx='+getID('idx').value, '');";

        }
    }
}
//end list_json_init



function list_json_load() {
    global $base_root, $real_pid, $misJoinPid, $logicPid, $parent_idx, $addDir;
    global $flag, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript;
    global $data, $key_aliasName, $parent_alias, $selectQuery, $keyword, $menuName;

    if($flag=="view" || $flag=="modify") { 
        if($idx!="") {

            $db_addLogic = json_decode($data)[0]->addLogic;
            $file_addLogic = ReadTextFile($base_root . "/_mis_addLogic/" . $idx . ".php");
            if($file_addLogic!="") {
                $new_data = json_decode($data);
                $new_data[0]->addLogic = $file_addLogic;
                $data = json_encode($new_data);
            } else if($db_addLogic!="") {
                $db_addLogic = replace($db_addLogic, '@_' . 'q;', '?');
                $new_data = json_decode($data);
                $new_data[0]->addLogic = $db_addLogic;
                $data = json_encode($new_data);
            }

            $db_addLogic_treat = json_decode($data)[0]->addLogic_treat;
            $file_addLogic_treat = ReadTextFile($base_root . "/_mis_addLogic_treat/" . $idx . ".php");
            if($file_addLogic_treat!="") {
                $new_data = json_decode($data);
                $new_data[0]->addLogic_treat = $file_addLogic_treat;
                $data = json_encode($new_data);
            } else if($db_addLogic_treat!="") {
                $db_addLogic_treat = replace($db_addLogic_treat, '@_' . 'q;', '?');
                $new_data = json_decode($data);
                $new_data[0]->addLogic_treat = $db_addLogic_treat;
                $data = json_encode($new_data);
            }


        }
    }
}
//end list_json_load



function save_updateReady() {
    global $base_root, $real_pid, $misJoinPid, $logicPid, $parent_idx, $addDir;
    global $key_aliasName, $key_value, $saveList, $viewList, $deleteList;
    if($key_value!="") {

        $addLogic = $saveList["addLogic"];
        $destination = $base_root . "/_mis_addLogic/" . $key_value . ".php";
        if($addLogic!="") {
            WriteTextFile($destination, $addLogic);
        } else {
            if (file_exists($destination)) unlink($destination);
        }
        $addLogic = replace($addLogic, '?', '@_' . 'q;');
        $saveList["addLogic"] = $addLogic;
        
        $addLogic_treat = $saveList["addLogic_treat"];
        $destination_treat = $base_root . "/_mis_addLogic/" . $key_value . "_treat.php";
        if($addLogic_treat!="") {
            WriteTextFile($destination_treat, $addLogic_treat);
        } else {
            if (file_exists($destination_treat)) unlink($destination_treat);
        }
        $addLogic_treat = replace($addLogic_treat, '?', '@_' . 'q;');
        $saveList["addLogic_treat"] = $addLogic_treat;

    }
}
//end save_updateReady

?>