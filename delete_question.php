<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

require_once 'db_connection.php';

if (isset($_GET['question_id']) && isset($_GET['quiz_id'])) {
    $question_id = $_GET['question_id'];
    $quiz_id = $_GET['quiz_id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM question_table WHERE question_id = ?");
        if ($stmt->execute([$question_id])) {
            $_SESSION['success'] = "Question deleted successfully!";
        } else {
            $_SESSION['error'] = "Failed to delete question!";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
    }
    
    header('Location: manage_quiz_questions.php?quiz_id=' . $quiz_id);
    exit();
}
?>