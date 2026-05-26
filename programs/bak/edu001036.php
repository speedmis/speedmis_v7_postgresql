<?php 

function pageLoad() {

    global $actionFlag,$real_pid,$parent_gubun, $parent_idx,$ServerVariables_QUERY_STRING,$isMenuIn,$idx;


	
	if($actionFlag=="view") {


?>



<script>
function thisPage_options_pdf() {
    return {
        paperSize: "A4",
		scale: 0.75,
		landscape: false,
		margin: { left: "0.38cm", top: "1.0cm", right: "0.2cm", bottom: "0.4cm" }
    }
}
	
function viewLogic_afterLoad_viewPrint() {

		
	$('div.viewPrint').append('<img style="position: absolute;bottom: 32px;left: 35px;width: 200px;" src="/_mis/img/speedmis_wide.png">');
	
}


</script><?php
	}
}
//end pageLoad


?>