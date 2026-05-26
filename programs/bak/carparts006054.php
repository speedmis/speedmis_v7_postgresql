<?php

function pageLoad() {

    global $actionFlag, $real_pid, $parent_idx, $idx;
	global $misSessionUserId, $misSessionIsAdmin, $parent_RealPid;
/*
	//특정상황에서 페이지를 이동시키는 예제입니다.
	if($actionFlag=='list' && $parent_RealPid=='speedmis000028') {
		$target_parent_gubun = RealPidIntoGubun('speedmis001071');
		$url = "index.php?RealPid=$real_pid&parent_gubun=$target_parent_gubun&parent_idx=$parent_idx";
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
			

function columns_templete(p_dataItem, p_aliasName) {

    if(p_aliasName=='qq_first_img') {
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
	$('#virtual_fieldQngrade_a,#virtual_fieldQngrade_b,#virtual_fieldQngrade_c,input#virtual_fieldQnprint_request').change( function() {
		$('#btn_save').click();
	});

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
    global $real_pid, $misJoinPid, $logicPid, $parent_idx, $full_siteID, $grid_load_once_event;
    global $flag, $selField, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript;
	//$flag 는 목록조회시 'read'   내용조회시 'view'    수정시 'modify'   입력시 'write'
	//$selField 는 필터링을 하는 순간 발생하는 필드alias 값.

	//아래의 예제는 자동정렬이라는 명령을 받았을 경우, 목록이 생성되기 전에 숫자를 정렬하는 기능입니다.


	//단순 동기화는 잘되지만, 프론트에서 이미 업로드한 내역을 일부수정했을 경우, 테스트 부족함.

    if($flag=='read' || $flag=='modify' || $flag=='view') { 
        $appSql = "

/* 2-1) speedmis 부품관리 리스트를 열때, 프론트에 추가된 파일내역을 동기화 시키기. */

INSERT INTO MisAttachList (table_m,Grid_FieldName,excel_idxname,idxnum,attachUrl,attachSize,attachMime,attachName, wdate, lastupdate)
SELECT 'car_parts' AS table_m, 'photos' AS Grid_FieldName, 'id' AS excel_idxname, fileable_id AS idxnum
,real_path AS attachUrl, file_size as attachSize, mime AS attachMime, file_name AS attachName, created_at, updated_at
from files WHERE real_path NOT IN 
(SELECT attachUrl FROM MisAttachList WHERE table_m='car_parts')
ORDER BY id;
-- 2-2) MisAttachList 의 midx  업데이트하기
UPDATE MisAttachList AS main
JOIN (
    -- 1. 각 idxnum 그룹별로 최대 idx 값을 찾는 서브쿼리
    SELECT
        idxnum,
        MAX(idx) AS max_idx_value
    FROM
        MisAttachList
    -- midx가 NULL인 레코드만 대상으로 지정합니다.
    -- (단, midx가 NULL이 아닌 레코드도 업데이트 대상에 포함하려면 WHERE 절을 제거하세요)
    WHERE
        midx IS NULL
    GROUP BY
        idxnum
) AS sub
ON
    main.idxnum = sub.idxnum
SET
    main.midx = sub.max_idx_value
WHERE
    main.midx IS NULL;

-- 2-3) car_parts 의 photos_midx 업데이트 시키기 
UPDATE car_parts AS cp
JOIN MisAttachList AS mal
    -- 연결 조건: car_parts.id = MisAttachList.idxnum
    -- MisAttachList의 table_m 값이 'car_parts'인 경우만 조인 대상에 포함
ON 
    cp.id = mal.idxnum AND mal.table_m = 'car_parts'
SET
    -- 업데이트 대상: car_parts.photos_midx에 MisAttachList.midx 값을 설정
    cp.photos_midx = mal.midx
WHERE
    -- 업데이트 조건: 현재 photos_midx 값이 NULL인 레코드만 업데이트
    cp.photos_midx IS NULL;
    
-- 2-4) car_parts 의 photos 업데이트 시키기 
UPDATE car_parts AS cp
JOIN (
    -- 1. midx별로 attachName을 '@AND@'로 연결하는 서브쿼리
    SELECT
        midx,
        GROUP_CONCAT(attachName ORDER BY attachName ASC SEPARATOR '@AND@') AS photos_list
    FROM
        MisAttachList
    GROUP BY
        midx
) AS attached_photos
ON
    -- 연결 조건: car_parts의 photos_midx와 MisAttachList의 midx 일치
    cp.photos_midx = attached_photos.midx
SET
    -- 업데이트 대상: car_parts.photos에 연결된 파일 목록 설정
    cp.photos = attached_photos.photos_list
WHERE
    -- 업데이트 조건: photos_midx 값이 채워져 있고, photos 필드가 아직 NULL인 레코드만 업데이트
    cp.photos IS NULL AND cp.photos_midx IS NOT NULL;



-- ----------------------- 프론트에서 첨부파일을 추가했을 경우를 대비해서 아래 쿼리 작동 -----------



-- ---------------------------------------------------------------------
-- MisAttachList와 car_parts 간의 midx 불일치 및 중복 오류를 수정하는 쿼리입니다.
-- (MisAttachList에 대해 midx가 2개 이상 사용된 경우, 큰 값으로 통일합니다.)
-- ---------------------------------------------------------------------

-- 1. [핵심 서브쿼리] midx 중복 오류 그룹을 찾아 최대 midx 값을 추출
DROP TEMPORARY TABLE IF EXISTS temp_midx_fix;
CREATE TEMPORARY TABLE temp_midx_fix AS
SELECT
    idxnum,
    MAX(midx) AS correct_midx
FROM
    MisAttachList
WHERE
    table_m = 'car_parts'  
GROUP BY
    idxnum
HAVING
    COUNT(DISTINCT midx) > 1;

-- ---------------------------------------------------------------------
-- 2. [car_parts 업데이트 - 1순위] MisAttachList의 오류 그룹을 찾아
--    car_parts.photos_midx를 최대 midx 값으로 먼저 업데이트
-- ---------------------------------------------------------------------

UPDATE car_parts AS cp
JOIN temp_midx_fix AS tf
    -- 조인 조건: car_parts.id = 오류그룹.idxnum
ON 
    cp.id = tf.idxnum
SET
    -- 업데이트: photos_midx를 그룹의 최대 midx 값으로 설정
    cp.photos_midx = tf.correct_midx
WHERE
    -- 현재 photos_midx 값이 오류 그룹 내의 작은 midx 값일 경우에만 업데이트
    -- (현재 photos_midx 값이 max_midx가 아닌 경우 업데이트)
    cp.photos_midx != tf.correct_midx;

-- ---------------------------------------------------------------------
-- 3. [MisAttachList 업데이트 - 2순위] MisAttachList의 midx 오류를 최대 midx 값으로 통일
-- ---------------------------------------------------------------------

UPDATE MisAttachList AS mal_main
JOIN temp_midx_fix AS tf
ON
    -- 조인 조건: idxnum은 일치하지만, midx 값이 최대 midx 값과 다른 레코드
    mal_main.idxnum = tf.idxnum AND mal_main.midx != tf.correct_midx
SET
    -- 업데이트: midx를 그룹의 최대 midx 값으로 통일
    mal_main.midx = tf.correct_midx;

-- ---------------------------------------------------------------------
-- 4. [photos 업데이트 - 최종] photos_midx 값을 기반으로 attachName을 연결하여 car_parts.photos 필드를 업데이트
-- ---------------------------------------------------------------------
UPDATE car_parts AS cp
JOIN (
    -- 1. midx별로 attachName을 '@AND@'로 연결하는 서브쿼리
    SELECT
        midx,
        GROUP_CONCAT(attachName ORDER BY attachName ASC SEPARATOR '@AND@') AS photos_list
    FROM
        MisAttachList
    GROUP BY
        midx
) AS attached_photos ON cp.photos_midx = attached_photos.midx
-- 2. temp_midx_fix 임시 테이블과 조인하여 수정이 필요한 레코드를 식별
JOIN temp_midx_fix AS tf ON cp.idxnum = tf.idxnum
SET
    -- photos_midx를 임시 테이블의 'correct_midx'로 수정
    cp.photos_midx = tf.correct_midx,
    -- photos 컬럼을 새로 계산된 'photos_list'로 업데이트
    cp.photos = attached_photos.photos_list
WHERE
    cp.photos IS NULL                                           -- 1. photos 컬럼이 NULL인 경우
    OR cp.photos != attached_photos.photos_list                 -- 2. photos 컬럼의 값이 새로운 photos_list와 다른 경우
    OR cp.photos_midx != tf.correct_midx;                       -- 3. 현재 photos_midx가 임시 테이블의 correct_midx와 다른 경우 (핵심 수정 조건)



";
	execSql($appSql);


    }

}
//end list_json_init



function save_updateQueryBefore() {

	global $sql, $sql_prev, $sql_next, $key_value;
	global $result, $updateList, $upload_idx;

	//아래는 업데이트 쿼리에 특정쿼리를 더 추가합니다.
$sql = $sql . " 

UPDATE car_parts set updated_at=CURRENT_TIMESTAMP, updated_by=3 where id=$key_value;

 UPDATE car_parts AS cp
JOIN v_mis_parts_cate_tree AS vc
  ON cp.part_cate_idx = vc.idx
SET cp.part_cate_fullname = vc.full_name
WHERE cp.part_cate_idx IS NOT NULL AND cp.part_cate_idx != 0 
and cp.id=$key_value;

UPDATE car_parts AS cp
JOIN v_mis_parts_storage_tree AS vs
  ON cp.storage_cate_idx = vs.idx
SET cp.storage_cate_fullname = vs.full_name
WHERE cp.storage_cate_idx IS NOT NULL AND cp.storage_cate_idx != 0 
and cp.id=$key_value;


";



}
//end save_updateQueryBefore



function save_updateAfter() {

	global $initList, $updateList,$saveList, $kendoCulture, $afterScript, $base_domain, $key_value;

	//print_r($initList);
	//print_r($saveList);
	$init_grade_a = (int)$initList['grade_a'];
	$init_grade_b = (int)$initList['grade_b'];
	$init_grade_c = (int)$initList['grade_c'];

	$save_grade_a = (int)$saveList['grade_a'];
	$save_grade_b = (int)$saveList['grade_b'];
	$save_grade_c = (int)$saveList['grade_c'];

	$virtual_fieldQngrade_a = (int)$saveList['virtual_fieldQngrade_a'];
	$virtual_fieldQngrade_b = (int)$saveList['virtual_fieldQngrade_b'];
	$virtual_fieldQngrade_c = (int)$saveList['virtual_fieldQngrade_c'];

	$sql = "
DELETE FROM files WHERE fileable_id=$key_value;
INSERT INTO files (fileable_type, fileable_id, file_name, saved_name, real_path, file_size, mime
, STORAGE, TYPE, created_at, updated_at)
SELECT 'App\\Models\\CarPart' AS fileable_type, idxnum AS fileable_id
, attachName AS file_name, REPLACE(attachUrl,'/storage/','') AS saved_name
, attachUrl AS real_path, attachSize AS file_size
, case when RIGHT(attachName,4)='.jpg' OR RIGHT(attachName,4)='jpeg' then 'image/jpeg'
when RIGHT(attachName,4)='.png' then 'image/png' ELSE 'application/zip' END AS mime
,'public' AS STORAGE
, case when RIGHT(attachName,4)='.jpg' OR RIGHT(attachName,4)='jpeg' then 'image'
when RIGHT(attachName,4)='.png' then 'image' ELSE 'zip' END AS type
, wdate AS created_at, lastupdate AS updated_at
 from MisAttachList WHERE table_m='car_parts' AND idxnum=$key_value
 ORDER BY idx; 
";

if($virtual_fieldQngrade_a!=0 || $virtual_fieldQngrade_b!=0 || $virtual_fieldQngrade_c!=0) {
	$sql = $sql . "
	update car_parts set grade_a=ifnull(grade_a,0)+$virtual_fieldQngrade_a, grade_b=ifnull(grade_b,0)+$virtual_fieldQngrade_b, grade_c=ifnull(grade_c,0)+$virtual_fieldQngrade_c where id=$key_value;
	INSERT INTO mis_parts_add_log (car_parts_id, grade_a_add, grade_b_add, grade_c_add, grade_a, grade_b, grade_c, remark)
	select id, $virtual_fieldQngrade_a, $virtual_fieldQngrade_b, $virtual_fieldQngrade_c, grade_a, grade_b, grade_c, '수량추가' from car_parts where id=$key_value;
	";
} else if($init_grade_a!=$save_grade_a || $init_grade_b!=$save_grade_b || $init_grade_c!=$save_grade_c) {
	$sql = $sql . "
	INSERT INTO mis_parts_add_log (car_parts_id, grade_a_add, grade_b_add, grade_c_add, grade_a, grade_b, grade_c, remark)
	select id, $virtual_fieldQngrade_a, $virtual_fieldQngrade_b, $virtual_fieldQngrade_c, grade_a, grade_b, grade_c, '재고수정' from car_parts where id=$key_value;
	";
}

if(requestVB('click_id')=='virtual_fieldQnprint_request') {
	$sql = $sql . "
	update car_parts set print_request_time=NOW(), print_response_time=null where id=$key_value and ifnull(storage_cate_idx,0)>0;
	";
}
//echo $sql;exit;
	execSql($sql);

	//print_r($updateList);


}
//end save_updateAfter



function save_writeQueryBefore() {
	//$viewList: 내용전체 array     $saveList: 저장내역 array(aliasName 으로 표현됨)      $updateList: 최종업데이트될 내역(실제필드이름으로 표현됨)
	//$newIdx: 입력시 생성된 자동증가번호     $sql: 입력쿼리문      $sql_prev: 입력쿼리문 앞에 붙을 쿼리문     $sql_next: 입력실행 뒤에 붙을 쿼리문
	global $full_siteID, $base_root, $real_pid, $misJoinPid, $logicPid, $parent_idx;
    global $key_aliasName, $key_value, $actionFlag, $viewList, $saveList, $updateList, $sql, $sql_prev, $sql_next, $newIdx;

$sql = $sql . " 
 UPDATE car_parts set created_at=CURRENT_TIMESTAMP where id=$newIdx;

 UPDATE car_parts AS cp
JOIN v_mis_parts_cate_tree AS vc
  ON cp.part_cate_idx = vc.idx
SET cp.part_cate_fullname = vc.full_name
WHERE cp.part_cate_idx IS NOT NULL AND cp.part_cate_idx != 0 
and cp.id=$newIdx;

UPDATE car_parts AS cp
JOIN v_mis_parts_storage_tree AS vs
  ON cp.storage_cate_idx = vs.idx
SET cp.storage_cate_fullname = vs.full_name
WHERE cp.storage_cate_idx IS NOT NULL AND cp.storage_cate_idx != 0 
and cp.id=$newIdx;


";

}
//end save_writeQueryBefore



function save_writeAfter() {

	global $base_root, $real_pid, $misJoinPid, $logicPid, $parent_idx;
    global $key_aliasName, $key_value, $saveList, $saveUploadList, $viewList, $deleteList;
    global $Grid_Default, $actionFlag, $misSessionUserId, $newIdx;
    global $afterScript;

	$sql = "
DELETE FROM files WHERE fileable_id=$newIdx;
INSERT INTO files (fileable_type, fileable_id, file_name, saved_name, real_path, file_size, mime
, STORAGE, TYPE, created_at, updated_at)
SELECT 'App\\Models\\CarPart' AS fileable_type, idxnum AS fileable_id
, attachName AS file_name, REPLACE(attachUrl,'/storage/','') AS saved_name
, attachUrl AS real_path, attachSize AS file_size
, case when RIGHT(attachName,4)='.jpg' OR RIGHT(attachName,4)='jpeg' then 'image/jpeg'
when RIGHT(attachName,4)='.png' then 'image/png' ELSE 'application/zip' END AS mime
,'public' AS STORAGE
, case when RIGHT(attachName,4)='.jpg' OR RIGHT(attachName,4)='jpeg' then 'image'
when RIGHT(attachName,4)='.png' then 'image' ELSE 'zip' END AS type
, wdate AS created_at, lastupdate AS updated_at
 from MisAttachList WHERE table_m='car_parts' AND idxnum=$newIdx
 ORDER BY idx; 
";
//echo $sql;exit;
	execSql($sql);

}
//end save_writeAfter

?>