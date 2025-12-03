<?php 
session_start();
require_once 'db_connection.php';

// Sanitize input function
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

$error = '';
$success = '';

// Redirect if already logged in
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $user_type = sanitize($_POST['user_type']);
    $phone = sanitize($_POST['phone']);

    // Validation
    $errors = [];
    if (empty($full_name)) $errors[] = "Full name is required";
    if (empty($username)) $errors[] = "Username is required";
    if (empty($email)) $errors[] = "Email is required";
    if (empty($password)) $errors[] = "Password is required";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters";

    // Check username using PDO
    try {
        $check_username = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
        $check_username->execute([$username]);
        if ($check_username->rowCount() > 0) $errors[] = "Username already exists";

        // Check email
        $check_email = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $check_email->execute([$email]);
        if ($check_email->rowCount() > 0) $errors[] = "Email already registered";
    } catch(PDOException $e) {
        $errors[] = "Database error: " . $e->getMessage();
    }

    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    } else {
        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Insert new user using PDO
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name, user_type, phone) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$username, $email, $password_hash, $full_name, $user_type, $phone]);
            
            $success = "Registration successful! Redirecting to login...";
            header("refresh:2;url=login.php");
        } catch(PDOException $e) {
            $error = "Registration failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="dark-theme">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - ExamPro</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --accent: #3b82f6;
            --accent-hover: #2563eb;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border: #334155;
            --success: #10b981;
            --danger: #ef4444;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .register-container {
            width: 100%;
            max-width: 500px;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        
        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 1rem;
        }
        
        .logo-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--accent), var(--accent-hover));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
        }
        
        .logo-text {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--text-primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .register-header h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .register-header p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }
        
        .register-card {
            background: var(--bg-secondary);
            border-radius: 20px;
            padding: 2.5rem;
            border: 1px solid var(--border);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #6ee7b7;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group.full-width {
            grid-column: span 2;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .input-group {
            position: relative;
        }
        
        input, select {
            width: 100%;
            padding: 1rem;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        select option {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        
        .input-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            cursor: pointer;
        }
        
        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.85rem;
        }
        
        .strength-bar {
            height: 4px;
            background: var(--border);
            border-radius: 2px;
            margin-top: 0.25rem;
            overflow: hidden;
        }
        
        .strength-fill {
            height: 100%;
            width: 0%;
            transition: width 0.3s;
        }
        
        .strength-weak { background: var(--danger); }
        .strength-medium { background: #f59e0b; }
        .strength-strong { background: var(--success); }
        
        .btn-register {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--accent), var(--accent-hover));
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 1.5rem;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.4);
        }
        
        .terms-agreement {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 1.5rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .terms-agreement input {
            width: auto;
            margin-top: 3px;
        }
        
        .terms-agreement a {
            color: var(--accent);
            text-decoration: none;
        }
        
        .register-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .register-footer a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }
        
        @media (max-width: 480px) {
            .register-card {
                padding: 1.5rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-group.full-width {
                grid-column: span 1;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <span class="logo-text">ExamPro</span>
            </div>
            <h1>Create Account</h1>
            <p>Join thousands of users managing exams with ExamPro</p>
        </div>
        
        <div class="register-card">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="full_name">Full Name *</label>
                        <div class="input-group">
                            <input type="text" id="full_name" name="full_name" required>
                            <div class="input-icon">
                                <i class="fas fa-user"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <div class="input-group">
                            <input type="text" id="username" name="username" required>
                            <div class="input-icon">
                                <i class="fas fa-at"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <div class="input-group">
                        <input type="email" id="email" name="email" required>
                        <div class="input-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <div class="input-group">
                            <input type="password" id="password" name="password" required>
                            <div class="input-icon" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </div>
                        </div>
                        <div class="password-strength">
                            <span id="strength-text">Password strength</span>
                            <div class="strength-bar">
                                <div class="strength-fill" id="strength-fill"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password *</label>
                        <div class="input-group">
                            <input type="password" id="confirm_password" name="confirm_password" required>
                            <div class="input-icon" id="toggleConfirmPassword">
                                <i class="fas fa-eye"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="user_type">Account Type *</label>
                        <select id="user_type" name="user_type" required>
                            <option value="student" selected>Student</option>
                            <option value="teacher">Teacher</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <div class="input-group">
                            <input type="tel" id="phone" name="phone">
                            <div class="input-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="terms-agreement">
                    <input type="checkbox" id="terms" name="terms" required>
                    <label for="terms">
                        I agree to the <a href="terms.php">Terms of Service</a> and <a href="privacy.php">Privacy Policy</a>
                    </label>
                </div>
                
                <button type="submit" class="btn-register">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
            </form>
            
            <div class="register-footer">
                Already have an account? <a href="login.php">Sign in here</a>
            </div>
        </div>
    </div>
    
    <script>
        // Password strength indicator
        const passwordInput = document.getElementById('password');
        const strengthFill = document.getElementById('strength-fill');
        const strengthText = document.getElementById('strength-text');
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            // Length check
            if (password.length >= 8) strength += 25;
            // Lowercase check
            if (/[a-z]/.test(password)) strength += 25;
            // Uppercase check
            if (/[A-Z]/.test(password)) strength += 25;
            // Number/special char check
            if (/[0-9]/.test(password) || /[^A-Za-z0-9]/.test(password)) strength += 25;
            
            strengthFill.style.width = strength + '%';
            
            if (strength <= 25) {
                strengthFill.className = 'strength-fill strength-weak';
                strengthText.textContent = 'Weak password';
            } else if (strength <= 50) {
                strengthFill.className = 'strength-fill strength-medium';
                strengthText.textContent = 'Medium password';
            } else if (strength <= 75) {
                strengthFill.className = 'strength-fill strength-strong';
                strengthText.textContent = 'Strong password';
            } else {
                strengthFill.className = 'strength-fill strength-strong';
                strengthText.textContent = 'Very strong password';
            }
        });
        
        // Toggle password visibility
        function togglePasswordVisibility(inputId, icon) {
            const input = document.getElementById(inputId);
            const iconElement = icon.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                iconElement.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                iconElement.className = 'fas fa-eye';
            }
        }
        
        document.getElementById('togglePassword').addEventListener('click', function() {
            togglePasswordVisibility('password', this);
        });
        
        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            togglePasswordVisibility('confirm_password', this);
        });
        
        // Form validation
        const form = document.querySelector('form');
        form.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>