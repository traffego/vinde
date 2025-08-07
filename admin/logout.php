<?php
require_once '../includes/init.php';

// Fazer logout
fazer_logout();

// Redirecionar para página de login
redirecionar(SITE_URL . '/admin/login.php');
?> 