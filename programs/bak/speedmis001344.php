<?php

function MisMenuList0_change() {

    global $data, $allFilter, $actionFlag, $real_pid, $list_numbering;

	//아래 주석을 풀면 분석에 도움이 됩니다.
	//print_r($data[0]); 

	//$list_numbering = 'Y';    //모든프로그램에 대해 리스트에서 번호가 보이게하려면 _mis_uniqueInfo/top_addLogic.php 파일에서 진행하세요.
								//해당프로그램에서 번호를 보이게 하려면 'Y' 숨기려면 'N' 을 넣으세요.

	//목록에서 특정프로그램에 대해 AddURL 를 변경합니다.
    if($real_pid=='speedmis001343') {
		$data[0]["BodyType"] = "only_one_list";
		$data[0]["psize"] = "5";
	}

}
//end MisMenuList0_change

?>