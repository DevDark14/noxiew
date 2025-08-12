<?php
require_once 'includes/config.php';

// If already logged in, redirect to home page
if (isLoggedIn()) {
    redirect('home.php');
}

$message = '';

// Display ban/suspension/deletion messages when redirecting from admin_panel.php
if (isset($_GET['banned']) && $_GET['banned'] == 'true') {
    $message = 'error:Your account has been permanently banned by vDanier Owner.';
} elseif (isset($_GET['suspended']) && $_GET['suspended'] == 'true') {
    $message = 'error:Your account has been temporarily suspended by vDanier Owner. Please contact support.';
} elseif (isset($_GET['deleted']) && $_GET['deleted'] == 'true') {
    $message = 'error:Your account has been permanently deleted by vDanier Owner.';
} elseif (isset($_GET['message']) && isset($_GET['type'])) {
    // This part handles messages redirected from home.php, which include suspension time
    // Added urldecode() here to correctly interpret the message
    $message = htmlspecialchars($_GET['type']) . ':' . htmlspecialchars(urldecode($_GET['message']));
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $message = 'error:Please enter your username and password.';
    } else {
        // Select user data including 'status' and 'unban_date'
        $stmt = $mysqli->prepare("SELECT id, username, email, password, avatar, theme, membership_type, `rank`, status, created_at, unban_date FROM usuarios WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // **VERIFY USER STATUS HERE**
            if ($user['status'] === 'banned') {
                $message = 'error:Your account has been permanently banned by vDanier Owner. You cannot log in.';
            } elseif ($user['status'] === 'suspended') {
                $unban_datetime = new DateTime($user['unban_date']);
                $current_datetime = new DateTime();
                $remaining_info = '';

                if ($unban_datetime > $current_datetime) {
                    $interval = $current_datetime->diff($unban_datetime);
                    $remaining_time_parts = [];
                    // Asegúrate de que solo se añadan partes si son mayores que 0
                    if ($interval->y > 0) $remaining_time_parts[] = $interval->y . ' año' . ($interval->y > 1 ? 's' : '');
                    if ($interval->m > 0) $remaining_time_parts[] = $interval->m . ' mes' . ($interval->m > 1 ? 'es' : '');
                    if ($interval->d > 0) $remaining_time_parts[] = $interval->d . ' día' . ($interval->d > 1 ? 's' : '');
                    if ($interval->h > 0) $remaining_time_parts[] = $interval->h . ' hora' . ($interval->h > 1 ? 's' : '');
                    if ($interval->i > 0) $remaining_time_parts[] = $interval->i . ' minuto' . ($interval->i > 1 ? 's' : '');
                    
                    // Asegúrate de que siempre haya un mensaje, incluso si es menos de 1 minuto
                    $remaining_info = empty($remaining_time_parts) ? 'menos de 1 minuto' : implode(', ', $remaining_time_parts);
                    $message = 'error:Your account has been temporarily suspended by vDanier Owner. Remaining time: ' . $remaining_info . '.';

                } else {
                    // If suspension date has passed, activate the user and let them log in
                    $stmt_activate = $mysqli->prepare("UPDATE usuarios SET status = 'active', unban_date = NULL WHERE id = ?");
                    $stmt_activate->bind_param("i", $user['id']);
                    $stmt_activate->execute();
                    // Fall through to password_verify
                }
            }
            
            // Only proceed to password verification if not permanently banned or still suspended
            if ($user['status'] !== 'banned' && $user['status'] !== 'suspended') { // Re-check status after potential auto-activation
                if (password_verify($password, $user['password'])) {
                    // Correct password and active account, start session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['avatar'] = $user['avatar'];
                    $_SESSION['theme'] = $user['theme'];
                    $_SESSION['membership_type'] = $user['membership_type'];
                    $_SESSION['rank'] = $user['rank'];
                    $_SESSION['created_at'] = $user['created_at'];
                    $_SESSION['status'] = $user['status']; // Save status to session
                    $_SESSION['unban_date'] = $user['unban_date']; // Save unban_date to session

                    // Redirect to home or appropriate page
                    redirect('home.php');
                } else {
                    $message = 'error:Incorrect credentials. Please try again.';
                }
            }

        } else {
            $message = 'error:Incorrect credentials. Please try again.';
        }
    }
}

require_once 'includes/header.php';
?>

<div class="form-container">
    <div class="form-card">
        <h2 class="form-title"><i class="fas fa-sign-in-alt"></i> Log In</h2>
        
        <?php if ($message): ?>
            <?php
            // Corrected explode to limit to 2 parts, ensuring all text after first ':' is captured
            $message_parts = explode(':', $message, 2); 
            $type = $message_parts[0];
            $text = $message_parts[1] ?? ''; // Use null coalescing for safety
            ?>
            <div class="message message-<?php echo $type; ?>">
                <i class="fas fa-<?php echo $type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($text); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username" class="form-label">Username or Email</label>
                <input type="text" id="username" name="username" class="form-input" placeholder="Your username or email" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-input" placeholder="Your password" required>
            </div>
            <button type="submit" name="login" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i>
                Log In
            </button>
        </form>
        <p class="form-link">Don't have an account? <a href="<?php echo BASE_URL; ?>register.php">Register here</a></p>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
