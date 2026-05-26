<?php

function pageLoad() {

	global $MisSession_UserID, $full_siteID, $ActionFlag;
	global $send_admin_mail, $telegram_bot_name, $telegram_bot_token, $full_site, $kendoCulture;
	if($full_siteID=='edu') {
?>
<script>

	location.href = 'index.php?gubun=1099&isMenuIn=auto';
	
</script>
<?php
		exit;
	}
	if($ActionFlag=="modify") {


		$cnt_allPush_member = 1*onlyOnereturnSql("select count(*) from MisGroup_Member where gidx=20 and userid='$MisSession_UserID';");
		
		?>
		<style>
a#btn_step1 {
    min-width: 300px;
}			
div#round_telegram_chat_id.nodata  > div > span.k-widget {
	left: 305px;	
}
div.before_round_allPush_YN, div#round_allPush_YN {
	display: none;
}
div#zallimsangtae {
    color: red;
    font-weight: bold;
}			
			
		<?php if($telegram_bot_name=='') { ?>
		div#round_telegram_chat_id {
			display: none;
		}
		<?php } ?>
			div#round_telegram_chat_id span.k-icon.k-i-x {
				display: none;
			}
			input#telegram_chat_id {
				max-width: 189px;
			}
			div#round_telegram_chat_id span.k-widget.k-autocomplete.k-autocomplete-clearable {
				width: 189px;
			}
			label#telegram_chat_id_label {
				pointer-events: none;
			}
			
		</style>
        <script>
function thisLogic_toolbar() {
	if(location.origin=='https://www.speedmis.com') {
		$("a#btn_1").text("계정삭제요청");
		$("#btn_1").css("background", "#88f");
		$("#btn_1").css("color", "#fff");
		$("#btn_1").click( function() {
			alert('계정삭제가 요청되었습니다.');
		});
	}
}
			
function columns_templete(p_dataItem, p_aliasName) {

    if(p_aliasName=='zallimsangtae') {

		//웹소스디테일의 데이터타입을 number^^#,##0 라고 지정 및 템플릿을 Y 로 했을 경우, 아래와 같은 식으로 하면 number 포맷으로 출력할 수 있음.
		//rValue = rValue + columns_format(p_dataItem, p_aliasName);
		<?php 
		if($send_admin_mail=='' && $telegram_bot_name=='') {
			$msg = '사이트설정에 의해 알림기능이 제공되지 않습니다.';
		} else if($send_admin_mail!='' && $telegram_bot_name=='') {
			$msg = '사이트설정에 의해 이메일 알림기능을 사용하실 수 있습니다.';
		} else if($send_admin_mail=='' && $telegram_bot_name!='') {
			$msg = '사이트설정에 의해 텔레그램 알림기능을 사용하실 수 있습니다.';
		} else {
			$msg = '사이트설정에 의해 이메일 또는 텔레그램 알림기능을 모두 사용할 수 있습니다.';
		}
		?>
		rValue = '<?php echo $msg; ?>';
        return rValue;
    } else {
        return p_dataItem[p_aliasName];
    }
}			
			
			
			
    function viewLogic_afterLoad_continue() {
		
		
		if($('a#btn_step1').length==0) {
			$('div#round_telegram_chat_id').addClass('nodata');
			tags = '<a id="btn_step1" href="https://t.me/<?php echo $telegram_bot_name; ?>" target="_blank" style="padding: 4px 7px;position: absolute;top: 0px;left: 15px;width: 299px;" class="k-button">인증1단계: <?php echo $telegram_bot_name; ?> 채팅창 열기</a>';
			$('input#telegram_chat_id').parent().before(tags);
			$('a#btn_step1').click( function() {
				alert("텔레그램의 <?php echo $telegram_bot_name; ?> 봇 접근 후, 시작을 클릭하시면 됩니다. 모바일 텔레그램에서 진행하시거나, 또는 확인을 누르시면 PC 용 봇으로 연결됩니다.");
			});


			tags = '<a role="button" class="k-button k-button-icontext" id="btn_step2" style="padding: 4px; border-top: 0; border-bottom: 0; border-right: 0; float: right; display: inline-block;">인증2단계</a>';
			$('input#telegram_chat_id').after(tags);
			$('a#btn_step2').click( function() {
				txt = $('a#btn_step2').text();
				if(txt=='인증2단계') {
					$('a#btn_step2').text('2단계 완료');
					var rnd = Math.ceil(Math.random()*8999)+1000;
					$('a#btn_step2').attr('rnd',rnd);
					alert("인증번호 "+rnd+" 을 텔레그램 대화창에서 입력 후, 2단계 완료를 누르세요! 그러면 인증이 마무리됩니다.");


				} else {
					getUpdates = JSON.parse(ajax_url_return("https://api.telegram.org/bot<?php echo $telegram_bot_token; ?>/getUpdates"));
					var chat_id = "";

					if(getUpdates.ok) {
						cnt = getUpdates.result.length;
						for(i=cnt-1;i>=cnt-100 && i>=0;i--) {
							if(getUpdates.result[i].message.text==$('a#btn_step2').attr('rnd')+"") {
								chat_id = getUpdates.result[i].message.from.id;
								i = -1;
							}
						}
					} else {
						alert("인증과정에 문제가 발생했습니다. 관리자에게 문의하세요!");
						$('a#btn_step2').text('인증2단계');
					}
					if(chat_id=="") {
						alert("텔레그램 chat id 를 찾을 수 없습니다. 다시 시도해보세요!");
						$('a#btn_step2').text('인증2단계');
					} else {
						$('input#telegram_chat_id')[0].value = chat_id;
						text = "인증을 축하합니다. 모바일에서 <?php echo $full_site; ?>/_mis 를 방문해보세요!";
						telegram_sendMessage(chat_id, "", text, "", "카톡알리미");
						alert("인증이 완료되었습니다! 수정완료를 누르시면 최종반영됩니다.");
						$('div#round_telegram_chat_id').removeClass('nodata');
						$('input#telegram_chat_id').css('width','100%');
						$('a#btn_step0').css('display', 'inline-block');
						$('a#btn_step1').css('display', 'none');
						$('a#btn_step2').css('display', 'none');
					}
				}

			});
			
			tags = '<a id="btn_step0" style="padding: 4px 9px;position: absolute;top: 0px;left: 216px;" class="k-button">chat id 지우기 / 알림중지</a>';
			$('input#telegram_chat_id').parent().after(tags);
			$('div#round_telegram_chat_id').removeClass('nodata');
			$('a#btn_step0').click( function() {
				$('input#telegram_chat_id')[0].value = "";
				alert("수정완료를 누르시면 최종반영됩니다.");
				$('div#round_telegram_chat_id').addClass('nodata');
				$('input#telegram_chat_id').css('width','calc(100% - 82px)');
				$('a#btn_step0').css('display', 'none');
				$('a#btn_step1').css('display', 'inline-block');
				$('a#btn_step2').css('display', 'inline-block');
			});

			$('input#telegram_chat_id').css('pointer-events','none');
			$('input#telegram_chat_id').css('display','inline-block');

		}

		if($('input#telegram_chat_id')[0].value!='') $('div#round_telegram_chat_id').removeClass('nodata');
		else $('div#round_telegram_chat_id').addClass('nodata');
		
		$('span#span_kendoCulture').text('<?php echo $kendoCulture; ?>');
		
		if($('input#telegram_chat_id')[0].value!="") {

			$('input#telegram_chat_id').css('width','100%');

			$('a#btn_step0').css('display', 'inline-block');
			$('a#btn_step1').css('display', 'none');
			$('a#btn_step2').css('display', 'none');

		} else {

			$('input#telegram_chat_id').css('width','calc(100% - 82px)');

			$('a#btn_step0').css('display', 'none');
			$('a#btn_step1').css('display', 'inline-block');
			$('a#btn_step2').css('display', 'inline-block');

		}
		
		var send_admin_mail = '<?=$send_admin_mail?>';
		var telegram_bot_name = '<?=$telegram_bot_name?>';

		var alim_status = '';
		/*
		if($('input#telegram_chat_id')[0].value!='' && telegram_bot_name!='') {
			alim_status = '알림수신 체크 시, 텔레그램으로 메세지가 수신됩니다.';
			if($('input#email')[0].value!='' && send_admin_mail!='') alim_status = alim_status + ' 텔레그램ID 를 지우면 이메일로 수신됩니다.';
			else if(send_admin_mail!='') alim_status = alim_status + ' 텔레그램ID 를 지우고 이메일을 넣으면 이메일로 수신됩니다.';
		} else if($('input#email')[0].value!='' && send_admin_mail!='') {
			alim_status = '알림수신 체크 시, 이메일로 메세지가 수신됩니다.';
			if(telegram_bot_name!='') alim_status = alim_status + ' 텔레그램ID 를 넣으면 텔레그램으로 수신됩니다.';
		} else if(send_admin_mail!='') {
			alim_status = '알림수신 체크 시, 이메일을 넣으면 이메일로 메세지가 수신됩니다.';
			if(telegram_bot_name!='') alim_status = alim_status + ' 텔레그램ID 를 넣으면 텔레그램으로 수신됩니다.';
		} else $('div#round_receive_YN').css('display', 'none');
		
		$('div#round_receive_YN span.alim')[0].innerText = alim_status;	
		*/
		
		
		
		<?php if($cnt_allPush_member==1) { ?>
		
		$('div.before_round_allPush_YN, div#round_allPush_YN').css('display', 'inline-block');
		
		<?php } ?>
		
	}
        </script>
	
		<?php
	}
	

}
//end pageLoad



function save_updateReady() {

    global $base_root, $RealPid, $MisJoinPid, $logicPid, $parent_idx;
    global $key_aliasName, $key_value, $saveList, $viewList, $deleteList, $saveTextdecrypt2List;

	//아래의 예제는 특정항목에 대해 저장 전에, $saveList["addLogic"] 를 백업 파일로 생성시키는 로직입니다. 실제로 아래와 같이 자동백업됩니다.
    if($key_value!="") {
		if(isset($saveTextdecrypt2List["passwdDecrypt"])) {
        	$passwdDecrypt = $saveTextdecrypt2List["passwdDecrypt"];

			//숫자와 문자 포함 형태의 6~12자리 이내의 암호 정규식 (1 가지 조합)
			$regExp1 = '/^[A-Za-z0-9]{6,12}$/u'; 

			//영문, 숫자, 특수문자 중 2가지 이상 조합하여 10자리 이내의 암호 정규식 ( 2 가지 조합)
			$regExp2 = '/^(?!((?:[A-Za-z]+)|(?:[~!@#$%^&*()_+=]+)|(?:[0-9]+))$)[A-Za-z\d~!@#$%^&*()_+=]{6,12}$/u';  

			//특수문자 / 문자 / 숫자 포함 형태의 8~15자리 이내의 암호 정규식 ( 3 가지 조합)
			$regExp3 = '/^.*(?=^.{6,12}$)(?=.*\d)(?=.*[a-zA-Z])(?=.*[!@#$%^&+=]).*$/u';  

			$match1 = preg_match('/^[0-9]/u', $passwdDecrypt);
			$match2 = preg_match('/^(?!((?:[A-Za-z]+)|(?:[~!@#$%^&*()_+=]+)|(?:[0-9]+))$)[A-Za-z\d~!@#$%^&*()_+=]{6,12}$/u', $passwdDecrypt);
			$match3 = preg_match('/^.*(?=^.{6,12}$)(?=.*\d)(?=.*[a-zA-Z])(?=.*[!@#$%^&+=]).*$/u', $passwdDecrypt);
			

        	if($match1 . $match2 . $match3 != '110' && $match1 . $match2 . $match3 != '111') {
					gzecho('새 비밀번호는 숫자와 문자(또는 특수문자)가 포함된 6~12 자리로 넣으세요.');
					exit;
			}

		}
    }

}
//end save_updateReady



function save_updateAfter() {

	global $updateList, $kendoCulture, $afterScript, $base_domain;
	if(request_cookies("myLanguageCode")!=$updateList['myLanguageCode']) {
		$afterScript = 'location.href = location.href;';
	}	
	setcookie('myLanguageCode', '', -1, '/', '.' . $base_domain);
	setcookie('myLanguageCode', $updateList['myLanguageCode'], time() + 3600*24*100, '/');

}
//end save_updateAfter

?>