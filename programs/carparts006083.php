<?php

function misMenuList0_change() {
	/*
    global $data, $allFilter, $actionFlag, $real_pid, $list_numbering;

	//아래 주석을 풀면 분석에 도움이 됩니다.
	//print_r($data[0]); 

	//$list_numbering = 'Y';    //모든프로그램에 대해 리스트에서 번호가 보이게하려면 _mis_uniqueInfo/top_addLogic.php 파일에서 진행하세요.
								//해당프로그램에서 번호를 보이게 하려면 'Y' 숨기려면 'N' 을 넣으세요.

	//목록에서 특정프로그램에 대해 AddURL 를 변경합니다.
    if($actionFlag=='list') {
        if($real_pid=='sdmoters001185') $data[0]["AddURL"] = '&allFilter=[{"operator":"eq","value":"수량","field":"toolbar_gubun"},{"operator":"eq","value":"' . Date('Y-m') . '","field":"toolbar_woldo"}]&ChkOnlySum=1';
        else if($real_pid=='sdmoters001186') $data[0]["AddURL"] = '&allFilter=[{"operator":"eq","value":"금액","field":"toolbar_gubun"},{"operator":"eq","value":"' . Date('Y-m') . '","field":"toolbar_woldo"}]&ChkOnlySum=1';
        else $data[0]["AddURL"] = '&allFilter=[{"operator":"eq","value":"' . Date('Y-m') . '","field":"toolbar_woldo"}]&ChkOnlySum=1';
    }

	//아래는 특정 2개 프로그램에 대해 BodyType 을 기본형으로 바꿔줍니다.
    if($real_pid=="sdmoters001179" || $real_pid=="sdmoters001180") {
        $data[0]["BodyType"] = "default";
    }
    */
}
//end misMenuList0_change



function misMenuList_change() {

	//misMenuList 테이블에 의한 설정값인 $result 를 바꾸는게 이 함수의 핵심기능
    global $actionFlag, $gubun, $parent_idx, $real_pid, $logicPid, $result, $misSessionUserId, $MisSession_StationName;
	global $misSessionPositionCode, $flag, $list_check_hidden, $orderby;

	if($real_pid=='carparts006086' || $real_pid=='carparts006096') {
		$result[0]['g09'] = "and table_name.it_use=1 and table_name.ca_id<>'' and table_name.ca_id<>'4007' and table_name.it_stock_qty>0";
	} else if($real_pid=='carparts006084') {
		$result[0]['g09'] = "and not (table_name.it_use=1 and table_name.ca_id<>'' and table_name.ca_id<>'4007') and table_name.part_union_level<>3";
		
	} else if($real_pid=='carparts006094') {
		$result[0]['g09'] = "and not (table_name.it_use=1 and table_name.ca_id<>'' and table_name.ca_id<>'4007') ";
		
	} else if($real_pid=='carparts006123') {
		$result[0]['g09'] = "and not (table_name.it_use=1 and table_name.ca_id<>'' and table_name.ca_id<>'4007')  and ic.select_site_id is null and table_name.part_union_level<>3";
	} else if($real_pid=='carparts006124') {
		$result[0]['g09'] = "and not (table_name.it_use=1 and table_name.ca_id<>'' and table_name.ca_id<>'4007')  and ifnull(ic.select_site_id,'')<>''";
	} else if($real_pid=='carparts006085' || $real_pid=='carparts006095') {
		$result[0]['g09'] = "and table_name.it_use=1 and table_name.ca_id<>'' and table_name.ca_id<>'4007' and table_name.it_stock_qty=0";
	} else if($real_pid=='carparts006129') {
		$result[0]['g09'] = "and not (table_name.it_use=1 and table_name.ca_id<>'' and table_name.ca_id<>'4007') and table_name.part_union_level=3";
	}

	if($real_pid=='carparts006074' || $real_pid=='carparts006094' || $real_pid=='carparts006095' || $real_pid=='carparts006096') {
		$temp1 = $MisSession_StationName;
		if($temp1=='gadmin') {
			$temp1 = 'admin';
		}


		//협력사로만 검색한다.
		$result[0]['g09'] = $result[0]['g09'] . " and table_name.it_10='$temp1'";


		//창고를 제한한다.
		$sql = "SELECT fixgubun FROM parts_storage WHERE upidx = 1 and storage_name='$temp1';";
		$fixgubun = onlyOnereturnSql($sql);
		if($fixgubun!='') {
			$search_index = array_search("ca_storage_id", array_column($result, 'aliasName'));
			$result[$search_index]["Grid_PrimeKey"] = "full_name#v_parts_storage_tree#g4name!autogubun#fixgubun#@outer_tbname.fixgubun like '$fixgubun%'";

		}

	}

	if($real_pid=='carparts006106') {
		$search_index = array_search("it_name", array_column($result, 'aliasName'));
		$result[$search_index]["Grid_Columns_Width"] = "20";
	} else if($real_pid=='carparts006111' || $real_pid=='carparts006112') {		//이베이
/*
		$search_index = array_search("it_name_ebay", array_column($result, 'aliasName'));
		$result[$search_index]["Grid_FormGroup"] = "";
		$result[$search_index+1]["Grid_FormGroup"] = "";
		$result[$search_index+2]["Grid_FormGroup"] = "";
		$result[$search_index+3]["Grid_FormGroup"] = "";
		$result[$search_index+4]["Grid_FormGroup"] = "";
		$result[$search_index+5]["Grid_FormGroup"] = "";
		$result[$search_index+6]["Grid_FormGroup"] = "";
		$result[$search_index]["Grid_Columns_Width"] = "20";
		$result[$search_index+1]["Grid_Columns_Width"] = "20";
*/
	}
	if(InStr(requestVB('orderby'),'part_union_level')>0) {
		$search_index = array_search("table_ca_car_idQnfull_name", array_column($result, 'aliasName'));
		$result[$search_index]["Grid_Columns_Width"] = "0";
		$search_index = array_search("qq_malls_YN", array_column($result, 'aliasName'));
		$result[$search_index]["Grid_Columns_Width"] = "0";
		$search_index = array_search("assy_define_idxQnsort_num", array_column($result, 'aliasName'));
		$result[$search_index]["Grid_Columns_Width"] = "10";

		$search_index = array_search("assy_define_idxQnsort_num", array_column($result, 'aliasName'));
		$result[$search_index]["Grid_Columns_Width"] = "10";
		//$search_index = array_search("assy_define_idx", array_column($result, 'aliasName'));
		//$result[$search_index]["Grid_Columns_Width"] = "10";
	} else {
		$search_index = array_search("qq_assy_img", array_column($result, 'aliasName'));
		$result[$search_index]["Grid_Columns_Width"] = "-1";

	}


	//일괄변경
	if($real_pid=='carparts006121') {
		$search_index = array_search("table_ca_storage_id1Qnfull_name", array_column($result, 'aliasName'));
		$result[$search_index]["Grid_IsHandle"] = "s";
		$result[$search_index+2]["Grid_IsHandle"] = "s";
		$search_index = array_search("table_ca_storage_id2Qnfull_name", array_column($result, 'aliasName'));
		$result[$search_index]["Grid_IsHandle"] = "s";
		$result[$search_index+2]["Grid_IsHandle"] = "s";

		$search_index = array_search("table_ca_car_idQnfull_name", array_column($result, 'aliasName'));
		$result[$search_index]["Grid_IsHandle"] = "s";
		$result[$search_index+2]["Grid_IsHandle"] = "s";
		$search_index = array_search("table_ca_idQnfull_name", array_column($result, 'aliasName'));
		$result[$search_index]["Grid_IsHandle"] = "s";
		$result[$search_index+2]["Grid_IsHandle"] = "s";
		$search_index = array_search("table_ca_storage_idQnfull_name", array_column($result, 'aliasName'));
		$result[$search_index]["Grid_IsHandle"] = "s";
		$result[$search_index+2]["Grid_IsHandle"] = "s";
		$search_index = array_search("qq_into_it_10", array_column($result, 'aliasName'));
		$result[$search_index]["Grid_IsHandle"] = "s";
		$search_index = array_search("qq_into_it_stock_qty", array_column($result, 'aliasName'));
		$result[$search_index]["Grid_IsHandle"] = "t";
		$search_index = array_search("qq_into_it_price", array_column($result, 'aliasName'));
		$result[$search_index]["Grid_IsHandle"] = "t";



	}

}
//end misMenuList_change



/**
 * 통합검색 필드는 v7 표준 필드로 재구성됨 (mis_menu_fields):
 *   alias_name='qq_unified_search', db_table='ms', db_field='search_text',
 *   group_compute='mis_g5_shop_item_search ms ON ms.it_id=table_m.it_id',
 *   col_width=-1 (그리드 숨김), grid_is_handle='t' (toolbar 텍스트 검색)
 *
 * → framework 가 자동으로 LEFT JOIN + WHERE ms.search_text LIKE '%kw%' 생성.
 *   별도 before_query 가로채기 불필요. 인덱스 갱신은 save_*After / 5분 cron / 수동 버튼.
 */



function pageLoad() {

    global $actionFlag, $real_pid, $parent_idx, $idx;
	global $misSessionUserId, $misSessionIsAdmin, $parent_real_pid, $MisSession_StationName;

	// ── v7: '+ 등록' 버튼 → 즉시 등록 후 수정모드 진입 ──
	// 기본 +등록(빈 폼) 숨기고, 동일 라벨로 커스텀 버튼 노출.
	// 클릭 시 list reload 가 발생하며 list_json_init 의 instantWrite 핸들러가 수행됨.
	$GLOBALS['_client_css']     = (isset($GLOBALS['_client_css']) ? $GLOBALS['_client_css'] : '')
	                            . ' #mis-btn-write { display: none !important; }';
	$GLOBALS['_client_buttons'] = [
		['label' => '+ 등록', 'action' => 'instantWrite'],
	];
	// 검색인덱스 갱신은 gadmin + 개발자그룹(group_idx=83) 만 노출 — 일반 사용자는 5분 cron 으로 자동 갱신됨
	if ($misSessionUserId === 'gadmin' || ($GLOBALS['misSessionIsDev'] ?? '') === 'Y') {
		$GLOBALS['_client_buttons'][] = ['label' => '🔄 검색인덱스 갱신', 'action' => 'refreshSearchIndex'];
	}

	// '저장후 새로입력' 버튼 노출 (write/modify 양쪽)
	$GLOBALS['_client_saveAndNew'] = true;

	//특정상황에서 페이지를 이동시키는 예제입니다.



        ?>

<style>
	
	
#toolbar span.k-widget.k-dropdown {
    min-width: 200px;
}
#toolbar span.k-widget.k-dropdown.k-textbox {
    min-width: auto;
}
#toolbar span.k-widget.k-dropdown[aria-controls*="Qnis"] {
    min-width: auto;
}
	
	
/* div#toolbar 안에 있으면서 id에 'toolbar_qq_into'가 포함된 label */
div#toolbar label[for*="qq_into"] {
    margin-left: 20px;
    background: black;
    color: yellow;
}
div#toolbar span.k-widget.k-dropdown.k-textbox[aria-controls*="qq_into"] {
	display: none;	
}
	
	
	
.before_round_virtual_fieldQnprint_request,div#round_virtual_fieldQnprint_request,div#round_print_request_time,div#round_print_response_time {
		display:none;
	}

<?php if($MisSession_StationName=='admin') { ?>
body[userid="<?php echo $misSessionUserId; ?>"][actionflag="modify"] .before_round_virtual_fieldQnprint_request
,body[userid="<?php echo $misSessionUserId; ?>"][actionflag="modify"] div#round_virtual_fieldQnprint_request
,body[userid="<?php echo $misSessionUserId; ?>"][actionflag="modify"] div#round_print_request_time
,body[userid="<?php echo $misSessionUserId; ?>"][actionflag="modify"] div#round_print_response_time
{
		display:inline-block!important;
	}
<?php } ?>

	
	
a#btn_refWrite {
    display: none;
}	
a#btn_write {
    display: none;
}
body[realpid="carparts006083"] a#btn_write
,body[realpid="carparts006074"] a#btn_write
{
    display: inline-block;
}
body[actionflag="write"] div[tabnumber="1"] div.form-group.row {
	display:none;	
}
body[actionflag="write"] div[tabnumber="1"] div#round_it_name
, body[actionflag="write"] div[tabnumber="1"] div#round_part_number 
, body[actionflag="write"] div[tabnumber="1"] div#round_it_10 
	{
	display:inline-block!important;	
}
	
/* 필요할 경우, 해당프로그램에 추가할 css 를 넣으세요 */
/* 1. 번호 초기화: 부모 요소(ul)에서 카운터 이름을 정의합니다. */
ul.k-upload-files {
    counter-reset: my-counter; 
    list-style: none; /* 기본 점(bullet)을 제거합니다. */
    padding-left: 20px;
}

/* 2. 번호 증가 및 출력: 자식 요소(li)의 :before에서 카운터를 올리고 출력합니다. */
ul.k-upload-files li {
    counter-increment: my-counter; /* 숫자를 1씩 증가시킵니다. */
    margin-bottom: 10px;
    position: relative;
}

ul.k-upload-files li:before {
    /* 카운터 값을 가져와서 출력합니다. 뒤에 마침표(.)나 괄호 등 원하는 문자를 넣을 수 있습니다. */
    content: counter(my-counter) ". "; 
    
    /* 디자인 속성: 여기서 번호의 색상, 굵기 등을 마음껏 바꿀 수 있습니다. */
    color: #ff5722; 
    font-weight: bold;
    margin-right: 8px;
}




body[parentismainframe="N"][ actionflag="list"] .themechooser 
,body[parentismainframe="N"][ actionflag="list"] tr.k-filter-row
{
    display: none;
}



@media (min-width: 992px) {
	li.k-file.k-file-success {
		width: calc(50% - 30px);
		display: inline-flex;
	}
}




</style>

        <script>
			
function execForm_resize() {
	setTimeout( function() {
		if ($('div#toast-container [onmouseover]').length) {
			$('div#toast-container [onmouseover]').trigger('mouseover');
			cnt = $('#grid').data('kendoGrid').dataSource._total;
			$('span#search_cnt').text(cnt);

		}
	},1000);
}
function exec_bat() {
	cnt = $('#grid').data('kendoGrid').dataSource._total;
	if(cnt==0) {
		alert('변경할 내역이 없습니다.');
		return false;
	}
	if(cnt>50) {
		alert('50건 이하만 처리가능합니다.');
		return false;
	}
	msg = $('div#toast-container [onmouseover]')[0].innerText;
	검색된조건 = msg.split('1. 검')[1].split('2. 변')[0];
	변경할내용 = msg.split('2. 변')[1].split('3. 업')[0];
	if(InStr(변경할내용,'재고수량')>0 && InStr(검색된조건,'재고수량')==0) {
		alert('재고수량을 변경하시려면, 검색된조건에 재고수량 범위가 지정되어야 합니다.');
		return false;
	}
	if(InStr(변경할내용,'판매가격')>0 && InStr(검색된조건,'판매가격')==0) {
		alert('판매가격을 변경하시려면, 검색된조건에 판매가격 범위가 지정되어야 합니다.');
		return false;
	}


	if(!confirm('해당내역 '+cnt+'건에 대해 일괄변경을 실행할까요?')) {
		return false;
	}
	$("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = msg;
	$("#grid").data("kendoGrid").dataSource.read();
	$("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "";
}
function wide_success(p_this) {
	
	$(p_this).closest('.toast-list.toast-success').css('min-width','500px');
}
	
async function getProductId(sellerProductId) {
    const response = await fetch(`/_coupang/coupang_get_product_id.php?id=${sellerProductId}`);
    const data = await response.json();
    return data.productId || null;
}	
			
if(location.href.split('parent_idx').length==2) {
	if(parent.result['assy_yn']=='Y') {
		url = `index.php?gubun=`+parent.document.getElementById('gubun').value+`&allFilter=[{"operator":"contains","value":"=`+parent.result['parts_union']+`","field":"parts_union"},{"operator":"contains","value":"=3","field":"part_union_level"}]&orderby=part_union_level,assy_define_idxQnsort_num&recently=N&isAddURL=Y`;
		location.href = url;
	}
}

if(InStr(location.href,'part_union_level')>0 && isMainFrame()==false) {
	$('body').attr('onlylist','');
}
			
//아래 한줄의 주석문을 풀면 리스트 상에서 내용조회나 수정으로 접근할 수 없습니다.
//$('body').attr('onlylist','');			
//아래 한줄의 주석문을 풀면 리스트 상에서 목록1개만 로딩되어도 자동내용열림을 방지할 수 있습니다.
$('body').attr('auto_open_refuse','');	
			
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
			
function open_qq_equal_parts_cnt(p_this) {
	
	part_number_clean = $(p_this).attr('part_number_clean');
	url = 'index.php?gubun='+document.getElementById('gubun').value+'&allFilter=[{"operator":"contains","value":"'
		+ part_number_clean + '","field":"part_number_clean"}]&isMenuIn=Y';
	window.open(url);
	
}
function open_parts_union(p_this) {
	
	parts_union = $(p_this).attr('parts_union');
	url = 'index.php?gubun=6083&isMenuIn=Y&allFilter=[{"operator":"contains","value":"'+parts_union+'","field":"parts_union"}]&orderby=part_union_level&isAddURL=Y';
	window.open(url);
	
}

async function cp_mall_open(vendorId) {
	const productId = await getProductId(vendorId);
	if (productId) {
		window.open(`https://www.coupang.com/vp/products/${productId}`, '_blank');
	} else {
		alert('아직 임시저장상태입니다.');
	}
}
			
function columns_templete(p_dataItem, p_aliasName) {

    if(p_aliasName=='qq_whanRate') {
		var rValue = p_dataItem[p_aliasName];
		rValue = '1달러당 '+rValue+'원';
        return rValue;
    } else if(p_aliasName=='it_use_coupang') {
		var rValue = p_dataItem[p_aliasName]+` <a class="k-button" href="https://xn--or3b27p5mi.com/shop/item.php?it_id=`+p_dataItem['it_id']+`" target=_blank>부자톡</a>
			`;

		if(document.getElementById('ActionFlag').value=='modify') {
			rValue = rValue+` <a class="k-button" href="/_coupang/coupang_item_sync.php?it_id=`+p_dataItem['it_id']+`" target=_blank>쿠팡.임시저장</a>
			<a class="k-button" href="/_coupang/coupang_item_sync.php?it_id=`+p_dataItem['it_id']+`&mode=approve" target=_blank>쿠팡.판매요청</a>
			`;
			if(p_dataItem['cp_rec_id']!='' && p_dataItem['cp_rec_id']!=null) {
				rValue = rValue+` 
<a class="k-button" href="/_coupang/coupang_delete.php?id=`+p_dataItem['it_id']+`"
onclick="if(!confirm('쿠팡에 연동 중인 해당 상품을 쿠팡에서 삭제하시겠습니까?')) return false;" target=_blank>쿠팡삭제</a>
<a class="k-button" href="javascript:;" onclick="cp_mall_open('`+p_dataItem['cp_rec_id']+`');">쿠팡열기</a>
<a class="k-button" href="https://wing.coupang.com/tenants/seller-web/vendor-inventory/modify?vendorInventoryId=`+p_dataItem['cp_rec_id']+`" target=_blank>쿠팡수정열기</a>
				`;
			}
		} 
        return rValue;
    } else if(p_aliasName=='it_use_ebay') {
		var rValue = p_dataItem[p_aliasName];
		if(document.getElementById('ActionFlag').value=='modify') {
			rValue = rValue+` <a class="k-button" href="/_ebay/ebay_item_sync.php?it_id=`+p_dataItem['it_id']+`" target=_blank>ebay로 전송</a>
			`;
			if(p_dataItem['eb_rec_id']!='' && p_dataItem['eb_rec_id']!=null) {
				rValue = rValue+` 
<a class="k-button" href="/_ebay/ebay_item_sync.php?it_id=`+p_dataItem['it_id']+`&mode=del"
onclick="if(!confirm('이베이에 연동 중인 해당 상품을 이베이에서 삭제하시겠습니까?')) return false;" target=_blank>ebay삭제</a>
<a class="k-button" href="https://www.ebay.com/itm/`+p_dataItem['eb_rec_id']+`" target=_blank>ebay열기</a>
				`;
			}
		} 
        return rValue;
    } else if(p_aliasName=='part_union_level') {
		
    } else if(p_aliasName=='it_name') {
		var rValue = p_dataItem[p_aliasName];
		if(document.getElementById('ActionFlag').value=='list') {
			rValue = rValue+`<br><a class="k-button" href="https://xn--or3b27p5mi.com/shop/item.php?it_id=`+p_dataItem['it_id']+`" target=_blank>프론트</a>`

			if($('body').attr('toprealpid')=='kim000865') {

				rValue = rValue+`<a class="k-button" href="https://xn--or3b27p5mi.com/adm/shop_admin/itemform.php?w=u&it_id=`+p_dataItem['it_id']+`" target=_blank>adm</a><br>`;



				if(document.getElementById('RealPid').value=='carparts006123' || document.getElementById('RealPid').value=='carparts006124') {
					if(p_dataItem['icQnselect_site_id']==null) {
						rValue = rValue+`<a class="k-button" onclick="window.open('index.php?gubun=6118&idx=`+p_dataItem['it_id']+`&ActionFlag=modify&tabid=carparts006119&isMenuIn=Y'); $('#grid').data('kendoGrid').dataSource.read();" target=_blank>검수생성</a>`;
					} else if(p_dataItem['icQnselect_site_id']=='') {
						
						if(p_dataItem['icQnsteps']=='미완료' || p_dataItem['icQnsteps']=='기각') {
							
							rValue = rValue+`<a class="k-button" href="index.php?gubun=6118&idx=`+p_dataItem['it_id']+`&ActionFlag=modify&tabid=carparts006119&isMenuIn=Y" target=_blank>인네</a>`;
						} else {
							rValue = rValue+`<a class="k-button" href="index.php?gubun=6125&idx=`+p_dataItem['it_id']+`&ActionFlag=modify&tabid=carparts006119&isMenuIn=Y" target=_blank>`+p_dataItem['icQnsteps']+`</a>`;
						}
					} else {
						rValue = rValue+`
		<a class="k-button" href="index.php?gubun=6118&idx=`+p_dataItem['it_id']+`&ActionFlag=modify&tabid=carparts006119&isMenuIn=Y" target=_blank>검수 완료</a>
						`;
					}
				}



					if(p_dataItem['icQnselect_site_id']==null) {
					} else if(p_dataItem['icQnselect_site_id']=='') {
						
						if(p_dataItem['icQnsteps']=='미완료' || p_dataItem['icQnsteps']=='기각') {
							
						} else {
						}
					} else {
						rValue = rValue+`
		<a class="k-button" href="`+p_dataItem['icQnselect_url']+`" target=_blank>참고몰</a>
		<a class="k-button" href="`+p_dataItem['icQnselect_url_ebay']+`" target=_blank>참고ebay</a>
						`;
					}




			}

			
		} 
		if(p_dataItem['eb_rec_id']!='' && p_dataItem['eb_rec_id']!=null) {
			rValue = rValue+` <a class="k-button" href="https://www.ebay.com/itm/`+p_dataItem['eb_rec_id']+`" target=_blank>ebay</a>
`;
		}
        return rValue;
    } else if(p_aliasName=='part_number_clean' || p_aliasName=='part_number') {
		var part_union_level = p_dataItem['part_union_level'];
		var rValue = p_dataItem[p_aliasName]
		if(part_union_level=='2') {
			part_union_level_name = '조립품';
		} else if(part_union_level=='3') {
			part_union_level_name = '조립부품';
		} else {
			part_union_level_name = '완성품';
		}
		return rValue + `<br><span class="part_union_level_name">`+part_union_level_name+`</span>`;
		/*
		if(p_dataItem['parts_count']>1) {
			rValue = rValue+`<br><a class="k-button" onclick="open_parts_union(this);"
title="같은 제조사와 같은 부품카테고리 2단계가 일치하는 갯수"
			parts_union="`+p_dataItem['parts_union']+`">결합부품`+p_dataItem['parts_count']+'개</a>';
		} 
		*/
        return rValue;
    } else if(p_aliasName=='table_mQmit_img1') {
		var rValue = '/data/item/'+p_dataItem[p_aliasName];
		if(rValue!='' && rValue!=null) {
			rValue = '<img src="thumbnail.php?'+rValue+'" style="max-height:100px;"/>';
		} 
        return rValue;
    } else if(p_aliasName=='qq_assy_img') {
		var rValue = p_dataItem[p_aliasName];
		if(rValue!='' && rValue!=null) {
			rValue = '<img src="'+rValue+'" style="max-height:100px;"/>';
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
//해당프로그램의 관리자 권한을 가진 사용자는 $misSessionIsAdmin=='Y' 입니다. 필요 시, 여러 부분에서 해당 조건을 넣어 사용하세요.
//if($misSessionIsAdmin=='Y' && $actionFlag=='list') { 
			
//}
?>
//사용자 정의버튼 생성 예제입니다.
//툴바 명령버튼에 btn_1 이라고 하는 예비버튼을 "적용" 이라는 기능을 넣고, 클릭 시, 그리드에 app="적용" 이라는 신호로 보냅니다.
//이때 app=="적용" 에 대한 처리는 개발Tip 의 list_json_init() 를 참조하세요.
function thisLogic_toolbar() {
	if(document.getElementById('RealPid').value=='carparts006121') {
		$("a#btn_1").text("일괄변경 적용 전 검증");
		$("#btn_1").css("background", "#88f");
		$("#btn_1").css("color", "#fff");
		<?php if($misSessionIsAdmin=='Y') { ?>
		$("#btn_1").click( function() {
			$("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "검증";
			$("#grid").data("kendoGrid").dataSource.read();
			$("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "";
		});
		<?php } else { ?>
		$("#btn_1").click( function() {
			alert('특정관리자 전용기능입니다.');
		});
		<?php } ?>
	}

}

//목록에서 grid 로드 후 한번만 실행됨, 이때 처리해야할 일반 스크립트를 삽입합니다.
function listLogic_afterLoad_once()	{
	//grid_remove_sort();    //그리드의 상단 정렬 기능 제거를 원할 경우.
	

	
}
			
//목록에서 grid 로드 후 데이터 로딩마다 실행됨, 이때 처리해야할 일반 스크립트를 삽입합니다.		
function listLogic_afterLoad_continue()	{
			if(InStr(status_url(),'toolbar_assy_yn')>0) {
				if(parent.$('li[tabalias="virtual_fieldQnisAssy"] span.k-link')[0]) {
					cnt = parent.$('li[tabalias="virtual_fieldQnisAssy"] span.k-link span.cnt')[0].innerText;
					parent.$('li[tabalias="virtual_fieldQnisAssy"] span.k-link')[0].innerHTML = 'Assy및부속품<span class="cnt" cnt="'+cnt+'">'+cnt+'</span>';
				}
			}
	
}

function helpboxLogic_afterSelect(p_actionFlag, p_opener, p_sel_alias, p_sel_value) {
	//dropdownlist 에 의한 helplist (popup) 선택 직후 추가 작업이 필요할 경우 사용합니다.
	/*
	if(p_actionFlag=='write' && p_sel_alias=='TUNE_CD') {
		v = p_sel_value;
		//p_opener.$('input#vTUNE_CD')[0].value = v;
	}
	*/
	
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



	$('select#ca_storage_id1').change( function() {
	//1단계

		top1_value = this.value;

		top2_DS = $('select#ca_storage_id2').data('kendoDropDownList').dataSource;
		top2_url = top2_DS.transport.options.read.url;
		top2_url = top2_url.split('&upValue=')[0]+"&upValue='"+top1_value+"'";
		top2_DS.transport.options.read.url = top2_url;
		top2_DS.read();

		top3_DS = $('select#ca_storage_id').data('kendoDropDownList').dataSource;
		top3_url = top3_DS.transport.options.read.url;
		top3_url = top3_url.split('&upValue=')[0]+"&upValue='999999'";
		top3_DS.transport.options.read.url = top3_url;
		top3_DS.read();
		
	});
	$('select#ca_storage_id2').change( function() {
	//2단계
		top2_value = this.value;

		top3_DS = $('select#ca_storage_id').data('kendoDropDownList').dataSource;
		top3_url = top3_DS.transport.options.read.url;
		top3_url = top3_url.split('&upValue=')[0]+"&upValue='"+top2_value+"'";
		top3_DS.transport.options.read.url = top3_url;
		top3_DS.read();
		
	});		


	
function treat_check() {
	
	toastr.warning("", "정상적으로 판매처리되었습니다.", {progressBar: true, timeOut: 3000});
	
}
			
//내용조회 또는 수정/입력 페이지 로딩이 끝나는 순간 한번만 실행됨, 이때 처리해야할 일반 스크립트를 삽입합니다.
function viewLogic_afterLoad() {

	if(document.getElementById('ActionFlag').value=='write') {
		$('a#btn_save').click();
	} else if(document.getElementById('ActionFlag').value=='modify') {
		const diff = Math.floor((new Date() - new Date(result.it_time)) / 1000);
		if(diff<=12) {
			//입력된지 12초 이내이고, 재고가 0이면 1로 변경처리
			if($('input#it_stock_qty').data('kendoNumericTextBox').value()==0) {
				$('input#it_stock_qty').data('kendoNumericTextBox').value(1);
			}
		}
	}


	//Math.floor((new Date() - new Date(result.it_time)) / 1000);

	if(document.getElementById('RealPid').value!="carparts006086" && document.getElementById('RealPid').value!="carparts006096") {
		//$('div.before_round_virtual_fieldQnprice').nextAll().addBack().slice(0, 13).hide();
	}

	
	//각탭에 html 에디터를 통해 pdf 업로드나 pdf 링크가 href 가 1개만 있으면 뷰어로 변신
	//tab_pdf_into_viewer();

	//특정항목에 대한 내용을 바꾸는 코딩
    //$('div#table_mQmidx')[0].innerText = resultAll.d.results[0].table_mQmidx;
	
	//특정 tabid 를 넣으면 해당 탭이 먼저 열린다. 아래는 wdate 를 넣어서 등록정보 탭이 열리는 예제.
	//if($('input#ActionFlag')[0].value=='view') $('li[tabid="viewPrint"]').attr('active_tabid','wdate');
	$('#virtual_fieldQngrade_a,#virtual_fieldQngrade_b,#virtual_fieldQngrade_c,input#virtual_fieldQnprint_request').change( function() {
		$('#btn_save').click();
	});
	
	
	$('input#virtual_fieldQntreat').change( function() {
		
		setTimeout("$('input#virtual_fieldQntreat')[0].checked = false;",1000);
		
		price = $('input#virtual_fieldQnprice').data('kendoNumericTextBox').value();
		qty = $('input#virtual_fieldQnqty').data('kendoNumericTextBox').value();
		send_cost = $('input#virtual_fieldQnsend_cost').data('kendoNumericTextBox').value();
		user_id = $('select#virtual_fieldQnuser_id').data('kendoDropDownList').value();

		od_name = $('input#virtual_fieldQnod_name').val();
		od_email = $('input#virtual_fieldQnod_email').val();
		od_hp = $('input#virtual_fieldQnod_hp').val();
		od_addr2 = $('input#virtual_fieldQnod_addr2').val();
		
		if(price<=0) {
			alert('판매단가를 입력하세요!');
			return false
		} else if(qty<=0) {
			alert('판매수량을 입력하세요!');
			return false
		} else if(send_cost<0 || send_cost==null) {
			alert('배송비를 입력하세요!');
			return false
		} else if(user_id=='' && (od_name=='' || od_hp=='')) {
			alert('거래처(소비자)를 선택하시거나, 비회원구매 정보를 입력하세요!');
			return false
		}
		if(!confirm('판매처리를 진행할까요?')) {
			return false;	
		}
		$('#btn_save').click();


	});

	$('#btn_save').on('click', function(e) {
		if ($('li.k-file.k-file-success').length > 20) {
			alert('파일 업로드는 20개 까지만 가능합니다!');
			
			// 1. 브라우저 기본 동작 차단
			e.preventDefault(); 
			// 2. 다른 이벤트 핸들러로의 전파를 즉시 중단 (매우 중요)
			e.stopImmediatePropagation(); 
			displayLoadingOff();
			return false;
		}
	});





	/*
	$('select#virtual_fieldQnit_10').data('kendoDropDownList').bind("change", function(e) {
		it_10_new = e.sender.value();
		it_10_old = $('select#it_10').data('kendoDropDownList').value();
		if(it_10_new==it_10_old || it_10_new=='' || it_10_new==null || it_10_old=='' || it_10_old==null) {
			e.sender.value('');
			alert('변경할 판매처를 선택하세요!');
			return false;
		}
		if(!confirm('판매처 원장변경을 진행할까요?')) {
			e.sender.value('');
			return false;	
		}
		url = "addLogic_treat.php?RealPid=carparts006083&it_10_new="+it_10_new+'&it_10_old='+it_10_old+'&idx='+document.getElementById('idx').value;
		result = ajax_url_return(url);
		if(result!='OK' && result!='') {
			alert(result);
			e.sender.value('');
			return false;
		} else if(result=='') {
			alert('판매처 원장변경 중 오류가 발생했습니다. 관리자에게 문의하세요!');
			e.sender.value('');
			return false;
		}
		$('a#btn_reload').click();
	});
	*/

		


}		
function viewLogic_afterLoad_continue() {

setTimeout(() => {
		$('[for="it_name"]').after(`<a id="btn_copy_it_name" role="button" 
	onclick="copyStringToClipboard('`+result['it_name']+`');" 
	class="k-button k-button-icontext btn_into_top_img" 
	style="position: absolute;top: 3px;left: 61px;">복사</a>`);

}, 2000);

	if(document.getElementById('ActionFlag').value!='list') {
		//if( result.assy_yn=='Y' ) {
		//	$('li[tabalias="virtual_fieldQnisAssy"]')[0].style.display = 'block';
		//} else {
		//	$('li[tabalias="virtual_fieldQnisAssy"]')[0].style.display = 'none';
		//}
		
		/*
		if($('textarea#ca_id_coupang_txt')[0]) {
				if($('textarea#ca_id_coupang_txt')[0].value=='') {
					$('textarea#ca_id_coupang_txt')[0].value = result['qq_category_meta_json'];
				}
		}
		*/

	}



	if(document.getElementById('ActionFlag').value!='modify') {
		return false;
	}
	//debugger;
	//console.log(resultAll);
	//console.log(resultAll.d.results[0]);
	$('a.btn_into_top_img').remove();
	if($('span.k-file-name-size-wrapper')[0]) {
		$('span.k-file-name-size-wrapper').after(`<a role="button" class="k-button k-button-icontext btn_into_top_img">대표이미지로</a>`);
		$($('a.btn_into_top_img')[0]).addClass('zip_download');
		$($('a.btn_into_top_img')[0]).removeClass('btn_into_top_img');
		$('a.zip_download')[0].innerText = '일괄다운로드';
		$('a.zip_download').css('background','rgb(186, 225, 45)');
		$('a.zip_download').click( function() {
			url = "/_mis_app/downloadItemImages.php?it_id="+document.getElementById('idx').value;
			window.open(url);
		});

		
		$('a.btn_into_top_img').click( function() {
			img_name = $(this).parent().find('img')[0].src.split('/data/')[1].split('?')[0];
			url = "addLogic_treat.php?RealPid=carparts006083&top_img="+img_name+'&idx='+document.getElementById('idx').value;
			temp = ajax_url_return(url);
			setTimeout("$('a#btn_reload').click();",0);
		});

	}
	price = $('input#it_price').data('kendoNumericTextBox').value();
	$('input#virtual_fieldQnprice').data('kendoNumericTextBox').value(price);

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



// 현재 메뉴의 최상단 real_pid 계산 (v6 의 toprealpid). 한 요청 안에서 캐시.
function _carparts006083_top_realpid(): string {
    static $cache = [];
    global $real_pid, $__pdo;
    $start = (string)$real_pid;
    if ($start === '' || !$__pdo) return '';
    if (isset($cache[$start])) return $cache[$start];
    $cur = $start;
    $top = $start;
    for ($i = 0; $i < 10; $i++) {
        try {
            $st = $__pdo->prepare("SELECT up_real_pid FROM mis_menus WHERE real_pid=? LIMIT 1");
            $st->execute([$cur]);
            $up = (string)$st->fetchColumn();
        } catch (\Throwable) { break; }
        if ($up === '' || $up === $cur || $up === 'speedmis000001') break;
        $top = $up;
        $cur = $up;
    }
    return $cache[$start] = $top;
}

// v7 서버측 포팅 — list 셀 + view 폼 양쪽에 동일 버튼 노출 (한 번 코딩)
function row_buttons(&$row, array &$buttons): void {
    global $real_pid, $actionFlag, $gubun;
    $itId = $row['it_id'] ?? '';
    if ($itId === '') return;
    $itIdEsc = urlencode((string)$itId);
    // 현재 사용자가 보고 있는 메뉴 idx — 6083(원본) 일 수도 6112(외부몰 연동) 등 mis_join_pid 로
    // 6083 을 빌려쓰는 다른 메뉴일 수도. mis:reloadView 이벤트 detail.gubun 으로 사용.
    $curGubunJs = (int)$gubun;

    // ── v6 columns_templete 포팅: view/modify 폼 전용 (it_use_coupang / it_use_ebay 라벨 옆 버튼) ──
    if ($actionFlag === 'view' || $actionFlag === 'modify') {
        // it_use_coupang — 항상 부자톡, modify 면 쿠팡 액션들 추가
        $buttons['it_use_coupang'][] = [
            'label'  => '부자톡',
            'url'    => 'https://xn--or3b27p5mi.com/shop/item.php?it_id=' . $itIdEsc,
            'target' => '_blank',
        ];
        if ($actionFlag === 'modify') {
            $buttons['it_use_coupang'][] = [
                'label'  => '쿠팡.임시저장',
                'url'    => '/_coupang/coupang_item_sync.php?it_id=' . $itIdEsc,
                'target' => '_blank',
            ];
            $buttons['it_use_coupang'][] = [
                'label'  => '쿠팡.판매요청',
                'url'    => '/_coupang/coupang_item_sync.php?it_id=' . $itIdEsc . '&mode=approve',
                'target' => '_blank',
            ];
            $cpRec = trim((string)($row['cp_rec_id'] ?? ''));
            if ($cpRec !== '') {
                // cp_rec_id 를 JSON 으로 인코딩 → JS 리터럴로 안전. 그 후 htmlspecialchars 로 HTML-attr 안전.
                $cpJsLit = htmlspecialchars(json_encode($cpRec, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS), ENT_QUOTES, 'UTF-8');
                $cpUrlEsc = urlencode($cpRec);
                // confirm 처리 — raw HTML
                $buttons['it_use_coupang'][] =
                    '<a class="btn-open" href="/_coupang/coupang_delete.php?id=' . $itIdEsc . '"'
                  . ' target="_blank"'
                  . ' onclick="if(!confirm(\'쿠팡에 연동 중인 해당 상품을 쿠팡에서 삭제하시겠습니까?\')) return false;"'
                  . '>쿠팡삭제</a>';
                // 쿠팡열기 — onclick 안에 IIFE 로 직접 임베드 (글로벌 함수 의존 제거).
                // view/modify 응답엔 _client_js 가 안 실리므로 deep-link(idx=...) modify 진입에서도 동작 보장.
                $cpOnclick = '(async()=>{try{const r=await fetch(\'/_coupang/coupang_get_product_id.php?id=\'+encodeURIComponent(' . $cpJsLit . '));const d=await r.json();if(d.productId)window.open(\'https://www.coupang.com/vp/products/\'+d.productId,\'_blank\');else alert(\'아직 임시저장상태입니다.\');}catch(e){alert(\'쿠팡 열기 실패: \'+e.message);}})();return false;';
                $buttons['it_use_coupang'][] =
                    '<a class="btn-open" href="javascript:;"'
                  . ' onclick="' . $cpOnclick . '">쿠팡열기</a>';
                $buttons['it_use_coupang'][] = [
                    'label'  => '쿠팡수정열기',
                    'url'    => 'https://wing.coupang.com/tenants/seller-web/vendor-inventory/modify?vendorInventoryId=' . $cpUrlEsc,
                    'target' => '_blank',
                ];
            }
        }

        // it_use_ebay — modify 모드만
        if ($actionFlag === 'modify') {
            $buttons['it_use_ebay'][] = [
                'label'  => 'ebay로 전송',
                'url'    => '/_ebay/ebay_item_sync.php?it_id=' . $itIdEsc,
                'target' => '_blank',
            ];
            $ebRec = trim((string)($row['eb_rec_id'] ?? ''));
            if ($ebRec !== '') {
                $ebUrlEsc = urlencode($ebRec);
                $buttons['it_use_ebay'][] =
                    '<a class="btn-open" href="/_ebay/ebay_item_sync.php?it_id=' . $itIdEsc . '&mode=del"'
                  . ' target="_blank"'
                  . ' onclick="if(!confirm(\'이베이에 연동 중인 해당 상품을 이베이에서 삭제하시겠습니까?\')) return false;"'
                  . '>ebay삭제</a>';
                $buttons['it_use_ebay'][] = [
                    'label'  => 'ebay열기',
                    'url'    => 'https://www.ebay.com/itm/' . $ebUrlEsc,
                    'target' => '_blank',
                ];
            }
        }

        // it_use_naver — modify 모드만 (쿠팡/이베이와 동형)
        if ($actionFlag === 'modify') {
            $buttons['it_use_naver'][] = [
                'label'  => '네이버 전송',
                'url'    => '/_naver/naver_item_sync.php?it_id=' . $itIdEsc,
                'target' => '_blank',
            ];
            $nvRec = trim((string)($row['ec_mall_pid'] ?? ''));
            if ($nvRec !== '') {
                $nvUrlEsc = urlencode($nvRec);
                $buttons['it_use_naver'][] =
                    '<a class="btn-open" href="/_naver/naver_item_sync.php?it_id=' . $itIdEsc . '&mode=del"'
                  . ' target="_blank"'
                  . ' onclick="if(!confirm(\'네이버에 연동 중인 해당 상품을 네이버에서 삭제하시겠습니까?\')) return false;"'
                  . '>네이버삭제</a>';
                $buttons['it_use_naver'][] = [
                    'label'  => '네이버 스토어열기',
                    'url'    => 'https://smartstore.naver.com/main/products/' . $nvUrlEsc,
                    'target' => '_blank',
                ];
            }
        }

        // 카테고리감지 — 적용일(sync_date) 미설정 시 COU/NAV 카테고리 라벨 옆에 노출
        //  1순위: 같은 ca_id 의 이전 전송성공 사례 최빈값 카테고리
        //  2순위: 같은 ca_id 의 기존 지정값 최빈값
        //  3순위(NAV): mis_naver_category_map prefix-walk
        //  4순위: it_name 키워드 ↔ 카테고리명 LIKE 매칭
        if ($actionFlag === 'modify') {
            $apiUrl = URL_BASE_PATH . '/api.php';
            $mkDetect = function (string $kind) use ($apiUrl, $itIdEsc, $curGubunJs): string {
                // 풀-리로드(location.reload) 는 폼이 닫히면서 리스트로 돌아가는 "지진" 현상 발생.
                // 대신 mis:reloadView 커스텀 이벤트로 DataForm 의 act=view 만 재호출 → 폼 상태/탭 유지.
                // detail.gubun 은 현재 보고있는 메뉴 idx ({$curGubunJs}) — 6083 외에 6112 등에서도 동작.
                // fetch URL 의 gubun=6083 은 program(carparts006083.php) 이 위치한 곳이라 그대로 둠.
                $js = "(async()=>{try{const r=await fetch('{$apiUrl}?act=treat&gubun=6083&idx={$itIdEsc}&action={$kind}');const d=await r.json();const x=d.data||{};if(x._client_alert){alert(x._client_alert);return;}if(x._client_toast)alert(x._client_toast);if(x.reloadView)window.dispatchEvent(new CustomEvent('mis:reloadView',{detail:{gubun:{$curGubunJs},idx:'{$itIdEsc}'}}));}catch(e){alert('카테고리 감지 실패: '+e.message);}})();return false;";
                return '<a class="btn-open" href="javascript:;" onclick="'
                     . htmlspecialchars($js, ENT_QUOTES, 'UTF-8')
                     . '">🔎 카테고리감지</a>';
            };
            $cpSync = trim((string)($row['cp_sync_date'] ?? ''));
            if ($cpSync === '' || $cpSync === '0000-00-00 00:00:00') {
                $buttons['table_ca_id_coupangQnfull_path'][] = $mkDetect('detectCouCategory');
            }
            $nvSync = trim((string)($row['nv_sync_date'] ?? ''));
            if ($nvSync === '' || $nvSync === '0000-00-00 00:00:00') {
                $buttons['table_ca_id_naverQnwhole_name'][] = $mkDetect('detectNavCategory');
            }
        }
    }

    // 줄바꿈 — 셀의 원래 it_name 값 다음 줄부터 버튼들이 표시되도록
    $buttons['it_name'][] = '<br>';

    // 프론트 링크 — 항상 표시
    $buttons['it_name'][] = [
        'label'  => '프론트',
        'url'    => 'https://xn--or3b27p5mi.com/shop/item.php?it_id=' . $itIdEsc,
        'target' => '_blank',
    ];

    // 이하 toprealpid='kim000865' 인 경우에만 노출
    $top = _carparts006083_top_realpid();
    if ($top !== 'kim000865') return;

    // adm 링크 — 관리자 쇼핑몰 편집용
    $buttons['it_name'][] = [
        'label'  => 'adm',
        'url'    => 'https://xn--or3b27p5mi.com/adm/shop_admin/itemform.php?w=u&it_id=' . $itIdEsc,
        'target' => '_blank',
    ];

    // 6123 / 6124 전용: 검수 상태 버튼
    if ($real_pid === 'carparts006123' || $real_pid === 'carparts006124') {
        $siteId = $row['icQnselect_site_id'] ?? null;
        $steps  = (string)($row['icQnsteps'] ?? '');

        // SPA 탭으로 열기 + 2초 후 현재 목록 자동 새로고침 (검수row 자동생성으로 버튼 상태가 바뀌므로)
        $mkTabBtn = function(string $label, int $gubun, int $idxVal, int $reloadMs = 2000): string {
            $detail = json_encode(['gubun' => $gubun, 'idx' => $idxVal, 'label' => $label], JSON_UNESCAPED_UNICODE);
            return '<button class="btn-open" data-opentab=\'' . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . '\''
                 . ' data-reload-after-ms="' . $reloadMs . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</button>';
        };

        $itIdInt = (int)$itId;
        if ($siteId === null) {
            // 검수 row 자체가 없음 → 6118 modify 진입 시 자동 14사이트 생성됨 → 2초 후 reload 로 버튼 상태 갱신
            $buttons['it_name'][] = $mkTabBtn('검수생성', 6118, $itIdInt, 2000);
        } elseif ($siteId === '') {
            // 검수 row 있으나 site 미정
            if ($steps === '미완료' || $steps === '기각') {
                $buttons['it_name'][] = $mkTabBtn('인네', 6118, $itIdInt, 2000);
            } else {
                $label = $steps !== '' ? $steps : '검수';
                $buttons['it_name'][] = $mkTabBtn($label, 6125, $itIdInt, 2000);
            }
        } else {
            // 검수 완료 (site 지정됨)
            $buttons['it_name'][] = $mkTabBtn('검수 완료', 6118, $itIdInt, 2000);
            // 참고 링크들 — 외부 URL 이라 새 브라우저 탭 (target=_blank) 유지
            $refUrl  = (string)($row['icQnselect_url'] ?? '');
            $refEbay = (string)($row['icQnselect_url_ebay'] ?? '');
            if ($refUrl !== '') {
                $buttons['it_name'][] = ['label' => '참고몰', 'url' => $refUrl, 'target' => '_blank'];
            }
            if ($refEbay !== '') {
                $buttons['it_name'][] = ['label' => '참고ebay', 'url' => $refEbay, 'target' => '_blank'];
            }
        }
    }
}

// 대표이미지: 리스트에서 파일명 → 섬네일 IMG 태그
// it_img1 컬럼값은 이미 '<it_id>/<filename>.jpg' 형식 → 추가 it_id prefix 필요 없음.
// rawurlencode 가 슬래시도 인코딩하지만 thumbnail.php 가 rawurldecode 로 풀어 정규식 매칭함.
function list_json_load(&$data) {
    global $actionFlag;
    if ($actionFlag !== 'list') return;

    $img = trim((string)($data['table_mQmit_img1'] ?? ''));
    if ($img !== '') {
        $src = URL_BASE_PATH . '/tools/thumbnail.php?/data/item/' . rawurlencode($img);
        $data['__html']['table_mQmit_img1'] =
            '<img src="' . htmlspecialchars($src, ENT_QUOTES) . '" alt="" loading="lazy" '
          . 'style="max-width:200px;max-height:70px;object-fit:contain;display:block" />';
    }
}

// 대표이미지: 뷰(내용조회) 모드에서만 파일명을 섬네일 IMG 로 치환 (수정 모드에서는 파일명 그대로 편집 가능)
function view_load(&$row) {
    global $actionFlag;
    if ($actionFlag === 'view') {
        $img = trim((string)($row['table_mQmit_img1'] ?? ''));
        if ($img !== '') {
            $src = URL_BASE_PATH . '/tools/thumbnail.php?/data/item/' . rawurlencode($img);
            $row['table_mQmit_img1'] =
                '<img src="' . htmlspecialchars($src, ENT_QUOTES) . '" alt="" loading="lazy" '
              . 'style="max-width:400px;max-height:300px;object-fit:contain;display:block" />';
        }
    }

    // qq_whan_rate — '1달러당 X원' (view/modify 양쪽). 가상 SELECT 필드라 저장 영향 없음.
    if ($actionFlag === 'view' || $actionFlag === 'modify') {
        $rate = trim((string)($row['qq_whan_rate'] ?? ''));
        if ($rate !== '') {
            $row['qq_whan_rate'] = '1달러당 ' . $rate . '원';
        }
    }
}

function list_json_init() {
    global $allFilter, $real_pid, $mis_join_pid, $logicPid, $parent_idx, $full_siteID, $grid_load_once_event, $misSessionUserId;
    global $flag, $selField, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript;
    global $customAction, $__pdo;
	//$flag 는 목록조회시 'read'   내용조회시 'view'    수정시 'modify'   입력시 'write'
	//$selField 는 필터링을 하는 순간 발생하는 필드alias 값.

	// ── 통합검색 인덱스 즉시 갱신 ──
	// 카테고리/창고 이름이 바뀌었을 때 (다른 프로그램에서 수정) 즉시 인덱스를 재구축.
	if ($customAction === 'refreshSearchIndex') {
		try {
			$__pdo->exec('CALL mis_rebuild_g5_shop_item_search_proc()');
			$cnt = (int)$__pdo->query('SELECT COUNT(*) FROM mis_g5_shop_item_search')->fetchColumn();
			$GLOBALS['_client_toast'] = "검색 인덱스 갱신 완료 ({$cnt}건)";
		} catch (\Throwable $e) {
			$GLOBALS['_client_alert'] = '검색 인덱스 갱신 실패: ' . $e->getMessage();
		}
		return;
	}

	// ── '+ 등록' 즉시등록 처리 ──
	// 커스텀 버튼 클릭 시 g5_shop_item 에 빈 행 INSERT → 새 idx 반환 → 클라이언트에서 modify 모드로 즉시 진입
	if ($customAction === 'instantWrite') {
		try {
			// NOT NULL + DEFAULT 없는 컬럼들에 안전한 빈값/0 부여 (auto_increment idx, wdate 는 자동)
			$insertSql = "
				INSERT INTO g5_shop_item (
					ca_id_coupang_txt, it_basic, it_explan, it_explan2, it_mobile_explan,
					it_head_html, it_tail_html, it_mobile_head_html, it_mobile_tail_html,
					it_info_value, it_use_avg, it_shop_memo, it_img_mis,
					it_11_subj, it_12_subj, it_13_subj, it_14_subj, it_15_subj,
					it_11, it_12, it_13, it_14, it_15, it_16, it_17, it_18, it_19, it_20, it_21,
					item_type, al_chk, it_partner_nick, wdater, wdate
				) VALUES (
					'', '', '', '', '',
					'', '', '', '',
					'', 0, '', '',
					'', '', '', '', '',
					'', '', '', '', '', '', '', '', '', '', '',
					0, 0, '', ?, NOW()
				)
			";
			$st = $__pdo->prepare($insertSql);
			$st->execute([(string)$misSessionUserId]);
			$newIdx = (int)$__pdo->lastInsertId();

			if ($newIdx > 0) {
				// mis_menu_fields 의 default_value 적용 — 단순 상수만 (SQL 'select ...', 세션변수 '@...', 템플릿 '{...}' 제외).
				// 복잡한 default_value 는 첨부파일 customPath / 사용자 입력 시점에 별도 처리.
				try {
					$dfStmt = $__pdo->prepare(
						"SELECT db_field, default_value FROM mis_menu_fields
						  WHERE real_pid='carparts006083' AND db_table='table_m'
						    AND useflag='1' AND default_value IS NOT NULL AND default_value <> ''"
					);
					$dfStmt->execute();
					$sets = []; $binds = [];
					foreach ($dfStmt->fetchAll(\PDO::FETCH_ASSOC) as $df) {
						$col = trim((string)$df['db_field']);
						$dv  = (string)$df['default_value'];
						$dvT = ltrim($dv);
						// 특수패턴 스킵: SQL 서브쿼리, 세션변수, 토큰
						if (preg_match('/^select\s/i', $dvT)) continue;
						if (str_contains($dv, '@')) continue;
						if (str_contains($dv, '{')) continue;
						// 컬럼명 안전성 (영숫자/언더스코어만)
						if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $col)) continue;
						$sets[]  = "`{$col}` = ?";
						$binds[] = $dv;
					}
					if (!empty($sets)) {
						$binds[] = $newIdx;
						$__pdo->prepare("UPDATE g5_shop_item SET " . implode(', ', $sets) . " WHERE it_id = ?")
						      ->execute($binds);
					}
				} catch (\Throwable $e) {
					// 디폴트 적용 실패해도 등록 자체는 성공 — 사용자가 폼에서 채우면 됨
					@error_log('instantWrite default_value apply failed: ' . $e->getMessage());
				}

				// 새 행 통합검색 인덱스 즉시 등록 (빈값이지만 it_id 기반 검색 가능 + 첫 저장시 갱신됨)
				try {
					$__pdo->prepare("CALL mis_refresh_g5_shop_item_search_proc(?)")->execute([$newIdx]);
				} catch (\Throwable) { /* 인덱스 실패는 무시 */ }
				// 클라이언트에서 새 행을 modify 모드로 즉시 오픈 (목록 새로고침 포함)
				$GLOBALS['_client_js']    = "window.dispatchEvent(new CustomEvent('mis:openIdxModify', { detail: { idx: " . $newIdx . " } }));";
				$GLOBALS['_client_toast'] = '신규 등록 — 항목을 입력하세요.';
			} else {
				$GLOBALS['_client_alert'] = '등록 실패: 새 idx 를 얻지 못했습니다.';
			}
		} catch (\Throwable $e) {
			$GLOBALS['_client_alert'] = '등록 실패: ' . $e->getMessage();
		}
		return; // 등록 직후 list_json_init 본 로직 스킵 (불필요한 영카트 동기화 등)
	}

	global $temp3;
	if($flag=='read' && $real_pid=='carparts006121') {
		$temp3 = $allFilter;   //원본 $allFilter 를 저장해놓는다.
		if(InStr($app, '@@END_QUERY;')>0) {
			$appSql = splitVB(splitVB($app,'@@START_QUERY;')[1],'@@END_QUERY;')[0];
			execSql($appSql);
			$msg = replace(splitVB($app,'@@START_QUERY;')[0],"'","''");
			$appSql = replace($appSql,"'","''");
			$sql = "
insert into g5_shop_item_update_log (msg, uquery, wdater) values ('$msg','$appSql','$misSessionUserId');
			";
			execSql($sql);
			$resultCode = 'success';
			$resultMessage = '정상적으로 일괄변경이 처리되었습니다.';
        }

		// 2. 배열로 디코딩
		$filterData = json_decode($allFilter, true);

		// 3. entries 내부의 배열만 필터링
		if (isset($filterData['entries']) && is_array($filterData['entries'])) {
			$filterData['entries'] = array_filter($filterData['entries'], function($item) {
				// field 값에 'qq_into'가 포함되어 있지 않은 것만 유지 (true)
				return strpos($item['field'], 'qq_into') === false;
			});

			// 인덱스 번호 재정렬 (0, 1, 2...)
			$filterData['entries'] = array_values($filterData['entries']);
		}

		// 4. 다시 JSON 문자열로 변환
		$allFilter = json_encode($filterData, JSON_UNESCAPED_UNICODE);

	}

	if($flag=='read' && $grid_load_once_event=='N') {
		//환율 동기화
		get_and_update_exchange_rate();
		//계정 동기화
		sync_g5member_to_MisUser();
	}

    if(($flag=='modify' || $flag=='view') && $selField=='') { 
		$sql1 = " 
SELECT it_img_mis_midx
FROM g5_shop_item  where it_id='$idx' 
		";
		$it_img_mis_midx = onlyOnereturnSql($sql1);
		$sql1 = " 
SELECT replace(replace(replace(replace(replace(replace(CONCAT(';',it_img1,';',it_img2,';',it_img3,';',it_img4,';',it_img5,';',it_img6,';',it_img7,';',it_img8,';',it_img9,';',it_img10,';',it_img11,';',it_img12,';',it_img13,';',it_img14,';',it_img15,';',it_img16,';',it_img17,';',it_img18,';',it_img19,';',it_img20,';'),';;;;',';'),';;;',';'),';;;',';'),';;',';'),';;',';'),';;',';')
FROM g5_shop_item  where it_id='$idx' 
		";
		$imgs1 = onlyOnereturnSql($sql1);
		$sql2 = " 
SELECT CONCAT(';', GROUP_CONCAT(replace(attach_url,'/data/item/','') SEPARATOR ';'), ';') as result
FROM mis_attach_list WHERE table_name='g5_shop_item' AND field_name='it_img_mis' AND idx_num='$idx' ORDER BY idx; 
		";
		$imgs2 = onlyOnereturnSql($sql2);



		if($imgs1!=$imgs2 || $it_img_mis_midx=='0') {

			//이 시점은 영카트에 의해 변환된 첨부파일을 mis 에 적용해야 하는 상황임.
			$imgs1_array = splitVB($imgs1,';');
			$appSql = "delete from mis_attach_list where table_name='g5_shop_item' AND field_name='it_img_mis' AND idx_num='$idx';";
			$it_explan = '<p>';
			$it_img_mis = '';
			for($i=0;$i<count($imgs1_array);$i++) {
				$it_url = $imgs1_array[$i];
				if($it_url!='') {
					$it_file = replace($it_url, "$idx/", '');
					$it_url = '/data/item/' . $it_url;
					$it_explan = $it_explan . '<img src="' . $it_url . '"><br style="clear:both;">';
					$it_img_mis = $it_img_mis . iif($it_img_mis=='','','@AND@') . $it_file;
					$appSql = $appSql . " 
				insert into mis_attach_list (table_name,field_name,idx_name,idx_num,attach_url,attach_name,attach_size,attach_mime,wdater)
				values ('g5_shop_item', 'it_img_mis', 'it_id', '$idx', '$it_url', '$it_file', 6000000, 'image/jpeg', 'admin');
					";
												  
				}
			}


			$it_explan = $it_explan . '&nbsp;</p>';
			//midx 를 2개 테이블에 동시에 업데이트
			$appSql = $appSql . " 
UPDATE mis_attach_list AS t1
JOIN g5_shop_item AS t2 ON t2.it_id = t1.idx_num
JOIN (
    /* 가장 큰 idx 값을 미리 계산 */
    SELECT MAX(idx) AS max_idx
    FROM mis_attach_list
    WHERE table_name = 'g5_shop_item' 
      AND field_name = 'it_img_mis' 
      AND idx_num = '$idx'
) AS sub
SET 
    t1.midx = sub.max_idx,
    t2.it_img_mis_midx = sub.max_idx, t2.it_img_mis = '$it_img_mis', t2.it_explan = '$it_explan'
WHERE t1.table_name = 'g5_shop_item' 
  AND t1.field_name = 'it_img_mis' 
  AND t1.idx_num = '$idx'
  AND t2.it_id = '$idx';
			";
			//echo $appSql;
			execSql($appSql);
		}
		

    }

}
//end list_json_init



function list_query(&$selectQuery, &$countQuery) {

    global $real_pid, $mis_join_pid, $logicPid, $parent_idx, $selField;
    global $actionFlag, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript;
    global $idx_aliasName;
	global $allFilter, $temp3;
	$flag = $actionFlag ?? '';

	// v_parts_cate_* 뷰 → vv_parts_cate_* 뷰로 치환 (성능 최적화된 버전 사용)
    if($flag!='') {
        $countQuery = replace(replace($countQuery, "v_parts_cate_", "vv_parts_cate_"), "vvv_mis_parts_cate_", "vv_parts_cate_");
        $selectQuery = replace(replace($selectQuery, "v_parts_cate_", "vv_parts_cate_"), "vvv_mis_parts_cate_", "vv_parts_cate_");
   }

if($flag=='read' && $real_pid=='carparts006121') {
		
		if($app=='검증') {
//header("Content-Type:text/html; charset=UTF-8");

$target_sql = replace($countQuery, "select count(*) from", "select table_name.it_id from");
//echo $target_sql;

// 1. 원본 데이터
$allFilterJson = $temp3;
$data = json_decode($allFilterJson, true);

$outputLines1 = [];
$outputLines2 = [];
$aliasName1 = [];
$aliasName2 = [];
$realField1 = [];
$realField2 = [];

$title1 = [];
$title2 = [];
$realTitle1 = [];
$realTitle2 = [];

$value1 = [];
$value2 = [];
$realValue1 = [];
$realValue2 = [];

$count1 = 1;
$count2 = 1;
$msg1 = '';
$msg2 = '';
$exec_sql = '';

if (isset($data['entries'])) {
    foreach ($data['entries'] as $item) {
        // field명에 'qq_into'가 포함 안된 경우만 추출
        if (strpos($item['field'], 'qq_into') == false) {
            // 필드명 정리: 'toolbar_qq_into_' 또는 'toolbarSel_qq_into_' 제거
            $cleanField = str_replace(['toolbar_', 'toolbarSel_'], '', $item['field']);
            $Grid_Columns_Title = gridAlias_into_ColumnTitlePure('carparts006083',$cleanField);
			
			$v = $item['value'];
			if($item['operator']=='contains') {
				$v = "$v 포함";
			} else if($item['operator']=='lte') {  //범위 이므로, 바로 전 필드것으로, 여기서 종결지어야 함.
				$value1[$count1-2] = $value1[$count1-2] . ' ~ ' . $v;
				$realValue1[$count1-2] = $value1[$count1-2];
				continue;
			}
			$aliasName1[] = $cleanField;
			$title1[] = $Grid_Columns_Title;
			$value1[] = $v;

			if(InStr($cleanField,'Qn')>0) {

				$SortElement = (int)gridAlias_into_SortElement('carparts006083',$cleanField);
	            $rf = gridSortElement_into_Field('carparts006083',$SortElement+1);
	            $rt = gridAlias_into_ColumnTitlePure('carparts006083', $rf);
				if(InStr($cleanField, $rf)==0) {
					//바로 아래 필드가 관계없는 경우임.
					$realField1[] = $cleanField;
					$realTitle1[] = $Grid_Columns_Title;
					$realValue1[] = $item['value'];
				} else {
					$realField1[] = $rf;
					$realTitle1[] = $rt;
					if($rt=='') {
						echo "$rf 필드에 대한 타이틀을 찾을 수 없어 종료합니다.";exit;
					} else if($rf=='ca_car_id') {
						$realValue1[] = onlyOnereturnSql("SELECT fixgubun from v_parts_cate_tree_a WHERE full_name='$v';");
					} else if($rf=='ca_id') {
						$realValue1[] = onlyOnereturnSql("SELECT fixgubun from v_parts_cate_tree_b WHERE full_name='$v';");
					} else if($rf=='ca_storage_id1') {
						$realValue1[] = onlyOnereturnSql("SELECT idx from v_parts_storage_tree WHERE full_name='$v';");
					} else if($rf=='ca_storage_id2') {
						$realValue1[] = onlyOnereturnSql("SELECT idx from v_parts_storage_tree WHERE full_name='$v';");
					} else if($rf=='ca_storage_id') {
						$realValue1[] = onlyOnereturnSql("SELECT idx from v_parts_storage_tree WHERE full_name='$v';");
					} else {
						echo "정의되지 않은 필드값( $rf )이 존재하여 종료합니다.";exit;
					}
				}
			} else {
				$realField1[] = $cleanField;
				$realValue1[] = $v;
				$realTitle1[] = $Grid_Columns_Title;
			}
            // 서술형 라인 생성
            $outputLines1[] = "{$count1}. {$realTitle1[$count1-1]}: {$realValue1[$count1-1]}";
            $count1++;
        }
    }
}


if (isset($data['entries'])) {
    foreach ($data['entries'] as $item) {
        // field명에 'qq_into'가 포함된 경우만 추출
        if (strpos($item['field'], 'qq_into') == true) {
            
            // 필드명 정리: 'toolbar_qq_into_' 또는 'toolbarSel_qq_into_' 제거
            $cleanField = str_replace(['toolbar_qq_into_', 'toolbarSel_qq_into_'], '', $item['field']);
            $Grid_Columns_Title = gridAlias_into_ColumnTitlePure('carparts006083',$cleanField);
			
			$v = $item['value'];
			$aliasName2[] = $cleanField;
			$title2[] = $Grid_Columns_Title;
			$value2[] = $v;


			if(InStr($cleanField,'Qn')>0) {

				$SortElement = (int)gridAlias_into_SortElement('carparts006083',$cleanField);
	            $rf = gridSortElement_into_Field('carparts006083',$SortElement+1);
	            $rt = gridAlias_into_ColumnTitlePure('carparts006083', $rf);
				if(InStr($cleanField, $rf)==0) {
					//바로 아래 필드가 관계없는 경우임.
					$realField2[] = $cleanField;
					$realTitle2[] = $Grid_Columns_Title;
					$realValue2[] = $item['value'];
				} else {
					$realField2[] = $rf;
					$realTitle2[] = $rt;
					if($rt=='') {
						echo "$rf 필드에 대한 타이틀을 찾을 수 없어 종료합니다.";exit;
					} else if($rf=='ca_car_id') {
						$realValue2[] = onlyOnereturnSql("SELECT fixgubun from v_parts_cate_tree_a WHERE full_name='$v';");
					} else if($rf=='ca_id') {
						$realValue2[] = onlyOnereturnSql("SELECT fixgubun from v_parts_cate_tree_b WHERE full_name='$v';");
					} else if($rf=='ca_storage_id1') {
						$realValue2[] = onlyOnereturnSql("SELECT idx from v_parts_storage_tree WHERE full_name='$v';");
					} else if($rf=='ca_storage_id2') {
						$realValue2[] = onlyOnereturnSql("SELECT idx from v_parts_storage_tree WHERE full_name='$v';");
					} else if($rf=='ca_storage_id') {
						$realValue2[] = onlyOnereturnSql("SELECT idx from v_parts_storage_tree WHERE full_name='$v';");
					} else {
						echo "정의되지 않은 필드값( $rf )이 존재하여 종료합니다.";exit;
					}
				}
			} else {
				$realField2[] = $cleanField;
				$realValue2[] = $v;
				$realTitle2[] = $Grid_Columns_Title;
			}
            $exec_sql = $exec_sql . iif($count2==1,'',',') . "{$realField2[$count2-1]}='{$realValue2[$count2-1]}'";
            // 서술형 라인 생성
            $outputLines2[] = "{$count2}. {$realTitle2[$count2-1]}: {$realValue2[$count2-1]}";
            $count2++;
        }
    }
}

if($count1==1) {
	$resultCode = 'fail';
	$resultMessage = '검색조건이 없으면 일괄변경을 실행할 수 없습니다.';
} else if($count2==1) {
	$resultCode = 'fail';
	$resultMessage = '변경할 내역이 없으면 일괄변경을 실행할 수 없습니다.';
} else {
	$resultCode = 'success';
	$resultMessage = "<div onmouseover='wide_success(this)'>실행을 누르시면, 검색된 모든 내역에 대해 다음과 같이 변경됩니다.<br><br>";

	$ii = 0;
	foreach ($title1 as $line) {
		$msg1 = $msg1 . "{$title1[$ii]}: {$value1[$ii]}";
		if($title1[$ii]!=$realTitle1[$ii]) {
			$msg1 = $msg1 . "<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;[ {$realTitle1[$ii]}: {$realValue1[$ii]} ]";
		}
		$msg1 = $msg1 . "<br>";
		++$ii;
	}

	$ii = 0; 
	foreach ($title2 as $line) {
		$msg2 = $msg2 . "{$title2[$ii]}: {$value2[$ii]}";
		if($title2[$ii]!=$realTitle2[$ii]) {
			$msg2 = $msg2 . "<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;[ {$realTitle2[$ii]}: {$realValue2[$ii]} ]";
		}
		$msg2 = $msg2 . "<br>";
		++$ii;
	}
	$target_sql = str_replace(array("\r\n", "\r", "\n"), "\\n", $target_sql);
	$exec_sql_brief = " update g5_shop_item set $exec_sql where ....;";
	$exec_sql = " update g5_shop_item set $exec_sql where it_id in ($target_sql); ";
	$resultMessage = $resultMessage . "1. 검색된 조건 (총 <span id='search_cnt'></span>건 검색됨) ==========<br>$msg1<br><br>2. 변경할 내용 ==========<br>$msg2<br><br>3. 업데이트문 ==========<br>$exec_sql_brief<div style='position: absolute; left: -9999px;'>@@START_QUERY;$exec_sql@@END_QUERY;</div>";
	$resultMessage = $resultMessage . "<br><br><button onclick='exec_bat();' style='padding:10px 20px; cursor:pointer;'>실행하기</button>";
	$afterScript = "execForm_resize();";
}

        }
}
/*
	if($selField=='qq_into_ca_storage_id1Qmfull_name') {
echo $selectQuery;exit;
	}
*/

}
//end list_query



function save_updateBefore() {

    global $base_root, $real_pid, $mis_join_pid, $logicPid, $parent_idx;
    global $key_value, $table_name, $actionFlag, $saveList, $viewList, $deleteList, $updateList, $initList;
    global $upload_idx, $key_aliasName, $key_value;    //key_value 는 순수 post 값 = 입력시 공백또는 0. upload_idx 는 입력시 예상값

	//아래는 입력 시에, 자동생성번호에 맞게 참조번호를 생성시키는 로직입니다. 주로 게시판에서 사용되는 로직입니다.
    // ※ it_name 이 이번 저장에 포함된 경우(=전체 폼 저장)만 it_name_ebay/ca_id_coupang 재생성.
    //    목록 인라인편집(예: 상품품절 체크)은 it_name 을 안 보내고 $updateList 가 null 일 수 있음 → null-safe 가드.
    if($actionFlag=="modify" && is_array($updateList) && array_key_exists('it_name', $updateList)) {
        if($updateList['it_name']!='' && $updateList['it_name_ebay']=='' || Trim($initList['it_name'])!=Trim($updateList['it_name'])) {
	//echo $updateList['it_name'];
	//echo getGoogleTranslate($updateList['it_name']);exit;
			$updateList['it_name_ebay'] = getGoogleTranslate($updateList['it_name']);
		}

		if($updateList['ca_id_coupang']=='') {
			if(InStr($updateList['it_name'],'라이트')+InStr($updateList['it_name'],'램프')+InStr($updateList['it_basic'],'램프')+InStr($viewList['table_ca_id_coupangQnfull_path'],'램프')>0) {
				$updateList['ca_id_coupang'] = '78979';		//78979:램프/배터리/전기>램프/LED/HID>LED전조등

			}
		}



    }

}
//end save_updateBefore



function save_updateQueryBefore() {

	global $sql, $sql_prev, $sql_next, $key_value;
	global $result, $updateList, $upload_idx, $viewList, $initList;

	//아래는 업데이트 쿼리에 특정쿼리를 더 추가합니다.
	//$sql = $sql . " update edu_N_ghsa_new set ex5=convert(char(19),getdate(),120) where isnull(ex5,'')='' and isnull(ex3,'')='Y' and isnull(ex2,'')='Y' and idx=$key_value";
	

	//part_union_level 조립여부 관련 조건.
	$ca_car_id = $updateList['ca_car_id'];
	$ca_id = $updateList['ca_id'];
	$part_union_level = $updateList['part_union_level'];


	$assy_yn = $viewList['assy_yn'];

	if(Len($ca_car_id . $ca_id)==14) {
		if($assy_yn=='Y' && $part_union_level=='1') {
			exit('부품카테고리가 Assy 일 경우, [조립여부]는 조립품 또는 조립부품을 선택해야 합니다.');
		} else if($assy_yn=='N' && $part_union_level!='1') {
			exit('부품카테고리가 Assy 가 아닐 경우, 항상 [완성품]을 선택해야 합니다.');
		}
	} else {
		//따지지 않는 걸로.
	}



}
//end save_updateQueryBefore



/**
 * mis_attach_list 의 it_img_mis 행들을 g5_shop_item 의 컬럼들로 동기화.
 * — 수정(modify) 시에만 실행 (입력폼 신규 등록은 적용 X)
 *
 * 룰 (기존 데이터 분석 기반):
 *   - mis_attach_list 의 useflag='1' 행 (idx ASC) → 최대 20개 (초과분은 useflag='0')
 *   - it_img_mis      = attach_name 들 '@AND@' join
 *   - it_img1..20     = attach_url 에서 '/data/item/' 제거 (없는 슬롯은 빈문자열)
 *   - it_img_mis_midx = MAX(idx)
 *   - it_explan       = '<p><img src="{attach_url}"><br style="clear:both;">...&nbsp;</p>'
 *
 * PDO 의 ATTR_EMULATE_PREPARES=false 환경에서는 multi-statement 가 거부되므로
 * 각 SQL 을 개별 prepare/execute 로 직접 실행. (반환값 없음 — 즉시 적용)
 */
function _carparts006083_normalize_imgs($itId) {
	global $__pdo;
	if ((string)$itId === '' || !$__pdo) return '';

	// 1) 현재 활성 첨부 목록 (idx ASC)
	$st = $__pdo->prepare(
		"SELECT idx, attach_url, attach_name
		   FROM mis_attach_list
		  WHERE table_name='g5_shop_item' AND field_name='it_img_mis'
		    AND idx_num=? AND useflag='1'
		  ORDER BY idx ASC"
	);
	$st->execute([(string)$itId]);
	$rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

	// 2) 20개 초과분은 useflag='0' (오래된 것 = idx 작은 것)
	if (count($rows) > 20) {
		$excess = array_slice($rows, 20);
		$ids    = array_map(fn($r) => (int)$r['idx'], $excess);
		$ph     = implode(',', array_fill(0, count($ids), '?'));
		$__pdo->prepare("UPDATE mis_attach_list SET useflag='0' WHERE idx IN ({$ph})")->execute($ids);
		$rows = array_slice($rows, 0, 20);
	}

	// 3) 본체 컬럼 빌드
	$attachNames = array_map(fn($r) => (string)$r['attach_name'], $rows);
	$it_img_mis  = implode('@AND@', $attachNames);
	$newMidx     = !empty($rows) ? max(array_map(fn($r) => (int)$r['idx'], $rows)) : 0;

	$itImgs    = array_fill(1, 20, '');
	$it_explan = '<p>';
	foreach ($rows as $i => $r) {
		$rel = ltrim(str_replace('/data/item/', '', (string)$r['attach_url']), '/');
		$itImgs[$i + 1] = $rel;
		$it_explan .= '<img src="' . htmlspecialchars((string)$r['attach_url'], ENT_QUOTES, 'UTF-8') . '"><br style="clear:both;">';
	}
	$it_explan .= '&nbsp;</p>';

	// 4) UPDATE g5_shop_item — prepare/bind 로 실행 (ATTR_EMULATE_PREPARES=false 환경 호환)
	$sets = ['it_img_mis = ?', 'it_img_mis_midx = ?', 'it_explan = ?'];
	$bind = [$it_img_mis, $newMidx, $it_explan];
	for ($i = 1; $i <= 20; $i++) {
		$sets[] = "it_img{$i} = ?";
		$bind[] = $itImgs[$i];
	}
	$bind[] = (string)$itId;
	$__pdo->prepare("UPDATE g5_shop_item SET " . implode(', ', $sets) . " WHERE it_id = ?")->execute($bind);

	// 5) 활성 행들의 midx 통일 (혹시 어긋난 경우 보정)
	if ($newMidx > 0 && !empty($rows)) {
		$ids = array_map(fn($r) => (int)$r['idx'], $rows);
		$ph  = implode(',', array_fill(0, count($ids), '?'));
		$__pdo->prepare("UPDATE mis_attach_list SET midx=? WHERE idx IN ({$ph})")
		      ->execute(array_merge([$newMidx], $ids));
	}

	return ''; // 직접 실행 완료 — caller 는 더 이상 SQL 처리 불필요
}

function save_updateAfter($idx, &$afterScript) {

	global $updateList, $saveUploadList, $saveList, $kendoCulture, $base_domain;
	// v6 호환 — 일부 하위 코드가 $key_value 참조
	$key_value = (string)$idx;
	global $resultQuery;

	// 저장 후 view 로 전환되지 않고 modify 폼 그대로 유지
	$GLOBALS['_client_stayOnModify'] = true;

	// 첨부파일 정규화 — 함수 내부에서 직접 PDO 실행 (multi-statement 사용 안 함)
	_carparts006083_normalize_imgs($key_value);

	// 통합검색 인덱스: 이 행만 즉시 갱신
	global $__pdo;
	try {
		$__pdo->prepare("CALL mis_refresh_g5_shop_item_search_proc(?)")->execute([(int)$idx]);
	} catch (\Throwable) { /* 인덱스 갱신 실패해도 저장 자체는 성공 처리 */ }

	$appSql = "call mis_after_save_update_item_proc('$key_value');";

	if(requestVB('click_id')=='virtual_fieldQnprint_request') {
		//임시로 작동 막음. 그냥 인쇄되게 조치함.
		if(1==2 && ($saveList['ca_car_id']=='' || $saveList['ca_id']=='')) {
			$afterScript = 'alert("인쇄를 하려면 제조사~세부모델, 부품카테고리를 먼저 선택해야 합니다.");';
		} else {
			$appSql = $appSql . "
			update g5_shop_item set print_request_time=NOW(), print_response_time=null where it_id=$key_value;
			";
		}
	}
			
	//판매처리
	if(requestVB('click_id')=='virtual_fieldQntreat') {
		$afterScript = 'treat_check();';

		$price = $saveList['virtual_fieldQnprice'];
		$qty = (int)$saveList['virtual_fieldQnqty'];
		$user_id = $saveList['virtual_fieldQnuser_id'];
		$send_cost = $saveList['virtual_fieldQnsend_cost'];

		$od_name = sqlValueReplace($saveList['virtual_fieldQnod_name']);
		$od_email = sqlValueReplace($saveList['virtual_fieldQnod_email']);
		$od_hp = sqlValueReplace($saveList['virtual_fieldQnod_hp']);
		$od_addr2 = sqlValueReplace($saveList['virtual_fieldQnod_addr2']);
			
			
		$it_stock_qty =  (int)$saveList['it_stock_qty'];
		if($qty>$it_stock_qty || $it_stock_qty<=0) {
			echo "판매수량 $qty 가 현재재고 $it_stock_qty 보다 작아서 처리할 수 없습니다.";
			exit;
		}
			
		$appSql = $appSql . "
			CALL mis_g5_create_order_proc('$user_id','$key_value',$it_stock_qty, $qty,$price,$send_cost,'$od_name','$od_email','$od_hp','$od_addr2');
		";

	}
	//부품파트넘버 part_number 동기화
	$appSql = $appSql . "CALL proc_split_part_numbers('$key_value');";
//echo  $appSql;exit;
	execSql($appSql);
	
	//이미지의 변화가 있으면 프론트 접속하여 미리 썸네일 생성- 리눅스 crontab -e 에서 해결하여 아래는 사용 안함.
	//if (!empty($saveUploadList)) {
			//$afterScript = '$("body").append(`<iframe src="/shop/item.php?it_id=' . $key_value . '"></iframe>`);';
	//}

}
//end save_updateAfter



/**
 * 신규 INSERT 직후 — 통합검색 인덱스 등록.
 * (instantWrite 경로는 list_json_init 에서 별도 호출하므로 이 훅은 일반 등록폼 경로 보안망용.)
 */
function save_writeAfter($newIdx, &$afterScript) {
	global $__pdo;
	if ((int)$newIdx <= 0) return;
	try {
		$__pdo->prepare("CALL mis_refresh_g5_shop_item_search_proc(?)")->execute([(int)$newIdx]);
	} catch (\Throwable) {}
}

/**
 * DELETE 직후 — 통합검색 인덱스에서 해당 행 제거.
 * (실제 g5_shop_item 은 useflag=0 으로 soft-delete 일 수 있음 → 어느 쪽이든 인덱스에선 제거가 맞음.)
 */
function save_deleteAfter($idx, &$afterScript) {
	global $__pdo;
	if ((int)$idx <= 0) return;
	try {
		$__pdo->prepare("DELETE FROM mis_g5_shop_item_search WHERE it_id = ?")->execute([(int)$idx]);
	} catch (\Throwable) {}
}



function addLogic_treat(&$result) {

	global $misSessionUserId, $__pdo;

	// ── 카테고리감지 — COU/NAV 카테고리 자동선택 (모달연동 미전송 상품 한정) ──
	// 1순위: 같은 ca_id 의 이전 전송성공(sync_date 채워진) 사례 최빈값
	// 2순위: 같은 ca_id 의 기존 지정값 최빈값 (전송 전/후 무관)
	// 3순위(NAV 전용): mis_naver_category_map prefix-walk (resolveNaverCategory 동등로직)
	// 4순위: it_name 키워드 ↔ 카테고리명 LIKE 매칭
	$actionTreat = (string)($result['action'] ?? '');
	if ($actionTreat === 'detectCouCategory' || $actionTreat === 'detectNavCategory') {
		$isCou    = ($actionTreat === 'detectCouCategory');
		$col      = $isCou ? 'ca_id_coupang'     : 'ca_id_naver';
		$syncCol  = $isCou ? 'cp_sync_date'      : 'nv_sync_date';
		$catTable = $isCou ? 'coupang_category'  : 'mis_naver_categories';
		$catKey   = $isCou ? 'display_code'      : 'category_id';
		$catName  = $isCou ? 'full_path'         : 'whole_name';
		$catLeaf  = $isCou ? 'leaf'              : 'is_leaf';
		$mallName = $isCou ? '쿠팡'              : '네이버';

		$itId = (string)($result['idx'] ?? '');
		if ($itId === '') {
			$result['success'] = false;
			$result['_client_alert'] = '대상 상품 ID(idx)가 없습니다.';
			return;
		}

		$st = $__pdo->prepare("SELECT ca_id, it_name, $col AS cur_cat FROM g5_shop_item WHERE it_id = ?");
		$st->execute([$itId]);
		$cur = $st->fetch(\PDO::FETCH_ASSOC);
		if (!$cur) {
			$result['success'] = false;
			$result['_client_alert'] = '상품을 찾을 수 없습니다.';
			return;
		}
		$caId   = (string)$cur['ca_id'];
		$itName = (string)$cur['it_name'];

		$detected = '';
		$reason   = '';

		// 1순위: 같은 ca_id, 전송성공 사례 중 최빈값
		$q = $__pdo->prepare(
			"SELECT $col AS cat, COUNT(*) AS cnt
			 FROM g5_shop_item
			 WHERE ca_id = ? AND $col <> '' AND $col <> '0'
			   AND $syncCol IS NOT NULL AND $syncCol <> '0000-00-00 00:00:00'
			   AND it_id <> ?
			 GROUP BY $col ORDER BY cnt DESC, $col LIMIT 1"
		);
		$q->execute([$caId, $itId]);
		if ($r1 = $q->fetch(\PDO::FETCH_ASSOC)) {
			$detected = (string)$r1['cat'];
			$reason   = "동일 ca_id({$caId}) 전송성공 {$r1['cnt']}건";
		}

		// 2순위: 같은 ca_id, 기존 지정값(전송 무관) 최빈값
		if ($detected === '') {
			$q = $__pdo->prepare(
				"SELECT $col AS cat, COUNT(*) AS cnt
				 FROM g5_shop_item
				 WHERE ca_id = ? AND $col <> '' AND $col <> '0' AND it_id <> ?
				 GROUP BY $col ORDER BY cnt DESC, $col LIMIT 1"
			);
			$q->execute([$caId, $itId]);
			if ($r2 = $q->fetch(\PDO::FETCH_ASSOC)) {
				$detected = (string)$r2['cat'];
				$reason   = "동일 ca_id({$caId}) 기존지정 {$r2['cnt']}건 (전송 전/후 무관)";
			}
		}

		// 3순위(NAV 전용): mis_naver_category_map prefix-walk
		if ($detected === '' && !$isCou) {
			$ca = (string)preg_replace('/[^0-9]/', '', $caId);
			$stmtMap = $__pdo->prepare(
				"SELECT naver_category_id FROM mis_naver_category_map
				 WHERE bujatok_ca_id = ? AND naver_category_id <> '' LIMIT 1"
			);
			while (strlen($ca) >= 2) {
				$stmtMap->execute([$ca]);
				$hit = $stmtMap->fetchColumn();
				if ($hit) { $detected = (string)$hit; $reason = "네이버 카테고리 매핑(ca_id prefix={$ca})"; break; }
				$ca = substr($ca, 0, -2);
			}
		}

		// 4순위: 키워드 매칭 — it_name 에서 자동차부품 키워드 추출 후 카테고리명 LIKE
		if ($detected === '') {
			$keywords = [];
			foreach ([
				'후미등','테일램프','테일라이트','헤드라이트','헤드램프','전조등','안개등','방향지시등',
				'범퍼','그릴','사이드미러','후사경','휠','타이어','시트','핸들','와이퍼','엔진','오일',
				'머플러','도어','본넷','후드','펜더','트렁크','라디에이터','콘덴서','블로어','컴프레서',
			] as $kw) {
				if (strpos($itName, $kw) !== false) $keywords[] = $kw;
			}
			if ($keywords) {
				$kwQ = $__pdo->prepare(
					"SELECT $catKey FROM $catTable WHERE $catLeaf = 1 AND $catName LIKE ? ORDER BY LENGTH($catName) LIMIT 1"
				);
				foreach ($keywords as $kw) {
					$kwQ->execute(['%' . $kw . '%']);
					if ($hit = $kwQ->fetchColumn()) {
						$detected = (string)$hit;
						$reason   = "it_name 키워드 '{$kw}' 매칭";
						break;
					}
				}
			}
		}

		if ($detected === '') {
			$result['success'] = false;
			$result['_client_alert'] = "{$mallName} 카테고리를 감지하지 못했습니다.\n수동으로 드롭다운에서 선택해주세요.";
			return;
		}

		// 카테고리 경로 (토스트 표시용)
		$stN = $__pdo->prepare("SELECT $catName FROM $catTable WHERE $catKey = ? LIMIT 1");
		$stN->execute([$detected]);
		$catPath = (string)$stN->fetchColumn();

		try {
			$__pdo->prepare("UPDATE g5_shop_item SET $col = ? WHERE it_id = ?")
			      ->execute([$detected, $itId]);
			$result['success']       = true;
			$result['reloadView']    = true;
			$result['_client_toast'] = "{$mallName} 카테고리 자동선택 완료\n[{$detected}] {$catPath}\n근거: {$reason}";
		} catch (\Throwable $e) {
			$result['success']       = false;
			$result['_client_alert'] = "감지 적용 실패: " . $e->getMessage();
		}
		return;
	}

	// ── v7 — virtual_field 체크박스 클릭 즉시 처리 ────────────────────
	// 'treat:print_request_toggle' 액션 — 인쇄요청시각 즉시 기록 + 뷰 리로드
	$action = (string)($result['action'] ?? '');
	if ($action === 'print_request_toggle') {
		$itId = (string)($result['idx'] ?? '');
		if ($itId === '' || (int)$itId <= 0) {
			$result['success'] = false;
			$result['_client_alert'] = '대상 it_id 가 없습니다.';
			return;
		}
		try {
			$__pdo->prepare("UPDATE g5_shop_item SET print_request_time = NOW() WHERE it_id = ?")
			      ->execute([$itId]);
			$result['success']       = true;
			$result['reloadView']    = true;
			$result['_client_toast'] = '인쇄요청 시각이 기록되었습니다.';
		} catch (\Throwable $e) {
			$result['success']       = false;
			$result['_client_alert'] = '인쇄요청 실패: ' . $e->getMessage();
		}
		return;
	}

	// ── 판매처리 — v6 virtual_fieldQntreat 클릭 → mis_g5_create_order_proc 호출 ──
	if ($action === 'sale_treat') {
		$itId = (string)($result['idx'] ?? '');
		if ($itId === '' || (int)$itId <= 0) {
			$result['success'] = false;
			$result['_client_alert'] = '대상 it_id 가 없습니다.';
			return;
		}
		$values    = $result['values'] ?? [];
		$price     = (float)($values['virtual_fieldQnprice']     ?? 0);
		$qty       = (int)  ($values['virtual_fieldQnqty']       ?? 0);
		$send_cost = (float)($values['virtual_fieldQnsend_cost'] ?? -1);
		$user_id   = trim((string)($values['virtual_fieldQnuser_id']  ?? ''));
		$od_name   = trim((string)($values['virtual_fieldQnod_name']  ?? ''));
		$od_email  = trim((string)($values['virtual_fieldQnod_email'] ?? ''));
		$od_hp     = trim((string)($values['virtual_fieldQnod_hp']    ?? ''));
		$od_addr2  = trim((string)($values['virtual_fieldQnod_addr2'] ?? ''));

		// 검증
		if ($price <= 0)        { $result['success']=false; $result['_client_alert']='판매단가를 입력하세요!'; return; }
		if ($qty   <= 0)        { $result['success']=false; $result['_client_alert']='판매수량을 입력하세요!'; return; }
		if ($send_cost < 0)     { $result['success']=false; $result['_client_alert']='배송비를 입력하세요!';   return; }
		if ($user_id === '' && ($od_name === '' || $od_hp === '')) {
			$result['success']=false;
			$result['_client_alert']='거래처(소비자)를 선택하시거나, 비회원구매 정보를 입력하세요!';
			return;
		}

		// 현재 재고 확인
		$st = $__pdo->prepare("SELECT it_stock_qty FROM g5_shop_item WHERE it_id = ?");
		$st->execute([$itId]);
		$stockQty = (int)$st->fetchColumn();
		if ($qty > $stockQty || $stockQty <= 0) {
			$result['success'] = false;
			$result['_client_alert'] = "판매수량 {$qty} 가 현재재고 {$stockQty} 보다 작아서 처리할 수 없습니다.";
			return;
		}

		// 확인 다이얼로그 (1차 호출 시에만 prompt, _confirmed=true 면 스킵)
		if (empty($result['_confirmed'])) {
			$result['_client_confirm'] = '판매처리를 진행할까요?';
			$result['success'] = true;
			return;
		}

		try {
			$call = $__pdo->prepare(
				"CALL mis_g5_create_order_proc(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
			);
			$call->execute([
				$user_id, $itId, $stockQty, $qty, $price, $send_cost,
				$od_name, $od_email, $od_hp, $od_addr2,
			]);
			$result['success']       = true;
			$result['reloadView']    = true;
			$result['reloadList']    = true;
			$result['_client_toast'] = '판매처리가 완료되었습니다.';
		} catch (\Throwable $e) {
			$result['success']       = false;
			$result['_client_alert'] = '판매처리 실패: ' . $e->getMessage();
		}
		return;
	}

	// ── 대표이미지로 — v6 의 top_img 로직 v7 포팅 ─────────────────────────
	if ($action === 'select_top_img') {
		$itId    = (int)($result['idx'] ?? 0);
		$topImg  = trim((string)($result['top_img'] ?? ''));
		if ($itId <= 0 || $topImg === '') {
			$result['success'] = false;
			$result['_client_toast'] = '대상 it_id / 파일명 누락';
			return;
		}
		try {
			$__pdo->beginTransaction();
			// 1) 클릭한 이미지의 attach_list idx
			$st = $__pdo->prepare(
				"SELECT idx FROM mis_attach_list
				  WHERE table_name='g5_shop_item' AND idx_num=? AND attach_url LIKE ? LIMIT 1"
			);
			$st->execute([(string)$itId, '%' . $topImg]);
			$currentIdx = (int)$st->fetchColumn();
			// 2) 현재 최상위(첫번째) 이미지의 idx
			$st2 = $__pdo->prepare(
				"SELECT MIN(idx) FROM mis_attach_list
				  WHERE table_name='g5_shop_item' AND idx_num=?"
			);
			$st2->execute([(string)$itId]);
			$topIdx = (int)$st2->fetchColumn();

			if ($currentIdx > 0 && $topIdx > 0 && $currentIdx !== $topIdx) {
				// 3) idx 자리바꿈 (PK 충돌 회피용 -1 sentinel 경유)
				$__pdo->prepare("UPDATE mis_attach_list SET idx = -1 WHERE idx = ?")->execute([$topIdx]);
				$__pdo->prepare("UPDATE mis_attach_list SET idx = ?  WHERE idx = ?")->execute([$topIdx, $currentIdx]);
				$__pdo->prepare("UPDATE mis_attach_list SET idx = ?  WHERE idx = -1")->execute([$currentIdx]);

				// 4) 새 순서로 g5_shop_item.it_img1~20 + it_img_mis + it_explan 재구성
				//    (저장 직후 normalize 와 동일한 흐름 — idx ASC 로 다시 읽어 본체 컬럼 갱신)
				_carparts006083_normalize_imgs($itId);
			}
			$__pdo->commit();
			$result['success']       = true;
			$result['_client_toast'] = '대표이미지가 변경되었습니다.';
			$result['reloadView']    = true;
			$result['reloadList']    = true;
		} catch (\Throwable $e) {
			if ($__pdo->inTransaction()) $__pdo->rollBack();
			$result['success']       = false;
			$result['_client_toast'] = '대표이미지 변경 실패: ' . $e->getMessage();
		}
		return;
	}

	// ── v6 호환 ajax — 아래는 $_GET/$_POST 직접 읽는 레거시 echo 출력 흐름 ───
    //addLogic_treat 함수는 ajax 로 요청되어진(url 형식) 것에 대한 출력문입니다. echo 등으로 출력내용만 표시하면 됩니다.
	//아래는 url 에 동반된 파라메터의 예입니다.
	//해당 예제 TIP 의 기본폼에 보면 addLogic_treat 를 호출하는 코딩이 있습니다.

    $idx = requestVB("idx");
    $top_img = requestVB("top_img");

	$it_10_new = requestVB("it_10_new");
	$it_10_old = requestVB("it_10_old");

	//아래는 값에 따라 mysql 서버를 통해 알맞는 값을 출력하여 보냅니다.

	if($it_10_new!='' && $it_10_old!='') {
		//판매처 원장변경
		$sql = "
select count(*) from g5_shop_item where it_10='$it_10_old' and it_id='$idx';
		";
		$cnt = onlyOnereturnSql($sql);
		if($cnt==0) {
			echo "선택한 판매처 원장에 해당 상품이 없습니다!";
			return;
		}

		$sql = "
insert into g5_shop_item_it_10_log (it_id, it_10_old, it_10_new, wdater)
values ('$idx', '$it_10_old', '$it_10_new', '$misSessionUserId');
UPDATE g5_shop_item SET it_10='$it_10_new' WHERE it_10='$it_10_old' AND it_id='$idx';
		";
		//echo $sql;
		execSql($sql);
		echo "OK";
    } else if($top_img!='') {
		
		$sql = " 
SELECT idx FROM  mis_attach_list WHERE table_name='g5_shop_item' and idx_num='$idx'
AND attach_url LIKE '%$top_img'
";

		$current_idx = onlyOnereturnSql($sql);

		$sql = " 
SELECT min(idx) FROM  mis_attach_list WHERE table_name='g5_shop_item' and idx_num='$idx'
";
		$top_idx = onlyOnereturnSql($sql);
 
        //echo " $current_idx!=$top_idx;";

		$sql = " 
UPDATE mis_attach_list SET idx = -1 WHERE idx = $top_idx;
UPDATE mis_attach_list SET idx = $top_idx  WHERE idx = $current_idx;
UPDATE mis_attach_list SET idx = $current_idx  WHERE idx = -1;
 ";
		if($current_idx!=$top_idx) {
			//echo $sql;exit;
			execSql($sql);


			//아래 로직은 g5_shop_item 의 it_img_mis 필드와 영카트에도 동시에 반영하기 위한 로직. save_updateAfter() 도 비슷. 참고할 것.
			$key_value = $idx;
			$appSql = " update g5_shop_item set it_explan=''";
			
			for($i=0;$i<20;$i++) {
				$ii = $i + 1;
				 $appSql = $appSql . ",it_img$ii=''";	
			}
			$appSql = $appSql . " where it_id='$key_value';";


			$appSql2 = "
	SELECT attach_url from mis_attach_list WHERE table_name='g5_shop_item' AND field_name='it_img_mis' and idx_num='$key_value'
	ORDER BY idx  ;
			";
			$r = allreturnSql($appSql2);
			$it_img_mis = '';
			if(count($r)>0) {
				$appSql = $appSql . " update g5_shop_item set ";
		//echo $appSql2;

				$it_explan = '<p>';
				for($i=0;$i<count($r);$i++) {
					$ii = $i + 1;
					$pure_img_name = basename($r[$i]['attach_url']);
					if($pure_img_name!='') {
						if($it_img_mis!='') {
							$it_img_mis = $it_img_mis . '@AND@' . $pure_img_name;
						} else {
							$it_img_mis = $pure_img_name;
						}
					}
					$it_img = replace($r[$i]['attach_url'],'/data/item/','');
					$appSql = $appSql . iif($i>0,',','') . "it_img$ii='$it_img'";	
					$it_explan = $it_explan . '<img src="' . $r[$i]['attach_url'] . '"><br style="clear:both;">';

				}
				$it_explan = $it_explan . '&nbsp;</p>';
				$appSql = $appSql . ",it_explan='$it_explan' 
				,it_img_mis='$it_img_mis'
				where it_id='$key_value';
				";
			}
				//echo $appSql;exit;
			execSql($appSql);
		}
		
    }

}
//end addLogic_treat

?>