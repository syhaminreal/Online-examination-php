<?php
include_once 'header.php';
include_once('master/Examination.php');
$exam = new Examination;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$exam_id = $_POST['exam_id'] ?? 0;
$user_id = $_SESSION['user_id'] ?? 0;

// Fetch user's name from user_table
$exam->query = "
    SELECT user_name 
    FROM user_table 
    WHERE user_id = $user_id
";
$user_data = $exam->query_result();
$user_name = $user_data[0]['user_name'] ?? 'User';

// Fetch all questions for this exam
$exam->query = "
    SELECT question_id, question_title, answer_option 
    FROM question_table 
    WHERE online_exam_id = $exam_id
";
$questions = $exam->query_result();

// Initialize counters
$total_questions = count($questions);
$correct_count = 0;
$wrong_count = 0;

foreach ($questions as $q) {
    $user_answer = $_POST['question_'.$q['question_id']] ?? null;
    if ($user_answer !== null) {
        if ($user_answer == $q['answer_option']) {
            $correct_count++;
        } else {
            $wrong_count++;
        }
    }
}

$score_percent = ($total_questions > 0) ? round(($correct_count / $total_questions) * 100) : 0;
?>

<div class="container mt-5">
    <div class="card shadow-lg">
        <div class="card-header bg-success text-white">
            <h3>Exam Result for <?php echo htmlspecialchars($user_name); ?></h3>
        </div>
        <div class="card-body text-center">
            <h4>Your Score: <strong><?php echo $score_percent; ?>%</strong></h4>
            <!-- Smaller chart container -->
            <div style="width: 120px; height: 120px; margin: 0 auto;">
                <canvas id="resultChart"></canvas>
            </div>
            <p>Total Questions: <strong><?php echo $total_questions; ?></strong></p>
            <p>Correct Answers: <strong><?php echo $correct_count; ?></strong></p>
            <p>Wrong Answers: <strong><?php echo $wrong_count; ?></strong></p>

            <div class="mt-4">
                <button id="printResultBtn" class="btn btn-primary me-2 no-print">Print Result</button>
                <a href="enroll_exam.php" class="btn btn-secondary no-print">Take Another Exam</a>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('resultChart').getContext('2d');
var resultChart = new Chart(ctx, {
    type: 'pie',
    data: {
        labels: ['Correct', 'Wrong'],
        datasets: [{
            data: [<?php echo $correct_count; ?>, <?php echo $wrong_count; ?>],
            backgroundColor: ['#198754', '#dc3545']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false, // respects the container size
        plugins: {
            legend: { 
                position: 'bottom', 
                labels: { boxWidth: 10, padding: 8 } // smaller legend
            },
            title: {
                display: true,
                text: 'Exam Result Breakdown',
                font: { size: 12 } // smaller title font
            }
        }
    }
});
</script>
<script>
// Print button: resize chart then call print
document.addEventListener('DOMContentLoaded', function() {
    var btn = document.getElementById('printResultBtn');
    if (!btn) return;
    btn.addEventListener('click', function() {
        try { resultChart.resize(); } catch (e) {}
        window.print();
    });
});
</script>
<style>
    @media print {
        .no-print { display: none !important; }
        #resultChart { width: 320px !important; height: 320px !important; }
    }
</script>
