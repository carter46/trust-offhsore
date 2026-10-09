<?php
/**
 * Admin password reset page.
 * Only works while ADMIN_PASSWORD_RESET_ENABLED is true in config.php; otherwise it redirects to the login page.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!adminPasswordResetEnabled()) {
    header('Location: /admin/login.php');
    exit;
}

$error = '';
$success = false;
$loginId = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginId = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (!csrfValid()) {
        $error = 'Your session expired. Reload the page and try again.';
    } elseif ($loginId === '' || strlen($loginId) > 100) {
        $error = 'Enter the admin username or email.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (strlen($password) > 255) {
        $error = 'Password is too long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $conn->prepare(
            "SELECT id, username
             FROM admin_users
             WHERE username = ?
                OR (email IS NOT NULL AND email != '' AND LOWER(email) = LOWER(?))
             LIMIT 1"
        );
        if (!$stmt) {
            $error = 'Password reset is temporarily unavailable. Check the database connection.';
        } else {
            $stmt->bind_param('ss', $loginId, $loginId);
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$admin) {
                $error = 'Admin account not found.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $adminId = (int) $admin['id'];
                $update = $conn->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?');
                $update->bind_param('si', $passwordHash, $adminId);
                if ($update->execute()) {
                    $success = true;
                    writeAppLog('settings', 'Admin password reset through the reset page', ['admin_id' => $adminId, 'username' => $admin['username']], 'WARN');
                } else {
                    $error = 'Could not save the new password. Database error.';
                }
                $update->close();
            }
        }
    }
}

$companyName = getCompanyName();
$primaryColor = getSetting('primary_color', '#4D148C');
$secondaryColor = getSetting('secondary_color', '#FF6200');
$primaryDark = darkenColor($primaryColor, 30);
$secondaryDark = darkenColor($secondaryColor, 30);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <meta name="robots" content="noindex, nofollow"/>
    <title>Reset Admin Password - <?php echo htmlspecialchars($companyName); ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet"/>
    <link rel="stylesheet" href="/css/admin.css">
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
                    <img src="<?php echo htmlspecialchars(getLogo('light')); ?>" alt="<?php echo htmlspecialchars($companyName); ?>" class="h-12 w-auto mx-auto">
                </div>
                <h1 class="text-2xl font-light text-gray-800 dark:text-white">Reset Admin Password</h1>
            </div>

            <?php if ($success): ?>
                <div class="bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200 px-4 py-3 rounded mb-4">
                    The admin password has been reset. You can now log in with the new password.
                </div>
                <div class="bg-amber-100 dark:bg-amber-900 text-amber-800 dark:text-amber-200 px-4 py-3 rounded mb-6 text-sm">
                    Now set <strong>ADMIN_PASSWORD_RESET_ENABLED</strong> back to <strong>false</strong> in config.php.
                </div>
                <a href="/admin/login.php" class="block w-full text-center bg-primary hover:bg-primary-dark text-white font-bold py-3 px-6 rounded uppercase tracking-wide transition-colors">
                    Go to Admin Login
                </a>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 px-4 py-3 rounded mb-4">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?php echo csrfField(); ?>
                    <div class="mb-6">
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="username">
                            Admin Username or Email
                        </label>
                        <input type="text" id="username" name="username" required
                               value="<?php echo htmlspecialchars($loginId); ?>"
                               class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary focus:border-transparent"
                               placeholder="Enter username or email"
                               autocomplete="username">
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="password">
                            New Password
                        </label>
                        <input type="password" id="password" name="password" required minlength="8"
                               class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary focus:border-transparent"
                               placeholder="At least 8 characters"
                               autocomplete="new-password">
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="confirm_password">
                            Confirm New Password
                        </label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="8"
                               class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary focus:border-transparent"
                               placeholder="Repeat the new password"
                               autocomplete="new-password">
                    </div>

                    <button type="submit" class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-3 px-6 rounded uppercase tracking-wide transition-colors">
                        Reset Password
                    </button>
                    <p class="mt-4 text-xs text-center text-gray-500 dark:text-gray-400">
                        Security note: set ADMIN_PASSWORD_RESET_ENABLED back to false in config.php after use.
                    </p>
                </form>
            <?php endif; ?>

            <div class="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">
                <a href="/admin/login.php" class="text-primary hover:underline">&larr; Back to Admin Login</a>
            </div>
        </div>
    </div>
</body>
</html>
