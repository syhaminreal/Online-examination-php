<?php

class Examination
{
    var $host;
    var $username;
    var $password;
    var $database;
    var $connect;
    var $home_page;
    var $query;
    var $data = [];
    var $statement;
    var $filedata;

    function __construct()
    {
        $this->host = 'localhost';
        $this->username = 'root';
        $this->password = '';
        $this->database = 'online_examination';
        $this->home_page = 'http://localhost/new by sam/';

        $this->connect = new PDO(
            "mysql:host=$this->host;dbname=$this->database",
            $this->username,
            $this->password,
            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
        );

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /* ---------------------------------------------
       EXECUTE QUERY - FIXED (CLEAR PARAMS ALWAYS)
    --------------------------------------------- */
    function execute_query()
    {
        $this->statement = $this->connect->prepare($this->query);

        try {
            if (!empty($this->data)) {
                $this->statement->execute($this->data);
            } else {
                $this->statement->execute();
            }
        } catch (PDOException $e) {
            if ($e->getCode() === 'HY093') {
                $this->statement->execute();
            } else {
                throw $e;
            }
        }

        // FIX: Clear parameters after each query
        $this->data = [];
    }

    /* --------------------------------------------- */

    function total_row()
    {
        $this->execute_query();
        return $this->statement->rowCount();
    }

    function query_result()
    {
        $this->execute_query();
        return $this->statement->fetchAll();
    }

    /* ---------------------------------------------
       EXAM STATUS CHECKS
    --------------------------------------------- */

    function Is_exam_is_not_started($online_exam_id)
    {
        $current_datetime = date("Y-m-d H:i:s");

        $this->query = "
            SELECT online_exam_datetime 
            FROM online_exam_table
            WHERE online_exam_id = '$online_exam_id'
        ";

        $result = $this->query_result();
        foreach ($result as $row) {
            if ($row['online_exam_datetime'] > $current_datetime) {
                return true;
            }
        }

        return false;
    }

    function Get_exam_question_limit($exam_id)
    {
        $this->query = "
            SELECT total_question 
            FROM online_exam_table
            WHERE online_exam_id = '$exam_id'
        ";
        $result = $this->query_result();
        return $result[0]['total_question'] ?? 0;
    }

    function Get_exam_total_question($exam_id)
    {
        $this->query = "
            SELECT question_id 
            FROM question_table 
            WHERE online_exam_id = '$exam_id'
        ";
        return $this->total_row();
    }

    function Is_allowed_add_question($exam_id)
    {
        return $this->Get_exam_total_question($exam_id) < $this->Get_exam_question_limit($exam_id);
    }

    function execute_question_with_last_id()
    {
        $this->statement = $this->connect->prepare($this->query);

        try {
            if (!empty($this->data)) {
                $this->statement->execute($this->data);
            } else {
                $this->statement->execute();
            }
        } catch (PDOException $e) {
            if ($e->getCode() === 'HY093') {
                $this->statement->execute();
            } else {
                throw $e;
            }
        }

        $this->data = [];
        return $this->connect->lastInsertId();
    }

    function Get_exam_id($exam_code)
    {
        $this->query = "
            SELECT online_exam_id 
            FROM online_exam_table 
            WHERE online_exam_code = '$exam_code'
        ";
        $result = $this->query_result();
        return $result[0]['online_exam_id'] ?? null;
    }

    /* ---------------------------------------------
       FILE UPLOAD
    --------------------------------------------- */
    function Upload_file()
    {
        if (!empty($this->filedata['name'])) {
            $extension = pathinfo($this->filedata['name'], PATHINFO_EXTENSION);
            $new_name = uniqid() . '.' . $extension;
            move_uploaded_file($this->filedata['tmp_name'], 'upload/' . $new_name);
            return $new_name;
        }
    }

    /* ---------------------------------------------
       SESSION CHECKS
    --------------------------------------------- */

    function admin_session_private()
    {
        if (!isset($_SESSION['admin_id'])) {
            $this->redirect($this->home_page . 'master/login.php');
        }
    }

    function admin_session_public()
    {
        if (isset($_SESSION['admin_id'])) {
            $this->redirect($this->home_page . 'master/index.php');
        }
    }

    function user_session_private()
    {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('login.php');
        }
    }

    function user_session_public()
    {
        if (isset($_SESSION['user_id'])) {
            $this->redirect('index.php');
        }
    }

    /* ---------------------------------------------
       EXAM FUNCTIONS
    --------------------------------------------- */

    function Fill_exam_list()
    {
        $this->query = "
            SELECT online_exam_id, online_exam_title 
            FROM online_exam_table 
            WHERE online_exam_status IN ('Created', 'Pending')
            ORDER BY online_exam_title ASC
        ";

        $result = $this->query_result();
        $output = '';

        foreach ($result as $row) {
            $output .= '<option value="' . $row["online_exam_id"] . '">' . $row["online_exam_title"] . '</option>';
        }

        return $output;
    }

    function If_user_already_enroll_exam($exam_id, $user_id)
    {
        $this->query = "
            SELECT * 
            FROM user_exam_enroll_table 
            WHERE exam_id = '$exam_id'
            AND user_id = '$user_id'
        ";
        return $this->total_row() > 0;
    }

    function Change_exam_status($user_id)
    {
        $this->query = "
            SELECT * 
            FROM user_exam_enroll_table 
            INNER JOIN online_exam_table 
            ON online_exam_table.online_exam_id = user_exam_enroll_table.exam_id 
            WHERE user_exam_enroll_table.user_id = '$user_id'
        ";

        $result = $this->query_result();
        $current_datetime = date("Y-m-d H:i:s");

        foreach ($result as $row) {
            $exam_start_time = $row["online_exam_datetime"];
            $duration = $row["online_exam_duration"] . ' minute';
            $exam_end_time = date('Y-m-d H:i:s', strtotime($exam_start_time . '+' . $duration));

            if ($current_datetime >= $exam_start_time && $current_datetime <= $exam_end_time) {

                // Started
                $this->query = "
                    UPDATE online_exam_table 
                    SET online_exam_status = :status
                    WHERE online_exam_id = '{$row['online_exam_id']}'
                ";

                $this->data = [':status' => 'Started'];
                $this->execute_query();
            } 
            else if ($current_datetime > $exam_end_time) {

                // Completed
                $this->query = "
                    UPDATE online_exam_table 
                    SET online_exam_status = :status
                    WHERE online_exam_id = '{$row['online_exam_id']}'
                ";

                $this->data = [':status' => 'Completed'];
                $this->execute_query();
            }
        }
    }

    function Get_user_question_option($question_id, $user_id)
    {
        $this->query = "
            SELECT user_answer_option 
            FROM user_exam_question_answer
            WHERE question_id = '$question_id'
            AND user_id = '$user_id'
        ";
        $result = $this->query_result();
        return $result[0]['user_answer_option'] ?? null;
    }

    function Get_question_right_answer_mark($exam_id)
    {
        $this->query = "
            SELECT marks_per_right_answer 
            FROM online_exam_table
            WHERE online_exam_id = '$exam_id'
        ";
        $result = $this->query_result();
        return $result[0]['marks_per_right_answer'] ?? 0;
    }

    function Get_question_wrong_answer_mark($exam_id)
    {
        $this->query = "
            SELECT marks_per_wrong_answer 
            FROM online_exam_table
            WHERE online_exam_id = '$exam_id'
        ";
        $result = $this->query_result();
        return $result[0]['marks_per_wrong_answer'] ?? 0;
    }

    function Get_question_answer_option($question_id)
    {
        $this->query = "
            SELECT answer_option 
            FROM question_table
            WHERE question_id = '$question_id'
        ";
        $result = $this->query_result();
        return $result[0]['answer_option'] ?? null;
    }

    function Get_exam_status($exam_id)
    {
        $this->query = "
            SELECT online_exam_status 
            FROM online_exam_table
            WHERE online_exam_id = '$exam_id'
        ";
        $result = $this->query_result();
        return $result[0]['online_exam_status'] ?? null;
    }

    function Get_user_exam_status($exam_id, $user_id)
    {
        $this->query = "
            SELECT attendance_status 
            FROM user_exam_enroll_table 
            WHERE exam_id = '$exam_id'
            AND user_id = '$user_id'
        ";
        $result = $this->query_result();
        return $result[0]['attendance_status'] ?? null;
    }

    /* ---------------------------------------------
       STATISTICS
    --------------------------------------------- */

    function get_total_exams()
    {
        $this->query = "SELECT COUNT(*) AS total FROM online_exam_table";
        $result = $this->query_result();
        return $result[0]['total'] ?? 0;
    }

    function get_total_users()
    {
        $this->query = "SELECT COUNT(*) AS total FROM user_table";
        $result = $this->query_result();
        return $result[0]['total'] ?? 0;
    }

    function get_active_exams()
    {
        $this->query = "
            SELECT COUNT(*) AS total 
            FROM online_exam_table 
            WHERE online_exam_status = 'Started'
        ";
        $result = $this->query_result();
        return $result[0]['total'] ?? 0;
    }

    /* --------------------------------------------- */

    function redirect($page)
    {
        header('location:' . $page);
        exit;
    }
}

?>
