<?php
session_start();
require_once 'db_connection.php';

// Check if results exist in session
if (!isset($_SESSION['quiz_results'])) {
    $_SESSION['error'] = "No quiz results found! Please take a quiz first.";
    header('Location: user_quizzes.php');
    exit();
}

$results = $_SESSION['quiz_results'];
$user_answers = $results['user_answers'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Results - <?php echo $results['quiz_title']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .result-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .score-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            margin: 0 auto;
        }
        .passed { background-color: #d4edda; color: #155724; border: 4px solid #c3e6cb; }
        .failed { background-color: #f8d7da; color: #721c24; border: 4px solid #f5c6cb; }
        .correct-answer { background-color: #d4edda; padding: 5px; border-radius: 4px; }
        .wrong-answer { background-color: #f8d7da; padding: 5px; border-radius: 4px; }
        .user-answer { font-weight: bold; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="card result-card">
            <div class="card-header text-center bg-primary text-white">
                <h2>Quiz Results: <?php echo $results['quiz_title']; ?></h2>
                <p class="mb-0">Completed on: <?php echo $results['completed_at']; ?></p>
            </div>
            <div class="card-body">
                <!-- Score Summary -->
                <div class="row text-center mb-4">
                    <div class="col-md-3">
                        <div class="score-circle <?php echo ($results['percentage'] >= 50) ? 'passed' : 'failed'; ?>">
                            <?php echo round($results['percentage'], 1); ?>%
                        </div>
                    </div>
                    <div class="col-md-9">
                        <div class="row">
                            <div class="col-md-3">
                                <h4><?php echo $results['correct_answers']; ?>/<?php echo $results['total_questions']; ?></h4>
                                <p class="text-muted">Correct Answers</p>
                            </div>
                            <div class="col-md-3">
                                <h4><?php echo $results['obtained_marks']; ?>/<?php echo $results['total_marks']; ?></h4>
                                <p class="text-muted">Marks Obtained</p>
                            </div>
                            <div class="col-md-3">
                                <h4><?php echo round($results['percentage'], 1); ?>%</h4>
                                <p class="text-muted">Percentage</p>
                            </div>
                            <div class="col-md-3">
                                <h4>
                                    <?php if ($results['percentage'] >= 50): ?>
                                        <span class="badge bg-success">PASSED</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">FAILED</span>
                                    <?php endif; ?>
                                </h4>
                                <p class="text-muted">Status</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Question-wise Review -->
                <h4 class="mb-3">Question-wise Review</h4>
                <?php foreach ($user_answers as $index => $answer): 
                    $is_correct = $answer['is_correct'];
                    $answer_class = $is_correct ? 'correct-answer' : 'wrong-answer';
                ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <h5>Question <?php echo $index + 1; ?> (<?php echo $answer['marks']; ?> marks)</h5>
                        <p><strong>Question:</strong> <?php echo $answer['question_text']; ?></p>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Your Answer:</strong> 
                                    <span class="<?php echo $answer_class; ?> user-answer">
                                        <?php echo $answer['user_answer'] ?: 'Not answered'; ?>
                                        <?php if ($is_correct): ?>
                                            ✓ Correct
                                        <?php else: ?>
                                            ✗ Wrong
                                        <?php endif; ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Correct Answer:</strong> 
                                    <span class="correct-answer"><?php echo $answer['correct_answer']; ?> ✓</span>
                                </p>
                            </div>
                        </div>
                        
                        <div class="options-review">
                            <p><strong>Options:</strong></p>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>A)</strong> <?php echo $answer['option_a']; ?></p>
                                    <p><strong>B)</strong> <?php echo $answer['option_b']; ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>C)</strong> <?php echo $answer['option_c']; ?></p>
                                    <p><strong>D)</strong> <?php echo $answer['option_d']; ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <p class="mt-2">
                            <strong>Marks:</strong> 
                            <?php echo $answer['marks_obtained']; ?>/<?php echo $answer['marks']; ?>
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <div class="text-center mt-4">
                    <a href="user_quizzes.php" class="btn btn-primary">Take Another Quiz</a>
                    <button onclick="window.print()" class="btn btn-secondary">Print Results</button>
                    <button onclick="clearResults()" class="btn btn-warning">Clear Results</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function clearResults() {
            if (confirm('Are you sure you want to clear these results?')) {
                window.location.href = 'clear_results.php';
            }
    </script>
</body>
</html>