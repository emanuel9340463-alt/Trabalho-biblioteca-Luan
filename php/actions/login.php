<?php
require_once __DIR__ . '/../../config/seguranca.php';
iniciarSessaoSegura();

function falharLogin()
{
    header('Location: ../../login.php?erro=credenciais');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validarTokenCsrf($_POST['csrf_token'] ?? null)) {
    falharLogin();
}

$email = isset($_POST['email']) && is_string($_POST['email']) ? trim($_POST['email']) : '';
$senha = isset($_POST['senha']) && is_string($_POST['senha']) ? $_POST['senha'] : '';
$chavesTentativas = chavesTentativaLogin($email);

require_once __DIR__ . '/../../config/conexao.php';

try {
    if (loginTemBloqueio($conn, $chavesTentativas)) {
        falharLogin();
    }

    if (
        $email === '' ||
        strlen($email) > 255 ||
        filter_var($email, FILTER_VALIDATE_EMAIL) === false ||
        $senha === '' ||
        strlen($senha) > 4096
    ) {
        registrarFalhaLogin($conn, $chavesTentativas);
        falharLogin();
    }

    $stmt = $conn->prepare('SELECT id, email, senha FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // O hash fictício aproxima o custo de validação quando o e-mail não existe.
    $hashArmadilha = '$2y$10$mgA0UvFzklsdGtiXmefiE./G5gff7RxdvW/K.x7rF5XmjK.7Jr68K';
    if (defined('PASSWORD_ARGON2ID') && algoritmoHashSenha() === PASSWORD_ARGON2ID) {
        $hashArmadilha = '$argon2id$v=19$m=65536,t=4,p=1$R0YzT0hMbzFWdXhMR2tnYQ$GJNltT2Bl+VHQ3/9bILclsh8rbiBkOS39WzSTmfEsFU';
    }

    $senhaValida = $usuario
        ? password_verify($senha, $usuario['senha'])
        : password_verify($senha, $hashArmadilha);

    if (!$usuario || !$senhaValida) {
        registrarFalhaLogin($conn, $chavesTentativas);
        falharLogin();
    }

    if (password_needs_rehash($usuario['senha'], algoritmoHashSenha())) {
        $hash = password_hash($senha, algoritmoHashSenha());
        $atualizar = $conn->prepare('UPDATE usuarios SET senha = ? WHERE id = ?');
        $atualizar->bind_param('si', $hash, $usuario['id']);
        $atualizar->execute();
        $atualizar->close();
    }

    limparTentativasLogin($conn, $chavesTentativas);
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = (int) $usuario['id'];
    $_SESSION['usuario_email'] = $usuario['email'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    header('Location: ../../index.php');
    exit;
} catch (Throwable $erro) {
    error_log('Falha no processo de autenticação (código ' . (int) $erro->getCode() . ').');
    falharLogin();
}
