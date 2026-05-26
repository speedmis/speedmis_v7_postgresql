<?php
    <style>
    body[parentmenutype="22"][menutype="22"] .k-overflow-anchor.k-button {
        display: none;
    }
    body[parentmenutype="22"][menutype="22"] .k-page {
        width: 100%!important;
    }
a#btn_reload, .k-button-group {
    display: none;
}
    </style>

<div id="div_pdf"></div>
    
<script>
    var url = 'https://www.speedmis.com/SpeedMIS_intro.pdf';
    if(isMobile()) {

        $.when(
                    $.getScript("https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.6.347/pdf.min.js"),
                    $.getScript("https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.6.347/pdf.worker.entry.min.js"),
                    $.getScript("https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.6.347/pdf.worker.min.js")
                )
                .done(function () {
                    window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.6.347/pdf.worker.min.js';           
                }).then(function() {
                    $('div#div_pdf').kendoPDFViewer({
                        pdfjsProcessing: {
                            file: url
                        },
                        render: function(e) {
                            setTimeout( function(p_e) {
                                if($('div#div_pdf').data('kendoPDFViewer')._events.render[0]) {
                                    p_e.sender.toolbar.zoom.combobox.value("actual");
                                    p_e.sender.toolbar.zoom.combobox.trigger("change");
                                    $('div#div_pdf').data('kendoPDFViewer')._events.render[0] = null;
                                }
                            },0,e);
                        },
                        width: "calc(100% - 0px)",
                        height: "calc(100vh - 0px)"
                    });
                });
                
    } else {
    $('div#main')[0].innerHTML = `
        <iframe src="`+url+`" style="width:100%; height: calc(100% - 4px);" frameborder="0" allowfullscreen></iframe>
    `;
    }
</script>
