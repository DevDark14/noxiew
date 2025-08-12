<?php
require_once 'includes/config.php';

// Verificar si está logueado
if (!isLoggedIn()) {
    redirect('login.php');
}

$message = '';
$user_id = $_SESSION['user_id'];

// Obtener datos actuales del usuario
loadUserData($user_id, $mysqli);
$user_data = [
    'id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'email' => $_SESSION['email'],
    'avatar' => $_SESSION['avatar'],
    'theme' => $_SESSION['theme'],
    'membership_type' => $_SESSION['membership_type'],
    'first_name' => $_SESSION['first_name'] ?? '',
    'last_name' => $_SESSION['last_name'] ?? '',
    'rank' => $_SESSION['rank'] ?? 'user', // Default to 'user'
    'created_at' => $_SESSION['created_at'] ?? date('Y-m-d H:i:s')
];


// Procesar formulario de perfil (Información Personal)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $first_name = sanitize_input($_POST['first_name']);
    $last_name = sanitize_input($_POST['last_name']);
    $username = sanitize_input($_POST['username']);
    $email = sanitize_input($_POST['email']);
    
    // Validaciones
    if (empty($username) || empty($email)) {
        $message = 'error:El nombre de usuario y email son requeridos';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'error:Por favor, ingresa un email válido';
    } else {
        // Verificar si username/email ya existen (excluyendo el usuario actual)
        $stmt = $mysqli->prepare("SELECT id FROM usuarios WHERE (username = ? OR email = ?) AND id != ?");
        $stmt->bind_param("ssi", $username, $email, $user_id);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            $message = 'error:El nombre de usuario o email ya están en uso';
        } else {
            // Actualizar datos
            $stmt = $mysqli->prepare("UPDATE usuarios SET first_name = ?, last_name = ?, username = ?, email = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $first_name, $last_name, $username, $email, $user_id);
            
            if ($stmt->execute()) {
                // Recargar datos del usuario en la sesión después de una actualización exitosa
                loadUserData($user_id, $mysqli);
                // Actualizar user_data localmente para reflejar los cambios inmediatamente
                $user_data['first_name'] = $first_name;
                $user_data['last_name'] = $last_name;
                $user_data['username'] = $username;
                $user_data['email'] = $email;

                $message = 'success:Perfil actualizado correctamente';
            } else {
                $message = 'error:Error al actualizar el perfil';
            }
        }
    }
}

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password_submit'])) { // Renombrado para evitar conflicto
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = 'error:Todos los campos de contraseña son requeridos';
    } elseif (strlen($new_password) < 6) {
        $message = 'error:La nueva contraseña debe tener al menos 6 caracteres';
    } elseif ($new_password !== $confirm_password) {
        $message = 'error:Las nuevas contraseñas no coinciden';
    } else {
        // Debemos obtener la contraseña hasheada del usuario desde la DB para verificarla
        $stmt_password = $mysqli->prepare("SELECT password FROM usuarios WHERE id = ?");
        $stmt_password->bind_param("i", $user_id);
        $stmt_password->execute();
        $db_password_hash = $stmt_password->get_result()->fetch_assoc()['password'];

        if (!password_verify($current_password, $db_password_hash)) {
            $message = 'error:La contraseña actual es incorrecta';
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                $message = 'success:Contraseña cambiada correctamente';
            } else {
                $message = 'error:Error al cambiar la contraseña';
            }
        }
    }
}

// Procesar subida de avatar (ahora solo se activa al confirmar desde el modal)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_avatar_upload'])) {
    if (isset($_FILES['avatar_file'])) {
        $upload_dir = 'uploads/';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file = $_FILES['avatar_file'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if ($file['error'] === UPLOAD_ERR_OK) {
            if (in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'avatar_' . $user_id . '_' . time() . '.' . $extension;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Eliminar avatar anterior si existe
                    if (!empty($user_data['avatar']) && file_exists($upload_dir . $user_data['avatar'])) {
                        unlink($upload_dir . $user_data['avatar']);
                    }
                    
                    // Actualizar en base de datos
                    $stmt = $mysqli->prepare("UPDATE usuarios SET avatar = ? WHERE id = ?");
                    $stmt->bind_param("si", $filename, $user_id);
                    
                    if ($stmt->execute()) {
                        loadUserData($user_id, $mysqli);
                        $user_data['avatar'] = $filename; // Actualizar localmente
                        $message = 'success:Avatar actualizado correctamente';
                    } else {
                        $message = 'error:Error al guardar el avatar en la base de datos';
                    }
                } else {
                    $message = 'error:Error al subir el archivo.';
                }
            } else {
                $message = 'error:Archivo no válido. Solo se permiten imágenes JPG, PNG, GIF menores a 5MB.';
            }
        } else {
            $message = 'error:No se seleccionó ningún archivo o hubo un error de subida.';
        }
    } else {
        $message = 'error:No se envió ningún archivo.';
    }
}

require_once 'includes/header.php';
?>

<div class="container">
    <div class="hero">
        <h1 class="hero-title">Configuración</h1>
        <p class="hero-subtitle">Personaliza tu cuenta y preferencias</p>
    </div>
    
    <?php if ($message): ?>
        <?php $type = explode(':', $message)[0]; $text = explode(':', $message)[1]; ?>
        <div class="message message-<?php echo $type; ?>">
            <i class="fas fa-<?php echo $type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($text); ?>
        </div>
    <?php endif; ?>
    
    <!-- Sección Avatar - ¡Ahora con su propio formulario! -->
    <div class="settings-section">
        <h2><i class="fas fa-user-circle"></i> Foto de Perfil</h2>
        <!-- El formulario ahora es para el input de archivo y el botón de confirmar oculto -->
        <form id="avatarUploadForm" method="POST" enctype="multipart/form-data">
            <div class="profile-photo-upload">
                <?php if (!empty($user_data['avatar'])): ?>
                    <img src="<?php echo BASE_URL . 'uploads/' . htmlspecialchars($user_data['avatar']); ?>" alt="Avatar" class="avatar-preview">
                <?php else: ?>
                    <div class="default-avatar-large">
                        <?php echo strtoupper(htmlspecialchars(substr($user_data['username'], 0, 1))); ?>
                    </div>
                <?php endif; ?>
                
                <div class="file-upload-controls">
                    <div class="file-input-wrapper">
                        <input type="file" name="avatar_file" id="avatarFileInput" accept="image/jpeg, image/png, image/gif">
                        <label for="avatarFileInput" class="file-input-label">
                            <i class="fas fa-upload"></i> Seleccionar archivo
                        </label>
                        <span id="fileNameDisplay" class="file-name-display">
                            <?php echo !empty($user_data['avatar']) ? htmlspecialchars($user_data['avatar']) : 'Sin archivos seleccionados'; ?>
                        </span>
                    </div>
                    <span class="file-size-info">JPG, PNG o GIF. Máximo 5MB</span>
                    <!-- El botón de submit original es eliminado, la subida ahora es vía modal -->
                </div>
            </div>
            <!-- Campo oculto para enviar el nombre del archivo al confirmar -->
            <input type="hidden" name="uploaded_avatar_filename" id="uploadedAvatarFilename">
        </form>
    </div>
    
    <!-- Sección Información Personal -->
    <div class="settings-section">
        <h2><i class="fas fa-user-edit"></i> Información Personal</h2>
        <form method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label for="first_name" class="form-label">Nombre</label>
                    <input 
                        type="text" 
                        id="first_name" 
                        name="first_name" 
                        class="form-input" 
                        value="<?php echo htmlspecialchars($user_data['first_name'] ?? ''); ?>"
                        placeholder="Tu nombre"
                    >
                </div>
                
                <div class="form-group">
                    <label for="last_name" class="form-label">Apellido</label>
                    <input 
                        type="text" 
                        id="last_name" 
                        name="last_name" 
                        class="form-input" 
                        value="<?php echo htmlspecialchars($user_data['last_name'] ?? ''); ?>"
                        placeholder="Tu apellido"
                    >
                </div>
            </div>
            
            <div class="form-group">
                <label for="username" class="form-label">Nombre de Usuario</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    class="form-input" 
                    value="<?php echo htmlspecialchars($user_data['username']); ?>"
                    readonly
                >
            </div>
            
            <div class="form-group">
                <label for="email" class="form-label">Email</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    class="form-input" 
                    value="<?php echo htmlspecialchars($user_data['email']); ?>"
                    required
                >
            </div>
            
            <button type="submit" name="update_profile" class="btn btn-primary">
                <i class="fas fa-save"></i>
                Guardar Cambios
            </button>
        </form>
    </div>
    
    <!-- Sección Cambiar Contraseña -->
    <div class="settings-section">
        <h2><i class="fas fa-key"></i> Cambiar Contraseña</h2>
        <form method="POST">
            <div class="form-group">
                <label for="current_password" class="form-label">Contraseña Actual</label>
                <input 
                    type="password" 
                    id="current_password" 
                    name="current_password" 
                    class="form-input" 
                    placeholder="Tu contraseña actual"
                    required
                >
            </div>
            
            <div class="form-group">
                <label for="new_password" class="form-label">Nueva Contraseña</label>
                <input 
                    type="password" 
                    id="new_password" 
                    name="new_password" 
                    class="form-input" 
                    placeholder="Nueva contraseña (mínimo 6 caracteres)"
                    minlength="6"
                    required
                >
            </div>
            
            <div class="form-group">
                <label for="confirm_password" class="form-label">Confirmar Nueva Contraseña</label>
                <input 
                    type="password" 
                    id="confirm_password" 
                    name="confirm_password" 
                    class="form-input" 
                    placeholder="Repite la nueva contraseña"
                    minlength="6"
                    required
                >
            </div>
            
            <button type="submit" name="change_password_submit" class="btn btn-primary">
                <i class="fas fa-lock"></i>
                Cambiar Contraseña
            </button>
        </form>
    </div>
    
    <!-- Sección Preferencias de Tema -->
    <div class="settings-section">
        <h2><i class="fas fa-palette"></i> Preferencias de Tema</h2>
        <p style="color: var(--text-secondary); margin-bottom: 1rem;">Cambia entre tema claro y oscuro según tu preferencia.</p>
        <form method="POST">
            <div class="theme-selector">
                <div class="theme-button" id="lightThemeButton" data-theme-value="light">
                    <i class="fas fa-sun theme-icon"></i>
                    <span class="theme-text">Tema Claro</span>
                </div>
                <div class="theme-button" id="darkThemeButton" data-theme-value="dark">
                    <i class="fas fa-moon theme-icon"></i>
                    <span class="theme-text">Tema Oscuro</span>
                </div>
            </div>
            <!-- El botón de guardar para el tema se maneja con JavaScript de forma asíncrona -->
        </form>
    </div>
        
    <!-- Sección Información de Cuenta - NO necesita formulario si es solo visualización -->
    <div class="settings-section">
        <h2><i class="fas fa-info-circle"></i> Información de Cuenta</h2>
        
        <div class="user-detail">
            <span>ID de Usuario:</span>
            <span><?php echo htmlspecialchars($user_data['id']); ?></span>
        </div>
        
        <div class="user-detail">
            <span>Tipo de Membresía:</span>
            <span>
                <span class="membership-badge membership-<?php echo htmlspecialchars($user_data['membership_type']); ?>">
                    <?php if ($user_data['membership_type'] === 'premium'): ?>
                        <i class="fas fa-crown"></i>
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                    <?php echo ucfirst(htmlspecialchars($user_data['membership_type'])); ?>
                </span>
            </span>
        </div>
        
        <div class="user-detail">
            <span>Miembro desde:</span>
            <span><?php echo date('d/m/Y', strtotime($user_data['created_at'])); ?></span>
        </div>
        
        <?php if ($user_data['membership_type'] === 'free'): ?>
            <div style="margin-top: 1.5rem;">
                <a href="membership.php" class="btn btn-primary">
                    <i class="fas fa-crown"></i>
                    Upgrade a Premium
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para la previsualización y confirmación de la imagen -->
<div id="avatarModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:1000; justify-content:center; align-items:center;">
    <div style="background:var(--bg-secondary); padding:2rem; border-radius:12px; text-align:center; box-shadow:var(--shadow-lg); max-width:500px; width:90%;">
        <h3 style="color:var(--text-primary); margin-bottom:1.5rem;">Previsualización de Avatar</h3>
        <img id="modalAvatarPreview" src="" alt="Previsualización" style="max-width:100%; max-height:300px; border-radius:8px; margin-bottom:1.5rem; object-fit:contain; border-radius: 50%;">
        <p style="color:var(--text-secondary); margin-bottom:1.5rem;">¿Deseas confirmar la subida de esta imagen como tu nuevo avatar?</p>
        <div style="display:flex; justify-content:center; gap:1rem;">
            <button id="confirmAvatarBtn" class="btn btn-primary" style="width:auto;">Confirmar</button>
            <button id="cancelAvatarBtn" class="btn" style="width:auto; background-color:var(--border-color); color:var(--text-primary);">Cancelar</button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const avatarFileInput = document.getElementById('avatarFileInput');
        const fileNameDisplay = document.getElementById('fileNameDisplay');
        const avatarModal = document.getElementById('avatarModal');
        const modalAvatarPreview = document.getElementById('modalAvatarPreview');
        const confirmAvatarBtn = document.getElementById('confirmAvatarBtn');
        const cancelAvatarBtn = document.getElementById('cancelAvatarBtn');
        const avatarUploadForm = document.getElementById('avatarUploadForm');

        // Cuando se selecciona un archivo
        if (avatarFileInput) {
            avatarFileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        modalAvatarPreview.src = e.target.result;
                        fileNameDisplay.textContent = avatarFileInput.files[0].name; // Update displayed file name
                        avatarModal.style.display = 'flex'; // Show modal
                    };
                    reader.readAsDataURL(this.files[0]);
                } else {
                    fileNameDisplay.textContent = 'Sin archivos seleccionados';
                }
            });
        }

        // Confirmar subida
        if (confirmAvatarBtn) {
            confirmAvatarBtn.addEventListener('click', function() {
                avatarModal.style.display = 'none'; // Hide modal
                // Create a hidden input to signal PHP to process the upload
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'confirm_avatar_upload'; // This name triggers the PHP logic
                hiddenInput.value = '1';
                avatarUploadForm.appendChild(hiddenInput);
                avatarUploadForm.submit(); // Submit the form
            });
        }

        // Cancelar subida
        if (cancelAvatarBtn) {
            cancelAvatarBtn.addEventListener('click', function() {
                avatarModal.style.display = 'none'; // Hide modal
                avatarFileInput.value = ''; // Clear the selected file
                fileNameDisplay.textContent = 'Sin archivos seleccionados'; // Reset text
            });
        }

        // Script para el cambio de tema con botones
        const lightThemeButton = document.getElementById('lightThemeButton');
        const darkThemeButton = document.getElementById('darkThemeButton');
        const body = document.body;

        function updateThemeButtons(currentTheme) {
            lightThemeButton.classList.remove('active');
            darkThemeButton.classList.remove('active');
            if (currentTheme === 'dark') {
                darkThemeButton.classList.add('active');
            } else {
                lightThemeButton.classList.add('active');
            }
        }

        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) {
            body.setAttribute('data-theme', savedTheme);
            updateThemeButtons(savedTheme);
        } else {
            updateThemeButtons(body.getAttribute('data-theme'));
        }

        if (lightThemeButton) {
            lightThemeButton.addEventListener('click', function(event) {
                event.preventDefault(); 
                body.setAttribute('data-theme', 'light');
                localStorage.setItem('theme', 'light');
                updateThemeButtons('light');
                fetch('<?php echo BASE_URL; ?>update_theme.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ theme: 'light' })
                }).then(response => {
                    if (!response.ok) {
                        console.error('Error al actualizar el tema en el servidor.');
                    }
                }).catch(error => console.error('Error de red al actualizar el tema:', error));
            });
        }

        if (darkThemeButton) {
            darkThemeButton.addEventListener('click', function(event) {
                event.preventDefault(); 
                body.setAttribute('data-theme', 'dark');
                localStorage.setItem('theme', 'dark');
                updateThemeButtons('dark');
                fetch('<?php echo BASE_URL; ?>update_theme.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ theme: 'dark' })
                }).then(response => {
                    if (!response.ok) {
                        console.error('Error al actualizar el tema en el servidor.');
                    }
                }).catch(error => console.error('Error de red al actualizar el tema:', error));
            });
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>
