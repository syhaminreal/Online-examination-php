<?php

//enroll_exam.php

include('master/Examination.php');

$exam = new Examination;

$exam->user_session_private();

$exam->Change_exam_status($_SESSION['user_id']);

include('header.php');

?>

<br />
<div class="card">
	<div class="card-header">Online Exam List</div>
	<div class="card-body">
		<div class="table-responsive">
			<table class="table table-bordered table-striped table-hover" id="exam_data_table">
				<thead>
					<tr>
						<th>Exam Title</th>
						<th>Date & Time</th>
						<th>Duration</th>
						<th>Total Question</th>
						<th>Right Answer Mark</th>
						<th>Wrong Answer Mark</th>
						<th>Status</th>
						<th>Action</th>
					</tr>
				</thead>
			</table>
		</div>
	</div>
</div>
</div>
</body>
</html>

<script>

$(document).ready(function(){

	var dataTable = $('#exam_data_table').DataTable({
		"processing" : true,
		"serverSide" : true,
		"order" : [],
		"ajax" : {
			url:"user_ajax_action.php",
			type:"POST",
			data:{action:'fetch', page:'enroll_exam'}
		},
		"columnDefs":[
			{
				"targets":[7],
				"orderable":false,
				"render": function(data, type, row) {
					var html = '';
					if(row[6] === 'Enrolled') {
						if(row[7] === 'Started') {
							html = '<button type="button" class="btn btn-success btn-sm take_exam" data-exam_code="' + row[8] + '">Take Exam</button>';
						} else {
							html = '<button type="button" class="btn btn-info btn-sm" disabled>Enrolled</button>';
						}
					} else {
						html = '<button type="button" class="btn btn-warning btn-sm enroll_exam" data-exam_id="' + row[9] + '">Enroll</button>';
					}
					return html;
				}
			},
		],
	});

	$(document).on('click', '.enroll_exam', function(){
		var exam_id = $(this).data('exam_id');
		var $btn = $(this);
		$btn.prop('disabled', true).text('Enrolling...');
		$.ajax({
			url: 'user_ajax_action.php',
			type: 'POST',
			data: {action: 'enroll_exam', page: 'index', exam_id: exam_id},
			success: function(){
				dataTable.ajax.reload(null, false);
			},
			error: function(){
				$btn.prop('disabled', false).text('Enroll');
				alert('Failed to enroll. Please try again.');
			}
		});
	});

	$(document).on('click', '.take_exam', function(){
		var exam_code = $(this).data('exam_code');
		window.location.href = 'view_exam.php?code=' + exam_code;
	});

});

</script>