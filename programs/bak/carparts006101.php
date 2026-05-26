<?php

function pageLoad() {

    global $ActionFlag, $RealPid, $logicPid, $parent_idx, $idx;
	global $MisSession_UserID, $MisSession_IsAdmin, $parent_RealPid;
/*
	//특정상황에서 페이지를 이동시키는 예제입니다.
	if($ActionFlag=='list' && $parent_RealPid=='speedmis000028') {
		$target_parent_gubun = RealPidIntoGubun('speedmis001071');
		$url = "index.php?RealPid=$RealPid&parent_gubun=$target_parent_gubun&parent_idx=$parent_idx";
		re_direct($url);
	}
*/

        ?>

<style>
/* 필요할 경우, 해당프로그램에 추가할 css 를 넣으세요 */
	
	
</style>


        <script>
			
//아래 한줄의 주석문을 풀면 리스트 상에서 내용조회나 수정으로 접근할 수 없습니다.
//$('body').attr('onlylist','');			
//아래 한줄의 주석문을 풀면 리스트 상에서 목록1개만 로딩되어도 자동내용열림을 방지할 수 있습니다.
//$('body').attr('auto_open_refuse','');	
			
//간편추가 쿼리를 팝업이 아닌, 현재창에서 진행합니다.			
//$('body').attr('brief_insert_this_page','');
			
			
//엑셀을 이용한 인쇄폼에서 PDF 로 저장할 경우, 또는 인쇄폼에서 PDF 로 저장할 경우 용지나 여백을 조정해야할 경우.
/*
function thisPage_options_pdf() {
    return {
        paperSize: "A4",
		scale: 0.567,
		landscape: false,
		margin: { left: "0.2cm", top: "0.9cm", right: "1.0cm", bottom: "0.5cm" }
    }
}
*/
	
			
			
//웹소스 디테일에서 템플릿으로 체크한 항목에 대해 출력내용을 변경할 수 있습니다. 이때 목록 또는 본문내용에 동일하게 적용됩니다.
//row 갯수만큼 실행됩니다.
			
function 완료처리() {
	
	sel_status = $('select#status').data('kendoDropDownList').value();
	table_link_idxQnstatus = resultAll.d.results[0]['table_link_idxQnstatus'];
	if(Right(sel_status,3)=='금완료' && Right(table_link_idxQnstatus,3)=='금완료') {
		if(!confirm('최종완료처리를 진행할까요? 이럴 경우, 구매업체에 새로운 부품ID 가 생성되며 재고가 표시됩니다.')) {
			return false;
		}
	} else if (Right(sel_status,2)=='거부') {
		if(!confirm('이제까지의 원장내역을 무시하고, 취소처리하시겠습니까?')) {
			return false;
		}
	} else if(Right(sel_status,3)=='금완료' && Right(table_link_idxQnstatus,3)!='금완료') {
		alert('[거래처_진행상태]가 송금완료 또는 수금완료가 되어야 처리할 수 있습니다.');		
		return false;
	} else if(Right(sel_status,3)!='금완료' && Right(table_link_idxQnstatus,3)=='금완료') {
		alert('진행상태를 송금완료 또는 수금완료로 저장 후 처리가 가능합니다.');		
		return false;
	} else {
		alert('진행상태가 올바르지 않아 처리할 수 없습니다.');		
		return false;
	}
	url = "addLogic_treat.php?RealPid=<?php echo $logicPid; ?>&idx="+$('input#idx')[0].value+"&link_idx="+resultAll.d.results[0]['table_link_idxQnidx']+"&question=완료처리";
	temp = ajax_url_return(url);
	alert(temp);
}

function columns_templete(p_dataItem, p_aliasName) {
  
    if(p_aliasName=='table_mQmnaljja') {

		//웹소스디테일의 데이터타입을 number^^#,##0 라고 지정 및 템플릿을 Y 로 했을 경우, 아래와 같은 식으로 하면 number 포맷으로 출력할 수 있음.
		rValue = p_dataItem['table_mQmnaljja'] + '<br/>' + p_dataItem['zsubalsin'] + '<br/>' + iif(p_dataItem['tr_type']=='OUT', '출고', '입고');


		return rValue;
    } else if(p_aliasName=='table_it_idQnit_img1') {

		//웹소스디테일의 데이터타입을 number^^#,##0 라고 지정 및 템플릿을 Y 로 했을 경우, 아래와 같은 식으로 하면 number 포맷으로 출력할 수 있음.
		rValue = `
		<div><a href="https://xn--or3b27p5mi.com/shop/item.php?it_id=`+ p_dataItem['it_id'] + `" target=_blank>SHOP</a>`;
		if(p_dataItem['tr_type']=='OUT') {
			rValue = rValue + ` | <a href="/gadmin/index.php?gubun=6074&idx=`+ p_dataItem['it_id'] + `&isMenuIn=Y" target=_blank>gadmin</a>`;
		}
		rValue = rValue + `</div><img style="max-height:150px;" src="thumbnail.php?/data/item/`+ p_dataItem[p_aliasName] + `"/>
		`;


		return rValue;
    } else if(p_aliasName=='qq_item_info') {

		//웹소스디테일의 데이터타입을 number^^#,##0 라고 지정 및 템플릿을 Y 로 했을 경우, 아래와 같은 식으로 하면 number 포맷으로 출력할 수 있음.
		rValue = '<span title="제조사카테고리&#10;부품카테고리&#10;창고카테고리&#10;부품명&#10;부품파트넘버">'+p_dataItem[p_aliasName]+'</span>';


		return rValue;
    } else if(p_aliasName=='isEnd') {

		if(p_dataItem[p_aliasName]=='C') {
			rValue = '<a disabled class="k-button k-button-icontext">취소된 내역</a>';
		} else if(p_dataItem[p_aliasName]=='Y') {
			rValue = '<a disabled class="k-button k-button-icontext">처리된 내역</a>';
		} else {
			rValue = `<a class="k-button k-button-icontext" onclick="완료처리();"
			title="양자간 송금완료/수금완료 또는 승인거부일 경우 처리 가능합니다.">처리하기</a>`;
		}


		return rValue;
    } else {
        return p_dataItem[p_aliasName];
    }
}


			
			
//아래의 함수는 목록에서만 해당되며, 템플릿으로 정의하지 않아도 특정항목의 값이나 태그를 추가할 수 있습니다. 
//row 갯수만큼 실행됩니다.
function rowFunction_UserDefine(p_this) {
/*
	p_this.MenuName = p_this.depth + p_this.LanguageCode; 
    p_this.AutoGubun = 
    "<a href=index.asp?gubun=" + p_this.idx + "&isMenuIn=Y target=_blank>[Go]</a> <a id='aid_" + p_this.idx + "' href=index.asp?RealPid=speedmis000266&idx=" + p_this.idx + "&isMenuIn=Y target=_blank>[Source]</a>?" 
    + p_this.AutoGubun;
*/
}

			
//아래는 그리드의 로딩이 거의 끝난 후, 항목에 스타일 시트를 적용하는 예입니다. 스타일 등을 직접 변경하려면 이 함수를 이용해야 합니다.
//row 갯수만큼 실행됩니다.
function rowFunctionAfter_UserDefine(p_this) {
	if(p_this.사진) {
		if(p_this.사진.length>10) {
			$(getCellObj_idx(p_this[key_aliasName], "사진"))[0].innerHTML = '<img src="thumbnail.php?/data/item/'+p_this.사진+'"/>';
		}
	}
	/*
    if(p_this.AutoGubun.length==6) {
        $(getCellObj_idx(p_this[key_aliasName], "SortG2")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "SortG2")).css("color","transparent");
        $(getCellObj_idx(p_this[key_aliasName], "SortG4")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "SortG4")).css("color","transparent");
    } else if(p_this.AutoGubun.length==4) {
        $(getCellObj_idx(p_this[key_aliasName], "SortG2")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "SortG2")).css("color","transparent");
        $(getCellObj_idx(p_this[key_aliasName], "SortG6")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "SortG6")).css("color","transparent");
    } else if(p_this.AutoGubun.length==2) {
        $(getCellObj_idx(p_this[key_aliasName], "SortG4")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "SortG4")).css("color","transparent");
        $(getCellObj_idx(p_this[key_aliasName], "SortG6")).attr("stopEdit","true");
        $(getCellObj_idx(p_this[key_aliasName], "SortG6")).css("color","transparent");
    }
	*/

}
			
<?php 
//해당프로그램의 관리자 권한을 가진 사용자는 $MisSession_IsAdmin=='Y' 입니다. 필요 시, 여러 부분에서 해당 조건을 넣어 사용하세요.
//if($MisSession_IsAdmin=='Y' && $ActionFlag=='list') { 
			
//}
?>
//사용자 정의버튼 생성 예제입니다.
//툴바 명령버튼에 btn_1 이라고 하는 예비버튼을 "적용" 이라는 기능을 넣고, 클릭 시, 그리드에 app="적용" 이라는 신호로 보냅니다.
//이때 app=="적용" 에 대한 처리는 개발Tip 의 list_json_init() 를 참조하세요.
function thisLogic_toolbar() {
/*
    $("a#btn_1").text("적용");
    $("li#btn_1_overflow").text("적용");
    $("#btn_1").css("background", "#88f");
    $("#btn_1").css("color", "#fff");
    $("#btn_1").click( function() {
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "적용";
        $("#grid").data("kendoGrid").dataSource.read();
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "";
    });
*/
}

//목록에서 grid 로드 후 한번만 실행됨, 이때 처리해야할 일반 스크립트를 삽입합니다.
function listLogic_afterLoad_once()	{
	//grid_remove_sort();    //그리드의 상단 정렬 기능 제거를 원할 경우.
	
}
			
//목록에서 grid 로드 후 데이터 로딩마다 실행됨, 이때 처리해야할 일반 스크립트를 삽입합니다.		
function listLogic_afterLoad_continue()	{
	
	
}

function helpboxLogic_afterSelect(p_actionFlag, p_opener, p_sel_alias, p_sel_value) {
	if(p_opener.$('select#tr_type').data('kendoDropDownList').value()=='') {
		alert('물류구분을 먼저 선택하세요!');
		return false;
	}
	if(p_actionFlag=='write' && p_sel_alias=='it_id') {
		sel_it_10_target = p_opener.$('select#it_10_target').data('kendoDropDownList').value();
		거래처id = getCellObj_list('거래처id')[getGridRowIndex_gridRowTr(event.currentTarget)].innerText;
		if(getCookie('tr_type')=='OUT') {
			if(sel_it_10_target=='admin') {
				p_opener.$('select#it_10_target').data('kendoDropDownList').value('');
			}
		} else {
			p_opener.$('select#it_10_target').data('kendoDropDownList').value(거래처id);
		}
	}

}

//업무용 MIS 의 목록형태가 save_templist 일 경우, 데이터가 추가될때마다 자동화면clear 대신 임의설정을 하고자할때 활성화 시켜서 사용할 것.
/*			
function templist_write_form_clear() {
    
	//자동 forme clear
    //write_form_clear();  
	
	//입력컨트롤 중에 첫째 컨트롤에 포커스를 맞춤
    //if($('form#frm input[type="text"][data-role]:visible')[0]) {
    //    $($('form#frm input[type="text"][data-role]:visible')[0]).focus();
    //}
    
    //임의의 특정컨트롤에 포커스를 맞춤.
	//$('input#BARCODE').focus();
}
*/

			
function before_read_idx() {
			/*
            document.getElementById('idx').value 만 전달된 상황에서 
            viewLogic_afterLoad, viewLogic_afterLoad_continue 함수보다 더 빠르게 사용자로직을 적용해야 하는 경우 유용함.
            예) kimgo. 3041 프로그램. list 는 일반적인 형태이지만, view 에서는 list5 를 사용하는 경우,
                사용자가 리스트를 클릭하면 빠르게 location 을 변경시켜야할 때가 있음. 이때 사용함.
            */
		/*
		url = "addLogic_treat.php?RealPid="+document.getElementById('RealPid').value
		+"&idx="+getUrlParameter('idx')+"&question=get_gidx";
		gidx_by_url = ajax_url_return(url);

		url = "addLogic_treat.php?RealPid="+document.getElementById('RealPid').value
		+"&idx="+document.getElementById('idx').value+"&question=get_gidx";
		gidx_by_value = ajax_url_return(url);

		if(gidx_by_url!=gidx_by_value) {
			beforeunload_ignore();
			re_url = "index.php?gubun="+document.getElementById('gubun').value
					+"&parent_gubun="+document.getElementById('gubun').value
					+"&parent_idx="+gidx_by_value
					+"&idx="+document.getElementById('idx').value+"&ActionFlag=modify&isAddURL=Y&psize=5";
			location.replace(re_url);
			return 'stop';
		}
		*/

}			

function viewLogic_before_save() {
	if($('input#ActionFlag')[0].value=='write') {
		공급가 = $('input#gonggeupga').data('kendoNumericTextBox').value();
		판매가 = $('input#pammaega').data('kendoNumericTextBox').value();
		if(공급가<1000) {
			if(!confirm('공급가가 천원도 안됩니다. 진행할까요?')) {
				return false;
			}
		}
		if(공급가>판매가 && 판매가>0) {
			if(!confirm('공급가가 판매가 보다 비쌉니다. 진행할까요?')) {
				return false;
			}
		}
	}
}
			
			
			
			
	var dropdown = null;
	// 1. 로딩 직후: 원본 데이터 기억하기
    // dataSource.data()를 통해 현재 목록을 배열로 복사해둡니다.
    var originalData = null;


//내용조회 또는 수정/입력 페이지 로딩이 끝나는 순간 한번만 실행됨, 이때 처리해야할 일반 스크립트를 삽입합니다.
function viewLogic_afterLoad() {

	
	dropdown_tr_type = $('select#tr_type').data('kendoDropDownList');
	dropdown = $('select#status').data('kendoDropDownList');
	// 1. 로딩 직후: 원본 데이터 기억하기
    // dataSource.data()를 통해 현재 목록을 배열로 복사해둡니다.
    originalData = dropdown.dataSource.data().toJSON();

	if($('input#ActionFlag')[0].value=='write') {
		dropdown_tr_type.bind("change", function() {
			setCookie('tr_type', this.value());
			dropdown.trigger("open");
		});
		dropdown.bind("open", function() {
			var IN_OUT = $('select#tr_type').data('kendoDropDownList').value();
			// value에 'OUT'이 포함된 내역만 필터링 (case-insensitive 고려)
			var filteredData = originalData.filter(function(item) {
				return item.value.includes(IN_OUT) || item.value === ''; // 빈값(선택하세요 등) 포함 여부는 선택
			});

			// 필터링된 데이터로 재설정
			dropdown.setDataSource(new kendo.data.DataSource({
				data: filteredData
			}));
			if(dropdown.value()=='' || InStr(dropdown.value(),IN_OUT)==0) {
				dropdown.value('CASE_'+IN_OUT);
			}
			dropdown.trigger("change");
		});
		

	}


}			
//내용조회 또는 수정/입력 페이지 로딩이 끝난 후, 데이터 호출때마다 실행됨. 
function viewLogic_afterLoad_continue() {

	if($('input#ActionFlag')[0].value=='modify') {
		var IN_OUT = resultAll.d.results[0]['tr_type'];
        var filteredData = originalData.filter(function(item) {
            return item.value.includes(IN_OUT) || item.value === ''; // 빈값(선택하세요 등) 포함 여부는 선택
        });

        // 필터링된 데이터로 재설정
        dropdown.setDataSource(new kendo.data.DataSource({
            data: filteredData
        }));
	}
	

}	
			
			
//사용자 정의 인쇄폼의 로딩이 완료될때 처리해야할 스크립트를 삽입합니다.
function viewLogic_afterLoad_viewPrint() {
	
	//아래는 특정이미지를 하단에 넣는 예제입니다.
	//$('div.viewPrint').append('<img style="position: absolute;bottom: 32px;left: 35px;width: 200px;" src="/_mis/img/speedmis_wide.png">');
	
}			

			
//입력 또는 수정 폼에서 우편번호 팝업 선택 직후, 추가로직을 넣을 수 있습니다. 아주 간혹 필요합니다.
/*
function addLogic_zipcode(zipdata) {
		//sido / sigungu / bname / jibunAddress
	$('input#dodo')[0].value = zipdata.sido.split(' ')[0];
	$('input#sigunggu')[0].value = zipdata.sigungu.split(' ')[0];
	$('input#dongmyeon')[0].value = zipdata.bname.split(' ')[0];
	$('input#beonji')[0].value = zipdata.jibunAddress.split(zipdata.bname+' ')[1];
}	
*/		
			
        </script>
        <?php 
}
//end pageLoad



function list_json_init() {
    global $RealPid, $MisJoinPid, $logicPid, $parent_idx, $full_siteID;
    global $flag, $selField, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript;
	//$flag 는 목록조회시 'read'   내용조회시 'view'    수정시 'modify'   입력시 'write'
	//$selField 는 필터링을 하는 순간 발생하는 필드alias 값.

	//아래의 예제는 자동정렬이라는 명령을 받았을 경우, 목록이 생성되기 전에 숫자를 정렬하는 기능입니다.

    if($flag=='read' && $selField=='') { 

    	$appSql = "

UPDATE mis_projects AS parent
JOIN (
    -- 하위 항목들의 sdate 최소값과 edate 최대값을 구하는 서브쿼리
    SELECT 
        LEFT(autogubun, 4) AS group_prefix, 
        MIN(NULLIF(sdate, '')) AS min_sdate, 
        MAX(NULLIF(edate, '')) AS max_edate
    FROM mis_projects
    WHERE useflag = '1' 
      AND LENGTH(autogubun) > 4 -- 하위 depth들만 대상으로 함
    GROUP BY LEFT(autogubun, 4)
) AS child_info ON parent.autogubun = child_info.group_prefix
SET 
    parent.sdate = child_info.min_sdate,
    parent.edate = child_info.max_edate
WHERE 
    parent.depth = 1 
    AND parent.useflag = '1';

";
       execSql($appSql);
    }

}
//end list_json_init



function list_query() {

    global $RealPid, $MisJoinPid, $logicPid, $parent_idx, $MisSession_UserID;
    global $flag, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript;
    global $countQuery, $selectQuery, $idx_aliasName, $helpbox;



	//아래는 어떤 특정한 상황에 대한 적용예입니다.
    if($helpbox=="it_id") { 
		$MisSession_UserID2 = str_replace('gadmin','admin', $MisSession_UserID);
		if(getCookie('tr_type')=='OUT') {
			$countQuery = str_replace("and 123=123", "and 123=123 and table_m.it_10='$MisSession_UserID2'", $countQuery);
			$selectQuery = str_replace("and 123=123", "and 123=123 and table_m.it_10='$MisSession_UserID2'", $selectQuery);
		} else {
			$countQuery = str_replace("and 123=123", "and 123=123 and table_m.it_10<>'$MisSession_UserID2'", $countQuery);
			$selectQuery = str_replace("and 123=123", "and 123=123 and table_m.it_10<>'$MisSession_UserID2'", $selectQuery);
		}
   }

}
//end list_query



function save_writeBefore() {
    global $full_siteID, $base_root, $RealPid, $MisJoinPid, $logicPid, $parent_idx, $MisSession_UserID;

//$viewList: 내용전체 array     $saveList: 저장내역 array(aliasName 으로 표현됨)      $updateList: 최종업데이트될 내역(실제필드이름으로 표현됨)
    global $key_aliasName, $key_value, $ActionFlag, $viewList, $saveList, $updateList;

	//아래와 같이 특정항목의 값으로 대체하는 경우도 있습니다.
    //$RealCid = $full_siteID . onlyOnereturnSql("declare @idx int set @idx=ident_current('dbo.MisCommonTable')+1 select replicate('0', 6-len(@idx))+convert(nvarchar(6),@idx) ");
    //$updateList["RealCid"] =  $RealCid;
    
	if($updateList['it_10']==$updateList['it_10_target']) {
echo '<script>alert("당사와 거래할 수는 없습니다.");</script>';
exit;
	}
    
	if($updateList['it_10']!=replace($MisSession_UserID,'gadmin','admin')) {
		$updateList['it_10'] = replace($MisSession_UserID,'gadmin','admin');	//참조입력할때 이럴 수 있음. 그래서 코딩함.
	}
}
//end save_writeBefore



function save_writeQueryBefore() {
	//$viewList: 내용전체 array     $saveList: 저장내역 array(aliasName 으로 표현됨)      $updateList: 최종업데이트될 내역(실제필드이름으로 표현됨)
	//$newIdx: 입력시 생성된 자동증가번호     $sql: 입력쿼리문      $sql_prev: 입력쿼리문 앞에 붙을 쿼리문     $sql_next: 입력실행 뒤에 붙을 쿼리문
	global $full_siteID, $base_root, $RealPid, $MisJoinPid, $logicPid, $parent_idx, $MisSession_UserID;
    global $key_aliasName, $key_value, $ActionFlag, $viewList, $saveList, $updateList, $sql, $sql_prev, $sql_next, $newIdx;

	$deliv_qty = $updateList['deliv_qty'];
	$원가 = $updateList['원가'];
	$판매가 = $updateList['판매가'];
	$공급가 = $updateList['공급가'];
	$sql2 = $sql;
	$sql = $sql . "
SET @ref_idx = LAST_INSERT_ID();
update g5_shop_원장 set ref_idx=@ref_idx, link_idx=@ref_idx+1, wdater=it_10 where idx=@ref_idx;
$sql2
update g5_shop_원장 set wdater = it_10_target
, ref_idx=@ref_idx
, link_idx=@ref_idx
, status=case when tr_type='IN' then 'CASE_OUT' else 'CASE_IN' end 
, tr_type=case when tr_type='IN' then 'OUT' else 'IN' end 
, deliv_qty=$deliv_qty
, 원가=$원가
, 판매가=$판매가
, 공급가=$공급가
where idx=@ref_idx+1;
	";

    execSql($sql);
echo("<script>parent.location.href = 'index.php?RealPid=$RealPid&isMenuIn=Y';</script>");
exit;

}
//end save_writeQueryBefore



function addLogic_treat() {

	global $MisSession_UserID, $base_root;
	
    //addLogic_treat 함수는 ajax 로 요청되어진(url 형식) 것에 대한 출력문입니다. echo 등으로 출력내용만 표시하면 됩니다.
	//아래는 url 에 동반된 파라메터의 예입니다.
	//해당 예제 TIP 의 기본폼에 보면 addLogic_treat 를 호출하는 코딩이 있습니다.

    $question = requestVB("question");
    $p_idx = (int)requestVB("idx");
    $link_idx = requestVB("link_idx");

	//아래는 값에 따라 mysql 서버를 통해 알맞는 값을 출력하여 보냅니다.
    if($question=="완료처리") {

		$sql = " select * from g5_shop_원장 where idx in ('$p_idx','$link_idx'); ";
		$r = allreturnSql($sql);
		
		if(count($r)<2) {
			exit('비정상적인 내역입니다. 관리자에게 문의하세요!');				
		}
						
		//print_r($r);
		$me_index = 0; $target_index = 1;
		if($p_idx!=$r[0]['idx']) {
			$me_index = 1; $target_index = 0;
		}
		$it_id = $r[0]['it_id'];
		$status = $r[$me_index]['status'];
		$status_target = $r[$target_index]['status'];

		if($r[0]['tr_type']=='OUT') {
			$it_10 = $r[0]['it_10_target'];
		} else {
			$it_10 = $r[1]['it_10'];
		}
						
		if(Right($status,3)=='금완료' && Right($status_target,3)=='금완료') {
			$sql = "select UserAlias from MisUser where UniqueNum='$it_10';";
			$it_partner_nick = onlyOnereturnSql($sql);

			$it_stock_qty = $r[0]['deliv_qty'];
			$deliv_qty = $r[0]['deliv_qty'];

//print_r($r);
/*
Array
(
    [0] => Array
        (
            [idx] => 13
            [ref_idx] => 13
            [link_idx] => 14
            [날짜] => 2026-01-25
            [it_10] => admin
            [it_10_target] => bearsparts
            [it_id] => 1766995456
            [it_id_new] => 
            [it_idca_car_fullname] => 
            [it_idca_fullname] => 
            [it_idca_storage] => 
            [tr_type] => OUT   --> 이것이 OUT 이므로 IN 업체는 it_10_target 임.
            [status] => OUT3.수금완료
            [status_target] => 
            [curr_qty] => 2
            [deliv_qty] => 1
            [isEnd] => N
            [네이버] => 0
            [원가] => 9
            [판매가] => 300000
            [공급가] => 11
            [사입금] => 0
            [수입] => 0
            [지출] => 0
            [HIT] => 
            [IP] => 
            [useflag] => 1
            [wdate] => 2026-01-25 17:37:32
            [wdater] => admin
            [lastupdate] => 2026-01-25 17:46:40
            [lastupdater] => gadmin
        )

    [1] => Array
        (
            [idx] => 14
            [ref_idx] => 13
            [link_idx] => 13
            [날짜] => 2026-01-25
            [it_10] => admin
            [it_10_target] => bearsparts
            [it_id] => 1766995456
            [it_id_new] => 
            [it_idca_car_fullname] => 
            [it_idca_fullname] => 
            [it_idca_storage] => 
            [tr_type] => IN
            [status] => IN3.송금완료
            [status_target] => 
            [curr_qty] => 2
            [deliv_qty] => 1
            [isEnd] => N
            [네이버] => 0
            [원가] => 9
            [판매가] => 300000
            [공급가] => 11
            [사입금] => 0
            [수입] => 0
            [지출] => 0
            [HIT] => 
            [IP] => 
            [useflag] => 1
            [wdate] => 2026-01-25 17:37:32
            [wdater] => bearsparts
            [lastupdate] => 2026-01-25 17:46:28
            [lastupdater] => bearsparts
        )

)

*/
$sql = "

insert into g5_shop_item
(
ca_car_id, ca_id, ca_id2, ca_id3
-- , ca_storage_id -- 차후 선택하도록 유도
, it_skin, it_mobile_skin, it_name, it_seo_title, part_number, it_maker, it_origin, it_brand, it_model, it_option_subject, it_supply_subject, it_type1, it_type2, it_type3, it_type4, it_type5, it_basic
, it_explan -- 사진경로 수정
, it_explan2, it_mobile_explan, it_cust_price, it_price, it_price_sea, it_price_cost, it_price_supply, it_point, it_point_type, it_supply_point, it_notax, it_sell_email
, it_use  -- 0
, it_nocoupon  -- 0
, it_soldout  -- 0
, it_stock_qty  -- 납품수량
, it_stock_sms  -- 0
, it_noti_qty, it_sc_type, it_sc_method, it_sc_price, it_sc_minimum, it_sc_qty, it_buy_min_qty, it_buy_max_qty, it_head_html, it_tail_html, it_mobile_head_html, it_mobile_tail_html, it_hit
,it_ip, it_order, it_tel_inq, it_info_gubun, it_info_value, it_sum_qty, it_use_cnt, it_use_avg, it_shop_memo, ec_mall_pid, it_img_mis

-- 신규 _midx 생성
, it_img_mis_midx

-- 사진경로 수정
, it_img1, it_img2, it_img3, it_img4, it_img5, it_img6, it_img7, it_img8, it_img9, it_img10, it_img11, it_img12, it_img13, it_img14, it_img15, it_img16, it_img17, it_img18, it_img19, it_img20

, it_1_subj, it_2_subj, it_3_subj, it_4_subj, it_5_subj, it_6_subj, it_7_subj, it_8_subj, it_9_subj, it_10_subj, it_11_subj, it_12_subj, it_13_subj, it_14_subj, it_15_subj, it_1, it_2, it_3, it_4, it_5, it_6, it_7, it_8, it_9
, it_10  -- IN 업체ID
, it_partner_nick  -- IN 업체명
, it_11, it_12, it_13, it_14, it_15, it_16, it_17, it_18, it_19, it_20, it_21, item_type, al_chk
)
SELECT  
ca_car_id, ca_id, ca_id2, ca_id3
-- , ca_storage_id -- 차후 선택하도록 유도
, it_skin, it_mobile_skin, it_name, it_seo_title, part_number, it_maker, it_origin, it_brand, it_model, it_option_subject, it_supply_subject, it_type1, it_type2, it_type3, it_type4, it_type5, it_basic
, it_explan -- 사진경로 수정
, it_explan2, it_mobile_explan, it_cust_price, it_price, it_price_sea, it_price_cost, it_price_supply, it_point, it_point_type, it_supply_point, it_notax, it_sell_email
, it_use  -- 0
, it_nocoupon  -- 0
, it_soldout  -- 0
, $it_stock_qty  -- 납품수량
, it_stock_sms  -- 0
, it_noti_qty, it_sc_type, it_sc_method, it_sc_price, it_sc_minimum, it_sc_qty, it_buy_min_qty, it_buy_max_qty, it_head_html, it_tail_html, it_mobile_head_html, it_mobile_tail_html, it_hit
, it_ip, it_order, it_tel_inq, it_info_gubun, it_info_value, it_sum_qty, it_use_cnt, it_use_avg, it_shop_memo, ec_mall_pid, it_img_mis

-- 신규 _midx 생성
, 0   -- 나중에 해당 부품id 페이지 조회/수정 페이지 열면 자동으로 생성됨.

-- 사진경로 수정
, it_img1, it_img2, it_img3, it_img4, it_img5, it_img6, it_img7, it_img8, it_img9, it_img10, it_img11, it_img12, it_img13, it_img14, it_img15, it_img16, it_img17, it_img18, it_img19, it_img20

, it_1_subj, it_2_subj, it_3_subj, it_4_subj, it_5_subj, it_6_subj, it_7_subj, it_8_subj, it_9_subj, it_10_subj, it_11_subj, it_12_subj, it_13_subj, it_14_subj, it_15_subj, it_1, it_2, it_3, it_4, it_5, it_6, it_7, it_8, it_9
, '$it_10'  -- IN 업체ID
, '$it_partner_nick'  -- IN 업체명
, it_11, it_12, it_13, it_14, it_15, it_16, it_17, it_18, it_19, it_20, it_21, item_type, al_chk
from g5_shop_item where it_id='$it_id';
";

execSql($sql);
			$rr = allreturnSql("select it_id, TIMESTAMPDIFF(SECOND, it_time, NOW()) AS elapsed_seconds from g5_shop_item order by it_id desc limit 1;");
			$it_id_new = $rr[0]['it_id'];
			$elapsed_seconds = (int)$rr[0]['elapsed_seconds'];

			if($elapsed_seconds>10) {
				exit('오류발생: 신규 부품ID 생성에 실패했습니다. 관리자에게 문의하세요!');
			}


// 기본 설정 (이미 정의되어 있다고 가정)
// $it_id = '1766995558';
// $it_id_new = '새로운ID';
// $base_root = '/your/server/path';

		$sql = "SELECT it_img1, it_img2, it_img3, it_img4, it_img5, it_img6, it_img7, it_img8, it_img9, it_img10, 
               it_img11, it_img12, it_img13, it_img14, it_img15, it_img16, it_img17, it_img18, it_img19, it_img20 
        FROM g5_shop_item 
        WHERE it_id = '$it_id'";
		$row = allreturnSql($sql)[0];

    for ($i = 1; $i <= 20; $i++) {
        $img_field = "it_img" . $i;
        $img_path = $row[$img_field];

        if ($img_path) {
            // 2. 소스 물리 경로 설정
            $source = "$base_root/data/item/$img_path";

            // 3. 목적지 경로 생성 ($it_id 부분을 $it_id_new로 변경)
            // DB 결과값이 '$it_id/파일명' 형태이므로 문자열 치환 필요
            $new_img_path = str_replace($it_id, $it_id_new, $img_path);
            $destination = "$base_root/data/item/$new_img_path";

            // 4. 목적지 디렉토리가 없다면 생성 (권한 주의)
            $dest_dir = dirname($destination);
            if (!is_dir($dest_dir)) {
                @mkdir($dest_dir, 0755, true);
            }

            // 5. 파일 복사 실행
            if (file_exists($source)) {
                copy($source, $destination);
                echo "Success: it_img{$i} 복사 완료.<br>";
            } else {
                echo "Notice: it_img{$i} 원본 파일이 존재하지 않습니다.<br>";
            }
        }
    }




$sql = "
UPDATE g5_shop_item
SET 
    it_explan = REPLACE(it_explan, '$it_id', '$it_id_new'),
    it_img1 = REPLACE(it_img1, '$it_id', '$it_id_new'),
    it_img2 = REPLACE(it_img2, '$it_id', '$it_id_new'),
    it_img3 = REPLACE(it_img3, '$it_id', '$it_id_new'),
    it_img4 = REPLACE(it_img4, '$it_id', '$it_id_new'),
    it_img5 = REPLACE(it_img5, '$it_id', '$it_id_new'),
    it_img6 = REPLACE(it_img6, '$it_id', '$it_id_new'),
    it_img7 = REPLACE(it_img7, '$it_id', '$it_id_new'),
    it_img8 = REPLACE(it_img8, '$it_id', '$it_id_new'),
    it_img9 = REPLACE(it_img9, '$it_id', '$it_id_new'),
    it_img10 = REPLACE(it_img10, '$it_id', '$it_id_new'),
    it_img11 = REPLACE(it_img11, '$it_id', '$it_id_new'),
    it_img12 = REPLACE(it_img12, '$it_id', '$it_id_new'),
    it_img13 = REPLACE(it_img13, '$it_id', '$it_id_new'),
    it_img14 = REPLACE(it_img14, '$it_id', '$it_id_new'),
    it_img15 = REPLACE(it_img15, '$it_id', '$it_id_new'),
    it_img16 = REPLACE(it_img16, '$it_id', '$it_id_new'),
    it_img17 = REPLACE(it_img17, '$it_id', '$it_id_new'),
    it_img18 = REPLACE(it_img18, '$it_id', '$it_id_new'),
    it_img19 = REPLACE(it_img19, '$it_id', '$it_id_new'),
    it_img20 = REPLACE(it_img20, '$it_id', '$it_id_new')
WHERE it_id = '$it_id_new';

update g5_shop_item set it_stock_qty=it_stock_qty-$deliv_qty where it_id='$it_id';

update g5_shop_원장 set it_id_new=$it_id_new, isEnd='Y' where idx in ('$p_idx','$link_idx');

";

			execSql($sql);
			

			//exit('완료되었습니다.');

			//1. g5_shop_item 의 it_id 기준으로 복사하기 (심화때는 존재여부까지 따지기)
			//1-1 테이블에서 복사
						
			//1-2. 이미지 복사
						
			//2. 재고 변경
						
			//3. 원장테이블 완료처리
						
			//4. 페이지 새로고침
						
						
		} else if(Right($status,4)=='승인거부') {
			
			exit('취소 로직발동');
		} else {
			
			exit('다시 확인');
		}
		
    }

}
//end addLogic_treat

?>