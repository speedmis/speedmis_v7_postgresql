<?php 
$json_url = 'list_json.php';
$comment_PID = 'speedmis000974';
if($full_siteID=="rbk") {
    if($parent_gubun=="1028") {
        re_direct(replace($ServerVariables_URL,'parent_gubun=1028','parent_gubun=1009'));
    }
} else if($full_siteID=='121bible') {
    if($parent_gubun=="3859") {
        re_direct(replace($ServerVariables_URL,'?gubun=990','?gubun=3875'));
    }
} else if($full_siteID=='speedmis') {
    if($parent_gubun=="1035") {
        re_direct(replace($ServerVariables_URL,'parent_gubun=1035','parent_gubun=1040'));
    } else if($parent_gubun=="1040") {
        $comment_PID = 'speedmis001352';
    }
} else if($full_siteID=='speedsup') {
    if($parent_gubun=="1118") {
        re_direct(replace($ServerVariables_URL,'parent_gubun=1118','parent_gubun=1040'));
    }
} else {
    if($parent_RealPid=="speedmis001040") {
        $json_url = 'https://www.speedmis.com/_mis/list_json_speedmis001040.php';
        $parent_gubun = '1040';
        $comment_PID = 'speedmis001352';
    }
}

?>

<script>
    

var replays0 = `
    <li class="u_cbox_comment" idx="{idx}">
        <div class="u_cbox_comment_box u_cbox_type_profile"><span class="u_cbox_ico_reply"></span>
        <div class="u_cbox_area"><div class="u_cbox_info"><div class="u_cbox_thumb u_cbox_naver">
        <span class="u_cbox_thumb_wrap">
        <img onerror="this.src = 'img/noface3.jpg';" onload="if(this.naturalWidth<=20) this.src = 'img/noface3.jpg';" src="<?php if($full_siteID!='speedsup') echo 'https://www.speedmis.com/_mis/thumbnail.php?/z_support'; else echo 'thumbnail.php?'; ?>/uploadFiles/staffList/{wdater}.jpg" alt="프로필 이미지" class="u_cbox_img_profile">
        <span class="u_cbox_thumb_mask"></span></span></div>
        


        <span class="u_cbox_info_main">
        <span class="u_cbox_name"><span class="u_cbox_name_area"><span class="u_cbox_nick_area"><span class="u_cbox_nick">
        {table_wdaterQnusername}</span>
        </span></span></span></span>
        




<span class="u_cbox_info_sub {not_auth_hide}">
<span class="u_cbox_work_sub">
<a class="u_cbox_btn_open" onclick="onoff_button(this);">
<span class="u_cbox_ico_open">
</span>
</a>
<span class="u_cbox_work_box" style="display: block;">
<span class="u_cbox_work_inner delete" style="display: none;">
        <a class="u_cbox_btn_delete" onclick="deleteReply(this);">

<span class="u_cbox_in_delete">삭제</span>
</a>
</span>
</span>
</span>
</span>





        
        
        </div>
        <div class="u_cbox_text_wrap"><span class="u_cbox_contents">
        {contents}</span>
        </div>
        <div class="u_cbox_info_base"><span class="u_cbox_date">{wdate}</span></div>
        <div class="u_cbox_tool"><div class="u_cbox_recomm_set">
        <strong class="u_vc">공감/비공감</strong>
        <a href="javascript:;" idx="L{idx}" onclick="likeOrHate({idx},'L',this);" class="u_cbox_btn_recomm {zmyLH_like}">
        <span class="u_cbox_ico_recomm">공감</span><em class="u_cbox_cnt_recomm">{sel_like}</em></a>
        <a href="javascript:;" idx="H{idx}" onclick="likeOrHate({idx},'H',this);" class="u_cbox_btn_unrecomm {zmyLH_hate}">
        <span class="u_cbox_ico_unrecomm">비공감</span><em class="u_cbox_cnt_unrecomm">{sel_hate}</em></a></div></div></div></div>
    </li>
`;







</script>



<script id="list-mobile-template" type="text/x-kendo-template">


<li class="u_cbox_comment" id="comment_#: idx #" idx="#: idx #">

# if('<?php echo $misSessionUserId; ?>'==wdater || '<?php echo $misSessionUserId; ?>'=='gadmin' || '<?php echo $misSessionUserId; ?>'=='admin') { #
<div class="u_cbox_comment_box u_cbox_mine">
<div class="u_cbox_area">
<div class="u_cbox_info">


<div class="u_cbox_thumb u_cbox_naver"><span class="u_cbox_thumb_wrap"><img 
onerror="this.src = 'img/noface3.jpg';" onload="if(this.naturalWidth<=20) this.src = 'img/noface3.jpg';" 
src="<?php if($full_siteID!='speedsup') echo 'https://www.speedmis.com/_mis/thumbnail.php?/z_support'; else echo 'thumbnail.php?'; ?>/uploadFiles/staffList/#: wdater #.jpg" alt="프로필 이미지" class="u_cbox_img_profile" onerror="cbox.Util.onImageError(this);"><span class="u_cbox_thumb_mask"></span></span></div>




<span class="u_cbox_info_main">
<span class="u_cbox_name">
<span class="u_cbox_name_area">
<span class="u_cbox_nick_area">
<span class="u_cbox_nick">#: table_wdaterQnusername # | #: table_Station_NewNumQmstationname #</span>
</span>
</span>
</span>
</span>




<span class="u_cbox_info_sub 
# if('<?php echo $misSessionUserId; ?>'!=wdater) { #
auth_admin
# } #">
<span class="u_cbox_work_sub">
<a class="u_cbox_btn_open" onclick="onoff_button(this);">
<span class="u_cbox_ico_open">
</span>
</a>
<span class="u_cbox_work_box" style="display: block;">
<span class="u_cbox_work_inner delete" style="display: none;">
        <a class="u_cbox_btn_delete" onclick="deleteArticle(this);">

<span class="u_cbox_in_delete">삭제</span>
</a>
</span>
<span class="u_cbox_work_inner modify" style="display: none;">
        <a class="u_cbox_btn_delete" onclick="modifyArticle(this);">

<span class="u_cbox_in_delete">수정</span>
</a>
</span></span>
</span>
</span>




</div>
<div class="u_cbox_text_wrap">
<span class="u_cbox_contents" data-lang="ko" data-bind="html: contents"></span>
</div>
<div class="u_cbox_info_base">
<span class="u_cbox_date wdate">#: wdate #</span>
<span class="u_cbox_date lastupdate"># if(wdate!=lastupdate) { # , UP: #: lastupdate # # } # </span>
</div>











# } else { #



<div class="u_cbox_comment_box">
<div class="u_cbox_area" style="background-color:rgb(252 255 249);">
<div class="u_cbox_info">

<div class="u_cbox_thumb u_cbox_naver"><span class="u_cbox_thumb_wrap"><img 
onerror="this.src = 'img/noface3.jpg';" onload="if(this.naturalWidth<=20) this.src = 'img/noface3.jpg';" 
src="<?php if($full_siteID!='speedsup') echo 'https://www.speedmis.com/_mis/thumbnail.php?/z_support'; else echo 'thumbnail.php?'; ?>/uploadFiles/staffList/#: wdater #.jpg" alt="프로필 이미지" class="u_cbox_img_profile"><span class="u_cbox_thumb_mask"></span></span></div>

<span class="u_cbox_info_main">
<span class="u_cbox_name">
<span class="u_cbox_name_area">
<span class="u_cbox_nick_area">
<span class="u_cbox_nick">#: table_wdaterQnusername # | #: table_Station_NewNumQmstationname #</span>
</span>
</span>
</span>
</span>
</div>
<div class="u_cbox_text_wrap">
<span class="u_cbox_contents" data-lang="ko" data-bind="html: contents">#: contents #</span>
</div>
<div class="u_cbox_info_base">
<span class="u_cbox_date">#: wdate #</span>
</div>


# } #

# if(boardattach1!="" && boardattach1!=null) { #
            <p class="files">
            <span class="k-icon k-i-attachment-45"></span>    
            첨부파일(<span class="red">#: boardattach1.split(",").length #</span>): <span class="names">
            # for(i=0;i<boardattach1.split(",").length;i++) { #
                <span class="file" onmouseover="attach_over(this);" onclick="attach_click(this);">#: boardattach1.split(",")[i] #</span>
            # } #</span></p>
# } #            

<div class="u_cbox_tool"><a href="javascript:;" class="u_cbox_btn_reply u_cbox_btn_reply_on">
    <strong class="u_cbox_reply_txt">답글쓰기</strong><span class="u_cbox_reply_cnt">#: zdapgeulsu #</span></a>
    <div class="u_cbox_recomm_set"><strong class="u_vc">공감/비공감</strong>
    <a href="javascript:;" idx="L#: idx #" onclick="likeOrHate(#: idx #,'L',this);" class="u_cbox_btn_recomm
    # if(zmyLH=="L") { #
        u_cbox_btn_recomm_on
    # } #
    "><span class="u_cbox_ico_recomm">공감</span><em class="u_cbox_cnt_recomm">#: sel_like #</em></a>
    <a href="javascript:;" idx="H#: idx #" onclick="likeOrHate(#: idx #,'H',this);" class="u_cbox_btn_unrecomm
    # if(zmyLH=="H") { #
        u_cbox_btn_unrecomm_on
    # } #
    "><span class="u_cbox_ico_unrecomm">비공감</span>
    <em class="u_cbox_cnt_unrecomm">#: sel_hate #</em></a>
    </div>
</div>









</div>
</div>







<div class="u_cbox_reply_area" style="">
<ul id="comment_replys" idx="#: idx #" class="u_cbox_list" aria-labelledby="cbox_module_wai_u_cbox_sort_option_tab1">
</ul>
<iframe id="ifr_replys" idx="#: idx #" zdapgeulsu="#: zdapgeulsu #" style="display:none" onload="replays_draw(this);"></iframe>




<div class="u_cbox_write_wrap u_cbox_focus"><span class="u_cbox_ico_reply"></span><div class="u_cbox_write_box u_cbox_type_logged_in"><form><fieldset><legend class="u_vc">댓글 쓰기</legend><div class="u_cbox_write"><div class="u_cbox_write_inner"><div class="u_cbox_profile_area"><div class="u_cbox_profile"><strong class="u_vc">로그인 정보</strong><div class="u_cbox_thumb">
    <img 
    onerror="this.src = 'img/noface3.jpg';" onload="if(this.naturalWidth<=20) this.src = 'img/noface3.jpg';" 
    src="<?php if($full_siteID!='speedsup') echo 'https://www.speedmis.com/_mis/thumbnail.php?/z_support'; else echo 'thumbnail.php?'; ?>/uploadFiles/staffList/<?php echo $misSessionUserId; ?>.jpg" alt="<?php echo $MisSession_UserName; ?>" class="u_cbox_img_profile"></div><div class="u_cbox_box_name"><span class="u_cbox_write_name"><?php echo $MisSession_UserName; ?></span></div></div></div><div class="u_cbox_write_area"><strong class="u_vc">댓글 입력</strong><div class="u_cbox_inbox">
        <textarea title="답글" onkeyup="placeholders(this);" id="cbox_module__reply_textarea_#: idx #" class="u_cbox_text" rows="3" cols="30" data-log="RPC.replyinput"></textarea>
    <label for="cbox_module__reply_textarea_#: idx #" class="u_cbox_guide">
      건전한 댓글문화 정착을 위해 이용에 주의를 부탁드립니다.</label></div></div>
    <div class="u_cbox_upload_image" style="display:none"><span class="u_cbox_upload_image_wrap fileButton browsebutton _cboxImageSelect"><span class="u-cbox-browse-box"><input class="u-cbox-browse-file-input" type="file" name="browse" accept="image/*" title="이미지 추가"></span>
    <a href="javascript:;" class="u_cbox_upload_thumb_link u-cbox-browse-button" data-log="RPP.add"><span class="u_cbox_upload_thumb_add">이미지 추가</span><span class="u_cbox_upload_thumb_mask"></span></a></span></div>
    <div class="u_cbox_write_count"><span class="u_vc">현재 입력한 글자수</span><span class="u_cbox_write_total">< 로 시작하면 HTML 로 인식됩니다.</span></div>
    
    <div class="u_cbox_upload"><div class="u_cbox_addition"></div>
    <button type="button" class="u_cbox_btn_upload" onclick="reply_write(#: idx #);">
        <span class="u_cbox_ico_upload"></span><span class="u_cbox_txt_upload">등록</span>
    </button>
    </div>


    </div>

</div></div></fieldset></form></div></div>



<a style="display:none;" href="javascript:;" class="u_cbox_btn_fold"><span class="u_cbox_btn_fold_wrap">
    <span class="u_cbox_cnt_fold">답글 접기</span><span class="u_cbox_ico_fold"></span>
</span></a></div>









</li>


</script>


<link rel="stylesheet" href="css/comment.css">

<!--
https://n.news.naver.com/article/comment/001/0011413154
-->

<div id="cbox_module" class="_COMMENT _COMMENT_HIDE u_cbox">

<div class="u_cbox_wrap u_cbox_ko u_cbox_type_sort_favorite">


<div class="u_cbox_write_wrap">
<div class="u_cbox_write_box u_cbox_type_logged_in">
<form>
<fieldset>
<legend class="u_vc">댓글 쓰기</legend>
<div class="u_cbox_write">
<div class="u_cbox_write_inner">
<div class="u_cbox_profile_area">
<div class="u_cbox_box_name">
<span class="u_cbox_btn_social u_cbox_btn_social_on">
<span class="u_cbox_box_social_naver">
<span class="u_cbox_ico_social">
</span>

</span>
<span class="u_cbox_write_name"><?php echo $MisSession_UserName; ?></span>
</div>
</div>
<div class="u_cbox_write_area">
<strong class="u_vc">댓글 입력</strong>
<div class="u_cbox_inbox">
<textarea id="cbox_module__write_textarea" class="u_cbox_text" readonly rows="3" cols="30" data-log="RPC.input">
</textarea>
<label for="cbox_module__write_textarea" class="u_cbox_guide" data-action="write#placeholder" data-param="@event">댓글을 입력해주세요</label>
</div>
</div>

</div>
</div>
</fieldset>
</form>
</div>
</div>



<div class="u_cbox_sort">
<div class="u_cbox_sort_option">
<div class="u_cbox_sort_scroller">
<ul class="u_cbox_sort_option_list">
<li class="u_cbox_sort_option_wrap u_cbox_sort_option_on" recently="Y">
<a href="javascript:;" data-action="sort#request" data-param="favorite" data-log="RPS.like" class="u_cbox_select">
<span class="u_cbox_sort_label">최신순</span>
</a>
</li>
<li class="u_cbox_sort_option_wrap" recently="N">
<a href="javascript:;" data-action="sort#request" data-param="old" data-log="RPS.old" class="u_cbox_select">
<span class="u_cbox_ico_select">
</span>
<span class="u_cbox_sort_label">과거순</span>
</a>
</li>
</ul>
</div>
</div>
</div>

<div class="u_cbox_content_wrap">
<ul class="u_cbox_list">

<div id="listView" data-role="listview"
data-template="list-mobile-template"
data-bind="source: dataSourceListView"></div>



</ul>
</div>

<div class="u_cbox_paginate page_more" style="">
<a href="javascript:;" class="u_cbox_btn_more">
<span class="u_cbox_more_wrap">
<span class="u_cbox_box_more">
<span class="u_cbox_page_more">더보기</span>
<span class="u_cbox_ico_more">
</span>
</span>
</span>
</a>
</div>




<div class="u_cbox_paginate go_top" style="display: none">
<a onclick="$('div#main').scrollTop(0);" href="javascript:;" class="u_cbox_btn_fold">
<span class="u_cbox_more_wrap">
<span class="u_cbox_box_more">
<span class="u_cbox_page_more">맨 위로</span>

</span>
</span>
</a>
</div>




</div>
</div>



        <style>
div#cbox_module {
    height: 100%;
    overflow-y: auto;
}
			
            i#btn_urlCopy_overflow,li#btn_newopen_overflow {
                display: none!important;
            }

            a#btn_modify, li#btn_modify_overflowiew {
                display: none!important;
            }
            a#btn_view, li#btn_view_overflowiew {
                display: none!important;
            }
            a#btn_excel, li#btn_excel_overflow {
                display: none!important;
            }
            a#btn_pdf, li#btn_pdf_overflow {
                display: none!important;
            }

            a#btn_1, li#btn_1_overflow {
                display: inline-block!important;
            }

            body[isTop=N] #toolbar .k-overflow-anchor.k-button {
                display: none;
            }
            .k-toolbar .k-overflow-anchor {
                right: 102px;
            }

            .u_cbox .u_cbox_btn_fold {
                line-height: inherit; 
            }
            .u_cbox .u_cbox_reply_area .u_cbox_write_wrap {
                position: relative;
                margin-top: 1px;
            }
            #cbox_module .u_cbox_ico_reply {
                display: none;
            }
            #cbox_module .u_cbox_reply_area .u_cbox_write_wrap .u_cbox_write_box {
                padding-bottom: 13px;
            }
            .u_cbox fieldset {
                min-width: 0;
            }

            .u_cbox fieldset, .u_cbox img {
                border: 0;
            }
            .u_cbox .u_vc {
                overflow: hidden;
                position: absolute;
                clip: rect(0,0,0,0);
                width: 1px;
                height: 1px;
                margin: -1px;
            }
            #cbox_module .u_cbox_write .u_cbox_write_inner {
                overflow: hidden;
                border: 1px solid #d0d0d0;
                border-radius: 4px;
            }
            .u_cbox .u_cbox_write .u_cbox_write_area {
                max-width: 100%;
            }
            #cbox_module .u_cbox_btn_fold, #cbox_module .u_cbox_btn_more {
                padding: 0;
                margin: 0;
                font-size: 13px;
                font-weight: 500;
                color: #222;
            }
            #cbox_module .u_cbox_write .u_cbox_inbox {
                margin-right: 0;
                padding: 13px 14px 10px;
            }
            #cbox_module .u_cbox_type_logged_in .u_cbox_inbox .u_cbox_text, #cbox_module .u_cbox_type_logged_out .u_cbox_inbox .u_cbox_text {
                height: 22px;
                font-size: 15px;
            }
            .u_cbox .u_cbox_write .u_cbox_inbox .u_cbox_text {
                display: block;
                overflow-x: hidden;
                overflow-y: auto;
                position: relative;
                z-index: 1;
                width: calc(100% - 9px);
                height: 18px;
                border: none;
                background-color: transparent;
                font-size: 16px;
                line-height: 1.25;
                letter-spacing: -.5px;
                color: #333;
                -webkit-appearance: none;
                resize: none;
            }
            #cbox_module .u_cbox_write .u_cbox_inbox .u_cbox_guide {
                top: 13px;
                line-height: 20px;
                bottom: 12px;
                font-size: 15px;
                color: #999;
            }
            .u_cbox .u_cbox_btn_fold_wrap {
                display: inline-block;
                position: relative;
                vertical-align: top;
            }

            #cbox_module .u_cbox_thumb .u_cbox_thumb_wrap {
                margin-right: 8px;
            }

            .u_cbox .u_cbox_thumb .u_cbox_thumb_wrap {
                display: block;
                position: relative;
                margin-right: 4px;
                z-index: 10;
            }
            #cbox_module .u_cbox_comment_box .u_cbox_thumb_wrap .u_cbox_img_profile {
                width: 35px;
                height: 35px;
                border-radius: 50%;
            }

            .u_cbox .u_cbox_thumb .u_cbox_img_profile {
                width: 23px;
                height: 23px;
                vertical-align: top;
            }
            #cbox_module .u_cbox_thumb .u_cbox_thumb_mask {
                display: none;
            }
            .u_cbox .u_cbox_btn_fold {
                display: block;
                padding-left: 70px;
                margin-right: 70px;
                font-size: 14px;
                color: #333;
                height: 42px;
                line-height: 42px;
                text-align: center;
            }
            #cbox_module .u_cbox_thumb .u_cbox_thumb_wrap::after {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                border: 1px solid rgba(0,0,0,.1);
                border-radius: 50%;
                content: '';
            }
            #cbox_module .u_cbox_comment_box.u_cbox_type_profile .u_cbox_info_base {
                left: 43px;
            }

            #cbox_module .u_cbox_info_base {
                position: absolute;
                top: 38px;
                padding-top: 0;
                left: 57px;
            }
            .u_cbox .u_cbox_info_base {
                padding-top: 6px;
            }
            #cbox_module .u_cbox_write .u_cbox_btn_upload {
                display: none;
            }
            #cbox_module .u_cbox_write .u_cbox_thumb {
                position: relative;
                margin-right: 5px;
            }

            .u_cbox .u_cbox_thumb {
                float: left;
            }
            #cbox_module .u_cbox_write .u_cbox_thumb .u_cbox_img_profile {
                width: 26px;
                height: 26px;
                border-radius: 50%;
            }
            #cbox_module .u_cbox_write .u_cbox_thumb::after {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                border: 1px solid rgba(0,0,0,.1);
                border-radius: 50%;
                content: '';
            }
            #cbox_module .u_cbox_write .u_cbox_write_inner .u_cbox_thumb+.u_cbox_box_name {
                left: 45px;
            }

            #cbox_module .u_cbox_box_name, #cbox_module .u_cbox_social {
                top: 15px;
                line-height: 20px;
            }
            .u_cbox .u_cbox_write .u_cbox_write_inner .u_cbox_thumb+.u_cbox_box_name {
                left: 40px;
            }
            #cbox_module .u_cbox_box_name .u_cbox_write_name {
                display: inline-block;
                font-size: 14px;
                vertical-align: top;
                font-family: -apple-system,BlinkMacSystemFont,Helvetica,"Apple SD Gothic Neo",sans-serif;
            }

            .u_cbox .u_cbox_box_name .u_cbox_write_name {
                font-weight: 700;
            }
            #cbox_module .u_cbox_attached .u_cbox_inbox .u_cbox_text, #cbox_module .u_cbox_edit .u_cbox_inbox .u_cbox_text, #cbox_module .u_cbox_focus .u_cbox_inbox .u_cbox_text, #cbox_module .u_cbox_writing .u_cbox_inbox .u_cbox_text {
                height: 143px;
            }
            .u_cbox .u_cbox_attached .u_cbox_inbox .u_cbox_text, .u_cbox .u_cbox_edit .u_cbox_inbox .u_cbox_text, .u_cbox .u_cbox_focus .u_cbox_inbox .u_cbox_text, .u_cbox .u_cbox_writing .u_cbox_inbox .u_cbox_text {
                z-index: 20;
                height: 140px;
            }
            .u_cbox, .u_cbox button, .u_cbox input, .u_cbox select, .u_cbox table, .u_cbox textarea {
                font-size: 14px;
                line-height: 1.25em;
            }
            .u_cbox button, .u_cbox dd, .u_cbox dl, .u_cbox dt, .u_cbox fieldset, .u_cbox form, .u_cbox h1, .u_cbox h2, .u_cbox h3, .u_cbox h4, .u_cbox h5, .u_cbox h6, .u_cbox input, .u_cbox legend, .u_cbox li, .u_cbox ol, .u_cbox p, .u_cbox select, .u_cbox table, .u_cbox td, .u_cbox textarea, .u_cbox th, .u_cbox ul {
                margin: 0;
                padding: 0;
                font-family: Helvetica,sans-serif;
            }


            #cbox_module .u_cbox_attached .u_cbox_inbox .u_cbox_guide, #cbox_module .u_cbox_focus .u_cbox_inbox .u_cbox_guide {
                top: 15px;
            }
            #cbox_module .u_cbox_attached .u_cbox_inbox .u_cbox_guide, #cbox_module .u_cbox_edit .u_cbox_inbox .u_cbox_guide, #cbox_module .u_cbox_focus .u_cbox_inbox .u_cbox_guide, #cbox_module .u_cbox_writing .u_cbox_inbox .u_cbox_guide {
                opacity: .5;
                line-height: 20px;
            }
            .u_cbox .u_cbox_attached .u_cbox_inbox .u_cbox_guide, .u_cbox .u_cbox_focus .u_cbox_inbox .u_cbox_guide {
                display: block;
                display: -webkit-box;
                left: 14px;
                right: 14px;
                color: #d7d7d7;
                white-space: normal;
                -webkit-line-clamp: 6;
                -webkit-box-orient: vertical;
            }
            .u_cbox .u_cbox_write .u_cbox_inbox .u_cbox_guide {
                overflow: hidden;
                position: absolute;
                top: 10px;
                right: 75px;
                left: 11px;
                z-index: 10;
                border: none;
                font-size: 16px;
                color: #b6b6b6;
                line-height: 1.25;
                letter-spacing: -.5px;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            
            li {
                text-align: -webkit-match-parent;
            }
            .u_cbox ol, .u_cbox ul {
                list-style: none;
            }
            ul, ol {
                list-style: none;
            }
            
            ul {
                list-style-type: disc;
            }
            .u_cbox {
                position: relative;
                background-color: #fff;
                color: #000;
                text-align: left;
                -webkit-text-size-adjust: none;
            }
            #cbox_module .u_cbox_profile_area {
                padding-top: 14px;
                margin-bottom: 3px;
            }
            .u_cbox_write_wrap.u_cbox_focus {
                display: none;
            }
            .u_cbox_inbox textarea {
                padding: 0 5px;
            }

            @media only screen and (min-width: 1025px) {

                .as_mp_layout #cbox_module .u_cbox_write .u_cbox_inbox .u_cbox_guide {
                    top: 12px;
                    bottom: 13px;
                    font-size: 13px;
                }

            }

            @media (min-width: 768px) {
                #cbox_module .u_cbox_reply_area .u_cbox_comment_box.u_cbox_type_profile .u_cbox_area .u_cbox_info_base {
                    left: 43px;
                }
            }
        </style>

    

        

<script>
    

function placeholders(p_this) {
    txtid = p_this.id;
    lb = $('label[for="'+txtid+'"]');
    if(lb.css('display')=='none') {
        if(p_this.value=='') lb.show();
    } else {
        if(p_this.value!='') lb.hide();
    }
    if(p_this.value!='') {
        if(rgbToHexColor($('button.u_cbox_btn_upload').css('background-color'))!='#3f63bf') {
            $('button.u_cbox_btn_upload').css('background-color','#3f63bf');
        }
    } else {
        if(rgbToHexColor($('button.u_cbox_btn_upload').css('background-color'))=='#3f63bf') {
            $('button.u_cbox_btn_upload').css('background-color','');
        }
    }
}
function likeOrHate(p_idx, p_LH, p_this) {
    if(check_speedsup()==false) return false;
    $.ajax({
        type: "POST",
        url: "reply_treat.php",
        data: ({
            flag : 'likeOrHate',
            commentsIdx : p_idx,
            LH: p_LH
        }),
        dataType: "html",
        async: false,
        success: function(data) {
            //이미 공감한 글입니다.    이미 비공감한 글입니다.     존재하지 않는 글입니다.
            //alert(data);
            rr = eval(data);
                            
            $('a[idx="L'+p_idx).find('em')[0].innerText = rr[0]['cntL'];
            $('a[idx="H'+p_idx).find('em')[0].innerText = rr[0]['cntH'];
            if(rr[0]['msg']!='') {
                alert(rr[0]['msg']);
            } else {
                resultLH = Trim(rr[0]['resultLH']);
                if(p_LH=='L' && resultLH=='L') {
                    $(p_this).addClass('u_cbox_btn_recomm_on');
                } else if(p_LH=='L' && resultLH=='') {
                    $(p_this).removeClass('u_cbox_btn_recomm_on');
                } else if(p_LH=='H' && resultLH=='H') {
                    $(p_this).addClass('u_cbox_btn_unrecomm_on');
                } else if(p_LH=='H' && resultLH=='') {
                    $(p_this).removeClass('u_cbox_btn_unrecomm_on');
                } 
            }
        },
        error: function() {
            alert('비정상적인 에러입니다. 관리자에게 문의하세요.');
        }
    });
}
function reply_write(p_idx) {
    txt = $('textarea#cbox_module__reply_textarea_'+p_idx)[0];
    if(txt.value=='') {
        alert('답글을 먼저 입력하세요!');
        txt.focus();
        return false;
    }
    
    $.ajax({
        type: "POST",
        url: "reply_treat.php",
        data: ({
            flag : 'write',
            RealPid : '<?php echo $parent_RealPid; ?>',
            contents: encode_cafe24(txt.value),
            midx: '<?php echo $parent_idx; ?>',
            refidx: p_idx
        }),
        dataType: "html",
        async: false,
        success: function(data) {
            result = JSON.parse(data);
            if(result['resultCode']=='fail') {
                alert('저장이 실패하였습니다. 관리자에게 문의하세요.');
            } else {
                /* 아래의 코딩은 save.php 의 코드를 기준으로 복사하여 수정한 것임 */
                var mailList = result.mailList;
                var pushList = result.pushList;
                if(mailList!="" && mailList!="[]") {
                    title = replaceAll(location.hostname,'www.','');
                    contents = '답글 작성자 : ' + $('input#MisSession_UserName')[0].value + '<br>답글 : ' + txt.value;

                    if(document.getElementById("parent_idx").value!="") {

                        if(!isMainFrame() && parent.document.getElementById("gubun").value==document.getElementById("parent_gubun").value) {
                            if(parent.document.getElementById("MenuName").value!=parent.parent.document.getElementById("MenuName").value) {
                                contents = '<br>메뉴명 : ' + parent.parent.document.getElementById("MenuName").value + '(idx='
                                + parent.document.getElementById("parent_idx").value + ') 의 '
                                + parent.document.getElementById("MenuName").value + ' 의 댓글에 대한 답글'
                                +'<br>'+contents;
                                title = title + " " + parent.parent.document.getElementById("MenuName").value + '(idx='
                                + parent.document.getElementById("parent_idx").value + ') 의 '
                                + parent.document.getElementById("MenuName").value + ' 의 답글 / '+$('input#MisSession_UserName')[0].value;

                                url = siteHome() + '/_mis/index.php?gubun='+parent.document.getElementById('parent_gubun').value+'&idx='+parent.document.getElementById('parent_idx').value
                                +"&tabid=speedmis000974&tabrealpid_idx="+document.getElementById("parent_idx").value+"&tabrealpid_tabid="+document.getElementById("RealPid").value;
                            } else {
                                contents = '<br>메뉴명 : ' + parent.document.getElementById("MenuName").value
                                +'(idx='+document.getElementById("parent_idx").value+') 의 댓글에 대한 답글'
                                +'<br>'+contents;
                                title = title + " " + parent.document.getElementById("MenuName").value
                                +'(idx='+document.getElementById("parent_idx").value+') 의 답글 / '+$('input#MisSession_UserName')[0].value;
                                url = siteHome() + "/_mis/index.php?gubun="+document.getElementById("parent_gubun").value+"&idx="+document.getElementById("parent_idx").value+"&tabid=speedmis000974";
                            }
                        }
                        

                        //답글목록은 목록으로만 보여줘야 함.
                        contents = '답글은 다음과 같습니다 | <a href="'+url+'&isMenuIn=auto">열기</a><br>'+contents;
                        
                        email_sendGroupMessage(mailList, title, contents, '새글알리미');
                    } 
                    

                }
                if(pushList!="" && pushList!="[]" && document.getElementById("bot_name").value!="") {
                    title = "답글 안내";
                    contents = txt.value;
                    if(uni_len(title+contents)>80) contents = uni_left(contents, 79-uni_len(title))+"..";

                    if(document.getElementById("parent_idx").value!="") {

                        if(parent.document.getElementById("MenuName").value!=parent.parent.document.getElementById("MenuName").value) {
                            contents = "["+parent.parent.document.getElementById("MenuName").value+'(idx='
                            + parent.document.getElementById("parent_idx").value + ') 의 '+parent.document.getElementById("MenuName").value+" 의 댓글에 대한 답글 : " + contents;

                            url = siteHome() + '/_mis/index.php?gubun='+parent.document.getElementById('parent_gubun').value+'&idx='+parent.document.getElementById('parent_idx').value
                            +"&tabid=speedmis000974&tabrealpid_idx="+document.getElementById("parent_idx").value+"&tabrealpid_tabid="+document.getElementById("RealPid").value;
                        } else {
                            contents = '[' + parent.document.getElementById("MenuName").value
                            +'(idx='+document.getElementById("parent_idx").value+') 의 댓글에 대한 답글'
                            +'] '+contents;
                            url = siteHome() + "/_mis/index.php?gubun="+document.getElementById("parent_gubun").value+"&idx="+document.getElementById("parent_idx").value+"&tabid=speedmis000974";
                        }

                        //답글목록은 목록으로만 보여줘야 함.
                        if(document.getElementById("RealPid").value!="speedmis000974") url = url +"&tabrealpid_idx="+result.newIdx;
                        //텔레그램 push
                        if(typeof viewLogic_beforeSendChangeMessage=="function") {
                            contents = viewLogic_beforeSendChangeMessage(title,contents,url,result.newIdx);
                        } else {
                            contents = title+' <a href="'+url+'&isMenuIn=auto">'+contents+'</a>';
                        }
                        
                        telegram_sendGroupMessage(pushList, contents);

                    }

                }
                /* push 전송 end */
                $('a#btn_reload').click();
            }
        },
        error: function() {
            alert('비정상적인 에러입니다. 관리자에게 문의하세요.');
        }
    });

}


    function viewModel_bind() {

        viewModel = kendo.observable({
            dataSourceListView: new kendo.data.DataSource({
                schema: {
                    model: {
                        fields: { 
                            idx: { type: "number", format: "{0}", editable: false },
                            midx: { type: "string", editable: false }, 
                        }
                    },
                    data: "d.results",
                },
                serverPaging: true,
                type: "odata",
                transport: {
                    read: {
                        type: "POST",
                        data: {
                            recently: "N",
                            $orderby: "refidx desc,idx",    //최신순
                            $top: 10
                        },
                        url: '<?php echo $json_url; ?>?<?php echo iif(requestVB('lite')=='Y','lite=Y&',''); ?>flag=read&RealPid=<?php echo $comment_PID; ?>&parent_gubun=<?php echo $parent_gubun; ?>&parent_idx=<?php echo $parent_idx; ?>&allFilter=[{"operator":"eq","value":"Y","field":"toolbar_zrefidx0"}]',
                        dataType: "jsonp",
                        complete: function (jqXHR, textStatus) {
                            if(jqXHR.responseJSON==undefined) {
                                //toastr.error("해당 내역 조회에 실패했습니다.");
                                return false;
                            }
                            if(jqXHR.responseJSON.d.resultCode=="success") {
                                //$("li#btn_menuName_overflow")[0].style.setProperty ("display", "none", "important");
                                var panelBar = $("#panelbar-left").data("kendoPanelBar");
                                //panelBar.select($($('div#panelbar-left > li')[4]));
                                
                                if(isMainFrame()) {
                                    if($('div#listView').data('kendoListView')) {
                                        if(jqXHR.responseJSON.d.__count*1<$('div#listView').data('kendoListView').options.dataSource.options.transport.read.data.$top*1) $('a.u_cbox_btn_more').css("display","none");
                                    } else $('a.u_cbox_btn_more').css("display","none");
                                } else {
                                    
                                    tabObj = parent.$("div#round_"+windowID()).closest("div[tabnumber]");
                                    if(tabObj) {
                                        tabnumber = tabObj.attr("tabnumber");
                                        if(tabnumber!=undefined) {
                                            if(jqXHR.responseJSON.d.__count*1>99) {
                                                parent.$('body li[tabnumber="'+tabnumber+'"] .cnt').text("99+");
                                            } else {
                                                parent.$('body li[tabnumber="'+tabnumber+'"] .cnt').text(jqXHR.responseJSON.d.__count);
                                            }
                                            parent.$('body li[tabnumber="'+tabnumber+'"] .cnt').attr("cnt",jqXHR.responseJSON.d.__count);
                                            
                                            if($('div#listView').data('kendoListView')) {
                                                if(jqXHR.responseJSON.d.__count*1<$('div#listView').data('kendoListView').options.dataSource.options.transport.read.data.$top*1) $('a.u_cbox_btn_more').css("display","none");
                                            } else $('a.u_cbox_btn_more').css("display","none");

                                        }
                                    }
                                    

                                }

                                //댓글갯수표시
                                tabObj = parent.$("div#round_"+windowID()).closest("div[tabnumber]");
                                if(tabObj) {
                                    tabnumber = tabObj.attr("tabnumber");
                                    if(tabnumber!=undefined) {
                                        if(jqXHR.responseJSON.d.__count*1>99) {
                                            parent.$('body li[tabnumber="'+tabnumber+'"] .cnt').text("99+");
                                        } else {
                                            parent.$('body li[tabnumber="'+tabnumber+'"] .cnt').text(jqXHR.responseJSON.d.__count);
                                        }
                                        parent.$('body li[tabnumber="'+tabnumber+'"] .cnt').attr("cnt",jqXHR.responseJSON.d.__count);
                                    }
                                }

                                $('a.u_cbox_btn_reply.u_cbox_btn_reply_on').click( function() {
                                    if(check_speedsup()==false) return false;
                                    if($(this).closest('li.u_cbox_comment').find('.u_cbox_write_wrap.u_cbox_focus').css('display')=='block') {
                                        $(this).closest('li.u_cbox_comment').find('.u_cbox_write_wrap.u_cbox_focus').css('display','none');
                                    } else {
                                        $('.u_cbox_write_wrap.u_cbox_focus').css('display','none');
                                        $(this).closest('li.u_cbox_comment').find('.u_cbox_write_wrap.u_cbox_focus').css('display','block');
                                        $(this).closest('li.u_cbox_comment').find('textarea').focus();
                                    }
                                });
                                

                            } else if(jqXHR.responseJSON.d.resultCode!="") {
                                toastr.error(jqXHR.responseJSON.d.resultMessage);
                            }
                            if(jqXHR.responseJSON.d.afterScript!="") eval(jqXHR.responseJSON.d.afterScript);



                        }
                    }
                },
            }),

        });
        
        kendo.bind($("#example"), viewModel);




    }




viewModel_bind();

//입력완료 후, 모래시계 현상 없애기
displayLoadingOff();


function check_speedsup() {
    <?php if($full_siteID!='speedsup' && $parent_RealPid=='speedmis001040') { ?>
    if(!confirm('원하시는 작업을 수행하기 위해 SpeedMIS Support 페이지로 이동할까요?')) return false;
    url = 'http://support.speedmis.com/_mis/login/?preaddress=/_mis/index.php?gubun=1040&isMenuIn=auto&treemenu=Y&list_idx='+getUrlParameter('parent_idx');
    window.open(url);
    return false;
    <?php } ?>
}
	
	
$('.u_cbox_write_wrap').click( function() {
    if(check_speedsup()==false) return false;
    <?php if($misSessionUserId=='임시로그인' || $misSessionUserId=='guest') { ?>
    if(!confirm('구글인증 로그인을 하셔야 댓글작성이 가능합니다. 진행할까요?')) return false;
    
    if(isMainFrame()) {
        url = '/_mis/google_oauth/url.php?'+location.href;
    } else if(parent.isMainFrame()) {
        url = '/_mis/google_oauth/url.php?'+parent.status_view_url();
    } else {
        url = '/_mis/google_oauth/url.php?'+parent.parent.status_url()+'&list_idx='+parent.getID('idx').value;
    }
    top.location.href = url;
    <?php } else { ?>
        url = "<?php echo replace(replace($ServerVariables_URL,'index_main.php','index.php'), "gubun=".RealPidIntoGubun($real_pid), "gubun=".RealPidIntoGubun($comment_PID)); ?>&ActionFlag=write&idx=0";
        const urlObj = new URL(url, location.origin);
        urlObj.searchParams.set("parent_idx", document.getElementById('parent_idx').value);
        url = urlObj.toString();
        location.href = url;
    <?php } ?>
    return false;
})

$('body').attr('isTop', "Y");
$('div#main').scroll( function() {
    if($('div#example-search-wrapper').offset().top>=-2) var isTop = "Y"; else var isTop = "N";
    $('body').attr('isTop', isTop);
});

$('li.u_cbox_sort_option_wrap').click(function() {
    $('li.u_cbox_sort_option_wrap').removeClass('u_cbox_sort_option_on');
    $(this).addClass('u_cbox_sort_option_on');
    page = 1;
    if($(this).attr("recently")=='Y') {
        $('div#listView').data('kendoListView').dataSource.transport.options.read.data.$orderby = 'refidx desc,idx';
    } else {
        $('div#listView').data('kendoListView').dataSource.transport.options.read.data.$orderby = 'refidx,idx';
    }
    $('div#listView').data('kendoListView').dataSource.read();
    $('a.u_cbox_btn_more').css("display","block");
});


var page = 1;

function nextPage() {
    page++;
    var lastItem = $('#listView .u_cbox_comment').last();
    console.log(lastItem);
    var listView = $('div#listView').data('kendoListView');

    var dataSourceScroll = new kendo.data.DataSource({
        schema: {
            model: {
                fields: { 
                    idx: { type: "number", format: "{0}", editable: false },
                    midx: { type: "string", editable: false }, 
                }
            },
            data: "d.results",
        },
        serverPaging: true,
        type: "odata",
        transport: {
            read: {
                type: "POST",
                data: {
                    recently: 'N',
                    $orderby: iif($('li.u_cbox_sort_option_wrap').attr('recently')=='Y',"refidx desc, idx","refidx, idx"),
                    $top: $('div#listView').data('kendoListView').options.dataSource.options.transport.read.data.$top
                },
                url: '<?php echo $json_url; ?>?flag=read&RealPid=<?php echo $comment_PID; ?>&parent_gubun=<?php echo $parent_gubun; ?>&parent_idx=<?php echo $parent_idx; ?>&allFilter=[{"operator":"eq","value":"Y","field":"toolbar_zrefidx0"}]',
                dataType: "jsonp",
                
            }
        },
    });
    
    dataSourceScroll.query({
        page: page,
        pageSize: $('div#listView').data('kendoListView').options.dataSource.options.transport.read.data.$top
    }).then(function () {
        if(dataSourceScroll.data().length>0) {
            dataSourceScroll.data().forEach(x => {
                console.log(listView.dataSource);
                listView.dataSource.add(x);
            });
        } 

        if(dataSourceScroll._pristineTotal*1 <= dataSourceScroll._skip*1+dataSourceScroll._take) {
            $('a.u_cbox_btn_more').css("display","none");
            $('.u_cbox_paginate.go_top').css("display","block");
        }
    });
}

$('a.u_cbox_btn_more').click( function() {
    nextPage();
});

function attach_over(p_this) {
    
}
function attach_click(p_this) {
    var idx = $(p_this).closest('li.u_cbox_comment').attr('idx');
    var fileName = p_this.innerText;
    //info url 은 항상  speedmis000974 임.  $full_siteID=='speedmis' 는 의문/과제.
    <?php if($full_siteID=='speedmisxxx') { ?>
        var info_url = "/z_support/_mis/info.php?RealPid=speedmis000974&key_aliasName=idx&MSUI=<?php echo $misSessionUserId; ?>&thisAlias=boardattach1&key_idx="+idx;
        var url = '/z_support'+ajax_url_return(info_url+"&flag=download&fileName="+encodeURI(fileName));
    <?php } else { ?>
        var info_url = "info.php?RealPid=speedmis000974&key_aliasName=idx&thisAlias=boardattach1&key_idx="+idx;
        var url = ajax_url_return(info_url+"&flag=download&fileName="+encodeURI(fileName));
    <?php } ?>
    if(isMobile()) {
        if(isWebPlayFile(url)) window.open(url);
        else location.href = url;
    } else {
        var x=new XMLHttpRequest();
        x.open("GET", url, true);
        x.responseType = 'blob';
        x.onload=function(e){download(x.response, fileName, "application/zip" ); }
        x.send();
    }
}

//thisLogic_toolbar 도 simple 에서는 페이지 로딩 후의 실행으로 이용가능.
function thisLogic_toolbar() {
    //btn_reload 의 기존이벤트를 모두 없앰.
    $('a#btn_reload')[0].outerHTML = $('a#btn_reload')[0].outerHTML;
    $('a#btn_reload').click( function() {
        if($('div#listView').data('kendoListView')) $('div#listView').data('kendoListView').dataSource.read(); 
    });
}


function replays_draw(p_this) {
    if($(p_this).attr('zdapgeulsu')=='0') return false;
    p_idx = $(p_this).attr('idx');
    url = '<?php echo $json_url; ?>?flag=read&RealPid=<?php echo $comment_PID; ?>&parent_gubun=<?php echo $parent_gubun; ?>&parent_idx=<?php echo $parent_idx; ?>&allFilter=[{"operator":"eq","value":"N","field":"toolbar_zrefidx0"},{"operator":"eq","value":"'+p_idx+'","field":"toolbar_refidx"}]&flag=readResult&$orderby=idx&recently=N&$callback=jQuery1124011063766301926448_1642346369714';
    data = ajax_url_return(url);
    data = eval(replaceAll(data, '@dda', '"'));
    replaysAll = '';
    for(i=0;i<data.length;i++) {
        replays = replaceAll(replays0, '{idx}', data[i]['idx']);

        contents = data[i]['contents'];
        if(Left(contents,1)!='<') contents = TextToHtml(contents);

        replays = replaceAll(replays, '{contents}', contents);
        replays = replaceAll(replays, '{wdater}', data[i]['wdater']);
        replays = replaceAll(replays, '{wdate}', data[i]['wdate']);

        if(data[i]['wdater']!='<?php echo $misSessionUserId?>') {
            if('<?php echo $misSessionUserId?>'=='gadmin' || '<?php echo $misSessionUserId?>'=='admin') {
                replays = replaceAll(replays, '{not_auth_hide}', 'auth_admin');
            } else {
                replays = replaceAll(replays, '{not_auth_hide}', 'hide');
            }
        }
        

        replays = replaceAll(replays, '{table_wdaterQnusername}', data[i]['table_wdaterQnusername']);
        replays = replaceAll(replays, '{sel_like}', data[i]['sel_like']);
        replays = replaceAll(replays, '{sel_hate}', data[i]['sel_hate']);

        if(data[i]['zmyLH']=='L') replays = replaceAll(replays, '{zmyLH_like}', 'u_cbox_btn_recomm_on');
        else if(data[i]['zmyLH']=='H') replays = replaceAll(replays, '{zmyLH_hate}', 'u_cbox_btn_unrecomm_on');

        replaysAll = replaysAll+replays;
    }
    $('ul#comment_replys[idx="'+$(p_this).attr('idx')+'"]').html(replaysAll);
}

function onoff_button(p_this) {
    if(check_speedsup()==false) return false;
    var obj_delete = $(p_this).closest('div.u_cbox_area').find('.u_cbox_work_inner.delete');
    var obj_modify = $(p_this).closest('div.u_cbox_area').find('.u_cbox_work_inner.modify');
    if(obj_delete.css('display')=='none') {
        obj_delete.show();
    	obj_modify.show();
    } else {
        obj_delete.hide();
    	obj_modify.hide();   
    }
}

function modifyArticle(p_this) {
    if(check_speedsup()==false) return false;
    var idx = $(p_this).closest('li[idx]').attr('idx');
    var url = "index.php?RealPid=<?php echo $comment_PID; ?>&parent_gubun=<?php echo $parent_gubun?>&parent_idx=<?php echo $parent_idx?>&idx="+idx+"&ActionFlag=modify";
    location.href = url;
}
function deleteArticle(p_this) {
    if(check_speedsup()==false) return false;
    if($(p_this).closest('span.u_cbox_info_sub').hasClass('auth_admin')==true) {
        if(!confirm("본인의 글은 아니지만, admin 관리자로서 해당 댓글을 삭제하시겠습니까?")) return false;
    } else if(!confirm("해당 댓글을 삭제하시겠습니까?")) return false;


    var deleteList = [];
    var idx = $(p_this).closest('li[idx]').attr('idx');
    deleteList.push(idx);
    $.ajax({
        method: "POST",
        url: "save.php",
        data: { deleteList : JSON.stringify(deleteList), key_aliasName : "idx"
            ,ActionFlag : "delete", RealPid : "<?php echo $comment_PID; ?>", MisJoinPid : "", parent_gubun : "<?php echo $parent_gubun?>" }
    })
    .done(function( result ) {
        parent.toastr.success(JSON.parse(result).msg, "처리결과", {timeOut: 2000, positionClass: "toast-bottom-right"});
        //location.href = location.href;
        $('div#listView').data('kendoListView').dataSource.read();
    })
    .fail(function() {
        debugger;
        console.log( "save error" );
    });
}
function deleteReply(p_this) {
    if(check_speedsup()==false) return false;
    if($(p_this).closest('span.u_cbox_info_sub').hasClass('auth_admin')==true) {
        if(!confirm("본인의 글은 아니지만, admin 관리자로서 해당 답글을 삭제하시겠습니까?")) return false;
    } else if(!confirm("해당 답글을 삭제하시겠습니까?")) return false;

    var deleteList = [];
    var idx = $(p_this).closest('li.u_cbox_comment').attr('idx');
    deleteList.push(idx);
    $.ajax({
        method: "POST",
        url: "save.php",
        data: { deleteList : JSON.stringify(deleteList), key_aliasName : "idx"
            ,ActionFlag : "delete", RealPid : "<?php echo $comment_PID; ?>", MisJoinPid : "", parent_gubun : "<?php echo $parent_gubun?>" }
    })
    .done(function( result ) {
        parent.toastr.success(JSON.parse(result).msg, "처리결과", {timeOut: 2000, positionClass: "toast-bottom-right"});
        //location.href = location.href;
        $('div#listView').data('kendoListView').dataSource.read();
    })
    .fail(function() {
        debugger;
        console.log( "save error" );
    });
}


</script>

