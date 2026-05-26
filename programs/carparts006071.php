<?php

function misMenuList_change() {

    global $actionFlag, $gubun, $parent_idx, $real_pid, $logicPid, $result;
	global $misSessionPositionCode, $flag, $list_check_hidden, $allFilter, $misSessionUserId;

	if(($actionFlag=='list' || $flag=='read')) {
        
        if(InStr($allFilter,'"field":"toolbar_qqProname"')>0) {
            $project_info = splitVB(splitVB($allFilter,'[{"operator":"eq","value":"')[1],'","field":"toolbar_qqProname"')[0];
            $sdate = splitVB($project_info,' | ')[0];
            $project_name = splitVB($project_info,' | ')[1];
            $sql2 = "SELECT idx, sdate, edate FROM mis_projects WHERE project_name='$project_name' AND sdate like '$sdate%'";
            $result2 = allreturnSql($sql2);
        } else {
            $sdate = '2026-01-01';
            $edate = '2026-06-30';
            $project_name = '';
            $sql2 = "SELECT min(sdate) as sdate, max(edate) as edate FROM mis_projects ";
            $result2 = allreturnSql($sql2);
        }

		if(count($result2)==0) {
			$resultMessage = '존재하지 않는 프로젝트입니다.';
            $resultCode = "fail";
		} else {

            //$project_top_idx = $result2[0]['idx'];
            $sdate = $result2[0]['sdate'];
            $edate = $result2[0]['edate'];


            // 문자열을 DateTime 객체로 변환합니다.
            $begin = new DateTime($sdate);
            $end = new DateTime($edate);

            // 시작일부터 종료일 당일까지 포함하기 위해 간격을 설정합니다.
            $end->modify('+1 day'); 
            $interval = new DateInterval('P1D');
            $daterange = new DatePeriod($begin, $interval, $end);

            $i_start = array_search("virtual_fieldQnd1", array_column($result, 'aliasName')) - 1;
            $i_end = array_search("virtual_fieldQnd131", array_column($result, 'aliasName')) - 1;
            $i = $i_start;
            
$all_holidays = [
    // --- 2025년 ---
    "2025-01-01", // 신정
    "2025-01-28", "2025-01-29", "2025-01-30", // 설날 연휴
    "2025-03-01", "2025-03-03", // 삼일절 & 대체공휴일
    "2025-05-05", "2025-05-06", // 어린이날 & 부처님오신날 & 대체공휴일
    "2025-06-06", // 현충일
    "2025-08-15", // 광복절
    "2025-10-03", // 개천절
    "2025-10-05", "2025-10-06", "2025-10-07", "2025-10-08", // 추석 연휴 & 대체공휴일
    "2025-10-09", // 한글날
    "2025-12-25", // 성탄절

    // --- 2026년 ---
    "2026-01-01", 
    "2026-02-16", "2026-02-17", "2026-02-18", 
    "2026-03-01", "2026-03-02", // 삼일절 대체
    "2026-05-05", "2026-05-24", "2026-05-25", // 어린이날 & 부처님오신날 대체
    "2026-06-06", 
    "2026-08-15", "2026-08-17", // 광복절 대체
    "2026-09-24", "2026-09-25", "2026-09-26", 
    "2026-10-03", "2026-10-05", // 개천절 대체
    "2026-10-09", 
    "2026-12-25",

    // --- 2027년 ---
    "2027-01-01",
    "2027-02-06", "2027-02-07", "2027-02-08", "2027-02-09", // 설날 연휴 & 대체
    "2027-03-01", 
    "2027-05-05", "2027-05-13", // 어린이날 & 부처님오신날
    "2027-06-06",
    "2027-08-15", "2027-08-16", // 광복절 대체
    "2027-09-14", "2027-09-15", "2027-09-16", 
    "2027-10-03", "2027-10-04", // 개천절 대체
    "2027-10-09", "2027-10-11", // 한글날 대체
    "2027-12-25",

    // --- 2028년 ---
    "2028-01-01",
    "2028-01-26", "2028-01-27", "2028-01-28", 
    "2028-03-01", 
    "2028-05-02", "2028-05-05", // 부처님오신날 & 어린이날
    "2028-06-06",
    "2028-08-15", 
    "2028-10-02", "2028-10-03", "2028-10-04", "2028-10-05", // 추석 연휴 & 개천절 & 대체
    "2028-10-09",
    "2028-12-25",

    // --- 2029년 ---
    "2029-01-01",
    "2029-02-12", "2029-02-13", "2029-02-14", 
    "2029-03-01", 
    "2029-05-05", "2029-05-07", // 어린이날 대체
    "2029-05-20", "2029-05-21", // 부처님오신날 대체
    "2029-06-06",
    "2029-08-15",
    "2029-09-21", "2029-09-22", "2029-09-23", "2029-09-24", // 추석 & 대체
    "2029-10-03",
    "2029-10-09",
    "2029-12-25"
];
            $dd0 = 99;
            foreach ($daterange as $date) {
                // 요일 확인 (1: 월요일, 6: 토요일, 7: 일요일)
                $dayOfWeek = $date->format('N'); 
                $pdate = $date->format('Y-m-d');
                // 1(월) ~ 5(금) 사이인 경우만 출력
                if (in_array($pdate, $all_holidays) || $dayOfWeek > 5) {
                    //echo "오늘은 즐거운 휴일입니다!";
                    $sql = "
                    delete from mis_holidays where hdate='$pdate';
                    insert into mis_holidays (hdate,wdater) values ('$pdate', '$misSessionUserId');
                    ";

                    //echo $sql;
                    execSql($sql);

                } else {
                    ++$i;
                    // 한국어 요일 표시를 위한 배열
                    $weekDays = ["", "월", "화", "수", "목", "금", "토", "일"];
                    $dayName = $weekDays[$dayOfWeek];

                    $dd = ($date->format('d'))*1;
                    $title = '';
                    if($dd<$dd0) {
                        $title = $date->format('Y-m');
                    }
                    $title = $title . ',' . $dd . '<br>' . $dayName;

                    $result[$i]["Grid_Columns_Title"] = $title;
                    $result[$i]["Grid_Columns_Width"] = '0.2';
                    $result[$i]["Grid_Select_Tname"] = '';
                    $result[$i]["Grid_Select_Field"] = "(select is_checked from mis_projects_days where project_idx=table_m.idx and pdate='$pdate')";
                    //if($i<=78) 
                    setcookie($result[$i]["aliasName"],  $pdate, 0, "/");
                    $dd0 = $dd;
                    //echo $dd . $dayName;
                }
                
                
            }
            ++$i;
            for($i2=$i;$i2<=$i_end+1;$i2++) {
                $result[$i2]["Grid_Columns_Width"] = '-1';
            } 
//print_r($result);exit;

        }

		
	}

 
}
//end misMenuList_change



function pageLoad() {

    global $actionFlag;

    if($actionFlag=="list") { 
        ?>
<style>

.k-tooltip-content {
     position: relative;
     top:7px;
}
html.invert-mode body[topsite="mis"] .k-tooltip-content {
    text-shadow: 
    -1px -1px 0 #000,  
     1px -1px 0 #000,
    -1px  1px 0 #000,
     1px  1px 0 #000;
}
/* 툴팁 본체 스타일 */
.k-tooltip {
    width: auto !important;
    min-width: 0 !important;
    background: transparent !important;
    border: 0;
    box-shadow: none!important;
}

/* 툴팁 내부 텍스트 스타일 */
.k-tooltip-content {
    color: #2c3e50 !important; /* 차분한 다크 네이비 */
    font-size: 13px !important;
    font-weight: 600 !important;
    white-space: nowrap !important;
    border: 0;
}

/* 툴팁 아래 꼬리표(Callout) 제거 (더 세련된 느낌을 위해) */
.k-callout {
    display: none !important;
}

.k-i-close:before {
    display: none;
}




.k-checkbox:checked::before {
    -webkit-transform: none;
}
input.k-checkbox {
    background: #FEFF00!important;
    border-color: #FEFF00!important;
}

/* 글자가 막대기에 가려지지 않도록 강조 */
td[data-field="vsQnfull_name"]::before {
    content: attr(data-value) "%"; /* % 숫자를 표시하고 싶을 때 */
    position: absolute;
    right: 10px;
    font-size: 13px!important;
    color: #333;
    font-weight: bold;
    z-index: 2;
}




.k-filter-row th, .k-grid-header th.k-header {
    white-space: normal;
}

td.ctl_check.editorStyle {
    padding: 0;
}
input.k-checkbox {
    padding: 13px;
    -webkit-appearance: none;
  appearance: none;
  
  /* 체크박스 외형 설정 */
  width: 20px;
  height: 20px;
  border: 2px solid #ccc;
  border-radius: 3px;
  background-color: #fff;
  cursor: pointer;
  outline: none;
  
  /* 부드러운 색상 전환 */
  transition: background-color 0.2s ease, border-color 0.2s ease;
  
  /* 위치 조정을 위해 필요한 경우 사용 */
  vertical-align: middle;
  position: relative;
}
input.k-checkbox:checked {
    background-color: #BFBFBF !important;
    border-color: #BFBFBF !important;
}
span[aria-controls="toolbar_qqProname_listbox"] {
    min-width: 230px;
}
.union.toolbar_round_qqProname * {
    color: yellow;
    background: blueviolet;
}
</style>


        <script>
$('body').attr('onlylist','');	
//사용자 정의 함수 = 함수 이름은 변형하면 안됨. 내용만. 없어도 됨. ==============================
//데이타의 변형은 즉시 가능 = rowFunction
function columns_templete(p_dataItem, p_aliasName) {

    if(p_aliasName=='vsQnfull_name') {
		if(p_dataItem[p_aliasName]==null) {
			return '';	
		}
		R1 = p_dataItem[p_aliasName].split(' > ')[p_dataItem[p_aliasName].split(' > ').length-1];
        L1 = Left(p_dataItem[p_aliasName], p_dataItem[p_aliasName].length - R1.length);
        //RR = '<span style="color:transparent;">'+L1+'</span>'+R1;
        RR = '<span style="width:'+(p_dataItem['depth']-1)*20+'px;display:inline-block;"></span>'+iif(p_dataItem['depth']>1,'└','')+R1;
        return RR;
    } else {
        return p_dataItem[p_aliasName];
    }
}
function rowFunction_UserDefine(p_this) {
	if(p_this.autogubun) {
        p_this.table_upidxQnStationName = ":".repeat(iif(p_this.autogubun.length<8,8,p_this.autogubun.length)-8) + p_this.table_upidxQnStationName; 
	}
    //p_this.autogubun = 
    //"<a href=index.asp?gubun=" + p_this.idx + "&isMenuIn=Y target=_blank>[Go]</a> <a id='aid_" + p_this.idx + "' href=index.asp?RealPid=speedmis000266&idx=" + p_this.idx + "&isMenuIn=Y target=_blank>[Source]</a>?" 
    //+ p_this.autogubun; 
}
//목록에서 grid 로드 후 한번만 실행됨, 이때 처리해야할 일반 스크립트를 삽입합니다.
function listLogic_afterLoad_once()	{
	grid_remove_sort();    //그리드의 상단 정렬 기능 제거를 원할 경우.

	$('input#toolbar_qqProname').change( function() {
		location.href = status_url();
	});	
	
}
function listLogic_afterLoad_continue()	{
    if(location.href.split('toolbar_qqProname').length==1) {
        $('td.ctl_check.editorStyle').children().css("display","none");
    } 
	$('.k-animation-container').each( function() {
        if($(this).offset().top>230) {
            this.outerHTML = '';
        }
    });
}

function thisLogic_toolbar() {
    $("a#btn_1").text("자동정렬");
    $("li#btn_1_overflow").text("자동정렬");
    $("#btn_1").css("background", "#88f");
    $("#btn_1").css("color", "#fff");
    $("#btn_1").click( function() {
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "자동정렬";
        $("#grid").data("kendoGrid").dataSource.read();
        $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "";
    });

}


function location_href(sdate) {
    if(sdate!=undefined) {
        now_mm = Left(decodeURI(location.href).split('{"operator":"eq","value":"')[1],7);
        if(isDate(now_mm+'-01')==false) {
            now_mm = '';
        }
        location.href = replaceAll(status_url(),'{"operator":"eq","value":"'+now_mm,'{"operator":"eq","value":"'+Left(sdate,7));
    } else {
        location.href = status_url();
    }
};



//스타일 등의 변형은 로딩후에 가능 = rowFunctionAfter
function rowFunctionAfter_UserDefine(p_this) {

    if(location.href.split('toolbar_qqProname').length==1) {
        $('td.ctl_check.editorStyle').children().css("display","none");
    } else {
        obj = null;
        for(ii=1;ii<131;ii++) {
            ii_aliasName = 'virtual_fieldQnd'+ii;
            if(InStr(p_this.vsQnfull_name,'>')>0 && isDate(p_this.sdate) && isDate(p_this.edate)) {
                if(getCookie(ii_aliasName)<p_this.sdate || getCookie(ii_aliasName)>p_this.edate) {
                    $(getCellObj_idx(p_this[key_aliasName], ii_aliasName)).children().css("display","none");
                } else {
                    obj2 = $(getCellObj_idx(p_this[key_aliasName], ii_aliasName));
                    if(obj2.hasClass('ctl_check')==true) {
                        if(obj2.children()[0].checked==true) {
                            obj = obj2;
                        }
                    }
                }
            } else {
                $(getCellObj_idx(p_this[key_aliasName], ii_aliasName)).children().css("display","none");
            }

        }

        if(obj) { 

            
// 커스텀 예외 클래스 정의 (한 번만 실행)
if (typeof PreventTooltipHideException === 'undefined') {
    PreventTooltipHideException = function() {};
}

// Popup의 close 메서드 오버라이드 (한 번만 실행)
if (!kendo.ui.Popup.fn._originalClose) {
    kendo.ui.Popup.fn._originalClose = kendo.ui.Popup.fn.close;
    kendo.ui.Popup.fn.close = function(skipeffects) {
        try {
            return this._originalClose.call(this, skipeffects);
        } catch (err) {
            if (err instanceof PreventTooltipHideException) {
                return; // 닫기 방지
            }
            throw err;
        }
    };
}

var tooltip = obj.kendoTooltip({
    content: function() {
        return replaceAll(
            p_this.vsQnfull_name,
            p_this.vsQng4name + ' > ',
            ''
        ) 
    },
    position: "right",
    autoHide: false,
    callout: false,
    showOn: "click",
    animation: false,
    show: function(e) {
        var t = e.sender;
        var popup = t.popup.element;

        // 내부 클릭 완전 차단 (이벤트 버블링/기본 동작 막기)
        popup.on('mousedown click', function(ev) {
            ev.stopImmediatePropagation();
            ev.preventDefault();
            ev.stopPropagation();
            return false;
        });

        // 닫기 버튼만 동작 (수동 hide)
        popup.find('.tooltip-close').on('click', function(ev) {
            ev.stopImmediatePropagation();
            ev.preventDefault();
            ev.stopPropagation();
            t.hide();
            return false;
        });

        // 추가 안전장치: hide 이벤트에서 예외 던지기 (outside click 무시)
        t.bind('hide', function(hideE) {
            throw new PreventTooltipHideException();
        });

        var targetOffset = obj.offset();  // 타겟 요소 위치
        popup.css({
            left: 0,  // 타겟 오른쪽 + 20px
            top: 0                     // 타겟 top + 110px
        });
    }
}).data("kendoTooltip");

setTimeout( function(p_obj) {
    tooltip.show(p_obj);
},2000,obj);


        }

    }

    setTimeout( function() {
        $('.k-animation-container').each( function() {
            if($(this).offset().top>230) {
                $(this).css('pointer-events', 'none');
            }
        });
    },2500);

    if(InStr(p_this.vsQnfull_name,'>')==0) {
        $(getCellObj_idx(p_this[key_aliasName], 'vsQnfull_name')).css("font-weight","bold");
        $(getCellObj_idx(p_this[key_aliasName], 'vsQnfull_name')).css("font-size","15px");
    }
    $(getCellObj_idx(p_this[key_aliasName], 'vsQnfull_name')).each(function() {
        // 1. td 안의 텍스트에서 숫자만 추출 (예: "75" 또는 "75%")
        var val = p_this.progress_rate;
        
        // 2. 0~100 사이로 값 제한
        if (val > 100) val = 100;
        if (val < 0) val = 0;

        // 3. CSS 변수 업데이트 및 속성 부여
        $(this).css('--progress', val + '%');
        $(this).attr('data-value', val); // CSS에서 숫자를 표시하기 위함
    });

}      
        </script>
        <?php 
    }
}
//end pageLoad



function list_json_init() {
    global $real_pid, $mis_join_pid, $logicPid, $parent_idx, $full_siteID, $MS_MJ_MY, $grid_load_once_event;
    global $flag, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript, $allFilter;
    if($flag=='read') { 
        if($grid_load_once_event=='N') {
            $appSql = "select count(idx) from mis_projects where sortG01='0' and use_yn=1 ";
            $cnt = onlyOnereturnSql($appSql);
            if($cnt==1) {
                if($MS_MJ_MY=='MY') {
                    $appSql = "call mis_projects_ordering_proc();";
                } else {
                    $appSql = "EXECUTE mis_projects_ordering_proc";
                }
                if(execSql($appSql)) {
                    $resultCode = "success";
                    $resultMessage = "자동정렬이 완료되었습니다. ";
                } else {
                    $resultCode = "fail";
                    $resultMessage = "자동정렬 처리가 실패하였습니다.";
                }
            } else {
                $resultCode = "fail";
                $resultMessage = "1레벨정렬값 중 0 으로 된 최상위 부서는 하나만 존재해야 합니다.";
            }

			$appSql = "
UPDATE mis_projects AS parent
JOIN (
    -- 하위 항목들의 sdate 최소값과 edate 최대값을 구하는 서브쿼리
    SELECT 
        LEFT(autogubun, 4) AS group_prefix, 
        MIN(NULLIF(sdate, '')) AS min_sdate, 
        MAX(NULLIF(edate, '')) AS max_edate
    FROM mis_projects
    WHERE use_yn = '1' 
      AND LENGTH(autogubun) > 4 
    GROUP BY LEFT(autogubun, 4)
) AS child_info ON parent.autogubun = child_info.group_prefix
SET 
    -- sdate: 기존값이 없거나(NULL/''), 새로운 최소값이 더 작을 때만 업데이트
    parent.sdate = CASE 
        WHEN parent.sdate IS NULL OR parent.sdate = '' THEN child_info.min_sdate
        WHEN child_info.min_sdate < parent.sdate THEN child_info.min_sdate
        ELSE parent.sdate 
    END,
    -- edate: 기존값이 없거나(NULL/''), 새로운 최대값이 더 클 때만 업데이트
    parent.edate = CASE 
        WHEN parent.edate IS NULL OR parent.edate = '' THEN child_info.max_edate
        WHEN child_info.max_edate > parent.edate THEN child_info.max_edate
        ELSE parent.edate 
    END
WHERE 
    parent.depth = 1 
    AND parent.use_yn = '1';
			";
			execSql($appSql);
        }
    }

}
//end list_json_init



function list_query() {

    global $real_pid, $mis_join_pid, $logicPid, $parent_idx;
    global $flag, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript;
    global $countQuery, $selectQuery, $idx_aliasName;

	//아래는 어떤 특정한 상황에 대한 적용예입니다.


}
//end list_query



function save_updateAfter() {

	global $updateList, $kendoCulture, $afterScript, $base_domain;

	$afterScript = "$('#btn_1').click();";

}
//end save_updateAfter



function save_writeBefore() {

    global $full_siteID, $base_root, $real_pid, $mis_join_pid, $logicPid, $parent_idx;
    global $key_aliasName, $key_value, $actionFlag, $updateList;

//print_r($updateList);
//exit;


	//print_r($updateList);
	//exit;

}
//end save_writeBefore



function save_writeAfter() {

    global $base_root, $real_pid, $mis_join_pid, $logicPid, $parent_idx;
    global $key_aliasName, $key_value, $saveList, $saveUploadList, $viewList, $deleteList;
    global $Grid_Default, $actionFlag, $misSessionUserId, $newIdx;
    global $afterScript,$MS_MJ_MY;


	$appSql = "select count(idx) from mis_projects where sortG01='0' and use_yn=1 ";
	$cnt = onlyOnereturnSql($appSql);

	if($cnt==1) {
        if($MS_MJ_MY=='MY') {
            $appSql = "call mis_projects_ordering_proc();";
        } else {
            $appSql = "EXECUTE mis_projects_ordering_proc";
        }
		if(execSql($appSql)) {
			$resultCode = "success";
			$resultMessage = "자동정렬이 완료되었습니다. ";
		} else {
			$resultCode = "fail";
			$resultMessage = "자동정렬 처리가 실패하였습니다.";
		}
	} else {
		$resultCode = "fail";
		$resultMessage = "1레벨정렬값 중 0 으로 된 최상위 부서는 하나만 존재해야 합니다.";
	}

	//입력 처리 후, 임의의 url 로 보내는 처리문입니다. 
    $afterScript = "alert('$resultMessage'); $('#btn_1').click();";
   
}
//end save_writeAfter



function textUpdate_sql() {

    global $strsql, $keyAlias, $keyValue, $thisValue, $oldText, $thisAlias, $resultCode, $resultMessage, $afterScript, $misSessionUserId;
    if(InStr($thisAlias,'virtual_fieldQnd')>0) {
        $project_idx = $keyValue;
        $pdate = getcookie($thisAlias);
        $sql = "
        delete from mis_projects_days where project_idx='$project_idx' ;
-- 1. 데이터 삽입 (시작일 ~ '2025-12-30')
INSERT INTO mis_projects_days (project_idx, pdate, is_checked, wdater)
WITH RECURSIVE DateRange AS (
    -- 시작점: mis_projects 테이블에서 sdate를 가져옴
    SELECT sdate AS pdate, idx AS p_idx
    FROM mis_projects 
    WHERE idx = $project_idx
    
    UNION ALL
    
    -- 재귀: 2025-12-30까지 하루씩 더함
    SELECT pdate + INTERVAL 1 DAY, p_idx
    FROM DateRange
    WHERE pdate < '$pdate'
)
SELECT '$project_idx', pdate, 'Y', '$misSessionUserId' -- is_checked와 wdater는 기본값 설정
FROM DateRange;

DELETE FROM mis_projects_days 
WHERE project_idx = '$project_idx' and '$thisValue'='N' and is_checked='Y'
  AND pdate = '$pdate'
  AND pdate = (
      SELECT MIN(pdate) 
      FROM (SELECT pdate FROM mis_projects_days WHERE project_idx = '$project_idx') AS temp
  );


-- 2. 공휴일 제외 (mis_holidays 테이블과 대조하여 삭제)
DELETE FROM mis_projects_days
WHERE project_idx = $project_idx
  AND pdate IN (SELECT hdate FROM mis_holidays);


        UPDATE mis_projects p
        LEFT JOIN (
            SELECT project_idx, COUNT(pdate) as done_cnt 
            FROM mis_projects_days 
            GROUP BY project_idx
        ) d ON p.idx = d.project_idx 
        SET p.progress_rate = LEAST(100, ROUND((IFNULL(d.done_cnt, 0) / NULLIF(p.day_cnt, 0)) * 100))
        WHERE p.idx = '$project_idx';


        update mis_projects set progress_rate=100 where idx='$project_idx' and edate='$pdate' and '$thisValue'='Y';

-- 최상위 프로젝트 진행률 업데이트   
UPDATE mis_projects p_parent
SET p_parent.progress_rate = (
    SELECT ROUND(AVG(p_child.progress_rate))
    FROM (SELECT * FROM mis_projects) as p_child -- MariaDB 같은 테이블 참조 제약 방지
    WHERE p_child.autogubun LIKE CONCAT(LEFT(p_parent.autogubun, 4), '%')
      AND LENGTH(p_child.autogubun) > 4
)
WHERE p_parent.idx = (
    -- 특정 idx를 통해 찾은 최상위 항목만 업데이트
    SELECT t.parent_idx FROM (
        SELECT sub.idx as parent_idx 
        FROM mis_projects sub 
        WHERE sub.autogubun = (SELECT LEFT(autogubun, 4) FROM mis_projects WHERE idx = '$project_idx')
    ) t
);
        ";
        execSql($sql);
        //echo $sql;
        
        $afterScript = "$('#btn_reload').click();";
    }
    if($thisAlias=='sdate' || $thisAlias=='edate') {
        $project_idx = $keyValue;
        $pdate = $thisValue;
        if($thisAlias=='sdate') {
            $strsql = $strsql . "
            delete from mis_projects_days where project_idx='$project_idx' and pdate<'$pdate';
            UPDATE mis_projects
            SET sdate = DATE_ADD(sdate, INTERVAL 1 DAY)
            WHERE idx='$project_idx' and sdate = '$pdate'
            AND EXISTS (
                SELECT 1 
                FROM mis_holidays 
                WHERE hdate = '$pdate'
            );
            ";
        } else if($thisAlias=='edate') {
            $strsql = $strsql . "
            delete from mis_projects_days where project_idx='$project_idx' and pdate>'$pdate';
            UPDATE mis_projects
            SET edate = DATE_ADD(edate, INTERVAL -1 DAY)
            WHERE idx='$project_idx' and edate = '$pdate'
            AND EXISTS (
                SELECT 1 
                FROM mis_holidays 
                WHERE hdate = '$pdate'
            );
            ";
        }
        $strsql = $strsql . "
        UPDATE mis_projects p
        SET p.day_cnt = (
            -- 1. 전체 기간 일수 계산 (끝날짜 포함을 위해 +1)
            DATEDIFF(p.edate, p.sdate) + 1 
            -- 2. 해당 기간 내에 포함된 휴일 수 차감
            - (
                SELECT COUNT(*)
                FROM mis_holidays h
                WHERE h.hdate BETWEEN p.sdate AND p.edate
            )
        )
        WHERE p.sdate IS NOT NULL AND p.edate IS NOT NULL and p.idx='$project_idx';
        ";
        
        //echo $sql;
        if($thisAlias=='sdate') {
            $afterScript = "location_href('$pdate');";
        } else {
            $afterScript = "location_href();";
        }
    }



}
//end textUpdate_sql

?>