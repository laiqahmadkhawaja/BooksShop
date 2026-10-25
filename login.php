<?php
// login.php
require_once __DIR__ . '/config/app.php';

if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $user = dbFetchOne("SELECT * FROM users WHERE username = ? AND is_active = 1", [$username]);
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'role' => $user['role'],
                'email' => $user['email']
            ];
            header("Location: index.php");
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please fill in both fields.';
    }
}

$shopName = getSetting('shop_name', 'Kitab Ghar & Stationery');
$shopNameUrdu = getSetting('shop_name_urdu', 'کتاب گھر اینڈ اسٹیشنری مارٹ');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($shopName) ?> - Sign In</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.25);
            max-width: 440px;
            width: 100%;
            overflow: hidden;
        }
        .login-header {
            background: #142850;
            color: #fff;
            padding: 30px;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="container p-3">
    <div class="login-card mx-auto">
        <div class="login-header">
            <i class="fa-solid fa-book-open-reader fa-3x text-warning mb-2"></i>
            <h4 class="fw-bold mb-1"><?= htmlspecialchars($shopName) ?></h4>
            <div class="text-white-50 small"><?= htmlspecialchars($shopNameUrdu) ?></div>
        </div>

        <div class="p-4">
            <?php if ($error): ?>
                <div class="alert alert-danger py-2 small d-flex align-items-center">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary">Username</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-user text-muted"></i></span>
                        <input type="text" name="username" id="usernameInput" class="form-control" placeholder="Enter username" required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-semibold text-secondary">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-lock text-muted"></i></span>
                        <input type="password" name="password" id="passwordInput" class="form-control" placeholder="Enter password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                    <i class="fa-solid fa-right-to-bracket me-2"></i> Sign In to POS
                </button>
            </form>

            <div class="mt-4 pt-3 border-top text-center">
                <span class="small text-muted d-block mb-2">One-Click Demo Fill:</span>
                <div class="btn-group btn-group-sm w-100" role="group">
                    <button type="button" class="btn btn-outline-primary" onclick="fillCreds('admin', 'admin123')">Admin</button>
                    <button type="button" class="btn btn-outline-success" onclick="fillCreds('cashier', 'cashier123')">Cashier</button>
                    <button type="button" class="btn btn-outline-info" onclick="fillCreds('accountant', 'acc123')">Accountant</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function fillCreds(u, p) {
    document.getElementById('usernameInput').value = u;
    document.getElementById('passwordInput').value = p;
}
</script>
</body>
</html>
