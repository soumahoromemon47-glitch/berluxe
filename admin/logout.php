<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Méthode non autorisée.');
}

if (!is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('La demande a expiré. Recharge la page et réessaie.');
}

destroy_app_session();
admin_redirect('admin/login.php');
