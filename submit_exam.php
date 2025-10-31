<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required class
include_once('master/Examination.php');
$exam = new Examination;

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$exam_id = $input['exam_id'] ?? '';
$answers = $input['answers'] ?? [];
// optional tz_name (IANA) from Intl API, or tz_offset in minutes from JS getTimezoneOffset()
$tz_name = isset($input['tz_name']) ? trim($input['tz_name']) : null;
$tz_offset = isset($input['tz_offset']) ? intval($input['tz_offset']) : null;

// Validate input
if (empty($exam_id) || empty($answers)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

// Enforce scheduling: only allow submission on the scheduled date for the exam
$exam->query = "SELECT online_exam_datetime FROM online_exam_table WHERE online_exam_id = :exam_id LIMIT 1";
$exam->data = array(':exam_id' => $exam_id);
$exam_info = $exam->query_result();
if (empty($exam_info)) {
    echo json_encode(['success' => false, 'message' => 'Exam not found']);
    exit;
}
$scheduled_dt = $exam_info[0]['online_exam_datetime'];

// Prefer IANA tz_name when provided (accurate across DST). Fallback to tz_offset, then server date.
if (!empty($tz_name)) {
    try {
        // Assume stored scheduled datetime is in server timezone
        $serverTz = new DateTimeZone(date_default_timezone_get());
        $dt = new DateTime($scheduled_dt, $serverTz);
        $userTz = new DateTimeZone($tz_name);
        $dt->setTimezone($userTz);
        $scheduled_local_date = $dt->format('Y-m-d');

        $now = new DateTime('now', $userTz);
        $current_date = $now->format('Y-m-d');

        if ($scheduled_local_date !== $current_date) {
            // If user's local date doesn't match, allow when server's scheduled date equals server current date
            $server_scheduled_date = date('Y-m-d', strtotime($scheduled_dt));
            if ($server_scheduled_date === date('Y-m-d')) {
                // allow submission (use server date for display)
                // no-op (proceed)
            } else {
                $readable = $dt->format('F j, Y');
                echo json_encode(['success' => false, 'message' => 'This exam is scheduled for ' . $readable . '. You cannot submit now.']);
                exit;
            }
        }
    } catch (Exception $e) {
        // invalid tz_name provided; fall through to next checks
    }
} 

// If we reach here, either tz_name wasn't provided/valid, try tz_offset fallback
if ($tz_offset !== null) {
    // compute user's local dates using offset (minutes)
    $user_now_ts = time() - ($tz_offset * 60);
    $current_date = date('Y-m-d', $user_now_ts);
    $scheduled_local_date = date('Y-m-d', strtotime($scheduled_dt) - ($tz_offset * 60));
    if ($scheduled_local_date !== $current_date) {
        echo json_encode(['success' => false, 'message' => 'This exam is scheduled for ' . date('F j, Y', strtotime($scheduled_local_date)) . '. You cannot submit now.']);
        exit;
    }
} else {
    // Last resort: compare server-local dates
    $scheduled_date = date('Y-m-d', strtotime($scheduled_dt));
    $current_date = date('Y-m-d');
    if ($scheduled_date !== $current_date) {
        echo json_encode(['success' => false, 'message' => 'This exam is scheduled for ' . date('F j, Y', strtotime($scheduled_date)) . '. You cannot submit now.']);
        exit;
    }
}

try {
    // Get correct answers from database
    $exam->query = "
        SELECT question_id, answer_option 
        FROM question_table 
        WHERE online_exam_id = '$exam_id'
    ";
    $correct_answers = $exam->query_result();
    
    // Calculate results
    $total_questions = count($correct_answers);
    $correct_count = 0;
    $total_marks = 0;
    
    foreach ($correct_answers as $answer) {
        $question_id = $answer['question_id'];
        $user_answer = $answers["question_$question_id"] ?? null;
        
        if ($user_answer == $answer['answer_option']) {
            $correct_count++;
            $marks = 1; // Add positive mark for correct answer
        } else {
            $marks = 0; // No negative marking
        }
        
        // Save user's answer
        $exam->query = "
            INSERT INTO user_exam_question_answer 
            (user_id, exam_id, question_id, user_answer_option, marks) 
            VALUES (
                '" . $_SESSION['user_id'] . "',
                '$exam_id',
                '$question_id',
                '" . ($user_answer ?? '') . "',
                '$marks'
            )
        ";
        $exam->execute_query();
        
        $total_marks += $marks;
    }
    
    // Calculate percentage
    $percentage = ($total_marks / $total_questions) * 100;
    
    // Update exam status
    $exam->query = "
        UPDATE online_exam_table 
        SET online_exam_status = 'Completed'
        WHERE online_exam_id = '$exam_id'
    ";
    $exam->execute_query();

    // Update or insert aggregated results
    $exam->query = "
        INSERT INTO user_exam_result 
        (exam_id, user_id, total_mark, total_possible, percentage, attendance_status, created_on, updated_on)
        VALUES (
            :exam_id,
            :user_id,
            :total_mark,
            :total_possible,
            :percentage,
            'Present',
            NOW(),
            NOW()
        )
        ON DUPLICATE KEY UPDATE
            total_mark = VALUES(total_mark),
            total_possible = VALUES(total_possible),
            percentage = VALUES(percentage),
            attendance_status = VALUES(attendance_status),
            updated_on = VALUES(updated_on)
    ";
    $exam->data = array(
        ':exam_id' => $exam_id,
        ':user_id' => $_SESSION['user_id'],
        ':total_mark' => $total_marks,
        ':total_possible' => $total_questions,
        ':percentage' => round($percentage, 2)
    );
    $exam->execute_query();
    
    // Prepare results
    $results = [
        'total' => $total_questions,
        'correct' => $correct_count,
        'marks' => $total_marks,
        'percentage' => round($percentage, 2)
    ];
    
    echo json_encode([
        'success' => true,
        'results' => $results
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error processing exam: ' . $e->getMessage()
    ]);
}
?>