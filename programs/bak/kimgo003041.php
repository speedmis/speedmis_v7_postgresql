<?php

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

	if($RealPid=='kimgo003041') {
		$result[0]['g09'] = $result[0]['g09'] . " and table_m.isLong=0";
	} else {
		$result[0]['g09'] = $result[0]['g09'] . " and table_m.isLong=1";
	}
 /*
	//아래는 MenuName 이라는 aliasName 에 대해 표시명을 바꾸는 예제임.
    $search_index = array_search("freeField01", array_column($result, 'aliasName'));
	if($search_index>=5 && ($ActionFlag=='view' || $ActionFlag=='modify')) {
		$sql = "select * from kimgoStyle_detail where midx=(select style_idx from kimgoMainList where idx='$idx')  order by SortElement,idx";
		$rr = allreturnSql($sql);
		if(count($rr)==10) {
			for($ii=0;$ii<10;$ii++) {
				$result[$search_index+$ii]['Grid_Columns_Title'] = $rr[$ii]['Grid_Columns_Title'];		
				$result[$search_index+$ii]['Grid_Columns_Width'] = $rr[$ii]['Grid_Columns_Width'];		
				$result[$search_index+$ii]['Grid_Schema_Type'] = $rr[$ii]['Grid_Schema_Type'];		
				$result[$search_index+$ii]['Grid_Items'] = $rr[$ii]['Grid_Items'];		
				$result[$search_index+$ii]['Grid_Schema_Validation'] = $rr[$ii]['Grid_Schema_Validation'];		
				$result[$search_index+$ii]['Grid_Align'] = $rr[$ii]['Grid_Align'];		
				$result[$search_index+$ii]['Grid_MaxLength'] = $rr[$ii]['Grid_MaxLength'];		
				$result[$search_index+$ii]['Grid_Default'] = $rr[$ii]['Grid_Default'];		
				$result[$search_index+$ii]['Grid_CtlName'] = $rr[$ii]['Grid_CtlName'];		
				$result[$search_index+$ii]['Grid_ListEdit'] = $rr[$ii]['Grid_ListEdit'];		
				$result[$search_index+$ii]['Grid_Alim'] = $rr[$ii]['Grid_Alim'];		
				$result[$search_index+$ii]['Grid_Pil'] = $rr[$ii]['Grid_Pil'];		
			}
		}


	}

    */
}
//end misMenuList_change



function pageLoad() {

    global $ActionFlag,$idx,$parent_idx,$gubun;
	global $MisSession_IsAdmin, $MisSession_UserID;

	$sql = "update kimgoMainList set isEnd=1 where isnull(향후계획,'')=''; exec kimgo_misReadList_Proc;";
	execSql($sql);
/*
	//특정상황에서 페이지를 이동시키는 예제입니다.
	if($ActionFlag=='list' && $parent_RealPid=='speedmis000028') {
		$target_parent_gubun = RealPidIntoGubun('speedmis001071');
		$url = "index.php?RealPid=$RealPid&parent_gubun=$target_parent_gubun&parent_idx=$parent_idx";
		re_direct($url);
	}
*/

        ?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" type="text/css">

<div id="choiceDialog" style="display: none;">
  <div class="dialog-content">
  </div>
</div>
<style>

a.open_attach {
	position: absolute;
    left: 96px;
    top: 5px;
    font-weight: bold;
}
	
li[tabrealpid="speedmis000979"] span.k-link {
    font-weight: bold;
}
a#btn_menuName, a#btn_menuTitle
, .subtitle.before_round_depG1
, .subtitle.before_round_comG1 
, .subtitle.before_round_dirG1
{
    display: none;
}
.k-tabstrip-items-wrapper {
    xdisplay: none !important;
}
div#round_table_mQmeommuilja,div#round_table_mQmnaeyong,div#round_table_mQmhyanghugyehoek,div#round_table_mQmmagamilja  {
	    position:absolute;
		top:-1000px;
	}
	div.before_round_eommuilja {
		width: 25%;
	}
	
	
.결재 {
    position: absolute;
    left: 82px;
    top: 4px;
    font-weight: bold;
}

	
.결재.대기 {
    pointer-events: all;
}	
.결재.승인 {
    color: blue;
}	
.결재.반려 {
    color: red;
}
/* 윈도우 외관 */
.k-window.fancy-shadow {
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
  background: linear-gradient(145deg, #ffffff, #f0f0f0);
}	
/* 컨텐츠 박스 */
#choiceDialog .dialog-content {
  padding: 24px;
  font-family: 'Segoe UI', 'Apple SD Gothic Neo', sans-serif;
  text-align: center;
  background: #fafafa;
}

/* 텍스트 */
#choiceDialog .dialog-content p {
  font-size: 20px;
  color: #333;
  margin-bottom: 20px;
}

/* 버튼 정렬 */
#choiceDialog .button-group {
  display: flex;
  justify-content: center;
  gap: 20px;
}

/* 버튼 스타일 */
#choiceDialog .k-button {
  padding: 12px 24px;
  border-radius: 10px;
  font-size: 16px;
  font-weight: bold;
  cursor: pointer;
  transition: all 0.3s ease;
}

#choiceDialog .k-button.k-primary {
  background: linear-gradient(to right, #3a8ef8, #6f7bf7);
  color: white;
  border: none;
}
#choiceDialog .k-button.k-primary:hover {
  background: #295ccc;
}

#choiceDialog .k-button.k-secondary {
  background: #e0e0e0;
  color: #333;
}
#choiceDialog .k-button.k-secondary:hover {
  background: #c5c5c5;
}	
</style>

        <script>
if($('a#back-forward.forward-nav')[0]==undefined) {
	$('a#back-forward').click();			
}			
	if(document.getElementById('ActionFlag').value=='view' || (document.getElementById('ActionFlag').value=='modify') && getUrlParameter('psize')!='5') {
		beforeunload_ignore();
		url = "addLogic_treat.php?RealPid="+document.getElementById('RealPid').value
		+"&idx="+document.getElementById('idx').value+"&question=get_gidx";
		gidx = ajax_url_return(url);

		re_url = "index.php?gubun="+document.getElementById('gubun').value
				+"&parent_gubun="+document.getElementById('gubun').value
				+"&parent_idx="+gidx
				+"&idx="+document.getElementById('idx').value+"&ActionFlag=modify&isAddURL=Y&psize=5";
		if(isMainFrame()==true) {
			re_url = re_url+'&isMenuIn=Y';
		}
alert(re_url);
		location.replace(re_url);
		
	} else if(typeof parent.select_list=='function') {

		if((document.getElementById('idx').value!=parent.select_list() || document.getElementById('parent_idx').value=='')
		   && document.getElementById('ActionFlag').value=='modify' || isMainFrame()==false && document.getElementById('ActionFlag').value=='list' && document.getElementById('parent_idx').value!='') {

			p_idx = parent.select_list();
			if(p_idx=='' && document.getElementById('ActionFlag').value=='list' && document.getElementById('parent_idx').value!='') {
				p_idx = document.getElementById('parent_idx').value;
			}
			url = "addLogic_treat.php?RealPid="+document.getElementById('RealPid').value
			+"&idx="+p_idx+"&question=get_gidx";
			gidx = ajax_url_return(url);
			if(gidx!='') {
				if(gidx==document.getElementById('parent_idx').value && p_idx*1<document.getElementById('idx').value*1) {
					//신규입력 후 수정페이지로 간주하여 이동안함.
					if(getUrlParameter('psize')==undefined) {
						parent.$('div#grid').data('kendoGrid').dataSource.read();
						//setTimeout("parent.grid_top_line_tr_select();",1500);
				        
						//re_url = location.href + '&psize=5';
						//setTimeout( function(p_re_url) {
							//beforeunload_ignore();
							//location.replace(re_url);
						//},2000,re_url);
					}
				} else {
			        
					parent.$('div#grid').data('kendoGrid').dataSource.read();
					if(parent.select_list()!='') {
						re_url = "index.php?gubun="+document.getElementById('gubun').value
						+"&parent_gubun="+document.getElementById('gubun').value
						+"&parent_idx="+gidx
						+"&idx="+p_idx+"&ActionFlag=modify&isAddURL=Y&psize=5";
					} else {
						
						re_url = "index.php?gubun="+document.getElementById('gubun').value
						+"&parent_gubun="+document.getElementById('gubun').value
						+"&parent_idx="+gidx
						+"&idx="+p_idx+"&ActionFlag=modify&isAddURL=Y&psize=5";
					}
					if(isMainFrame()==true) {
						re_url = re_url+'&isMenuIn=Y';
					}
	
					beforeunload_ignore();
					location.replace(re_url);
				}
			} else if(isMainFrame()==false && document.getElementById('ActionFlag').value=='list') {
				if(gidx!='') {
					
					parent.$('div#grid').data('kendoGrid').dataSource.read();
					re_url = "index.php?gubun="+document.getElementById('gubun').value
						+"&parent_gubun="+document.getElementById('gubun').value
						+"&parent_idx="+gidx
						+"&psize=5&ActionFlag=modify&idx="+gidx;
					
					if(isMainFrame()==true) {
						re_url = re_url+'&isMenuIn=Y';
					}

					beforeunload_ignore();
					
					location.replace(re_url);
				} else {
					re_url = "index.php?gubun="+document.getElementById('gubun').value
									+"&isMenuIn="+parent.document.getElementById('isMenuIn').value;
					beforeunload_ignore();
					parent.location.replace(re_url);
				}
			}
		}	
		
	}
			
			
$('select#depG1').change( function() {

    top1_value = this.value;
	
	if(document.getElementById('ActionFlag').value=='write') {
		if(top1_value=='') {
			$('input#chbAll')[0].checked = true;
			$('input#chbAll').click();
		} else if(top1_value=='272') {
			top1_value = '999';	
			if($('input#chbAll')[0].checked==false) {
				$('input#chbAll').click();
			}
			$('input#chbAll').click();
			$('input#chbAll').click();
		} else {
			$('input#chbAll')[0].checked = true;
			$('input#chbAll').click();

			txt = $(this).data('kendoDropDownList').text();
			var dropdown1 = $("#gyeoljae1").data("kendoDropDownList");
			var dropdown2 = $("#gyeoljae2").data("kendoDropDownList");
						
			dropdown1.dataSource.data([]);
			dropdown2.dataSource.data([]);

			document.querySelectorAll('div#virtual_fieldQnmisPildokMem ul.k-group li').forEach(li => {
			  const checkbox = li.querySelector('input[type="checkbox"]');

			  // 일단 전체 항목을 모두 체크 해제
			  if (checkbox) {
				checkbox.checked = false;
				li.setAttribute('aria-checked', 'false');
				li.classList.remove('k-selected');
			  }

			  txt1 = txt.split('.')[0];
			  if(li.innerText.split('>').length<=5) {
				if(li.innerText.includes('> ROOT >') || li.innerText.split('>').length<=3 && li.innerText.includes('> '+txt1+' >')) {
					if(checkbox) {
					checkbox.checked = true;
					li.setAttribute('aria-checked', 'true');
					li.classList.add('k-selected');

					dropdown1.dataSource.add({ table_gyeoljae1Qnusername: li.innerText, gyeoljae1: li.innerText.split(' | ')[0] });
					dropdown2.dataSource.add({ table_gyeoljae2Qnusername: li.innerText, gyeoljae2: li.innerText.split(' | ')[0] });


					// Kendo UI는 이벤트 트리거 필요할 수 있음
					checkbox.dispatchEvent(new Event('change', { bubbles: true }));
					}
				}

				if(txt.split('.').length>=2) {
					txt2 = txt.split('.')[1];
					if(li.innerText.split('>').length<=4 && li.innerText.includes('> '+txt2+' >')) {
						if (checkbox) {
						checkbox.checked = true;
						li.setAttribute('aria-checked', 'true');
						li.classList.add('k-selected');

						// Kendo UI는 이벤트 트리거 필요할 수 있음
						checkbox.dispatchEvent(new Event('change', { bubbles: true }));
						}
					}
				}

				if(txt.split('.').length>=3) {
					txt3 = txt.split('.')[2];
					if(li.innerText.split('>').length<=5 && li.innerText.includes('> '+txt3+' >')) {
						if (checkbox) {
						checkbox.checked = true;
						li.setAttribute('aria-checked', 'true');
						li.classList.add('k-selected');

						// Kendo UI는 이벤트 트리거 필요할 수 있음
						checkbox.dispatchEvent(new Event('change', { bubbles: true }));
						}
					}
				}
			   }
			});
			dropdown1.refresh();
			dropdown2.refresh();
			dropdown1.value("");
			dropdown2.value("");


		}
	}
	

    if(top1_value=='') {
        $('select#docPathID').data('kendoDropDownList').value('');
        $('select#docPathID').trigger('change');
    } else {
        top1_text = $('select#depG1').data('kendoDropDownList').text();
        doc_like = Mid(top1_text,4,50);
        obj_docPathID = $('select#docPathID').data('kendoDropDownList');
		docPathID_change = false;
        $('select#docPathID option').each(function() {
            if(InStr(this.innerText,doc_like)>0) {
                obj_docPathID.value(this.value);
                $('select#docPathID').trigger('change');
				console.log('docPathID change');
				docPathID_change = true;
                return false;
            }
        });
		if(docPathID_change==false) {
			obj_docPathID.value('');
			$('select#docPathID').trigger('change');
		}
    }

});


$('select#docPathID').change( function() {
    //docPathID 에 대한 처리
    docPathID_value = this.value;
    docPathID_text = this.options[this.selectedIndex].innerText;
    r_style_idx = docPathID_text.split(' | ')[1];
    if(r_style_idx==undefined) {
        r_style_idx = '';
    } 
    $('select#style_idx').data('kendoDropDownList').value(r_style_idx);
    
});
//웹소스 디테일에서 템플릿으로 체크한 항목에 대해 출력내용을 변경할 수 있습니다. 이때 목록 또는 본문내용에 동일하게 적용됩니다.
//row 갯수만큼 실행됩니다.


$('select#comG1').change( function() {
//2단계

    top1_value = this.value;

    top2_DS = $('select#comG2').data('kendoDropDownList').dataSource;
    top2_url = top2_DS.transport.options.read.url;
    top2_url = top2_url.split('&upValue=')[0]+"&upValue='"+top1_value+"'";
    top2_DS.transport.options.read.url = top2_url;
    top2_DS.read();

    top3_DS = $('select#comG3').data('kendoDropDownList').dataSource;
    top3_url = top3_DS.transport.options.read.url;
    top3_url = top3_url.split('&upValue=')[0]+"&upValue='999999'";
    top3_DS.transport.options.read.url = top3_url;
    top3_DS.read();

    if(top1_value=='') {
        $('select#docPathID').data('kendoDropDownList').value('');
        $('select#docPathID').trigger('change');
    } else {
        top1_text = $('select#comG1').data('kendoDropDownList').text();
        doc_like = top1_text;
        obj_docPathID = $('select#docPathID').data('kendoDropDownList');
        $('select#docPathID option').each(function() {
            if(InStr(this.innerText,doc_like)>0) {
                obj_docPathID.value(this.value);
                $('select#docPathID').trigger('change');
                return false;
            }
        });
    }

});
$('select#comG2').change( function() {
//3단계
    top2_value = this.value;

    top3_DS = $('select#comG3').data('kendoDropDownList').dataSource;
    top3_url = top3_DS.transport.options.read.url;
    top3_url = top3_url.split('&upValue=')[0]+"&upValue='"+top2_value+"'";
    top3_DS.transport.options.read.url = top3_url;
    top3_DS.read();
    
    
    top1_text = $('select#comG1').data('kendoDropDownList').text();
    top2_text = $('select#comG2').data('kendoDropDownList').text();
    doc_like = top1_text+'.'+top2_text;
    obj_docPathID = $('select#docPathID').data('kendoDropDownList');
    $('select#docPathID option').each(function() {
        if(InStr(this.innerText,doc_like)>0) {
            obj_docPathID.value(this.value);
            $('select#docPathID').trigger('change');
            return false;
        } else {
            obj_docPathID.value('');
            $('select#docPathID').trigger('change');
            return false;
        }
    });

});		


$('select#dirG1').change( function() {
//2단계

    top1_value = this.value;

    top2_DS = $('select#dirG2').data('kendoDropDownList').dataSource;
    top2_url = top2_DS.transport.options.read.url;
    top2_url = top2_url.split('&upValue=')[0]+"&upValue='"+top1_value+"'";
    top2_DS.transport.options.read.url = top2_url;
    top2_DS.read();

    top3_DS = $('select#dirG3').data('kendoDropDownList').dataSource;
    top3_url = top3_DS.transport.options.read.url;
    top3_url = top3_url.split('&upValue=')[0]+"&upValue='999999'";
    top3_DS.transport.options.read.url = top3_url;
    top3_DS.read();


});
$('select#dirG2').change( function() {
//3단계
    top2_value = this.value;

    top3_DS = $('select#dirG3').data('kendoDropDownList').dataSource;
    top3_url = top3_DS.transport.options.read.url;
    top3_url = top3_url.split('&upValue=')[0]+"&upValue='"+top2_value+"'";
    top3_DS.transport.options.read.url = top3_url;
    top3_DS.read();


});


		
function columns_templete(p_dataItem, p_aliasName) {



    if(p_aliasName=='gidx') {

		readDate = p_dataItem['qqReadDate'];
		if(readDate==null || readDate=='') {
			rValue = p_dataItem[p_aliasName]+ '<br><i class="fa fa-envelope"></i>';
		} else {
			rValue = p_dataItem[p_aliasName]+ '<br><i title="idx:'+p_dataItem['idx']+', ' + readDate + ' 읽음" class="fa fa-envelope-open"></i>';
		}
		
		
        return rValue;
    } else if(p_aliasName=='zmongnaeyong') {
		rValue = p_dataItem[p_aliasName];
		if(document.getElementById('ActionFlag').value=='list') {
			cc1 = 'blue';cc2 = 'blue';
			if($('#kendoTheme_css').attr('kendoTheme')=='highcontrast') {
				cc1 = 'yellow';cc2 = 'yellow';
			}
			if(rValue.split('결재1').length>1) {
				temp1 = rValue.split(';')[0];
				temp1 = temp1.split('/')[0];
				if(p_dataItem['qqTreatDate1']!=null) {
					cc1 = 'block';
					if($('#kendoTheme_css').attr('kendoTheme')=='highcontrast') {
						cc1 = 'gray';
					}
				}
				rValue = replaceAll(rValue, temp1, '<span style="color:'+cc1+';">'+temp1+'</span>');
			}
			if(rValue.split('결재2').length>1) {
				temp1 = rValue.split('>/')[1];
				if(p_dataItem['qqTreatDate2']!=null) {
					cc2 = 'block';
					if($('#kendoTheme_css').attr('kendoTheme')=='highcontrast') {
						cc2 = 'gray';
					}
				}
				rValue = replaceAll(rValue, temp1, '<span style="color:'+cc2+';">'+temp1+'</span>');
			}
		} 
        return rValue;
    } else if(p_aliasName=='qqConforms') {
		if(document.getElementById('ActionFlag').value=='list') {
			rValue = p_dataItem[p_aliasName];
			rValue = replaceAll(rValue, '¶', '<br>');
		}
        return rValue;
    } else if(p_aliasName=='table_mQmeommuilja') {
		if(document.getElementById('ActionFlag').value!='list') {
			rValue = p_dataItem[p_aliasName];
		} else {
			if(Left(p_dataItem[p_aliasName],4)==new Date().getFullYear()) {
				var rValue = Right(p_dataItem[p_aliasName],5);
			} else {
				var rValue = p_dataItem[p_aliasName];
			}
		}
        return rValue;
    } else if(p_aliasName=='virtual_fieldQnwork_end') {
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

    if(p_this.zjongnyo=='종료') {
        $(getCellObj_idx(p_this[key_aliasName], "zjongnyo")).closest('tr').css("color","green");
        //$(getCellObj_idx(p_this[key_aliasName], "zjongnyo")).closest('tr').css("font-weight","lighter");
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
		$("a#btn_1").text("업무일기준인쇄");
		$("#btn_1").click( function() {
			location.href = 'index.php?gubun=3052&isMenuIn=auto';
		});
		$("a#btn_2").text("작성일기준인쇄");
		$("#btn_2").click( function() {
			location.href = 'index.php?gubun=3053&isMenuIn=auto';
		});
	}

	if(document.getElementById('ActionFlag').value=='modify') {
		$("a#btn_1").text("참조하여 신규입력");
		$("#btn_1").click( function() {
			parent.go_mis_gubun('3041','index.php?gubun=3041&idx='+document.getElementById('idx').value+'&ActionFlag=write&isMenuIn=Y&isAddURL=Y&isMenuIn=Y&addParam=new');

		});
		$("a#btn_2").text("닫기");
		$("#btn_2").click( function() {
			if(parent.$("#grid").data("kendoGrid")) parent.$("#grid").data("kendoGrid").dataSource.read();

				  var splitter = parent.$("#horizontal").data("kendoSplitter");
				  var size = splitter.size(".k-pane:first");
				  if(size!="100%") {
					  parent.setCookie('lastSize_right',size,1000);
					  splitter.size(".k-pane:first", "100%");
				  }

		});
	}	
}

//목록에서 grid 로드 후 한번만 실행됨, 이때 처리해야할 일반 스크립트를 삽입합니다.
function listLogic_afterLoad_once()	{
	
	/*
	if($('a#btn_leftVibile')[0]==undefined) {
		$('a#btn_fullScreen').before(`
		<a role="button" class="k-button k-button-icon k-toolbar-first-visible" 
	id="btn_leftVibile"><span class="k-icon k-i-k-icon k-i-thumbnails-left"></span></a>
		`);
	}
		$('a#btn_leftVibile').click(function() {
            if ($('div#example-sidebar').css('position') == 'absolute')
                setCookie('left_menu_hide', '1', 1000);
            if (getCookie('left_menu_hide') == '1') {
                setCookie('left_menu_hide', '0', 1000);
                $('style#left_menu_hide')[0].innerHTML = ''
            } else {
                setCookie('left_menu_hide', '1', 1000);
                $('style#left_menu_hide')[0].innerHTML = str_left_menu_hide;
                setTimeout(function() {
                    $('div#toolbar').click()
                })
            }
            $(document).resize()
        });
	
	$('a#btn_leftVibile').click();
	*/
	if($('a#back-forward.forward-nav')[0]==undefined) {
		$('a#back-forward').click();	
	}
	//common_bottom_ready();
	
	
	
	
	//grid_remove_sort();    //그리드의 상단 정렬 기능 제거를 원할 경우.
	var dropdown = $("#toolbar_virtual_fieldQnaddDep").data("kendoDropDownList");

	dropdown.bind("change", function(e) {
	  setTimeout( function() {
		 location.href = location.href;
	  },0);
	});
}
			
//목록에서 grid 로드 후 데이터 로딩마다 실행됨, 이때 처리해야할 일반 스크립트를 삽입합니다.		
function listLogic_afterLoad_continue()	{
	
	
}

function open_attach() {
	dirG1_text = Mid($('select#dirG1').data('kendoDropDownList').text(),4,20);
	dirG2_text = Mid($('select#dirG2').data('kendoDropDownList').text(),4,20);
	dirG3_text = Mid($('select#dirG3').data('kendoDropDownList').text(),4,20);
	if(dirG3_text!='') {
		filter1 = dirG3_text;
		filter2 = dirG2_text;
	} else if(dirG2_text!='') {
		filter1 = dirG2_text;
		filter2 = dirG1_text;
	} else if(dirG1_text!='') {
		filter1 = dirG1_text;
		filter2 = 'ROOT';
	} else {
		alert('문서폴더가 선택되지 않았습니다.');
		return;
	}
	url = 'index.php?gubun=3059&isMenuIn=Y&allFilter=[{"operator":"contains","value":"'+filter1+'","field":"poldeomyeong"},{"operator":"contains","value":"'+filter2+'","field":"table_upidxQnpoldeomyeong"}]';
	window.open(url);
}
	
			
//내용조회 또는 수정/입력 페이지 로딩이 끝나는 순간 한번만 실행됨, 이때 처리해야할 일반 스크립트를 삽입합니다.
function viewLogic_afterLoad() {


	$('label#dirG1_label').after('<a class="open_attach" href="javascript:;" onclick="open_attach();">첨부할파일</a>');


	$('#btn_write').remove();
	if($('#btn_refWrite')[0]) {
		$('#btn_refWrite')[0].innerHTML = replaceAll($('#btn_refWrite')[0].innerHTML,'참조입력','향후계획 계획추가');
		$('#btn_refWrite').css('background','#187ebf');
		$('#btn_refWrite').css('color','#fff');
   
	}
	if(document.getElementById('ActionFlag').value=='write') {
		$('input#eommuilja').data('kendoDatePicker').value(today10());	
		
		if(getUrlParameter('addParam')=='new') {
			document.getElementById('naeyong').value = '';
			document.getElementById('hyanghugyehoek').value = '';
			$('input#biyong').data('kendoNumericTextBox').value(0);
			$('input#eommusigan').data('kendoNumericTextBox').value(0);
		} else if(document.getElementById('idx').value!='0') {
			document.getElementById('naeyong').value = document.getElementById('hyanghugyehoek').value;
			document.getElementById('hyanghugyehoek').value = '';
			$('input#biyong').data('kendoNumericTextBox').value(0);
			$('input#eommusigan').data('kendoNumericTextBox').value(0);
		}
	}

	if($('select#docPathID')[0].value!='') {
		$('li[tabalias="zdainamingmunseosangse"]').show();
	} else {
		$('li[tabalias="zdainamingmunseosangse"]').hide();
	}
	if(document.getElementById('ActionFlag').value=='write' || document.getElementById('ActionFlag').value=='modify') {
		if($('select#depG1')[0].value!='') {
			$('select#depG1').change();
		}
	}
}	
			
  function 승인(p_idx) {
	const dialog = $("#choiceDialog").data("kendoWindow");
	console.log("A 선택");
	url = "addLogic_treat.php?RealPid=speedmis000979&widx="+p_idx+"&question=confirm&select=Y";
	temp = ajax_url_return(url);	  
	dialog.close();
	location.replace(status_view_url());
  }

  function 반려(p_idx) {
	const dialog = $("#choiceDialog").data("kendoWindow");
	console.log("B 선택");
	url = "addLogic_treat.php?RealPid=speedmis000979&widx="+p_idx+"&question=confirm&select=N";
	temp = ajax_url_return(url);
	dialog.close();
	location.replace(status_view_url());
  }
			

	$("#choiceDialog").kendoWindow({
	  title: "🌈 커스텀 선택창",
	  modal: true,
	  visible: false,
	  resizable: false,
	  draggable: true,
	  width: "420px",
	  height: "200px",
	  animation: {
		open: {
		  effects: "fade:in scale:in",
		  duration: 500
		},
		close: {
		  effects: "fade:out",
		  duration: 300
		}
	  },
	  open: function () {
		$(".k-window").addClass("fancy-shadow");
	  },
	  close: function () {
		//this.destroy(); // 창 닫을 때 제거
	  }
	});
function confirms(p_this) {
	p_idx = $(p_this).attr('idx');
	
	const dialog = $("#choiceDialog").data("kendoWindow");
	dialog.title(p_this.innerText + " 진행");
  	dialog.content(`
    <div class="dialog-content">
    <p>무엇을 선택하시겠습니까?</p>
    <div class="button-group">
      <button class="k-button k-primary" onclick="승인(`+p_idx+`);">승인</button>
      <button class="k-button" onclick="반려(`+p_idx+`);">반려</button>
    </div>
  </div>
  `).center().open();
	

}


function before_read_idx() {


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
			if(isMainFrame()==true) {
				re_url = re_url+'&isMenuIn=Y';
			}

			location.replace(re_url);
			return 'stop';
		}

}
function viewLogic_afterLoad_continue() {
	


	if(document.getElementById('ActionFlag').value=='modify') {
		console.log('getUrlParameter(psize)');
		console.log(getUrlParameter('psize'));
		if(resultAll.d.results[0]['style_idx']>0) {
			$('div#round_docPathID').css('pointer-events','none');
		} else if(Left(resultAll.d.results[0]['wdate'],10)==today10()) {
			$('div#round_docPathID').css('pointer-events','all');
		} else {
			$('div#round_docPathID').css('pointer-events','none');
		}

		qqMyAuth = resultAll.d.results[0]['qqMyAuth'];
		qqMyTreat = resultAll.d.results[0]['qqMyTreat'];
		qqMyTreatDate = resultAll.d.results[0]['qqMyTreatDate'];

		qqTreat1 = resultAll.d.results[0]['qqTreat1'];
		qqTreatDate1 = resultAll.d.results[0]['qqTreatDate1'];
		qqTreat2 = resultAll.d.results[0]['qqTreat2'];
		qqTreatDate2 = resultAll.d.results[0]['qqTreatDate2'];

		if(qqTreat1!=null && qqTreat1!='') {
			$('div#round_gyeoljae1').append(`
			<div class='결재 `+qqTreat1+`'>`+qqTreat1+`: `+Mid(qqTreatDate1,6,20)+`</div>
			`);
		}
		if(qqTreat2!=null && qqTreat2!='') {
			$('div#round_gyeoljae2').append(`
			<div class='결재 `+qqTreat2+`'>`+qqTreat2+`: `+Mid(qqTreatDate2,6,20)+`</div>
			`);
		}


		if(qqMyAuth!=null && qqMyAuth!='') {
			if(qqMyTreat!=null && qqMyTreat!='') {
			} else {
				nums = Right(qqMyAuth,1);
				$('div#round_gyeoljae'+nums).css('pointer-events','all');
				$('div#round_gyeoljae'+nums+' *').css('pointer-events','none');
				$('div#round_gyeoljae'+nums).append(`
				<div class='결재 대기'><a class="k-button k-state-active" onclick="confirms(this);" idx="`+document.getElementById('idx').value+`">결재하기</a></div>
				`);
			}
		}


		if(resultAll.d.results[0].style_idx>0) {
			$('li[tabrealpid="kimgo003045"]').show();
			var dropdown = $("#docPathID").data("kendoDropDownList");
			var itemText = dropdown.text().split('.')[dropdown.text().split('.').length-1].split(' |')[0];
			$('li[tabrealpid="kimgo003045"] span.k-link').text(itemText);
		} else {
			$('li[tabrealpid="kimgo003045"]').hide();
		}
	}

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

    if(InStr($filter,'toolbar_virtual_fieldQnaddDep')>0) { 
		
        $v = splitVB(splitVB($filter,"toolbar_virtual_fieldQnaddDep eq '")[1]," | ")[0];
        $appSql = "
insert into kimgoMainList (depG1, wdater) values ('$v', N'$MisSession_UserID');
			";

        execSql($appSql);
                   
    }
	if($flag=='read') {
		$sql = "
			exec kimgo_misReadList_Proc;
		";
		execSql($sql);
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
	global $full_siteID, $base_root, $RealPid, $MisJoinPid, $logicPid, $parent_idx, $addParam, $MisSession_UserID;
    global $key_aliasName, $key_value, $ActionFlag, $viewList, $saveList, $updateList, $sql, $sql_prev, $sql_next, $newIdx;

	//아래와 같이 입력 sql 문을 직접 변경할수 있습니다.
	if($addParam=='new') {
		$sql_next = " update kimgoMainList set gidx=$newIdx where idx=$newIdx;";
	} else {
		$sql_next = " 
		update kimgoMainList set gidx=$newIdx where idx=$newIdx and gidx=0;
		update kimgoMainList set isEnd=1 where gidx=(select gidx from kimgoMainList where idx=$newIdx) and idx<$newIdx and isEnd=0;
		update kimgoMainList set 마감일자=convert(char(10),getdate(),120) where gidx=(select gidx from kimgoMainList where idx=$newIdx) and idx<$newIdx and isdate(마감일자)=0;
		";
	}
	$담당ID = $updateList['write_id'];
	$결재1 = $updateList['결재1'];
	$결재2 = $updateList['결재2'];
	$depG1 = $updateList['depG1'];

	$all_members = ",$결재1,$결재2,".$saveList['virtual_fieldQnmisPildokMem'].',';
	$all_members = replace($all_members,",$담당ID,",',');
	$all_members = replace($all_members,',,',',');
	$all_members = replace($all_members,',,',',');
	$all_members = replace($all_members,',,',',');
	$all_members = replace($all_members,',,',',');
	$all_members = replace($all_members,',,',',');
	$all_members = replace(",$all_members,",',,','');
	if($all_members==',') {
		$all_members = '';
	}


	if($결재1=='' && $결재2!='') {
		exit('결재라인을 지정하시려면, 결재1부터 채워주세요.');
	}
	if($결재1!='' && $결재1==$결재2) {
		exit('결재1과 결재2가 동일합니다.');
	}

	if($all_members=='') {
		//exit('필독 또는 결재할 멤버가 없습니다. 또는 자기자신만 해당되는 경우입니다.');
	}

	if($결재1!='') {
		$sql_next = $sql_next . "
delete MisReadList where widx=$newIdx and userid='$담당ID' and RealPid='kimgo003041' and 자격='필독';
insert into MisReadList (widx, userid, RealPid, 자격) values ($newIdx, '$결재1', 'kimgo003041', '결재1');
		";
	}
	if($결재2!='') {
		$sql_next = $sql_next . "
delete MisReadList where widx=$newIdx and userid='$담당ID' and RealPid='kimgo003041' and 자격='필독';
insert into MisReadList (widx, userid, RealPid, 자격) values ($newIdx, '$결재2', 'kimgo003041', '결재2');
		";
	}
	

}
//end save_writeQueryBefore



function save_writeAfter() {

    global $base_root, $RealPid, $MisJoinPid, $logicPid, $parent_idx;
    global $key_aliasName, $key_value, $saveList, $saveUploadList, $viewList, $deleteList;
    global $Grid_Default, $ActionFlag, $MisSession_UserID, $newIdx;
    global $afterScript;

	//입력 처리 후, 임의의 url 로 보내는 처리문입니다. 
    $afterScript = 'location.href = "index.php?gubun=3041&parent_gubun=3041&parent_idx='.$newIdx.'&idx='.$newIdx.'&ActionFlag=modify&isAddURL=Y&psize=5&isMenuIn=Y";';
   
}
//end save_writeAfter



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