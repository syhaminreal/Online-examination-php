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

// Fetch exam details
$exam->query = "
    SELECT online_exam_title, online_exam_code, marks_per_right_answer 
    FROM online_exam_table 
    WHERE online_exam_id = $exam_id
";
$exam_data = $exam->query_result();
$exam_title = $exam_data[0]['online_exam_title'] ?? 'Exam';
$exam_code = $exam_data[0]['online_exam_code'] ?? '';
$marks_per_right = $exam_data[0]['marks_per_right_answer'] ?? 1;

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
$not_attempted = 0;
$total_marks = 0;

foreach ($questions as $q) {
    $user_answer = $_POST['question_'.$q['question_id']] ?? null;
    if ($user_answer !== null) {
        if ($user_answer == $q['answer_option']) {
            $correct_count++;
            $total_marks += $marks_per_right;
        } else {
            $wrong_count++;
        }
    } else {
        $not_attempted++;
    }
}

$score_percent = ($total_questions > 0) ? round(($correct_count / $total_questions) * 100, 2) : 0;
$total_possible_marks = $total_questions * $marks_per_right;

// Insert/Update result in user_exam_result table
// Check if result already exists first
$exam->query = "SELECT * FROM user_exam_result WHERE exam_id = :exam_id AND user_id = :user_id";
$exam->data = array(':exam_id' => $exam_id, ':user_id' => $user_id);
$existing_result = $exam->query_result();

if (empty($existing_result)) {
    // Insert new result - FIXED: removed attendance_status from data array since it has default value
    $exam->data = array(
        ':exam_id' => $exam_id,
        ':user_id' => $user_id,
        ':total_mark' => $total_marks,
        ':total_possible' => $total_possible_marks,
        ':percentage' => $score_percent
    );

    $exam->query = "
    INSERT INTO user_exam_result 
    (exam_id, user_id, total_mark, total_possible, percentage) 
    VALUES (:exam_id, :user_id, :total_mark, :total_possible, :percentage)
    ";
} else {
    // Update existing result - FIXED: removed attendance_status from update
    $exam->data = array(
        ':total_mark' => $total_marks,
        ':total_possible' => $total_possible_marks,
        ':percentage' => $score_percent,
        ':exam_id' => $exam_id,
        ':user_id' => $user_id
    );

    $exam->query = "
    UPDATE user_exam_result 
    SET total_mark = :total_mark, 
        total_possible = :total_possible, 
        percentage = :percentage,
        updated_on = NOW()
    WHERE exam_id = :exam_id AND user_id = :user_id
    ";
}
$exam->execute_query();

// Performance feedback
if ($score_percent >= 90) {
    $performance = "Outstanding";
    $feedback = "Exceptional performance! Excellent understanding demonstrated.";
    $badge_class = "bg-success";
} elseif ($score_percent >= 80) {
    $performance = "Excellent";
    $feedback = "Great work! Strong knowledge and skills shown.";
    $badge_class = "bg-success";
} elseif ($score_percent >= 70) {
    $performance = "Very Good";
    $feedback = "Good performance! Solid understanding of material.";
    $badge_class = "bg-primary";
} elseif ($score_percent >= 60) {
    $performance = "Good";
    $feedback = "Satisfactory performance. Review areas for improvement.";
    $badge_class = "bg-info";
} elseif ($score_percent >= 50) {
    $performance = "Average";
    $feedback = "Fair performance. Additional study recommended.";
    $badge_class = "bg-warning";
} elseif ($score_percent >= 40) {
    $performance = "Below Average";
    $feedback = "Needs improvement. Focus on core concepts.";
    $badge_class = "bg-warning";
} else {
    $performance = "Poor";
    $feedback = "Significant improvement needed. Review thoroughly.";
    $badge_class = "bg-danger";
}

// Fetch past attempts with performance ranking
$exam->query = "
SELECT 
    uer.*,
    oet.online_exam_title,
    oet.online_exam_code,
    RANK() OVER (ORDER BY uer.percentage DESC, uer.total_mark DESC) as performance_rank,
    COUNT(*) OVER () as total_exams_taken
FROM user_exam_result uer
JOIN online_exam_table oet ON uer.exam_id = oet.online_exam_id
WHERE uer.user_id = '$user_id'
ORDER BY uer.percentage DESC, uer.total_mark DESC
";
$past_attempts = $exam->query_result();

// Calculate performance statistics
$total_past_attempts = count($past_attempts);
$average_percentage = 0;
$highest_percentage = 0;
$lowest_percentage = 100;

if ($total_past_attempts > 0) {
    $total_percentage = 0;
    foreach ($past_attempts as $attempt) {
        $total_percentage += $attempt['percentage'];
        if ($attempt['percentage'] > $highest_percentage) $highest_percentage = $attempt['percentage'];
        if ($attempt['percentage'] < $lowest_percentage) $lowest_percentage = $attempt['percentage'];
    }
    $average_percentage = round($total_percentage / $total_past_attempts, 2);
}
?>

<div class="container mt-4">
    <!-- Current Result Card -->
    <div class="card shadow-lg mb-4">
        <div class="card-header bg-success text-white">
            <h3><i class="fas fa-award me-2"></i>Exam Result: <?php echo htmlspecialchars($exam_title); ?></h3>
            <p class="mb-0">Candidate: <strong><?php echo htmlspecialchars($user_name); ?></strong></p>
        </div>
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-4 text-center">
                    <div class="score-circle" style="width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; margin: 0 auto; border: 5px solid #e9ecef;">
                        <div>
                            <div style="font-size: 2em;"><?php echo $score_percent; ?>%</div>
                            <div style="font-size: 0.8em;">Score</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <span class="badge <?php echo $badge_class; ?> mb-2 p-2"><?php echo $performance; ?> Performance</span>
                    <p class="lead"><?php echo $feedback; ?></p>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Correct Answers:</strong> <?php echo $correct_count; ?></p>
                            <p><strong>Wrong Answers:</strong> <?php echo $wrong_count; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Not Attempted:</strong> <?php echo $not_attempted; ?></p>
                            <p><strong>Total Score:</strong> <?php echo $total_marks; ?>/<?php echo $total_possible_marks; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-md-6">
                    <div style="width: 200px; height: 200px; margin: 0 auto;">
                        <canvas id="resultChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="text-center mt-4 no-print">
                <button id="printResultBtn" class="btn btn-primary me-2">
                    <i class="fas fa-print me-2"></i>Print Result
                </button>
                <a href="enroll_exam.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Exams
                </a>
            </div>
        </div>
    </div>

    <!-- Past Attempts Section -->
    <?php if ($total_past_attempts > 0): ?>
    <div class="card shadow-lg">
        <div class="card-header bg-dark text-white">
            <h4 class="mb-0"><i class="fas fa-history me-2"></i>Your Exam Performance History</h4>
        </div>
        <div class="card-body">
            <!-- Performance Stats -->
            <div class="row mb-4">
                <div class="col-md-3 text-center">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h5 class="text-muted">Total Exams</h5>
                            <h3 class="text-primary"><?php echo $total_past_attempts; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 text-center">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h5 class="text-muted">Average Score</h5>
                            <h3 class="text-info"><?php echo $average_percentage; ?>%</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 text-center">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h5 class="text-muted">Highest Score</h5>
                            <h3 class="text-success"><?php echo $highest_percentage; ?>%</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 text-center">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h5 class="text-muted">Lowest Score</h5>
                            <h3 class="text-warning"><?php echo $lowest_percentage; ?>%</h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Past Attempts Table -->
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Rank</th>
                            <th>Exam</th>
                            <th>Score</th>
                            <th>Percentage</th>
                            <th>Total Marks</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($past_attempts as $attempt): 
                            $current_exam_class = ($attempt['exam_id'] == $exam_id) ? 'table-success' : '';
                            $rank_class = '';
                            if ($attempt['performance_rank'] == 1) $rank_class = 'bg-warning text-dark';
                            elseif ($attempt['performance_rank'] <= 3) $rank_class = 'bg-success text-white';
                        ?>
                        <tr class="<?php echo $current_exam_class; ?>">
                            <td>
                                <span class="badge <?php echo $rank_class; ?>">
                                    #<?php echo $attempt['performance_rank']; ?>
                                </span>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($attempt['online_exam_title']); ?>
                                <?php if ($attempt['exam_id'] == $exam_id): ?>
                                    <span class="badge bg-primary">Current</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                if ($attempt['percentage'] >= 90) echo '<span class="badge bg-success">Outstanding</span>';
                                elseif ($attempt['percentage'] >= 80) echo '<span class="badge bg-success">Excellent</span>';
                                elseif ($attempt['percentage'] >= 70) echo '<span class="badge bg-primary">Very Good</span>';
                                elseif ($attempt['percentage'] >= 60) echo '<span class="badge bg-info">Good</span>';
                                elseif ($attempt['percentage'] >= 50) echo '<span class="badge bg-warning">Average</span>';
                                else echo '<span class="badge bg-danger">Poor</span>';
                                ?>
                            </td>
                            <td><strong><?php echo $attempt['percentage']; ?>%</strong></td>
                            <td><?php echo $attempt['total_mark']; ?>/<?php echo $attempt['total_possible']; ?></td>
                            <td><?php echo date('M j, Y', strtotime($attempt['created_on'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('resultChart').getContext('2d');
var resultChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['Correct', 'Wrong', 'Not Attempted'],
        datasets: [{
            data: [<?php echo $correct_count; ?>, <?php echo $wrong_count; ?>, <?php echo $not_attempted; ?>],
            backgroundColor: ['#28a745', '#dc3545', '#ffc107']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' }
        },
        cutout: '50%'
    }
});

document.getElementById('printResultBtn').addEventListener('click', function() {
    window.print();
});
</script>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
@media print {
    .no-print { display: none !important; }
}
.score-circle {
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
</style>