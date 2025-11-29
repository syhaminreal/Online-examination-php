<?php
session_start();
require_once 'db_connection.php';

$quiz_id = $_GET['quiz_id'];

// Get quiz details
$stmt = $pdo->prepare("SELECT * FROM quiz_table WHERE quiz_id = ? AND quiz_status = 'active'");
$stmt->execute([$quiz_id]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    $_SESSION['error'] = "Quiz not found or inactive!";
    header('Location: user_quizzes.php');
    exit();
}

// Get questions for this quiz
$stmt = $pdo->prepare("SELECT * FROM question_table WHERE quiz_id = ? ORDER BY question_id");
$stmt->execute([$quiz_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($questions)) {
    $_SESSION['error'] = "No questions available for this quiz!";
    header('Location: user_quizzes.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $total_questions = count($questions);
        $correct_answers = 0;
        $total_marks = 0;
        $obtained_marks = 0;
        
        $user_answers = [];
        
        // Process each question
        foreach ($questions as $question) {
            $user_answer = $_POST['answer_' . $question['question_id']] ?? '';
            $is_correct = false;
            $marks_obtained = 0;
            
            // Check if answer is correct
            if (strtoupper(trim($user_answer)) == strtoupper(trim($question['correct_answer']))) {
                $is_correct = true;
                $marks_obtained = $question['marks'];
                $correct_answers++;
                $obtained_marks += $question['marks'];
            }
            
            $total_marks += $question['marks'];
            
            // Store user answers in session for results
            $user_answers[] = [
                'question_id' => $question['question_id'],
                'question_text' => $question['question_text'],
                'option_a' => $question['option_a'],
                'option_b' => $question['option_b'],
                'option_c' => $question['option_c'],
                'option_d' => $question['option_d'],
                'correct_answer' => $question['correct_answer'],
                'user_answer' => $user_answer,
                'is_correct' => $is_correct,
                'marks' => $question['marks'],
                'marks_obtained' => $marks_obtained
            ];
        }
        
        // Calculate percentage
        $percentage = ($total_marks > 0) ? ($obtained_marks / $total_marks) * 100 : 0;
        
        // Store results in session
        $_SESSION['quiz_results'] = [
            'quiz_id' => $quiz_id,
            'quiz_title' => $quiz['quiz_title'],
            'total_questions' => $total_questions,
            'correct_answers' => $correct_answers,
            'total_marks' => $total_marks,
            'obtained_marks' => $obtained_marks,
            'percentage' => $percentage,
            'user_answers' => $user_answers,
            'completed_at' => date('Y-m-d H:i:s')
        ];
        
        header('Location: quiz_results.php');
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error submitting quiz: " . $e->getMessage();
        header('Location: user_quizzes.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Quiz - <?php echo $quiz['quiz_title']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .question-card {
            margin-bottom: 20px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: #f9f9f9;
        }
        .option-label {
            font-weight: normal;
            margin-left: 8px;
        }
        .quiz-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="quiz-header">
            <h2><?php echo $quiz['quiz_title']; ?></h2>
            <p class="mb-0">Duration: <?php echo $quiz['quiz_duration_minutes']; ?> minutes | 
               Questions: <?php echo count($questions); ?> | 
               Total Marks: <?php echo array_sum(array_column($questions, 'marks')); ?></p>
        </div>
        
        <form method="POST" id="quizForm">
            <?php foreach ($questions as $index => $question): ?>
            <div class="card question-card">
                <div class="card-body">
                    <h5 class="card-title">Question <?php echo $index + 1; ?> (<?php echo $question['marks']; ?> marks)</h5>
                    <p class="card-text fs-5"><?php echo $question['question_text']; ?></p>
                    
                    <div class="options">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="answer_<?php echo $question['question_id']; ?>" value="A" id="q<?php echo $question['question_id']; ?>_a" required>
                            <label class="form-check-label option-label" for="q<?php echo $question['question_id']; ?>_a">
                                <strong>A)</strong> <?php echo $question['option_a']; ?>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="answer_<?php echo $question['question_id']; ?>" value="B" id="q<?php echo $question['question_id']; ?>_b">
                            <label class="form-check-label option-label" for="q<?php echo $question['question_id']; ?>_b">
                                <strong>B)</strong> <?php echo $question['option_b']; ?>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="answer_<?php echo $question['question_id']; ?>" value="C" id="q<?php echo $question['question_id']; ?>_c">
                            <label class="form-check-label option-label" for="q<?php echo $question['question_id']; ?>_c">
                                <strong>C)</strong> <?php echo $question['option_c']; ?>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="answer_<?php echo $question['question_id']; ?>" value="D" id="q<?php echo $question['question_id']; ?>_d">
                            <label class="form-check-label option-label" for="q<?php echo $question['question_id']; ?>_d">
                                <strong>D)</strong> <?php echo $question['option_d']; ?>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <div class="text-center mt-4">
                <button type="submit" class="btn btn-success btn-lg px-5">Submit Quiz</button>
                <a href="user_quizzes.php" class="btn btn-secondary btn-lg px-5">Cancel</a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.getElementById('quizForm').addEventListener('submit', function(e) {
            const unanswered = [];
            const questions = document.querySelectorAll('[class*="question-card"]');
            
            questions.forEach((question, index) => {
                const questionId = question.querySelector('input[type="radio"]').name;
                const answered = document.querySelector(`input[name="${questionId}"]:checked`);
                if (!answered) {
                    unanswered.push(index + 1);
                }
            });
            
            if (unanswered.length > 0) {
                e.preventDefault();
                if (!confirm(`You have ${unanswered.length} unanswered question(s). Are you sure you want to submit?`)) {
                    return false;
                }
            }
        });
    </script>
</body>
</html>