<?php
// create_question_table.php

require_once __DIR__ . '/db_connection.php';

try {
    // Drop old table
    $pdo->exec("DROP TABLE IF EXISTS question_table");

    // Ensure parent table uses InnoDB
    $pdo->exec("ALTER TABLE online_exam_table ENGINE=InnoDB");

    // Create correct question table
    $pdo->exec("
        CREATE TABLE question_table (
            question_id INT PRIMARY KEY AUTO_INCREMENT,

            quiz_id INT NOT NULL,

            question_text TEXT NOT NULL,

            option_a VARCHAR(500) NOT NULL,
            option_b VARCHAR(500) NOT NULL,
            option_c VARCHAR(500) NOT NULL,
            option_d VARCHAR(500) NOT NULL,

            correct_answer CHAR(1) NOT NULL,

            marks INT DEFAULT 1,

            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            CONSTRAINT fk_question_quiz
                FOREIGN KEY (quiz_id)
                REFERENCES online_exam_table(online_exam_id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");

    echo "✅ Question table created successfully!<br>";
    echo '<a href="admin_quiz.php">Go to Admin Panel</a>';

} catch (PDOException $e) {
    echo "❌ Error creating question table: " . $e->getMessage();
}
?>
