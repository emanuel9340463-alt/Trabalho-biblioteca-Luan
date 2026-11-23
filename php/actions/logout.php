<?php
require_once __DIR__ . '/../../config/seguranca.php';
exigirAutenticacao();
iniciarSessaoSegura();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validarTokenCsrf($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Não foi possível encerrar a sessão. Atualize a página e tente novamente.');
}

$_SESSION = [];
$parametrosCookie = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires' => time() - 42000,
    'path' => $parametrosCookie['path'],
    'domain' => $parametrosCookie['domain'],
    'secure' => $parametrosCookie['secure'],
    'httponly' => $parametrosCookie['httponly'],
    'samesite' => 'Lax',
]);
session_destroy();

header('Location: ../../login.php');
exit;
