<?php
require_once '../includes/init.php';
require_once '../includes/auth_participante.php';

// Fazer logout
participante_fazer_logout();

// Redirecionar para login
redirecionar(SITE_URL . '/participante/login.php');
?> 