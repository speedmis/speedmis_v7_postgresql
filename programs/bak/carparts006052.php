<?php

function pageLoad() {

    global $ActionFlag, $RealPid, $parent_idx, $idx;
	global $MisSession_UserID, $MisSession_IsAdmin, $parent_RealPid;

	
	$sid = requestVB('sid');
	$pid = requestVB('pid');
	if($sid!='') {
		$sql = "SELECT full_name FROM vv_mis_parts_storage_tree  WHERE idx=$sid;";
        
		$full_name = onlyOnereturnSql($sql);
		if($full_name!='') {
			$url = 'index.php?gubun=6083&allFilter=[{"operator":"contains","value":"' . $full_name . '","field":"table_ca_storage_idQnfull_name"}]&isMenuIn=Y';
			re_direct($url);
		} else {
?>
<script>
	setCookie('modify_YN', 'Y');
	alert('해당 창고번호: <?php echo $sid; ?> 는 존재하지 않습니다.');
	location.href = "index.php?gubun=6083&isMenuIn=Y";
</script>
<?php
		}
		exit;
    } else if($pid!='') {
        $pid_10 = $pid;
        if(Len($pid_10)<10) {
            $pid_10 = FormatNum($pid_10,'0000000000');
        }
        $url = 'index.php?gubun=6083&allFilter=[{"operator":"contains","value":"' . $pid_10 . '","field":"toolbarSel_it_id"}]&isMenuIn=Y';
        re_direct($url);
    }
	

}
//end pageLoad


?>