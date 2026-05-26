<?php 


function save_writeBefore() {
    global $full_siteID, $base_root, $RealPid, $MisJoinPid, $logicPid, $parent_idx;
    global $key_aliasName, $key_value, $ActionFlag, $updateList, $MS_MJ_MY;

    if($MS_MJ_MY=='MY') {
        $sql = "SELECT formatnums(AUTO_INCREMENT,'000000') FROM information_schema.TABLES WHERE TABLE_NAME = 'MisCommonTable'; ";
    } else {
        $sql = "declare @idx int set @idx=ident_current('MisCommonTable')+1 select replicate('0', 6-len(@idx))+convert(nvarchar(6),@idx) ";
    }
    $RealCid = $full_siteID . onlyOnereturnSql($sql);
    $updateList["RealCid"] =  $RealCid;
    $updateList["gcode"] =  $parent_idx;
    $updateList["ggcode"] =  $parent_idx;
}
//end save_writeBefore


function commandTreat_briefInsertBefore() {
    global $full_siteID, $base_root, $RealPid, $MisJoinPid, $logicPid, $parent_idx, $MS_MJ_MY;
    global $key_aliasName, $key_value, $ActionFlag, $updateList, $multiInsertSql;

    if($MS_MJ_MY=='MY') {
        $multiInsertSql = replace($multiInsertSql, "Rep_RealCid", "@RealCid");
        $multiInsertSql = replace($multiInsertSql, "insert into", " SELECT @RealCid:=concat('$full_siteID',formatnums(AUTO_INCREMENT,'000000')) FROM information_schema.TABLES WHERE TABLE_NAME = 'MisCommonTable'; insert into");
    } else {
        $multiInsertSql = replace($multiInsertSql, "Rep_RealCid", "@RealCid");
        $multiInsertSql = replace($multiInsertSql, "insert into", " set @idx = ident_current('MisCommonTable')+1 set @RealCid = '" . $full_siteID . "'+replicate('0', 6-len(@idx))+convert(nvarchar(6),@idx) insert into");
        $multiInsertSql = "declare @RealCid nvarchar(14), @idx int " . $multiInsertSql;
    }

}
//end commandTreat_briefInsertBefore

?>