<?php



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
    //if(Len($expiryDate)==10999) {
        //엑셀 분석 시작
        
        $f = $base_root . "uploadFiles/spmoters_금융내역/파일명/$newIdx/$pailmyeong";
        //echo $f;
        if (!file_exists($f)) {
            exit("파일 없음");
        }

        $objPHPExcel = PHPExcel_IOFactory::load($f);

        $bankName = '';
        $bankRange = '';

        if($objPHPExcel->getActiveSheet()->getCell("A3")=="계좌번호") {
            //신한
            $계좌번호 = $objPHPExcel->getActiveSheet()->getCell("B3");
            $bankName = '신한'; $bankRange = 'A8:H10000';
            if(InStr($계좌번호,"-")==0) {
                execSql("delete spmoters_금융내역 where idx=$newIdx");
                exit("올바른 신한은행 양식이 아닙니다 : B3 계좌번호란에 계좌번호가 없음");
            }
        } else if($objPHPExcel->getActiveSheet()->getCell("B4")=="계좌번호") {
            //농협
            $계좌번호 = $objPHPExcel->getActiveSheet()->getCell("D4");
            $bankName = '농협'; $bankRange = 'C9:J10000';
            if(InStr($계좌번호,"-")==0) {
                execSql("delete spmoters_금융내역 where idx=$newIdx");
                exit("올바른 농협은행 양식이 아닙니다 : D4 계좌번호란에 계좌번호가 없음");
            }
        } else if(InStr($objPHPExcel->getActiveSheet()->getCell("A1"),"계좌번호 :")>0) {
            //국민
            $계좌번호 = replace($objPHPExcel->getActiveSheet()->getCell("A1"),"계좌번호 : ","");
            $bankRange = 'B8:J10000';
            if(InStr($계좌번호,"-")==0) {
                execSql("delete spmoters_금융내역 where idx=$newIdx");
                exit("올바른 국민은행 양식이 아닙니다 : A1 계좌번호란에 계좌번호가 없음");
            }
			if($objPHPExcel->getActiveSheet()->getCell("C7")=='적요') $bankName = '국민';
			else $bankName = '국민NEW';
        } else if(InStr($objPHPExcel->getActiveSheet()->getCell("A4"),"계좌번호 :")>0) {
            //하나
            $계좌번호 = replace($objPHPExcel->getActiveSheet()->getCell("A4"),"계좌번호 : ","");
            $bankName = '하나'; $bankRange = 'A8:I10000';
            if(InStr($계좌번호,"-")==0) {
                execSql("delete spmoters_금융내역 where idx=$newIdx");
                exit("올바른 하나은행 양식이 아닙니다 : A4 계좌번호란에 계좌번호가 없음");
            }
        } else if(InStr($objPHPExcel->getActiveSheet()->getCell("H6"),"취급점")>0) {
            //광주
            $계좌번호 = Trim(splitVB(splitVB($objPHPExcel->getActiveSheet()->getCell("A3"),"계좌번호 :")[1],"/")[0]);
            $bankName = '광주'; $bankRange = 'A7:I10000';
            if(InStr($계좌번호,"-")==0) {
                execSql("delete spmoters_금융내역 where idx=$newIdx");
                exit("올바른 광주은행 양식이 아닙니다 : A4 계좌번호란에 계좌번호가 없음");
            }
        } else {
            //알수 없는 양식
            exit("알 수 없는 양식입니다. 관리자에게 문의하세요.");
        }

		$담당자ID = '';
        $allData = $objPHPExcel->getActiveSheet()->rangeToArray($bankRange);

        $cnt = count($allData);
        $sql = " 
        delete spmoters_금융내역_detail where midx in (select idx from spmoters_금융내역 where useflag=0) or useflag=0
        delete spmoters_금융내역_detail where midx not in (select idx from spmoters_금융내역)
        ";

        $real_cnt = 0;
//출금은 출금원금이 원금이며, 입금은 입금이 원금임.
        for($i=0;$i<$cnt;$i++) {
            
            if($allData[$i][0]=="") {
                $real_cnt = $i;
                $i = 999999;
            } else {

                if($bankName=='신한') {
                    $sql = $sql . " 
                    if not exists(
                    select * from spmoters_금융내역_detail 
                    where 거래일자=N'" . $allData[$i][0] . "'
                    and 거래시간=N'" . $allData[$i][1] . "'
                    and 적요=N'" . $allData[$i][2] . "'
                    and 출금원금=N'" . replace($allData[$i][3],",","") . "'
                    and 입금=N'" . replace($allData[$i][4],",","") . "'
                    and 내용=N'" . $allData[$i][5] . "'
                    and 잔액=N'" . replace($allData[$i][6],",","") . "'
                    and 거래점=N'" . $allData[$i][7] . "'
                    )
                    insert into spmoters_금융내역_detail(midx,refidx,파일명,담당자ID,거래일자,거래시간,적요,출금원금,출금,입금,입금배분,내용,잔액,거래점,wdater) values ";
                    $sql = $sql . "(N'" . $newIdx . "'";          //midx
                    $sql = $sql . ",IDENT_CURRENT('spmoters_금융내역_detail')";          //refidx
                    $sql = $sql . ",N'" . $pailmyeong . "'";    //파일명
                    $sql = $sql . ",N'" . $담당자ID . "'";    //담당자ID
                    $sql = $sql . ",N'" . $allData[$i][0] . "'";    //거래일자
                    $sql = $sql . ",N'" . $allData[$i][1] . "'";    //거래시간
                    $sql = $sql . ",N'" . $allData[$i][2] . "'";    //적요
                    $sql = $sql . ",N'" . replace($allData[$i][3],",","") . "'";    //출금원금
                    $sql = $sql . ",N'" . replace($allData[$i][3],",","") . "'";    //출금
                    $sql = $sql . ",N'" . replace($allData[$i][4],",","") . "'";    //입금
                    $sql = $sql . ",N'" . replace($allData[$i][4],",","") . "'";    //입금배분
                    $sql = $sql . ",N'" . $allData[$i][5] . "'";    //내용
                    $sql = $sql . ",N'" . replace($allData[$i][6],",","") . "'";    //잔액
                    $sql = $sql . ",N'" . $allData[$i][7] . "'";    //거래점
                    $sql = $sql . ",N'" . $misSessionUserId . "');
                    ";    //wdater
                } else if($bankName=='농협') {
                    $sql = $sql . " 
                    if not exists(
                    select * from spmoters_금융내역_detail 
                    where 거래일자=N'" . Left($allData[$i][0],10) . "'
                    and 거래시간=N'" . Right($allData[$i][0],8) . "'
                    and 적요=N'" . $allData[$i][4] . "'
                    and 출금원금=N'" . replace(replace($allData[$i][1],",",""),"원","") . "'
                    and 입금=N'" . replace(replace($allData[$i][2],",",""),"원","") . "'
                    and 내용=N'" . $allData[$i][5] . "'
                    and 잔액=N'" . replace(replace($allData[$i][3],",",""),"원","") . "'
                    and 거래점=N'" . $allData[$i][6] . "'
                    )
                    insert into spmoters_금융내역_detail(midx,refidx,파일명,담당자ID,거래일자,거래시간,적요,출금원금,출금,입금,입금배분,내용,잔액,거래점,거래특이사항,wdater) values ";
                    $sql = $sql . "(N'" . $newIdx . "'";          //midx
                    $sql = $sql . ",IDENT_CURRENT('spmoters_금융내역_detail')";          //refidx
                    $sql = $sql . ",N'" . $pailmyeong . "'";    //파일명
                    $sql = $sql . ",N'" . $담당자ID . "'";    //담당자ID
                    $sql = $sql . ",N'" . replace(Left($allData[$i][0],10),"/","-") . "'";    //거래일자
                    $sql = $sql . ",N'" . Right($allData[$i][0],8) . "'";    //거래시간
                    $sql = $sql . ",N'" . $allData[$i][4] . "'";    //적요
                    $sql = $sql . ",N'" . replace(replace($allData[$i][1],",",""),"원","") . "'";    //출금원금
                    $sql = $sql . ",N'" . replace(replace($allData[$i][1],",",""),"원","") . "'";    //출금
                    $sql = $sql . ",N'" . replace(replace($allData[$i][2],",",""),"원","") . "'";    //입금
                    $sql = $sql . ",N'" . replace(replace($allData[$i][2],",",""),"원","") . "'";    //입금배분
                    $sql = $sql . ",N'" . $allData[$i][5] . "'";    //내용
                    $sql = $sql . ",N'" . replace(replace($allData[$i][3],",",""),"원","") . "'";    //잔액
                    $sql = $sql . ",N'" . $allData[$i][6] . "'";    //거래점
                    $sql = $sql . ",N'" . $allData[$i][7] . "'";    //거래특이사항
                    $sql = $sql . ",N'" . $misSessionUserId . "');
                    ";    //wdater
                } else if($bankName=='국민') {
                    $sql = $sql . " 
                    if not exists(
                    select * from spmoters_금융내역_detail 
                    where 거래일자=N'" . replace(Left($allData[$i][0],10),".","-") . "'
                    and 거래시간=N'" . Right($allData[$i][0],8) . "'
                    and 적요=N'" . $allData[$i][1] . "'
                    and 출금원금=N'" . replace($allData[$i][3],",","") . "'
                    and 입금=N'" . replace($allData[$i][4],",","") . "'
                    and 내용=N'" . $allData[$i][6] . "'
                    and 잔액=N'" . replace($allData[$i][5],",","") . "'
                    and 거래점=N'" . $allData[$i][7] . "'
                    )
                    insert into spmoters_금융내역_detail(midx,refidx,파일명,담당자ID,거래일자,거래시간,적요,출금원금,출금,입금,입금배분,내용,잔액,거래점,의뢰인수취인,거래특이사항,wdater) values ";
                    $sql = $sql . "(N'" . $newIdx . "'";          //midx
                    $sql = $sql . ",IDENT_CURRENT('spmoters_금융내역_detail')";          //refidx
                    $sql = $sql . ",N'" . $pailmyeong . "'";    //파일명
                    $sql = $sql . ",N'" . $담당자ID . "'";    //담당자ID
                    $sql = $sql . ",N'" . replace(Left($allData[$i][0],10),".","-") . "'";    //거래일자
                    $sql = $sql . ",N'" . Right($allData[$i][0],8) . "'";    //거래시간
                    $sql = $sql . ",N'" . $allData[$i][1] . "'";    //적요
                    $sql = $sql . ",N'" . replace($allData[$i][3],",","") . "'";    //출금원금
                    $sql = $sql . ",N'" . replace($allData[$i][3],",","") . "'";    //출금
                    $sql = $sql . ",N'" . replace($allData[$i][4],",","") . "'";    //입금
                    $sql = $sql . ",N'" . replace($allData[$i][4],",","") . "'";    //입금배분
                    $sql = $sql . ",N'" . $allData[$i][6] . "'";    //내용
                    $sql = $sql . ",N'" . replace($allData[$i][5],",","") . "'";    //잔액
                    $sql = $sql . ",N'" . $allData[$i][7] . "'";    //거래점
                    $sql = $sql . ",N'" . $allData[$i][2] . "'";    //의뢰인수취인
                    $sql = $sql . ",N'" . $allData[$i][8] . "'";    //거래특이사항
                    $sql = $sql . ",N'" . $misSessionUserId . "');
                    ";    //wdater
                } else if($bankName=='국민NEW') {



                    $sql = $sql . " 
                    if not exists(
                    select * from spmoters_금융내역_detail 
                    where 거래일자=N'" . replace(Left($allData[$i][0],10),".","-") . "'
                    and 거래시간=N'" . Right($allData[$i][0],8) . "'
                    and 적요=N'" . $allData[$i][6] . "'
                    and 출금원금=N'" . replace($allData[$i][2],",","") . "'
                    and 입금=N'" . replace($allData[$i][3],",","") . "'
                    and 내용=N'" . $allData[$i][5] . "'
                    and 잔액=N'" . replace($allData[$i][4],",","") . "'
                    and 거래점=N'" . $allData[$i][7] . "'
                    )
                    insert into spmoters_금융내역_detail(midx,refidx,파일명,담당자ID,거래일자,거래시간,적요,출금원금,출금,입금,입금배분,내용,잔액,거래점,의뢰인수취인,거래특이사항,wdater) values ";
                    $sql = $sql . "(N'" . $newIdx . "'";          //midx
                    $sql = $sql . ",IDENT_CURRENT('spmoters_금융내역_detail')";          //refidx
                    $sql = $sql . ",N'" . $pailmyeong . "'";    //파일명
                    $sql = $sql . ",N'" . $담당자ID . "'";    //담당자ID
                    $sql = $sql . ",N'" . replace(Left($allData[$i][0],10),".","-") . "'";    //거래일자
                    $sql = $sql . ",N'" . Right($allData[$i][0],8) . "'";    //거래시간
                    $sql = $sql . ",N'" . $allData[$i][6] . "'";    //적요
                    $sql = $sql . ",N'" . replace($allData[$i][2],",","") . "'";    //출금원금
                    $sql = $sql . ",N'" . replace($allData[$i][2],",","") . "'";    //출금
                    $sql = $sql . ",N'" . replace($allData[$i][3],",","") . "'";    //입금
                    $sql = $sql . ",N'" . replace($allData[$i][3],",","") . "'";    //입금배분
                    $sql = $sql . ",N'" . $allData[$i][5] . "'";    //내용
                    $sql = $sql . ",N'" . replace($allData[$i][4],",","") . "'";    //잔액
                    $sql = $sql . ",N'" . $allData[$i][7] . "'";    //거래점
                    $sql = $sql . ",N'" . $allData[$i][1] . "'";    //의뢰인수취인
                    $sql = $sql . ",N'" . $allData[$i][8] . "'";    //거래특이사항
                    $sql = $sql . ",N'" . $misSessionUserId . "');
                    ";    //wdater
                } else if($bankName=='하나') {
                    $sql = $sql . " 
                    if not exists(
                    select * from spmoters_금융내역_detail 
                    where 거래일자=N'" . Left($allData[$i][0],10) . "'
                    and 거래시간=N'" . Right($allData[$i][0],8) . "'
                    and 의뢰인수취인=N'" . $allData[$i][2] . "'
                    and 출금원금=N'" . replace(replace($allData[$i][4],",",""),"원","") . "'
                    and 입금=N'" . replace(replace($allData[$i][3],",",""),"원","") . "'
                    and 내용=N'" . $allData[$i][1] . "'
                    and 잔액=N'" . replace(replace($allData[$i][5],",",""),"원","") . "'
                    and 거래점=N'" . $allData[$i][7] . "'
                    )
                    insert into spmoters_금융내역_detail(midx,refidx,파일명,담당자ID,거래일자,거래시간,의뢰인수취인,출금원금,출금,입금,입금배분,내용,잔액,거래점,거래특이사항,wdater) values ";
                    $sql = $sql . "(N'" . $newIdx . "'";          //midx
                    $sql = $sql . ",IDENT_CURRENT('spmoters_금융내역_detail')";          //refidx
                    $sql = $sql . ",N'" . $pailmyeong . "'";    //파일명
                    $sql = $sql . ",N'" . $담당자ID . "'";    //담당자ID
                    $sql = $sql . ",N'" . replace(Left($allData[$i][0],10),"/","-") . "'";    //거래일자
                    $sql = $sql . ",N'" . Right($allData[$i][0],8) . "'";    //거래시간
                    $sql = $sql . ",N'" . $allData[$i][2] . "'";    //의뢰인수취인
                    $sql = $sql . ",N'" . replace(replace($allData[$i][4],",",""),"원","") . "'";    //출금원금
                    $sql = $sql . ",N'" . replace(replace($allData[$i][4],",",""),"원","") . "'";    //출금
                    $sql = $sql . ",N'" . replace(replace($allData[$i][3],",",""),"원","") . "'";    //입금
                    $sql = $sql . ",N'" . replace(replace($allData[$i][3],",",""),"원","") . "'";    //입금배분
                    $sql = $sql . ",N'" . $allData[$i][1] . "'";    //내용
                    $sql = $sql . ",N'" . replace(replace($allData[$i][5],",",""),"원","") . "'";    //잔액
                    $sql = $sql . ",N'" . $allData[$i][7] . "'";    //거래점
                    $sql = $sql . ",N'" . $allData[$i][8] . "'";    //거래특이사항
                    $sql = $sql . ",N'" . $misSessionUserId . "');
                    ";    //wdater
                } else if($bankName=='광주') {

					if(is_date(Left($allData[$i][0],10))==false) {
						$real_cnt = $i;
						$i = 999999;
					} else {

						$sql = $sql . " 
						if not exists(
						select * from spmoters_금융내역_detail 
						where 거래일자=N'" . Left($allData[$i][0],10) . "'
						and 거래시간=N'" . Right($allData[$i][0],8) . "'
						and 적요=N'" . $allData[$i][1] . "'
						and 출금원금=N'" . replace($allData[$i][4],",","") . "'
						and 입금=N'" . replace($allData[$i][5],",","") . "'
						and 내용=N'" . $allData[$i][2] . "'
						and 잔액=N'" . replace($allData[$i][6],",","") . "'
						and 거래점=N'" . $allData[$i][7] . "'
						)
						insert into spmoters_금융내역_detail(midx,refidx,파일명,담당자ID,거래일자,거래시간,적요,출금원금,출금,입금,입금배분,내용,잔액,거래점,거래특이사항,wdater) values ";
						$sql = $sql . "(N'" . $newIdx . "'";          //midx
						$sql = $sql . ",IDENT_CURRENT('spmoters_금융내역_detail')";          //refidx
						$sql = $sql . ",N'" . $pailmyeong . "'";    //파일명
						$sql = $sql . ",N'" . $담당자ID . "'";    //담당자ID
						$sql = $sql . ",N'" . replace(Left($allData[$i][0],10),".","-") . "'";    //거래일자
						$sql = $sql . ",N'" . Right($allData[$i][0],8) . "'";    //거래시간
						$sql = $sql . ",N'" . $allData[$i][1] . "'";    //적요
						$sql = $sql . ",N'" . replace($allData[$i][4],",","") . "'";    //출금원금
						$sql = $sql . ",N'" . replace($allData[$i][4],",","") . "'";    //출금
						$sql = $sql . ",N'" . replace($allData[$i][5],",","") . "'";    //입금
						$sql = $sql . ",N'" . replace($allData[$i][5],",","") . "'";    //입금배분
						$sql = $sql . ",N'" . $allData[$i][2] . "'";    //내용
						$sql = $sql . ",N'" . replace($allData[$i][6],",","") . "'";    //잔액
						$sql = $sql . ",N'" . $allData[$i][7] . "'";    //거래점
						$sql = $sql . ",N'" . $allData[$i][8] . "'";    //거래특이사항<=처리결과
						$sql = $sql . ",N'" . $misSessionUserId . "');
						";    //wdater
					}
				}
                
            }
        }

		$bankName = replace($bankName, 'NEW', '');
        $sql = $sql . " 

  

        update spmoters_금융내역 set 분류='통장거래', 파일명='$pailmyeong', 계좌배분_직원ID목록='$담당자ID', 은행='$bankName', 계좌번호='$계좌번호', 파일총건수='$real_cnt'
        where idx='$newIdx'

        update spmoters_금융내역_detail set 입금세전=round(입금/1.1,0), 입금부가세=입금-round(입금/1.1,0), 입금세전타입='부가세'
        where midx='$newIdx' and isnull(입금,0)>0
		
		update spmoters_금융내역_detail set 분류='통장거래' where midx='$newIdx'

        ";

        execSql($sql);
        
    
}
//end save_writeAfter

?>