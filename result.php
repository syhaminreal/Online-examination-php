<?php
// result.php: Modern exam result page with graphs and print option
include('master/Examination.php');
$exam = new Examination;

$exam_code = isset($_GET['code']) ? $_GET['code'] : '';
$user_id = $_SESSION['user_id'];
$exam_id = '';
$exam_title = '';
$total_questions = 0;
$total_marks = 0;
$correct = 0;
$wrong = 0;
$not_attempted = 0;

if ($exam_code) {
    $exam->query = "SELECT * FROM online_exam_table WHERE online_exam_code = '" . $exam_code . "' LIMIT 1";
    $result = $exam->query_result();
    if (!empty($result)) {
        $exam_id = $result[0]['online_exam_id'];
        $exam_title = $result[0]['online_exam_title'];
        $total_questions = $result[0]['total_question'];
    }
}

if (!$exam_id) {
    echo '<div class="container mt-5"><div class="alert alert-danger">Invalid exam code.</div></div>';
    exit;
}

// Fetch user answers and calculate stats
$exam->query = "SELECT * FROM user_exam_question_answer WHERE user_id = '$user_id' AND exam_id = '$exam_id'";
$user_answers = $exam->query_result();
foreach ($user_answers as $ans) {
    $total_marks += intval($ans['marks']);
    if ($ans['marks'] > 0) $correct++;
    elseif ($ans['marks'] < 0) $wrong++;
    else $not_attempted++;
}

// Fetch user name
$user_name = '';
$exam->query = "SELECT user_name FROM user_table WHERE user_id = '$user_id' LIMIT 1";
$user_result = $exam->query_result();
if (!empty($user_result)) {
    $user_name = $user_result[0]['user_name'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Exam Result - <?php echo htmlspecialchars($exam_title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6"> <!-- Changed from col-md-8 to col-md-6 for smaller size -->
            <div class="card shadow-lg">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">Exam Result: <?php echo htmlspecialchars($exam_title); ?></h4>
                    <div class="mt-2 small">User: <strong><?php echo htmlspecialchars($user_name); ?></strong></div>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <strong>Total Questions:</strong> <?php echo $total_questions; ?> <br>
                        <strong>Correct:</strong> <?php echo $correct; ?> <br>
                        <strong>Wrong:</strong> <?php echo $wrong; ?> <br>
                        <strong>Not Attempted:</strong> <?php echo $not_attempted; ?> <br>
                        <strong>Total Marks:</strong> <?php echo $total_marks; ?>
                    </div>
                    <div class="mb-4">
                        <canvas id="resultChart" width="300" height="180"></canvas> <!-- Smaller chart -->
                    </div>
                    <div class="d-flex justify-content-center gap-3">
                        <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print me-2"></i> Print Result</button>
                        <a href="enroll_exam.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i> Return to Exam List</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
const ctx = document.getElementById('resultChart').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['Correct', 'Wrong', 'Not Attempted'],
        datasets: [{
            data: [<?php echo $correct; ?>, <?php echo $wrong; ?>, <?php echo $not_attempted; ?>],
            backgroundColor: ['#198754', '#dc3545', '#6c757d'],
        }]
    },
    options: {
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});
</script>
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>
</html>
