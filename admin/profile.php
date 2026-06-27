<?php
include __DIR__ . '/includes/admin-header.php';

$success = '';
$error = '';

// Get current admin user info
$userId = $_SESSION['admin_user_id'] ?? 0;
$user = ['username' => '', 'email' => ''];
if ($userId > 0) {
    $stmt = $conn->prepare("SELECT username, email FROM admin_users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $userData = $result->fetch_assoc();
    if ($userData) {
        $user = $userData;
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle password change
    if (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'All password fields are required.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'New password must be at least 8 characters long.';
        } else {
            // Verify current password
            $checkStmt = $conn->prepare("SELECT password_hash FROM admin_users WHERE id = ?");
            $checkStmt->bind_param("i", $userId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $checkUser = $checkResult->fetch_assoc();
            $checkStmt->close();
            
            if ($checkUser && password_verify($currentPassword, $checkUser['password_hash'])) {
                // Update password
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $updateStmt = $conn->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?");
                $updateStmt->bind_param("si", $newHash, $userId);
                
                if ($updateStmt->execute()) {
                    $success = 'Password changed successfully!';
                } else {
                    $error = 'Failed to update password.';
                }
                $updateStmt->close();
            } else {
                $error = 'Current password is incorrect.';
            }
        }
    }
    
    // Handle email change
    if (isset($_POST['change_email'])) {
        $newEmail = sanitizeInput($_POST['new_email'] ?? '');
        $confirmPassword = $_POST['email_password'] ?? '';
        
        if (empty($newEmail)) {
            $error = 'Email is required.';
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } elseif (empty($confirmPassword)) {
            $error = 'Password confirmation is required to change email.';
        } else {
            // Verify password
            $checkStmt = $conn->prepare("SELECT password_hash FROM admin_users WHERE id = ?");
            $checkStmt->bind_param("i", $userId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $checkUser = $checkResult->fetch_assoc();
            $checkStmt->close();
            
            if ($checkUser && password_verify($confirmPassword, $checkUser['password_hash'])) {
                // Update email
                $updateStmt = $conn->prepare("UPDATE admin_users SET email = ? WHERE id = ?");
                $updateStmt->bind_param("si", $newEmail, $userId);
                
                if ($updateStmt->execute()) {
                    $success = 'Email updated successfully!';
                    // Refresh user data
                    $stmt = $conn->prepare("SELECT username, email FROM admin_users WHERE id = ?");
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user = $result->fetch_assoc();
                    $stmt->close();
                } else {
                    $error = 'Failed to update email.';
                }
                $updateStmt->close();
            } else {
                $error = 'Password is incorrect.';
            }
        }
    }
}
?>
<div class="mb-8">
    <h1 class="text-3xl font-light text-gray-800 dark:text-white mb-2">Profile Settings</h1>
    <p class="text-gray-600 dark:text-gray-400">Manage your account password and email</p>
</div>

<?php if ($success): ?>
    <div class="bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200 px-4 py-3 rounded mb-6">
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 px-4 py-3 rounded mb-6">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="space-y-6">
    <!-- Change Password -->
    <div class="bg-white dark:bg-surface-dark rounded-lg shadow p-6">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4">Change Password</h2>
        <form method="POST" action="">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="current_password">
                        Current Password
                    </label>
                    <input type="password" id="current_password" name="current_password" required
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="new_password">
                        New Password
                    </label>
                    <input type="password" id="new_password" name="new_password" required minlength="8"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                    <p class="mt-1 text-xs text-gray-500">Must be at least 8 characters long</p>
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="confirm_password">
                        Confirm New Password
                    </label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                
                <div>
                    <button type="submit" name="change_password" class="bg-primary hover:bg-primary-dark text-white font-bold py-3 px-8 rounded uppercase tracking-wide transition-colors">
                        Change Password
                    </button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Change Email -->
    <div class="bg-white dark:bg-surface-dark rounded-lg shadow p-6">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4">Change Email</h2>
        <form method="POST" action="">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">
                        Current Email
                    </label>
                    <input type="text" value="<?php echo htmlspecialchars($user['email'] ?? 'Not set'); ?>" disabled
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="new_email">
                        New Email
                    </label>
                    <input type="email" id="new_email" name="new_email" required
                           value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="email_password">
                        Confirm Password
                    </label>
                    <input type="password" id="email_password" name="email_password" required
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                    <p class="mt-1 text-xs text-gray-500">Enter your password to confirm email change</p>
                </div>
                
                <div>
                    <button type="submit" name="change_email" class="bg-primary hover:bg-primary-dark text-white font-bold py-3 px-8 rounded uppercase tracking-wide transition-colors">
                        Change Email
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>

