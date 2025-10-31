
<?php
//index.php
include('master/Examination.php');
$exam = new Examination;
include('header.php');
?>

<div class="container mt-5">
	<?php if(isset($_SESSION["user_id"])) { ?>
	<div class="row justify-content-center">
		<div class="col-md-8">
			<div class="card shadow-sm">
				<div class="card-body text-center">
					<h2 class="mb-3 text-primary">Welcome to the Online Examination System</h2>
					<p class="lead">This application allows students to register, enroll, and take online exams securely and efficiently. Results are instantly available with analytics and print options. Teachers can create exams, manage questions, and monitor student performance.</p>
					<hr>
					<p class="mb-0">To get started, select an exam below and begin your test!</p>
				</div>
			</div>
		</div>
	</div>

	<div class="row justify-content-center mt-4">
		<div class="col-md-6">
			<div class="card shadow-sm">
				<div class="card-body text-center">
					<h4 class="mb-4">Take Exam</h4>
					<select name="exam_list" id="exam_list" class="form-control input-lg mb-3">
						<option value="">Select Exam</option>
						<?php echo $exam->Fill_exam_list(); ?>
					</select>
				</div>
			</div>
		</div>
	</div>
	
	<div class="row justify-content-center mt-4">
		<div class="col-md-12 text-center">
			<div class="jumbotron" style="padding: 1rem 1rem; background: transparent; border: none;">
				<img src="master/logo.png" class="img-fluid" width="300" alt="Online Examination System in PHP" />
			</div>
		</div>
	</div>
	<!-- Add Modal for Exam Details -->
	<div class="modal fade" id="examDetailsModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Exam Details</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body" id="examDetailsContent">
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
					<button type="button" class="btn btn-primary" id="takeExamBtn" style="display:none">Take Exam</button>
				</div>
			</div>
		</div>
	</div>

	<script>
	$(document).ready(function(){
		$('#exam_list').parsley();
		var exam_id = '';
		
		$('#exam_list').change(function(){
			$('#exam_list').attr('required', 'required');
			if($('#exam_list').parsley().validate()) {
				exam_id = $('#exam_list').val();
				
				// Fetch exam details
				$.ajax({
					url: "user_ajax_action.php",
					method: "POST",
					data: {action:'fetch_exam', page:'index', exam_id:exam_id},
					success: function(data) {
						try {
							const examData = JSON.parse(data);
							// Get exact exam date and time
							const examDate = new Date(examData.online_exam_datetime);
							const now = new Date();
							
							// Format dates for comparison
							const examDateStr = examDate.toISOString().split('T')[0];
							const todayStr = now.toISOString().split('T')[0];

							// Prepare modal content
							let modalContent = `
								<div class="alert ${today.getTime() === examDate.getTime() ? 'alert-success' : 'alert-warning'} mb-3">
									<strong>Exam Date:</strong> ${examData.online_exam_datetime}
								</div>
								<p><strong>Title:</strong> ${examData.online_exam_title}</p>
								<p><strong>Duration:</strong> ${examData.online_exam_duration} Minutes</p>
								<p><strong>Total Questions:</strong> ${examData.total_question}</p>
								<p><strong>Marks per Right Answer:</strong> +${examData.marks_per_right_answer}</p>
								<p><strong>Marks per Wrong Answer:</strong> ${examData.marks_per_wrong_answer}</p>
							`;

							if (examDateStr === todayStr) {
								modalContent += '<div class="alert alert-success">This exam is available today!</div>';
								$('#takeExamBtn').show();
							} else {
								if (examDate < now) {
									modalContent += '<div class="alert alert-danger">This exam has already passed.</div>';
								} else {
									modalContent += `<div class="alert alert-warning">
										This exam is scheduled for ${examData.online_exam_datetime}.<br>
										You can only take this exam on the scheduled date.
									</div>`;
								}
								$('#takeExamBtn').hide();
							}

							$('#examDetailsContent').html(modalContent);
							$('#examDetailsModal').modal('show');
						} catch (e) {
							console.error('Error parsing exam data:', e);
						}
					}
				});
			}
		});

		// Handle Take Exam button click
		$('#takeExamBtn').click(function() {
			window.location.href = 'enroll_exam.php?exam_id=' + exam_id;
		});
	});
	</script>
	<?php } else { ?>
	<div class="row justify-content-center">
		<div class="col-md-6 text-center">
			<a href="register.php" class="btn btn-warning btn-lg me-2">Register</a>
			<a href="login.php" class="btn btn-dark btn-lg">Login</a>
		</div>
	</div>
	<?php } ?>
</div>
</body>
</html>