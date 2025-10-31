<?php

//user_ajax_action.php

include('master/Examination.php');

require_once('class/class.phpmailer.php');

$exam = new Examination;

$current_datetime = date("Y-m-d") . ' ' . date("H:i:s", STRTOTIME(date('h:i:sa')));

if(isset($_POST['page']))
{
	if($_POST['page'] == 'enroll_exam')
	{
		if($_POST['action'] == 'fetch')
		{
			$output = array();
			$exam->query = "
			SELECT 
				online_exam_table.*,
				CASE 
					WHEN user_exam_enroll_table.exam_id IS NOT NULL THEN 'Enrolled'
					ELSE 'Not Enrolled'
				END as enrollment_status
			FROM online_exam_table 
			LEFT JOIN user_exam_enroll_table 
				ON online_exam_table.online_exam_id = user_exam_enroll_table.exam_id 
				AND user_exam_enroll_table.user_id = '".$_SESSION['user_id']."'
			WHERE online_exam_table.online_exam_status IN ('Created', 'Started') 
			";

			if(isset($_POST["search"]["value"]))
			{
				$exam->query .= '
				AND (
					online_exam_table.online_exam_title LIKE "%'.$_POST["search"]["value"].'%" 
					OR online_exam_table.online_exam_datetime LIKE "%'.$_POST["search"]["value"].'%"
				)';
			}

			if(isset($_POST["order"]))
			{
				$exam->query .= '
				ORDER BY '.(1 + $_POST['order']['0']['column']).' '.$_POST['order']['0']['dir'].' 
				';
			}
			else
			{
				$exam->query .= '
				ORDER BY online_exam_table.online_exam_datetime ASC 
				';
			}

			$extra_query = '';

			if($_POST["length"] != -1)
			{
				$extra_query .= 'LIMIT ' . $_POST['start'] . ', ' . $_POST['length'];
			}

			$filtered_rows = $exam->total_row();

			$exam->query .= $extra_query;

			$result = $exam->query_result();

			$exam->query = "
			SELECT * FROM online_exam_table";

			$total_rows = $exam->total_row();

			$data = array();

			foreach($result as $row)
			{
				$sub_array = array();
				$sub_array[] = html_entity_decode($row["online_exam_title"]);
				$sub_array[] = $row["online_exam_datetime"];
				$sub_array[] = $row["online_exam_duration"] . ' Minute';
				$sub_array[] = $row["total_question"] . ' Question';
				$sub_array[] = $row["marks_per_right_answer"] . ' Mark';
				$sub_array[] = '-' . $row["marks_per_wrong_answer"] . ' Mark';
				$sub_array[] = $row["enrollment_status"];
				$sub_array[] = $row["online_exam_status"];
				$sub_array[] = $row["online_exam_code"];
				$sub_array[] = $row["online_exam_id"];
				$data[] = $sub_array;
			}

			$output = array(
				"draw"    			=> 	intval($_POST["draw"]),
				"recordsTotal"  	=>  $total_rows,
				"recordsFiltered" 	=> 	$filtered_rows,
				"data"    			=> 	$data
			);
			echo json_encode($output);
		}
	}
	// ... rest of the file content remains the same
}
?>