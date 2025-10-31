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

            // Compute exam active status based on local-day equality (user's local date equals scheduled local date)
            $is_active = false;
            $can_take = false;

            $scheduled_datetime = $row["online_exam_datetime"];
            $user_current_date = null;
            $scheduled_local_date = null;

            // Prefer tz_name if provided in request
            if (isset($_POST['tz_name']) && is_string($_POST['tz_name']) && $_POST['tz_name'] !== '') {
                $tz_name = $_POST['tz_name'];
                try {
                    $user_tz = new DateTimeZone($tz_name);
                    $server_tz = new DateTimeZone(date_default_timezone_get());
                    $scheduled_dt = new DateTime($scheduled_datetime, $server_tz);
                    $scheduled_dt->setTimezone($user_tz);
                    $scheduled_local_date = $scheduled_dt->format('Y-m-d');

                    $user_now = new DateTime('now', $user_tz);
                    $user_current_date = $user_now->format('Y-m-d');
                } catch (Exception $e) {
                    // invalid tz_name; reset to null to use fallback
                    $user_current_date = null;
                    $scheduled_local_date = null;
                }
            }

            // Fallback to tz_offset if tz_name not available or invalid
            if ($user_current_date === null) {
                $tz_offset = isset($_POST['tz_offset']) ? intval($_POST['tz_offset']) : null;
                if ($tz_offset !== null) {
                    $user_now_ts = time() - ($tz_offset * 60);
                    $user_current_date = date('Y-m-d', $user_now_ts);
                    $scheduled_local_date = date('Y-m-d', strtotime($scheduled_datetime) - ($tz_offset * 60));
                }
            }

            // Last resort: server-local date comparison
            if ($user_current_date === null) {
                $user_current_date = date('Y-m-d');
                $scheduled_local_date = date('Y-m-d', strtotime($scheduled_datetime));
            }

            if ($scheduled_local_date === $user_current_date) {
                $is_active = true;
            }

            if ($row["enrollment_status"] == 'Enrolled' && $is_active) {
                $can_take = true;
            }

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
        $exam_id = intval($_POST['exam_id']);
        $exam->query = "SELECT online_exam_datetime FROM online_exam_table WHERE online_exam_id = :exam_id LIMIT 1";
        $exam->data = array(':exam_id' => $exam_id);
        $res = $exam->query_result();
        if (empty($res)) {
            echo json_encode(['success' => false, 'error' => 'Exam not found']);
            exit;
        }
        $scheduled = $res[0]['online_exam_datetime'];

        // Prefer IANA timezone name if provided
        if (isset($_POST['tz_name']) && is_string($_POST['tz_name']) && $_POST['tz_name'] !== '') {
            $tz_name = $_POST['tz_name'];
            try {
                $user_tz = new DateTimeZone($tz_name);
                // Create DateTime from scheduled string using server timezone, then convert to user's timezone
                $server_tz = new DateTimeZone(date_default_timezone_get());
                $scheduled_dt = new DateTime($scheduled, $server_tz);
                $scheduled_dt->setTimezone($user_tz);
                $scheduled_local_date = $scheduled_dt->format('Y-m-d');

                $user_now = new DateTime('now', $user_tz);
                $current_date = $user_now->format('Y-m-d');

                $allowed = ($scheduled_local_date === $current_date);
                // fallback: if server's scheduled date equals server's current date, allow as well
                if (!$allowed) {
                    $server_scheduled_date = date('Y-m-d', strtotime($scheduled));
                    if ($server_scheduled_date === date('Y-m-d')) {
                        $allowed = true;
                        $scheduled_local_date = $server_scheduled_date;
                    }
                }
                echo json_encode(['success' => true, 'allowed' => $allowed, 'scheduled_date' => $scheduled_local_date]);
                exit;
            } catch (Exception $e) {
                // invalid timezone, fall through to offset or server comparison
            }
        }

        // If client provided tz_offset (minutes from getTimezoneOffset), compute user's local date as fallback
        $tz_offset = isset($_POST['tz_offset']) ? intval($_POST['tz_offset']) : null;
        if ($tz_offset !== null) {
            // JS getTimezoneOffset() returns minutes to add to local time to get UTC
            // To get user's local timestamp from UTC timestamp: user_local = time() - (tz_offset*60)
            $user_now_ts = time() - ($tz_offset * 60);
            $current_date = date('Y-m-d', $user_now_ts);

            // Convert scheduled datetime to timestamp (absolute) and then to user's local date
            $scheduled_ts = strtotime($scheduled);
            $scheduled_local_date = date('Y-m-d', $scheduled_ts - ($tz_offset * 60));

            $allowed = ($scheduled_local_date === $current_date);
            // fallback: allow if server-side scheduled date equals server date
            if (!$allowed) {
                $server_scheduled_date = date('Y-m-d', strtotime($scheduled));
                if ($server_scheduled_date === date('Y-m-d')) {
                    $allowed = true;
                    $scheduled_local_date = $server_scheduled_date;
                }
            }
            echo json_encode(['success' => true, 'allowed' => $allowed, 'scheduled_date' => $scheduled_local_date]);
            exit;
        }

        // Fallback: use server date comparison
        $scheduled_date = date('Y-m-d', strtotime($scheduled));
        $current_date = date('Y-m-d');
        $allowed = ($scheduled_date === $current_date);
        echo json_encode(['success' => true, 'allowed' => $allowed, 'scheduled_date' => $scheduled_date]);
        exit;
    }

    /* =========================
       ENROLL IN EXAM
    ========================== */
    if ($_POST['page'] == 'index' && $_POST['action'] == 'enroll_exam') {
        $exam->data = array(
            ':user_id' => $_SESSION['user_id'],
            ':exam_id' => $_POST['exam_id']
        );

        $exam->query = "
        INSERT INTO user_exam_enroll_table (user_id, exam_id) 
        VALUES (:user_id, :exam_id)
        ";
        $exam->execute_query();

        // Insert default question answers for this user
        $exam->query = "
        SELECT question_id FROM question_table 
        WHERE online_exam_id = '" . $_POST['exam_id'] . "'
        ";
        $result = $exam->query_result();

        foreach ($result as $row) {
            $exam->data = array(
                ':user_id' => $_SESSION['user_id'],
                ':exam_id' => $_POST['exam_id'],
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

        echo json_encode(['success' => true]);
        exit;
    }
}
?>
