<?php

function pageLoad() {

    global $ActionFlag;
    global $RealPid, $MisJoinPid, $logicPid, $parent_gubun, $parent_RealPid, $parent_idx;
    global $isAuthW, $isAuthR, $MisSession_UserID, $MisSession_IsAdmin, $ServerVariables_URL;




    if($ActionFlag=="list") { ?>
    <style>
body[ismainframe="Y"] td[comment="Y"] {
    background-image: none;
}
body td[comment="Y"]:after {
    content: "";
}
              
html .k-grid tr:hover {
  background: transparent;
}
 
html .k-grid tr.k-alt:hover {
  background: #f1f1f1;
}      

.k-grid > table > tbody > tr:hover,
.k-grid-content > table > tbody > tr:hover
{
        background: none!important;
}

.k-grid td.k-state-selected:hover, .k-grid tr.k-state-selected:hover {
    background-color: none!important;
    background-image: none!important;
}

.k-grid tr:hover {
    background-color: none!important;
    background-image: none!important;
}
.k-grid tr.k-state-selected:hover {
    background-color: none!important;
    background-image: none!important;
}
.k-state-selected {
    background-image: none!important;
    color: none!important;
    background-color: none!important;
    border-color: none!important;
}

.u_cbox, .u_cbox input, .u_cbox textarea, .u_cbox select, .u_cbox button, .u_cbox table {
    font-size: 12px;
    line-height: 1.25em;
}

.u_cbox ul, .u_cbox ol {
    list-style: none;
    margin: 0;
    padding: 0;
    font-family: AppleSDGothicNeo,'돋움',dotum,Helvetica,sans-serif;

}
.u_cbox .u_cbox_comment {
    overflow-anchor: none;
    list-style: none;
    display: list-item;
    text-align: -webkit-match-parent;

}
.u_cbox .u_cbox_comment_box {
    position: relative;
    border-bottom: 0px solid #e2e2e2;
}
.u_cbox .u_cbox_comment_box .u_cbox_area {
    padding: 5px 0;
    max-width: 1000px;
}
.u_cbox .u_cbox_comment_box .u_cbox_info {
    padding-bottom: 1px;
    height: 25px;
}
.u_cbox .u_cbox_name_area {
    font-size: 15px;
    line-height: 25px;
    font-weight: normal;
    vertical-align: top;
}
.u_cbox .u_cbox_name .u_cbox_nick_area {
    display: inline-block;
    overflow: hidden;
    float: left;
    max-width: 50%;
    white-space: nowrap;
    text-overflow: ellipsis;
}
.u_cbox .u_cbox_comment .u_cbox_text_wrap {
    overflow: hidden;
    font-size: 15px;
    line-height: 18px;
    word-break: break-all;
    word-wrap: break-word;
    margin-top: 6px;
    padding-top: 10px;
    margin-bottom: 6px;
    padding-bottom: 10px;
}
.u_cbox .u_cbox_info_base {
    position: relative;
    padding: 2px 0 0 0;
}
.u_cbox .u_cbox_date {
    display: block;
    font-size: 13px;
    font-family: tahoma,helvetica,sans-serif;
}
.u_cbox .u_cbox_attach {
    display: block;
    font-size: 13px;
    font-family: tahoma,helvetica,sans-serif;
    margin-top:5px;
    cursor: pointer;
}

.u_cbox a, .u_cbox a:link {
    background-color: transparent;
}
.u_cbox .u_cbox_btn_report, .u_cbox .u_cbox_btn_unhide {
    float: left;
    padding-top: 2px;
    vertical-align: top;
}
.u_cbox a {
    text-decoration: none;
}
.u_cbox .u_cbox_info_base .u_cbox_ico_bar {
    display: inline-block;
    float: left;
    position: relative;
    top: 1px;
    left: auto;
    height: 12px;
    margin: 0 7px 0 9px;
    vertical-align: top;
}
.u_cbox .u_cbox_info_base:after {
    display: block;
    clear: both;
    content: '';
}

.k-alt, .k-pivot-layout>tbody>tr:first-child>td:first-child, .k-resource.k-alt, .k-separator {
    background-color: inherit;
}
.k-draghandle.k-state-selected:hover, .k-ghost-splitbar-horizontal, .k-ghost-splitbar-vertical, .k-list>.k-state-highlight, .k-list>.k-state-selected, .k-marquee-color, .k-panel>.k-state-selected, .k-scheduler .k-scheduler-toolbar .k-state-selected, .k-scheduler .k-today.k-state-selected, .k-state-selected, .k-state-selected:link, .k-state-selected:visited, .k-tool.k-state-selected {
    background-color: inherit;
    border-color: inherit;
}
.k-grid td.k-state-selected:hover, .k-grid tr.k-state-selected:hover td, .k-state-selected {
    background-color: none!important;
    background: none!important;
}
.u_cbox .u_cbox_comment_box .u_cbox_area {
    border-bottom: 1px dotted #999;
}

    </style>

<script id="list-mobile-template" type="text/x-kendo-tmpl">
<div class="u_cbox">
<ul class="u_cbox_list"><li class="u_cbox_comment"><div class="u_cbox_comment_box"><div class="u_cbox_area">
<div class="u_cbox_info">
<span class="u_cbox_info_main">
<span class="u_cbox_name">
<span class="u_cbox_name_area">
<span class="u_cbox_nick_area">
<span class="k-state-hover">{seongmyeong} | {yeollakcheo}
</span>
</span>
</span>
</span>
</span>
<span class="u_cbox_info_sub">
</span></div><div class="u_cbox_text_wrap">
<span class="u_cbox_contents name" data-lang="ko">
    {zjiyeok} | 예상제작비 {yesangjejakbi}
</span></div><div class="u_cbox_info_base">
<span class="u_cbox_date">{wdate}
</span>

</div>

</div></div></li></ul>
</div>


  
</script>

    <?php
}
}
//end pageLoad

?>