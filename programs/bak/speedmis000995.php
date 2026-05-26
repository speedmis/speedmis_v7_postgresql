<?php

function scheduler_pageLoad() {
    global $actionFlag;
    global $real_pid, $misJoinPid, $logicPid, $parent_gubun, $parent_RealPid, $parent_idx;
    global $isAuthW, $isAuthR, $misSessionUserId, $misSessionIsAdmin; 
    global $filterField_sql, $filterField;
    
    //$filterField_sql = "select distinct m.wdater as 'value', (select top 1 mu.UserName from MisUser mu where m.wdater=mu.UniqueNum) as 'text' from MisSchedule_meeting m";
    $filterField_sql = "select kcode as 'value', kname as 'text', kname2 as 'color' from MisCommonTable where RealCid<>ggcode and ggcode = 'rbk001063' ";
    $filterField = jsonReturnSql($filterField_sql);   
?>
<script>
var filterField = "roomID";
var filterField_title = "Room";
var filterField_data = JSON.parse('<?php echo $filterField; ?>');

var p_fields = {

    taskID: { from: "taskID", type: "number" },
    title: { from: "title", defaultValue: "No title", validation: { required: true } },
    start: { type: "date", from: "startDate" },
    end: { type: "date", from: "endDate" },
    //startTimezone: { from: "startTimezone" },
    //endTimezone: { from: "endTimezone" },
    description: { from: "description" },
    //recurrenceId: { from: "recurrenceID" },
    //recurrenceRule: { from: "recurrenceRule" },
    //recurrenceException: { from: "recurrenceException" },
    isAllDay: { type: "boolean", from: "isAllDay" },
    roomID: { from: "roomID", nullable: true },
    bottle: { from: "bottle", type: "string", nullable: true, validation: { required: false } },
    liquid: { from: "liquid", type: "string", nullable: true, validation: { required: false } },
    quantity: { from: "quantity", type: "string", nullable: true, validation: { required: false } },
    wdater: { from: "wdater" },

}


function fun_roomName(p_roomID) {
    if(p_roomID==null) return '';
    json = $("#scheduler").data("kendoScheduler").resources[0].dataSource.options.data; 
    var text = getObjects(json, "value", p_roomID)[0]["text"];
    return text;
}
</script>

<script id="allDay-event-template" type="text/x-kendo-template">
<div class="scheduler-template" onclick="console.log(111);popDialog('#: taskID #'); return false;">
    <p roomID='#: roomID#' title='#: fun_roomName(roomID) # | #if(kendo.toString(start, "HH:mm")!=kendo.toString(end, "HH:mm")) {##=kendo.toString(start, "HH:mm") + " ~ " + kendo.toString(end, "HH:mm") ##}# #: description#'>
    #: fun_roomName(roomID) # | #: title # | bottle=#: bottle# | liquid=#: liquid# | quantity=#: quantity#
    </p>
</div>
</script>

<script id="event-template" type="text/x-kendo-template">
</script>

<script>
    $('#event-template').html($('#allDay-event-template').html());
</script>
<?php    
}
//end scheduler_pageLoad



function scheduler_treatTop() {
    global $actionFlag;
    global $real_pid, $misJoinPid, $MS_MJ_MY, $MS_MJ_MY2;
    global $isAuthW, $isAuthR, $misSessionUserId, $misSessionIsAdmin; 
    global $flag, $sql_read, $newIdx, $models, $key_aliasName, $sql_destroy, $updateList;


    if($flag=="read") {
        if($MS_MJ_MY2=='MY') {
            $sql_read = "select taskID,title,'' as description,null as recurrenceRule,null as recurrenceException,null as isAllDay,
            misGetKendoDatetime(concat(useDate,' ',case when ifnull(startTime,'')='' then '00:00' else startTime END)) as startDate, 
            misGetKendoDatetime(concat(useDate,' ',case when ifnull(endTime,'')='' then '23:59' else endTime END)) as endDate,
            null as startTimezone, null as endTimezone, null as recurrenceID,roomID,bottle,liquid,quantity,wdater
            from MisSchedule_meeting ";
        } else {
            $sql_read = "select taskID,title,'' as description,null as recurrenceRule,null as recurrenceException,null as isAllDay,
            dbo.misGetKendoDatetime(useDate+' '+case when isnull(startTime,'')='' then '00:00' else startTime end) as startDate, 
            dbo.misGetKendoDatetime(useDate+' '+case when isnull(endTime,'')='' then '23:59' else endTime end) as endDate,
            null as startTimezone, null as endTimezone, null as recurrenceID,roomID,bottle,liquid,quantity,wdater
            from MisSchedule_meeting ";
        }
        if($newIdx!="") $sql_read = $sql_read . " where taskID=" . $newIdx;
        $sql_read = $sql_read . " order by 1";
    } else if($flag=="destroy") {
        //$sql_destroy = "delete MisSchedule where taskID=" . $models["taskID"];
    } else {
        /*
        $key_aliasName = "taskID";


        $updateList["title"] = "";
        $updateList["description"] = "";
        $updateList["recurrenceRule"] = "";
        $updateList["recurrenceException"] = "";
        $updateList["isAllDay"] = "";
        $updateList["startDate"] = null;
        $updateList["endDate"] = null;
        $updateList["startTimezone"] = null;
        $updateList["endTimezone"] = null;
        $updateList["recurrenceID"] = null;
        $updateList["roomID"] = "";
        $updateList["bottle"] = "";
        $updateList["liquid"] = "";
        $updateList["quantity"] = "";
        */
    }

}
//end scheduler_treatTop

?>