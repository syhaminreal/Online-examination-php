<?php
// user_ajax_action.php

include('master/Examination.php');
require_once('class/class.phpmailer.php');

$exam = new Examination;
$current_datetime = date("Y-m-d") . ' ' . date("H:i:s", STRTOTIME(date('h:i:sa')));

// Start output buffering so we can detect stray HTML/PHP warnings
ob_start();

register_shutdown_function(function() {
    $buf = ob_get_clean();

    // If there's no output, nothing to do
    if ($buf === null || $buf === '') {
        return;
    }

    // Trim whitespace
    $trimmed = trim($buf);

    // If the output is valid JSON, print it and finish
    json_decode($trimmed);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo $trimmed;
        return;
    }

    // Otherwise write debug info to a log for inspection and return a safe JSON error
    $log_file = __DIR__ . '/ajax_debug.log';
    $request_dump = [];
    if (!empty($_REQUEST)) $request_dump['request'] = $_REQUEST;
    if (!empty($_FILES)) $request_dump['files'] = array_keys($_FILES);
    $entry = "[".date('Y-m-d H:i:s')."] Non-JSON response\n";
    $entry .= "REQUEST: " . json_encode($request_dump) . "\n";
    $entry .= "RESPONSE:\n" . $buf . "\n\n";
    // attempt to append to log (ignore failures)
    @file_put_contents($log_file, $entry, FILE_APPEND);

    if (!headers_sent()) {
        header('Content-Type: application/json', true, 500);
    }
    echo json_encode(['error' => 'Server error, see ajax_debug.log for details']);
});

if (isset($_POST['page'])) {

    /* =========================
       USER REGISTRATION
    ========================== */
    if ($_POST['page'] == 'register') {
        if (isset($_POST['action']) && $_POST['action'] == 'check_email') {
            $email = trim($_POST['email']);
            $exam->data = array(':user_email_address' => $email);
            $exam->query = "SELECT * FROM user_table WHERE user_email_address = :user_email_address";
            $total_row = $exam->total_row();

            if ($total_row == 0) {
                echo json_encode(array('success' => true));
            } else {
                // return a JSON response indicating the email is already taken
                echo json_encode(array('error' => 'Email already exists'));
            }
            exit;
        }

        if (isset($_POST['action']) && $_POST['action'] == 'register') {
            // Handle file upload if provided
            $user_image = '';
            if (isset($_FILES['user_image']) && $_FILES['user_image']['name'] != '') {
                $extension = pathinfo($_FILES['user_image']['name'], PATHINFO_EXTENSION);
                $user_image = uniqid() . '.' . $extension;
                move_uploaded_file($_FILES['user_image']['tmp_name'], 'upload/' . $user_image);
            }

            $user_verification_code = md5(rand());

            $exam->data = array(
                ':user_name' => $_POST['user_name'],
                ':user_email_address' => $_POST['user_email_address'],
                ':user_password' => password_hash($_POST['user_password'], PASSWORD_DEFAULT),
                ':user_gender' => $_POST['user_gender'],
                ':user_address' => $_POST['user_address'],
                ':user_mobile_no' => $_POST['user_mobile_no'],
                ':user_image' => $user_image,
                ':user_verification_code' => $user_verification_code,
                ':user_created_on' => $current_datetime
            );

            $exam->query = "
            INSERT INTO user_table 
            (user_name, user_email_address, user_password, user_gender, user_address, user_mobile_no, user_image, user_verification_code, user_created_on)
            VALUES (:user_name, :user_email_address, :user_password, :user_gender, :user_address, :user_mobile_no, :user_image, :user_verification_code, :user_created_on)
            ";

            $exam->execute_query();

            // send verification email
            $receiver_email = $_POST['user_email_address'];
            $subject = 'User Registration Verification';
            $body = '<p>Thank you for registering.</p>
            <p>Please verify your email by clicking this <a href="' . $exam->home_page . 'verify_email.php?type=user&code=' . $user_verification_code . '" target="_blank">link</a>.</p>';

            $exam->send_email($receiver_email, $subject, $body);

            echo json_encode(array('success' => true));
            exit;
        }
    }

    /* =========================
       USER LOGIN LOGIC
    ========================== */
    if ($_POST['page'] == 'login' && $_POST['action'] == 'login') {
        $output = array();

        $email = trim($_POST['user_email_address']);
        $password = trim($_POST['user_password']);

        // Prepare SQL to check email
        $exam->data = array(':user_email_address' => $email);
        $exam->query = "SELECT * FROM user_table WHERE user_email_address = :user_email_address";
        $result = $exam->query_result();

        if ($exam->total_row() > 0) {
            foreach ($result as $row) {
                if ($row['user_email_verified'] == 'yes') {
                    // ✅ Verify password (use password_hash during registration)
                    if (password_verify($password, $row['user_password'])) {
                        $_SESSION['user_id'] = $row['user_id'];
                        $_SESSION['user_name'] = $row['user_name'];
                        $_SESSION['user_email'] = $row['user_email_address'];
                        $output['success'] = true;
                    } else {
                        $output['error'] = 'Incorrect password. Please try again.';
                    }
                } else {
                    $output['error'] = 'Please verify your email before logging in.';
                }
            }
        } else {
            $output['error'] = 'Invalid email address.';
        }

        echo json_encode($output);
        exit;
    }

    /* =========================
       USER PROFILE UPDATE
    ========================== */
    if ($_POST['page'] == 'profile' && $_POST['action'] == 'profile') {
        $output = array();
        
        // Handle file upload if new image is provided
        $user_image = $_POST['hidden_user_image'];
        if (isset($_FILES['user_image']) && $_FILES['user_image']['name'] != '') {
            // Check file size (2MB limit)
            if ($_FILES['user_image']['size'] > 2097152) {
                $output['error'] = 'File size too large. Maximum 2MB allowed.';
                echo json_encode($output);
                exit;
            }
            
            $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif');
            $extension = strtolower(pathinfo($_FILES['user_image']['name'], PATHINFO_EXTENSION));
            
            if (!in_array($extension, $allowed_extensions)) {
                $output['error'] = 'Invalid file type. Only JPG, JPEG, PNG, GIF allowed.';
                echo json_encode($output);
                exit;
            }
            
            // Delete old image if it exists and is not default
            if ($user_image != '' && file_exists('upload/' . $user_image)) {
                unlink('upload/' . $user_image);
            }
            
            $user_image = uniqid() . '.' . $extension;
            move_uploaded_file($_FILES['user_image']['tmp_name'], 'upload/' . $user_image);
        }

        $exam->data = array(
            ':user_name' => $_POST['user_name'],
            ':user_gender' => $_POST['user_gender'],
            ':user_address' => $_POST['user_address'],
            ':user_mobile_no' => $_POST['user_mobile_no'],
            ':user_image' => $user_image,
            ':user_id' => $_SESSION['user_id']
        );

        $exam->query = "
        UPDATE user_table 
        SET user_name = :user_name, 
            user_gender = :user_gender, 
            user_address = :user_address, 
            user_mobile_no = :user_mobile_no, 
            user_image = :user_image 
        WHERE user_id = :user_id
        ";

        if ($exam->execute_query()) {
            // Update session data
            $_SESSION['user_name'] = $_POST['user_name'];
            $output['success'] = true;
        } else {
            $output['error'] = 'Failed to update profile. Please try again.';
        }

        echo json_encode($output);
        exit;
    }

    /* =========================
       FETCH EXAM LIST (for DataTable)
    ========================== */
    if ($_POST['page'] == 'enroll_exam' && $_POST['action'] == 'fetch') {
        $output = array();

        $exam->query = "
        SELECT 
            online_exam_table.*,
            CASE 
                WHEN user_exam_enroll_table.exam_id IS NOT NULL THEN 'Enrolled'
                ELSE 'Not Enrolled'
            END as enrollment_status
        FROM online_exam_table 
        LEFT JOIN user_exam_enroll_table 
            ON online_exam_table.online_exam_id = user_exam_enroll_table.exam_id 
            AND user_exam_enroll_table.user_id = '" . $_SESSION['user_id'] . "'
        WHERE 1=1
        ";

        // Search
        if (isset($_POST["search"]["value"])) {
            $exam->query .= '
            AND (
                online_exam_table.online_exam_title LIKE "%' . $_POST["search"]["value"] . '%" 
                OR online_exam_table.online_exam_datetime LIKE "%' . $_POST["search"]["value"] . '%"
            )';
        }

        // Sorting
        if (isset($_POST["order"])) {
            $exam->query .= '
            ORDER BY ' . (1 + $_POST['order']['0']['column']) . ' ' . $_POST['order']['0']['dir'] . ' 
            ';
        } else {
            $exam->query .= ' ORDER BY online_exam_table.online_exam_datetime ASC ';
        }

        $extra_query = '';
        if ($_POST["length"] != -1) {
            $extra_query .= 'LIMIT ' . $_POST['start'] . ', ' . $_POST['length'];
        }

        // Get filtered and total counts
        $filtered_query = $exam->query;
        $exam->query = $filtered_query;
        $filtered_rows = $exam->total_row();

        $exam->query = $filtered_query . $extra_query;
        $result = $exam->query_result();

        $exam->query = "SELECT COUNT(*) as total FROM online_exam_table";
        $total_rows = $exam->total_row();

        $data = array();

        foreach ($result as $row) {
            $sub_array = array();
            $sub_array[] = html_entity_decode($row["online_exam_title"]);
            $sub_array[] = $row["online_exam_datetime"];
            $sub_array[] = $row["online_exam_duration"] . ' Minute';
            $sub_array[] = $row["total_question"] . ' Question';
            $sub_array[] = $row["marks_per_right_answer"] . ' Mark';
            $sub_array[] = '-' . $row["marks_per_wrong_answer"] . ' Mark';
            $sub_array[] = $row["enrollment_status"];
            $sub_array[] = $row["online_exam_status"];
            $sub_array[] = $row["online_exam_code"];
            $sub_array[] = $row["online_exam_id"];

            // Previously this code compared server/ client local dates to decide if an exam
            // was active for the current day; scheduling-by-date has been removed so
            // exams are considered active for enrolled users regardless of date.
            $is_active = true; // always mark active (schedule date no longer enforced)
            $can_take = ($row["enrollment_status"] == 'Enrolled');

            $sub_array[] = $is_active ? '1' : '0';
            $sub_array[] = $can_take ? '1' : '0';
            $data[] = $sub_array;
        }

        $output = array(
            "draw" => intval($_POST["draw"]),
            "recordsTotal" => $total_rows,
            "recordsFiltered" => $filtered_rows,
            "data" => $data
        );

        echo json_encode($output);
        exit;
    }

    /* =========================
       CHECK EXAM SCHEDULE (AJAX helper)
    ========================== */
    if ($_POST['page'] == 'enroll_exam' && isset($_POST['action']) && $_POST['action'] == 'check_schedule') {
        // Scheduling-by-date removed: always allow taking exam when requested.
        $exam_id = intval($_POST['exam_id']);
        $exam->query = "SELECT online_exam_datetime FROM online_exam_table WHERE online_exam_id = :exam_id LIMIT 1";
        $exam->data = array(':exam_id' => $exam_id);
        $res = $exam->query_result();
        if (empty($res)) {
            echo json_encode(['success' => false, 'error' => 'Exam not found']);
            exit;
        }
        $scheduled = $res[0]['online_exam_datetime'];
        $scheduled_date = date('Y-m-d', strtotime($scheduled));
        // Always allow; include scheduled_date for UI information only.
        echo json_encode(['success' => true, 'allowed' => true, 'scheduled_date' => $scheduled_date]);
        exit;
    }

    /* =========================
       ENROLL IN EXAM
    ========================== */
    if ($_POST['page'] == 'index' && $_POST['action'] == 'enroll_exam') {
        $exam_id = intval($_POST['exam_id']);
        $user_id = $_SESSION['user_id'];

        // Check if user is already enrolled to prevent duplicates
        $exam->query = "
        SELECT * FROM user_exam_enroll_table 
        WHERE user_id = :user_id AND exam_id = :exam_id LIMIT 1
        ";
        $exam->data = array(':user_id' => $user_id, ':exam_id' => $exam_id);
        $existing = $exam->query_result();

        if (!empty($existing)) {
            // Already enrolled, return success (idempotent)
            echo json_encode(['success' => true, 'message' => 'Already enrolled']);
            exit;
        }

        // Proceed with enrollment
        $exam->data = array(
            ':user_id' => $user_id,
            ':exam_id' => $exam_id
        );

        $exam->query = "
        INSERT INTO user_exam_enroll_table (user_id, exam_id) 
        VALUES (:user_id, :exam_id)
        ";
        $exam->execute_query();

        // Insert default question answers for this user
        $exam->query = "
        SELECT question_id FROM question_table 
        WHERE online_exam_id = '" . $exam_id . "'
        ";
        $result = $exam->query_result();

        foreach ($result as $row) {
            // Check if answer already exists before inserting
            $exam->query = "
            SELECT * FROM user_exam_question_answer 
            WHERE user_id = :user_id AND exam_id = :exam_id AND question_id = :question_id LIMIT 1
            ";
            $exam->data = array(
                ':user_id' => $user_id,
                ':exam_id' => $exam_id,
                ':question_id' => $row['question_id']
            );
            $answer_exists = $exam->query_result();

            if (empty($answer_exists)) {
                $exam->data = array(
                    ':user_id' => $user_id,
                    ':exam_id' => $exam_id,
                    ':question_id' => $row['question_id'],
                    ':user_answer_option' => '0',
                    ':marks' => '0'
                );

                $exam->query = "
                INSERT INTO user_exam_question_answer 
                (user_id, exam_id, question_id, user_answer_option, marks) 
                VALUES (:user_id, :exam_id, :question_id, :user_answer_option, :marks)
                ";
                $exam->execute_query();
            }
        }

        echo json_encode(['success' => true]);
        exit;
    }
}
?>