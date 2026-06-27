<?php
/**
 * Password Update Script
 * Resets admin password to: Secretpass0721//
 * Access this URL once to update the password
 */

require_once __DIR__ . '/config.php';

// New password
$newPassword = 'Secretpass0721//';
$username = 'admin';

// Generate password hash
$passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

// Update admin password
$stmt = $conn->prepare("UPDATE admin_users SET password_hash = ? WHERE username = ?");
$stmt->bind_param("ss", $passwordHash, $username);

$success = false;
$message = '';

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        $success = true;
        $message = "Password updated successfully!<br><br>";
        $message .= "<strong>Username:</strong> admin<br>";
        $message .= "<strong>Password:</strong> Secretpass0721//<br><br>";
        $message .= "You can now login at <a href='/admin/login.php'>/admin/login.php</a>";
    } else {
        $message = "Admin user 'admin' not found. Creating new admin account...";
        
        // Create admin account if it doesn't exist
        $stmt2 = $conn->prepare("INSERT INTO admin_users (username, password_hash, email) VALUES (?, ?, ?)");
        $email = 'admin@courier.com';
        $stmt2->bind_param("sss", $username, $passwordHash, $email);
        
        if ($stmt2->execute()) {
            $success = true;
            $message = "Admin account created successfully!<br><br>";
            $message .= "<strong>Username:</strong> admin<br>";
            $message .= "<strong>Password:</strong> Secretpass0721//<br><br>";
            $message .= "You can now login at <a href='/admin/login.php'>/admin/login.php</a>";
        } else {
            $message = "Error creating admin account: " . $conn->error;
        }
        $stmt2->close();
    }
} else {
    $message = "Error updating password: " . $conn->error;
}

$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Update</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-lg p-8 max-w-md w-full">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Password Update</h1>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo $message; ?>
            </div>
            <div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded">
                <p class="text-sm text-yellow-800">
                    <strong>Security Note:</strong> For security reasons, you should delete or protect this file after use.
                </p>
            </div>
        <?php else: ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="mt-6">
            <a href="/admin/login.php" class="inline-block bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded">
                Go to Admin Login
            </a>
        </div>
    </div>
</body>
</html>

