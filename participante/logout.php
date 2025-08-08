<?php
require_once '../includes/init.php';
require_once '../includes/auth_participante.php';

// Fazer logout de participante e admin (se houver)
participante_fazer_logout();
fazer_logout();

// Redirecionar para login
redirecionar(SITE_URL . '/participante/login.php');
?> 