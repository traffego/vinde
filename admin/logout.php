<?php
require_once '../includes/init.php';
require_once '../includes/auth_participante.php';

// Fazer logout de participante (se houver) e admin
participante_fazer_logout();
fazer_logout();

// Redirecionar para página de login
redirecionar(SITE_URL . '/admin/login.php');
?> 