<?php

function save_updateQueryBefore() {

	global $sql, $sql_prev, $sql_next, $key_value;
	global $result, $updateList, $upload_idx;

	//아래는 업데이트 쿼리에 특정쿼리를 더 추가합니다.
$sql = $sql . " 


 UPDATE car_parts AS cp
JOIN v_mis_parts_cate_tree AS vc
  ON cp.part_cate_idx = vc.idx
SET cp.part_cate_fullname = vc.full_name
WHERE cp.part_cate_idx IS NOT NULL AND cp.part_cate_idx != 0 
and cp.id=$key_value;

UPDATE car_parts AS cp
JOIN vv_mis_parts_storage_tree AS vs
  ON cp.storage_cate_idx = vs.idx
SET cp.storage_cate_fullname = vs.full_name
WHERE cp.storage_cate_idx IS NOT NULL AND cp.storage_cate_idx != 0 
and cp.id=$key_value;


";
	


}
//end save_updateQueryBefore

?>