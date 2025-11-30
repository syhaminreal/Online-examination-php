<?php
session_start();
require_once 'db_connection.php';

if (isset($_GET['quiz_id'])) {
    $quiz_id = $_GET['quiz_id'];
    
    try {
        // First delete all questions related to this quiz
        $stmt = $pdo->prepare("DELETE FROM question_table WHERE quiz_id = ?");
        $stmt->execute([$quiz_id]);
        
        // Then delete the quiz
        $stmt = $pdo->prepare("DELETE FROM quiz_table WHERE quiz_id = ?");
        
        if ($stmt->execute([$quiz_id])) {
            $_SESSION['success'] = "Quiz deleted successfully!";
        } else {
            $_SESSION['error'] = "Failed to delete quiz!";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
    }
    
    header('Location: admin_quiz.php');
    exit();
}
?>