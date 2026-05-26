<?php
$only_gadmin_YN = "Y";	//항상 gadmin 기준으로만으로 확정. 수정금지.

?>
<script type="text/x-kendo-tmpl" id="template_speedmis000338">
<section id="profile" class="well">
    <div class="row">
        <div class="leftarea">
            <img src="/uploadFiles/staffList/<?php echo $misSessionUserId; ?>.jpg?<?php echo time(); ?>" 
            onerror="this.src = 'img/noface3.jpg';" class="ra-avatar img-responsive">
        </div>

        <div class="rightarea">
            <span class="ra-first-name">#:UserName#</span>
            <span class="ra-last-name">#:table_positionNumQnkname#</span>
            <div class="ra-position">
                <div>TEAM : #:table_Station_NewNumQnstationname#</div>
                <div>HP : #:HandPhone#</div>
                <div>E-MAIL : #:email#</div>
        </div>
    </div>
</section>
</script>
<style>
    #profile .row > div {
        float: left;
    }
    #profile .row > div.leftarea {
        width: calc(40% - 20px);
        padding: 0 10px;
    }
    #profile .row > div.rightarea {
        width: calc(60% - 0px);
        color: #555;
    }
    .ra-avatar {
        border: 1px solid #e7e7e7;
        border-radius: 2px;
        height: 100%;
        float: right;
        max-height: 154px;
    }
    .ra-first-name {
        display: block;
        margin-top: 0.8571em;
    }
    .ra-last-name {
        display: block;
        font-size: 1.7143em;
        line-height: 1.3em;
        max-height: 62px;
        overflow-y: auto;
    }
    .ra-position {
        font-size: 0.8571em;
        color: #999;
        padding: 10px 0 0 0;
        line-height: 150%;
    }
	
	div.widget.W2 {
		width: calc(100% - 10px)!important;
	}
	.k-grid-header {
		padding-right: 0px!important;
	}
	.k-widget.k-listview div.name {
		top: 5px;
		position: relative;
	}
	
</style>

<script type="text/x-kendo-tmpl" id="template_speedmis000633">
    <div class="product-view k-widget">
        <div class="sitelist">
            <div class="name">#:Kname#</div>
            <div class="url">#: makeUrl(Kname2) #</div>
        </dl>
    </div>
</script>
<style>
    .sitelist > div {
        width: 50%;
        float: left;
        margin: 3px 0 7px 0;
        white-space: nowrap;
        text-overflow: ellipsis;
        overflow: hidden;
        word-wrap: normal;
    }
</style>
<div id="dialog"></div>


        <div id="dashboard" class="panel-wrap">

        <div id="main-content">
        <?php
        if($MS_MJ_MY=='MY') {
            $sql = "select table_m.idx
            ,case when ifnull(table_m.title,'')<>'' then table_m.title else table_RealPid.menuname end as menuname
            ,table_m.RealPid as 'RealPid'
            ,table_RealPid.MisJoinPid
            ,table_RealPid.idx as 'gubun'
            ,table_RealPid.MenuType
            ,case when ifnull(table_m.AddURL,'')<>'' then table_m.AddURL else table_RealPid.AddURL end as AddURL
            ,table_m.isNotRecently
            ,table_m.W2
            ,table_m.H2
            ,table_m.no_link
            ,table_m.use_template
            from MisFavoriteMenu table_m   
            left outer join MisMenuList table_RealPid on table_RealPid.RealPid = table_m.RealPid and (table_RealPid.MenuType in ('01','02','04','06','21','22') 
            and table_RealPid.useflag=1 and table_RealPid.menuname not like 'x%')   
            left outer join MisMenuList_Member table_member on table_member.RealPid = table_RealPid.RealPid and table_member.userid='" . $misSessionUserId . "'   
            where table_m.isMain='Y' and table_m.useflag='1' and 
            ifnull(table_m.isPublic,'')='Y' and table_RealPid.useflag=1 and (ifnull(table_RealPid.AuthCode,'')<>'02' or table_member.userid='" . $misSessionUserId . "' and table_member.AuthorityLevel<>9) and ifnull(table_m.gidx,0)=0
            order by ifnull(table_m.isPublic,'') desc
            ";
        } else {
            $sql = "select table_m.idx
            ,case when isnull(table_m.title,'')<>'' then table_m.title else table_RealPid.menuname end as menuname
            ,table_m.RealPid as 'RealPid'
            ,table_RealPid.MisJoinPid
            ,table_RealPid.idx as 'gubun'
            ,table_RealPid.MenuType
            ,case when isnull(table_m.AddURL,'')<>'' then table_m.AddURL else table_RealPid.AddURL end as AddURL
            ,table_m.isNotRecently
            ,table_m.W2
            ,table_m.H2
            ,table_m.no_link
            ,table_m.use_template
            from MisFavoriteMenu table_m   
            left outer join MisMenuList table_RealPid on table_RealPid.RealPid = table_m.RealPid and (table_RealPid.MenuType in ('01','02','04','06','21','22') 
            and table_RealPid.useflag=1 and table_RealPid.menuname not like 'x%')   
            left outer join MisMenuList_Member table_member on table_member.RealPid = table_RealPid.RealPid and table_member.userid='" . $misSessionUserId . "'   
            where table_m.isMain='Y' and table_m.useflag='1' and 
            isnull(table_m.isPublic,'')='Y' and table_RealPid.useflag=1 and (isnull(table_RealPid.AuthCode,'')<>'02' or table_member.userid='" . $misSessionUserId . "' and table_member.AuthorityLevel<>9) and isnull(table_m.gidx,0)=0
            order by isnull(table_m.isPublic,'') desc
            ";
        }
            
            $result = allreturnSql($sql);

            $mm = 0;
            
            $cnt_result = count($result);
            while ($mm<$cnt_result) {
                //print_r($mm . ":" . $result[$mm]["menuname"]);
                $dash_gubun = $result[$mm]["gubun"];
                $dash_RealPid = $result[$mm]["RealPid"];
                $dash_misJoinPid = $result[$mm]["MisJoinPid"];
                $dash_menuname = $result[$mm]["menuname"];
                $dash_MenuType = $result[$mm]["MenuType"];
                $dash_AddURL = $result[$mm]["AddURL"];
                $dash_AddURL = replace($dash_AddURL, '&orderby=', '&$orderby=');

                $dash_W2 = $result[$mm]["W2"];
                if($dash_W2==1) $dash_W2 = "W2"; else $dash_W2 = "";
                $dash_H2 = $result[$mm]["H2"];
                if($dash_H2==1) $dash_H2 = "H2"; else $dash_H2 = "";

                $dash_isNotRecently = $result[$mm]["isNotRecently"];
                $dash_no_link = $result[$mm]["no_link"];
                $dash_use_template = $result[$mm]["use_template"];
                
                if($dash_MenuType=="21" || $dash_MenuType=="22" || InStr($dash_AddURL,'&chartKey=')>0) {
        ?> 

                <div id="dash_<?php echo $dash_RealPid;?>" class="widget m<?php echo $dash_MenuType . ' ' . $dash_W2 . ' ' . $dash_H2;?>">
                    <h3 class="k-state-hover"><?php echo $dash_menuname . iif(InStr($dash_AddURL,'&chartKey=')>0,' 차트','');?> 
                    <?php if($dash_no_link==1) {} else { ?>
                        <span class="collapse k-icon <?php echo iif($dash_RealPid=='speedmis000633','k-i-custom','k-i-hyperlink-open');?>" 
                        <?php if($dash_RealPid=='speedmis000633' && $misSessionIsAdmin!='Y' && $misSessionUserId!='gadmin') echo 'style="display:none;"';?>
                        pid="<?php echo $dash_RealPid;?>" gubun="<?php echo $dash_gubun;?>"></span>
                    <?php } ?>
                    </h3>
                    <div class="k-content">
                        <iframe id="ifr_<?php echo $dash_RealPid;?>" src="index.php?gubun=<?php echo $dash_gubun;?><?php echo $dash_AddURL;?>"
                        marginwidth="0" marginheight="0" frameborder="0"></iframe>
                    </div>
                </div>
        <?php
                } else if(Left($dash_MenuType,1)=="0") {
                    if($MS_MJ_MY=='MY') {
                        $sql = "select table_m.idx
                    ,table_m.aliasName as 'field'
                    ,table_m.Grid_Columns_Title as 'title'
                    ,case when table_m.Grid_Columns_Width=0 or table_m.Grid_Columns_Width=-1 then 1 else 0 end as 'hidden'
                    ,14+8*abs(table_m.Grid_Columns_Width) as 'width'
                    ,table_m.Grid_Columns_Width as 'width0'
                    ,table_m.Grid_Align
                    ,table_m.Grid_Orderby
                    ,table_m.Grid_Schema_Type
                    from MisMenuList_Detail table_m   
                    where table_m.useflag='1' and table_m.aliasName<>'' and table_m.RealPid = '" . iif($dash_MenuType=="06", $dash_misJoinPid, $dash_RealPid) . "'
                    and (table_m.SortElement<=15 or (table_m.Grid_Columns_Width<-1 or table_m.Grid_Columns_Width>0) or isnumeric(left(table_m.Grid_Orderby,1))=1)
                    order by case when table_m.SortElement<=2 then 0 else 1 end, table_m.SortElement limit 40;";
                    } else {
                        $sql = "select top 40 table_m.idx
                    ,table_m.aliasName as 'field'
                    ,table_m.Grid_Columns_Title as 'title'
                    ,case when table_m.Grid_Columns_Width=0 or table_m.Grid_Columns_Width=-1 then 1 else 0 end as 'hidden'
                    ,14+8*abs(table_m.Grid_Columns_Width) as 'width'
                    ,table_m.Grid_Columns_Width as 'width0'
                    ,table_m.Grid_Align
                    ,table_m.Grid_Orderby
                    ,table_m.Grid_Schema_Type
                    from MisMenuList_Detail table_m   
                    where table_m.useflag='1' and table_m.aliasName<>'' and table_m.RealPid = '" . iif($dash_MenuType=="06", $dash_misJoinPid, $dash_RealPid) . "'
                    and (table_m.SortElement<=15 or (table_m.Grid_Columns_Width<-1 or table_m.Grid_Columns_Width>0) or isnumeric(left(table_m.Grid_Orderby,1))=1)
                    order by case when table_m.SortElement<=2 then 0 else 1 end, table_m.SortElement";
                    }
                    

                    $data_columns = jsonReturnSql($sql);

                    if($dash_use_template==1) {
        ?>
                <div id="dash_<?php echo $dash_RealPid;?>" class="widget m01 <?php echo $dash_W2 . ' ' . $dash_H2;?>">
                <h3 class="k-state-hover"><?php echo $dash_menuname;?> 

                    <?php if($dash_no_link==1) {} else { ?>
                        <span class="collapse k-icon <?php echo iif($dash_RealPid=='speedmis000633','k-i-custom','k-i-hyperlink-open');?>" 
                        <?php if($dash_RealPid=='speedmis000633' && $misSessionIsAdmin!='Y' && $misSessionUserId!='gadmin') echo 'style="display:none;"';?>
                        pid="<?php echo $dash_RealPid;?>" gubun="<?php echo $dash_gubun;?>"></span>
                    <?php } ?>
                    </h3>

                    <div data-role="listview"
                            data-template="template_<?php echo $dash_RealPid;?>"
                            data-bind="source: data_<?php echo $dash_RealPid;?>,visible: isVisible"
                            >
                    </div>

                </div>
        <?php
                    } else {
        ?> 

                <div id="dash_<?php echo $dash_RealPid;?>" class="widget m01 <?php echo $dash_W2 . ' ' . $dash_H2;?>">
                    <h3 class="k-state-hover"><?php echo $dash_menuname;?> 
                        
                    
                    <span class="collapse k-icon <?php echo iif($dash_RealPid=='speedmis000633','k-i-custom','k-i-hyperlink-open');?>" 
                    <?php if($dash_RealPid=='speedmis000633' && $misSessionIsAdmin!='Y' && $misSessionUserId!='gadmin') echo 'style="display:none;"';?>
                    pid="<?php echo $dash_RealPid;?>" gubun="<?php echo $dash_gubun;?>"></span>
                
                    </h3>

                    <div data-role="grid"
                    data-columns='<?php echo $data_columns;?>'
                    data-selectable="true"
                    data-bind="
                    source: data_<?php echo $dash_RealPid;?>,
                    visible: isVisible,
                    events: { change: gridClick }
                    "
                    data-resizable="true"
                    >
                    </div>
                        
                </div>

        <?php
                    }        

                }

                ++$mm;

            }
        ?> 

            </div>
            <div id="sidebar">


            </div>
        </div>


        <script>


function thisLogic_toolbar() {
	
	<?php if($only_gadmin_YN=="Y") { ?>
	if(getID("MisSession_UserID").value!="gadmin") return false;
	<?php } ?>
	
	
    $("a#btn_1").text("배열설정");
    $("li#btn_1_overflow").text("배열설정");
    $("#btn_1").css("background", "#88f");
    $("#btn_1").css("color", "#fff");

    $("#btn_1").click( function() {
        $("#sidebar").kendoSortable({
            filter: ">div",
            handler: "h3",
            connectWith: "#main-content",
            placeholder: placeholder,
        });
        $("#main-content").kendoSortable({
            filter: ">div",
            handler: "h3",
            connectWith: "#sidebar",
            placeholder: placeholder,
        });

        $('.widget h3').css('cursor','move');

        $("a#btn_1").after('<a id="btn_2" class="k-button"></a>');
        $("a#btn_2").text("설정저장");
        $("#btn_2").css("background", "#88f");
        $("#btn_2").css("color", "#fff");
        $("#btn_2").click( function() {

            var mainSortable = {};
            var maincontent = [];
            var sidebar = [];
            $('#main-content div.widget').each(function() {
                maincontent.push(this.id);
            });
            $('#sidebar div.widget').each(function() {
                sidebar.push(this.id);
            });
            mainSortable = { "maincontent": maincontent, "sidebar": sidebar }
            $.ajax({
            type: "POST",
                url: "../_mis_addLogic/<?php echo $real_pid; ?>_treat.php",
                data: {
                    mainSortable: JSON.stringify(mainSortable)
                },
                success: function (data) {
                    alert("설정저장이 완료되었습니다.");
                    location.href = location.href;
                },
                error: function(data){
                    alert("설정저장이 실패하였습니다.");
                },
                dataType : "json",
            });
        });


        $("a#btn_2").after('<a id="btn_3" class="k-button"></a>');
        $("a#btn_3").text("취소");
        $("#btn_3").css("background", "#f88");
        $("#btn_3").css("color", "#fff");
        $("#btn_3").click( function() {
            location.href = location.href;
        });



        $("a#btn_3").after('<a id="btn_4" class="k-button"></a>');
        $("a#btn_4").text("RESET");
        $("#btn_4").click( function() {

            if(!confirm("배열설정 초기화를 진행할까요?")) return false;

            var mainSortable = "";
            $.ajax({
            type: "POST",
                url: "../_mis_addLogic/<?php echo $real_pid; ?>_treat.php",
                data: {
                    mainSortable: JSON.stringify(mainSortable)
                },
                success: function (data) {
                    location.href = location.href;
                },
                error: function(data){
                    alert("RESET 이 실패하였습니다.");
                },
                dataType : "json",
            });
        });

        $("a#btn_1").remove();
        $("li#btn_1_overflow").remove();

        return false;

    });

}


            $(document).ready(function() {

                function onResize(e) {
                    // Prevent the endless recursion from resizes.
                    if (!this.appliesSizes) {
                        if($(document).width()<=1023) location.href = location.href;

                    }
                }


                if($(document).width()>1023) {
                    $("#dashboard").kendoSplitter({
                        //orientation: "vertical",
                        panes: [
                            { collapsible: false, resizable: false, size: "75%" },
                            { collapsible: false, resizable: false, size: "25%" }
                        ],
                        resize: onResize
                    });
                }


                <?php
                
				if($only_gadmin_YN=="Y") $sql = "select mainSortable from MisUser where uniquenum=N'gadmin';";
				else $sql = "select mainSortable from MisUser where uniquenum=N'" . $misSessionUserId . "';";
					
                $mainSortable = onlyOnereturnSql($sql);
                if($mainSortable=="") $mainSortable = "{}";
                ?>
                var mainSortable = <?php echo $mainSortable; ?>

                if(JSON.stringify(mainSortable).length<=2) {
                    var mainSortable = $('div[data-role="listview"]').closest('div.widget');
                    for(i=0;i<mainSortable.length;i++) {
                        $("#sidebar").append($(mainSortable[i]));
                    }
                } else {
                    for(i=0;i<mainSortable.maincontent.length;i++) {
                        $("#main-content").append($("#main-content > div#"+mainSortable.maincontent[i]));
                    }
                    for(i=0;i<mainSortable.sidebar.length;i++) {
                        $("#sidebar").append($("#main-content > div#"+mainSortable.sidebar[i]));
                    }
                }

                
            });

            function placeholder(element) {
                return element.clone().addClass("placeholder");
            }
            function window_resize() {
                if($(document).width()>1023 && $('div.k-splitbar').length==0 && document.getElementById('RealPid').value=='speedmis000988') {
                    location.href = location.href;
                }
            }
            $( window ).resize( function() {
                window_resize();
            });
            
        </script>

        <style>
            #example {
                -webkit-user-select: none;
                -moz-user-select: none;
                -ms-user-select: none;
                user-select: none;
                overflow: hidden;
                height: 100%;
            }
            a#btn_modify {
                display: none;
            }
            span.collapse.k-icon {
                padding: 0 7px;
            }

            .panel-wrap {
                display: table;
                margin: 0 0 20px;
                width: 100%;
                height: 100%;
            }

            #main-content, #sidebar {
                display: table-cell;
                margin: 0;
                padding: 0 20px 0px 20px;
                vertical-align: top;
                overflow-x: hidden;
                height: 100%!important;
            }
            #main-content {
                padding-right: 0;
            }
            #sidebar {
                width: calc(25% + 23px)!important;
                
            }
            .widget.placeholder {
                opacity: 0.4;
                border: 1px dashed #a6a6a6;
            }

            /* WIDGETS */
            .widget {
                height: 235px;
                margin: 10px 0 10px 0;
                padding: 0;
            }
            #sidebar .widget {
                height: 245px;
                margin-bottom: 0;
            }
            #main-content .widget.m22, #main-content .widget.H2 {
                height: 492px;
            }


            .widget:hover {
                background-color: #fcfcfc;
                border-color: #cccccc;
            }


            .m01 .k-grid-content.k-auto-scrollable {
                padding-bottom: 10px;
                overflow-y: hidden;
            }

            .widget > div {
                padding: 10px;
                height: 211px;
                
            }
            .k-grid-header .k-grid-header-wrap:last-child {
                border: 0;
            }
            #main-content .widget.m22 > div, #main-content .widget.H2 > div {
                height: 458px;
                padding: 0;
            }


            .widget h3 {
                font-size: 12px;
                padding: 10px;
                text-transform: uppercase;
                border-bottom: 1px solid #e7e7e7;
                margin-bottom: 0;
            }

            .widget h3:hover, .widget > div:hover {
                xbackground: #999;
            }

            .widget h3 span {
                float: right;
            }

            .widget h3 span:hover {
                cursor: pointer;
                border-radius: 20px;
            }

            #main-content iframe {
                width: 100%;
                height: 100%;
            }
            #main-content .widget.m22 iframe, #main-content .widget.H2 iframe {
                width: 100%;
                height: 457px;
            }

            #sidebar iframe {
                width: 100%;
                height: 100%;
            }


            tr[data-uid] td {
                white-space: nowrap;
                text-overflow: ellipsis;
                overflow: hidden;
                word-wrap: normal;
            }
            .k-grid.k-widget.k-grid-display-block {
                padding: 0;
            }

            .k-widget {
                border: 0;
            }
            #main-content .widget {
                width: calc(50% - 10px);
                xxxmin-width: 450px;
                float: left;
                display: inline-block;
                margin-right: 10px;
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

            @media only screen and (max-width: 1023px) {

                .panel-wrap {
                    display: block;
                    overflow-y: auto;
                    width: 100%!important;
                    padding-right: 0!important;
                }
                #main-content .widget, #sidebar .widget {
                    width: calc(100% - 0px);
                }

                #main-content, #sidebar {
                    width: 100%!important;
                    display: block;
                    padding: 0 10px;
                    height: max-content!important;
                    overflow: hidden;
                }

                body[isMobile="N"] div#dashboard > div {
                    margin-top: 10px;
                }
            }


        </style>

    

        

<script>
    

    function fun_btn_reload() {
        viewModel_bind();
        $('div.widget iframe').each( function() {
            this.src = this.src;
        });
    }
    function makeUrl(p_url) {
        if(Left(p_url.toLowerCase(),4)=="http") {
            var url = p_url;
        } else if(InStr(p_url.toLowerCase(), "javascript:")>0) {
            var url = p_url;
        } else {
            var url = "http://" + p_url;
        }
        setTimeout( function() {
            $('.sitelist .url').each(function() {

                if(InStr(this.innerText,"<")>0) {
                    this.innerHTML = this.innerText;
                    if(InStr($(this).find("a").attr("href"), "javascript:")>0) {
                        $(this).find("a").attr("target", "_self");
                        $(this).find("a").text("열기");
                    }
                }
            });
        },1000);

        return "<a href='" + url + "' class='k-button k-flat k-primary' target='_blank'>" + url + "</a>";
    }

    function viewModel_bind() {

        
        var viewModel = kendo.observable({
            isVisible: true,
            gridClick: function(e) {
                if(e.sender.columns[0].width0==-1) keyIndex = 1; else keyIndex = 0;
                var idx = e.sender.element.find('tr[aria-selected="true"] td')[keyIndex].innerText;
                var pid = e.sender.element.closest('div.widget')[0].id.split('dash_')[1];
                url = "index.php?RealPid="+pid+"&idx="+idx;
                var title = e.sender.columns[keyIndex].title + ":"+idx+ " 상세 ";
                parent_popup_jquery(url,title);
            },

        <?php
        
            $mm = 0;
            $cnt_result = count($result);
            while ($mm<$cnt_result) {
                //print_r($mm . ":" . $result[$mm]["menuname"]);
                $dash_gubun = $result[$mm]["gubun"];
                $dash_RealPid = $result[$mm]["RealPid"];
                $dash_misJoinPid = $result[$mm]["MisJoinPid"];
                $dash_menuname = $result[$mm]["menuname"];
                $dash_MenuType = $result[$mm]["MenuType"];

                $dash_AddURL = $result[$mm]["AddURL"];
                $dash_AddURL = replace($dash_AddURL, '&orderby=', '&$orderby=');
                $dash_AddURL = replace(replace($dash_AddURL, "@MisSession_UserID", $misSessionUserId), "{MisSession_UserID}", $misSessionUserId);
                if(InStr($dash_AddURL,"@SQL:")>0) {
                    $temp1 = splitVB($dash_AddURL,"@SQL:");
                    $temp_sql = $temp1[1];
                    $dash_AddURL = $temp1[0] . onlyOnereturnSql($temp_sql);
                }

                $dash_W2 = $result[$mm]["W2"];
                if($dash_W2==1) $dash_W2 = "W2"; else $dash_W2 = "";
                $dash_H2 = $result[$mm]["H2"];
                if($dash_H2==1) $dash_H2 = "H2"; else $dash_H2 = "";

                $dash_isNotRecently = $result[$mm]["isNotRecently"];
                $dash_no_link = $result[$mm]["no_link"];
                $dash_use_template = $result[$mm]["use_template"];

                if(Left($dash_MenuType,1)=="0") {

                    $sql = "select table_m.idx
                    ,table_m.SortElement
                    ,table_m.aliasName
                    ,table_m.Grid_CtlName
                    ,table_m.Grid_Schema_Type
                    ,table_m.Grid_Orderby
                    from MisMenuList_Detail table_m   
                    where table_m.useflag='1' and table_m.aliasName<>'' and table_m.RealPid = '" . iif($dash_MenuType=="06", $dash_misJoinPid, $dash_RealPid) . "'
                    and (table_m.SortElement<=8 or (table_m.Grid_Columns_Width<-1 or table_m.Grid_Columns_Width>0) or isnumeric(left(table_m.Grid_Orderby,1))=1)
                    order by case when table_m.SortElement<=2 then 0 else 1 end, table_m.SortElement";
                    
                    $result2 = allreturnSql($sql);

                    $mm2 = 0;
                    $fields = "";
                    $fields_all = "";
                    $orderby = "{1},{2},{3}";

                    $cnt_result2 = count($result2);
                    while ($mm2<$cnt_result2) {

                        $data_idx = $result2[$mm2]["idx"];
                        $data_SortElement = $result2[$mm2]["SortElement"];
                        $data_aliasName = $result2[$mm2]["aliasName"];
                        $data_Grid_CtlName = $result2[$mm2]["Grid_CtlName"];
                        $data_Grid_Schema_Type = $result2[$mm2]["Grid_Schema_Type"];
                        $Grid_Orderby = $result2[$mm2]["Grid_Orderby"];
                        
                        if($Grid_Orderby=="1a") $orderby = replace($orderby, "{1}", $data_aliasName);
                        else if($Grid_Orderby=="1d") $orderby = replace($orderby, "{1}", $data_aliasName . " desc");
                        else if($Grid_Orderby=="2a") $orderby = replace($orderby, "{2}", $data_aliasName);
                        else if($Grid_Orderby=="2d") $orderby = replace($orderby, "{2}", $data_aliasName . " desc");
                        else if($Grid_Orderby=="3a") $orderby = replace($orderby, "{3}", $data_aliasName);
                        else if($Grid_Orderby=="3d") $orderby = replace($orderby, "{3}", $data_aliasName . " desc");


                        if($data_SortElement==1 || $data_Grid_Schema_Type=="number") {
                            $type = "number";
                        } else {
                            $type = "string";
                        }
                        $fields = $data_aliasName . ': { type: "' . $type . '" },';
                        
                        if($data_SortElement==1) $fields = replace($fields, '" },', '", format: "{0}" },');
        
                        $fields_all = $fields_all . $fields;
                        ++$mm2;
                    }
                    $orderby = replace($orderby, "{1}", "");
                    $orderby = replace($orderby, ",{2}", "");
                    $orderby = replace($orderby, ",{3}", "");

    ?>

                data_<?php echo $dash_RealPid;?>: new kendo.data.DataSource({
                schema: {
                    model: {
                        fields: {
                            <?php echo $fields_all;?>
                        }
                    },
                    data: "d.results",
                },
                transport: {
                    read: {
                        type: "POST",
                        data: {
                            recently: "<?php echo iif($dash_isNotRecently=="Y","N","Y");?>",
                            $orderby: "<?php echo $orderby;?>",
                            $top: <?php echo iif($dash_H2=="H2","13","5");?>
                        },
                        url: 'list_json.php?flag=read&RealPid=<?php echo $dash_RealPid;?>&MisJoinPid=<?php echo $dash_misJoinPid;?><?php echo $dash_AddURL;?>',
                        dataType: "jsonp",
                    }
                },
            }),
        <?php
                }

                ++$mm;

            }
        ?> 


        
        pid02: new kendo.data.DataSource({
            schema: {
                model: {
                    fields: {
                        idx: { type: "number" },
                        gname: { type: "string" }
                    }
                },
                data: "d.results",
                
            },
            transport: {
                read: {
                    type: "POST",
                    data: {
                        recently: "N",
                        $orderby: "Kcode asc",
                    },
                    url: "list_json.php?flag=read&RealPid=speedmis000633&parent_gubun=632&parent_idx=rbk001067",
                    dataType: "jsonp",
                }
            },
        }),






        });

        kendo.bind($("#example"), viewModel);

    }

    viewModel_bind();

    setTimeout( function() {
        $('.k-icon.k-i-hyperlink-open, .k-icon.k-i-custom').click( function() {
            var url = "index.php?RealPid=" + $(this).attr("pid");
            if($('div[data-template="template_'+$(this).attr('pid')+'"]').data('kendoListView')) {
                json_url = $('div[data-template="template_'+$(this).attr('pid')+'"]').data('kendoListView').dataSource.options.transport.read.url;
                if(InStr(json_url, '&RealPid=speedmis000633')>0) url = url + json_url.split('&RealPid=speedmis000633')[1];
            }
            if(getID("isMenuIn").value=="Y") {
                url = url + "&isMenuIn=Y";
            }
            if(event.ctrlKey) {
                window.open(url);
            } else {
                go_mis_gubun($(this).attr("gubun"));
            }
        });

    },0);


    $('.panel-wrap').css('width', 'calc(100% - 110px)');
    $('.panel-wrap').css('padding-right', '110px');

    //22 로 인식시켜야, 스타일이 적용됨.
    getID("MenuType").value = "22";


    //자동업데이트 체크 및 진행.
    speedmis_update();

</script>

