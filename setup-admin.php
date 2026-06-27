<?php
/**
 * One-time admin account setup
 *
 * - If no admin users exist: create the first admin (no key required).
 * - If admins already exist: set ADMIN_SETUP_KEY in config.php, use this page once,
 *   then clear the key and delete this file.
 *
 * URL: /setup-admin.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$error = '';
$success = '';
$createdUsername = '';

$adminCount = 0;
$countResult = $conn->query('SELECT COUNT(*) AS total FROM admin_users');
if ($countResult) {
    $row = $countResult->fetch_assoc();
    $adminCount = (int) ($row['total'] ?? 0);
    $countResult->free();
}

$setupKeyConfigured = defined('ADMIN_SETUP_KEY') && ADMIN_SETUP_KEY !== '';
$firstInstall = ($adminCount === 0);
$setupAllowed = $firstInstall || $setupKeyConfigured;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $setupAllowed) {
    $username = isset($_POST['username']) ? trim(sanitizeInput($_POST['username'])) : '';
    $email = isset($_POST['email']) ? trim(sanitizeInput($_POST['email'])) : '';
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $setupKey = $_POST['setup_key'] ?? '';

    if (!$firstInstall) {
        if (!$setupKeyConfigured || !hash_equals(ADMIN_SETUP_KEY, $setupKey)) {
            $error = 'Invalid setup key.';
        }
    }

    if ($error === '') {
        if ($username === '' || !preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
            $error = 'Username must be 3–50 characters (letters, numbers, underscore only).';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $passwordConfirm) {
            $error = 'Passwords do not match.';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address or leave it blank.';
        }
    }

    if ($error === '') {
        $checkStmt = $conn->prepare('SELECT id FROM admin_users WHERE username = ? LIMIT 1');
        $checkStmt->bind_param('s', $username);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if ($existing) {
            $error = 'That username is already taken. Choose a different one or log in.';
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $emailValue = $email !== '' ? $email : '';

            $insertStmt = $conn->prepare('INSERT INTO admin_users (username, password_hash, email) VALUES (?, ?, ?)');
            $insertStmt->bind_param('sss', $username, $passwordHash, $emailValue);

            if ($insertStmt->execute()) {
                $createdUsername = $username;
                $success = 'Admin account created successfully. You can log in now.';
                $adminCount++;
                if ($firstInstall) {
                    $firstInstall = false;
                }
            } else {
                $error = 'Could not create admin account. Database error.';
            }

            $insertStmt->close();
        }
    }
}

$companyName = getSetting('company_name', 'Admin Setup');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Admin Setup - <?php echo htmlspecialchars($companyName); ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet"/>
    <style>body { font-family: Inter, sans-serif; }</style>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-xl shadow-lg p-8">
            <h1 class="text-2xl font-bold text-slate-800 mb-2">Create Admin Account</h1>

            <?php if ($firstInstall && !$success): ?>
                <p class="text-sm text-slate-600 mb-6">No admin users found. Create your first administrator below.</p>
            <?php elseif ($setupKeyConfigured && !$success): ?>
                <p class="text-sm text-slate-600 mb-6">Enter your setup key from <code class="text-xs bg-slate-100 px-1 rounded">config.php</code> to add a new admin.</p>
            <?php elseif (!$setupAllowed && !$success): ?>
                <div class="bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded-lg text-sm mb-6">
                    <p class="font-semibold mb-2">Setup is locked</p>
                    <p>An admin account already exists. To add another without logging in:</p>
                    <ol class="list-decimal ml-5 mt-2 space-y-1">
                        <li>Open <code class="text-xs bg-amber-100 px-1 rounded">config.php</code> on the server</li>
                        <li>Set <code class="text-xs bg-amber-100 px-1 rounded">ADMIN_SETUP_KEY</code> to a long random string</li>
                        <li>Reload this page and submit the form with that key</li>
                        <li>Clear the key and delete <code class="text-xs bg-amber-100 px-1 rounded">setup-admin.php</code></li>
                    </ol>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm mb-4">
                    <?php echo htmlspecialchars($success); ?>
                    <?php if ($createdUsername): ?>
                        <p class="mt-2"><strong>Username:</strong> <?php echo htmlspecialchars($createdUsername); ?></p>
                    <?php endif; ?>
                </div>
                <div class="bg-yellow-50 border border-yellow-200 text-yellow-900 px-4 py-3 rounded-lg text-sm mb-6">
                    <strong>Important:</strong> Delete <code class="text-xs bg-yellow-100 px-1 rounded">setup-admin.php</code> from the server and clear <code class="text-xs bg-yellow-100 px-1 rounded">ADMIN_SETUP_KEY</code> in config.php.
                </div>
                <a href="/admin/login.php" class="block w-full text-center bg-yellow-400 hover:bg-yellow-500 text-black font-bold py-3 px-6 rounded-lg transition-colors">
                    Go to Admin Login
                </a>
            <?php elseif ($setupAllowed): ?>
                <form method="POST" action="" class="space-y-4">
                    <?php if (!$firstInstall): ?>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1" for="setup_key">Setup key</label>
                        <input type="password" id="setup_key" name="setup_key" required
                               class="w-full rounded-lg border border-slate-300 px-4 py-3 text-sm focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400"
                               placeholder="From ADMIN_SETUP_KEY in config.php"/>
                    </div>
                    <?php endif; ?>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1" for="username">Username</label>
                        <input type="text" id="username" name="username" required autocomplete="username"
                               class="w-full rounded-lg border border-slate-300 px-4 py-3 text-sm focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400"
                               placeholder="e.g. admin"
                               value="<?php echo htmlspecialchars($_POST['username'] ?? 'admin'); ?>"/>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1" for="email">Email (optional)</label>
                        <input type="email" id="email" name="email" autocomplete="email"
                               class="w-full rounded-lg border border-slate-300 px-4 py-3 text-sm focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400"
                               placeholder="admin@example.com"
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"/>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1" for="password">Password</label>
                        <input type="password" id="password" name="password" required autocomplete="new-password"
                               class="w-full rounded-lg border border-slate-300 px-4 py-3 text-sm focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400"
                               placeholder="At least 8 characters"/>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1" for="password_confirm">Confirm password</label>
                        <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password"
                               class="w-full rounded-lg border border-slate-300 px-4 py-3 text-sm focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400"
                               placeholder="Repeat password"/>
                    </div>

                    <button type="submit" class="w-full bg-yellow-400 hover:bg-yellow-500 text-black font-bold py-3 px-6 rounded-lg transition-colors">
                        Create Admin Account
                    </button>
                </form>

                <p class="mt-6 text-center text-sm text-slate-500">
                    <a href="/admin/login.php" class="text-yellow-600 hover:underline">Already have an account? Log in</a>
                </p>
            <?php else: ?>
                <a href="/admin/login.php" class="block w-full text-center bg-slate-200 hover:bg-slate-300 text-slate-800 font-bold py-3 px-6 rounded-lg transition-colors">
                    Go to Admin Login
                </a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
