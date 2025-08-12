<?php
require_once 'includes/config.php';

// Verificar si ya está logueado
if (isLoggedIn()) {
    redirect('home.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize_input($_POST['username']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validaciones
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Por favor, completa todos los campos';
    } elseif (strlen($username) < 3) {
        $error = 'El nombre de usuario debe tener al menos 3 caracteres';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Por favor, ingresa un email válido';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } elseif ($password !== $confirm_password) {
        $error = 'Las contraseñas no coinciden';
    } else {
        // Verificar si el usuario o email ya existen
        $stmt = $mysqli->prepare("SELECT id FROM usuarios WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'El nombre de usuario o email ya están en uso';
        } else {
            // Crear usuario
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare("INSERT INTO usuarios (username, email, password, membership_type) VALUES (?, ?, ?, 'free')");
            $stmt->bind_param("sss", $username, $email, $hashed_password);
            
            if ($stmt->execute()) {
                $success = 'Cuenta creada exitosamente. Ya puedes iniciar sesión.';
            } else {
                $error = 'Error al crear la cuenta. Inténtalo de nuevo.';
            }
        }
    }
}

require_once 'includes/header.php';
?>

<div class="form-container">
    <div class="form-card">
        <h2 class="form-title">Crear Cuenta</h2>
        
        <?php if ($error): ?>
            <div class="message message-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="message message-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username" class="form-label">Nombre de Usuario</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    class="form-input" 
                    value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                    placeholder="Elige un nombre de usuario"
                    required
                    minlength="3"
                >
            </div>
            
            <div class="form-group">
                <label for="email" class="form-label">Email</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    class="form-input" 
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    placeholder="tu@email.com"
                    required
                >
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">Contraseña</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="form-input" 
                    placeholder="Mínimo 6 caracteres"
                    required
                    minlength="6"
                >
            </div>
            
            <div class="form-group">
                <label for="confirm_password" class="form-label">Confirmar Contraseña</label>
                <input 
                    type="password" 
                    id="confirm_password" 
                    name="confirm_password" 
                    class="form-input" 
                    placeholder="Repite tu contraseña"
                    required
                    minlength="6"
                >
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-user-plus"></i>
                Crear Cuenta
            </button>
        </form>
        
        <div class="form-link">
            ¿Ya tienes cuenta? 
            <a href="login.php">Inicia sesión aquí</a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>