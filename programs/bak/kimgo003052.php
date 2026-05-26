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



function misMenuList0_change() {

    global $data, $allFilter, $ActionFlag, $RealPid, $list_numbering, $parent_idx, $idx, $gubun, $MisSession_UserID;

	//아래 주석을 풀면 분석에 도움이 됩니다.
	//아래는 특정 2개 프로그램에 대해 BodyType 을 기본형으로 바꿔줍니다.
    if($ActionFlag!='list') {
        $data[0]["isThisChild"] = "Y";
        $data[0]["psize"] = "5";
    }

}
//end misMenuList0_change



function misMenuList_change() {
	//misMenuList 테이블에 의한 설정값인 $result 를 바꾸는게 이 함수의 핵심기능
    global $ActionFlag, $gubun, $parent_idx, $idx, $RealPid, $logicPid, $result;
	global $MisSession_PositionCode, $flag, $list_check_hidden;

	//아래는 MenuName 이라는 aliasName 에 대해 표시명을 바꾸는 예제임.
	if($RealPid=='kimgo003054') {
		$search_index = array_search("isEnd", array_column($result, 'aliasName'));
		$result[$search_index]["Grid_Columns_Width"] = "-1";
	}
}
//end misMenuList_change



function pageLoad() {

    global $ActionFlag,$idx,$parent_idx,$gubun, $RealPid;
	global $MisSession_IsAdmin, $MisSession_UserID;

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
	div.before_round_depG1, div.before_round_comG1 {
	    display:none;	
	}
.k-tabstrip-items-wrapper {
    display: none !important;
}
div#round_table_mQmeommuilja,div#round_table_mQmnaeyong,div#round_table_mQmhyanghugyehoek,div#round_table_mQmmagamilja  {
	    position:absolute;
		top:-1000px;
	}
	div.before_round_eommuilja {
		width: 50%;
	}
	
div.td_div {
    max-height: max-content;
    text-overflow: ellipsis;
    overflow: hidden;
    overflow-wrap: normal;
}
	

</style>


        <script>



$('body').attr('onlylist','');	

			
function list_print_add(doc) {
	
	ds = $('div#grid').data('kendoGrid').dataSource._data;
	if(ds[0]) {
		
		var obj_grid = $(doc.activeElement).find('div#grid');
		tt = 0;
		for(i=0;i<ds.length;i++) {
			tt = tt + ds[i]['eommusigan'];
		}

		obj_grid.before(`<div class="work_time">총업무시간: `+formatnum(tt,'###0.0')+`시간<div>`);
		
		
		var obj_title = $(doc.activeElement).find('h2#title');
		dd = replaceAll($('div#grid').data('kendoGrid').dataSource._data[0]['eommuilja'],'-','월');
		dd = replaceAll(' '+Right(dd,5)+'일',' 0',' ');
		<?php if($RealPid=='kimgo003052') { ?>
		obj_title[0].innerHTML = `일일업무일지(업무일)`+dd;
		<?php } else if($RealPid=='kimgo003053') { ?>
		obj_title[0].innerHTML = `일일업무일지(작성일)`+dd;
		<?php } else { ?>
		obj_title[0].innerHTML = `일일업무일지(미완료)`+dd;
		<?php } ?>
	}
	var obj_btn = $(doc.activeElement).find('span.k-icon.k-i-pdf.no-print');
	
	if(obj_btn.find('approval-box0')[0]) {
		obj_btn.find('approval-box0').remove();
	}
	obj_btn.before(`<div class="approval-box0"><style>
.work_time {
    position: absolute;
    top: 80px;
}

span.k-icon.k-i-pdf {
    left: 150px;
}
span.k-icon.k-i-print {
    left: 178px;
}

.k-upload {
    max-width: 80px!important;
}
	
span.k-file-group-wrapper {
    display: none!important;
}
.k-file:last-child {
    padding: 0!important;
}
.td_div {
    min-width: 250px;
}
.approval-box0 {
      height: 49px;
    width: 100%;
    }
    .approval-box {
          display: flex
;
    width: 333px;
    height: 93px;
    border: 1px solid #ccc;
        padding: 0 10px;
    gap: 10px;
    font-family: sans-serif;
    float: right;
    position: absolute;
    right: 0;
    top: 0;
    background: #fff;
    z-index: 10;
    }

    .box {
      flex: 1;
      border: 2px solid black;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      align-items: center;
      padding: 5px;
    }

    .box-label {
      font-weight: bold;
      margin-bottom: 5px;
    }

    .signature-line {
width: 80%;
    border-top: 1px solid #000;
    margin-top: auto;
    margin-bottom: 5px;
    height: 1px;
    top: -54px;
    position: relative;
    }
  </style>
<div class="approval-box">
  <!-- 담당 칸 -->
  <div class="box">
    <div class="box-label">담당</div>
    <div class="signature-line"></div>
  </div>

  <!-- 빈칸 1 -->
  <div class="box">
    <div class="box-label">결재1</div>
    <div class="signature-line"></div>
  </div>

  <!-- 빈칸 2 -->
  <div class="box">
    <div class="box-label">결재2</div>
    <div class="signature-line"></div>
  </div>
</div>
</div>`);
	
}
			
function columns_templete(p_dataItem, p_aliasName) {

    if(p_aliasName=='virtual_fieldQnwork_end') {
		var rValue = `<a role="button" href="javascript:;" class="k-button k-button-icontext">완료</a>`;
		
		rValue = rValue + columns_format(p_dataItem, p_aliasName);
        return rValue;
    } else if(p_aliasName=='naeyong' || p_aliasName=='hyanghugyehoek') {
		var rValue = `<div style="overflow-y:auto;max-height:100px;">`+p_dataItem[p_aliasName]+`</div>`;
		
        return rValue;
    } else {
        return p_dataItem[p_aliasName];
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
	
	if(document.getElementById('ActionFlag').value=='list') {
		$("a#btn_1").text("인쇄창열기");
		$("#btn_1").click( function() {
			$('li#btn_listprint_overflow a.k-button').click();
		});
		$("a#btn_2").text("업무일지로 이동");
		$("#btn_2").click( function() {
			location.href = 'index.php?gubun=3041&isMenuIn=auto';
		});
	}


}

//목록에서 grid 로드 후 한번만 실행됨, 이때 처리해야할 일반 스크립트를 삽입합니다.
function listLogic_afterLoad_once()	{
	

}
			
//목록에서 grid 로드 후 데이터 로딩마다 실행됨, 이때 처리해야할 일반 스크립트를 삽입합니다.		
function listLogic_afterLoad_continue()	{
	
	
}

		
	
			
			
        </script>
        <?php 
}
//end pageLoad



function list_json_init() {
    global $RealPid, $MisJoinPid, $logicPid, $parent_idx, $full_siteID, $filter, $MisSession_UserID;
    global $flag, $selField, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript;
	//$flag 는 목록조회시 'read'   내용조회시 'view'    수정시 'modify'   입력시 'write'
	//$selField 는 필터링을 하는 순간 발생하는 필드alias 값.

	//아래의 예제는 자동정렬이라는 명령을 받았을 경우, 목록이 생성되기 전에 숫자를 정렬하는 기능입니다.
 
    if(InStr($filter,'toolbar_virtual_fieldQnaddDep')>0) { 
		
        $v = splitVB(splitVB($filter,"toolbar_virtual_fieldQnaddDep eq '")[1]," | ")[0];
        $appSql = "
insert into kimgoMainList (depG1, depG2, depG3, wdater) 
select g4num, g8num, g12num,N'$MisSession_UserID' from vMisStationTree where idx=$v
			";

        execSql($appSql);
                   
    }
}
//end list_json_init



function list_query() {

    global $RealPid, $MisJoinPid, $logicPid, $parent_idx, $selField;
    global $flag, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript;
    global $countQuery, $selectQuery, $idx_aliasName;

	//아래는 어떤 특정한 상황에 대한 적용예입니다.
    if($flag=='toolbar_searchField_kendoDropDownList' && $selField=='virtual_fieldQnaddDep') { 

        $selectQuery = "
select top 100 virtual_fieldQnaddDep from(select distinct 1 as nnn,
concat(idx, ' | ', g4name, case when len(AutoGubun)>=8 then ' > ' else '' end, g8name, case when len(AutoGubun)>=12 then ' > ' else '' end, g12name) as virtual_fieldQnaddDep from vMisStationTree table_m) aaa order by nnn,
virtual_fieldQnaddDep
";

   }

}
//end list_query



function save_writeQueryBefore() {
	//$viewList: 내용전체 array     $saveList: 저장내역 array(aliasName 으로 표현됨)      $updateList: 최종업데이트될 내역(실제필드이름으로 표현됨)
	//$newIdx: 입력시 생성된 자동증가번호     $sql: 입력쿼리문      $sql_prev: 입력쿼리문 앞에 붙을 쿼리문     $sql_next: 입력실행 뒤에 붙을 쿼리문
	global $full_siteID, $base_root, $RealPid, $MisJoinPid, $logicPid, $parent_idx;
    global $key_aliasName, $key_value, $ActionFlag, $viewList, $saveList, $updateList, $sql, $sql_prev, $sql_next, $newIdx;

	//아래와 같이 입력 sql 문을 직접 변경할수 있습니다.

	$sql_next = " update kimgoMainList set gidx=$newIdx where idx=$newIdx and gidx=0;";

}
//end save_writeQueryBefore



function save_deleteBefore() {

    global $full_siteID, $base_root, $RealPid, $MisJoinPid, $logicPid, $parent_idx;
    global $key_aliasName, $key_value, $ActionFlag, $sql_prev, $sql, $sql_next, $deleteList;

	//$deleteList 는 실제 삭제하는 idx 값입니다.
	//아래의 같은 방식으로 변경할 수 있습니다. 
	//또는 실제삭제쿼리인 $sql 전에 실행할 쿼리문을 $sql_prev 에, 실행직후 쿼리문을 $sql_next 에 넣을 수 있습니다.
	//$sql = replace($sql, '', '');

	$cnt1 = onlyOnereturnSql("select count(*) from kimgoMainList where useflag=1 and gidx in ($deleteList);")*1;
	$cnt2 = onlyOnereturnSql("select count(*) from kimgoMainList where useflag=1 and idx in ($deleteList);")*1;

	if($cnt1>$cnt2) {
		exit('기준 업무보다 파생 업무를 먼저 삭제해야 합니다.');
	}

//echo "$cnt1 > $cnt2";
//exit;
}
//end save_deleteBefore



function addLogic_treat() {

	global $MisSession_UserID;
	
    //addLogic_treat 함수는 ajax 로 요청되어진(url 형식) 것에 대한 출력문입니다. echo 등으로 출력내용만 표시하면 됩니다.
	//아래는 url 에 동반된 파라메터의 예입니다.
	//해당 예제 TIP 의 기본폼에 보면 addLogic_treat 를 호출하는 코딩이 있습니다.

    $question = requestVB("question");
    $p_idx = requestVB("idx");


	//아래는 값에 따라 mysql 서버를 통해 알맞는 값을 출력하여 보냅니다.
    if($question=="get_gidx") {
		$sql = "select gidx from kimgoMainList where useflag=1 and idx=(select gidx from kimgoMainList where idx='$p_idx');";
		$gidx = onlyOnereturnSql($sql);

		echo $gidx;
		
    }

}
//end addLogic_treat

?>