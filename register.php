<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join CMS | Student Complaint System</title>
    <link rel="stylesheet" href="assets/css/style_index.css"> <link rel="stylesheet" href="assets/css/style_register.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

    <div class="register-wrapper">
        <div class="info-side">
            <div class="info-content">
                <i class="fas fa-user-plus main-icon"></i>
                <h2>Create Your Account</h2>
                <p>Join the digital community where your voice is heard and issues are resolved efficiently.</p>
                
                <ul class="benefit-list">
                    <li><i class="fas fa-check-circle"></i> Secure personal dashboard</li>
                    <li><i class="fas fa-check-circle"></i> Real-time status updates</li>
                    <li><i class="fas fa-check-circle"></i> Direct admin communication</li>
                </ul>
            </div>
        </div>

        <div class="form-side">
            <div class="form-container">
                <div class="form-header">
                    <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Home</a>
                    <h1>Get Started</h1>
                </div>

                <form action="handlers/process_register.php" method="post">
                    <div class="input-group">
                        <label>Select Your Identity</label>
                        <div class="role-selector">
                            <input type="radio" name="role" value="student" id="role-student" checked>
                            <label for="role-student" class="role-card">
                                <i class="fas fa-user-graduate"></i>
                                <span>Student</span>
                            </label>

                            <input type="radio" name="role" value="teacher" id="role-teacher">
                            <label for="role-teacher" class="role-card">
                                <i class="fas fa-chalkboard-teacher"></i>
                                <span>Teacher</span>
                            </label>
                        </div>
                    </div>

                    <div class="input-field">
                        <label for="username">Choose a Username</label>
                        <div class="input-wrapper">
                            <i class="fas fa-at"></i>
                            <input type="text" id="username" name="username" placeholder="johndoe123" required>
                        </div>
                    </div>
                    
                    <div class="input-field">
                        <label for="password">Security Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-shield-alt"></i>
                            <input type="password" id="password" name="password" placeholder="••••••••" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="register-btn">Create Account</button>
                    
                    <div class="form-footer">
                        Already have an account? <a href="index.php">Log in here</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

</body>
</html>