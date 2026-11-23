<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/conexao.php';

$transacaoIniciada = false;

try {
    $conn->begin_transaction();
    $transacaoIniciada = true;

    $resultado = $conn->query('SELECT id, senha FROM usuarios ORDER BY id');
    $atualizar = $conn->prepare('UPDATE usuarios SET senha = ? WHERE id = ?');
    $migradas = 0;

    while ($usuario = $resultado->fetch_assoc()) {
        $informacaoHash = password_get_info($usuario['senha']);
        $jaEhHash = isset($informacaoHash['algoName'])
            ? $informacaoHash['algoName'] !== 'unknown'
            : !empty($informacaoHash['algo']);

        if ($jaEhHash) {
            continue;
        }

        $hash = password_hash($usuario['senha'], algoritmoHashSenha());
        if (!is_string($hash)) {
            throw new RuntimeException('Falha ao gerar hash de senha.');
        }

        $id = (int) $usuario['id'];
        $atualizar->bind_param('si', $hash, $id);
        $atualizar->execute();
        $migradas++;
    }

    $atualizar->close();
    $resultado->free();
    $conn->commit();
    $transacaoIniciada = false;

    fwrite(STDOUT, 'Migração concluída. Contas atualizadas: ' . $migradas . PHP_EOL);
} catch (Throwable $erro) {
    if ($transacaoIniciada) {
        $conn->rollback();
    }
    error_log('Falha na migração de senhas (código ' . (int) $erro->getCode() . ').');
    fwrite(STDERR, 'Migração não concluída. Verifique a configuração e o log do PHP.' . PHP_EOL);
    exit(1);
}
