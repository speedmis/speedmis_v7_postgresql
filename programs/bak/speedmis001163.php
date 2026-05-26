<?php 


function MisMenuList_change() {

	//MisMenuList 테이블에 의한 설정값인 $result 를 바꾸는게 이 함수의 핵심기능
    global $actionFlag, $gubun, $parent_gubun, $parent_idx, $real_pid, $logicPid, $result;
	global $misSessionPositionCode, $flag;
	global $idx;


	if($flag=="view") {
		$search_index = array_search("va1", array_column($result, 'aliasName'));
		$result[$search_index]["Grid_Select_Tname"] = "";
		$result[$search_index]["Grid_Select_Field"] = "case when table_m.va1='레토리카' then 'Rhetorica' when table_m.va1='그라마티카' then 'Grammatica' when table_m.va1='로지카' then 'Logic' when table_m.va1='프리스카' then 'Prisca' else '' end";
	}
	

	//최종승인=ic3
	$ic3_index = gridAlias_into_SortElement($logicPid, "ic3")*1-1;
	if($misSessionPositionCode*1>10) $result[$ic3_index]["Grid_ListEdit"] = "";

}
//end MisMenuList_change


function save_updateQueryBefore() {
	global $sql, $key_value;
	$sql = $sql . " update edu_N_ghsa_new set ex5=convert(char(19),NOW(),120) where ifnull(ex5,'')='' and ifnull(ex3,'')='Y' and ifnull(ex2,'')='Y' and idx=$key_value";
	$sql = $sql . " update edu_N_ghsa_new set ex5='' where ifnull(ex5,'')<>'' and (ifnull(ex3,'')<>'Y' or ifnull(ex2,'')<>'Y') and idx=$key_value";


	//알림관련 : 업데이트전의 값과 업데이트된 값과 비교하여 알림을 보냄.
	global $result, $updateList, $upload_idx;
	if($updateList["ex2"]=="Y" && $updateList["ex3"]=="Y") {
		$temp = "select ifnull(ex2 + ex3,'') from edu_N_ghsa_new where idx=$upload_idx";
		$r = onlyOnereturnSql($temp);
		if($r!="YY") {
			$result["pushList"] = "gadmin,admin";
			$temp = "select gh0+'/'+gh3 from edu_N_ghsa_new where idx=$upload_idx";
			$r = onlyOnereturnSql($temp);
			$result["pushMsg"] = $r . " 학생의 학습계획표 승인이 요청되었습니다.";
			$result["afterScript"] = 'alert("정상적으로 제출되었습니다."); location.href = location.href;';
		}
	}


}
//end save_updateQueryBefore

function pageLoad() {

    global $actionFlag, $gubun, $parent_idx;


    
?>
<style>
	
div#viewPrintDiv input#udd_isuQSUs {
    width: 50px;
    text-align: center;
    margin: 2px 0px;
    font-size: 13px;
}
div#viewPrintDiv input#udd_gyehoekQSUs {
    border-bottom: 1px dashed #000!important;
}

a#btn_write, a#btn_refWrite, a#btn_saveView {
	display: none!important;
}

.viewPrint tr[firstline] > td:first-child input[type="checkbox"]:not([disabled]) {
    outline: 1px solid darkred;
}



	
</style>

<script>

function thisPage_options_pdf() {
    return {
        paperSize: "A4",
		scale: 0.77,
		landscape: false,
		margin: { left: "0.25cm", top: "1.0cm", right: "0.2cm", bottom: "0.4cm" }
    }
}
	
function columns_templete(p_dataItem, p_aliasName) {
    if(p_aliasName=="johoe" || p_aliasName=="jakseong") {
        this_RealPid = getID("RealPid").value;

        if(p_aliasName=="johoe") 
        var rValue = "<a href='index.php?RealPid="+this_RealPid+"&idx=" + p_dataItem["idx"] + "&isAddURL=Y' target='_blank' class='k-button'>조회</a>";
        
		else {
			if(p_dataItem["read_only_condition"]=="1") rValue = "";
        	else var rValue = "<a style='background: #88f; color: #fff;' href='index.php?RealPid="+this_RealPid+"&idx=" + p_dataItem["idx"] + "&ActionFlag=modify&isAddURL=Y' target='_blank' class='k-button'>작성</a>";
		}
        return rValue;
    } else {
        return p_dataItem[p_aliasName];
    }	
    
}<?php if($actionFlag=="view" || $actionFlag=="modify") { ?>

	
function thisLogic_toolbar() {

	$("a#btn_save").text("저장&제출");
	$("a#btn_save").css("background", "#3f3");

	<?php if($actionFlag=="modify") { ?>
	$("#btn_1").css("position","absolute");
	$("#btn_1").text("저장");
	$("#btn_1").click( function() {
		$("a#btn_save").attr("no_role","Y");
		$("a#btn_save").click();
		setTimeout( function() {
			$("a#btn_save").attr("no_role","");
		}, 1000);
	});
	setTimeout( function() {
		if($("a#btn_save").css("display")=="none") {
			$("#btn_1").css("display","none");
		} else {
			$("#btn_1").css("position","relative");
		}
	},1000);
	<?php } ?>


}	
	
//사용자 로직함수 : 사용자정의양식 상세가 있는 경우, 상세가 끝난 후 호출.
function viewLogic_afterLoad_viewPrint() {
	$('div#viewPrintDiv td:contains("standrd")').css('padding',0);
    $('div#viewPrintDiv td:contains("Applied")').css('padding',0);

    dataObj = getFrameObj("zhakseupgyehoekpyo").$('div#grid').data('kendoGrid').dataSource._data;

	if($('div#viewPrintDiv table td[colspan="6"]')[1]) $($('div#viewPrintDiv table td[colspan="6"]')[1]).closest('tr')[0].style.borderBottom = ".5pt solid black";

	<?php if($actionFlag=="view") { ?>

	viewPrintObj = $('tr[firstline][keyvalue]');

    for(i=0;i<dataObj.length;i++) {
        if(dataObj[i].pilsuyeobu!="Y") {
            $(viewPrintObj[i]).attr("seontaek","Y");
        };
    }
	
	if($('img.add_image').length==0) {
	   $('div.viewPrint').append('<img class="add_image" style="margin: 20px; width: 200px;" src="/_mis/img/speedmis_wide.png">');
	}
	//$('<tr><td align="right" colspan="13" style="border-bottom: .5pt solid black;">&nbsp;</td></tr>').insertBefore($($('tr[seontaek="Y"]')[0]));

	<?php } else { ?>

    viewPrintObj = $('tr[firstline][keyvalue] td:first-child input');

    for(i=0;i<dataObj.length;i++) {
        if(dataObj[i].pilsuyeobu=="Y") {
            $(getFrameObj("zhakseupgyehoekpyo").getCellObj_idx(dataObj[i].idx,'idx')).prev().find('input')[0].disabled = true;
            viewPrintObj[i].disabled = true;
            $(getFrameObj("zhakseupgyehoekpyo").getCellObj_idx(dataObj[i].idx,'idx')).prev().find('input')[0].title = "필수과목은 삭제할 수 없습니다.";
            viewPrintObj[i].title = "필수과목은 삭제할 수 없습니다.";

        };
    }

	$('input#ud_ex3,input#ud_ex2').change( function() {
		if($('input#ud_ex3')[0].checked && $('input#ud_ex2')[0].checked) {
			setTimeout( function() {
				alert('자녀서명, 부모서명이 모두 체크되었습니다. 상단의 [제출하기] 를 클릭하시면 제출처리가 됩니다.
제출처리 후에도 학습계획기간 동안 관리자의 승인이 없는 경우 수정이 가능합니다.');
			});
		}

	});

	<?php } ?>

	
	
}

	
	
function thisLogic_view_after_resultAll(p_resultAll) {
    
    //mis join 형태 이므로, 조회시 맞는 양식으로 이동시킴.
    var 과정 = p_resultAll.d.results[0].gh3;
    var 학적관리유형 = p_resultAll.d.results[0].gh5;
    var 언어 = p_resultAll.d.results[0].gh4;



}
	
<?php } ?>
	
</script>
<?php
}
//end pageLoad
?>