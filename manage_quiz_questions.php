<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

require_once 'db_connection.php';

$quiz_id = $_GET['quiz_id'];
$stmt = $pdo->prepare("SELECT * FROM quiz_table WHERE quiz_id = ?");
$stmt->execute([$quiz_id]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    die("Quiz not found!");
}

// Check if question_table has quiz_id column
try {
    $check_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM information_schema.COLUMNS 
                                WHERE TABLE_NAME = 'question_table' AND COLUMN_NAME = 'quiz_id'");
    $check_stmt->execute();
    $has_quiz_id = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    
    if (!$has_quiz_id) {
        die("Question table is not properly configured. Please run create_question_table.php first.");
    }
} catch (PDOException $e) {
    die("Error checking table structure: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Questions - <?php echo $quiz['quiz_title']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Manage Questions: <?php echo $quiz['quiz_title']; ?></h2>
        
        <!-- Display messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <!-- Add Question Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Add New Question</h5>
            </div>
            <div class="card-body">
                <form action="add_quiz_question.php" method="POST">
                    <input type="hidden" name="quiz_id" value="<?php echo $quiz_id; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Question Text</label>
                        <textarea class="form-control" name="question_text" rows="3" required placeholder="Enter the question..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Option A</label>
                            <input type="text" class="form-control" name="option_a" required placeholder="Enter option A">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Option B</label>
                            <input type="text" class="form-control" name="option_b" required placeholder="Enter option B">
                        </div>
                    </div>
                    
                    <div class="row mt-2">
                        <div class="col-md-6">
                            <label class="form-label">Option C</label>
                            <input type="text" class="form-control" name="option_c" required placeholder="Enter option C">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Option D</label>
                            <input type="text" class="form-control" name="option_d" required placeholder="Enter option D">
                        </div>
                    </div>
                    
                    <div class="mb-3 mt-3">
                        <label class="form-label">Correct Answer</label>
                        <select class="form-control" name="correct_answer" required>
                            <option value="">Select correct answer</option>
                            <option value="A">Option A</option>
                            <option value="B">Option B</option>
                            <option value="C">Option C</option>
                            <option value="D">Option D</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Marks</label>
                        <input type="number" class="form-control" name="marks" value="1" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Add Question</button>
                </form>
            </div>
        </div>

        <!-- Questions List -->
        <div class="card">
            <div class="card-header">
                <h5>Quiz Questions (<?php 
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM question_table WHERE quiz_id = ?");
                    $stmt->execute([$quiz_id]);
                    $count = $stmt->fetch(PDO::FETCH_ASSOC);
                    echo $count['count'] . '/' . $quiz['quiz_total_questions'];
                ?>)</h5>
            </div>
            <div class="card-body">
                <?php
                try {
                    $stmt = $pdo->prepare("SELECT * FROM question_table WHERE quiz_id = ? ORDER BY question_id");
                    $stmt->execute([$quiz_id]);
                    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (empty($questions)) {
                        echo "<p>No questions added yet. Add your first question above!</p>";
                    } else {
                        foreach ($questions as $index => $question) {
                            echo "<div class='card mb-3'>";
                            echo "<div class='card-body'>";
                            echo "<h6>Q" . ($index + 1) . ": {$question['question_text']} ({$question['marks']} marks)</h6>";
                            
                            echo "<div class='row mt-3'>";
                            echo "<div class='col-md-6'>";
                            echo "<p><strong>A)</strong> {$question['option_a']} " . ($question['correct_answer'] == 'A' ? '<span class="badge bg-success">Correct</span>' : '') . "</p>";
                            echo "<p><strong>B)</strong> {$question['option_b']} " . ($question['correct_answer'] == 'B' ? '<span class="badge bg-success">Correct</span>' : '') . "</p>";
                            echo "</div>";
                            echo "<div class='col-md-6'>";
                            echo "<p><strong>C)</strong> {$question['option_c']} " . ($question['correct_answer'] == 'C' ? '<span class="badge bg-success">Correct</span>' : '') . "</p>";
                            echo "<p><strong>D)</strong> {$question['option_d']} " . ($question['correct_answer'] == 'D' ? '<span class="badge bg-success">Correct</span>' : '') . "</p>";
                            echo "</div>";
                            echo "</div>";
                            
                            echo "<div class='mt-2'>";
                            echo "<a href='edit_question.php?question_id={$question['question_id']}' class='btn btn-sm btn-warning'>Edit</a> ";
                            echo "<a href='delete_question.php?question_id={$question['question_id']}&quiz_id={$quiz_id}' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure you want to delete this question?\")'>Delete</a>";
                            echo "</div>";
                            echo "</div></div>";
                        }
                    }
                } catch (PDOException $e) {
                    echo "<div class='alert alert-danger'>Error loading questions: " . $e->getMessage() . "</div>";
                }
                ?>
            </div>
        </div>
        
        <a href="admin_quiz.php" class="btn btn-secondary mt-3">Back to Quizzes</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>