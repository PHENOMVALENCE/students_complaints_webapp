<?php 
session_start();

if (isset($_SESSION['role'])) {
    header("Location: " . $_SESSION['role'] . "_dashboard.php");
    exit();
}

$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : "";
?> 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS | Students Complaint Management System</title>
    <link rel="stylesheet" href="style_index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

    <header class="main-nav">
        <div class="logo">
            <i class="fas fa-graduation-cap"></i> CMS<span>Portal</span>
        </div>
        <div class="nav-links">
           
            <a href="register.php" class="nav-btn">Get Started</a>
        </div>
    </header>

    <main class="hero-section">
        <div class="hero-container">
            <div class="hero-text">
                <h1>Bridging the gap between <span>Students</span> and <span>Administration</span>.</h1>
                <p>A transparent, efficient, and digital platform to voice your concerns. Track your complaints in real-time and get the resolution you deserve.</p>
                
                <div class="stats-mini">
                    <div class="s-item"><strong>100%</strong><span>Digital</span></div>
                    <div class="s-item"><strong>24/7</strong><span>Accessible</span></div>
                    <div class="s-item"><strong>Fast</strong><span>Resolution</span></div>
                </div>
            </div>

            <div class="login-card">
                <div class="card-top">
                    <h2>Welcome Back</h2>
                    <p>Login to your account</p>
                </div>

                <?php if ($error): ?>
                    <div class="error-msg"><i class="fas fa-circle-exclamation"></i> <?php echo $error; ?></div>
                <?php endif; ?>

                <form action="process_index.php" method="post">
                    <div class="input-field">
                        <i class="fas fa-user"></i>
                        <input type="text" name="username" placeholder="Username" required>
                    </div>
                    
                    <div class="input-field">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" placeholder="Password" required>
                    </div>
                    
                    <button type="submit" class="submit-btn">Login to Portal</button>
                    
                    <div class="card-footer">
                        Don't have an account? <a href="register.php">Register here</a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <section id="features" class="features-section">
        <div class="section-title">
            <h2>Why Use CMS?</h2>
            <p>Streamlining the academic experience for everyone.</p>
        </div>

        <div class="features-grid">
            <div class="f-card">
                <i class="fas fa-bolt"></i>
                <h3>Quick Filing</h3>
                <p>Submit your grievances in less than a minute with our intuitive interface.</p>
            </div>
            <div class="f-card">
                <i class="fas fa-search-location"></i>
                <h3>Live Tracking</h3>
                <p>Know exactly where your complaint is in the pipeline from 'Pending' to 'Resolved'.</p>
            </div>
            <div class="f-card">
                <i class="fas fa-user-shield"></i>
                <h3>Privacy First</h3>
                <p>Secure communication between students and authorized administrative staff only.</p>
            </div>
        </div>
    </section>

    <footer class="footer">
        <p>&copy; 2025 Students Complaint System. All Rights Reserved.</p>
    </footer>

</body>
</html>