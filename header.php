<?php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$current_script = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if ($current_script === '') {
    $current_script = 'index.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Online Examination System in PHP</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.19/css/dataTables.bootstrap4.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/guillaumepotier/Parsley.js@2.9.1/dist/parsley.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap4.min.js"></script>
    <link rel="stylesheet" href="style/style.css" />
    <link rel="stylesheet" href="style/TimeCircles.css" />
    <link rel="stylesheet" href="style/footer.css" />
    <link rel="stylesheet" href="style/nav.css" />
    <script src="style/TimeCircles.js"></script>
</head>
<body style="font-family: Inter, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial;">

<?php if(isset($_SESSION['user_id'])): ?>
    <!-- Logged-in user navigation -->
    <header class="nav user-nav" role="banner">
        <a href="index.php" class="logo" style="text-decoration: none; color: inherit;">
            <img src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?q=80&w=400&auto=format&fit=crop&ixlib=rb-4.0.3&s=2f8c2f8b2c2b7b1f" alt="ExamPro">
            <span>ExamPro</span>
        </a>
        <nav class="nav-links" role="navigation" aria-label="User navigation">
            <a class="btn secondary <?php echo ($current_script === 'examination_center.php') ? 'active' : ''; ?>" href="examination_center.php">Exam Center</a>
            <a class="btn secondary <?php echo ($current_script === 'enroll_exam.php') ? 'active' : ''; ?>" href="enroll_exam.php">Enroll Exam</a>
            <a class="btn secondary <?php echo ($current_script === 'profile.php') ? 'active' : ''; ?>" href="profile.php">Profile</a>
            <a class="btn secondary <?php echo ($current_script === 'change_password.php') ? 'active' : ''; ?>" href="change_password.php">Change Password</a>
            <a class="btn" href="logout.php">Logout</a>
        </nav>
    </header>
<?php else: ?>
    <!-- Public navigation -->
    <header class="nav" role="banner">
        <a href="index.php" class="logo" style="text-decoration: none; color: inherit;">
            <img src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?q=80&w=400&auto=format&fit=crop&ixlib=rb-4.0.3&s=2f8c2f8b2c2b7b1f" alt="ExamPro">
            <span>ExamPro</span>
        </a>
        <nav class="nav-links" role="navigation" aria-label="Main navigation">
            <a class="btn secondary <?php echo ($current_script === 'examination_center.php') ? 'active' : ''; ?>" href="examination_center.php">Exam Center</a>
            <a class="btn secondary" href="#about">About</a>
            <a class="btn secondary" href="#uses">Features</a>
            <a class="btn <?php echo ($current_script === 'login.php') ? 'active' : ''; ?>" href="login.php">Login</a>
            <a class="btn <?php echo ($current_script === 'register.php') ? 'active' : ''; ?>" href="register.php">Register</a>
        </nav>
    </header>
<?php endif; ?>

<?php if(isset($_SESSION['user_id'])): ?>
    <div class="container-fluid">
<?php endif; ?>