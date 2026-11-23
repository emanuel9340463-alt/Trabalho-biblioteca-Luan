<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/conexao.php';

$email = isset($argv[1]) && is_string($argv[1]) ? trim($argv[1]) : '';
$senha = rtrim(stream_get_contents(STDIN), "\r\n");

if (
    filter_var($email, FILTER_VALIDATE_EMAIL) === false ||
    strlen($email) > 255 ||
    $senha === '' ||
    strlen($senha) > 4096
) {
    unset($senha);
    fwrite(STDERR, 'Informe um e-mail válido e a senha pelo terminal.' . PHP_EOL);
    exit(1);
}

try {
    $stmt = $conn->prepare('SELECT senha FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $corresponde = $usuario && password_verify($senha, $usuario['senha']);
    unset($senha, $usuario);

    fwrite(
        STDOUT,
        $corresponde
            ? 'A senha corresponde ao hash no banco usado pela aplicação.' . PHP_EOL
            : 'A senha não corresponde ao hash no banco usado pela aplicação.' . PHP_EOL
    );
} catch (Throwable $erro) {
    unset($senha);
    error_log('Falha na verificação local de senha (código ' . (int) $erro->getCode() . ').');
    fwrite(STDERR, 'Não foi possível verificar. Confira a conexão e o log do PHP.' . PHP_EOL);
    exit(1);
}
