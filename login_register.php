<?php
require_once 'config.php';

// Handle Login
if (isset($_POST['login'])) {
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    
    // Validate email
    if (!validateEmail($email)) {
        $_SESSION['login_error'] = "Invalid email format";
        $_SESSION['active_form'] = 'login';
        header("Location: index.php");
        exit();
    }
    
    // Prepare statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT id, name, email, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();      
        if (password_verify($password, $user['password'])) {

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['logged_in'] = true;
            
            $stmt->close();
            header("Location: notes.php");
            exit();
        }
    }
    
    $_SESSION['login_error'] = "Invalid email or password";
    $_SESSION['active_form'] = 'login';
    $stmt->close();
    header("Location: index.php");
    exit();
}

// Handle Registration
if (isset($_POST['register'])) {
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    if (strlen($name) < 2 || strlen($name) > 100) {
        $errors[] = "Name must be between 2 and 100 characters";
    }   
    if (!validateEmail($email)) {
        $errors[] = "Invalid email format";
    }   
    if (!validatePassword($password)) {
        $errors[] = "Password must be at least 6 characters long";
    }   
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    

    if (empty($errors)) {
        
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = "Email already registered";
        }
        $stmt->close();
        
        if (empty($errors)) {

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $email, $hashed_password);
            
            if ($stmt->execute()) {
                $_SESSION['register_success'] = "Registration successful! Please login.";
                $_SESSION['active_form'] = 'login';
            } else {
                $_SESSION['register_error'] = "Registration failed. Please try again.";
                $_SESSION['active_form'] = 'register';
            }
            $stmt->close();
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['register_error'] = implode("<br>", $errors);
        $_SESSION['active_form'] = 'register';
    }
    
    header("Location: index.php");
    exit();
}

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}
?>