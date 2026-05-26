<?php

function MisMenuListxxx_change() {

	//MisMenuListxxx 테이블에 의한 설정값인 $result 를 바꾸는게 이 함수의 핵심기능
    global $actionFlag, $gubun, $parent_idx, $real_pid, $logicPid, $result;
	global $misSessionPositionCode, $flag;

	//만약 $result 의 값이 궁금하면 아래 주석을 해제하고 새로고침 해볼 것(주의:에러발생).
	//print_r($result);

	//아래는 MenuName 이라는 aliasName 에 대해 표시명을 바꾸는 예제임.
    $search_index = array_search("pailmyeong", array_column($result, 'aliasName'));
    $result[$search_index]["Grid_MaxLength"] = "";

}
//end MisMenuListxxx_change



function pageLoad() {

    global $actionFlag;
	global $misSessionIsAdmin;


	if($actionFlag=="modify") {
        ?>
        <style>
.k-dropzone, button.k-button.k-button-icon.k-flat.k-upload-action {
	display: none;
}
        </style>
        <?php 
	}
}
//end pageLoad



function save_writeAfter() {

    include '../_mis/PHPExcleReader/Classes/PHPExcel/IOFactory.php';

    global $base_root, $real_pid, $misJoinPid, $logicPid, $parent_idx;
    global $MenuName, $key_aliasName, $key_value, $saveList, $saveUploadList, $viewList, $deleteList;
    global $Grid_Default, $actionFlag, $misSessionUserId, $newIdx;

    $json_decode_saveList = json_decode($saveList, true);
    $json_decode_saveUploadList = json_decode($saveUploadList, true);
    $pailmyeong = $json_decode_saveUploadList["pailmyeong"];

    $f = $base_root . "uploadFiles/spmoters_금융내역/파일명/$newIdx/$pailmyeong";
    //echo $f;
    if (!file_exists($f)) {
        exit("파일 없음");
    }

    $objPHPExcel = PHPExcel_IOFactory::load($f);

    $구분 = Left($objPHPExcel->getActiveSheet()->getCell("A2"), 2);
	if($구분!='매입') $구분 = Left($objPHPExcel->getActiveSheet()->getCell("B2"), 2);
	if($구분!='매입' && $구분!='매출') exit('올바른 현금영수증 파일이 아닙니다.');


    $dataRange = '';


    if($구분=="매입") {
        $dataRange = 'A3:N10000';
        $총금액 = $objPHPExcel->getActiveSheet()->getCell("A1");
		$총금액 = replace(replace(splitVB(splitVB($총금액,':')[1],'(')[0],',',''),' ','')*1;
    } else if($구분=="매출") {
        $dataRange = 'A3:J10000';
        $총금액 = $objPHPExcel->getActiveSheet()->getCell("A1");
		$총금액 = replace(replace(splitVB(splitVB($총금액,':')[1],'(')[0],',',''),' ','')*1;
    } else {
        //알수 없는 양식
        exit("알 수 없는 양식입니다. 관리자에게 문의하세요.");
    }


    $allData = $objPHPExcel->getActiveSheet()->rangeToArray($dataRange);

    $cnt = count($allData);
    $sql = " 
    delete spmoters_금융내역_detail where midx in (select idx from spmoters_금융내역 where useflag=0) or useflag=0
    delete spmoters_금융내역_detail where midx not in (select idx from spmoters_금융내역)
    ";

    $real_cnt = 0;

    for($i=0;$i<$cnt;$i++) {
        
        if($allData[$i][0]=="") {
            $real_cnt = $i;
            $i = 999999;
        } else {

            if($구분=="매입") {
 /*
0 매입일시	
1 사용자명
2 가맹점사업자번호	
3 가맹점명
4 업종코드		
5 업종명	
6 공급가액 
7 부가세	
8 봉사료	
9 매입금액	
10 승인번호	
11 발급수단	
12 거래구분	
13 공제여부
*/

                $sql = $sql . " 
                if not exists(
                select * from spmoters_금융내역_detail where 분류='현금영수증' and 구분='$구분' and 승인번호='" . replace($allData[$i][10],"'","''") . "' and 발급수단='" . replace($allData[$i][11],"'","''") . "'
                )
                insert into spmoters_금융내역_detail(midx,구분,거래일자,거래시간,사용자명,가맹점사업자번호,가맹점명,업종코드,업종명,공급가액,부가세,봉사료,매입금액,승인번호,발급수단,거래구분,공제여부,wdater) values ";
                $sql = $sql . "(N'" . $newIdx . "'";          //midx
                $sql = $sql . ",N'" . $구분 . "'";
                $sql = $sql . ",N'" . Left($allData[$i][0],10) . "'";    //거래일자
                $sql = $sql . ",N'" . Right($allData[$i][0],8) . "'";    //거래시간
                $sql = $sql . ",N'" . replace($allData[$i][1],"'","''") . "'";    //사용자명
                $sql = $sql . ",N'" . replace($allData[$i][2],"'","''") . "'";    //가맹점사업자번호
                $sql = $sql . ",N'" . replace($allData[$i][3],",","") . "'";    //가맹점명
                $sql = $sql . ",N'" . replace($allData[$i][4],",","") . "'";    //업종코드
                $sql = $sql . ",N'" . replace($allData[$i][5],",","") . "'";    //업종명
                $sql = $sql . ",N'" . replace(replace($allData[$i][6],",",""),"'","''") . "'";    //공급가액
                $sql = $sql . ",N'" . replace(replace($allData[$i][7],",",""),"'","''") . "'";    //부가세
                $sql = $sql . ",N'" . replace(replace($allData[$i][8],",",""),"'","''") . "'";    //봉사료
                $sql = $sql . ",N'" . replace(replace($allData[$i][9],",",""),"'","''") . "'";    //매입금액
                $sql = $sql . ",N'" . replace($allData[$i][10],"'","''") . "'";    //승인번호
                $sql = $sql . ",N'" . replace($allData[$i][11],"'","''") . "'";    //발급수단
                $sql = $sql . ",N'" . replace($allData[$i][12],"'","''") . "'";    //거래구분
                $sql = $sql . ",N'" . replace($allData[$i][13],"'","''") . "'";    //공제여부
                $sql = $sql . ",N'" . $misSessionUserId . "');
                ";    //wdater
            } else if($구분=='매출') {
/*
발행구분	0
매출일시	1
공급가액	2
부가세	3
봉사료	4
총금액	5
승인번호	6
발급수단	7
거래구분	8
비고 9

*/
                $sql = $sql . " 
                if not exists(
                select * from spmoters_금융내역_detail where 분류='현금영수증' and 구분='$구분' and 승인번호='" . replace($allData[$i][6],"'","''") . "' and 발급수단='" . replace($allData[$i][7],"'","''") . "'
                )
                insert into spmoters_금융내역_detail(midx,구분,거래일자,거래시간,발행구분,공급가액,부가세,봉사료,총금액,승인번호,발급수단,거래구분,비고,wdater) values ";
                $sql = $sql . "(N'" . $newIdx . "'";          //midx
                $sql = $sql . ",N'" . $구분 . "'";
                $sql = $sql . ",N'" . Left($allData[$i][1],10) . "'";    //거래일자
                $sql = $sql . ",N'" . Right($allData[$i][1],8) . "'";    //거래시간
                $sql = $sql . ",N'" . replace($allData[$i][0],"'","''") . "'";    //발행구분
                $sql = $sql . ",N'" . replace(replace($allData[$i][2],",",""),"'","''") . "'";    //공급가액
                $sql = $sql . ",N'" . replace(replace($allData[$i][3],",",""),",","") . "'";    //부가세
                $sql = $sql . ",N'" . replace(replace($allData[$i][4],",",""),",","") . "'";    //봉사료
                $sql = $sql . ",N'" . replace(replace($allData[$i][5],",",""),",","") . "'";    //총금액
                $sql = $sql . ",N'" . replace($allData[$i][6],"'","''") . "'";    //승인번호
                $sql = $sql . ",N'" . replace($allData[$i][7],"'","''") . "'";    //발급수단
                $sql = $sql . ",N'" . replace($allData[$i][8],"'","''") . "'";    //거래구분
                $sql = $sql . ",N'" . replace($allData[$i][9],"'","''") . "'";    //비고
                $sql = $sql . ",N'" . $misSessionUserId . "');
                ";    //wdater

            }
            
        }
    }
    $sql = $sql . " 

    update spmoters_금융내역 set 총금액=$총금액, 분류='현금영수증', 파일명='$pailmyeong', 파일총건수=N'$real_cnt', 구분=N'$구분' where idx='$newIdx'
    update spmoters_금융내역_detail set 분류='현금영수증', 파일명=N'$pailmyeong' where midx='$newIdx'

    ";////exec sdmoters_세금계산서메일감지_Proc $newIdx, ''

	execSql($sql);
        
    
}
//end save_writeAfter

?>