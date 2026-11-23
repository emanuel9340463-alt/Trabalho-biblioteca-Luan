<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/seguranca.php';

$senha = rtrim(stream_get_contents(STDIN), "\r\n");
if ($senha === '' || strlen($senha) > 4096) {
    fwrite(STDERR, 'Informe uma senha válida pelo terminal.' . PHP_EOL);
    exit(1);
}

$hash = password_hash($senha, algoritmoHashSenha());
if (!is_string($hash)) {
    fwrite(STDERR, 'Não foi possível gerar o hash da senha.' . PHP_EOL);
    exit(1);
}

unset($senha);
fwrite(STDOUT, $hash . PHP_EOL);
