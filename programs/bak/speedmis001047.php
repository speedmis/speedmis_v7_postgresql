<?php

function pageLoad() {

    global $actionFlag,$full_site,$telegram_bot_name,$telegram_bot_token;


?>
<style>
	textarea#contents {
		width: 380px!important;
		height: 165px!important;
	}
</style>
<script>
	function viewLogic_beforeSendChangeMessage(p_title,p_contents,p_url,p_newIdx) {
		//텔레그램 수신확인용 
		return contents = uni_left($('textarea#contents')[0].value,400)+'<a href="<?php echo $full_site; ?>/_mis/c/?RealPid='+getID('RealPid').value+'&newIdx='+p_newIdx+'&userid=@userid">_</a>'
		
		//return contents = uni_left($('textarea#contents')[0].value,480);
	}

	function charLength_this(p_this) {

		if(uni_len(p_this.value)>400) p_this.value = uni_left(p_this.value, 400);
		$('span#msgLength')[0].innerText = uni_len(p_this.value);

	}

	$('textarea#contents').keyup( function() {
    	charLength_this(this);
	});
	
	<?php if($telegram_bot_name=='' || $telegram_bot_token=='') { ?>
	
	if($('input#MisSession_UserID').val()=='gadmin') {
		if(confirm('텔레그램 전송을 위한 관리자 설정이 되어 있지 않습니다. 텔레그램 설정 도움말을 여시겠습니까?')) {
			window.open('https://www.speedmis.com/_mis/index.php?gubun=1040&allFilter=[{"operator":"contains","value":"텔레그램 연동을","field":"zjemok"}]');
		}
	} else {
		alert('텔레그램 전송을 위한 관리자 설정이 되어 있지 않습니다. gadmin 권한으로 텔레그램 설정을 먼저 진행해야 합니다.');
	}
	
	<?php } ?>

</script>
<?php
}
//end pageLoad



function change_pildok_member() {

	global $MS_MJ_MY, $pildok_sql, $result, $resultCnt;

	if($MS_MJ_MY=='MY') {
		$pildok_sql = "select table_m.UniqueNum as 'value', concat(table_m.UserName,' | ',ifnull(table_Station_NewNum2.stationname,''),case when ifnull(table_Station_NewNum4.stationname,'')='' then '' else concat(' > ' , ifnull(table_Station_NewNum4.stationname,'')) end,case when length(ifnull(table_Station_NewNum.AutoGubun,''))>=6 then concat(' > ' , ifnull(table_Station_NewNum.stationname,'')) else '' end , ' > ' ,table_positionNum.kname,case when ifnull(telegram_chat_id,'')<>'' then ' (Telegram OK)' else '' end) as 'text' 
		from MisUser table_m 
		left outer join MisStation table_Station_NewNum on table_Station_NewNum.Num = table_m.Station_NewNum 
		left outer join MisStation table_Station_NewNum2 on table_Station_NewNum2.autogubun = left(table_Station_NewNum.autogubun,2) 
		left outer join MisStation table_Station_NewNum4 on table_Station_NewNum4.autogubun = left(table_Station_NewNum.autogubun,4) and length(table_Station_NewNum.autogubun)>=4 
		left outer join MisCommonTable table_positionNum on table_positionNum.kcode = table_m.positionNum and table_positionNum.gcode='speedmis000188' 
		where ifnull(telegram_chat_id,'')<>'' and table_m.delchk<>'D' 
		and ((datediff(table_m.toisa_date,DATE_FORMAT(NOW(), '%Y-%m-%d'))<=0) or lTrim(ifnull(table_m.toisa_date,''))='') 
		and ifnull(table_m.isRest,'')<>'Y' and (table_m.UniqueNum not in ('gadmin') or 'gadmin'='gadmin') 
		and (table_m.UniqueNum in (select userid from MisMenuList_Member where RealPid='speedmis001047') 
		or (select AuthCode from MisMenuList where RealPid='speedmis001047') in ('','01')) 
		order by table_Station_NewNum.AutoGubun, table_m.positionNum";
	} else {
		$pildok_sql = "select table_m.UniqueNum as 'value' ,table_m.UserName+' | '+isnull(table_Station_NewNum2.stationname,'')+case when isnull(table_Station_NewNum4.stationname,'')='' then '' else ' > ' + isnull(table_Station_NewNum4.stationname,'') end+case when len(isnull(table_Station_NewNum.AutoGubun,''))>=6 then ' > ' + isnull(table_Station_NewNum.stationname,'') else '' end + ' > ' +table_positionNum.kname+case when isnull(telegram_chat_id,'')<>'' then ' (Telegram OK)' else '' end as 'text' from MisUser table_m left outer join MisStation table_Station_NewNum on table_Station_NewNum.Num = table_m.Station_NewNum left outer join MisStation table_Station_NewNum2 on table_Station_NewNum2.autogubun = left(table_Station_NewNum.autogubun,2) left outer join MisStation table_Station_NewNum4 on table_Station_NewNum4.autogubun = left(table_Station_NewNum.autogubun,4) and len(table_Station_NewNum.autogubun)>=4 left outer join MisCommonTable table_positionNum on table_positionNum.kcode = table_m.positionNum and table_positionNum.gcode='speedmis000188' where isnull(telegram_chat_id,'')<>'' and table_m.delchk<>'D' and ((datediff(day,table_m.toisa_date,convert(char(10),getdate(),120))<=0) or lTrim(isnull(table_m.toisa_date,''))='') and isnull(table_m.isRest,'')<>'Y' and (table_m.UniqueNum not in ('gadmin') or 'gadmin'='gadmin') and (table_m.UniqueNum in (select userid from MisMenuList_Member where RealPid='speedmis001047') or (select AuthCode from MisMenuList where RealPid='speedmis001047') in ('','01')) order by table_Station_NewNum.AutoGubun, table_m.positionNum";
	}
	
	$result[$resultCnt]["Grid_Pil"] = "Y";
	$result[$resultCnt]["Grid_FormGroup"] = "";
	$result[$resultCnt]["Grid_Alim"] = "수신자를 선택하세요.";
	
}
//end change_pildok_member

?>