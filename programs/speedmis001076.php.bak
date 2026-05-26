<?php

/*info

이 파일을 ms code 등을 이용해서 직접 편집할 수 있음.
SpeedMIS 를 이용하면 함수별로 분할된 화면으로 편집이 가능함.

주의: 상단의 주석은 꼭 주석시작기호에 info 로 시작. 끝은 info 에 주석종료기호로 해야함. 아니면 소실될 수 있음.

info*/



function pageLoad() {

    global $ActionFlag, $kendoTheme, $MisSession_IsAdmin, $addDir;
    global $addParam;

?>
<style>
	div#round_virtual_fieldQnaddLogic_treat {
		height: auto !important;
	}

</style>

<?php
    if($ActionFlag=='list' && $MisSession_IsAdmin=='Y') { ?>


<style>

input#toolbarTxt_addLogic {
    min-width: 200px;
}
</style>	
    <script>
		
	
		
		
    function xthisLogic_toolbar() {

        
        
        $("#btn_1").text("전체파일 동기화");
        $("#btn_1").css("background", "#88f");
        $("#btn_1").css("color", "#fff");

        $("#btn_1").click( function() {
            
            $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "전체파일동기화";
            $("#grid").data("kendoGrid").dataSource.read();
            $("#grid").data("kendoGrid").dataSource.transport.options.read.data.app = "";
            
        });
    }

    </script>
    <?php } 


    if($ActionFlag=="view" || $ActionFlag=="modify") { 
    ?>
<style>

    a#btn_save, a#btn_saveClose, a#btn_saveView, li[tabid="speedmis000974"], li[tabid="speedmis000979"] {
        display: none!important;
    }
span.alim.k-checkbox {
    display: none;
}
	ul.k-tabstrip-items.k-reset > li {
		display: block!important;
	}
    ul.k-tabstrip-items.k-reset > li[tabalias="readApp"], ul.k-tabstrip-items.k-reset > li[tabalias="commentApp"], ul.k-tabstrip-items.k-reset > li[tabalias="wdate"], ul.k-tabstrip-items.k-reset > li[tabalias="viewPrint"] {
        display: none!important;
    }    
input#toolbarTxt_addLogic {
    min-width: 200px;
}
    body[isMenuIn="N"][isPopup="Y"] div.k-content.k-state-active {
        height: calc(100vh - 80px);
    }
    body[isMenuIn="N"] div.k-content.k-state-active {
        height: calc(100vh - 80px);
    }
    body[isMenuIn="Y"] div.k-content.k-state-active {
        height: calc(100vh - 138px);
    }
    div[data-role="tabstrip"] div.k-state-active {
        display: inline-block;
        margin-left: 0!important;
        width: calc(100% - 394px);
        padding-right: 0!important;
    }
    ul.k-tabstrip-items.k-reset {
        overflow-y: auto;
		overflow-x: hidden;
        height: calc(100vh - 80px);
        width: 370px;
        margin-left:0px!important;
        margin-right:0px!important;
    }
    body[isMenuIn="Y"] ul.k-tabstrip-items.k-reset {
        overflow-y: auto;
        height: calc(100vh - 124px);
    }
    body[isMenuIn="Y"] div#exampleWrap {
        overflow: hidden;
    }
    .round_textarea.code .textarea {
        height: calc(100% - 6px);
    }
    .CodeMirror {
        width: calc(100% - 18px);
    }

    .k-content.k-state-active {
        min-height: auto!important;
    }

    @media (min-width: 1200px) {
        div.round_textarea.code {
            width: calc(100% - 0px);
        }
    }

</style>

<input id="addLogic_init_lines" type="hidden"/>

<script>
<?php if($addParam=='pre') { ?>
$('title')[0].innerText = 'TEST개발중:'+$('title')[0].innerText;	
<?php } ?>	
function pop_phpCodePaste(p_field) {
    
    //$("input#checkSubmit").click();

    if($('#virtual_fieldQn'+p_field)[0].value!="") {
        if(!confirm("아래의 소스를 지우고, 예제소스로 대체하시겠습니까?")) return false;
    }
    if(p_field=='info') keyword = '상단 주석문'; else keyword = p_field;

    var r = ajax_url_return('https://www.speedmis.com/_mis/list_json.php?flag=read&RealPid=speedmis001040&$orderby=idx&recently=N&allFilter=[{"operator":"contains","value":"'+keyword+'","field":"zjemok"}]&$callback=jQuery112407752819346753541_1599384564376');
    if(InStr(r, '"__count": "0"')>0) {
        alert('관련 예제소스가 없습니다.');
        return false;
    }
    r = r.split('"results":')[1].split(',"lastupdater":')[0]+'}]';
    r = JSON.parse(r)[0].phpCode;
    
    $('#virtual_fieldQn'+p_field).next().get(0).CodeMirror.setValue(r);
   

}


function pop_phpCodeExample(p_field) {
    if(p_field=='info') keyword = '상단 주석문'; else keyword = p_field;
    url = 'index.php?RealPid=speedmis001040&allFilter=[{"operator":"contains","value":"'+keyword+'","field":"zjemok"}]&isSplitter=Y';
    parent_popup_jquery(url, $('input#MenuName')[0].value+ "[" + $('input#idx')[0].value + "] 의 ");
}

function viewLogic_afterLoad_continue() {

	$('div#round_addLogic .CodeMirror').remove();

	var interval = setInterval(function() {
  
		if ($('div#round_addLogic .CodeMirror').length==0) {
	    	console.log("await..")
  		} else {
			clearInterval(interval);
			

			if($('iframe#commentApp')[0]) $('iframe#commentApp')[0].outerHTML = "";
			if($('iframe#readApp')[0]) $('iframe#readApp')[0].outerHTML = "";
			

			var all_logic = $('#frm textarea[id]').next().get(0).CodeMirror.getValue();

			if($('input#addLogic_init_lines')[0].value.split('.')[0]!=$('input#idx').val()) {
				$('input#addLogic_init_lines')[0].value = $('input#idx').val() + '.' + all_logic.split('\n').length;
			}

			$('#frm textarea[id]').each( function(i) {
				if(i==0) {
					$('#addLogic').next().get(0).CodeMirror.setValue(all_logic);
					$($('.k-tabstrip-items .k-item')[i+1]).find('span.k-link')[0].style.setProperty("color", "#ffffff", "important");
					$($('.k-tabstrip-items .k-item')[i+1]).find('span.k-link')[0].style.setProperty("background", theme_active(), "important");
				} else if(i==1) {
					check_fun_start = InStr(all_logic,'/*info');
					check_fun_end = InStr(all_logic,'info*/');

					if(check_fun_start==0 && check_fun_end>0) {
						stop();
						alert("상단 주석문의 종료선언이 없습니다.");
						setTimeout( function() {
							$('a#btn_close').click();
						});
						return false;
					}

					if(check_fun_start>0 && check_fun_end==0) {
						stop();
						alert("상단 주석문의 시작선언이 없습니다.");
						setTimeout( function() {
							$('a#btn_close').click();
						});
						return false;
					}

					if(check_fun_start>0) {
						v = Mid(all_logic, check_fun_start, check_fun_end - check_fun_start + 6);
						$(this).next().get(0).CodeMirror.setValue(v);
						this.value = v;
						$(this).next().get(0).CodeMirror.clearHistory()
                        $($('.k-tabstrip-items .k-item')[i+1]).find('span.k-link')[0].style.color = "#ffffff";
                        $($('.k-tabstrip-items .k-item')[i+1]).find('span.k-link')[0].style.background = theme_active();
					} else {
						$($('.k-tabstrip-items .k-item')[i+1]).find('span.k-link')[0].style.color = theme_active();
                        $($('.k-tabstrip-items .k-item')[i+1]).find('span.k-link')[0].style.background = theme_buttonActive();
					} 
				} else {
					fun_name = this.id.split('Qn')[1];
					check_fun_start = InStr(all_logic,'function '+fun_name+'(');
					check_fun_end = InStr(all_logic,'//end '+fun_name);

					if(check_fun_start==0 && check_fun_end>0) {
						stop();
						alert(fun_name + " 함수에 대해 종료선언은 있으나, 시작선언이 없습니다.");
						setTimeout( function() {
							$('a#btn_close').click();
						});
						return false;
					}

					if(check_fun_start>0 && check_fun_end==0) {
						stop();
						alert('//end '+fun_name + " 함수에 대해 시작선언은 있으나, 종료선언이 없습니다.");
						setTimeout( function() {
							$('a#btn_close').click();
						});
						return false;
					}

					if(check_fun_start>0) {
						v = Mid(all_logic, check_fun_start, check_fun_end - check_fun_start + fun_name.length+6);
						$(this).next().get(0).CodeMirror.setValue(v);
						this.value = v;
						$(this).next().get(0).CodeMirror.clearHistory()
                        $($('.k-tabstrip-items .k-item')[i+1]).find('span.k-link')[0].style.color = "#ffffff";
                        $($('.k-tabstrip-items .k-item')[i+1]).find('span.k-link')[0].style.background = theme_active();
					} else {
						$($('.k-tabstrip-items .k-item')[i+1]).find('span.k-link')[0].style.color = theme_active();
						$($('.k-tabstrip-items .k-item')[i+1]).find('span.k-link')[0].style.background = theme_buttonActive();
					} 

				}

			});
			
			


			$('li[tabid]').click( function() {

				obj = $('label[for="'+$(this).attr('tabid')+'"]');
				if($(this).attr('tabid').split('Qn').length==2) {
					if(obj.css('z-index')!='99') {
						obj.css('z-index','99');
						keyword = $(this).attr('tabid').split('Qn')[1];

						obj[0].innerHTML = obj[0].innerHTML + ' | 예제소스 <a id="a_paste" href="javascript:;" style="z-index: 99;display: inline-block;position: absolute;margin-left: 20px;" onclick="pop_phpCodePaste(\''+keyword+'\',this);">붙여넣기</a>'
											+ ' <a id="a_example" href="javascript:;" style="z-index:99;display: inline-block;position: absolute;margin-left: 85px;" onclick="pop_phpCodeExample(\''+keyword+'\');">| 열기</a>';

					}
				}

				li_addLogic_click();


			});

			
		
		}
		
	}, 100);


}

$('a#btn_reload').click( function() {
    if($('input#ActionFlag')[0].value!='view') {
        if(!confirm("새로고침을 하시면 마지막으로 저장된 값으로 복원되지만, 편집되돌리기(ctrl+z) 기능은 초기화됩니다. 진행할까요?")) return false;
    }
});


function li_addLogic_click() {

    <?php if($ActionFlag=="view") { ?>
    return false;
    <?php } ?>
	//서버로직 전체탭을 클릭할 때, 여러탭의 코딩을 하나로 모아준다.
	all_logic = '<' + '?php';
	$('#frm textarea[id]').each( function(i) {
		v = $(this).next().get(0).CodeMirror.getValue();
        $($('.k-tabstrip-items .k-item')[i+1]).find('span.k-link')[0].style.fontWeight = 'normal';
		if(this.id.split('virtual_fieldQn').length==2 && v!='') {
			all_logic = all_logic + "\n\n" + v + "\n\n";
            $($('.k-tabstrip-items .k-item')[i+1]).find('span.k-link')[0].style.color = "#ffffff";
            $($('.k-tabstrip-items .k-item')[i+1]).find('span.k-link')[0].style.background = theme_active();
		} else {
            if(i>0) {
                $($('.k-tabstrip-items .k-item')[i+1]).find('span.k-link')[0].style.color = theme_active();
                $($('.k-tabstrip-items .k-item')[i+1]).find('span.k-link')[0].style.background = theme_buttonActive();
            }
		}
	});
    setTimeout( function() {
        if($('.k-tabstrip-left>.k-tabstrip-items .k-state-active span.k-link')[0]) {
            //$('.k-tabstrip-left>.k-tabstrip-items .k-state-active span.k-link')[0].style.setProperty ("color", "#ffffff", "important");
            $('.k-tabstrip-left>.k-tabstrip-items .k-state-active span.k-link')[0].style.fontWeight = 'bold';
        }
    });
	
	all_logic = all_logic + '?'+'>';

	all_logic = replaceAll(all_logic, '<'+'?'+'php'+'?'+'>', '');
    
	$('#addLogic').next().get(0).CodeMirror.setValue(all_logic);

}


function thisLogic_toolbar() {

	$('div[data-role="tabstrip"]').kendoTabStrip({
		tabPosition: "left",
	});
	
	<?php if($ActionFlag=="modify") { ?>
	
    $("#btn_1").text("저장완료");
    $("#btn_1").css("background", "#88f");
    $("#btn_1").css("color", "#fff");
    $("#btn_1").click( function() {
        
        li_addLogic_click();

        var all_logic = $('textarea#addLogic').next().get(0).CodeMirror.getValue();
        var delta_lines = $('input#addLogic_init_lines')[0].value.split('.')[1]*1 - all_logic.split('\n').length;
		
        if(delta_lines >= 10) {
            if(!confirm("코딩의 라인수가 로딩될 때보다 " + delta_lines + " 줄었습니다. 그래도 저장하시겠습니까?"))
            return false;
        }
		$('input#addLogic_init_lines')[0].value = $('input#idx').val() + '.' + all_logic.split('\n').length;
        $('a#btn_save').click();
        x.stop; //고의에러. 팝업에서 저장후 닫힘을 방지하게 됨.

    });
	
	<?php } ?>
}

</script>
    <?php 
    }
    
}
//end pageLoad



function list_json_load() {
    global $base_root, $RealPid, $MisJoinPid, $logicPid, $parent_idx, $addDir;
    global $flag, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript;
    global $data, $key_aliasName, $parent_alias, $selectQuery, $keyword, $menuName;
    global $addParam;
    $pre = '';
    if($addParam=='pre') {
        $pre = '.pre';
    }


    if($flag=="view" || $flag=="modify") { 
        if($idx!="") {
            $db_addLogic = json_decode($data)[0]->addLogic;
            $file_addLogic = ReadTextFile($base_root . "/_mis_addLogic/" . $idx . $pre . ".php");
            if($file_addLogic!='') {
                $new_data = json_decode($data);
                $new_data[0]->addLogic = $file_addLogic;
                $data = json_encode($new_data);
            } else if($db_addLogic!='' && $pre=='') {
                $db_addLogic = replace($db_addLogic, '@_' . 'q;', '?');
                $new_data = json_decode($data);
                $new_data[0]->addLogic = $db_addLogic;
                $data = json_encode($new_data);
            }

        }
    }
}
//end list_json_load



function save_updateReady() {
    global $base_root, $RealPid, $MisJoinPid, $logicPid, $parent_idx, $addDir;
    global $key_aliasName, $key_value, $saveList, $viewList, $deleteList;
    global $addParam;
    $pre = '';
    if($addParam=='pre') {
        $pre = '.pre';
    }
    
    if($key_value!="") {

        $addLogic = $saveList["addLogic"];
        $destination = $base_root . "/_mis_addLogic/" . $key_value . $pre . '.php';
        if(file_exists($destination)) {
            copy($destination, replace(replace($destination, "/_mis_addLogic", "/_mis_addLogic_autoSave")
                , $key_value, $key_value . "_" . date("Ymd_His")));
            unlink($destination);
        }
        if($addLogic!="") {
            WriteTextFile($destination, $addLogic);
        }
        $addLogic = replace($addLogic, '?', '@_' . 'q;');
        $saveList["addLogic"] = $addLogic;


    }
}
//end save_updateReady



function save_updateQueryBefore() {

	global $sql, $sql_prev, $sql_next, $key_value;
	global $result, $updateList, $upload_idx;
    global $addParam;
    $pre = '';
    if($addParam=='pre') {
        $sql = '';
        $sql_prev = '';
        $sql_next = '';
    }

	


}
//end save_updateQueryBefore



function save_updateAfter() {

	global $updateList, $kendoCulture, $afterScript, $base_domain, $key_value, $full_site, $parent_idx, $base_root;

    //프로그램 변경에 따른 캐쉬파일 삭제 로직
    $gubun = RealPidIntoGubun($key_value);
    $path_pattern = "$base_root/_mis_cache/*P.$key_value.*";
    delete_cache_files($path_pattern);
    $path_pattern = "$base_root/_mis_cache/*gubun.$gubun.*";
    delete_cache_files($path_pattern);

}
//end save_updateAfter

?>