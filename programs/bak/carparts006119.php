<?php

/*info

작성자 : 


주요기능 : 
*/

//선언문 영역
//$plist = ['John','Piter'];
$list_numbering = '';		//리스트에서 No. 순번항목을 없앰.


/*

특이사항 : 


참고url : 


info*/



function misMenuList_change() {
    
	//misMenuList 테이블에 의한 설정값인 $result 를 바꾸는게 이 함수의 핵심기능
    global $ActionFlag, $gubun, $parent_idx, $parent_gubun, $RealPid, $logicPid, $result;
	global $MisSession_PositionCode, $flag, $list_check_hidden;

	if($parent_gubun=='6118') {
		//아래는 MenuName 이라는 aliasName 에 대해 표시명을 바꾸는 예제임.
		$search_index = array_search("sales_price", array_column($result, 'aliasName'));
		$result[$search_index]["Grid_Columns_Width"] = "0";
		//$search_index = array_search("select_site_id", array_column($result, 'aliasName'));
		//$result[$search_index]["Grid_Columns_Width"] = "0";
	    
	}
}
//end misMenuList_change



function pageLoad() {

    global $ActionFlag, $RealPid, $parent_idx, $idx, $parent_gubun;
	global $MisSession_UserID, $MisSession_IsAdmin, $parent_RealPid;

	//특정상황에서 페이지를 이동시키는 예제입니다.
	if(requestVB('lite')=='Y') {
		exit;
		//$url = 
		//re_direct($url);
	}


        ?>

<style>
/* 필요할 경우, 해당프로그램에 추가할 css 를 넣으세요 */
	
	
</style>


        <script>
			
//아래 한줄의 주석문을 풀면 리스트 상에서 내용조회나 수정으로 접근할 수 없습니다.
$('body').attr('onlylist','');			
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
			
function open_site_home(p_site_id) {
	


	if (p_site_id == '00.부자톡') {
		// 자체몰 상세페이지
		window.open('/', '_blank');
	} 
	else if (p_site_id == '01.이베이') {
		window.open('https://www.ebay.com', '_blank');
	} 
	else if (p_site_id == '02.번개장터') {
		window.open('https://www.bunjang.co.kr', '_blank');
	} 
	else if (p_site_id == '03.ok파츠') {
		window.open('https://www.okparts.co.kr', '_blank');
	} 
	else if (p_site_id == '04.GK파츠') {
		window.open('https://www.gkparts.co.kr', '_blank');
	} 
	else if (p_site_id == '05.파츠핏') {
		window.open('https://www.partsfit.co.kr', '_blank');
	} 
	else if (p_site_id == '06.G파츠') {
		window.open('https://www.gparts.co.kr', '_blank');
	} 
	else if (p_site_id == '07.쿠팡') {
		window.open('https://www.coupang.com', '_blank');
	} 
	else if (p_site_id == '08.네이버스토어') {
		window.open('https://smartstore.naver.com', '_blank');
	} 
	else if (p_site_id == '09.중고나라') {
		window.open('https://www.joongna.com', '_blank');
	} 
	else if (p_site_id == '10.구글') {
		window.open('https://www.google.com', '_blank');
	} 
	else {
		//alert('미등록 채널: ' + p_site_id);
	}
}			
function open_site(p_idx) {
	url = getCellObj_idx(p_idx,'url').innerText;
	if(url!='' && url!=undefined) {
		window.open(url);
	}
}

function sel_site(p_idx) {
	url = "addLogic_treat.php?RealPid=<?=$RealPid?>&idx="+p_idx;
	ajax_url_return(url);
	$("#grid").data("kendoGrid").dataSource.read();
}

function columns_templete(p_dataItem, p_aliasName) {

    if(p_aliasName=='site_id') {
		var rValue = p_dataItem[p_aliasName];
		if(Left(rValue,2)<='10') {
			rValue = `
			<a onclick="open_site_home('`+p_dataItem[p_aliasName]+`');" class="k-button">홈</a>
			` + rValue;
		}
		
		return rValue;
    } else if(p_dataItem['zhwapye']=='$' && (p_aliasName=='sales_price_site' || p_aliasName=='sales_price')) {
		rValue = p_dataItem[p_aliasName];
		if(p_dataItem[p_aliasName]>0) {
			var rValue =  rValue + '<br><span style="color:blue;font-size:9px;">'
			+formatnum((p_dataItem[p_aliasName]*p_dataItem['qq_whanRate']).toFixed(0)*1,'##,##0')+'원</span>';
		}
		return rValue;
    } else if(p_aliasName=='select_site_id') {
		var rValue = `
<a onclick="open_site('`+p_dataItem['idx']+`');" class="k-button">url</a>
		`;
		
			if(getUrlParameter('parent_gubun')!='6118') {
		 rValue = rValue+`
<a onclick="sel_site('`+p_dataItem['idx']+`');" class="k-button">적용</a>
		`;
	    
			}
		
		
		return rValue;
    } else {
        return p_dataItem[p_aliasName];
    }
}
 

			
//아래는 그리드의 로딩이 거의 끝난 후, 항목에 스타일 시트를 적용하는 예입니다. 스타일 등을 직접 변경하려면 이 함수를 이용해야 합니다.
//row 갯수만큼 실행됩니다.
function rowFunctionAfter_UserDefine(p_this) {


    if(InStr(p_this.site_id, '기타')>0) {
        $(getCellObj_idx(p_this[key_aliasName], "isCheckedYn")).find('input').css('display','none');
    }

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
	
	<?php if($parent_gubun=='6125') { ?>
    $("a#btn_1").text("검수완료");
    $("li#btn_1_overflow").text("검수완료");
    $("#btn_1").css("background", "#88f");
    $("#btn_1").css("color", "#fff");
    $("#btn_1").click( function() {
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "검수완료";
        $("#grid").data("kendoGrid").dataSource.read();
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "";
    });
    $("a#btn_2").text("검수기각");
    $("li#btn_2_overflow").text("기각");
    $("#btn_2").css("background", "#f88");
    $("#btn_2").css("color", "#fff");
    $("#btn_2").click( function() {
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "기각";
        $("#grid").data("kendoGrid").dataSource.read();
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "";
    });
	<?php } else { ?>
    $("a#btn_1").text("입력완료 및 상신");
    $("li#btn_1_overflow").text("상신");
    $("#btn_1").css("background", "#88f");
    $("#btn_1").css("color", "#fff");
    $("#btn_1").click( function() {
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "상신";
        $("#grid").data("kendoGrid").dataSource.read();
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "";
    });
    $("a#btn_2").text("비교불가");
    $("li#btn_2_overflow").text("비교불가");
    $("#btn_2").css("background", "#f88");
    $("#btn_2").css("color", "#fff");
    $("#btn_2").click( function() {
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "비교불가";
        $("#grid").data("kendoGrid").dataSource.read();
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "";
    });
	<?php } ?>
	

}

//목록에서 grid 로드 후 한번만 실행됨, 이때 처리해야할 일반 스크립트를 삽입합니다.
function listLogic_afterLoad_once()	{
	//grid_remove_sort();    //그리드의 상단 정렬 기능 제거를 원할 경우.
	
}
			
//목록에서 grid 로드 후 데이터 로딩마다 실행됨, 이때 처리해야할 일반 스크립트를 삽입합니다.		
function listLogic_afterLoad_continue()	{
	
	
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


function viewLogic_before_save() {
	/*
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
    */
    
}

//내용조회 또는 수정/입력 페이지 로딩이 끝나는 순간 한번만 실행됨, 이때 처리해야할 일반 스크립트를 삽입합니다.
function viewLogic_afterLoad() {

	//debugger;
	//console.log(resultAll);
	//console.log(resultAll.d.results[0]);
	
	
	//각탭에 html 에디터를 통해 pdf 업로드나 pdf 링크가 href 가 1개만 있으면 뷰어로 변신
	//tab_pdf_into_viewer();

	//특정항목에 대한 내용을 바꾸는 코딩
    //$('div#table_mQmidx')[0].innerText = resultAll.d.results[0].table_mQmidx;
	
	//특정 tabid 를 넣으면 해당 탭이 먼저 열린다. 아래는 wdate 를 넣어서 등록정보 탭이 열리는 예제.
	//if($('input#ActionFlag')[0].value=='view') $('li[tabid="viewPrint"]').attr('active_tabid','wdate');


}			
//내용조회 또는 수정/입력 페이지 로딩이 끝난 후, 데이터 호출때마다 실행됨. 
function viewLogic_afterLoad_continue() {

	//debugger;
	//console.log(resultAll);
	//console.log(resultAll.d.results[0]);
	

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
        if($app=='상신') {
            $appSql = "select count(*) from g5_shop_item_compare where it_id='$parent_idx' and sales_price_site>0";
            $cnt = onlyOnereturnSql($appSql)*1;
            if($cnt<1) {
                $afterScript = "alert('최소 2건 이상의 가격이 입력되어야 합니다.');";
            } else {
				$appSql = "select count(*) from g5_shop_item_compare where it_id='$parent_idx' and isCheckedYn='Y'";
				$cnt = onlyOnereturnSql($appSql)*1;
				if($cnt<3) {
					$afterScript = "alert('3개 사이트의 점검완료가 체크되어야 합니다.');";
				} else {
					$appSql = "select count(*) from g5_shop_item_compare where it_id='$parent_idx' and sales_price_site>0 and site_id in ('01.이베이','13.기타$')";
					$cnt = onlyOnereturnSql($appSql)*1;
					if($cnt==0) {
						$afterScript = "alert('최소 1건 이상의 달러 가격이 입력되어야 합니다.');";
					} else {
						$appSql = "update g5_shop_item_compare set steps='상신' where it_id='$parent_idx'";
						execSql($appSql);
						$afterScript = "alert('상신되었습니다.'); top.location.href = 'index.php?gubun=6123&isMenuIn=Y';";
					}
				}

            } 

        } else if($app=='검수완료') {
            $appSql = "select count(*) from g5_shop_item_compare where it_id='$parent_idx' and select_site_id<>''";
            $cnt = onlyOnereturnSql($appSql)*1;
            if($cnt==0) {
                $afterScript = "alert('적용할 원화 가격에 대한 채택적용을 클릭하세요.');";
            } else {
                $appSql = "select count(*) from g5_shop_item_compare where it_id='$parent_idx' and select_site_id_ebay<>''";
                $cnt = onlyOnereturnSql($appSql)*1;
                if($cnt==0 && 111==222) {
                    $afterScript = "alert('적용할 달러 가격에 대한 채택적용을 클릭하세요.');";
                } else {
                    $appSql = "
                    update g5_shop_item set it_use=1, it_update_time=NOW() where it_id='$parent_idx';
                    update g5_shop_item_compare set steps='검수완료' where it_id='$parent_idx';
                    ";
                    execSql($appSql);
                    $afterScript = "alert('검수완료되었습니다.'); top.location.href = top.location.href.split('#')[0];";
                }
            } 

        } else if($app=='기각') {
            
					   $appSql = "update g5_shop_item_compare set steps='기각' where it_id='$parent_idx'";
					   execSql($appSql);
					   $afterScript = "alert('기각되었습니다.'); top.location.href = top.location.href.split('#')[0];";


        } else if($app=='비교불가') {
            $appSql = "select count(*) from g5_shop_item_compare where it_id='$parent_idx' and isCheckedYn='Y'";
			$cnt = onlyOnereturnSql($appSql)*1;
			if($cnt<9) {
				$afterScript = "alert('9개 사이트의 점검완료가 체크되어야 합니다.');";
			} else {
				$appSql = "update g5_shop_item_compare set steps='비교불가' where it_id='$parent_idx'";
				execSql($appSql);
				$afterScript = "alert('비교불가 처리되었습니다.'); top.location.href = top.location.href.split('#')[0];";
			}
        }

    }
	
}
//end list_json_init



function textUpdate_sql() {

    global $strsql, $keyAlias, $keyValue, $thisValue, $oldText, $thisAlias, $resultCode, $resultMessage, $afterScript;

	//아래는 특정항목을 수정할 경우, 해당항목이 정의된 리스트에 포함되었을 경우, 관련 업데이트문을 추가하고, 처리메세지를 브라우저로 전달하는 로직입니다.  
    if(InStr(";sales_price_site;sales_price;",";" . $thisAlias . ";")>0) {
       $afterScript = "$('#grid').data('kendoGrid').dataSource.read();";
    }

}
//end textUpdate_sql



function addLogic_treat() {

	global $MisSession_UserID;
	
    //addLogic_treat 함수는 ajax 로 요청되어진(url 형식) 것에 대한 출력문입니다. echo 등으로 출력내용만 표시하면 됩니다.
	//아래는 url 에 동반된 파라메터의 예입니다.
	//해당 예제 TIP 의 기본폼에 보면 addLogic_treat 를 호출하는 코딩이 있습니다.

    $idx = requestVB("idx");
   

	//아래는 값에 따라 mysql 서버를 통해 알맞는 값을 출력하여 보냅니다.
    if($idx!='') {
		$sql = " 
-- 0. idx=$idx 데이터 변수에 저장
SELECT 
    @it_id := it_id, 
    @site_id := site_id, 
    @url := url, 
    @sales_price := sales_price, 
    @sales_price_site := sales_price_site 
FROM g5_shop_item_compare 
WHERE idx = $idx
AND sales_price >= 50 
  AND url IS NOT NULL 
  AND url <> '';

-- 1. 판매가격
SELECT 
    @it_price := it_price 
    ,@it_price_ebay := it_price_ebay
FROM g5_shop_item 
WHERE it_id = @it_id;


-- 2. 대상 데이터 업데이트
UPDATE g5_shop_item_compare 
SET 
    select_site_id = @site_id, 
    select_url = @url,
	select_sales_price = @sales_price 
WHERE it_id = @it_id 
AND @it_id IS NOT NULL and @site_id not in ('01.이베이','13.기타$');

UPDATE g5_shop_item_compare 
SET 
    select_site_id_ebay = @site_id, 
    select_url_ebay = @url,
	select_sales_price_ebay = @sales_price 
WHERE it_id = @it_id 
AND @it_id IS NOT NULL and @site_id in ('01.이베이','13.기타$');

-- 3. 이력(Log) 추가
INSERT INTO g5_shop_item_compare_log 
    (it_id, select_site_id, select_url, select_sales_price_pre, select_sales_price, wdater)
SELECT @it_id, @site_id, @url, @it_price, @sales_price, '$MisSession_UserID'
WHERE @it_id IS NOT NULL and @site_id not in ('01.이베이','13.기타$');

INSERT INTO g5_shop_item_compare_log 
    (it_id, select_site_id, select_url, select_sales_price_pre, select_sales_price, wdater)
SELECT @it_id, @site_id, @url, @it_price_ebay, @sales_price, '$MisSession_UserID'
WHERE @it_id IS NOT NULL and @site_id in ('01.이베이','13.기타$');

-- 4. 가격반영

UPDATE g5_shop_item 
SET 
    it_price = @sales_price
WHERE it_id = @it_id 
AND @site_id not in ('01.이베이','13.기타$');
UPDATE g5_shop_item 
SET 
    it_cust_price = @sales_price_site
WHERE it_id = @it_id and @sales_price_site>0
AND @site_id not in ('01.이베이','13.기타$');



UPDATE g5_shop_item 
SET 
    it_price_ebay = @sales_price 
WHERE it_id = @it_id 
AND @site_id in ('01.이베이','13.기타$');
UPDATE g5_shop_item 
SET 
    it_cust_price_ebay = @sales_price_site 
WHERE it_id = @it_id and @sales_price_site>0
AND @site_id in ('01.이베이','13.기타$');

";
echo $sql;
		execSql($sql);
    }

}
//end addLogic_treat

?>