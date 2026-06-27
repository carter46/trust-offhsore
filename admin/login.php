<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

$error = '';

// If already logged in, redirect to dashboard
if (isAdminLoggedIn()) {
    header('Location: /admin/dashboard.php');
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? sanitizeInput($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Additional validation
    if (strlen($username) > 50 || strlen($password) > 255) {
        $error = 'Invalid input length';
    }
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        // Check user credentials
        $stmt = $conn->prepare("SELECT id, username, password_hash FROM admin_users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password_hash'])) {
                // Login successful
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_user_id'] = $row['id'];
                $_SESSION['admin_username'] = $row['username'];
                
                // Update last login
                $updateStmt = $conn->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
                $updateStmt->bind_param("i", $row['id']);
                $updateStmt->execute();
                $updateStmt->close();
                
                $stmt->close();
                header('Location: /admin/dashboard.php');
                exit;
            } else {
                $error = 'Invalid username or password';
            }
        } else {
            $error = 'Invalid username or password';
        }
        
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Admin Login - <?php echo htmlspecialchars(getSetting('company_name', 'FedEx')); ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet"/>
    <link rel="stylesheet" href="/css/admin.css">
    <?php
    // Get brand colors from settings (accessible even without login)
    $primaryColor = getSetting('primary_color', '#4D148C');
    $secondaryColor = getSetting('secondary_color', '#FF6200');
    
    // Auto-generate dark variants (30% darker)
    $primaryDark = darkenColor($primaryColor, 30);
    $secondaryDark = darkenColor($secondaryColor, 30);
    ?>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "<?php echo htmlspecialchars($primaryColor); ?>",
                        secondary: "<?php echo htmlspecialchars($secondaryColor); ?>",
                        "primary-dark": "<?php echo htmlspecialchars($primaryDark); ?>",
                        "secondary-dark": "<?php echo htmlspecialchars($secondaryDark); ?>",
                    },
                    fontFamily: {
                        sans: ["Roboto", "sans-serif"],
                    },
                },
            },
        };
    </script>
</head>
<body class="bg-background-light dark:bg-background-dark font-sans min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-md">
        <div class="bg-white dark:bg-surface-dark rounded-lg shadow-xl p-8">
            <div class="text-center mb-8">
                <div class="mb-4">
                    <img src="<?php echo htmlspecialchars(getLogo('light')); ?>" alt="<?php echo htmlspecialchars(getSetting('company_name', 'FedEx')); ?>" class="h-12 w-auto mx-auto">
                </div>
                <h1 class="text-2xl font-light text-gray-800 dark:text-white">Admin Login</h1>
            </div>
            
            <?php if ($error): ?>
                <div class="bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-6">
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="username">
                        Username
                    </label>
                    <input type="text" id="username" name="username" required
                           class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary focus:border-transparent"
                           placeholder="Enter your username">
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="password">
                        Password
                    </label>
                    <input type="password" id="password" name="password" required
                           class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary focus:border-transparent"
                           placeholder="Enter your password">
                </div>
                
                <button type="submit" class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-3 px-6 rounded uppercase tracking-wide transition-colors">
                    Sign In
                </button>
            </form>
            
            <div class="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">
                <a href="/" class="text-primary hover:underline">Back to Home</a>
            </div>
        </div>
    </div>
</body>
</html>

