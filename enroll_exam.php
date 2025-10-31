<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
			data: function(d){
				// forward DataTables params and include page/action plus any force_take query
				var params = $.extend({}, d, { action: 'fetch', page: 'enroll_exam' });
				// include client's timezone info so server can compute per-user local-day activity
				try {
					params.tz_name = Intl.DateTimeFormat().resolvedOptions().timeZone;
				} catch (e) {
					params.tz_name = '';
				}
				params.tz_offset = new Date().getTimezoneOffset();
				// read force_take from page URL
				var urlParams = new URLSearchParams(window.location.search);
				if(urlParams.get('force_take')){
					params.force_take = urlParams.get('force_take');
				}
				return params;
			}
		},
		"columnDefs":[
			{
				"targets":[7],
				"orderable":false,
				"render": function(data, type, row) {
					// Columns returned by server:
					// 0:title,1:datetime,2:duration,3:total_question,4:right_mark,5:wrong_mark,
					// 6:enrollment_status,7:online_exam_status,8:exam_code,9:exam_id,10:is_active,11:can_take
					var html = '';
					var enrollment = row[6];
					var exam_status = row[7];
					var exam_code = row[8];
					var exam_id = row[9];
					var is_active = (row[10] == '1');
					var can_take = (row[11] == '1');

								// Show a visible 'Take Exam' button for enrolled users so they can navigate to the exam page.
								if(enrollment === 'Enrolled') {
									// include extra data attributes for the modal (title, datetime, duration, exam_id)
									html = '<button type="button" class="btn btn-success btn-sm take_exam" '
									    + 'data-exam_code="' + exam_code + '" '
									    + 'data-exam_title="' + $('<div/>').text(row[0]).html() + '" '
									    + 'data-exam_datetime="' + $('<div/>').text(row[1]).html() + '" '
									    + 'data-exam_duration="' + $('<div/>').text(row[2]).html() + '" '
									    + 'data-exam_id="' + exam_id + '">Take Exam</button>';
								} else {
									html = '<button type="button" class="btn btn-warning btn-sm enroll_exam" data-exam_id="' + exam_id + '">Enroll</button>';
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
						showInfoModal('Failed to enroll. Please try again.', 'Enroll Failed');
			}
		});
	});

	$(document).on('click', '.take_exam', function(){
		// Check schedule before showing confirmation modal
		var $btn = $(this);
		var exam_code = $btn.data('exam_code');
		var exam_title = $btn.data('exam_title') || 'Exam';
		var exam_datetime = $btn.data('exam_datetime') || '';
		var exam_duration = $btn.data('exam_duration') || '';
		var exam_id = $btn.data('exam_id');

		// Ask server if the exam is allowed today
		$.ajax({
			url: 'user_ajax_action.php',
			type: 'POST',
			dataType: 'json',
			data: { page: 'enroll_exam', action: 'check_schedule', exam_id: exam_id, tz_name: Intl.DateTimeFormat().resolvedOptions().timeZone },
			success: function(resp) {
					if (resp && resp.success && resp.allowed) {
					// allowed: show modal
					$('#confirmExamModalLabel').text(exam_title);
					$('#confirmExamDatetime').text(exam_datetime);
					$('#confirmExamDuration').text(exam_duration + ' minutes');
					$('#confirmExamStart').data('exam_code', exam_code).data('exam_id', exam_id);
					$('#confirmExamStart').prop('disabled', false).text('Take Exam');
					$('#confirmExamNotice').remove();
					var modalEl = document.getElementById('confirmExamModal');
					if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
						try { new bootstrap.Modal(modalEl).show(); } catch(e){ if (window.jQuery) $(modalEl).modal('show'); }
					} else if (window.jQuery && $(modalEl).modal) { $(modalEl).modal('show'); }
					} else if (resp && resp.success && !resp.allowed) {
					// not allowed: show an alert modal with friendly conversion
						var scheduled = resp.scheduled_date ? new Date(resp.scheduled_date + 'T00:00:00').toDateString() : 'on the scheduled date';
						showInfoModal('This exam is scheduled for ' + scheduled + '. You can take it only on that date in your local timezone.', 'Exam Not Available');
				} else {
						showInfoModal('Unable to verify exam schedule. Please try again later.', 'Schedule Check Failed');
				}
			},
			error: function() {
				showInfoModal('Failed to check exam schedule. Please try again.', 'Network Error');
			}
		});
	});

	// When user confirms in modal, redirect to exam_start.php
	$(document).on('click', '#confirmExamStart', function(){
		var exam_code = $(this).data('exam_code');
		var exam_id = $(this).data('exam_id');
		// Prefer exam_id if available, otherwise use code
			if(exam_id) {
				window.location.href = 'exam_start.php?exam_id=' + encodeURIComponent(exam_id) + '&tz_name=' + encodeURIComponent(Intl.DateTimeFormat().resolvedOptions().timeZone);
			} else if(exam_code) {
				window.location.href = 'exam_start.php?code=' + encodeURIComponent(exam_code) + '&tz_name=' + encodeURIComponent(Intl.DateTimeFormat().resolvedOptions().timeZone);
		} else {
			// fallback to view_exam
			window.location.href = 'view_exam.php?code=' + encodeURIComponent(exam_code || '');
		}
	});

});

</script>

<!-- Confirmation Modal for Taking Exam -->
<div class="modal fade" id="confirmExamModal" tabindex="-1" aria-labelledby="confirmExamModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header bg-primary text-white">
				<h5 class="modal-title" id="confirmExamModalLabel">Confirm Exam</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body text-center">
				<p class="lead">You are about to start the following exam:</p>
				<h5 id="confirmExamModalLabelTitle" class="mb-2"></h5>
				<p id="confirmExamDatetime" class="mb-1"></p>
				<p id="confirmExamDuration" class="mb-0"></p>
			</div>
			<div class="modal-footer justify-content-center">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-success" id="confirmExamStart">Take Exam</button>
			</div>
		</div>
	</div>
</div>

			<!-- Info Modal for friendly messages -->
			<div class="modal fade" id="infoModal" tabindex="-1" aria-labelledby="infoModalLabel" aria-hidden="true">
				<div class="modal-dialog modal-dialog-centered">
					<div class="modal-content">
						<div class="modal-header bg-secondary text-white">
							<h5 class="modal-title" id="infoModalLabel">Notice</h5>
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
						</div>
						<div class="modal-body" id="infoModalBody">
						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
						</div>
					</div>
				</div>
			</div>

			<script>
			$(document).ready(function(){
				var examTable = $('#exam_data_table').DataTable({
					"processing" : true,
					"serverSide" : false,
					"order" : [],
					"pageLength": 10,
					"lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]]
				});
			});

			function showInfoModal(message, title) {
					title = title || 'Notice';
					var modalEl = document.getElementById('infoModal');
					var label = document.getElementById('infoModalLabel');
					var body = document.getElementById('infoModalBody');
					if (label) label.textContent = title;
					if (body) body.textContent = message;
					if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
							try { new bootstrap.Modal(modalEl).show(); } catch(e){ if (window.jQuery) $(modalEl).modal('show'); }
					} else if (window.jQuery && $(modalEl).modal) { $(modalEl).modal('show'); }
			}
			</script>
