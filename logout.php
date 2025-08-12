<?php
require_once 'includes/config.php';

// Destruir todas las variables de sesión
session_unset();

// Destruir la sesión
session_destroy();

// Redireccionar al login
redirect('login.php');
?>