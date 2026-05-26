<?php

function pageLoad() {

    global $ActionFlag;
	global $MisSession_IsAdmin;


        ?>
<style>

	a#btn_modify, a#btn_view, a#btn_up, a#btn_down {
		display: none!important;
	}
</style>
        <?php 
}
//end pageLoad



function textUpdate_sql() {

    global $strsql, $keyAlias, $keyValue, $thisValue, $oldText, $thisAlias, $resultCode, $resultMessage, $afterScript;
	global $base_root;

	//언어를 선택할때 언어팩이 없으면 다운로드 한다.
	if($thisAlias=='isSupport') {
		$sql = "select LanguageCode from MisGlobal_Language where idx=$keyValue";
		$LanguageCode = onlyOneReturnSql($sql);
		if($LanguageCode!='') {

//https://www.speedmis.com/_mis_speedmis/langJS/cultures/kendo.culture.ko-KR.min.js
//https://www.speedmis.com/_mis_speedmis/langJS/messages/kendo.messages.ko-KR.min.js
//https://www.speedmis.com/_mis_uniqueInfo/local_ko-KR.js

			$culturePath = $base_root . "/_mis_kendo/js/cultures/kendo.culture.$LanguageCode.min.js";
			$messagesPath = $base_root . "/_mis_kendo/js/messages/kendo.messages.$LanguageCode.min.js";
			$localPath = $base_root . "/_mis_uniqueInfo/local_$LanguageCode.js";

			$resultCode = "success";
			if($thisValue=='Y') {
				if(!file_exists($culturePath)) {
					$cultureUrl = "https://www.speedmis.com/_mis_speedmis/langJS/cultures/kendo.culture.$LanguageCode.min.js";
					$cultureContent = file_get_contents_new($cultureUrl);
					if(InStr($cultureContent,'function')>0) {
						WriteTextFile($culturePath, $cultureContent);
						$resultMessage = "$LanguageCode culture 언어팩 다운로드에 성공했습니다.";
					} else {
						$resultCode = "fail";
						$resultMessage = "$LanguageCode culture 언어팩 다운로드에 실패했습니다.";
					}
				}
				if(!file_exists($messagesPath) && $resultCode=="success") {
					$messagesUrl = "https://www.speedmis.com/_mis_speedmis/langJS/messages/kendo.messages.$LanguageCode.min.js";
					$messagesContent = file_get_contents_new($messagesUrl);
					if(InStr($messagesContent,'function')>0) {
						WriteTextFile($messagesPath, $messagesContent);
						$resultMessage = $resultMessage . " $LanguageCode messages 언어팩 다운로드에 성공했습니다.";
					} else {
						$messagesUrl = "https://www.speedmis.com/_mis_speedmis/langJS/messages/kendo.messages.$LanguageCode.min.js";
						$messagesContent = file_get_contents_new($messagesUrl);
						if(InStr($messagesContent,'function')>0) {
							WriteTextFile($messagesPath, $messagesContent);
							$resultMessage = $resultMessage . " $LanguageCode messages 언어팩 다운로드에 성공했습니다.";
						} else {
							$messagesUrl = "https://www.speedmis.com/_mis_speedmis/langJS/messages/kendo.messages.en-US.min.js";
							$messagesContent = file_get_contents_new($messagesUrl);
							WriteTextFile($messagesPath, $messagesContent);
							$resultMessage = $resultMessage . " 해당 메시지 언어팩이 없어서 영어로 대체하였습니다.";
						}
					}
				}
				if(!file_exists($localPath)) {
					$localUrl = "https://www.speedmis.com/_mis_uniqueInfo/local_$LanguageCode.js";
					$localContent = file_get_contents_new($localUrl);
					//www.speedmis.com 에서 생성된 커스텀 js 가 없으면 영문버전을 기준으로 카피시킴.
					if(InStr($localContent,'changeText')==0) {
						$localContent = file_get_contents_new("https://www.speedmis.com/_mis_uniqueInfo/local_en-US.js");
					}
					if(InStr($localContent,'changeText')>0) WriteTextFile($localPath, $localContent);
				}
			} else {
				if(file_exists($culturePath)) {
					@unlink($culturePath);
				}
				if(file_exists($messagesPath)) {
					@unlink($messagesPath);
				}
				//localPath 는 자체업데이트를 진행할 수도 있으므로 삭제하지 않음.
			}

		}

	}


}
//end textUpdate_sql

?>