<?php
// create_result_tables.php
require_once 'db_connection.php';

try {
    // Create user_exam_question_answer table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_exam_question_answer (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        exam_id INT NOT NULL,
        question_id INT NOT NULL,
        user_answer VARCHAR(10),
        marks_obtained INT DEFAULT 0,
        answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Create user_exam_result table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_exam_result (
        result_id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        exam_id INT NOT NULL,
        total_questions INT NOT NULL,
        total_marks INT NOT NULL,
        marks_obtained INT NOT NULL,
        percentage DECIMAL(5,2) NOT NULL,
        exam_status ENUM('Completed', 'In Progress') DEFAULT 'Completed',
        completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    echo "Result tables created successfully!<br>";
    echo '<a href="user_quizzes.php">Go to Quizzes</a>';
    
} catch (PDOException $e) {
    echo "Error creating result tables: " . $e->getMessage();
}
?>