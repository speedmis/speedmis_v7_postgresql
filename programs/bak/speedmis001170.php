<?php

function misMenuList_change() {

	//misMenuList 테이블에 의한 설정값인 $result 를 바꾸는게 이 함수의 핵심기능
    global $actionFlag, $gubun, $parent_idx, $real_pid, $logicPid, $result;
    global $misSessionPositionCode, $flag, $externalDB, $idx, $MS_MJ_MY;
    global $dataTextField, $result, $dbalias, $table_m, $base_db, $base_db2;
	if($actionFlag=="write") {
        $search_index = array_search("virtual_fieldQndbalias", array_column($result, 'aliasName'));

        if($MS_MJ_MY=='MY') {
            $dbList = '[{"value":"","text":""},{"value":"1st","text":"MYSQL메인DB"}';
        } else {
            $dbList = '[{"value":"","text":""},{"value":"default","text":"MSSQL메인DB"}';
        }

        foreach($externalDB as $key => $value){
            $dbList = $dbList . ',{"value":"' . $key. '","text":"' . $key . ' - ';
            for($i=0;$i<count(splitVB($value,'(@)'))-1;$i++) {
                $dbList = $dbList . iif($i==0,'',iif($i==1,' : ',' / ')) . splitVB($value,'(@)')[$i];
            }
            $dbList = $dbList . '"}';
        }
        $dbList = $dbList . ']';
		$result[$search_index]["Grid_Items"] = $dbList;
    }
    if($dataTextField=='table_g08QnTABLE_NAME') {
        $default_dbalias = requestVB('app');
        if($default_dbalias=='') $default_dbalias = onlyOneReturnSql("select dbalias from MisMenuList where idx=$idx");
        if(($default_dbalias=='default' || $default_dbalias=='') && $MS_MJ_MY=='MY') $default_dbalias = '1st';
        //gzecho('zz'.$default_dbalias);exit;
        if($default_dbalias!='default' && $default_dbalias!='') {
            $dbalias = $default_dbalias;
            connectDB_dbalias($dbalias);
            
            if(Left($externalDB[$default_dbalias],2)=='MY') {
                $result[1]['Grid_PrimeKey'] = "concat(table_name, ' / rows:', ifnull(table_rows,0), ' / ', table_type)#information_schema.tables#1#TABLE_NAME#(@outer_tbname.TABLE_NAME not like 'Speed%' or @outer_tbname.TABLE_NAME like 'Speedm%') and @outer_tbname.TABLE_SCHEMA='$base_db2'";
            } else if(Left($externalDB[$default_dbalias],2)=='OC') {
                $result[1]['Grid_PrimeKey'] = "OBJECT_NAME||' / rows'||(SELECT NUM_ROWS from user_tables where table_name=OBJECT_NAME)||' / '||OBJECT_TYPE#USER_OBJECTS#1#OBJECT_NAME#(OBJECT_TYPE='TABLE' or OBJECT_TYPE='VIEW') and OBJECT_NAME not like '%$%'";
            }
        }
    }

}
//end misMenuList_change



function pageLoad() {

    global $actionFlag,$paidKey_ucount,$full_siteID;
	global $misSessionIsAdmin, $real_pid, $MenuType, $idx, $externalDB;


        ?>
<style>
	
	body[actionflag="list"] .k-grid tbody .k-button {
        min-width: auto;
    }
    ul.k-tabstrip-items.k-reset {
        display: none;
    }
    body[isMenuIn="N"][isPopup="Y"] div.k-content.k-state-active {
        height: calc(100vh - 83px);
    }
    a.depth0, a.depth3,a.zhawiAPP0, div#round_RealPid, div#round_upRealPid {
        display: none;
    }
    div#round_zseontaekhanggyeongno {
        display: inline-block;
        width: 100%;
    }
    div#zseontaekhanggyeongno {
        width: 100%;
    }
    a#btn_up, a#btn_down, a#btn_saveClose, a#btn_list {
        display: none;
    }
    div#round_g08, div#round_g08b, div#round_excelData, div#round_spreadsheets_url, div#round_virtual_fieldQnsourceCopy, div#round_virtual_fieldQndbalias {
        display: none;
    }
    div#round_excelData {
        width: 100%;
    }
    div#round_spreadsheets_url {
        width: 100%;
    }
    label.k-checkbox-label.col-xs-4.col-md-4.col-form-label {
        font-weight: bold;
    }

</style>
        <script>
function applyDownAuth(p_this, p_idx) {
    if(!confirm(getGridCellValue_idx(p_idx,'MenuName')+' 메뉴 하위의 '+getGridCellValue_idx(p_idx,'zhawiAPP')
    +'개 메뉴의 권한도 \n'+getGridCellValue_idx(p_idx,'table_new_gidxQngname')+' | '+getGridCellValue_idx(p_idx,'table_AuthCodeQnkname')
    +'\n로 바꾸시겠습니까?')) return false;
    $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "권한전달."+p_idx;
    $("#grid").data("kendoGrid").dataSource.read();
    $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "";
}


function columns_templete(p_dataItem, p_aliasName) {
    
    if(p_aliasName=="RealPid") {
		var rValue = "<a href='index.php?RealPid=" + p_dataItem["RealPid"] + "&isMenuIn=Y' target='_blank' class='k-button'>연결</a>";
		if(p_dataItem["MenuType"]=="01") {
			rValue = rValue + "<a id='aid_" + p_dataItem["idx"] + "' href='index.php?RealPid=speedmis000266&idx=" 
				+ p_dataItem["RealPid"] + "&isMenuIn=Y' target='_blank' class='k-button'>소스</a>";
		} else if(p_dataItem["MenuType"]=="22") {
			rValue = rValue + "<a id='aid_" + p_dataItem["idx"] + "' href='index.php?RealPid=speedmis000989&idx=" 
				+ p_dataItem["RealPid"] + "&isMenuIn=Y' target='_blank' class='k-button'>소스</a>";
		}
        rValue = rValue + "<a href='javascript:;' onclick='addMenu(this);' idx='"+p_dataItem["idx"]+"' class='k-button'>추가</a>";
		rValue = rValue + p_dataItem["RealPid"];
        return rValue;
    } else if(p_aliasName=="zgwonhanjeondal") {
		var rValue = "<a href='javascript:;' onclick='applyDownAuth(this,"+p_dataItem["idx"] + ");' class='k-button depth"+p_dataItem["depth"]+" zhawiAPP"+p_dataItem["zhawiAPP"]+"'>적용"+p_dataItem["zhawiAPP"]+"</a>";
		return rValue;
    } else if(p_aliasName=="zseontaekhanggyeongno") {
        var rValue = 'root';
        if(p_dataItem['depth']==1) {
            rValue = rValue + ' > ' + p_dataItem['MenuName'];
        } else if(p_dataItem['depth']==2) {
            rValue = rValue + ' > ' + p_dataItem['table_upRealPidQnMenuName'] + ' > ' + p_dataItem['MenuName'];
        } else if(p_dataItem['depth']==3) {
            //depth==3 일 경우에 한해 최상위 메뉴명을 별도로 구해야 함.
            url = '/_mis/addLogic_treat.php?RealPid=<?php echo $real_pid; ?>&pidx='+p_dataItem['idx']+'&question=depth3';
            topMenuName = ajax_url_return2(url);
            rValue = rValue + ' > ' + topMenuName + ' > ' + p_dataItem['table_upRealPidQnMenuName'] + ' > ' + p_dataItem['MenuName'];
        }
        rValue = rValue + ' <span style="color: blue;">[' + p_dataItem['table_MenuTypeQnkname'] + ']</span>';
		return rValue;
    } else {
        return p_dataItem[p_aliasName];
    }
}

//사용자 정의 함수 = 함수 이름은 변형하면 안됨. 내용만. 없어도 됨. ==============================
//데이타의 변형은 즉시 가능 = rowFunction


function rowFunction_UserDefine(p_this) {
	if(p_this.AutoGubun!=null) {
		p_this.table_upRealPidQnMenuName = Left(p_this.AutoGubun, p_this.AutoGubun.length-2) + " " + p_this.table_upRealPidQnMenuName;
	}
    //p_this.MenuName = p_this.depth + p_this.MenuName; 
    //alert(p_this.MenuName)
    //p_this.AutoGubun = 
    //"<a href=index.php?gubun=" + p_this.idx + "&isMenuIn=Y target=_blank>[Go]</a> <a id='aid_" + p_this.idx + "' href=index.php?RealPid=speedmis000266&idx=" + p_this.idx + "&isMenuIn=Y target=_blank>[Source]</a>?" 
    //+ p_this.AutoGubun; 
}
<?php if($misSessionIsAdmin=="Y" && $actionFlag=="list") { ?>
function thisLogic_toolbar() {
	
    $("a#btn_1").text("권한 및 메뉴적용");
    $("li#btn_1_overflow").text("권한 및 메뉴적용");
    $("#btn_1").css("background", "#88f");
    $("#btn_1").css("color", "#fff");
    $("#btn_1").click( function() {
		<?php if($paidKey_ucount*1<5) { ?>
			if(!confirm("비구매 고객의 경우, gadmin / admin 포함하여 20개 유저 외에는 삭제될 수 있으며, 웹소스관리에 추가된 20개 프로그램 외에는 삭제될 수 있습니다. 진행할까요?")) return false; 
		<?php } else if($paidKey_ucount*1<500) { 
			$cnt_user = onlyOneReturnSql("select COUNT(*) from MisUser where delchk<>'D'");
			$cnt_app = onlyOneReturnSql("select COUNT(*) from MisMenuList where useflag=1 and MenuType='01' and idx>1312 and idx not in (select top 100 idx from MisMenuList where useflag=1 and MenuType='01' and idx>=1312 and RealPid not like 'speedmis%' order by idx)");

			if($cnt_user>100 || $cnt_app>100) {
			?>
			if(!confirm("스탠다드버전의 경우, gadmin / admin 포함하여 100개 유저 외에는 삭제될 수 있으며, 웹소스관리에 추가된 100개 프로그램 외에는 삭제될 수 있습니다. 진행할까요?")) return false; 
		<?php 
			} else {
			?>
			if(!confirm("스탠다드버전 사용 고객님, 현재 유저수 <?php echo $cnt_user; ?>/100개, 프로그램 <?php echo $cnt_app; ?>/100개 사용중입니다. 확인을 누르시면 권한이 적용됩니다.")) return false; 
		<?php 
			}
			
		} ?>
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "적용";
        $("#grid").data("kendoGrid").dataSource.read();
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "";
    });
}
<?php } ?>
			
			

function viewLogic_afterLoad_continue() {
        $('select#g08').data('kendoDropDownList').value('');
        $('select#MenuType').data('kendoDropDownList').value('');
        $('select#MisJoinPid').data('kendoDropDownList').value('');
        $('div#round_MisJoinPid').css('display', 'none');
        
        $('select#MenuType').bind('change', function() {
            if(this.value=='06') {
                $('div#round_MisJoinPid').css('display', 'inline-block');
            } else {
                $('div#round_MisJoinPid').css('display', 'none');
            }
           
            if(this.value=='01') {
                $('div#round_virtual_fieldQnsourceCopy').css('display', 'inline-block');
            } else {
                $('div#round_virtual_fieldQnsourceCopy').css('display', 'none');
            }
            if(this.value=='01') {
                $('div#round_virtual_fieldQndbalias').css('display', 'inline-block');
                $('div#round_g08, div#round_g08b, div#round_spreadsheets_url, div#round_excelData').css('display', 'inline-block');
                if($('select#virtual_fieldQndbalias').data('kendoDropDownList').value()=='') {
                    if(document.getElementById('MS_MJ_MY').value=='MY') {
                        $('select#virtual_fieldQndbalias').data('kendoDropDownList').value('1st');
                    } else {
                        $('select#virtual_fieldQndbalias').data('kendoDropDownList').value('default');
                    }
                }
            } else {
                $('div#round_virtual_fieldQndbalias').css('display', 'none');
                $('div#round_g08, div#round_g08b, div#round_spreadsheets_url, div#round_excelData').css('display', 'none');
            }
            
        });

        //DB 선택변경에 따른 테이블목록 재호출
        $('select#virtual_fieldQndbalias').bind('change', function() {
            
            getID("app").value = iif(this.value=='','default',this.value);
            $('select#g08').data('kendoDropDownList').dataSource.read();
        });

        

        <?php
        $default_dbalias = onlyOneReturnSql("select dbalias from MisMenuList where idx=$idx");
        ?>
        $('select#virtual_fieldQndbalias').data('kendoDropDownList').value('<?php echo $default_dbalias;?>');
        
    //$('div#table_mQmidx')[0].innerText = resultAll.d.results[0].table_mQmidx;
    if($('#ActionFlag').val()=='modify' && parent.document.getElementById('RealPid').value!='speedmis001333') {
		if(parent.$('div#example-nav').data('kendoTreeView').dataItem(parent.$('div#example-nav span.k-state-selected.k-in'))) {
			var gubun = parent.$('div#example-nav').data('kendoTreeView').dataItem(parent.$('div#example-nav span.k-state-selected.k-in')).id;
		} else {
			var gubun = parent.getUrlParameter('gubun');
		}
		$('input#temp1').attr('pre_values',get_now_values());	//이동 시, 경고창 무시.
        parent.location.href = 'index.php?gubun='+gubun+'&isMenuIn=auto';
    }

}	
        </script>
        <?php 
}
//end pageLoad



function save_writeBefore() {

    global $full_siteID, $base_root, $real_pid, $misJoinPid, $logicPid, $parent_idx, $MS_MJ_MY;
    global $key_aliasName, $key_value, $actionFlag, $updateList, $sql_next, $misSessionUserId, $db_name, $db_name2;
    global $externalDB, $base_db, $base_db2, $addDir, $saveList, $isnull;

    include '../_mis/PHPExcleReader/Classes/PHPExcel/IOFactory.php';
    include "../_mis/hangeul-utils-master/hangeul_romaja.php";
    


    $updateList['MenuName'] = Trim($updateList['MenuName']);
    
    if($MS_MJ_MY=='MY') {
        $sql = "select concat(AutoGubun,'.',MenuName) from MisMenuList where RealPid='" . $updateList["RealPid"] . "'; ";
    } else {
        $sql = "select AutoGubun+'.'+MenuName from MisMenuList where RealPid='" . $updateList["RealPid"] . "'";
    }

    $this_info = onlyOnereturnSql($sql);
    $this_MenuName = Trim(splitVB($this_info,'.')[1]);
    if($this_MenuName==$updateList['MenuName']) {
        echo '선택한 경로와 메뉴명이 같아 처리할 수 없습니다.';
        exit;
    }
    
    if($updateList['MenuName']=='') {
        echo '정확한 메뉴명을 넣으세요!';
        exit;
    }
    $this_AutoGubun = Trim(splitVB($this_info,'.')[0]);
    //print_r($updateList);
    //exit;
    $addPosition = Left($updateList["추가위치"],1)*1;         //1~4 : 같은 레벨,  5~6 : 하위로.
    
    //AutoGubun 길이 기준으로 허용 안되는 경우1 : 6 인데 하위이면 거부
    if(Len($this_AutoGubun)==6 && $addPosition>=5) {
        echo '선택한 경로에서는 더이상 하위메뉴로 추가할 수 없습니다.';
        exit;
    }

    //AutoGubun 길이 기준으로 허용 안되는 경우2 : 4 인데 하위가 메뉴표시용이면 거부
    if(Len($this_AutoGubun)==4 && $addPosition>=5 && $updateList['MenuType']=='00') {
        echo '선택한 경로에서는 하위메뉴를 메뉴표시용으로 추가할 수 없습니다.';
        exit;
    }

    //AutoGubun 길이 기준으로 허용 안되는 경우3 : 6 인데 같은 레벨이 메뉴표시용이면 거부
    if(Len($this_AutoGubun)==6 && $addPosition<=4 && $updateList['MenuType']=='00') {
        echo '선택한 경로에서는 메뉴표시용으로 추가할 수 없습니다.';
        exit;
    }

    //MIS JOIN 인데 그값이 없을 경우.
    if($updateList['MenuType']=='06' && $updateList['MisJoinPid']=='') {
        echo 'Mis Join 에 대한 메뉴명을 선택하세요!';
        exit;
    }
    $dataPullType = 0;
    $virtual_fieldQnsourceCopy = '';
    //업무용MIS 일때 택1만.
    if($updateList['MenuType']=='01') {
        if($saveList['virtual_fieldQnsourceCopy']!='') {
            $dataPullType = 1;
            $virtual_fieldQnsourceCopy = $saveList['virtual_fieldQnsourceCopy'];
        }
        if($updateList['g08']!='') $dataPullType = $dataPullType*10 + 2;
        if($updateList['g08b']!='') $dataPullType = $dataPullType*10 + 3;
        //첨부파일의 경우 첨부를 안하면 배열에 포함이 안됨.
        if(array_key_exists('excelData', $updateList)) { 
            if($updateList['excelData']!='') $dataPullType = $dataPullType*10 + 4;
        }
        if($updateList['spreadsheets_url']!='') $dataPullType = $dataPullType*10 + 5;

        if($dataPullType>5 || $dataPullType==0) {
            echo '업무용MIS에 대한 5가지 중 한가지를 선택하세요 선택하세요!';
            exit;
        }
    }

    if($updateList['MenuType']!='06' && $updateList['MisJoinPid']!='') {
        $updateList['MisJoinPid'] = '';
    }
    if($MS_MJ_MY=='MY') {
        $newRealPid = onlyOnereturnSql("select concat('$full_siteID', formatnums((SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_NAME = 'MisMenuList' AND TABLE_SCHEMA='$base_db'), '000000'));");
        $newRealPid2 = onlyOnereturnSql("select concat('$full_siteID', formatnums((SELECT MAX(idx) FROM MisMenuList)+1, '000000'));");
		if($newRealPid2>$newRealPid) {
			$newRealPid = $newRealPid2;
		}
    } else {
        $newRealPid = onlyOnereturnSql("select '$full_siteID' + dbo.formatnums(IDENT_CURRENT('MisMenuList') + 1, '000000') ");
    }
    if($addPosition>=5) {
        $updateList["AutoGubun"] = $this_AutoGubun . "99";
        $updateList["SortG2"] = Left($this_AutoGubun,2);
    
        if(Len($this_AutoGubun)==2) {
            if($addPosition==5) {
                $updateList["SortG4"] = 0.1;
            } else {
                $updateList["SortG4"] = 99;
            }
            $updateList["SortG6"] = 0;
        } else {
            $updateList["SortG4"] = Mid($this_AutoGubun,3,2);
            if($addPosition==5) {
                $updateList["SortG6"] = 0.1;
            } else {
                $updateList["SortG6"] = 99;
            }
        }
        $updateList["upRealPid"] = $updateList["RealPid"];
    } else {

        //같은 레벨에 대한 추가.

        $updateList["AutoGubun"] = $this_AutoGubun;

        if(Len($this_AutoGubun)==2) {
            if($addPosition==1) {
                $updateList["SortG2"] = 0.1;
            } else if($addPosition==2) {
                $updateList["SortG2"] = Left($this_AutoGubun,2)*1 - 0.1;
            } else if($addPosition==3) {
                $updateList["SortG2"] = Left($this_AutoGubun,2)*1 + 0.1;
            } else if($addPosition==4) {
                $updateList["SortG2"] = 99;
            }
            $updateList["SortG4"] = 0;
            $updateList["SortG6"] = 0;
        } else if(Len($this_AutoGubun)==4) {
            $updateList["SortG2"] = Left($this_AutoGubun,2);
            if($addPosition==1) {
                $updateList["SortG4"] = 0.1;
            } else if($addPosition==2) {
                $updateList["SortG4"] = Mid($this_AutoGubun,3,2)*1 - 0.1;
            } else if($addPosition==3) {
                $updateList["SortG4"] = Mid($this_AutoGubun,3,2)*1 + 0.1;
            } else if($addPosition==4) {
                $updateList["SortG4"] = 99;
            }
            $updateList["SortG6"] = 0;
        } else {
            $updateList["SortG2"] = Left($this_AutoGubun,2);
            $updateList["SortG4"] = Mid($this_AutoGubun,3,2);
            if($addPosition==1) {
                $updateList["SortG6"] = 0.1;
            } else if($addPosition==2) {
                $updateList["SortG6"] = Right($this_AutoGubun,2)*1 - 0.1;
            } else if($addPosition==3) {
                $updateList["SortG6"] = Right($this_AutoGubun,2)*1 + 0.1;
            } else if($addPosition==4) {
                $updateList["SortG6"] = 99;
            }
        }
    }
    if($updateList["SortG6"].''=='0') {
        $updateList["SortG6"] = '0';
    }

    if($dataPullType==1) {
        if($MS_MJ_MY=='MY') {

            $sql_next =  "

            set @wdater := '$misSessionUserId';
            set @full_siteID := '$full_siteID';
            set @RealPid := '$virtual_fieldQnsourceCopy';
            set @newRealPid := '$newRealPid';

            update MisMenuList a inner join MisMenuList b on b.RealPid=@RealPid
            SET a.g01=b.g01, a.g02=b.g02, a.g03=b.g03, a.g04=b.g04, a.g05=b.g05, a.g06=b.g06, a.g07=b.g07, a.g08=b.g08, a.g09=b.g09, 
          a.g10=b.g10, a.g11=b.g11, a.g12=b.g12, a.g14=b.g14, a.dbalias=b.dbalias, a.addLogic=b.addLogic, 
          a.isUsePrint=b.isUsePrint, a.isUseForm=b.isUseForm, a.addLogic_print=b.addLogic_print, a.LanguageCode=b.LanguageCode
          where a.RealPid=@newRealPid;

        insert into MisMenuList_Detail (RealPid, SortElement, Grid_Select_Field, Grid_Select_Tname, aliasName, Grid_View_Fixed, Grid_Enter, Grid_View_XS, Grid_View_SM, Grid_View_MD, Grid_View_LG, Grid_View_Hight, Grid_View_Class, Grid_Columns_Title, Grid_Columns_Width, Grid_Schema_Type
        , Grid_Items, Grid_Schema_Validation
        , Grid_Align, Grid_Orderby, Grid_MaxLength, Grid_Templete, Grid_Default, Grid_GroupCompute, Grid_CtlName, Grid_IsHandle, Grid_ListEdit, Grid_PrimeKey, Grid_Alim, Grid_Pil, Grid_FormGroup, wdater)
        SELECT @newRealPid, SortElement, Grid_Select_Field, Grid_Select_Tname, aliasName, Grid_View_Fixed, Grid_Enter, Grid_View_XS, Grid_View_SM, Grid_View_MD, Grid_View_LG, Grid_View_Hight, Grid_View_Class, Grid_Columns_Title, Grid_Columns_Width, Grid_Schema_Type
        , Grid_Items, Grid_Schema_Validation
        , Grid_Align, Grid_Orderby, Grid_MaxLength, Grid_Templete, Grid_Default, Grid_GroupCompute, Grid_CtlName, Grid_IsHandle, Grid_ListEdit, Grid_PrimeKey, Grid_Alim, Grid_Pil, Grid_FormGroup, @wdater
          FROM MisMenuList_Detail where RealPid=@RealPid order by SortElement, idx;
          ";
          
        } else {

            $sql_next =  "

        declare @full_siteID nvarchar(8)
        declare @RealPid nvarchar(20)
        declare @newRealPid nvarchar(20)
        declare @wdater nvarchar(50)
        set @wdater = '$misSessionUserId'
        set @full_siteID = '$full_siteID'
        set @RealPid='$virtual_fieldQnsourceCopy'
        set @newRealPid = '$newRealPid'

        update MisMenuList set g01=b.g01, g02=b.g02, g03=b.g03, g04=b.g04, g05=b.g05, g06=b.g06, g07=b.g07, g08=b.g08, g09=b.g09, 
        g10=b.g10, g11=b.g11, g12=b.g12, g14=b.g14, dbalias=b.dbalias, addLogic=b.addLogic, 
        isUsePrint=b.isUsePrint, isUseForm=b.isUseForm, addLogic_print=b.addLogic_print, LanguageCode=b.LanguageCode
        from MisMenuList
        join MisMenuList b on b.RealPid=@RealPid
        where MisMenuList.RealPid=@newRealPid

        insert into MisMenuList_Detail (RealPid, SortElement, Grid_Select_Field, Grid_Select_Tname, aliasName, Grid_View_Fixed, Grid_Enter, Grid_View_XS, Grid_View_SM, Grid_View_MD, Grid_View_LG, Grid_View_Hight, Grid_View_Class, Grid_Columns_Title, Grid_Columns_Width, Grid_Schema_Type
        , Grid_Items, Grid_Schema_Validation
        , Grid_Align, Grid_Orderby, Grid_MaxLength, Grid_Templete, Grid_Default, Grid_GroupCompute, Grid_CtlName, Grid_IsHandle, Grid_ListEdit, Grid_PrimeKey, Grid_Alim, Grid_Pil, Grid_FormGroup, wdater)
        SELECT @newRealPid, SortElement, Grid_Select_Field, Grid_Select_Tname, aliasName, Grid_View_Fixed, Grid_Enter, Grid_View_XS, Grid_View_SM, Grid_View_MD, Grid_View_LG, Grid_View_Hight, Grid_View_Class, Grid_Columns_Title, Grid_Columns_Width, Grid_Schema_Type
        , Grid_Items, Grid_Schema_Validation
        , Grid_Align, Grid_Orderby, Grid_MaxLength, Grid_Templete, Grid_Default, Grid_GroupCompute, Grid_CtlName, Grid_IsHandle, Grid_ListEdit, Grid_PrimeKey, Grid_Alim, Grid_Pil, Grid_FormGroup, @wdater
          FROM MisMenuList_Detail where RealPid=@RealPid order by SortElement, idx
          ";

        }
        
          $nowRealPid = $virtual_fieldQnsourceCopy;
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
    } else if($dataPullType==2 || $dataPullType==3) {
        //테이블 기준 프로그램 생성
        $updateList['g01'] = 'simplelist';
        $updateList['g07'] = 'Y';   //읽기전용
        $updateList['new_gidx'] = '83'; $updateList['AuthCode'] = '02'; //개발자 전용권한

        $this_db_name = $db_name;
        
        if($dataPullType==3) {
			
			if(isExistTable($updateList['g08b'], $updateList['dbalias'])==false) {
                echo '테이블이 존재하지 않습니다.';
                exit;
            }
			if(InStr($updateList['g08b'],'.')>0) {
				$updateList['g08'] = splitVB($updateList['g08b'],'.')[count(splitVB($updateList['g08b'],'.'))-1];
				$this_db_name = replace($updateList['g08b'],'.'.$updateList['g08'],'');
				$full_g08 = $updateList['g08b'];
			} else {
				$updateList['g08'] = $updateList['g08b'];
				$full_g08 = $updateList['g08b'];
			}
        } else {
            $full_g08 = $updateList['g08'];
        }
        
        $sql_next = "
        delete from MisMenuList_Detail where RealPid='$newRealPid';
        update MisMenuList set g08='$full_g08', dbalias=N'" . $updateList['dbalias'] . "' where RealPid='$newRealPid';
        insert into MisMenuList_Detail (RealPid, SortElement, Grid_Select_Field, Grid_Select_Tname, 
        aliasName, Grid_Columns_Title, Grid_Columns_Width, wdater 
        ,Grid_Schema_Type)
        ";

        if($updateList['dbalias']=='default') $updateList['dbalias'] = '';
        if($updateList['dbalias']!='') {
            connectDB_dbalias($updateList['dbalias']);
            if(Left($externalDB[$updateList['dbalias']],2)=='MY') {
                $sql = "
                select '$newRealPid' as \"RealPid\", ORDINAL_POSITION as \"SortElement\", COLUMN_NAME as \"Grid_Select_Field\", 'table_m' as \"Grid_Select_Tname\", 
                COLUMN_NAME as \"aliasName\", COLUMN_NAME as \"Grid_Columns_Title\", 10 as \"Grid_Columns_Width\", '$misSessionUserId' as \"wdater\"
                ,case when ORDINAL_POSITION=1 then '' when left(COLUMN_TYPE,3)='int' then 'number' when COLUMN_TYPE='date' then 'date' when COLUMN_TYPE='datetime' then 'datetime' else '' end as \"Grid_Schema_Type\"
                from INFORMATION_SCHEMA.COLUMNS where TABLE_NAME='" . $updateList['g08'] . "' and COLUMN_NAME not like '%:%' and column_name not like '%-%' and column_name not like '%=%' and TABLE_SCHEMA='$base_db2' order by ORDINAL_POSITION
                ";
                $data = allreturnSql_gate($sql, $updateList['dbalias']);
                //print_r($sql);exit;
                $sql_next = $sql_next . ' values ';

                $cnt_data = count($data);
                
                for($k=0;$k<$cnt_data;$k++) {
                
					$r_RealPid = $data[$k]['RealPid'];
                    $r_SortElement = $data[$k]['SortElement'];
                    $r_Grid_Select_Field = $data[$k]['Grid_Select_Field'];
                    $r_Grid_Select_Tname = $data[$k]['Grid_Select_Tname'];
                    $r_aliasName = $data[$k]['aliasName'];
                    $r_Grid_Columns_Title = $data[$k]['Grid_Columns_Title'];
                    $r_Grid_Columns_Width = $data[$k]['Grid_Columns_Width'];
                    $r_wdater = $data[$k]['wdater'];
                    $r_Grid_Schema_Type = $data[$k]['Grid_Schema_Type'];
                    $sql_next = $sql_next . iif($k>0,",","") . "
                    ('$r_RealPid', '$r_SortElement', '$r_Grid_Select_Field', '$r_Grid_Select_Tname', '$r_aliasName', '$r_Grid_Columns_Title', '$r_Grid_Columns_Width', '$r_wdater', '$r_Grid_Schema_Type')";
                }
                $sql_next = $sql_next . ';';

            } else if(Left($externalDB[$updateList['dbalias']],2)=='OC') {
                $sql = "
                select '$newRealPid' as \"RealPid\", column_id as \"SortElement\", column_name as \"Grid_Select_Field\", 'table_m' as \"Grid_Select_Tname\", 
                column_name as \"aliasName\", column_name as \"Grid_Columns_Title\", 10 as \"Grid_Columns_Width\", '$misSessionUserId' as \"wdater\"
                ,case when column_id=1 then '' when data_type='NUMBER' then 'number' when data_type='DATE' then 'date' else '' end as \"Grid_Schema_Type\"
                from user_tab_cols where table_name='" . $updateList['g08'] . "' and column_name not like '%:%' and column_name not like '%-%' and column_name not like '%=%' and column_id > 0 order by column_id
                ";

                $data = allreturnSql_gate($sql, $updateList['dbalias']);
                $sql_next = $sql_next . ' values ';

                $cnt_data = count($data);
                for($k=0;$k<$cnt_data;$k++) {
                
					$r_RealPid = $data[$k]['RealPid'];
                    $r_SortElement = $data[$k]['SortElement'];
                    $r_Grid_Select_Field = $data[$k]['Grid_Select_Field'];
                    $r_Grid_Select_Tname = $data[$k]['Grid_Select_Tname'];
                    $r_aliasName = $data[$k]['aliasName'];
                    $r_Grid_Columns_Title = $data[$k]['Grid_Columns_Title'];
                    $r_Grid_Columns_Width = $data[$k]['Grid_Columns_Width'];
                    $r_wdater = $data[$k]['wdater'];
                    $r_Grid_Schema_Type = $data[$k]['Grid_Schema_Type'];
                    $sql_next = $sql_next . iif($k>0,",","") . "
                    ('$r_RealPid', '$r_SortElement', '$r_Grid_Select_Field', '$r_Grid_Select_Tname', '$r_aliasName', '$r_Grid_Columns_Title', '$r_Grid_Columns_Width', '$r_wdater', '$r_Grid_Schema_Type')";
                }
                $sql_next = $sql_next . ';';
				
            } else {
                $sql = "
                select '$newRealPid' as 'RealPid', colorder as 'SortElement', name as 'Grid_Select_Field', 'table_m' as 'Grid_Select_Tname', 
                name as 'aliasName', name as 'Grid_Columns_Title', 10 as 'Grid_Columns_Width', '$misSessionUserId' as 'wdater'
                ,case when colorder=1 then '' when xtype=62 or xtype=56 then 'number' when xtype=104 then 'boolean' when xtype=61 then 'date' else '' end as 'Grid_Schema_Type'
                from " . $this_db_name . ".syscolumns where id=(select id from " . $this_db_name . ".sysobjects where name='" . $updateList['g08'] . "' and (type='U' or type='V')) 
                and xtype<>165 and name not like '%:%' order by colorder
                ";
                $data = allreturnSql_gate($sql, $updateList['dbalias']);
                $sql_next = $sql_next . ' values ';
                $cnt_data = count($data);
                for($k=0;$k<$cnt_data;$k++) {
                    $r_RealPid = $data[$k]['RealPid'];
                    $r_SortElement = $data[$k]['SortElement'];
                    $r_Grid_Select_Field = $data[$k]['Grid_Select_Field'];
                    $r_Grid_Select_Tname = $data[$k]['Grid_Select_Tname'];
                    $r_aliasName = $data[$k]['aliasName'];
                    $r_Grid_Columns_Title = $data[$k]['Grid_Columns_Title'];
                    $r_Grid_Columns_Width = $data[$k]['Grid_Columns_Width'];
                    $r_wdater = $data[$k]['wdater'];
                    $r_Grid_Schema_Type = $data[$k]['Grid_Schema_Type'];
                    $sql_next = $sql_next . iif($k>0,",","") . "
                    ('$r_RealPid', '$r_SortElement', '$r_Grid_Select_Field', '$r_Grid_Select_Tname', '$r_aliasName', '$r_Grid_Columns_Title', '$r_Grid_Columns_Width', '$r_wdater', '$r_Grid_Schema_Type')";
                }
                $sql_next = $sql_next . ';';
            }
        } else {
            $sql_next = $sql_next . "
            select '$newRealPid' as 'RealPid', colorder as 'SortElement', name as 'Grid_Select_Field', 'table_m' as 'Grid_Select_Tname', 
            name as 'aliasName', name as 'Grid_Columns_Title', 10 as 'Grid_Columns_Width', '$misSessionUserId' as 'wdater'
            ,case when colorder=1 then '' when xtype=62 or xtype=56 then 'number' when xtype=104 then 'boolean' when xtype=61 then 'date' else '' end as 'Grid_Schema_Type'
            from " . $this_db_name . ".syscolumns where id=(select id from " . $this_db_name . ".sysobjects where name='" . $updateList['g08'] . "' and (type='U' or type='V')) and  xtype<>165
            order by colorder
            ";
        }
        
        
    } else if($dataPullType==4) {   //엑셀파일 업로드에 의한 생성
        
        $newDbalias = $updateList['dbalias'];
        connectDB_dbalias($newDbalias);
        if($newDbalias=='default') $newDbalias = '';


        //---------------- 테이블 생성 및 엑셀데이타 입력 시작------------

        $f = '../temp/' . requestVB('tempDir') . '/excelData/' . $updateList['excelData'];
        
        if (!file_exists($f)) {
            echo "$f 엑셀파일 업로드에 문제가 발생하여 프로그램 생성에 실패했습니다. 파일을 다시 선택 후 시도하세요!";
            exit;
        }
        
        
        $ext = strtolower(splitVB($f,'.')[count(splitVB($f,'.'))-1]);
        if($ext=='csv') {
            $objReader = PHPExcel_IOFactory::createReader($ext);
            $objPHPExcel = $objReader->load($f);
        } else {
            $objPHPExcel = PHPExcel_IOFactory::load($f);
        }

        
        //echo $objPHPExcel->getActiveSheet()->getCell("C13");
        try {
            $selArea = $objPHPExcel->getActiveSheet()->rangeToArray("A1:J10");
        } catch (Exception $e) {
            echo "파일은 업로드되었으나, 분석이 안되는 파일입니다.";
            exit;
        }

        $startRow = 0;
        $startColumn = 0;
        $endColumn = 0;
		$columnCount = 0;

        //헤더행 구하기
        $cnt_selArea = count($selArea);
        for($i=0;$i<$cnt_selArea;$i++) {
            $startColumn0 = 0;$endColumn0 = 0;$columnCount0 = 0;
            $cnt_selArea_i = count($selArea[$i]);
            for($j=0;$j<$cnt_selArea_i;$j++) {
				if($j==0) {
					if($selArea[$i][$j]!='') {
						$startColumn0 = $j+1;$endColumn0 = $j+1;++$columnCount0;
					}
                } else {
					if($selArea[$i][$j]!='' && $selArea[$i][$j-1]!='') {
						if($startColumn0==0) $startColumn0 = $j;
						$endColumn0 = $j+1;
						++$columnCount0;
					} else if($selArea[$i][$j]!='') {
						if($startColumn0==0) $startColumn0 = $j+1;
						$endColumn0 = $j+1;
						$columnCount0 = 1;
					}
				}

            }
            if($columnCount<$columnCount0) {
				$columnCount = $columnCount0;
				$startRow = $i+1;
				$startColumn = $startColumn0;
				$endColumn = $endColumn0;
            }
			
        }


		//echo "A$startRow:AZ$startRow";exit;

        //확정된 row 에 대한 헤더행 제대로 구하기
        $startColumn = 0;
        $endColumn = 0;
		$columnCount = 0;
		$selArea = $objPHPExcel->getActiveSheet()->rangeToArray("A$startRow:CZ$startRow");

        $cnt_selArea = count($selArea);
        for($i=0;$i<$cnt_selArea;$i++) {
            $startColumn0 = 0;$endColumn0 = 0;$columnCount0 = 0;

            $cnt_selArea_i = count($selArea[$i]);
            for($j=0;$j<$cnt_selArea_i;$j++) {
				if($j==0) {
					if($selArea[$i][$j]!='') {
						$startColumn0 = $j+1;$endColumn0 = $j+1;++$columnCount0;
					}
                } else {
					if($selArea[$i][$j]!='' && $selArea[$i][$j-1]!='') {
						if($startColumn0==0) $startColumn0 = $j;
						$endColumn0 = $j+1;
						++$columnCount0;
					} else if($selArea[$i][$j]!='') {
						if($startColumn0==0) $startColumn0 = $j+1;
						$endColumn0 = $j+1;
						$columnCount0 = 1;
					}
				}

            }
            if($columnCount<$columnCount0) {
				$columnCount = $columnCount0;
				$startColumn = $startColumn0;
				$endColumn = $endColumn0;
            }
			
        }

		if($endColumn<=26) $range_to_array = chr(64+$startColumn).$startRow.":".chr(64+$endColumn).$startRow;
		else if($endColumn<=52) $range_to_array = chr(64+$startColumn).$startRow.":A".chr(64+$endColumn-26).$startRow;
		else if($endColumn<=78) $range_to_array = chr(64+$startColumn).$startRow.":B".chr(64+$endColumn-52).$startRow;
		else if($endColumn<=104) $range_to_array = chr(64+$startColumn).$startRow.":C".chr(64+$endColumn-78).$startRow;
		else $range_to_array = chr(64+$startColumn).$startRow.":D".chr(64+$endColumn-104).$startRow;

        //echo $range_to_array;exit;
		$selArea = $objPHPExcel->getActiveSheet()->rangeToArray($range_to_array);



        //헤더의 시작과 끝 구하기
        $addColumnQuery = '';$columnList = '';$addColumnAlter = '';
		$all_fieldName = ';;';

        $cnt_selArea_0 = count($selArea[0]);
        for($j=0;$j<$cnt_selArea_0;$j++) {

            if($selArea[0][$j]!='') {


                if($startColumn==0) {
                    $startColumn = $j+1;
                    $addColumnQuery = '';
                }
                $fieldName = replace(replace(replace(replace(replace(replace(replace(replace(replace(replace(replace(replace(replace($selArea[0][$j],"\n",""),".",""),",",""),"(",""),")",""),"[",""),"]",""),"*",""),":",""),"-",""),"&",""),"/","")," ","");
				if(is_numeric(Left($fieldName,1))) {
                    $fieldName = 'numQ' . $fieldName;
                }
                $all_fieldName = $all_fieldName . $fieldName . ';;';
				$field_count = count(splitVB($all_fieldName,";$fieldName;"));
				if($field_count>2) $fieldName = $fieldName . 'Q' . ($field_count-2);

                //$endColumn = $j+1;

                if($newDbalias!='') {
                    if(Left($externalDB[$newDbalias],2)=='OC') {
                        $columnList = $columnList . '"' . $fieldName . '",';
                    } else {
                        $columnList = $columnList . $fieldName . ',';
                    }

                    if(Left($externalDB[$newDbalias],2)=='MY') {
                        $addColumnQuery = $addColumnQuery . "
                        alter table $newRealPid add $fieldName varchar(500);
                        ";
                    } else if(Left($externalDB[$newDbalias],2)=='OC') {
                        // ;-- 를 넣어서 멀티명령구분자로 이용한다. ; 바로 뒤에 넣어야 함.
                        $addColumnQuery = $addColumnQuery . "
                        alter table \"$newRealPid\" add \"$fieldName\" varchar(500);--
                        ";
                    } else {
                        $addColumnQuery = $addColumnQuery . "
                        if not exists 
                        (select * from information_schema.Columns where TABLE_NAME = '$newRealPid' and COLUMN_NAME = '$fieldName' and TABLE_CATALOG='$base_db2') 
                        begin alter table $newRealPid add $fieldName nvarchar(500) end
                        ";
                        $addColumnAlter = $addColumnAlter . "
                        select @cnt=count(*) from $newRealPid where isnumeric(replace(case when $isnull($fieldName,'')='' then '0' else $isnull($fieldName,'') end,',',''))=1
                        if(@allCnt=@cnt) begin 
							select @cnt=count(*) from $newRealPid where $isnull($fieldName,'')=''
							if(@allCnt > @cnt) begin 
								update $newRealPid set $fieldName=replace(case when $isnull($fieldName,'')='' then '0' else $isnull($fieldName,'') end,',','')
								alter table $newRealPid alter column $fieldName float
							end
                        end
                        ";
                    }
                } else {
                    $columnList = $columnList . $fieldName . ',';

                    $addColumnQuery = $addColumnQuery . "
                    if not exists 
                    (select * from information_schema.Columns where TABLE_NAME = '$newRealPid' and COLUMN_NAME = '$fieldName' and TABLE_CATALOG='$base_db2') 
                    begin alter table $newRealPid add $fieldName nvarchar(500) end
                    ";
                    $addColumnAlter = $addColumnAlter . "
                    select @cnt=count(*) from $newRealPid where isnumeric(replace(case when $isnull($fieldName,'')='' then '0' else $isnull($fieldName,'') end,',',''))=1
                    if(@allCnt=@cnt) begin 
						select @cnt=count(*) from $newRealPid where $isnull($fieldName,'')=''
						if(@allCnt > @cnt) begin 
							update $newRealPid set $fieldName=replace(case when $isnull($fieldName,'')='' then '0' else $isnull($fieldName,'') end,',','')
							alter table $newRealPid alter column $fieldName float
						end
					end
                    ";
                }




            } else if($startColumn>0) break;
        }
        if($startRow * $startColumn ==0) {
            execSql("delete from MisMenuList where idx=$newIdx");
            echo "엑셀파일의 상단항목명 인식에 실패했습니다. 10행 10열 이내에 상단항목명이 있어야 합니다.";
            exit;
        }
        //헤더영역
       //echo "$startRow:$startColumn:$endColumn:$addColumnQuery";

        //$startColumn

        if($newDbalias!='') {
            if(Left($externalDB[$newDbalias],2)=='MY') {
                $sql = "
    
                CREATE TABLE `$newRealPid` (
                    `idx` int(11) NOT NULL,
                    `HIT` int(11) DEFAULT NULL,
                    `IP` varchar(50) DEFAULT NULL,
                    `useflag` char(1) DEFAULT '1',
                    `wdate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                    `wdater` varchar(50) DEFAULT NULL,
                    `lastupdate` datetime DEFAULT NULL,
                    `lastupdater` varchar(50) DEFAULT NULL
                  ) ENGINE=MyISAM DEFAULT CHARSET=utf8;
    
                  ALTER TABLE `$newRealPid`
                    ADD KEY `idx` (`idx`);
                  
    
                  ALTER TABLE `$newRealPid`
                    MODIFY `idx` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
                  COMMIT;
    
    
                $addColumnQuery
                ";
            } else if(Left($externalDB[$newDbalias],2)=='OC') {
                $sql = "
    
                BEGIN
                   EXECUTE IMMEDIATE 'DROP TABLE \"$newRealPid\"';
                EXCEPTION
                   WHEN OTHERS THEN
                      IF SQLCODE != -942 THEN
                         RAISE;
                      END IF;
                END;
                ";
                execSql_gate($sql, $newDbalias);	//OC 에서는 drop 와 create 동시 실행시 에러. 해결 시 합칠 것.
    
                $sql = "
                CREATE TABLE \"$newRealPid\" 
                (
                \"IDX\" NUMBER,
                \"HIT\" NUMBER,
                \"IP\" VARCHAR2(50),
                \"USEFLAG\" VARCHAR2(1) DEFAULT 1,
                \"WDATE\" DATE DEFAULT SYSDATE,
                \"WDATER\" VARCHAR2(50),
                \"LASTUPDATE\" DATE,
                \"LASTUPDATER\" VARCHAR2(50)
                )
                ";
                
                execSql_gate($sql, $newDbalias);	//OC 에서는 동시 실행시 에러. 해결 시 합칠 것.
    
                $sql = "
                CREATE SEQUENCE $newRealPid"."_SEQ
                MINVALUE 1
                MAXVALUE 999999999
                INCREMENT BY 1
                START WITH 1
                CACHE 20
                NOORDER
                NOCYCLE;--
                ";
                execSql_gate($sql, $newDbalias);	//OC 에서는 drop 와 create 동시 실행시 에러. 해결 시 합칠 것.
    
                $sql = $addColumnQuery;
                
                execSql_gate($sql, $newDbalias);	//OC 에서는 동시 실행시 에러. 해결 시 합칠 것.
    
                $sql = '';
            
            } else {
				//drop table dbo.$newRealPid 까지 별도 실행해야 됨.
                $sql = "
                if exists(select * from information_schema.tables where table_name='$newRealPid' and TABLE_CATALOG='$base_db2') begin
                    drop table dbo.$newRealPid
                end
				";
				execSql_gate($sql, $newDbalias);

				$sql = "
                CREATE TABLE dbo.$newRealPid (
                    [idx] [int] IDENTITY(1,1) NOT NULL,
                    [hit] [int] NULL,
                    [IP] [nvarchar](50) NULL,
                    [useflag] [nchar](1) NULL,
                    [wdate] [datetime] NULL,
                    [wdater] [nvarchar](50) NULL,
                    [lastupdate] [datetime] NULL,
                    [lastupdater] [nvarchar](50) NULL
                CONSTRAINT [PK_$newRealPid] PRIMARY KEY CLUSTERED 
                (
                    [idx] ASC
                )WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
                ) ON [PRIMARY]
                
                
                ALTER TABLE [dbo].[$newRealPid] ADD  CONSTRAINT [DF_" . $newRealPid . "_hit]  DEFAULT ((0)) FOR [hit]
                ALTER TABLE [dbo].[$newRealPid] ADD  CONSTRAINT [DF_" . $newRealPid . "_useflag]  DEFAULT ((1)) FOR [useflag]
                ALTER TABLE [dbo].[$newRealPid] ADD  CONSTRAINT [DF_" . $newRealPid . "_wdate]  DEFAULT (getdate()) FOR [wdate]
                
                $addColumnQuery
                ";
            }
        } else {
			//drop table dbo.$newRealPid 까지 별도 실행해야 됨.
            $sql = "
                if exists(select * from information_schema.tables where table_name='$newRealPid' and TABLE_CATALOG='$base_db2') begin
                    drop table dbo.$newRealPid
                end
				";
				execSql_gate($sql, $newDbalias);

				$sql = "
                CREATE TABLE dbo.$newRealPid (
                [idx] [int] IDENTITY(1,1) NOT NULL,
                [hit] [int] NULL,
                [IP] [nvarchar](50) NULL,
                [useflag] [nchar](1) NULL,
                [wdate] [datetime] NULL,
                [wdater] [nvarchar](50) NULL,
                [lastupdate] [datetime] NULL,
                [lastupdater] [nvarchar](50) NULL
            CONSTRAINT [PK_$newRealPid] PRIMARY KEY CLUSTERED 
            (
                [idx] ASC
            )WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
            ) ON [PRIMARY]
            
            
            ALTER TABLE [dbo].[$newRealPid] ADD  CONSTRAINT [DF_" . $newRealPid . "_hit]  DEFAULT ((0)) FOR [hit]
            ALTER TABLE [dbo].[$newRealPid] ADD  CONSTRAINT [DF_" . $newRealPid . "_useflag]  DEFAULT ((1)) FOR [useflag]
            ALTER TABLE [dbo].[$newRealPid] ADD  CONSTRAINT [DF_" . $newRealPid . "_wdate]  DEFAULT (getdate()) FOR [wdate]
            
            $addColumnQuery
            ";
        }



        $cnt = 1000000;
        for($i=0;$i<$cnt;$i++) {
            
			if($endColumn<=26) $range = chr(64+$startColumn).($startRow+$i+1).":".chr(64+$endColumn).($startRow+$i+1);
			else if($endColumn<=52) $range = chr(64+$startColumn).($startRow+$i+1).":A".chr(64+$endColumn-26).($startRow+$i+1);
			else if($endColumn<=78) $range = chr(64+$startColumn).($startRow+$i+1).":B".chr(64+$endColumn-52).($startRow+$i+1);
			else if($endColumn<=104) $range = chr(64+$startColumn).($startRow+$i+1).":C".chr(64+$endColumn-78).($startRow+$i+1);
			else $range = chr(64+$startColumn).($startRow+$i+1).":D".chr(64+$endColumn-104).($startRow+$i+1);


            

			//$range 가 첫 데이터영역행이며, 실제칼럼까지면 정상.
            //echo $range;exit;
            $allData = $objPHPExcel->getActiveSheet()->rangeToArray($range);
            
            if($allData[0][0]=="") {
                $real_cnt = $i;
                $i = 9999999;
            } else {
                if($newDbalias!='') {
                    if(Left($externalDB[$newDbalias],2)=='OC') {
                        $sql = $sql . " 
                        insert into \"$newRealPid\" ($columnList \"WDATER\", idx) values ";

                        $cnt_allData_0 = count($allData[0]);
                        for($j=0;$j<$cnt_allData_0;$j++) {
                            $field_value = replace($allData[0][$j],"'","''");
                            if($j==0) $sql = $sql . "(N'" . $field_value . "'";
                            else $sql = $sql . ",N'" . $field_value . "'";
                        }
                        $sql = $sql . ",N'" . $misSessionUserId . "', $newRealPid" . "_SEQ.NEXTVAL);
                        ";
                    } else {
                        $sql = $sql . " 
                        insert into $newRealPid ($columnList WDATER) values ";
                        $cnt_allData_0 = count($allData[0]);
                        for($j=0;$j<$cnt_allData_0;$j++) {
                            $field_value = replace($allData[0][$j],"'","''");
                            if($j==0) $sql = $sql . "(N'" . $field_value . "'";
                            else $sql = $sql . ",N'" . $field_value . "'";
                        }
                        $sql = $sql . ",N'" . $misSessionUserId . "');
                        ";
                    }
                } else {
                    $sql = $sql . " 
                    insert into $newRealPid ($columnList WDATER) values ";
                    $cnt_allData_0 = count($allData[0]);
                    for($j=0;$j<$cnt_allData_0;$j++) {
                        $field_value = replace($allData[0][$j],"'","''");
                        if($j==0) $sql = $sql . "(N'" . $field_value . "'";
                        else $sql = $sql . ",N'" . $field_value . "'";
                    }
                    $sql = $sql . ",N'" . $misSessionUserId . "');
                    ";
                }


            }
        }

        if($newDbalias!='') {
            if(Left($externalDB[$newDbalias],2)!='MY' && Left($externalDB[$newDbalias],2)!='OC') {
                $sql = $sql . "
    
                declare @allCnt int, @cnt int 
                select @allCnt=count(*) from $newRealPid
    
                $addColumnAlter
    
                ";
            }
        } else {
            $sql = $sql . "
    
            declare @allCnt int, @cnt int 
            select @allCnt=count(*) from $newRealPid

            $addColumnAlter

            ";    
        }



		//echo $sql;exit;
        execSql_gate($sql, $newDbalias);
		
        $sql = "";

        //---------------- 테이블 생성 및 엑셀데이타 입력 끝------------





        //---------------- MisMenuList_Detail 생성 시작------------
        $sql_next = $sql_next . "
        delete from MisMenuList_Detail where RealPid='$newRealPid';
        update MisMenuList set g08='$newRealPid', dbalias=N'" . $updateList['dbalias'] . "' where RealPid='$newRealPid';
        insert into MisMenuList_Detail (RealPid, SortElement, Grid_Select_Field, Grid_Select_Tname, 
        aliasName, Grid_Columns_Title, Grid_Columns_Width, wdater 
        ,Grid_Schema_Type)
        ";
        //echo $base_db2;exit;
        if($newDbalias!='') {
            if(Left($externalDB[$newDbalias],2)=='MY') {
                $tempSql = "
                select '$newRealPid' as 'RealPid', ORDINAL_POSITION as 'SortElement', COLUMN_NAME as 'Grid_Select_Field', 'table_m' as 'Grid_Select_Tname', 
                COLUMN_NAME as 'aliasName', COLUMN_NAME as 'Grid_Columns_Title', 10 as 'Grid_Columns_Width', '$misSessionUserId' as 'wdater'
                ,case when ORDINAL_POSITION=1 then '' when left(COLUMN_TYPE,3)='int' then 'number' when COLUMN_TYPE='date' then 'date' when COLUMN_TYPE='datetime' then 'datetime' else '' end as 'Grid_Schema_Type'
                from INFORMATION_SCHEMA.COLUMNS where TABLE_NAME='$newRealPid' and TABLE_SCHEMA='$base_db2'
                order by ORDINAL_POSITION;
                ";
                

            } else if(Left($externalDB[$newDbalias],2)=='OC') {
                $tempSql = "
                select '$newRealPid' as \"RealPid\", column_id as \"SortElement\", column_name as \"Grid_Select_Field\", 'table_m' as \"Grid_Select_Tname\", 
                column_name as \"aliasName\", column_name as \"Grid_Columns_Title\", 10 as \"Grid_Columns_Width\", '$misSessionUserId' as \"wdater\"
                ,case when column_id=1 then '' when data_type='NUMBER' then 'number' when data_type='DATE' then 'date' else '' end as \"Grid_Schema_Type\"
                from user_tab_cols where TABLE_NAME='$newRealPid' and column_id > 0 order by column_id
                ";
            } else {
                $tempSql = "
                select '$newRealPid' as 'RealPid', ROW_NUMBER() over (order by case when colorder between 2 and 8 then colorder+80 else colorder end) as 'SortElement', name as 'Grid_Select_Field', 'table_m' as 'Grid_Select_Tname', 
                name as 'aliasName', name as 'Grid_Columns_Title', 10 as 'Grid_Columns_Width', '$misSessionUserId' as 'wdater'
                ,case when colorder=1 then '' when xtype=62 or xtype=56 then 'number' when xtype=104 then 'boolean' when xtype=61 then 'date' else '' end as 'Grid_Schema_Type'
                from " . $db_name . ".syscolumns where id=(select id from " . $db_name . ".sysobjects where name='$newRealPid' and (type='U' or type='V')) 
                and xtype<>165 
                order by case when colorder between 2 and 8 then colorder+80 else colorder end
                ";
            }
            $data = allreturnSql_gate($tempSql, $newDbalias);
            $sql_next = $sql_next . ' values ';

            $cnt_data = count($data);
            for($k=0;$k<$cnt_data;$k++) {
                $r_RealPid = $data[$k]['RealPid'];
                $r_SortElement = $data[$k]['SortElement'];
                $r_Grid_Select_Field = $data[$k]['Grid_Select_Field'];
                $r_Grid_Select_Tname = $data[$k]['Grid_Select_Tname'];
                $r_aliasName = $data[$k]['aliasName'];
                $r_Grid_Columns_Title = replace($data[$k]['Grid_Columns_Title'],'numQ','');
                $r_Grid_Columns_Width = $data[$k]['Grid_Columns_Width'];
                $r_wdater = $data[$k]['wdater'];
                $r_Grid_Schema_Type = $data[$k]['Grid_Schema_Type'];
                $sql_next = $sql_next . iif($k>0,",","") . "
                ('$r_RealPid', '$r_SortElement', '$r_Grid_Select_Field', '$r_Grid_Select_Tname', '$r_aliasName', '$r_Grid_Columns_Title', '$r_Grid_Columns_Width', '$r_wdater', '$r_Grid_Schema_Type')";
            }
            $sql_next = $sql_next . ';';
        } else {
            $sql_next = $sql_next . "
            select '$newRealPid' as 'RealPid', ROW_NUMBER() over (order by case when colorder between 2 and 8 then colorder+80 else colorder end) as 'SortElement', name as 'Grid_Select_Field', 'table_m' as 'Grid_Select_Tname', 
            name as 'aliasName', replace(name,'numQ','') as 'Grid_Columns_Title', 10 as 'Grid_Columns_Width', '$misSessionUserId' as 'wdater'
            ,case when colorder=1 then '' when xtype=62 or xtype=56 then 'number' when xtype=104 then 'boolean' when xtype=61 then 'date' else '' end as 'Grid_Schema_Type'
            from " . $db_name . ".syscolumns where id=(select id from " . $db_name . ".sysobjects where name='$newRealPid' and (type='U' or type='V')) 
            and xtype<>165
            order by case when colorder between 2 and 8 then colorder+80 else colorder end
            ";

        }
        $sql_next = $sql_next . "
            update MisMenuList_Detail set Grid_Orderby='1a' where SortElement=1 and RealPid='$newRealPid'; 
        ";
       

        //---------------- MisMenuList_Detail 생성 끝------------


        //엑셀 업로드
        $updateList['g01'] = 'simplelist';
        $updateList['g07'] = 'Y';   //읽기전용
        $updateList['new_gidx'] = '83'; $updateList['AuthCode'] = '02'; //개발자 전용권한
    } else if($dataPullType==5) {    //구글스프레드
        

        $newDbalias = $updateList['dbalias'];
        if($newDbalias=='default') $newDbalias = '';

        //---------------- 테이블 생성 및 엑셀데이타 입력 시작------------

        $url = $updateList['spreadsheets_url'];
        if(InStr($url, '/spreadsheets/d/')>0) {
            $url = replace(replace($url, 'spreadsheets/d/', 'spreadsheets/u/0/d/'), '/edit#gid=', '/gviz/tq?gid=');
        }
        $json = file_get_contents_new($url);
        if (InStr($json, 'not publicly')+InStr($json, 'Moved Temporarily')>0) {
            echo "접근가능한 URL 이어야 합니다. 공유설정을 확인하세요!";
            exit;
        } else if (InStr($json, 'google.visualization.Query.setResponse({"version":')==0) {
            echo "정상적인 구글 스프레드 문서 URL 을 입력하세요! 아래와 같은 형식이어야 합니다.
https://docs.google.com/spreadsheets/d/1J-BxjYJivMxrmb8j-v_9X7MzMIvEzLGam7Gio2jzPlg/edit#gid=51740135
";
            exit;
        }
        $json = '[{"version":' . splitVB($json, 'google.visualization.Query.setResponse({"version":')[1];
        $json = Left($json, Len($json) - 2) . ']';


        $json = json_decode($json)[0];

        $json_table = $json->table;
        $json_table_cols = $json_table->cols;
        $json_table_rows = $json_table->rows;



        $startRow = 0;
        $startColumn = 0;
        $endColumn = 0;
		$columnCount = 0;



        //헤더의 시작과 끝 구하기
        $addColumnQuery = '';$columnList = '';$addColumnAlter = '';
		$all_fieldName = ';;';

        $cnt_json_table_cols = count($json_table_cols);
        for($j=0;$j<$cnt_json_table_cols;$j++) {

            if($startColumn==0) {
                $startColumn = $j+1;
                $addColumnQuery = '';
            }
            $fieldName = replace(replace(replace(replace(replace(replace(replace(replace(replace(replace(replace(replace(replace($json_table_cols[$j]->label,"\n",""),".",""),",",""),"(",""),")",""),"[",""),"]",""),"*",""),":",""),"-",""),"&",""),"/","")," ","");
            $all_fieldName = $all_fieldName . $fieldName . ';;';
            $field_count = count(splitVB($all_fieldName,";$fieldName;"));
            if($field_count>2) $fieldName = $fieldName . 'Q' . ($field_count-2);

            //$endColumn = $j+1;

            if($newDbalias!='') {
                if(Left($externalDB[$newDbalias],2)=='OC') {
                    $columnList = $columnList . '"' . $fieldName . '",';
                } else {
                    $columnList = $columnList . $fieldName . ',';
                }

                if(Left($externalDB[$newDbalias],2)=='MY') {
                    $addColumnQuery = $addColumnQuery . "
                    alter table $newRealPid add $fieldName varchar(500);
                    ";
                } else if(Left($externalDB[$newDbalias],2)=='OC') {
                    // ;-- 를 넣어서 멀티명령구분자로 이용한다. ; 바로 뒤에 넣어야 함.
                    $addColumnQuery = $addColumnQuery . "
                    alter table \"$newRealPid\" add \"$fieldName\" varchar(500);--
                    ";
                } else {
                    $addColumnQuery = $addColumnQuery . "
                    if not exists 
                    (select * from information_schema.Columns where TABLE_NAME = '$newRealPid' and COLUMN_NAME = '$fieldName' and TABLE_CATALOG='$base_db2') 
                    begin alter table $newRealPid add $fieldName nvarchar(500) end
                    ";
                    $addColumnAlter = $addColumnAlter . "
                    select @cnt=count(*) from $newRealPid where isnumeric(replace(case when $isnull($fieldName,'')='' then '0' else $isnull($fieldName,'') end,',',''))=1
                    if(@allCnt=@cnt) begin 
                        select @cnt=count(*) from $newRealPid where $isnull($fieldName,'')=''
                        if(@allCnt > @cnt) begin 
                            update $newRealPid set $fieldName=replace(case when $isnull($fieldName,'')='' then '0' else $isnull($fieldName,'') end,',','')
                            alter table $newRealPid alter column $fieldName float
                        end
                    end
                    ";
                }
            } else {
                $columnList = $columnList . $fieldName . ',';

                $addColumnQuery = $addColumnQuery . "
                if not exists 
                (select * from information_schema.Columns where TABLE_NAME = '$newRealPid' and COLUMN_NAME = '$fieldName' and TABLE_CATALOG='$base_db2') 
                begin alter table $newRealPid add $fieldName nvarchar(500) end
                ";
                $addColumnAlter = $addColumnAlter . "
                select @cnt=count(*) from $newRealPid where isnumeric(replace(case when $isnull($fieldName,'')='' then '0' else $isnull($fieldName,'') end,',',''))=1
                if(@allCnt=@cnt) begin 
                    select @cnt=count(*) from $newRealPid where $isnull($fieldName,'')=''
                    if(@allCnt > @cnt) begin 
                        update $newRealPid set $fieldName=replace(case when $isnull($fieldName,'')='' then '0' else $isnull($fieldName,'') end,',','')
                        alter table $newRealPid alter column $fieldName float
                    end
                end
                ";
            }


        }


        if($newDbalias!='') {
            if(Left($externalDB[$newDbalias],2)=='MY') {
                $sql = "
    
                CREATE TABLE `$newRealPid` (
                    `idx` int(11) NOT NULL,
                    `HIT` int(11) DEFAULT NULL,
                    `IP` varchar(50) DEFAULT NULL,
                    `useflag` char(1) DEFAULT '1',
                    `wdate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                    `wdater` varchar(50) DEFAULT NULL,
                    `lastupdate` datetime DEFAULT NULL,
                    `lastupdater` varchar(50) DEFAULT NULL
                  ) ENGINE=MyISAM DEFAULT CHARSET=utf8;
    
                  ALTER TABLE `$newRealPid`
                    ADD KEY `idx` (`idx`);
                  
    
                  ALTER TABLE `$newRealPid`
                    MODIFY `idx` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
                  COMMIT;
    
    
                $addColumnQuery
                ";
            } else if(Left($externalDB[$newDbalias],2)=='OC') {
                $sql = "
    
                BEGIN
                   EXECUTE IMMEDIATE 'DROP TABLE \"$newRealPid\"';
                EXCEPTION
                   WHEN OTHERS THEN
                      IF SQLCODE != -942 THEN
                         RAISE;
                      END IF;
                END;
                ";
                execSql_gate($sql, $newDbalias);	//OC 에서는 drop 와 create 동시 실행시 에러. 해결 시 합칠 것.
    
                $sql = "
                CREATE TABLE \"$newRealPid\" 
                (
                \"IDX\" NUMBER,
                \"HIT\" NUMBER,
                \"IP\" VARCHAR2(50),
                \"USEFLAG\" VARCHAR2(1) DEFAULT 1,
                \"WDATE\" DATE DEFAULT SYSDATE,
                \"WDATER\" VARCHAR2(50),
                \"LASTUPDATE\" DATE,
                \"LASTUPDATER\" VARCHAR2(50)
                )
                ";
                
                execSql_gate($sql, $newDbalias);	//OC 에서는 동시 실행시 에러. 해결 시 합칠 것.
    
                $sql = "
                CREATE SEQUENCE $newRealPid"."_SEQ
                MINVALUE 1
                MAXVALUE 999999999
                INCREMENT BY 1
                START WITH 1
                CACHE 20
                NOORDER
                NOCYCLE;--
                ";
                execSql_gate($sql, $newDbalias);	//OC 에서는 drop 와 create 동시 실행시 에러. 해결 시 합칠 것.
    
                $sql = $addColumnQuery;
                
                execSql_gate($sql, $newDbalias);	//OC 에서는 동시 실행시 에러. 해결 시 합칠 것.
    
                $sql = '';
            
            } else {
				//drop table dbo.$newRealPid 까지 별도 실행해야 됨.
                $sql = "
                if exists(select * from information_schema.tables where table_name='$newRealPid' and TABLE_CATALOG='$base_db2') begin
                    drop table dbo.$newRealPid
                end
				";
				execSql_gate($sql, $newDbalias);

				$sql = "
                CREATE TABLE dbo.$newRealPid (
                    [idx] [int] IDENTITY(1,1) NOT NULL,
                    [hit] [int] NULL,
                    [IP] [nvarchar](50) NULL,
                    [useflag] [nchar](1) NULL,
                    [wdate] [datetime] NULL,
                    [wdater] [nvarchar](50) NULL,
                    [lastupdate] [datetime] NULL,
                    [lastupdater] [nvarchar](50) NULL
                CONSTRAINT [PK_$newRealPid] PRIMARY KEY CLUSTERED 
                (
                    [idx] ASC
                )WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
                ) ON [PRIMARY]
                
                
                ALTER TABLE [dbo].[$newRealPid] ADD  CONSTRAINT [DF_" . $newRealPid . "_hit]  DEFAULT ((0)) FOR [hit]
                ALTER TABLE [dbo].[$newRealPid] ADD  CONSTRAINT [DF_" . $newRealPid . "_useflag]  DEFAULT ((1)) FOR [useflag]
                ALTER TABLE [dbo].[$newRealPid] ADD  CONSTRAINT [DF_" . $newRealPid . "_wdate]  DEFAULT (getdate()) FOR [wdate]
                
                $addColumnQuery
                ";
            }
        } else {
			//drop table dbo.$newRealPid 까지 별도 실행해야 됨.
            $sql = "
                if exists(select * from information_schema.tables where table_name='$newRealPid' and TABLE_CATALOG='$base_db2') begin
                    drop table dbo.$newRealPid
                end
				";
				execSql_gate($sql, $newDbalias);

				$sql = "
                CREATE TABLE dbo.$newRealPid (
                [idx] [int] IDENTITY(1,1) NOT NULL,
                [hit] [int] NULL,
                [IP] [nvarchar](50) NULL,
                [useflag] [nchar](1) NULL,
                [wdate] [datetime] NULL,
                [wdater] [nvarchar](50) NULL,
                [lastupdate] [datetime] NULL,
                [lastupdater] [nvarchar](50) NULL
            CONSTRAINT [PK_$newRealPid] PRIMARY KEY CLUSTERED 
            (
                [idx] ASC
            )WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
            ) ON [PRIMARY]
            
            
            ALTER TABLE [dbo].[$newRealPid] ADD  CONSTRAINT [DF_" . $newRealPid . "_hit]  DEFAULT ((0)) FOR [hit]
            ALTER TABLE [dbo].[$newRealPid] ADD  CONSTRAINT [DF_" . $newRealPid . "_useflag]  DEFAULT ((1)) FOR [useflag]
            ALTER TABLE [dbo].[$newRealPid] ADD  CONSTRAINT [DF_" . $newRealPid . "_wdate]  DEFAULT (getdate()) FOR [wdate]
            
            $addColumnQuery
            ";
        }



        $cnt = count($json_table_rows);
        for($i=0;$i<$cnt;$i++) {
            
            $allData = $json_table_rows[$i]->c;
            
            
            if($newDbalias!='') {
                if(Left($externalDB[$newDbalias],2)=='OC') {
                    $sql = $sql . " 
                    insert into \"$newRealPid\" ($columnList \"WDATER\", idx) values ";
                    $cnt_allData = count($allData);
                    for($j=0;$j<$cnt_allData;$j++) {
                        if($allData[$j]==null) {
                            if($j==0) $sql = $sql . "(null";
                            else $sql = $sql . ",null";
                        } else {
                            $field_value = ($allData[$j])->v;
                            $field_value = replace($field_value,"'","''");
                            if($j==0) $sql = $sql . "(N'" . $field_value . "'";
                            else $sql = $sql . ",N'" . $field_value . "'";
                        }
                    }
                    $sql = $sql . ",N'" . $misSessionUserId . "', $newRealPid" . "_SEQ.NEXTVAL);
                    ";
                } else {
                    $sql = $sql . " 
                    insert into $newRealPid ($columnList WDATER) values ";
                    $cnt_allData = count($allData);
                    for($j=0;$j<$cnt_allData;$j++) {
                        if($allData[$j]==null) {
                            if($j==0) $sql = $sql . "(null";
                            else $sql = $sql . ",null";
                        } else {
                            $field_value = ($allData[$j])->v;
                            $field_value = replace($field_value,"'","''");
                            if($j==0) $sql = $sql . "(N'" . $field_value . "'";
                            else $sql = $sql . ",N'" . $field_value . "'";
                        }
                    }
                    $sql = $sql . ",N'" . $misSessionUserId . "');
                    ";
                }
            } else {
                $sql = $sql . " 
                insert into $newRealPid ($columnList WDATER) values ";
                $cnt_allData = count($allData);
                for($j=0;$j<$cnt_allData;$j++) {
                    if($allData[$j]==null) {
                        if($j==0) $sql = $sql . "(null";
                        else $sql = $sql . ",null";
                    } else {
                        $field_value = ($allData[$j])->v;
                        $field_value = replace($field_value,"'","''");
                        if($j==0) $sql = $sql . "(N'" . $field_value . "'";
                        else $sql = $sql . ",N'" . $field_value . "'";
                    }
                }
                $sql = $sql . ",N'" . $misSessionUserId . "');
                ";
            }

        }

        if($newDbalias!='') {
            if(Left($externalDB[$newDbalias],2)!='MY' && Left($externalDB[$newDbalias],2)!='OC') {
                $sql = $sql . "
    
                declare @allCnt int, @cnt int 
                select @allCnt=count(*) from $newRealPid
    
                $addColumnAlter
    
                ";
            }
        } else {
            $sql = $sql . "
    
            declare @allCnt int, @cnt int 
            select @allCnt=count(*) from $newRealPid

            $addColumnAlter

            ";    
        }



		//echo $sql;exit;
        execSql_gate($sql, $newDbalias);
		
        $sql = "";

        //---------------- 테이블 생성 및 엑셀데이타 입력 끝------------








        //---------------- MisMenuList_Detail 생성 시작------------
        $sql_next = $sql_next . "
        delete from MisMenuList_Detail where RealPid='$newRealPid';
        update MisMenuList set g08='$newRealPid' where RealPid='$newRealPid';
        insert into MisMenuList_Detail (RealPid, SortElement, Grid_Select_Field, Grid_Select_Tname, 
        aliasName, Grid_Columns_Title, Grid_Columns_Width, wdater 
        ,Grid_Schema_Type)
        ";
        if($newDbalias!='') {
            if(Left($externalDB[$newDbalias],2)=='MY') {
                $tempSql = "
                select '$newRealPid' as 'RealPid', ORDINAL_POSITION as 'SortElement', COLUMN_NAME as 'Grid_Select_Field', 'table_m' as 'Grid_Select_Tname', 
                COLUMN_NAME as 'aliasName', COLUMN_NAME as 'Grid_Columns_Title', 10 as 'Grid_Columns_Width', '$misSessionUserId' as 'wdater'
                ,case when ORDINAL_POSITION=1 then '' when left(COLUMN_TYPE,3)='int' then 'number' when COLUMN_TYPE='date' then 'date' when COLUMN_TYPE='datetime' then 'datetime' else '' end as 'Grid_Schema_Type'
                from INFORMATION_SCHEMA.COLUMNS where TABLE_NAME='$newRealPid'  and TABLE_SCHEMA='$base_db2'
                order by ORDINAL_POSITION;
                ";

            } else if(Left($externalDB[$newDbalias],2)=='OC') {
                $tempSql = "
                select '$newRealPid' as \"RealPid\", column_id as \"SortElement\", column_name as \"Grid_Select_Field\", 'table_m' as \"Grid_Select_Tname\", 
                column_name as \"aliasName\", column_name as \"Grid_Columns_Title\", 10 as \"Grid_Columns_Width\", '$misSessionUserId' as \"wdater\"
                ,case when column_id=1 then '' when data_type='NUMBER' then 'number' when data_type='DATE' then 'date' else '' end as \"Grid_Schema_Type\"
                from user_tab_cols where TABLE_NAME='$newRealPid' and column_id > 0 order by column_id
                ";
            } else {
                $tempSql = "
                select '$newRealPid' as 'RealPid', ROW_NUMBER() over (order by case when colorder between 2 and 8 then colorder+80 else colorder end) as 'SortElement', name as 'Grid_Select_Field', 'table_m' as 'Grid_Select_Tname', 
                name as 'aliasName', name as 'Grid_Columns_Title', 10 as 'Grid_Columns_Width', '$misSessionUserId' as 'wdater'
                ,case when colorder=1 then '' when xtype=62 or xtype=56 then 'number' when xtype=104 then 'boolean' when xtype=61 then 'date' else '' end as 'Grid_Schema_Type'
                from " . $db_name . ".syscolumns where id=(select id from " . $db_name . ".sysobjects where name='$newRealPid' and (type='U' or type='V')) 
                and xtype<>165 
                order by case when colorder between 2 and 8 then colorder+80 else colorder end
                ";
            }
            $data = allreturnSql_gate($tempSql, $newDbalias);
            $sql_next = $sql_next . ' values ';

            $cnt_data = count($data);
            for($k=0;$k<$cnt_data;$k++) {
                $r_RealPid = $data[$k]['RealPid'];
                $r_SortElement = $data[$k]['SortElement'];
                $r_Grid_Select_Field = $data[$k]['Grid_Select_Field'];
                $r_Grid_Select_Tname = $data[$k]['Grid_Select_Tname'];
                $r_aliasName = $data[$k]['aliasName'];
                $r_Grid_Columns_Title = $data[$k]['Grid_Columns_Title'];
                $r_Grid_Columns_Width = $data[$k]['Grid_Columns_Width'];
                $r_wdater = $data[$k]['wdater'];
                $r_Grid_Schema_Type = $data[$k]['Grid_Schema_Type'];
                $sql_next = $sql_next . iif($k>0,",","") . "
                ('$r_RealPid', '$r_SortElement', '$r_Grid_Select_Field', '$r_Grid_Select_Tname', '$r_aliasName', '$r_Grid_Columns_Title', '$r_Grid_Columns_Width', '$r_wdater', '$r_Grid_Schema_Type')";
            }
            $sql_next = $sql_next . ';';
        } else {
            $sql_next = $sql_next . "
            select '$newRealPid' as 'RealPid', ROW_NUMBER() over (order by case when colorder between 2 and 8 then colorder+80 else colorder end) as 'SortElement', name as 'Grid_Select_Field', 'table_m' as 'Grid_Select_Tname', 
            name as 'aliasName', name as 'Grid_Columns_Title', 10 as 'Grid_Columns_Width', '$misSessionUserId' as 'wdater'
            ,case when colorder=1 then '' when xtype=62 or xtype=56 then 'number' when xtype=104 then 'boolean' when xtype=61 then 'date' else '' end as 'Grid_Schema_Type'
            from " . $db_name . ".syscolumns where id=(select id from " . $db_name . ".sysobjects where name='$newRealPid' and (type='U' or type='V')) 
            and xtype<>165
            order by case when colorder between 2 and 8 then colorder+80 else colorder end
            ";

        }
        $sql_next = $sql_next . "
            update MisMenuList_Detail set Grid_Orderby='1a' where SortElement=1 and RealPid='$newRealPid'; 
        ";

        //---------------- MisMenuList_Detail 생성 끝------------


        //엑셀 업로드
        $updateList['g01'] = 'simplelist';
        $updateList['g07'] = 'Y';   //읽기전용
        $updateList['new_gidx'] = '83'; $updateList['AuthCode'] = '02'; //개발자 전용권한
    }


    $updateList["RealPid"] = $newRealPid;


    //print_r($updateList);
    //exit;
    unset($updateList['추가위치']);
    unset($updateList['sourceCopy']);
    //unset($updateList['g08b']);
    //unset($updateList['g01b']);

}
//end save_writeBefore



function save_writeAfter() {


    global $base_root, $real_pid, $misJoinPid, $logicPid, $parent_idx, $full_siteID, $db_name;
    global $key_aliasName, $key_value, $saveList, $saveUploadList, $viewList, $deleteList;
    global $Grid_Default, $actionFlag, $misSessionUserId, $newIdx;
    global $afterScript, $externalDB, $MS_MJ_MY, $full_site;

 
    
    $data = allreturnSql("select RealPid, dbalias from MisMenuList where idx=$newIdx");
    $newRealPid = $data[0]['RealPid'];

    if($MS_MJ_MY=='MY') {
        $sql = "
        update MisMenuList set g10='111=111' where RealPid='$newRealPid'
        and not exists(select * from MisMenuList_Detail where RealPid='$newRealPid' and Grid_Select_Field='useflag');
        
        update MisMenuList_Detail set Grid_Columns_Width=-1 where  RealPid='$newRealPid' and Grid_Select_Field='useflag';
        update MisMenuList_Detail set Grid_Columns_Width=0 where  RealPid='$newRealPid' and Grid_Select_Field in ('hit','IP'); 
        update MisMenuList_Detail set Grid_MaxLength=8 where  RealPid='$newRealPid' and SortElement=1;
        update MisMenuList_Detail set Grid_Columns_Width=0, Grid_FormGroup='등록정보'
        where  RealPid='$newRealPid' and Grid_Select_Field in ('wdate','wdater','lastupdate','lastupdater');
        call MisUser_Authority_Proc ('$full_siteID','speedmis000001');
        update MisUser set menuRefresh = '' where uniquenum=N'$misSessionUserId';
        ";
    } else {
        $sql = "
        if not exists(select * from MisMenuList_Detail where RealPid='$newRealPid' and Grid_Select_Field='useflag')
        update MisMenuList set g10='111=111' where RealPid='$newRealPid'
        
        update MisMenuList_Detail set Grid_Columns_Width=-1 where  RealPid='$newRealPid' and Grid_Select_Field='useflag' 
        update MisMenuList_Detail set Grid_Columns_Width=0 where  RealPid='$newRealPid' and Grid_Select_Field in ('hit','IP') 
        update MisMenuList_Detail set Grid_MaxLength=8 where  RealPid='$newRealPid' and SortElement=1
        update MisMenuList_Detail set Grid_Columns_Width=0, Grid_FormGroup='등록정보' 
        where  RealPid='$newRealPid' and Grid_Select_Field in ('wdate','wdater','lastupdate','lastupdater')
        exec MisUser_Authority_Proc '$full_siteID','speedmis000001'
        update MisUser set menuRefresh = '' where uniquenum=N'$misSessionUserId'
        ";
    }


    execSql($sql);
    aliasN_update_RealPid($newRealPid);
    setcookie("newLogin", "Y", 0, "/");

	$url = "$full_site/_mis/list_json.php?flag=readResult&RealPid=speedmis000267&app=자동정렬&parent_idx=$newRealPid";
	file_get_contents_new($url);

}
//end save_writeAfter



function addLogic_treat() {

    global $misSessionUserId;
    
    //addLogic_treat 함수는 ajax 로 요청되어진(url 형식) 것에 대한 출력문입니다. echo 등으로 출력내용만 표시하면 됩니다.
    //아래는 url 에 동반된 파라메터의 예입니다.
    //해당 예제 TIP 의 기본폼에 보면 addLogic_treat 를 호출하는 코딩이 있습니다.

    $question = requestVB("question");
    $pidx = requestVB("pidx");

    //아래는 값에 따라 mysql 서버를 통해 알맞는 값을 출력하여 보냅니다.
    if($question=="depth3") {
        $sql = " select MenuName from MisMenuList where AutoGubun=(select left(AutoGubun,2) from MisMenuList where idx=$pidx) ";
        gzecho(onlyOnereturnSql($sql));
    }

}
//end addLogic_treat

?>