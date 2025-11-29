<?php
session_start();
require_once 'db_connection.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Quizzes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Available Quizzes</h2>
        
        <!-- Display messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <div class="row">
            <?php
            try {
                $stmt = $pdo->query("SELECT * FROM quiz_table WHERE quiz_status = 'active' ORDER BY created_at DESC");
                $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($quizzes)) {
                    echo "<div class='col-12'><div class='alert alert-info'>No quizzes available at the moment.</div></div>";
                } else {
                    foreach ($quizzes as $quiz) {
                        // Count questions in this quiz
                        $question_stmt = $pdo->prepare("SELECT COUNT(*) as question_count FROM question_table WHERE quiz_id = ?");
                        $question_stmt->execute([$quiz['quiz_id']]);
                        $question_count = $question_stmt->fetch(PDO::FETCH_ASSOC)['question_count'];
                        
                        echo "<div class='col-md-4 mb-3'>";
                        echo "<div class='card h-100'>";
                        echo "<div class='card-body'>";
                        echo "<h5 class='card-title'>{$quiz['quiz_title']}</h5>";
                        echo "<p class='card-text'>{$quiz['quiz_description']}</p>";
                        echo "<p class='mb-1'><small class='text-muted'>Duration: {$quiz['quiz_duration_minutes']} minutes</small></p>";
                        echo "<p class='mb-1'><small class='text-muted'>Questions: {$question_count}</small></p>";
                        echo "<p class='mb-1'><small class='text-muted'>Marks per question: {$quiz['quiz_marks_per_question']}</small></p>";
                        
                        echo "<a href='take_quiz.php?quiz_id={$quiz['quiz_id']}' class='btn btn-primary'>Start Quiz</a>";
                        
                        echo "</div></div></div>";
                    }
                }
            } catch (PDOException $e) {
                echo "<div class='col-12'><div class='alert alert-danger'>Error loading quizzes: " . $e->getMessage() . "</div></div>";
            }
            ?>
        </div>
    </div>
</body>
</html>