<?php

function pageLoad() {

    global $actionFlag, $kendoTheme, $misSessionIsAdmin, $base_domain;
	if($actionFlag=='list') updateEvent1_lastupdate_add_default('');
	?>
<script>
//원격 json 을 MY 가 아닌 MS DB 접속을 위해 변경.
document.getElementById('MS_MJ_MY').value = 'MS';
$('body').attr('auto_open_refuse','');
</script>

<style>
	li[tabid="commentApp"],li[tabid="readApp"] {
		display: none!important;
	}
	div#contents {
		font-family: 'Spoqa Han Sans','Malgun Gothic','맑은 고딕','Apple SD Gothic Neo','돋움',dotum, sans-serif;
		letter-spacing: -1px;
		line-height: 150%;
	}
	li[tabid="speedmis000974"] {
		display: inline-block!important;
	}
</style><?php


	if($actionFlag=="list" && requestVB("allFilter")) { 
		?>
		<script>
function listLogic_afterLoad_once() {

		if($('div#grid').data('kendoGrid').dataSource._total==1) {
			//$('a#btn_view').click();
		}
}
		</script>
		<?php
	}

    if($actionFlag=="view") { 

        ?>
        
<style>
	xdiv#contentsMirror {
		width: calc(100% - 0px);
		height: calc(100vh - 354px);
	}
	xdiv#contentsMirror > div {
		height: 100%;
	}
	div#youtubeCode {
		width: calc(100vw - 65px);
		height: calc(100vh - 169px);
		overflow: hidden;
	}
	div#round_youtubeCode label {
		cursor: pointer;
	}

</style>
<script>


	//웹소스 디테일에서 템플릿으로 체크한 항목에 대해 출력내용을 변경할 수 있습니다. 이때 목록 또는 본문내용에 동일하게 적용됩니다.
function columns_templete(p_dataItem, p_aliasName) {

    if(p_aliasName=="youtubeCode") {

		if(p_dataItem["youtubeCode"]!="" && p_dataItem["youtubeCode"]!=undefined) {
			var rValue = `
<iframe id="ifr_youtube" style="width: 100%; height: 100%;" 
src="https://www.youtube.com/embed/`+p_dataItem['youtubeCode']+`" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
allowfullscreen></iframe>
`;
			$('li[tabid="youtubeCode"]').css('display','inline-block');
			$('div#round_youtubeCode label').click( function() {
				window.open('https://youtu.be/'+p_dataItem['youtubeCode']);
				$('iframe#ifr_youtube')[0].outerHTML = $('iframe#ifr_youtube')[0].outerHTML;
			});
			setTimeout( function() {
				parent.$('a.k-button[aria-label="Close"]').click( function() {
					$(this).closest('.k-widget.k-window').remove();
				});
			});
			return rValue;
		} else {
			$('li[tabid="youtubeCode"]').css('display','none');
			return '';
		}
	} else {
        return p_dataItem[p_aliasName];
    }

}
	
	function viewLogic_afterLoad_continue() {

			if(getUrlParameter("tabid")==undefined) {

				if(resultAll.d.results[0].phpCode!="" && resultAll.d.results[0].phpCode!=null) {

					$('li[tabid="phpCode"]').click();
					$('li[tabid="phpCode"]').css('display','inline-block');
				} else {
					$('li[tabid="idx"]').click();
					$('li[tabid="phpCode"]').css('display','none');
				}
			}
			//각탭에 pdf href 가 1개만 있으면 뷰어로 변신
			tab_pdf_into_viewer();
	}
	
</script>

        <?php 
    }




}
//end pageLoad



function list_json_load() {
	
    global $base_root, $real_pid, $misJoinPid, $logicPid, $parent_idx;
    global $flag, $selField, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript;
    global $data, $key_aliasName, $child_alias, $selectQuery, $keyword, $menuName, $_count;
	global $allFilter, $speed_Grid_Columns_Title,$full_site, $misSessionUserId;

	global $commentSql, $commentData, $full_siteID;

	if($full_siteID=='speedmis' && $flag=='read' && $selField=='' && $commentSql!='') {
		$commentSql2 = replace($commentSql, 'MisComments', 'Mis_' . '_speedsup_' . '_Comments');
		$commentData = jsonReturnSql($commentSql2);
	}	
	if($full_siteID!='speedsup' && $flag=='view') {
        $p_data = json_decode($data, true);
        $p_data[0]['contents'] = replace($p_data[0]['contents'], 'http://support.speedmis.com/uploadFiles', 'https://www.speedmis.com/z_support/uploadFiles');			  
		$data = json_encode($p_data);
	}
}
//end list_json_load



function jsonUrl_index() {
	
	global $jsonUrl, $remote_MS_MJ_MY, $full_siteID;
	if($full_siteID=='speedsup') {
		$jsonUrl = "http://support.speedmis.com/_mis/";
	} else {
		$jsonUrl = "https://www.speedmis.com/_mis/";
	}
	$remote_MS_MJ_MY = "MS";
}
//end jsonUrl_index

?>