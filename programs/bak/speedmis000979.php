<?php

function misMenuList_change() {
    
	//misMenuList 테이블에 의한 설정값인 $result 를 바꾸는게 이 함수의 핵심기능
    global $actionFlag, $gubun, $parent_idx, $real_pid, $logicPid, $result;
	global $misSessionPositionCode, $flag, $full_siteID, $misSessionUserId;

	if($full_siteID=='speedsup') {
        $result[0]["g09"] = $result[0]["g09"] . " and (table_user.uniqueNum='$misSessionUserId' or table_user.uniqueNum='gadmin' or '$misSessionUserId'='gadmin') ";
	}
}
//end misMenuList_change



function pageLoad() {

    global $actionFlag, $real_pid, $parent_RealPid, $parent_idx;

/*
	if($actionFlag=="list" && $parent_RealPid=="speedmis000028") {
		$target_parent_gubun = RealPidIntoGubun("speedmis001071");
		$url = "index.php?RealPid=$real_pid&parent_gubun=$target_parent_gubun&parent_idx=$parent_idx";
		re_direct($url);
	}
*/
        ?>

<div id="choiceDialog" style="display: none;">
  <div class="dialog-content">
  </div>
</div>
<style>
/* 윈도우 외관 */
.k-window.fancy-shadow {
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
  background: linear-gradient(145deg, #ffffff, #f0f0f0);
}

/* 컨텐츠 박스 */
#choiceDialog .dialog-content {
  padding: 24px;
  font-family: 'Segoe UI', 'Apple SD Gothic Neo', sans-serif;
  text-align: center;
  background: #fafafa;
}

/* 텍스트 */
#choiceDialog .dialog-content p {
  font-size: 20px;
  color: #333;
  margin-bottom: 20px;
}

/* 버튼 정렬 */
#choiceDialog .button-group {
  display: flex;
  justify-content: center;
  gap: 20px;
}

/* 버튼 스타일 */
#choiceDialog .k-button {
  padding: 12px 24px;
  border-radius: 10px;
  font-size: 16px;
  font-weight: bold;
  cursor: pointer;
  transition: all 0.3s ease;
}

#choiceDialog .k-button.k-primary {
  background: linear-gradient(to right, #3a8ef8, #6f7bf7);
  color: white;
  border: none;
}
#choiceDialog .k-button.k-primary:hover {
  background: #295ccc;
}

#choiceDialog .k-button.k-secondary {
  background: #e0e0e0;
  color: #333;
}
#choiceDialog .k-button.k-secondary:hover {
  background: #c5c5c5;
}
</style>

        <script>
$('body').attr('onlylist','');

  function 승인(p_idx) {
	  const dialog = $("#choiceDialog").data("kendoWindow");
    console.log("A 선택");
	url = "addLogic_treat.php?RealPid=<?=$real_pid?>&idx="+p_idx+"&question=confirm&select=Y";
	temp = ajax_url_return(url);	  
   url = parent.status_view_url();
    url = replaceAll(url, '&tabid=speedmis000979', '');
	  parent.location.replace(url);
    //dialog.close();
  }

  function 반려(p_idx) {
	  const dialog = $("#choiceDialog").data("kendoWindow");
    console.log("B 선택");
	  url = "addLogic_treat.php?RealPid=<?=$real_pid?>&idx="+p_idx+"&question=confirm&select=N";
  	temp = ajax_url_return(url);	 
    url = parent.status_view_url();
    url = replaceAll(url, '&tabid=speedmis000979', '');
	  parent.location.replace(url);
    //dialog.close();
  }
			

	$("#choiceDialog").kendoWindow({
	  title: "???? 커스텀 선택창",
	  modal: true,
	  visible: false,
	  resizable: false,
	  draggable: true,
	  width: "420px",
	  height: "200px",
	  animation: {
		open: {
		  effects: "fade:in scale:in",
		  duration: 500
		},
		close: {
		  effects: "fade:out",
		  duration: 300
		}
	  },
	  open: function () {
		$(".k-window").addClass("fancy-shadow");
	  },
	  close: function () {
		//this.destroy(); // 창 닫을 때 제거
	  }
	});
			
function confirms(p_this) {
	p_idx = $(p_this).attr('idx');
	
	const dialog = $("#choiceDialog").data("kendoWindow");
	dialog.title(p_this.innerText + " 진행");
  	dialog.content(`
    <div class="dialog-content">
    <p>무엇을 선택하시겠습니까?</p>
    <div class="button-group">
      <button class="k-button k-primary" onclick="승인(`+p_idx+`);">승인</button>
      <button class="k-button" onclick="반려(`+p_idx+`);">반려</button>
    </div>
  </div>
  `).center().open();
	

}
			
function columns_templete(p_dataItem, p_aliasName) {
    if(p_aliasName=="zseongmyeong") {
        if(p_dataItem["zpusisayongyeobu"]=="Y") {
            return p_dataItem["jagyeok"] + '<img onclick="sendMsgForm(\''+p_dataItem['userid']+'\',\''+p_dataItem['zseongmyeong']+'\');" title="텔렘그램으로 알림을 수신받는 회원" src="img/telegram_mini.jpg"/>';
        } else return p_dataItem[p_aliasName];
    } if(p_aliasName=="jagyeok") {
        if(p_dataItem["readDate"]=='' || p_dataItem["readDate"]==null) {
			if(Left(p_dataItem[p_aliasName],2)=='결재' && p_dataItem['userid']==$('input#MisSession_UserID')[0].value) {
    	        return `<a class="k-button" onclick="confirms(this);" idx="`+p_dataItem['idx']+`">`+p_dataItem[p_aliasName]+'</a>';
			} else {
	            return p_dataItem[p_aliasName];
			}
        } else return p_dataItem[p_aliasName];
    } else {
        return p_dataItem[p_aliasName];
    }	
}

        </script>
        <?php

}
//end pageLoad



function list_query() {
    global $real_pid, $misJoinPid, $logicPid, $parent_idx, $parent_gubun;
    global $flag, $app, $idx, $appSql, $resultCode, $resultMessage, $afterScript;
    global $countQuery, $selectQuery, $idx_aliasName;   //특정필드에 대한 검색이 있는 경우.
    
    $countQuery = replace($countQuery, "and 123=123", "and 123=123 and table_m.RealPid=N'" . GubunIntoRealPid($parent_gubun) . "'");
    $selectQuery = replace($selectQuery, "and 123=123", "and 123=123 and table_m.RealPid=N'" . GubunIntoRealPid($parent_gubun) . "'");
}
//end list_query



function addLogic_treat() {
	
	global $misSessionUserId;
	
    //addLogic_treat 함수는 ajax 로 요청되어진(url 형식) 것에 대한 출력문입니다. echo 등으로 출력내용만 표시하면 됩니다.
	//아래는 url 에 동반된 파라메터의 예입니다.
	//해당 예제 TIP 의 기본폼에 보면 addLogic_treat 를 호출하는 코딩이 있습니다.

    $question = requestVB("question");
    $select = requestVB("select");
    $p_idx = requestVB("idx");
    $p_widx = requestVB("widx");
	if($select=='Y') {
		$select = '승인';
	} else {
		$select = '반려';
	}
	//아래는 값에 따라 mysql 서버를 통해 알맞는 값을 출력하여 보냅니다.
    if($question=="confirm") {
      if($p_idx!='') {
        $sql = "
        update MisReadList set 처리결과='$select', readDate=getdate() where idx='$p_idx' and userid='$misSessionUserId' and 자격 like '결재%';
        ";
      } else if($p_widx!='') {
        $sql = "
        update MisReadList set 처리결과='$select', readDate=getdate() where widx='$p_widx' and userid='$misSessionUserId' and 자격 like '결재%';
        ";  
      }
      execSql($sql);
    }
	echo 'success';

}
//end addLogic_treat







?>