<?php

function misMenuList_change() {
    
	//misMenuList 테이블에 의한 설정값인 $result 를 바꾸는게 이 함수의 핵심기능
    global $actionFlag, $gubun, $parent_idx, $real_pid, $logicPid, $result;
	global $misSessionPositionCode, $flag,$externalDB, $addDir, $isnull;

	//만약 $result 의 값이 궁금하면 아래 주석을 해제하고 새로고침 해볼 것(주의:에러발생).
	//print_r($result);
	if($real_pid=='speedmis000766') {
		$result[0]['g09'] = "and table_m.MenuType = '01' and table_m.RealPid in (select MisJoinPid from dbo.MisMenuList where $isnull(MisJoinPid,'') <> '' and MenuType = '06')";
    }
    
    $search_index = array_search("dbalias", array_column($result, 'aliasName'));
    //print_r($externalDB);
    $externalDB_json = '[';
    foreach ($externalDB as $value => $text) {
        if($externalDB_json!='[') $externalDB_json = $externalDB_json . ',';
        $long_text = $value . ' - ' . splitVB($text,'(@)')[0] . ':' . splitVB($text,'(@)')[1] . '/' . splitVB($text,'(@)')[2] . '/' . splitVB($text,'(@)')[3];
        $externalDB_json = $externalDB_json . '{"value":"' . $value . '","text":"' . $long_text . '"}';
     }
     $externalDB_json = $externalDB_json . ']';
    $result[$search_index]["Grid_Items"] = $externalDB_json;
}
//end misMenuList_change



function pageLoad() {

    global $actionFlag, $kendoTheme, $misSessionIsAdmin, $addDir, $MS_MJ_MY, $real_pid, $idx;

        ?>
        <style>
			
input#toolbarTxt_addLogic {
    min-width: 250px;
}

label[for="addLogic"] {
    z-index: 99;
}
label[for="addLogic"] a {
    font-size: 14px;
}
label[for="addLogic"] a:before {
    content: " | ";
}
        </style>
        
        <script>

			
			if(getUrlParameter('devQueryOn')=='Y') setCookie('devQueryOn','Y');
			
			
function coding_addLogic() {
    if($('input#ActionFlag')[0].value=='modify') {
        alert('소스의 안전한 보존을 위해 조회 모드에서 열어야 합니다.');
        return false;
    }
    url = "index.php?RealPid=speedmis001076&idx="+$('input#idx')[0].value+"&ActionFlag=modify";
    parent_popup_jquery(url, $('input#MenuName')[0].value+ "[" + $('input#idx')[0].value + "] 의 ");
}


        //사용자 정의 함수 = 함수 이름은 변형하면 안됨. 내용만. 없어도 됨. ==============================
        //데이타의 변형은 즉시 가능 = rowFunction
        
        function rowFunction_UserDefine(p_this) {
            //p_this.MenuName = p_this.depth + p_this.LanguageCode; 
            //p_this.AutoGubun = 
            //"<a href=index.asp?gubun=" + p_this.idx + "&isMenuIn=Y target=_blank>[Go]</a> <a id='aid_" + p_this.idx + "' href=index.asp?RealPid=speedmis000266&idx=" + p_this.idx + "&isMenuIn=Y target=_blank>[Source]</a>?" 
            //+ p_this.AutoGubun; 
        }
        
                
        function columns_templete(p_dataItem, p_aliasName) {
            if(p_aliasName=="table_mQmidx") {
                return "<a class='k-button' href='index.php?gubun=" + p_dataItem["idx"] + "&isMenuIn=Y' target='_blank'>연결</a>"
            + " " + p_dataItem[p_aliasName]; 

            } else if(p_aliasName=="zinswaeyangsikURL" || p_aliasName=="virtual_fieldQnprint_apply") {
                if(p_dataItem["addLogic_print"]!='') return "<a class='k-button' href='/_mis_addLogic/" + p_dataItem["RealPid"] + "_print.html' target='_blank'>연결</a>"; 
				else return '';
            } else {
                return p_dataItem[p_aliasName];
            }
        }
        
        //스타일 등의 변형은 로딩후에 가능 = rowFunctionAfter
        /*
        function rowFunctionAfter_UserDefine(p_this) {
        
            $(getCellObj_idx(p_this[key_aliasName], "MenuName"))[0].innerHTML = 
            "<a class='k-button' href='index.php?gubun=" + p_this.idx + "&isMenuIn=Y' target='_blank'>연결</a>"
            + "?" + p_this.MenuName; 
        
        }
        */
        </script>
        <?php 




    if($misSessionIsAdmin=="Y") { 
    ?>
<script>
function thisLogic_toolbar() {

    <?php if($actionFlag=="view" || $actionFlag=="modify") {  ?>
    $("#btn_1").text("완전복제");
    $("#btn_1").css("background", "#88f");
    $("#btn_1").css("color", "#fff");
    $("#btn_1").click( function() {
		if(confirm("해당 프로그램을 완전복제하여 새로 생성하시겠습니까?")==false) {
			return false;
		}
        getID("app").value = "완전복제";
        read_idx(getID("idx").value);
        getID("app").value = "";
    });
	
	$("a#btn_save,a#btn_saveView").click( function() {
		
		$('input#checkSubmit').click();	//이것을 해야 정상적인 code mirror 값을 가져올 수 있음.

		url = "addLogic_treat.php?RealPid=<?=$real_pid?>&question=file_php&file_RealPid=<?=$idx?>";
		file_php = ajax_url_return(url);
		web_php = $('textarea#addLogic')[0].value;
		//alert(file_php);
		//alert(web_php);
		if(file_php!=web_php && file_php!='') {
			if(!confirm('브라우저에 로딩된 서버로직(PHP) 와 실제 PHP 파일의 소스가 서로 다릅니다. 저장하실 경우 브라우저의 서버로직으로 최종저장됩니다. 진행할까요?')) {
				setTimeout( function() { displayLoadingOff(); }, 500);
				return false;
			}
		}
	});
    <?php }  ?>
    <?php if($actionFlag=="list") {  ?>
    $("#btn_2").text("PHP전체동기화");
    $("#btn_2").css("background", "#88f");
    $("#btn_2").css("color", "#fff");
    $("#btn_2").click( function() {
		if(confirm("PHP전체동기화 를 진행하시면, /_mis_addLogic/ 에 저장된 PHP 소스를 DB에도 일괄저장시키고 파일일자가 최신으로 바뀝니다. \n진행하지 않아도 특별한 문제가 발생하는 것은 아니며, 파일정리차원에서 필요한 작업입니다. 진행하시겠습니까?")==false) return false;
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "PHP전체동기화";
        $("#grid").data("kendoGrid").dataSource.read();
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "";
    });

    <?php }  ?>

}


function viewLogic_afterLoad() { //ok
    if($('input#document_load_once_event').val()=='N') {
        if($('<div>'+$('label[for="addLogic"]')[0].innerHTML+'</div>').find('a').length==0) {
            $('label[for="addLogic"]')[0].innerHTML = $('label[for="addLogic"]')[0].innerText 
            + ' <a href="javascript:;" onclick="coding_addLogic();">서버로직 분할편집하기</a>';
        }
    }
}

</script>
    <?php 
    }



    if($actionFlag=="modify" || $actionFlag=="write") { 

        ?>
        

<style>
div#round_addLogic {
    padding-right: 40px;
}
.CodeMirror {
    height: calc(100vh - 168px);
}
@media (min-width: 1200px) {
    div#round_addLogic {
        width: calc(100% - 50px);
    }
}
</style>

        <?php 
    }




}
//end pageLoad



function list_json_init() {
    global $real_pid, $misJoinPid, $logicPid, $parent_idx;
    global $flag, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript;
    global $full_siteID, $base_root, $base_db, $addDir, $MS_MJ_MY, $isnull, $dbalias;
    global $misSessionUserId;
    

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

            $destination_print = $base_root . "/_mis_addLogic/" . $nowRealPid . "_print.html";
            $newDestination_print = $base_root . "/_mis_addLogic/" . $newRealPid . "_print.html";
            $addLogic_print = onlyOnereturnSql("select $isnull(addLogic_print,'') from MisMenuList where RealPid=N'" .  $newRealPid . "'");

            if (file_exists($newDestination)) unlink($newDestination);
            if (file_exists($newDestination_print)) unlink($newDestination_print);
            //echo '$destination=' . $destination;
            //echo '$newDestination=' . $newDestination;
            //exit;

            if(file_exists($destination)) {
                copy($destination, $newDestination);
            } else if($addLogic!="") {
                $addLogic = replace($addLogic, '@_' . 'q;', '?');
                WriteTextFile($newDestination, $addLogic);
            }
            if(file_exists($destination_print)) {
                copy($destination_print, $newDestination_print);
            } else if($addLogic_print!="") {
                WriteTextFile($newDestination_print, $addLogic_print);
            }

            $resultCode = "success";
            $resultMessage = "프로그램 복제가 완료되었습니다.";
            setcookie("newLogin", "Y", 0, "/");
            $afterScript = "parent.location.href = replaceAll(replaceAll(parent.location.href, '#', ''), '&idx='+getID('idx').value, '');";

        }
    }



    if($flag=="read") { 

        if($app=="PHP전체동기화") {

            if($MS_MJ_MY=='MY') {

                //misjoin:06, 서버로직만:22
                $appSql = "select * from MisMenuList where useflag='1' and MenuType in ('01','06','22') order by idx ";

                $d_data = allreturnSql($appSql);

                $d_cnt = count($d_data);

                for($i=0;$i<$d_cnt;$i++) {

                    $d_RealPid = $d_data[$i]['RealPid'];
                    $d_MenuType = $d_data[$i]['MenuType'];
                    $d_addLogic = $d_data[$i]['addLogic'];
                    $d_addLogic_print = $d_data[$i]['addLogic_print'];
                    $d_addLogic_treat = $d_data[$i]['addLogic_treat'];
                    $destination = $base_root . "/_mis_addLogic/" . $d_RealPid . ".php";
                    $destination_print = $base_root . "/_mis_addLogic/" . $d_RealPid . "_print.html";
                    $destination_treat = $base_root . "/_mis_addLogic/" . $d_RealPid . "_treat.php";
                    $f_addLogic = ReadTextFile($destination);
                    $f_addLogic_print = ReadTextFile($destination_print);
                    $f_addLogic_treat = ReadTextFile($destination_treat);

                    //파일에 내용이 있으면 DB 에 저장, 파일에 내용이 없고 DB 에 있으면 DB 내용을 파일로 생성
                    if($d_MenuType=='01' || $d_MenuType=='22') {
                        if($f_addLogic!='') {
                            $appSql = " update MisMenuList set addLogic=N'" . sqlValueReplace(replace($f_addLogic, '?', '@_' . 'q;')) . "' where RealPid='$d_RealPid';";
                            execSql_gate($appSql, $dbalias);
                            WriteTextFile($destination, $f_addLogic);
                        } else if($d_addLogic!='') {
                            WriteTextFile($destination, replace($d_addLogic, '@_' . 'q;', '?'));
                        }
                        if($d_MenuType=='22') {
                            if($f_addLogic_treat!='') {
                                $appSql = " update MisMenuList set addLogic_treat=N'" . sqlValueReplace(replace($f_addLogic_treat, '?', '@_' . 'q;')) . "' where RealPid='$d_RealPid';";
                                execSql_gate($appSql, $dbalias);
                                WriteTextFile($destination_treat, $f_addLogic_treat);
                            } else if($d_addLogic_treat!='') {
                                WriteTextFile($destination_treat, replace($d_addLogic_treat, '@_' . 'q;', '?'));
                            }
                        }
                    }
                    if($d_MenuType=='01' || $d_MenuType=='06') {
                        if($f_addLogic_print!='') {
                            if($d_MenuType=='01') {       //mis join 의 경우 DB저장은 안함.
                                $appSql = " update MisMenuList set addLogic_print=N'" . sqlValueReplace($f_addLogic_print) . "' where RealPid='$d_RealPid';";
                                execSql_gate($appSql, $dbalias);
                            }
                            WriteTextFile($destination_print, $f_addLogic_print);
                        } else if($d_addLogic_print!='' && $d_MenuType=='01') {     //mis join 의 경우 DB저장은 안함.
                            WriteTextFile($destination_print, $d_addLogic_print);       
                        }
                    }
                }

            } else {

                //misjoin:06, 서버로직만:22
                $appSql = "select * from MisMenuList where useflag='1' and MenuType in ('01','06','22') order by idx ";

                $d_data = allreturnSql($appSql);

                $d_cnt = count($d_data);

                for($i=0;$i<$d_cnt;$i++) {

                    $d_RealPid = $d_data[$i]['RealPid'];
                    $d_MenuType = $d_data[$i]['MenuType'];
                    $d_addLogic = $d_data[$i]['addLogic'];
                    $d_addLogic_print = $d_data[$i]['addLogic_print'];
                    $d_addLogic_treat = $d_data[$i]['addLogic_treat'];
                    $destination = $base_root . "/_mis_addLogic/" . $d_RealPid . ".php";
                    $destination_print = $base_root . "/_mis_addLogic/" . $d_RealPid . "_print.html";
                    $destination_treat = $base_root . "/_mis_addLogic/" . $d_RealPid . "_treat.php";
                    $f_addLogic = ReadTextFile($destination);
                    $f_addLogic_print = ReadTextFile($destination_print);
                    $f_addLogic_treat = ReadTextFile($destination_treat);

                    //파일에 내용이 있으면 DB 에 저장, 파일에 내용이 없고 DB 에 있으면 DB 내용을 파일로 생성
                    if($d_MenuType=='01' || $d_MenuType=='22') {
                        if($f_addLogic!='') {
                            $appSql = " update MisMenuList set addLogic=N'" . sqlValueReplace(replace($f_addLogic, '?', '@_' . 'q;')) . "' where RealPid='$d_RealPid';";
                            execSql_gate($appSql, $dbalias);
                            WriteTextFile($destination, $f_addLogic);
                        } else if($d_addLogic!='') {
                            WriteTextFile($destination, replace($d_addLogic, '@_' . 'q;', '?'));
                        }
                        if($d_MenuType=='22') {
                            if($f_addLogic_treat!='') {
                                $appSql = " update MisMenuList set addLogic_treat=N'" . sqlValueReplace(replace($f_addLogic_treat, '?', '@_' . 'q;')) . "' where RealPid='$d_RealPid';";
                                execSql_gate($appSql, $dbalias);
                                WriteTextFile($destination_treat, $f_addLogic_treat);
                            } else if($d_addLogic_treat!='') {
                                WriteTextFile($destination_treat, replace($d_addLogic_treat, '@_' . 'q;', '?'));
                            }
                        }
                    }
                    if($d_MenuType=='01' || $d_MenuType=='06') {
                        if($f_addLogic_print!='') {
                            if($d_MenuType=='01') {       //mis join 의 경우 DB저장은 안함.
                                $appSql = " update MisMenuList set addLogic_print=N'" . sqlValueReplace($f_addLogic_print) . "' where RealPid='$d_RealPid';";
                                execSql_gate($appSql, $dbalias);
                            }
                            WriteTextFile($destination_print, $f_addLogic_print);
                        } else if($d_addLogic_print!='' && $d_MenuType=='01') {     //mis join 의 경우 DB저장은 안함.
                            WriteTextFile($destination_print, $d_addLogic_print);       
                        }
                    }
                }

            }
            

            $resultCode = "success";
            $resultMessage = "PHP 파일에 대한 동기화가 완료되었습니다.";

        }
    }
}
//end list_json_init



function list_json_load() {
    global $base_root, $real_pid, $misJoinPid, $logicPid, $parent_idx, $addDir;
    global $flag, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript;
    global $data, $key_aliasName, $child_alias, $selectQuery, $keyword, $menuName;

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

            $db_addLogic_print = json_decode($data)[0]->addLogic_print;
            $file_addLogic_print = ReadTextFile($base_root . "/_mis_addLogic/" . $idx . "_print.html");
            if($file_addLogic_print!="") {
                $new_data = json_decode($data);
                $new_data[0]->addLogic_print = $file_addLogic_print;
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
        if(file_exists($destination)) {
            copy($destination, replace(replace($destination, "/_mis_addLogic", "/_mis_addLogic_autoSave")
                , $key_value, $key_value . "_" . date("Ymd_His")));
            unlink($destination);
        }
        if($addLogic!="") {
            WriteTextFile($destination, $addLogic);
        }
        $addLogic = replace($addLogic, '?', '@_' . 'q;');
        $saveList["addLogic"] = $addLogic;

        $addLogic_print = $saveList["addLogic_print"];
        $destination_print = $base_root . "/_mis_addLogic/" . $key_value . "_print.html";
        if(file_exists($destination_print)) {
            copy($destination_print, replace(replace($destination_print, "/_mis_addLogic", "/_mis_addLogic_autoSave")
                , $key_value, $key_value . "_" . date("Ymd_His")));
            unlink($destination_print);
        }
        if($addLogic_print!="") {
            WriteTextFile($destination_print, $addLogic_print);
        }

        
        
    }
}
//end save_updateReady



function save_updateQueryBefore() {

	global $sql, $sql_prev, $sql_next, $key_value;
	global $result, $updateList, $upload_idx, $MS_MJ_MY;

	//아래는 업데이트 쿼리에 특정쿼리를 더 추가합니다.

	if($MS_MJ_MY=='MY') {
	} else {
		//spa 이슈로 인해 수정만 되어도 메뉴새로고침해야함.(추가쿼리때문)
		$sql = $sql . " 
if exists (select * from sysobjects where name='MisUser' and xtype='U') begin
	exec('update dbo.MisUser set menuRefresh=''Y'', menuRefreshApp=''Y''')
end
		";
	}
	


}
//end save_updateQueryBefore



function textUpdate_sql() {

    global $strsql, $keyAlias, $keyValue, $thisValue, $oldText, $thisAlias, $resultCode, $resultMessage, $afterScript, $MS_MJ_MY;

	//아래는 특정항목을 수정할 경우, 해당항목이 정의된 리스트에 포함되었을 경우, 관련 업데이트문을 추가하고, 처리메세지를 브라우저로 전달하는 로직입니다.  
    if($thisAlias=='AddURL') {
		if($MS_MJ_MY=='MY') {
		} else {
			//spa 이슈로 인해 수정만 되어도 메뉴새로고침해야함.(추가쿼리때문)
			$strsql = $strsql . " 
	if exists (select * from sysobjects where name='MisUser' and xtype='U') begin
		exec('update dbo.MisUser set menuRefresh=''Y'', menuRefreshApp=''Y''')
	end
			";
		}
    }

}
//end textUpdate_sql

function save_updateAfter() {

	global $updateList, $kendoCulture, $afterScript, $base_domain, $key_value, $full_site, $parent_idx, $base_root;

    //프로그램 변경에 따른 캐쉬파일 삭제 로직
    $gubun = GubunIntoRealPid($key_value);
    $path_pattern = "$base_root/_mis_cache/*P.$key_value.*";
    delete_cache_files($path_pattern);
    $path_pattern = "$base_root/_mis_cache/*gubun.$gubun.*";
    delete_cache_files($path_pattern);

}
//end save_updateAfter

function addLogic_treat() {
	
	global $misSessionUserId, $real_pid, $base_root, $addDir;
	
    //addLogic_treat 함수는 ajax 로 요청되어진(url 형식) 것에 대한 출력문입니다. echo 등으로 출력내용만 표시하면 됩니다.
	//아래는 url 에 동반된 파라메터의 예입니다.
	//해당 예제 TIP 의 기본폼에 보면 addLogic_treat 를 호출하는 코딩이 있습니다.

    $question = requestVB("question");
    $file_RealPid = requestVB("file_RealPid");
    

	//아래는 값에 따라 mysql 서버를 통해 알맞는 값을 출력하여 보냅니다.
    if($question=="file_php") {
	
		$destination = $base_root . "/_mis_addLogic/" . $file_RealPid . ".php";


		$addLogic = ReadTextFile($destination);

		echo $addLogic;

		
    }

}
//end addLogic_treat

?>