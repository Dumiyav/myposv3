<?php
session_start();
require_once '../../config.php';
require_once '../../includes/functions.php';

// Redirect if already logged in
if (isset($_SESSION['user'])) {
    header('Location: ../../index.php');
    exit;
}

$error = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? ''); // Trim whitespace
    $password = $_POST['password'] ?? '';

    // Validate input
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        // Get users from JSON file
        $users = readJsonFile('users.json');

        // Find user by username
        $user = null;
        foreach ($users as $u) {
            // Case-insensitive username comparison for better user experience,
            // but store username as is.
            if (strtolower($u['username']) === strtolower($username)) {
                $user = $u;
                break;
            }
        }

        // Verify password
        if ($user && password_verify($password, $user['password'])) {
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);

            // Update last login time
            $user['last_login'] = date(DATE_FORMAT);

            // Update user in users array
            foreach ($users as &$u_ref) { // Use reference to modify original array
                if ($u_ref['id'] === $user['id']) {
                    $u_ref = $user;
                    break;
                }
            }
            unset($u_ref); // Unset reference

            // Save updated users to JSON file
            writeJsonFile('users.json', $users);

            // Set session
            $_SESSION['user'] = $user;
            $_SESSION['login_time'] = time(); // Store login time for session timeout

            // Redirect to dashboard
            header('Location: ../../index.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></title>

    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 h-screen flex items-center justify-center">
    <div class="max-w-md w-full bg-white rounded-lg shadow-md overflow-hidden">
        <div class="bg-gray-800 text-white py-4 px-6">
            <h2 class="text-2xl font-bold"><?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></h2>
            <p class="text-gray-300">Login to your account</p>
        </div>

        <div class="p-6">
            <?php if ($error): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                    <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-4">
                    <label for="username" class="block text-gray-700 text-sm font-bold mb-2">Username</label>
                    <input type="text" id="username" name="username" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                </div>

                <div class="mb-6">
                    <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                    <input type="password" id="password" name="password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>

                <div class="flex items-center justify-between">
                    <button type="submit" class="bg-gray-800 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Sign In
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>