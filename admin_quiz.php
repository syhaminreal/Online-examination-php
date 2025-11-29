<?php
session_start();
require_once 'db_connection.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Quiz Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Quiz Management</h2>
        
        <!-- Display messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <!-- Navigation -->
        <div class="mb-3">
            <a href="user_quizzes.php" class="btn btn-outline-primary">View User Quizzes</a>
        </div>
        
        <!-- Create Quiz Button -->
        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#createQuizModal">
            Create New Quiz
        </button>

        <!-- Quizzes List -->
        <div class="card">
            <div class="card-header">
                <h5>Available Quizzes</h5>
            </div>
            <div class="card-body">
                <?php
                try {
                    $stmt = $pdo->query("SELECT * FROM quiz_table ORDER BY created_at DESC");
                    $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (empty($quizzes)) {
                        echo "<p>No quizzes found. Create your first quiz!</p>";
                    } else {
                        echo '<table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Duration</th>
                                        <th>Questions</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>';
                        
                        foreach ($quizzes as $quiz) {
                            // Count questions
                            $question_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM question_table WHERE quiz_id = ?");
                            $question_stmt->execute([$quiz['quiz_id']]);
                            $question_count = $question_stmt->fetch(PDO::FETCH_ASSOC)['count'];
                            
                            echo "<tr>";
                            echo "<td>{$quiz['quiz_id']}</td>";
                            echo "<td>{$quiz['quiz_title']}</td>";
                            echo "<td>{$quiz['quiz_duration_minutes']} min</td>";
                            echo "<td>{$question_count}/{$quiz['quiz_total_questions']}</td>";
                            echo "<td><span class='badge " . ($quiz['quiz_status'] == 'active' ? 'bg-success' : 'bg-secondary') . "'>{$quiz['quiz_status']}</span></td>";
                            echo "<td>
                                    <a href='manage_quiz_questions.php?quiz_id={$quiz['quiz_id']}' class='btn btn-sm btn-info'>Manage Questions</a>
                                    <a href='toggle_quiz.php?quiz_id={$quiz['quiz_id']}' class='btn btn-sm " . ($quiz['quiz_status'] == 'active' ? 'btn-warning' : 'btn-success') . "'>" . ($quiz['quiz_status'] == 'active' ? 'Deactivate' : 'Activate') . "</a>
                                    <a href='delete_quiz.php?quiz_id={$quiz['quiz_id']}' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure?\")'>Delete</a>
                                  </td>";
                            echo "</tr>";
                        }
                        
                        echo '</tbody></table>';
                    }
                } catch (PDOException $e) {
                    echo "<div class='alert alert-danger'>Error loading quizzes: " . $e->getMessage() . "</div>";
                }
                ?>
            </div>
        </div>
    </div>

    <!-- Create Quiz Modal -->
    <div class="modal fade" id="createQuizModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Quiz</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="create_quiz.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Quiz Title</label>
                            <input type="text" class="form-control" name="quiz_title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="quiz_description" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Duration (minutes)</label>
                            <input type="number" class="form-control" name="quiz_duration_minutes" value="10" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Total Questions</label>
                            <input type="number" class="form-control" name="quiz_total_questions" value="5" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Marks per Question</label>
                            <input type="number" class="form-control" name="quiz_marks_per_question" value="1" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Create Quiz</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>