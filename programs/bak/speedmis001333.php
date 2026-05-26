<?php

function misMenuList0_change() {
	
    global $data, $allFilter, $actionFlag, $real_pid, $list_numbering;

	//아래 주석을 풀면 분석에 도움이 됩니다.
	//print_r($data[0]); 

	$list_numbering = 'N';    //모든프로그램에 대해 리스트에서 번호가 보이게하려면 _mis_uniqueInfo/top_addLogic.php 파일에서 진행하세요.
								//해당프로그램에서 번호를 보이게 하려면 'Y' 숨기려면 'N' 을 넣으세요.

}
//end misMenuList0_change



function misMenuList_change() {
    
	//misMenuList 테이블에 의한 설정값인 $result 를 바꾸는게 이 함수의 핵심기능
    global $actionFlag, $gubun, $parent_idx, $real_pid, $logicPid, $result;
	global $misSessionPositionCode, $flag, $full_siteID;
    global $MS_MJ_MY, $addParam;

	//만약 $result 의 값이 궁금하면 아래 주석을 해제하고 새로고침 해볼 것(주의:에러발생).
	//print_r($result);
/*
    if($full_siteID!='speedmis' && $full_siteID!='speedmy' && (int)requestVB('$top')<20000) {
        $result[0]['g09'] = $result[0]['g09'] . " and table_m.RealPid not like 'speedmis%' and table_m.RealPid not like 'speedmy%'";
    }
*/

if($addParam=='pre') {
	$result[0]["g08"] = "MisMenuList_Detail_pre";																							
};


	//아래는 speedmis001333.php / speedmis000267.php 와 같음.
    $search_index = array_search("zgwonjangkeullaeseu", array_column($result, 'aliasName'));

    if($MS_MJ_MY=='MY') {
        $result[$search_index]["Grid_Select_Field"] = "
        CONCAT(case 
        when table_next.Grid_CtlName='dropdownlist' then
            case 
            when table_next.Grid_Columns_Width >= 50 or replace(table_next.Grid_MaxLength,'!','')*1 >= 50 then 'col-xs-12 col-sm-6 col-md-6 col-lg-6'
            else 'col-xs-6 col-sm-3 col-md-3 col-lg-3'
            end
        when table_m.Grid_CtlName='dropdownitem' then
            case 
            when table_m.Grid_Columns_Width >= 50 or replace(table_m.Grid_MaxLength,'!','')*1 >= 50 then 'col-xs-12 col-sm-6 col-md-6 col-lg-6'
            else 'col-xs-6 col-sm-3 col-md-3 col-lg-3'
            end
        when table_m.Grid_Columns_Width<>-1 and INSTR(table_m.Grid_Columns_Title,':')>0 then
            'col-xs-12 col-sm-12 col-md-12 col-lg-12'
            
        when table_m.Grid_Schema_Validation='code' then
            'col-xs-12 col-sm-12 col-md-12 col-lg-12'
        when table_m.Grid_CtlName='textarea' then
            'col-xs-12 col-sm-12 col-md-12 col-lg-6'
        when table_m.Grid_Select_Field='' and table_m.Grid_Templete='Y' then
            'col-xs-12 col-sm-12 col-md-6 col-lg-6'
            
        when table_m.Grid_CtlName='textdecrypt2' then
            'col-xs-12 col-sm-12 col-md-6 col-lg-4'
        when table_m.Grid_CtlName='text' and (table_m.Grid_Schema_Validation='zipcode' or table_prev.Grid_Schema_Validation='zipcode' or table_prev2.Grid_Schema_Validation='zipcode') then
            case 
            when table_m.Grid_Schema_Validation<>'zipcode' and table_prev.Grid_Schema_Validation='zipcode' then 'col-xs-12 col-sm-8 col-md-5 col-lg-5'
            when table_m.Grid_Schema_Validation='zipcode' then 'col-xs-12 col-sm-4 col-md-3 col-lg-3'
            else 'col-xs-12 col-sm-12 col-md-4 col-lg-4'
            end
        
        when left(table_m.Grid_CtlName,4)='text' then
            case 
            when table_m.Grid_Columns_Width >= 500 or replace(table_m.Grid_MaxLength,'!','') >= 500 then 'col-xs-12 col-sm-12 col-md-12 col-lg-12'
            when table_m.Grid_Columns_Width >= 200 or replace(table_m.Grid_MaxLength,'!','') >= 100 then 'col-xs-12 col-sm-12 col-md-12 col-lg-6'
            when table_m.Grid_Columns_Width >= 100 or replace(table_m.Grid_MaxLength,'!','') >= 100 or length(ifnull(table_m.Grid_Alim,''))>20 then 'col-xs-12 col-sm-12 col-md-6 col-lg-6'
            when table_m.Grid_Columns_Width >= 50 or replace(table_m.Grid_MaxLength,'!','')*1 >= 50 then 'col-xs-12 col-sm-6 col-md-3 col-lg-3'
            else 'col-xs-6 col-sm-6 col-md-3 col-lg-3'
            end
            
        else
            
            case when table_m.Grid_Columns_Width >= 100 or replace(table_m.Grid_MaxLength,'!','') >= 100 then 'col-xs-12 col-sm-12 col-md-12 col-lg-12'
            when table_m.Grid_CtlName='attach' or table_m.Grid_Columns_Width >= 50 or replace(table_m.Grid_MaxLength,'!','')*1 >= 50 then 'col-xs-12 col-sm-6 col-md-6 col-lg-6'
            else 'col-xs-6 col-sm-3 col-md-3 col-lg-3'
            end
            
        end 
        ,
        case 
        when table_m.Grid_CtlName='attach' or table_m.Grid_CtlName='dropdowntree' then ' row-54'
        when table_m.Grid_Schema_Validation='code' then ' row-0'
        when table_m.Grid_CtlName='textarea' then ' row-3'
        when table_m.Grid_CtlName='html' then ' row-60'
        else ' row-1'
        end 
        ) 
        ";

    } else {
        $result[$search_index]["Grid_Select_Field"] = "
        case 
        when table_next.Grid_CtlName='dropdownlist' then
            case 
            when table_next.Grid_Columns_Width >= 50 or replace(table_next.Grid_MaxLength,'!','')*1 >= 50 then 'col-xs-12 col-sm-6 col-md-6 col-lg-6'
            else 'col-xs-6 col-sm-3 col-md-3 col-lg-3'
            end
        when table_m.Grid_CtlName='dropdownitem' then
            case 
            when table_m.Grid_Columns_Width >= 50 or replace(table_m.Grid_MaxLength,'!','')*1 >= 50 then 'col-xs-12 col-sm-6 col-md-6 col-lg-6'
            else 'col-xs-6 col-sm-3 col-md-3 col-lg-3'
            end
        when table_m.Grid_Columns_Width<>-1 and dbo.INSTR(1,table_m.Grid_Columns_Title,':')>0 then
            'col-xs-12 col-sm-12 col-md-12 col-lg-12'
        when table_m.Grid_Schema_Validation='code' then
            'col-xs-12 col-sm-12 col-md-12 col-lg-12'
        when table_m.Grid_CtlName='textarea' then
            'col-xs-12 col-sm-12 col-md-12 col-lg-6'
        when table_m.Grid_Select_Field='' and table_m.Grid_Templete='Y' then
            'col-xs-12 col-sm-12 col-md-6 col-lg-6'
        when table_m.Grid_CtlName='textdecrypt2' then
            'col-xs-12 col-sm-12 col-md-6 col-lg-4'
        when table_m.Grid_CtlName='text' and (table_m.Grid_Schema_Validation='zipcode' or table_prev.Grid_Schema_Validation='zipcode' or table_prev2.Grid_Schema_Validation='zipcode') then
            case 
            when table_m.Grid_Schema_Validation<>'zipcode' and table_prev.Grid_Schema_Validation='zipcode' then 'col-xs-12 col-sm-8 col-md-5 col-lg-5'
            when table_m.Grid_Schema_Validation='zipcode' then 'col-xs-12 col-sm-4 col-md-3 col-lg-3'
            else 'col-xs-12 col-sm-12 col-md-4 col-lg-4'
            end
        when left(table_m.Grid_CtlName,4)='text' then
            case 
            when table_m.Grid_Columns_Width >= 500 or replace(table_m.Grid_MaxLength,'!','') >= 500 then 'col-xs-12 col-sm-12 col-md-12 col-lg-12'
            when table_m.Grid_Columns_Width >= 200 or replace(table_m.Grid_MaxLength,'!','') >= 100 then 'col-xs-12 col-sm-12 col-md-12 col-lg-6'
            when table_m.Grid_Columns_Width >= 100 or replace(table_m.Grid_MaxLength,'!','') >= 100 or len(isnull(table_m.Grid_Alim,''))>20 then 'col-xs-12 col-sm-12 col-md-6 col-lg-6'
            when table_m.Grid_Columns_Width >= 50 or replace(table_m.Grid_MaxLength,'!','')*1 >= 50 then 'col-xs-12 col-sm-6 col-md-3 col-lg-3'
            else 'col-xs-6 col-sm-6 col-md-3 col-lg-3'
            end
        else
            case when table_m.Grid_Columns_Width >= 100 or replace(table_m.Grid_MaxLength,'!','') >= 100 then 'col-xs-12 col-sm-12 col-md-12 col-lg-12'
            when table_m.Grid_CtlName='attach' or table_m.Grid_Columns_Width >= 50 or replace(table_m.Grid_MaxLength,'!','')*1 >= 50 then 'col-xs-12 col-sm-6 col-md-6 col-lg-6'
            else 'col-xs-6 col-sm-3 col-md-3 col-lg-3'
            end
        end 
        +
        case 
        when table_m.Grid_CtlName='attach' or table_m.Grid_CtlName='dropdowntree' then ' row-54'
        when table_m.Grid_Schema_Validation='code' then ' row-0'
        when table_m.Grid_CtlName='textarea' then ' row-3'
        when table_m.Grid_CtlName='html' then ' row-60'
        else ' row-1'
        end  
        ";
    }
    
	
}
//end misMenuList_change



function pageLoad() {

    global $actionFlag, $real_pid;
	global $misSessionIsAdmin;
    global $addParam;


//전체 적용 여부 체크 - 전체메뉴관리에 대해 class 설정이 전혀 없으면 일괄적용을 우선진행.
$sql = "select count(*) from MisMenuList_Detail where RealPid='speedmis000314' and Grid_View_Class like 'col%'";
if(onlyOnereturnSql($sql)*1==0 && requestVB('psize')!='20000') {
	execSql("update MisMenuList_Detail set Grid_MaxLength='' where Grid_MaxLength<>'' and ISNUMERIC(replace(replace(Grid_MaxLength,'!',''),' ',''))=0");
    re_direct('index.php?RealPid=speedmis001333&isAddURL=Y&psize=20000');
}

        ?>
<style>
    body[onlylist] td[comment="Y"] {
        font-weight: bold;
    }
    .union.toolbar_round_znaeyongnochuryeobu, a#btn_menuView {
        display: none;
    }
	span[aria-owns="toolbar_zpeurogeuraemseontaek_listbox"] {
		min-width: 200px!important;
	}
</style>
        <script>
			
<?php if($addParam=='pre' && $actionFlag=='list') { ?>
$('title')[0].innerText = 'TEST개발중:'+$('title')[0].innerText;	
<?php } ?>
			
if(getUrlParameter('psize')=='20000') {
    toastr.info("","<h3>전체 프로그램에 대한 내용디자인을 적용중입니다.</h3>", {progressBar: true, timeOut: 9000, closeButton: false, positionClass: "toast-center-center"});
    setTimeout( function() {
       if(InStr(getCookie('now_url'),'&allFilter')>0) location.href = getCookie('now_url');
       else location.href = 'index.php?RealPid='+$('input#RealPid')[0].value;
    },4000);
} else if(getUrlParameter('isMenuIn')=='Y') {
    //alert('뷰 디자이너는 상단 및 좌측메뉴를 포함해서는 안됩니다.');
    location.href = replaceAll(replaceAll(location.href,'&isMenuIn=Y',''),'?isMenuIn=Y&','?');
}

$('body').attr('onlylist','');	
//웹소스 디테일에서 템플릿으로 체크한 항목에 대해 출력내용을 변경할 수 있습니다. 이때 목록 또는 본문내용에 동일하게 적용됩니다.
function columns_templete(p_dataItem, p_aliasName) {

    if(p_aliasName=="table_RealPidQnidx") {
		var rValue = "<a href='index.php?gubun=" + p_dataItem["table_RealPidQnidx"] + "&isMenuIn=Y' target='_blank' class='k-button'>Go</a>";
		rValue = rValue + "<a id='aid_" + p_dataItem["table_RealPidQnidx"] + "' href='index.php?RealPid=speedmis000266&idx=" 
			+ p_dataItem["table_RealPidQnRealPid"] + "&isMenuIn=Y' target='_blank' class='k-button'>소스</a>";
		rValue = rValue + p_dataItem["table_RealPidQnidx"];
        return rValue;
    } else {
        return p_dataItem[p_aliasName];
    }

}

//아래의 함수는 목록에서만 해당되며, 템플릿으로 정의하지 않아도 특정항목의 값이나 태그를 추가할 수 있습니다. 
function rowFunction_UserDefine(p_this) {
/*
	p_this.MenuName = p_this.depth + p_this.LanguageCode; 
    p_this.AutoGubun = 
    "<a href=index.asp?gubun=" + p_this.idx + "&isMenuIn=Y target=_blank>[Go]</a> <a id='aid_" + p_this.idx + "' href=index.asp?RealPid=speedmis000266&idx=" + p_this.idx + "&isMenuIn=Y target=_blank>[Source]</a>?" 
    + p_this.AutoGubun;
*/
}

<?php 
//아래는 해당프로그램의 관리자 권한을 가진 사용자가 목록 조회 시에 적용되는 내용입니다.			
if($misSessionIsAdmin=="Y" && $actionFlag=="list") { 
?>

//툴바 명령버튼에 btn_1 이라고 하는 예비버튼을 "적용" 이라는 기능을 넣고, 클릭 시, 그리드에 app="적용" 이라는 신호로 보냅니다.
//이때 app=="적용" 에 대한 처리는 list_json_init() 를 참조하세요.
function thisLogic_toolbar() {

	if(InStr(getUrlParameter('allFilter'), '"field":"toolbar_zpeurogeuraemseontaek"')>0) {
        $("a#btn_1").text("목록디자인");
        $("#btn_1").css("background", "#88f");
        $("#btn_1").css("color", "#fff");
        $("#btn_1").click( function() {
            $('input#toolbar_znaeyongnochuryeobu').data('kendoDropDownList').value('');
            $('div#grid').data('kendoGrid').dataSource.read();
        });

        $("a#btn_2").text("내용디자인");
        $("#btn_2").css("background", "#f88");
        $("#btn_2").css("color", "#fff");
        $("#btn_2").click( function() {
            $('input#toolbar_znaeyongnochuryeobu').data('kendoDropDownList').value('Y');
            $('div#grid').data('kendoGrid').dataSource.read();
        });

        $("a#btn_3").text("디자인적용");
        $("#btn_3").css("background", "#8f8");
        $("#btn_3").css("color", "#000");
        $("#btn_3").click( function() {
            $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "class_init";
            $("#grid").data("kendoGrid").dataSource.read();
            $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "";
        });

        $('a#btn_3').after(`<div id="select-device">
            <span id="device-xxs">
                XXS(~500)
            </span>
            <span id="device-xs">
                XS(501~)
            </span>
            <span id="device-sm">
                SM(768~)
            </span>
            <span id="device-md">
                MD(992~)
            </span>
            <span id="device-lg">
                LG(1200~)
            </span>

        </div>`);
        $("#select-device").kendoButtonGroup({
            select: function (e) {
                btn_index = e.indices;
                if(btn_index==0) { w = 400; msg = '폭 500px 이하는 항상 1항목당 1줄입니다.'; }
                else if(btn_index==1) { w = 501; msg = 'XS 는 501 ~ 767px 에서 적용됩니다.'; }
                else if(btn_index==2) { w = 768; msg = 'SM 은 768 ~ 991px 에서 적용됩니다.'; }
                else if(btn_index==3) { w = 992; msg = 'MD 는 992 ~ 1199px 에서 적용됩니다.'; }
                else if(btn_index==4) { w = 1200; msg = 'LG 는 1200 이상에서 적용됩니다.'; }

                msg = msg + ' 현재 폭은 '+w+'px 입니다.';
                w = w + 10; //보정값
            
                obj = $("#horizontal").data("kendoSplitter");
                first_w = $(document).width() - w;
                obj.size(".k-pane:first",first_w+'px');
        
                kendoSplitter_reisze();
                toastr.info("", msg, {progressBar: true, timeOut: 6000});
            },
            index: 0
        });

    }

}
<?php } ?>

function kendoSplitter_reisze(p_target, p_size) {


    obj = $("#horizontal").data("kendoSplitter");
    w = $('iframe#grid_right').width();

    if(w<=500) btn_index = 0;
    else if(w<=767) btn_index = 1;
    else if(w<=991) btn_index = 2;
    else if(w<=1199) btn_index = 3;
    else btn_index = 4;
    $("#select-device").data('kendoButtonGroup').select(btn_index);


}
//아래는 그리드의 로딩이 거의 끝난 후, 항목에 스타일 시트를 적용하는 예입니다. 스타일 등을 직접 변경하려면 이 함수를 이용해야 합니다.
function rowFunctionAfter_UserDefine(p_this) {

    if(p_this.zuserdefined=='1') {
        $(getCellObj_idx(p_this[key_aliasName], "Grid_View_Class")).closest('tr').css("color","red");
    }

}
function listLogic_afterLoad_once()	{
	grid_remove_sort();
    $('div.k-grid-header tr[role="row"] th').unbind('click');
    $('a#btn_reload').css('color',theme_selectedBack());
    $('a#btn_reload').css('background',theme_selected());

	$('input#toolbar_zpeurogeuraemseontaek').data('kendoDropDownList').bind('select', function() {
		location.href = status_url();
		x.stop;
	});
	$('a#btn_menuName').after('<a class="k-button" id="btn_all_init" style="background: red; color: rgb(255, 255, 255);">전체적용</a>');
    $('a#btn_all_init').click( function() {
        if(confirm('전체 css class 적용을 진행하시겠습니까? 보호된 내역만 제외하고 새로 갱신됩니다.')) {
            toastr.info("","<h3>전체 프로그램에 대한 내용디자인을 적용중입니다.</h3>", {progressBar: true, timeOut: 7000, closeButton: false, positionClass: "toast-center-center"});
            url = "addLogic_treat.php?RealPid=<?=$real_pid?>&question=all_init_ready";
            temp = ajax_url_return(url);
            if(temp=='success') {
                setCookie('now_url',location.href);
                location.href = 'index.php?RealPid=speedmis001333&isAddURL=Y&psize=20000';
            } else {
                alert('디자인적용 준비작업이 실패하였습니다. 관리자에게 문의하세요.');
            }
        }
    });
}
			
function listLogic_afterLoad_continue()	{
	v = $('input#toolbar_zpeurogeuraemseontaek').data('kendoDropDownList').value();
	if(InStr(v,':')>0) {
		p = v.split(':')[0];
		u = 'index.php?RealPid='+p;
        if($('input#toolbar_znaeyongnochuryeobu').data('kendoDropDownList').value()=='Y') u = u + '&recently_view=Y';

        console.log(u);
        if($('iframe#grid_right').width()<20) {
    		splitter_right_read_idx(0,'about:blank');
        }
        document.getElementById("grid_right").src = u;
        kendoSplitter_reisze();
	}
}
			
			
	
			
			
        </script>
        <?php 



}
//end pageLoad



function list_json_init() {
    global $real_pid, $misJoinPid, $logicPid, $parent_idx;
    global $MS_MJ_MY, $flag, $app, $selField, $allFilter, $idx, $appSql, $resultCode, $resultMessage, $afterScript;

    global $addParam;

	$MisMenuList_Detail = 'MisMenuList_Detail';
	if($addParam=='pre') {
		$MisMenuList_Detail = 'MisMenuList_Detail_pre';
	}
    
    if(Left($flag,4)=='read' && $selField=='' && InStr($allFilter,'"field":"toolbar_zpeurogeuraemseontaek"')>0) {

        $pid = splitVB(splitVB($allFilter,'[{"operator":"eq","value":"')[1],':')[0];

        aliasN_update_RealPid($pid);

        
        if($MS_MJ_MY=='MY') {
            $appSql = "
            SET @sortNum := 0;
            UPDATE $MisMenuList_Detail SET SortElement = 
            ( SELECT @sortNum := @sortNum + 1 ), RealPidAliasName=concat(RealPid,'.',aliasName)
            where RealPid='$pid' ORDER BY SortElement, idx;     
            ";
        } else {
            $appSql = "
            DECLARE @RealPid nvarchar(14)  
            DECLARE @sql nvarchar(max)  
            DECLARE @idx int
            DECLARE @SortElement int
            DECLARE @ii int
        
            set @sql=''
            set @RealPid='$pid'
            set @ii=0
            
            DECLARE MisApp_CUR CURSOR FOR  
            select  idx,SortElement from $MisMenuList_Detail where RealPid=@RealPid order by SortElement, idx 
        
            Open MisApp_CUR
            FETCH NEXT FROM MisApp_CUR INTO @idx, @SortElement
            WHILE @@FETCH_STATUS = 0 
            BEGIN    
                set @ii = @ii+1
                set @sql = @sql + ' update $MisMenuList_Detail set RealPidAliasName=RealPid+''.''+aliasName,SortElement='+convert(nvarchar(5),@ii)+' where idx='+convert(nvarchar(5),@idx)
            
            FETCH NEXT FROM MisApp_CUR INTO @idx, @SortElement
            END
        
            exec (@sql)
        
            CLOSE MisApp_CUR
            DEALLOCATE MisApp_CUR      
            ";
        }

        if(execSql($appSql)) {
            //$_SESSION["newLogin"] = "Y";
            //$afterScript = "location.href = replaceAll(location.href, '#', '');";
        } else {
            $resultCode = "fail";
            $resultMessage = "처리가 실패하였습니다.";
        }

    }
  
}
//end list_json_init



function list_query() {

    global $real_pid, $misJoinPid, $logicPid, $parent_idx;
    global $flag, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript;
    global $countQuery, $selectQuery, $idx_aliasName, $addParam;

	if($addParam=='pre') {
        $countQuery = replace($countQuery,'MisMenuList_Detail','MisMenuList_Detail_pre');
        $countQuery = replace($countQuery,'_pre_pre','_pre');
        $selectQuery = replace($selectQuery,'MisMenuList_Detail','MisMenuList_Detail_pre');
        $selectQuery = replace($selectQuery,'_pre_pre','_pre');
	}
}
//end list_query



function list_json_load() {
	
    global $base_root, $real_pid, $misJoinPid, $logicPid, $parent_idx, $top, $isnull;
    global $flag, $selField, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript;
    global $data, $key_aliasName, $child_alias, $selectQuery, $keyword, $menuName, $allFilter;
    global $MS_MJ_MY;
	//$flag 는 목록조회시 'read'   내용조회시 'view'    수정시 'modify'   입력시 'write'
	//$selField 는 필터링을 하는 순간 발생하는 필드alias 값.

    global $addParam;

	$MisMenuList_Detail = 'MisMenuList_Detail';
	if($addParam=='pre') {
		$MisMenuList_Detail = 'MisMenuList_Detail_pre';
	}


	//아래는 조회 또는 수정 시, addLogic 이라는 항목에 대해 만약 파일로 존재하는 php 파일이 있다면 해당파일로 바꿔서 json 에 반영하는 로직입니다.
    if(Left($flag,4)=='read' && $selField=='') {

        //아래는 speedmis001333.php / speedmis000267.php 가 거의 같음.
        $r_data = json_decode($data);
        $r_count = count($r_data);
        $sql = '';
        $userdefined_cnt = 0;
        
        for($i=0;$i<$r_count;$i++) {
            $r_idx = $r_data[$i]->idx;
            $r_Grid_View_Fixed = $r_data[$i]->Grid_View_Fixed;
            $r_Grid_Schema_Validation = $r_data[$i]->Grid_Schema_Validation;
            $r_Grid_View_XS = $r_data[$i]->Grid_View_XS;
            $r_zgwonjangkeullaeseu = $r_data[$i]->zgwonjangkeullaeseu;      //권장class
            $r_cell = splitVB($r_zgwonjangkeullaeseu,' ');
            $r_XS = replace(Right($r_cell[0],2),'-','');
            $r_SM = replace(Right($r_cell[1],2),'-','');
            $r_MD = replace(Right($r_cell[2],2),'-','');
            $r_LG = replace(Right($r_cell[3],2),'-','');
            $r_Hight = replace(Right($r_cell[4],2),'-','');
            $simple_tag = "col-xs-$r_XS" . iif($r_XS!=$r_SM, " col-sm-$r_SM", "") . iif($r_SM!=$r_MD, " col-md-$r_MD", "") . iif($r_MD!=$r_LG || $r_LG=='12', " col-lg-$r_LG", "") . " row-$r_Hight";
        
            $r_zseoljeongkeullaeseu = $r_data[$i]->zseoljeongkeullaeseu;    //설정 class
            $r_zseoljeongSIMPLE = $r_data[$i]->zseoljeongSIMPLE;    //설정 SIMPLE
            
            $r_Grid_View_Class = $r_data[$i]->Grid_View_Class;          //저장 class
            $r_Grid_Enter = $r_data[$i]->Grid_Enter;
            
            if($r_Grid_View_XS=='0' || $r_Grid_View_XS=='' || $app=='class_init') {
                $sql = $sql . "
                update $MisMenuList_Detail set 
                Grid_View_Class=N'$simple_tag'
                ,Grid_View_XS=$r_XS
                ,Grid_View_SM=$r_SM
                ,Grid_View_MD=$r_MD
                ,Grid_View_LG=$r_LG
                ,Grid_View_Hight=$r_Hight
                where idx=$r_idx and $isnull(Grid_View_Fixed,0)<>1;
                ";


				
                if($r_Grid_View_Fixed!='1') {
                    $r_data[$i]->Grid_View_XS = $r_XS;
                    $r_data[$i]->Grid_View_SM = $r_SM;
                    $r_data[$i]->Grid_View_MD = $r_MD;
                    $r_data[$i]->Grid_View_LG = $r_LG;
                    $r_data[$i]->Grid_View_Hight = $r_Hight;
                }

                if($r_Grid_Schema_Validation=='zipcode') {
                    //print_r($r_data[$i-1]);exit;
                    if($i<$r_count-1) {
                        $r_idx_next = $r_data[$i+1]->idx;   //우편번호가 두번째로 나오던 시절때문에 수습차원.
                        $sql = $sql . "
                        update $MisMenuList_Detail set 
                        Grid_Enter=0
                        where idx=$r_idx_next;
                        update $MisMenuList_Detail set 
                        Grid_Enter=1
                        where idx=$r_idx;
                        ";
                        $r_data[$i+1]->Grid_Enter = 0;
                        $r_data[$i]->Grid_Enter = 1;
                    }
                }
                


            } else if($r_zseoljeongSIMPLE!=$r_Grid_View_Class) {
                $sql = $sql . "
                update $MisMenuList_Detail set 
                Grid_View_Class=N'$r_zseoljeongSIMPLE'
                where idx=$r_idx;
                ";
            }
            if(InStr($allFilter,'"field":"toolbar_zpeurogeuraemseontaek"')>0) {
                if($r_zseoljeongSIMPLE!=$simple_tag) {
                    ++$userdefined_cnt;
                    $r_data[$i]->zuserdefined = '1';
                } else if($r_Grid_Enter==1) {
                    if($r_data[$i]->Grid_Schema_Validation!='zipcode') {
                        ++$userdefined_cnt;
                    }
                    $r_data[$i]->zuserdefined = '1';
                }
            }
        }
		
        if($sql!='') {
			execSql($sql);
        }
        if($top==20000) {
            //출력할 필요 없음.
            $data = '[]';
        }
        if(InStr($allFilter,'"field":"toolbar_zpeurogeuraemseontaek"')>0) {
            $data = json_encode($r_data);
            if($userdefined_cnt>0) {
                $resultMessage = "<div style='background:yellow;color:blue;padding:5px;'>$userdefined_cnt 개의 사용자정의 항목수가 존재합니다.</div>";
            }
        }
    }
	
}
//end list_json_load



function textUpdate_sql() {

    global $strsql, $keyAlias, $keyValue, $thisValue, $oldText, $thisAlias, $resultCode, $resultMessage, $afterScript;

    global $addParam, $table_m;
    $MisMenuList_Detail = 'MisMenuList_Detail';

    if(Left($thisAlias,10)=='Grid_View_') {
        $thisValue = $thisValue*1;
        /*
패턴    Grid_View_LG	Grid_View_MD	Grid_View_SM	Grid_View_XS	빈도
1       3	3	3	6	3308
2       3	3	6	6	177
3       6	12	12	12	132
4       12	12	12	12	109
5       6	6	6	12	70
6       3	3	6	12	62
7       6	6	12	12	51
8       2	2	3	6	-
        */
        $Grid_View_LG = 'Grid_View_LG';
        $Grid_View_MD = 'Grid_View_MD';
        $Grid_View_SM = 'Grid_View_SM';
        $Grid_View_XS = 'Grid_View_XS';

        if($thisAlias=='Grid_View_LG' && $thisValue<=2) {
            $Grid_View_MD = 2;
            $Grid_View_SM = 3;
            $Grid_View_XS = 6;
        } else if($thisAlias=='Grid_View_LG' && $thisValue<=3) {
            $Grid_View_MD = 3;
            $Grid_View_SM = 3;
            $Grid_View_XS = 6;
        } else if($thisAlias=='Grid_View_LG' && $thisValue<=5) {
            $Grid_View_MD = $thisValue;
            $Grid_View_SM = 12;
            $Grid_View_XS = 12;
        } else if($thisAlias=='Grid_View_LG' && $thisValue<=12) {
            $Grid_View_MD = 12;
            $Grid_View_SM = 12;
            $Grid_View_XS = 12;
        } else if($thisAlias=='Grid_View_MD' && $thisValue<=3) {
            $Grid_View_SM = 3;
            $Grid_View_XS = 6;
        } else if($thisAlias=='Grid_View_MD' && $thisValue<=6) {
            $Grid_View_SM = $thisValue;
            $Grid_View_XS = 12;
        } else if($thisAlias=='Grid_View_MD' && $thisValue<=12) {
            $Grid_View_SM = 12;
            $Grid_View_XS = 12;
        } else if($thisAlias=='Grid_View_SM' && $thisValue<=6) {
            $Grid_View_XS = 6;
        } else if($thisAlias=='Grid_View_SM' && $thisValue<=12) {
            $Grid_View_XS = 12;
        }

        $strsql = $strsql . "
        update MisMenuList_Detail set Grid_View_LG=$Grid_View_LG, Grid_View_MD=$Grid_View_MD, Grid_View_SM=$Grid_View_SM, Grid_View_XS=$Grid_View_XS 
        where idx=$keyValue and Grid_View_Fixed=0;
        ";
    }


    if($addParam=='pre') {
        $strsql = replace($strsql, 'MisMenuList_Detail', 'MisMenuList_Detail_pre');
	 	$table_m = 'MisMenuList_Detail_pre';
    }

}
//end textUpdate_sql



function addLogic_treat() {
    global $real_pid, $MS_MJ_MY, $full_siteID, $isnull, $dbalias;
    //addLogic_treat 함수는 ajax 로 요청되어진(url 형식) 것에 대한 출력문입니다. echo 등으로 출력내용만 표시하면 됩니다.
	//아래는 url 에 동반된 파라메터의 예입니다.
	//해당 예제 TIP 의 기본폼에 보면 addLogic_treat 를 호출하는 코딩이 있습니다.
    global $addParam;

	$MisMenuList_Detail = 'MisMenuList_Detail';
	if($addParam=='pre') {
		$MisMenuList_Detail = 'MisMenuList_Detail_pre';
	}


    $question = requestVB("question");

	//아래는 값에 따라 sql 서버를 통해 알맞는 값을 출력하여 보냅니다.
    if($question=="apply_from_speedmis" && $full_siteID=='speedmis') {



        $sql = "SELECT RealPidAliasName, Grid_Enter, Grid_View_Class FROM $MisMenuList_Detail where RealPid like 'speedmis%'";
        echo jsonReturnSql($sql);
		
    }

    if($question=="all_init_ready") {

        
        $sql = '';
        if($full_siteID!='speedmis') {
            $url = "https://www.speedmis.com/_mis/addLogic_treat.php?RealPid=$real_pid&question=apply_from_speedmis";
            $data_from_speedmis = file_get_contents_new($url);
            $data_from_speedmis = json_decode($data_from_speedmis);
            $cnt = count($data_from_speedmis);
            
            for($i=0;$i<$cnt;$i++) {
                if($data_from_speedmis[$i]->Grid_Enter!=1) $data_from_speedmis[$i]->Grid_Enter = 0;
                $sql = $sql . " 
                update $MisMenuList_Detail set Grid_Enter=" . $data_from_speedmis[$i]->Grid_Enter . ", 
                Grid_View_Class='" . $data_from_speedmis[$i]->Grid_View_Class . "'
                where RealPidAliasName='" . $data_from_speedmis[$i]->RealPidAliasName . "';";
                //if($data_from_speedmis[$i]->RealPidAliasName=='speedmis000314.zgwonhanjeondal') {
                //    echo $sql;exit;
                //}
                if(Len($sql)>1000) {
                    execSql($sql);
                    $sql = '';
                }
            }
            if($sql!='') execSql($sql);
        }

        
		$sql = " 
        update $MisMenuList_Detail set Grid_View_XS='', Grid_View_Class='', Grid_View_Hight='' 
        where $isnull(Grid_View_Fixed,0)=0 " . iif($full_siteID=='speedmis', '', " and RealPid not like 'speedmis%'") . ';';

		execSql($sql);

		echo 'success';
		
    }

}
//end addLogic_treat

?>