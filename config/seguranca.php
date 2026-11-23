<?php

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');

function iniciarSessaoSegura()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    $https = $https || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

    session_name('biblioteca_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function tokenCsrf()
{
    iniciarSessaoSegura();

    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function validarTokenCsrf($token)
{
    iniciarSessaoSegura();

    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function algoritmoHashSenha()
{
    if (
        defined('PASSWORD_ARGON2ID') &&
        function_exists('password_algos') &&
        in_array('argon2id', password_algos(), true)
    ) {
        return PASSWORD_ARGON2ID;
    }

    return PASSWORD_DEFAULT;
}

function chavesTentativaLogin($email)
{
    $ip = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])
        ? $_SERVER['REMOTE_ADDR'] : '';
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
        $ip = 'desconhecido';
    }

    $email = is_string($email) ? strtolower(trim($email)) : '';
    $email = substr($email, 0, 255);

    return [
        hash('sha256', 'ip:' . $ip),
        hash('sha256', 'email:' . $email),
    ];
}

function loginTemBloqueio(mysqli $conn, array $chaves)
{
    $stmt = $conn->prepare(
        'SELECT 1 FROM login_tentativas WHERE chave_hash = ? AND bloqueado_ate > NOW() LIMIT 1'
    );

    foreach ($chaves as $chave) {
        $stmt->bind_param('s', $chave);
        $stmt->execute();
        $bloqueado = $stmt->get_result()->num_rows > 0;
        if ($bloqueado) {
            $stmt->close();
            return true;
        }
    }

    $stmt->close();
    return false;
}

function registrarFalhaLogin(mysqli $conn, array $chaves)
{
    $conn->query(
        'DELETE FROM login_tentativas WHERE ultima_tentativa < DATE_SUB(NOW(), INTERVAL 1 DAY)'
    );

    $upsert = $conn->prepare(
        'INSERT INTO login_tentativas (chave_hash, falhas, ultima_tentativa) ' .
        'VALUES (?, 1, NOW()) ' .
        'ON DUPLICATE KEY UPDATE ' .
        'falhas = IF(ultima_tentativa < DATE_SUB(NOW(), INTERVAL 1 HOUR), 1, LEAST(falhas + 1, 12)), ' .
        'ultima_tentativa = NOW()'
    );
    $consulta = $conn->prepare(
        'SELECT falhas FROM login_tentativas WHERE chave_hash = ?'
    );

    foreach ($chaves as $chave) {
        $upsert->bind_param('s', $chave);
        $upsert->execute();

        $consulta->bind_param('s', $chave);
        $consulta->execute();
        $falhas = (int) $consulta->get_result()->fetch_assoc()['falhas'];

        $segundosBloqueio = $falhas >= 12 ? 900 : ($falhas >= 8 ? 120 : ($falhas >= 5 ? 30 : 0));
        if ($segundosBloqueio > 0) {
            $atualizar = $conn->prepare(
                'UPDATE login_tentativas SET bloqueado_ate = DATE_ADD(NOW(), INTERVAL ' .
                (int) $segundosBloqueio . ' SECOND) WHERE chave_hash = ?'
            );
            $atualizar->bind_param('s', $chave);
            $atualizar->execute();
            $atualizar->close();
        }
    }

    $upsert->close();
    $consulta->close();
}

function limparTentativasLogin(mysqli $conn, array $chaves)
{
    $stmt = $conn->prepare('DELETE FROM login_tentativas WHERE chave_hash = ?');
    foreach ($chaves as $chave) {
        $stmt->bind_param('s', $chave);
        $stmt->execute();
    }
    $stmt->close();
}

function exigirAutenticacao($json = false)
{
    iniciarSessaoSegura();

    if (isset($_SESSION['usuario_id']) && (int) $_SESSION['usuario_id'] > 0) {
        return;
    }

    if ($json) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            ['erro' => 'Sua sessão expirou. Entre novamente para continuar.'],
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }

    $script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '';
    $prefixo = strpos($script, '/php/') !== false ? '../../' : '';
    header('Location: ' . $prefixo . 'login.php');
    exit;
}

function dataIsoValida($data)
{
    if (!is_string($data) || !preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $data)) {
        return false;
    }

    return checkdate((int) substr($data, 5, 2), (int) substr($data, 8, 2), (int) substr($data, 0, 4));
}

function datasEmprestimoValidas($dataEmprestimo, $dataDevolucao, $hoje = null)
{
    $hoje = $hoje === null ? date('Y-m-d') : $hoje;

    return dataIsoValida($dataEmprestimo)
        && dataIsoValida($dataDevolucao)
        && dataIsoValida($hoje)
        && $dataEmprestimo <= $hoje
        && $dataDevolucao >= $dataEmprestimo;
}

function textoTamanhoValido($texto, $minimo, $maximo)
{
    return is_string($texto)
        && preg_match('/\A.{'.$minimo.','.$maximo.'}\z/us', $texto) === 1;
}

function isbnValido($isbn)
{
    if (!is_string($isbn)) {
        return false;
    }

    if (preg_match('/\A[0-9Xx -]+\z/', $isbn) !== 1) {
        return false;
    }

    $isbn = strtoupper(str_replace([' ', '-'], '', $isbn));

    if (preg_match('/\A97[89]\d{10}\z/', $isbn)) {
        $soma = 0;
        for ($indice = 0; $indice < 12; $indice++) {
            $soma += (int) $isbn[$indice] * ($indice % 2 === 0 ? 1 : 3);
        }

        return (10 - ($soma % 10)) % 10 === (int) $isbn[12];
    }

    if (preg_match('/\A\d{9}[\dX]\z/', $isbn)) {
        $soma = 0;
        for ($indice = 0; $indice < 10; $indice++) {
            $digito = $isbn[$indice] === 'X' ? 10 : (int) $isbn[$indice];
            $soma += $digito * (10 - $indice);
        }

        return $soma % 11 === 0;
    }

    return false;
}
