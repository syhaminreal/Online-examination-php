<?php
// take_exam.php: Exam interface for users
include('master/Examination.php');
$exam = new Examination;

// Get exam code from URL
$exam_code = isset($_GET['code']) ? $_GET['code'] : '';
$exam_id = '';
$exam_title = '';
$exam_duration = '';
$exam_status = '';

if ($exam_code) {
    $exam->query = "SELECT * FROM online_exam_table WHERE online_exam_code = '" . $exam_code . "' LIMIT 1";
    $result = $exam->query_result();
    if (!empty($result)) {
        $exam_id = $result[0]['online_exam_id'];
        $exam_title = $result[0]['online_exam_title'];
        $exam_duration = $result[0]['online_exam_duration'];
        $exam_status = $result[0]['online_exam_status'];
    }
}

if (!$exam_id) {
    echo '<div class="container mt-5"><div class="alert alert-danger">Invalid exam code.</div></div>';
    exit;
}

// Check if exam is available (today)
$current_date = date('Y-m-d');
$exam_date = isset($result[0]['online_exam_datetime']) ? date('Y-m-d', strtotime($result[0]['online_exam_datetime'])) : '';
$can_take = ($exam_date === $current_date);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Take Exam - <?php echo htmlspecialchars($exam_title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-lg">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Take Exam: <?php echo htmlspecialchars($exam_title); ?></h4>
                </div>
                <div class="card-body">
<?php
if ($can_take) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['answer'])) {
        $answers = $_POST['answer'];
        $total_marks = 0;
        $user_id = $_SESSION['user_id'];
        // Fetch questions for marking
        $exam->query = "SELECT * FROM question_table WHERE online_exam_id = '$exam_id' ORDER BY question_id ASC";
        $questions = $exam->query_result();
        foreach ($questions as $question) {
            $qid = $question['question_id'];
            $correct_option = $question['answer_option'];
            $user_option = isset($answers[$qid]) ? $answers[$qid] : null;
            $marks = 0;
            if ($user_option !== null) {
                if ($user_option == $correct_option) {
                    $marks = $question['marks_per_right_answer'];
                } else {
                    $marks = -abs($question['marks_per_wrong_answer']);
                }
            }
            // Save answer to user_exam_question_answer table
            $exam->query = "REPLACE INTO user_exam_question_answer (user_id, exam_id, question_id, user_answer_option, marks) VALUES ('$user_id', '$exam_id', '$qid', '$user_option', '$marks')";
            $exam->execute_query();
            $total_marks += $marks;
        }
        // Show result message and redirect
        echo '<div class="alert alert-success mt-4">Your result is now available. Redirecting to result page...</div>';
        echo '<script>setTimeout(function(){ window.location.href = "result.php?code=' . htmlspecialchars($exam_code) . '"; }, 2000);</script>';
        exit;
    }
    // Fetch questions for this exam
    $exam->query = "SELECT * FROM question_table WHERE online_exam_id = '$exam_id' ORDER BY question_id ASC";
    $questions = $exam->query_result();
    if (!empty($questions)) {
        echo '<form method="post" action="">';
        foreach ($questions as $q_idx => $question) {
            echo '<div class="mb-4">';
            echo '<h5>Q' . ($q_idx + 1) . '. ' . htmlspecialchars($question['question_title']) . '</h5>';
            // Fetch options for this question
            $exam->query = "SELECT * FROM option_table WHERE question_id = '" . $question['question_id'] . "' ORDER BY option_number ASC";
            $options = $exam->query_result();
            foreach ($options as $opt) {
                echo '<div class="form-check">';
                echo '<input class="form-check-input" type="radio" name="answer[' . $question['question_id'] . ']" id="q' . $question['question_id'] . '_opt' . $opt['option_number'] . '" value="' . $opt['option_number'] . '">';
                echo '<label class="form-check-label" for="q' . $question['question_id'] . '_opt' . $opt['option_number'] . '">' . htmlspecialchars($opt['option_title']) . '</label>';
                echo '</div>';
            }
            echo '</div>';
        }
        echo '<button type="submit" class="btn btn-success btn-lg">Submit Exam</button>';
        echo '</form>';
    } else {
        echo '<div class="alert alert-info">No questions found for this exam.</div>';
    }
} else {
    echo '<div class="alert alert-warning mb-4">You cannot take this exam now. It is only available on <strong>' . date('F j, Y', strtotime($result[0]['online_exam_datetime'])) . '</strong>.</div>';
    echo '<a href="enroll_exam.php" class="btn btn-primary">Return to Exam List</a>';
}
?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
