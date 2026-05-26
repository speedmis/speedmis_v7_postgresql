<?php

function misMenuList_change() {

	//misMenuList 테이블에 의한 설정값인 $result 를 바꾸는게 이 함수의 핵심기능
    global $ActionFlag, $gubun, $parent_idx, $RealPid, $logicPid, $result;
    global $MisSession_PositionCode, $flag, $externalDB, $idx, $MS_MJ_MY;
    global $dataTextField, $result, $dbalias, $table_m, $base_db, $base_db2;
	if($ActionFlag=="write") {
        $search_index = array_search("virtual_fieldQndbalias", array_column($result, 'alias_name'));

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
		$result[$search_index]["items"] = $dbList;
    }
    if($dataTextField=='table_g08QnTABLE_NAME') {
        $default_dbalias = requestVB('app');
        if($default_dbalias=='') $default_dbalias = onlyOneReturnSql("select dbalias from mis_menus where idx=$idx");
        if(($default_dbalias=='default' || $default_dbalias=='') && $MS_MJ_MY=='MY') $default_dbalias = '1st';
        //gzecho('zz'.$default_dbalias);exit;
        if($default_dbalias!='default' && $default_dbalias!='') {
            $dbalias = $default_dbalias;
            connectDB_dbalias($dbalias);
            
            if(Left($externalDB[$default_dbalias],2)=='MY') {
                $result[1]['prime_key'] = "concat(table_name, ' / rows:', ifnull(table_rows,0), ' / ', table_type)#information_schema.tables#1#TABLE_NAME#(@outer_tbname.TABLE_NAME not like 'Speed%' or @outer_tbname.TABLE_NAME like 'Speedm%') and @outer_tbname.TABLE_SCHEMA='$base_db2'";
            } else if(Left($externalDB[$default_dbalias],2)=='OC') {
                $result[1]['prime_key'] = "OBJECT_NAME||' / rows'||(SELECT NUM_ROWS from user_tables where table_name=OBJECT_NAME)||' / '||OBJECT_TYPE#USER_OBJECTS#1#OBJECT_NAME#(OBJECT_TYPE='TABLE' or OBJECT_TYPE='VIEW') and OBJECT_NAME not like '%$%'";
            }
        }
    }

}
//end misMenuList_change



function pageLoad() {

    global $ActionFlag,$paidKey_ucount,$full_siteID;
	global $MisSession_IsAdmin, $RealPid, $menu_type, $idx, $externalDB;


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
    if(!confirm(getGridCellValue_idx(p_idx,'menu_name')+' 메뉴 하위의 '+getGridCellValue_idx(p_idx,'zhawiAPP')
    +'개 메뉴의 권한도 \n'+getGridCellValue_idx(p_idx,'table_new_gidxQngname')+' | '+getGridCellValue_idx(p_idx,'table_AuthCodeQnkname')
    +'\n로 바꾸시겠습니까?')) return false;
    $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "권한전달."+p_idx;
    $("#grid").data("kendoGrid").dataSource.read();
    $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "";
}


function columns_templete(p_dataItem, p_aliasName) {
    
    if(p_aliasName=="RealPid") {
		var rValue = "<a href='index.php?RealPid=" + p_dataItem["RealPid"] + "&isMenuIn=Y' target='_blank' class='k-button'>연결</a>";
		if(p_dataItem["menu_type"]=="01") {
			rValue = rValue + "<a id='aid_" + p_dataItem["idx"] + "' href='index.php?RealPid=speedmis000266&idx=" 
				+ p_dataItem["RealPid"] + "&isMenuIn=Y' target='_blank' class='k-button'>소스</a>";
		} else if(p_dataItem["menu_type"]=="22") {
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
            rValue = rValue + ' > ' + p_dataItem['menu_name'];
        } else if(p_dataItem['depth']==2) {
            rValue = rValue + ' > ' + p_dataItem['table_upRealPidQnMenuName'] + ' > ' + p_dataItem['menu_name'];
        } else if(p_dataItem['depth']==3) {
            //depth==3 일 경우에 한해 최상위 메뉴명을 별도로 구해야 함.
            url = '/_mis/add_logic_treat.php?RealPid=<?php echo $RealPid; ?>&pidx='+p_dataItem['idx']+'&question=depth3';
            topMenuName = ajax_url_return2(url);
            rValue = rValue + ' > ' + topMenuName + ' > ' + p_dataItem['table_upRealPidQnMenuName'] + ' > ' + p_dataItem['menu_name'];
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
    //p_this.menu_name = p_this.depth + p_this.menu_name; 
    //alert(p_this.menu_name)
    //p_this.AutoGubun = 
    //"<a href=index.php?gubun=" + p_this.idx + "&isMenuIn=Y target=_blank>[Go]</a> <a id='aid_" + p_this.idx + "' href=index.php?RealPid=speedmis000266&idx=" + p_this.idx + "&isMenuIn=Y target=_blank>[Source]</a>?" 
    //+ p_this.AutoGubun; 
}
<?php if($MisSession_IsAdmin=="Y" && $ActionFlag=="list") { ?>
function thisLogic_toolbar() {
	
    $("a#btn_1").text("권한 및 메뉴적용");
    $("li#btn_1_overflow").text("권한 및 메뉴적용");
    $("#btn_1").css("background", "#88f");
    $("#btn_1").css("color", "#fff");
    $("#btn_1").click( function() {
		<?php if($paidKey_ucount*1<5) { ?>
			if(!confirm("비구매 고객의 경우, gadmin / admin 포함하여 20개 유저 외에는 삭제될 수 있으며, 웹소스관리에 추가된 20개 프로그램 외에는 삭제될 수 있습니다. 진행할까요?")) return false; 
		<?php } else if($paidKey_ucount*1<500) { 
			$cnt_user = onlyOneReturnSql("select COUNT(*) from mis_users where delchk<>'D'");
			$cnt_app = onlyOneReturnSql("select COUNT(*) from mis_menus where useflag=1 and menu_type='01' and idx>1312 and idx not in (select top 100 idx from mis_menus where useflag=1 and menu_type='01' and idx>=1312 and RealPid not like 'speedmis%' order by idx)");

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
        $('select#menu_type').data('kendoDropDownList').value('');
        $('select#MisJoinPid').data('kendoDropDownList').value('');
        $('div#round_MisJoinPid').css('display', 'none');
        
        $('select#menu_type').bind('change', function() {
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
        $default_dbalias = onlyOneReturnSql("select dbalias from mis_menus where idx=$idx");
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

    // ── v7 자동 생성 우회 — 테이블/뷰명 입력 시 깔끔한 새 흐름 ──
    if (_v7_isAutoGenMode()) {
        _v7_genFromTable_writeBefore();
        return;
    }

    global $full_siteID, $base_root, $RealPid, $MisJoinPid, $logicPid, $parent_idx, $MS_MJ_MY;
    global $key_aliasName, $key_value, $ActionFlag, $updateList, $sql_next, $MisSession_UserID, $db_name, $db_name2;
    global $externalDB, $base_db, $base_db2, $addDir, $saveList, $isnull;

    include '../_mis/PHPExcleReader/Classes/PHPExcel/IOFactory.php';
    include "../_mis/hangeul-utils-master/hangeul_romaja.php";
    


    $updateList['menu_name'] = Trim($updateList['menu_name']);
    
    if($MS_MJ_MY=='MY') {
        $sql = "select concat(AutoGubun,'.',menu_name) from mis_menus where RealPid='" . $updateList["RealPid"] . "'; ";
    } else {
        $sql = "select AutoGubun+'.'+menu_name from mis_menus where RealPid='" . $updateList["RealPid"] . "'";
    }

    $this_info = onlyOnereturnSql($sql);
    $this_MenuName = Trim(splitVB($this_info,'.')[1]);
    if($this_MenuName==$updateList['menu_name']) {
        echo '선택한 경로와 메뉴명이 같아 처리할 수 없습니다.';
        exit;
    }
    
    if($updateList['menu_name']=='') {
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
    if(Len($this_AutoGubun)==4 && $addPosition>=5 && $updateList['menu_type']=='00') {
        echo '선택한 경로에서는 하위메뉴를 메뉴표시용으로 추가할 수 없습니다.';
        exit;
    }

    //AutoGubun 길이 기준으로 허용 안되는 경우3 : 6 인데 같은 레벨이 메뉴표시용이면 거부
    if(Len($this_AutoGubun)==6 && $addPosition<=4 && $updateList['menu_type']=='00') {
        echo '선택한 경로에서는 메뉴표시용으로 추가할 수 없습니다.';
        exit;
    }

    //MIS JOIN 인데 그값이 없을 경우.
    if($updateList['menu_type']=='06' && $updateList['MisJoinPid']=='') {
        echo 'Mis Join 에 대한 메뉴명을 선택하세요!';
        exit;
    }
    $dataPullType = 0;
    $virtual_fieldQnsourceCopy = '';
    //업무용MIS 일때 택1만.
    if($updateList['menu_type']=='01') {
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

    if($updateList['menu_type']!='06' && $updateList['MisJoinPid']!='') {
        $updateList['MisJoinPid'] = '';
    }
    if($MS_MJ_MY=='MY') {
        $newRealPid = onlyOnereturnSql("select concat('$full_siteID', formatnums((SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_NAME = 'mis_menus' AND TABLE_SCHEMA='$base_db'), '000000'));");
        $newRealPid2 = onlyOnereturnSql("select concat('$full_siteID', formatnums((SELECT MAX(idx) FROM mis_menus)+1, '000000'));");
		if($newRealPid2>$newRealPid) {
			$newRealPid = $newRealPid2;
		}
    } else {
        $newRealPid = onlyOnereturnSql("select '$full_siteID' + dbo.formatnums(IDENT_CURRENT('mis_menus') + 1, '000000') ");
    }
    if($addPosition>=5) {
        $updateList["AutoGubun"] = $this_AutoGubun . "99";
        $updateList["sort_g2"] = Left($this_AutoGubun,2);
    
        if(Len($this_AutoGubun)==2) {
            if($addPosition==5) {
                $updateList["sort_g4"] = 0.1;
            } else {
                $updateList["sort_g4"] = 99;
            }
            $updateList["sort_g6"] = 0;
        } else {
            $updateList["sort_g4"] = Mid($this_AutoGubun,3,2);
            if($addPosition==5) {
                $updateList["sort_g6"] = 0.1;
            } else {
                $updateList["sort_g6"] = 99;
            }
        }
        $updateList["up_real_pid"] = $updateList["RealPid"];
    } else {

        //같은 레벨에 대한 추가.

        $updateList["AutoGubun"] = $this_AutoGubun;

        if(Len($this_AutoGubun)==2) {
            if($addPosition==1) {
                $updateList["sort_g2"] = 0.1;
            } else if($addPosition==2) {
                $updateList["sort_g2"] = Left($this_AutoGubun,2)*1 - 0.1;
            } else if($addPosition==3) {
                $updateList["sort_g2"] = Left($this_AutoGubun,2)*1 + 0.1;
            } else if($addPosition==4) {
                $updateList["sort_g2"] = 99;
            }
            $updateList["sort_g4"] = 0;
            $updateList["sort_g6"] = 0;
        } else if(Len($this_AutoGubun)==4) {
            $updateList["sort_g2"] = Left($this_AutoGubun,2);
            if($addPosition==1) {
                $updateList["sort_g4"] = 0.1;
            } else if($addPosition==2) {
                $updateList["sort_g4"] = Mid($this_AutoGubun,3,2)*1 - 0.1;
            } else if($addPosition==3) {
                $updateList["sort_g4"] = Mid($this_AutoGubun,3,2)*1 + 0.1;
            } else if($addPosition==4) {
                $updateList["sort_g4"] = 99;
            }
            $updateList["sort_g6"] = 0;
        } else {
            $updateList["sort_g2"] = Left($this_AutoGubun,2);
            $updateList["sort_g4"] = Mid($this_AutoGubun,3,2);
            if($addPosition==1) {
                $updateList["sort_g6"] = 0.1;
            } else if($addPosition==2) {
                $updateList["sort_g6"] = Right($this_AutoGubun,2)*1 - 0.1;
            } else if($addPosition==3) {
                $updateList["sort_g6"] = Right($this_AutoGubun,2)*1 + 0.1;
            } else if($addPosition==4) {
                $updateList["sort_g6"] = 99;
            }
        }
    }
    if($updateList["sort_g6"].''=='0') {
        $updateList["sort_g6"] = '0';
    }

    if($dataPullType==1) {
        if($MS_MJ_MY=='MY') {

            $sql_next =  "

            set @wdater := '$MisSession_UserID';
            set @full_siteID := '$full_siteID';
            set @realPid := '$virtual_fieldQnsourceCopy';
            set @newRealPid := '$newRealPid';

            update mis_menus a inner join mis_menus b on b.RealPid=@realPid
            SET a.g01=b.g01, a.g02=b.g02, a.g03=b.g03, a.g04=b.g04, a.g05=b.g05, a.g06=b.g06, a.g07=b.g07, a.g08=b.g08, a.g09=b.g09, 
          a.g10=b.g10, a.g11=b.g11, a.g12=b.g12, a.g14=b.g14, a.dbalias=b.dbalias, a.add_logic=b.add_logic, 
          a.isUsePrint=b.isUsePrint, a.isUseForm=b.isUseForm, a.addLogic_print=b.addLogic_print, a.LanguageCode=b.LanguageCode
          where a.RealPid=@newRealPid;

        insert into mis_menu_fields (RealPid, sort_order, db_field, db_table, alias_name, Grid_View_Fixed, Grid_Enter, Grid_View_XS, Grid_View_SM, Grid_View_MD, Grid_View_LG, Grid_View_Hight, Grid_View_Class, col_title, col_width, schema_type
        , items, schema_validation
        , Grid_Align, Grid_Orderby, max_length, Grid_Templete, default_value, group_compute, Grid_CtlName, Grid_IsHandle, Grid_ListEdit, prime_key, Grid_Alim, required, form_group, wdater)
        SELECT @newRealPid, sort_order, db_field, db_table, alias_name, Grid_View_Fixed, Grid_Enter, Grid_View_XS, Grid_View_SM, Grid_View_MD, Grid_View_LG, Grid_View_Hight, Grid_View_Class, col_title, col_width, schema_type
        , items, schema_validation
        , Grid_Align, Grid_Orderby, max_length, Grid_Templete, default_value, group_compute, Grid_CtlName, Grid_IsHandle, Grid_ListEdit, prime_key, Grid_Alim, required, form_group, @wdater
          FROM mis_menu_fields where RealPid=@realPid order by sort_order, idx;
          ";
          
        } else {

            $sql_next =  "

        declare @full_siteID nvarchar(8)
        declare @realPid nvarchar(20)
        declare @newRealPid nvarchar(20)
        declare @wdater nvarchar(50)
        set @wdater = '$MisSession_UserID'
        set @full_siteID = '$full_siteID'
        set @realPid='$virtual_fieldQnsourceCopy'
        set @newRealPid = '$newRealPid'

        update mis_menus set g01=b.g01, g02=b.g02, g03=b.g03, g04=b.g04, g05=b.g05, g06=b.g06, g07=b.g07, g08=b.g08, g09=b.g09, 
        g10=b.g10, g11=b.g11, g12=b.g12, g14=b.g14, dbalias=b.dbalias, add_logic=b.add_logic, 
        isUsePrint=b.isUsePrint, isUseForm=b.isUseForm, addLogic_print=b.addLogic_print, LanguageCode=b.LanguageCode
        from mis_menus
        join mis_menus b on b.RealPid=@realPid
        where MisMenuList.RealPid=@newRealPid

        insert into mis_menu_fields (RealPid, sort_order, db_field, db_table, alias_name, Grid_View_Fixed, Grid_Enter, Grid_View_XS, Grid_View_SM, Grid_View_MD, Grid_View_LG, Grid_View_Hight, Grid_View_Class, col_title, col_width, schema_type
        , items, schema_validation
        , Grid_Align, Grid_Orderby, max_length, Grid_Templete, default_value, group_compute, Grid_CtlName, Grid_IsHandle, Grid_ListEdit, prime_key, Grid_Alim, required, form_group, wdater)
        SELECT @newRealPid, sort_order, db_field, db_table, alias_name, Grid_View_Fixed, Grid_Enter, Grid_View_XS, Grid_View_SM, Grid_View_MD, Grid_View_LG, Grid_View_Hight, Grid_View_Class, col_title, col_width, schema_type
        , items, schema_validation
        , Grid_Align, Grid_Orderby, max_length, Grid_Templete, default_value, group_compute, Grid_CtlName, Grid_IsHandle, Grid_ListEdit, prime_key, Grid_Alim, required, form_group, @wdater
          FROM mis_menu_fields where RealPid=@realPid order by sort_order, idx
          ";

        }
        
          $nowRealPid = $virtual_fieldQnsourceCopy;
          $destination = $base_root . "/_mis_addLogic/" . $nowRealPid . ".php";
          $newDestination = $base_root . "/_mis_addLogic/" . $newRealPid . ".php";
          $add_logic = onlyOnereturnSql("select $isnull(add_logic,'') from mis_menus where RealPid=N'" .  $newRealPid . "'");

          $destination_print = $base_root . "/_mis_addLogic/" . $nowRealPid . "_print.html";
          $newDestination_print = $base_root . "/_mis_addLogic/" . $newRealPid . "_print.html";
          $addLogic_print = onlyOnereturnSql("select $isnull(addLogic_print,'') from mis_menus where RealPid=N'" .  $newRealPid . "'");

          if (file_exists($newDestination)) unlink($newDestination);
          if (file_exists($newDestination_print)) unlink($newDestination_print);
          //echo '$destination=' . $destination;
          //echo '$newDestination=' . $newDestination;
          //exit;

          if(file_exists($destination)) {
            copy($destination, $newDestination);
          } else if($add_logic!="") {
            $add_logic = replace($add_logic, '@_' . 'q;', '?');
            WriteTextFile($newDestination, $add_logic);
          }
          if(file_exists($destination_print)) {
            copy($destination_print, $newDestination_print);
          } else if($addLogic_print!="") {
              WriteTextFile($newDestination_print, $addLogic_print);
          }
    } else if($dataPullType==2 || $dataPullType==3) {
        //테이블 기준 프로그램 생성
        $updateList['g01'] = 'simple_list';
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
        delete from mis_menu_fields where RealPid='$newRealPid';
        update mis_menus set g08='$full_g08', dbalias=N'" . $updateList['dbalias'] . "' where RealPid='$newRealPid';
        insert into mis_menu_fields (RealPid, sort_order, db_field, db_table, 
        alias_name, col_title, col_width, wdater 
        ,schema_type)
        ";

        if($updateList['dbalias']=='default') $updateList['dbalias'] = '';
        if($updateList['dbalias']!='') {
            connectDB_dbalias($updateList['dbalias']);
            if(Left($externalDB[$updateList['dbalias']],2)=='MY') {
                $sql = "
                select '$newRealPid' as \"RealPid\", ORDINAL_POSITION as \"sort_order\", COLUMN_NAME as \"db_field\", 'table_m' as \"db_table\", 
                COLUMN_NAME as \"alias_name\", COLUMN_NAME as \"col_title\", 10 as \"col_width\", '$MisSession_UserID' as \"wdater\"
                ,case when ORDINAL_POSITION=1 then '' when left(COLUMN_TYPE,3)='int' then 'number' when COLUMN_TYPE='date' then 'date' when COLUMN_TYPE='datetime' then 'datetime' else '' end as \"schema_type\"
                from INFORMATION_SCHEMA.COLUMNS where TABLE_NAME='" . $updateList['g08'] . "' and COLUMN_NAME not like '%:%' and column_name not like '%-%' and column_name not like '%=%' and TABLE_SCHEMA='$base_db2' order by ORDINAL_POSITION
                ";
                $data = allreturnSql_gate($sql, $updateList['dbalias']);
                //print_r($sql);exit;
                $sql_next = $sql_next . ' values ';

                $cnt_data = count($data);
                
                for($k=0;$k<$cnt_data;$k++) {
                
					$r_RealPid = $data[$k]['RealPid'];
                    $r_SortElement = $data[$k]['sort_order'];
                    $r_Grid_Select_Field = $data[$k]['db_field'];
                    $r_Grid_Select_Tname = $data[$k]['db_table'];
                    $r_aliasName = $data[$k]['alias_name'];
                    $r_Grid_Columns_Title = $data[$k]['col_title'];
                    $r_Grid_Columns_Width = $data[$k]['col_width'];
                    $r_wdater = $data[$k]['wdater'];
                    $r_Grid_Schema_Type = $data[$k]['schema_type'];
                    $sql_next = $sql_next . iif($k>0,",","") . "
                    ('$r_RealPid', '$r_SortElement', '$r_Grid_Select_Field', '$r_Grid_Select_Tname', '$r_aliasName', '$r_Grid_Columns_Title', '$r_Grid_Columns_Width', '$r_wdater', '$r_Grid_Schema_Type')";
                }
                $sql_next = $sql_next . ';';

            } else if(Left($externalDB[$updateList['dbalias']],2)=='OC') {
                $sql = "
                select '$newRealPid' as \"RealPid\", column_id as \"sort_order\", column_name as \"db_field\", 'table_m' as \"db_table\", 
                column_name as \"alias_name\", column_name as \"col_title\", 10 as \"col_width\", '$MisSession_UserID' as \"wdater\"
                ,case when column_id=1 then '' when data_type='NUMBER' then 'number' when data_type='DATE' then 'date' else '' end as \"schema_type\"
                from user_tab_cols where table_name='" . $updateList['g08'] . "' and column_name not like '%:%' and column_name not like '%-%' and column_name not like '%=%' and column_id > 0 order by column_id
                ";

                $data = allreturnSql_gate($sql, $updateList['dbalias']);
                $sql_next = $sql_next . ' values ';

                $cnt_data = count($data);
                for($k=0;$k<$cnt_data;$k++) {
                
					$r_RealPid = $data[$k]['RealPid'];
                    $r_SortElement = $data[$k]['sort_order'];
                    $r_Grid_Select_Field = $data[$k]['db_field'];
                    $r_Grid_Select_Tname = $data[$k]['db_table'];
                    $r_aliasName = $data[$k]['alias_name'];
                    $r_Grid_Columns_Title = $data[$k]['col_title'];
                    $r_Grid_Columns_Width = $data[$k]['col_width'];
                    $r_wdater = $data[$k]['wdater'];
                    $r_Grid_Schema_Type = $data[$k]['schema_type'];
                    $sql_next = $sql_next . iif($k>0,",","") . "
                    ('$r_RealPid', '$r_SortElement', '$r_Grid_Select_Field', '$r_Grid_Select_Tname', '$r_aliasName', '$r_Grid_Columns_Title', '$r_Grid_Columns_Width', '$r_wdater', '$r_Grid_Schema_Type')";
                }
                $sql_next = $sql_next . ';';
				
            } else {
                $sql = "
                select '$newRealPid' as 'RealPid', colorder as 'sort_order', name as 'db_field', 'table_m' as 'db_table', 
                name as 'alias_name', name as 'col_title', 10 as 'col_width', '$MisSession_UserID' as 'wdater'
                ,case when colorder=1 then '' when xtype=62 or xtype=56 then 'number' when xtype=104 then 'boolean' when xtype=61 then 'date' else '' end as 'schema_type'
                from " . $this_db_name . ".syscolumns where id=(select id from " . $this_db_name . ".sysobjects where name='" . $updateList['g08'] . "' and (type='U' or type='V')) 
                and xtype<>165 and name not like '%:%' order by colorder
                ";
                $data = allreturnSql_gate($sql, $updateList['dbalias']);
                $sql_next = $sql_next . ' values ';
                $cnt_data = count($data);
                for($k=0;$k<$cnt_data;$k++) {
                    $r_RealPid = $data[$k]['RealPid'];
                    $r_SortElement = $data[$k]['sort_order'];
                    $r_Grid_Select_Field = $data[$k]['db_field'];
                    $r_Grid_Select_Tname = $data[$k]['db_table'];
                    $r_aliasName = $data[$k]['alias_name'];
                    $r_Grid_Columns_Title = $data[$k]['col_title'];
                    $r_Grid_Columns_Width = $data[$k]['col_width'];
                    $r_wdater = $data[$k]['wdater'];
                    $r_Grid_Schema_Type = $data[$k]['schema_type'];
                    $sql_next = $sql_next . iif($k>0,",","") . "
                    ('$r_RealPid', '$r_SortElement', '$r_Grid_Select_Field', '$r_Grid_Select_Tname', '$r_aliasName', '$r_Grid_Columns_Title', '$r_Grid_Columns_Width', '$r_wdater', '$r_Grid_Schema_Type')";
                }
                $sql_next = $sql_next . ';';
            }
        } else {
            $sql_next = $sql_next . "
            select '$newRealPid' as 'RealPid', colorder as 'sort_order', name as 'db_field', 'table_m' as 'db_table', 
            name as 'alias_name', name as 'col_title', 10 as 'col_width', '$MisSession_UserID' as 'wdater'
            ,case when colorder=1 then '' when xtype=62 or xtype=56 then 'number' when xtype=104 then 'boolean' when xtype=61 then 'date' else '' end as 'schema_type'
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
            execSql("delete from mis_menus where idx=$newIdx");
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
                        $sql = $sql . ",N'" . $MisSession_UserID . "', $newRealPid" . "_SEQ.NEXTVAL);
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
                        $sql = $sql . ",N'" . $MisSession_UserID . "');
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
                    $sql = $sql . ",N'" . $MisSession_UserID . "');
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
        delete from mis_menu_fields where RealPid='$newRealPid';
        update mis_menus set g08='$newRealPid', dbalias=N'" . $updateList['dbalias'] . "' where RealPid='$newRealPid';
        insert into mis_menu_fields (RealPid, sort_order, db_field, db_table, 
        alias_name, col_title, col_width, wdater 
        ,schema_type)
        ";
        //echo $base_db2;exit;
        if($newDbalias!='') {
            if(Left($externalDB[$newDbalias],2)=='MY') {
                $tempSql = "
                select '$newRealPid' as 'RealPid', ORDINAL_POSITION as 'sort_order', COLUMN_NAME as 'db_field', 'table_m' as 'db_table', 
                COLUMN_NAME as 'alias_name', COLUMN_NAME as 'col_title', 10 as 'col_width', '$MisSession_UserID' as 'wdater'
                ,case when ORDINAL_POSITION=1 then '' when left(COLUMN_TYPE,3)='int' then 'number' when COLUMN_TYPE='date' then 'date' when COLUMN_TYPE='datetime' then 'datetime' else '' end as 'schema_type'
                from INFORMATION_SCHEMA.COLUMNS where TABLE_NAME='$newRealPid' and TABLE_SCHEMA='$base_db2'
                order by ORDINAL_POSITION;
                ";
                

            } else if(Left($externalDB[$newDbalias],2)=='OC') {
                $tempSql = "
                select '$newRealPid' as \"RealPid\", column_id as \"sort_order\", column_name as \"db_field\", 'table_m' as \"db_table\", 
                column_name as \"alias_name\", column_name as \"col_title\", 10 as \"col_width\", '$MisSession_UserID' as \"wdater\"
                ,case when column_id=1 then '' when data_type='NUMBER' then 'number' when data_type='DATE' then 'date' else '' end as \"schema_type\"
                from user_tab_cols where TABLE_NAME='$newRealPid' and column_id > 0 order by column_id
                ";
            } else {
                $tempSql = "
                select '$newRealPid' as 'RealPid', ROW_NUMBER() over (order by case when colorder between 2 and 8 then colorder+80 else colorder end) as 'sort_order', name as 'db_field', 'table_m' as 'db_table', 
                name as 'alias_name', name as 'col_title', 10 as 'col_width', '$MisSession_UserID' as 'wdater'
                ,case when colorder=1 then '' when xtype=62 or xtype=56 then 'number' when xtype=104 then 'boolean' when xtype=61 then 'date' else '' end as 'schema_type'
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
                $r_SortElement = $data[$k]['sort_order'];
                $r_Grid_Select_Field = $data[$k]['db_field'];
                $r_Grid_Select_Tname = $data[$k]['db_table'];
                $r_aliasName = $data[$k]['alias_name'];
                $r_Grid_Columns_Title = replace($data[$k]['col_title'],'numQ','');
                $r_Grid_Columns_Width = $data[$k]['col_width'];
                $r_wdater = $data[$k]['wdater'];
                $r_Grid_Schema_Type = $data[$k]['schema_type'];
                $sql_next = $sql_next . iif($k>0,",","") . "
                ('$r_RealPid', '$r_SortElement', '$r_Grid_Select_Field', '$r_Grid_Select_Tname', '$r_aliasName', '$r_Grid_Columns_Title', '$r_Grid_Columns_Width', '$r_wdater', '$r_Grid_Schema_Type')";
            }
            $sql_next = $sql_next . ';';
        } else {
            $sql_next = $sql_next . "
            select '$newRealPid' as 'RealPid', ROW_NUMBER() over (order by case when colorder between 2 and 8 then colorder+80 else colorder end) as 'sort_order', name as 'db_field', 'table_m' as 'db_table', 
            name as 'alias_name', replace(name,'numQ','') as 'col_title', 10 as 'col_width', '$MisSession_UserID' as 'wdater'
            ,case when colorder=1 then '' when xtype=62 or xtype=56 then 'number' when xtype=104 then 'boolean' when xtype=61 then 'date' else '' end as 'schema_type'
            from " . $db_name . ".syscolumns where id=(select id from " . $db_name . ".sysobjects where name='$newRealPid' and (type='U' or type='V')) 
            and xtype<>165
            order by case when colorder between 2 and 8 then colorder+80 else colorder end
            ";

        }
        $sql_next = $sql_next . "
            update mis_menu_fields set Grid_Orderby='1a' where sort_order=1 and RealPid='$newRealPid'; 
        ";
       

        //---------------- MisMenuList_Detail 생성 끝------------


        //엑셀 업로드
        $updateList['g01'] = 'simple_list';
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
                    $sql = $sql . ",N'" . $MisSession_UserID . "', $newRealPid" . "_SEQ.NEXTVAL);
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
                    $sql = $sql . ",N'" . $MisSession_UserID . "');
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
                $sql = $sql . ",N'" . $MisSession_UserID . "');
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
        delete from mis_menu_fields where RealPid='$newRealPid';
        update mis_menus set g08='$newRealPid' where RealPid='$newRealPid';
        insert into mis_menu_fields (RealPid, sort_order, db_field, db_table, 
        alias_name, col_title, col_width, wdater 
        ,schema_type)
        ";
        if($newDbalias!='') {
            if(Left($externalDB[$newDbalias],2)=='MY') {
                $tempSql = "
                select '$newRealPid' as 'RealPid', ORDINAL_POSITION as 'sort_order', COLUMN_NAME as 'db_field', 'table_m' as 'db_table', 
                COLUMN_NAME as 'alias_name', COLUMN_NAME as 'col_title', 10 as 'col_width', '$MisSession_UserID' as 'wdater'
                ,case when ORDINAL_POSITION=1 then '' when left(COLUMN_TYPE,3)='int' then 'number' when COLUMN_TYPE='date' then 'date' when COLUMN_TYPE='datetime' then 'datetime' else '' end as 'schema_type'
                from INFORMATION_SCHEMA.COLUMNS where TABLE_NAME='$newRealPid'  and TABLE_SCHEMA='$base_db2'
                order by ORDINAL_POSITION;
                ";

            } else if(Left($externalDB[$newDbalias],2)=='OC') {
                $tempSql = "
                select '$newRealPid' as \"RealPid\", column_id as \"sort_order\", column_name as \"db_field\", 'table_m' as \"db_table\", 
                column_name as \"alias_name\", column_name as \"col_title\", 10 as \"col_width\", '$MisSession_UserID' as \"wdater\"
                ,case when column_id=1 then '' when data_type='NUMBER' then 'number' when data_type='DATE' then 'date' else '' end as \"schema_type\"
                from user_tab_cols where TABLE_NAME='$newRealPid' and column_id > 0 order by column_id
                ";
            } else {
                $tempSql = "
                select '$newRealPid' as 'RealPid', ROW_NUMBER() over (order by case when colorder between 2 and 8 then colorder+80 else colorder end) as 'sort_order', name as 'db_field', 'table_m' as 'db_table', 
                name as 'alias_name', name as 'col_title', 10 as 'col_width', '$MisSession_UserID' as 'wdater'
                ,case when colorder=1 then '' when xtype=62 or xtype=56 then 'number' when xtype=104 then 'boolean' when xtype=61 then 'date' else '' end as 'schema_type'
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
                $r_SortElement = $data[$k]['sort_order'];
                $r_Grid_Select_Field = $data[$k]['db_field'];
                $r_Grid_Select_Tname = $data[$k]['db_table'];
                $r_aliasName = $data[$k]['alias_name'];
                $r_Grid_Columns_Title = $data[$k]['col_title'];
                $r_Grid_Columns_Width = $data[$k]['col_width'];
                $r_wdater = $data[$k]['wdater'];
                $r_Grid_Schema_Type = $data[$k]['schema_type'];
                $sql_next = $sql_next . iif($k>0,",","") . "
                ('$r_RealPid', '$r_SortElement', '$r_Grid_Select_Field', '$r_Grid_Select_Tname', '$r_aliasName', '$r_Grid_Columns_Title', '$r_Grid_Columns_Width', '$r_wdater', '$r_Grid_Schema_Type')";
            }
            $sql_next = $sql_next . ';';
        } else {
            $sql_next = $sql_next . "
            select '$newRealPid' as 'RealPid', ROW_NUMBER() over (order by case when colorder between 2 and 8 then colorder+80 else colorder end) as 'sort_order', name as 'db_field', 'table_m' as 'db_table', 
            name as 'alias_name', name as 'col_title', 10 as 'col_width', '$MisSession_UserID' as 'wdater'
            ,case when colorder=1 then '' when xtype=62 or xtype=56 then 'number' when xtype=104 then 'boolean' when xtype=61 then 'date' else '' end as 'schema_type'
            from " . $db_name . ".syscolumns where id=(select id from " . $db_name . ".sysobjects where name='$newRealPid' and (type='U' or type='V')) 
            and xtype<>165
            order by case when colorder between 2 and 8 then colorder+80 else colorder end
            ";

        }
        $sql_next = $sql_next . "
            update mis_menu_fields set Grid_Orderby='1a' where sort_order=1 and RealPid='$newRealPid'; 
        ";

        //---------------- MisMenuList_Detail 생성 끝------------


        //엑셀 업로드
        $updateList['g01'] = 'simple_list';
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

    global $newIdx;

    // ── v7 자동 생성 우회 — _v7_autogen marker 가 있으면 fields 자동 생성 ──
    if (!empty($GLOBALS['_v7_autogen'])) {
        _v7_genFromTable_writeAfter((int)$newIdx);
        return;
    }

    global $base_root, $RealPid, $MisJoinPid, $logicPid, $parent_idx, $full_siteID, $db_name;
    global $key_aliasName, $key_value, $saveList, $saveUploadList, $viewList, $deleteList;
    global $default_value, $ActionFlag, $MisSession_UserID;
    global $afterScript, $externalDB, $MS_MJ_MY, $full_site;

 
    
    $data = allreturnSql("select RealPid, dbalias from mis_menus where idx=$newIdx");
    $newRealPid = $data[0]['RealPid'];

    if($MS_MJ_MY=='MY') {
        $sql = "
        update mis_menus set g10='111=111' where RealPid='$newRealPid'
        and not exists(select * from mis_menu_fields where RealPid='$newRealPid' and db_field='useflag');
        
        update mis_menu_fields set col_width=-1 where  RealPid='$newRealPid' and db_field='useflag';
        update mis_menu_fields set col_width=0 where  RealPid='$newRealPid' and db_field in ('hit','IP'); 
        update mis_menu_fields set max_length=8 where  RealPid='$newRealPid' and sort_order=1;
        update mis_menu_fields set col_width=0, form_group='등록정보'
        where  RealPid='$newRealPid' and db_field in ('wdate','wdater','lastupdate','lastupdater');
        call MisUser_Authority_Proc ('$full_siteID','speedmis000001');
        update mis_users set menuRefresh = '' where uniquenum=N'$MisSession_UserID';
        ";
    } else {
        $sql = "
        if not exists(select * from mis_menu_fields where RealPid='$newRealPid' and db_field='useflag')
        update mis_menus set g10='111=111' where RealPid='$newRealPid'
        
        update mis_menu_fields set col_width=-1 where  RealPid='$newRealPid' and db_field='useflag' 
        update mis_menu_fields set col_width=0 where  RealPid='$newRealPid' and db_field in ('hit','IP') 
        update mis_menu_fields set max_length=8 where  RealPid='$newRealPid' and sort_order=1
        update mis_menu_fields set col_width=0, form_group='등록정보' 
        where  RealPid='$newRealPid' and db_field in ('wdate','wdater','lastupdate','lastupdater')
        exec MisUser_Authority_Proc '$full_siteID','speedmis000001'
        update mis_users set menuRefresh = '' where uniquenum=N'$MisSession_UserID'
        ";
    }


    execSql($sql);
    aliasN_update_RealPid($newRealPid);
    setcookie("newLogin", "Y", 0, "/");

	$url = "$full_site/_mis/list_json.php?flag=readResult&RealPid=speedmis000267&app=자동정렬&parent_idx=$newRealPid";
	file_get_contents_new($url);

}
//end save_writeAfter



function add_logic_treat() {

    global $MisSession_UserID;
    
    //add_logic_treat 함수는 ajax 로 요청되어진(url 형식) 것에 대한 출력문입니다. echo 등으로 출력내용만 표시하면 됩니다.
    //아래는 url 에 동반된 파라메터의 예입니다.
    //해당 예제 TIP 의 기본폼에 보면 add_logic_treat 를 호출하는 코딩이 있습니다.

    $question = requestVB("question");
    $pidx = requestVB("pidx");

    //아래는 값에 따라 mysql 서버를 통해 알맞는 값을 출력하여 보냅니다.
    if($question=="depth3") {
        $sql = " select menu_name from mis_menus where AutoGubun=(select left(AutoGubun,2) from mis_menus where idx=$pidx) ";
        gzecho(onlyOnereturnSql($sql));
    }

}
//end add_logic_treat


// =============================================================================
// v7 자동 생성: 테이블/뷰 이름 입력 → MIS 프로그램 자동 완성
// =============================================================================

/**
 * 자동 생성 모드 판정 — menu_type=01 + g08/g08b 입력 + 복제 아닌 경우
 */
function _v7_isAutoGenMode(): bool {
    global $updateList, $saveList;
    if (($updateList['menu_type'] ?? '') !== '01') return false;
    $sourceCopy = trim((string)($saveList['virtual_fieldQnsource_copy'] ?? ''));
    if ($sourceCopy !== '') return false;  // 복제 모드 우선
    $g08  = trim((string)($updateList['g08']  ?? ''));
    $g08b = trim((string)($updateList['g08b'] ?? ''));
    return ($g08 !== '' || $g08b !== '');
}

/**
 * INSERT 직전 — $updateList 에 v7 호환 기본값 채움
 * (개발자 전용권한 + 읽기전용 + simple_list)
 */
function _v7_genFromTable_writeBefore(): void {
    global $updateList, $saveList, $__pdo, $misSessionUserId;

    // 입력값
    $tableInput = trim((string)($updateList['g08'] ?? ''));
    $tableB     = trim((string)($updateList['g08b'] ?? ''));
    if ($tableInput === '') $tableInput = $tableB;

    // schema.table 형태 분리
    $schema = '';
    $tableName = $tableInput;
    if (strpos($tableInput, '.') !== false) {
        [$schema, $tableName] = explode('.', $tableInput, 2);
    }
    $checkSchema = $schema !== '' ? $schema : ($_ENV['DB_NAME'] ?? 'speedmis_v7');

    // 테이블 존재 여부
    $st = $__pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?");
    $st->execute([$checkSchema, $tableName]);
    if ((int)$st->fetchColumn() === 0) {
        echo "테이블/뷰 '{$checkSchema}.{$tableName}' 이 존재하지 않습니다.";
        exit;
    }

    // 부모 메뉴 (사용자가 선택한 RealPid)
    $upRealPid = trim((string)($updateList['real_pid'] ?? ''));
    if ($upRealPid === '') {
        echo '상위 메뉴 (real_pid) 가 선택되지 않았습니다.';
        exit;
    }
    $st = $__pdo->prepare("SELECT auto_gubun, depth FROM mis_menus WHERE real_pid = ? LIMIT 1");
    $st->execute([$upRealPid]);
    $parent = $st->fetch(\PDO::FETCH_ASSOC);
    if (!$parent) {
        echo "상위 메뉴를 찾을 수 없습니다: {$upRealPid}";
        exit;
    }
    $parentAg    = (string)$parent['auto_gubun'];
    $parentDepth = (int)$parent['depth'];

    // 추가 위치 (1~4 같은 레벨, 5~6 하위)
    $addPosRaw = (string)($saveList['virtual_fieldQnchugawichi'] ?? $updateList['추가위치'] ?? '5');
    $addPos    = (int)substr($addPosRaw, 0, 1);
    if ($addPos < 1 || $addPos > 6) $addPos = 5;

    if ($addPos >= 5) {
        // 하위 — auto_gubun 길이 +2
        $childLen = strlen($parentAg) + 2;
        if ($childLen > 6) {
            echo '더 깊은 하위 메뉴를 만들 수 없습니다 (최대 depth 3).';
            exit;
        }
        $st = $__pdo->prepare("
            SELECT COALESCE(MAX(CAST(SUBSTRING(auto_gubun, ?, 2) AS UNSIGNED)), 0)
              FROM mis_menus
             WHERE auto_gubun LIKE CONCAT(?, '__')
               AND CHAR_LENGTH(auto_gubun) = ?
        ");
        $st->execute([strlen($parentAg) + 1, $parentAg, $childLen]);
        $maxSuf = (int)$st->fetchColumn();
        $newAg  = $parentAg . str_pad((string)($maxSuf + 1), 2, '0', STR_PAD_LEFT);
        $newDepth = $parentDepth + 1;
    } else {
        // 같은 레벨 — 같은 prefix 형제 중 max suffix +1
        $newDepth = $parentDepth;
        $prefix   = strlen($parentAg) >= 2 ? substr($parentAg, 0, -2) : '';
        $st = $__pdo->prepare("
            SELECT COALESCE(MAX(CAST(SUBSTRING(auto_gubun, ?, 2) AS UNSIGNED)), 0)
              FROM mis_menus
             WHERE CHAR_LENGTH(auto_gubun) = ?
               AND SUBSTRING(auto_gubun, 1, ?) = ?
        ");
        $st->execute([strlen($parentAg) - 1, strlen($parentAg), strlen($prefix), $prefix]);
        $maxSuf = (int)$st->fetchColumn();
        $newAg  = $prefix . str_pad((string)($maxSuf + 1), 2, '0', STR_PAD_LEFT);
    }

    // 새 real_pid (다음 idx)
    $nextIdx = (int)$__pdo->query("SELECT COALESCE(MAX(idx), 0) + 1 FROM mis_menus")->fetchColumn();
    $siteId  = $_ENV['SITE_ID'] ?? 'speedmis';
    $newRealPid = $siteId . str_pad((string)$nextIdx, 6, '0', STR_PAD_LEFT);

    // 작성자 ID
    $userId = (string)($misSessionUserId ?? $GLOBALS['MisSession_UserID'] ?? '');

    // ── $updateList 에 v7 컬럼명으로 채움 (v6 컬럼 제거) ──
    $cleaned = [
        'real_pid'    => $newRealPid,
        'menu_name'   => trim((string)($updateList['menu_name'] ?? '새 메뉴')),
        'menu_type'   => '01',
        'up_real_pid' => $upRealPid,
        'auto_gubun'  => $newAg,
        'depth'       => $newDepth,
        'use_yn'      => '1',
        'is_menu_hidden' => 'N',
        'gidx'        => 21,                  // 일반 그룹 (관습)
        'new_gidx'    => 83,                  // 개발자 전용
        'auth_code'   => '02',                // 개발자 권한
        'g01'         => 'simple_list',       // 단순 목록
        'g07'         => 'Y',                 // 읽기전용
        'table_name'  => $tableName,          // v7: 구 g08
        'wdate'       => date('Y-m-d H:i:s'),
        'wdater'      => $userId,
        'language_code' => 'ko',
        'sort_g2' => (float)substr($newAg, 0, 2),
        'sort_g4' => strlen($newAg) >= 4 ? (float)substr($newAg, 2, 2) : 0,
        'sort_g6' => strlen($newAg) >= 6 ? (float)substr($newAg, 4, 2) : 0,
    ];
    if (!empty($updateList['dbalias']) && $updateList['dbalias'] !== 'default') {
        $cleaned['dbalias'] = $updateList['dbalias'];
    }
    if (!empty($updateList['add_url'])) $cleaned['add_url'] = $updateList['add_url'];

    // 기존 $updateList 키 모두 제거 후 cleaned 로 대체 (v6 잔존 키 제거)
    foreach (array_keys($updateList) as $k) unset($updateList[$k]);
    foreach ($cleaned as $k => $v) $updateList[$k] = $v;

    // marker — save_writeAfter 에서 사용
    $GLOBALS['_v7_autogen'] = [
        'realPid' => $newRealPid,
        'table'   => $tableName,
        'schema'  => $checkSchema,
        'wdater'  => $userId,
    ];
}

/**
 * INSERT 후 — INFORMATION_SCHEMA 에서 컬럼 조회 → mis_menu_fields 자동 INSERT
 */
function _v7_genFromTable_writeAfter(int $newIdx): void {
    global $__pdo;
    if (empty($GLOBALS['_v7_autogen'])) return;
    $info = $GLOBALS['_v7_autogen'];

    // 컬럼 정보 조회
    $st = $__pdo->prepare("
        SELECT COLUMN_NAME, ORDINAL_POSITION, DATA_TYPE,
               CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE, COLUMN_DEFAULT,
               COLUMN_COMMENT, COLUMN_KEY
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
           AND COLUMN_NAME NOT LIKE '%:%'
           AND COLUMN_NAME NOT LIKE '%-%'
           AND COLUMN_NAME NOT LIKE '%=%'
         ORDER BY ORDINAL_POSITION
    ");
    $st->execute([$info['schema'], $info['table']]);
    $cols = $st->fetchAll(\PDO::FETCH_ASSOC);
    if (empty($cols)) return;

    // idempotent — 기존 fields 정리
    $__pdo->prepare("DELETE FROM mis_menu_fields WHERE real_pid = ?")->execute([$info['realPid']]);

    $ins = $__pdo->prepare("
        INSERT INTO mis_menu_fields
          (real_pid, sort_order, db_field, db_table, alias_name, col_title,
           col_width, schema_type, max_length, use_yn, wdate, wdater)
        VALUES
          (?, ?, ?, 'table_m', ?, ?, ?, ?, ?, '1', NOW(), ?)
    ");

    foreach ($cols as $i => $c) {
        $colName = $c['COLUMN_NAME'];
        $type    = strtolower($c['DATA_TYPE']);
        $maxLen  = $c['CHARACTER_MAXIMUM_LENGTH'] !== null ? (int)$c['CHARACTER_MAXIMUM_LENGTH'] : null;
        $title   = trim((string)$c['COLUMN_COMMENT']) !== '' ? $c['COLUMN_COMMENT'] : $colName;
        $isPK    = $c['COLUMN_KEY'] === 'PRI';

        // schema_type
        $schemaType = '';
        if      ($type === 'date')                                    $schemaType = 'date';
        elseif  (in_array($type, ['datetime', 'timestamp']))          $schemaType = 'datetime';
        elseif  (in_array($colName, ['useflag', 'use_yn']))           $schemaType = 'boolean';

        // col_width
        $colWidth = 12;
        if      ($isPK || in_array($colName, ['idx', 'num']))               $colWidth = -1;
        elseif  (in_array($colName, ['useflag','use_yn','HIT','hit','IP','ip'])) $colWidth = -1;
        elseif  (in_array($colName, ['wdate','lastupdate','last_update']))      $colWidth = 10;
        elseif  (in_array($colName, ['wdater','lastupdater','last_updater']))   $colWidth = 8;
        elseif  (in_array($type, ['text','longtext','mediumtext','json']))      $colWidth = 30;
        elseif  ($type === 'varchar' && $maxLen !== null && $maxLen >= 200)     $colWidth = 25;
        elseif  ($type === 'varchar')                                            $colWidth = 15;
        elseif  (in_array($type, ['int','bigint','smallint','tinyint','mediumint'])) $colWidth = 8;
        elseif  (in_array($type, ['decimal','float','double']))                 $colWidth = 10;

        $ins->execute([
            $info['realPid'],
            (int)$c['ORDINAL_POSITION'],
            $colName,
            $colName,
            $title,
            $colWidth,
            $schemaType,
            $maxLen,
            $info['wdater'],
        ]);
    }

    // 권한 재계산
    try { $__pdo->prepare("CALL mis_user_authority_proc(?)")->execute([$_ENV['SITE_ID'] ?? 'speedmis']); } catch (\Throwable) {}

    $GLOBALS['_client_toast'] = "[{$info['realPid']}] 메뉴 생성 완료 (" . count($cols) . " 컬럼)";
}

?>