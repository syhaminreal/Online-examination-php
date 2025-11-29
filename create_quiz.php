<?php
session_start();
require_once 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $quiz_title = $_POST['quiz_title'];
        $quiz_description = $_POST['quiz_description'];
        $quiz_duration_minutes = $_POST['quiz_duration_minutes'];
        $quiz_total_questions = $_POST['quiz_total_questions'];
        $quiz_marks_per_question = $_POST['quiz_marks_per_question'];

        // Insert quiz into quiz_table
        $stmt = $pdo->prepare("INSERT INTO quiz_table (quiz_title, quiz_description, quiz_duration_minutes, quiz_total_questions, quiz_marks_per_question) VALUES (?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$quiz_title, $quiz_description, $quiz_duration_minutes, $quiz_total_questions, $quiz_marks_per_question])) {
            $_SESSION['success'] = "Quiz created successfully!";
            header('Location: manage_quiz_questions.php?quiz_id=' . $pdo->lastInsertId());
            exit();
        } else {
            $_SESSION['error'] = "Failed to create quiz!";
            header('Location: admin_quiz.php');
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        header('Location: admin_quiz.php');
        exit();
    }
} else {
    // If not POST request, redirect to admin page
    header('Location: admin_quiz.php');
    exit();
}
?>