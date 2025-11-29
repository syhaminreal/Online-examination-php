<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

require_once 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $quiz_id = $_POST['quiz_id'];
        $question_text = $_POST['question_text'];
        $option_a = $_POST['option_a'];
        $option_b = $_POST['option_b'];
        $option_c = $_POST['option_c'];
        $option_d = $_POST['option_d'];
        $correct_answer = $_POST['correct_answer'];
        $marks = $_POST['marks'];

        // First, check if question_table exists and has quiz_id column
        $check_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM information_schema.COLUMNS 
                                    WHERE TABLE_NAME = 'question_table' AND COLUMN_NAME = 'quiz_id'");
        $check_stmt->execute();
        $has_quiz_id = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
        
        if (!$has_quiz_id) {
            $_SESSION['error'] = "Question table is not properly configured. Please run create_question_table.php first.";
            header('Location: manage_quiz_questions.php?quiz_id=' . $quiz_id);
            exit();
        }

        // Check if we haven't exceeded total questions
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM question_table WHERE quiz_id = ?");
        $stmt->execute([$quiz_id]);
        $current_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        $stmt = $pdo->prepare("SELECT quiz_total_questions FROM quiz_table WHERE quiz_id = ?");
        $stmt->execute([$quiz_id]);
        $total_allowed = $stmt->fetch(PDO::FETCH_ASSOC)['quiz_total_questions'];

        if ($current_count >= $total_allowed) {
            $_SESSION['error'] = "Cannot add more questions. Quiz limit reached ({$total_allowed} questions maximum)!";
            header('Location: manage_quiz_questions.php?quiz_id=' . $quiz_id);
            exit();
        }

        // Insert question into question_table
        $stmt = $pdo->prepare("INSERT INTO question_table (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_answer, marks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$quiz_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $marks])) {
            $_SESSION['success'] = "Question added successfully!";
        } else {
            $_SESSION['error'] = "Failed to add question!";
        }
        
        header('Location: manage_quiz_questions.php?quiz_id=' . $quiz_id);
        exit();
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        header('Location: manage_quiz_questions.php?quiz_id=' . $quiz_id);
        exit();
    }
}
?>