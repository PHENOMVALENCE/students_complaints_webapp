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
    <title>Complaint Management System - Login</title>
    <link rel="stylesheet" href="assets/css/theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }
        
        /* Navigation */
        .main-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 8%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .logo {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .logo i {
            font-size: 2rem;
        }
        
        .logo span {
            color: var(--dark);
        }
        
        .nav-links {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .nav-btn {
            background: var(--primary);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .nav-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        /* Hero Section */
        .hero-section {
            background: transparent;
            padding: 80px 8%;
            min-height: calc(100vh - 80px);
        }
        
        .hero-container {
            display: grid;
            grid-template-columns: 1fr 450px;
            gap: 60px;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .hero-text {
            color: white;
        }
        
        .hero-text h1 {
            font-size: 3.5rem;
            margin-bottom: 24px;
            line-height: 1.2;
            color: white;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
            font-weight: 800;
        }
        
        .hero-text h1 span {
            color: #fbbf24;
            display: inline-block;
        }
        
        .hero-text p {
            color: rgba(255, 255, 255, 0.95);
            font-size: 1.25rem;
            margin-bottom: 40px;
            line-height: 1.8;
        }
        
        .stats-mini {
            display: flex;
            gap: 40px;
            margin-top: 40px;
        }
        
        .s-item {
            color: white;
        }
        
        .s-item strong {
            display: block;
            font-size: 2rem;
            color: #fbbf24;
            font-weight: 800;
            margin-bottom: 4px;
        }
        
        .s-item span {
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.9;
        }
        
        /* Login Card */
        .login-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 450px;
        }
        
        .card-top {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .card-top h2 {
            font-size: 2rem;
            color: var(--text-primary);
            margin-bottom: 8px;
            font-weight: 700;
        }
        
        .card-top p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }
        
        .input-field {
            position: relative;
            margin-bottom: 20px;
        }
        
        .input-field i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 1.1rem;
        }
        
        .input-field input {
            width: 100%;
            padding: 16px 16px 16px 50px;
            border: 2px solid var(--border);
            border-radius: 10px;
            background: var(--bg-white);
            font-size: 1rem;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .input-field input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .submit-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }
        
        .error-msg {
            background: var(--danger-light);
            color: #991b1b;
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            border-left: 4px solid var(--danger);
        }
        
        .card-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid var(--border);
            color: var(--text-secondary);
            font-size: 0.95rem;
        }
        
        .card-footer a {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
        }
        
        .card-footer a:hover {
            text-decoration: underline;
        }
        
        /* Features Section */
        .features-section {
            background: white;
            padding: 100px 8%;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 60px;
        }
        
        .section-title h2 {
            font-size: 2.5rem;
            color: var(--text-primary);
            margin-bottom: 12px;
        }
        
        .section-title p {
            font-size: 1.1rem;
            color: var(--text-secondary);
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 40px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .f-card {
            padding: 40px;
            border-radius: 16px;
            background: var(--bg-white);
            border: 1px solid var(--border);
            transition: all 0.3s;
            text-align: center;
        }
        
        .f-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.1);
            border-color: var(--primary);
        }
        
        .f-card i {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 24px;
        }
        
        .f-card h3 {
            font-size: 1.5rem;
            color: var(--text-primary);
            margin-bottom: 12px;
        }
        
        .f-card p {
            color: var(--text-secondary);
            line-height: 1.7;
        }
        
        /* Footer */
        .footer {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 40px;
            text-align: center;
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.95rem;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .hero-container {
                grid-template-columns: 1fr;
                gap: 40px;
            }
            
            .hero-text h1 {
                font-size: 2.5rem;
            }
            
            .login-card {
                max-width: 100%;
            }
        }
        
        @media (max-width: 768px) {
            .hero-section {
                padding: 40px 5%;
            }
            
            .hero-text h1 {
                font-size: 2rem;
            }
            
            .hero-text p {
                font-size: 1.1rem;
            }
            
            .stats-mini {
                flex-direction: column;
                gap: 20px;
            }
            
            .features-section {
                padding: 60px 5%;
            }
            
            .main-nav {
                padding: 15px 5%;
            }
        }
    </style>
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

                <form action="handlers/process_index.php" method="post">
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