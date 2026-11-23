<?php
require_once __DIR__ . '/../../config/seguranca.php';
exigirAutenticacao();
require_once __DIR__ . '/../../config/conexao.php';

// Aceitar somente requisições POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../emprestimos.php?resultado=dados_invalidos');
    exit;
}

if (!validarTokenCsrf($_POST['csrf_token'] ?? null)) {
    header('Location: ../../emprestimos.php?resultado=csrf_invalido');
    exit;
}

// Receber o ID do empréstimo
$emprestimoId = isset($_POST['emprestimo_id']) && is_string($_POST['emprestimo_id'])
    ? filter_var($_POST['emprestimo_id'], FILTER_VALIDATE_INT)
    : false;

if (!$emprestimoId || $emprestimoId <= 0 || $emprestimoId > 2147483647) {
    header('Location: ../../emprestimos.php?resultado=dados_invalidos');
    exit;
}

 $transacaoIniciada = false;
try {
    // Iniciar transação
    $conn->begin_transaction();
    $transacaoIniciada = true;

    // Verificar se o empréstimo existe e ainda está ativo
    $sql = "SELECT id
            FROM emprestimos
            WHERE id = ?
              AND data_devolucao_real IS NULL
            FOR UPDATE";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $emprestimoId);
    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 0) {
        throw new Exception("Empréstimo não encontrado ou já devolvido.");
    }

    $stmt->close();

    // Registrar a data real da devolução
    $sqlAtualizar = "UPDATE emprestimos
                     SET data_devolucao_real = CURDATE()
                     WHERE id = ?
                       AND data_devolucao_real IS NULL";

    $stmtAtualizar = $conn->prepare($sqlAtualizar);
    $stmtAtualizar->bind_param("i", $emprestimoId);
    $stmtAtualizar->execute();
    $stmtAtualizar->close();

    // Confirmar a operação
    $conn->commit();
    $transacaoIniciada = false;

    header('Location: ../../emprestimos.php?resultado=devolucao_sucesso');
    exit;

} catch (Throwable $erro) {
    if ($transacaoIniciada) {
        $conn->rollback();
    }
    error_log('Falha ao registrar devolução: ' . $erro->getMessage());

    header('Location: ../../emprestimos.php?resultado=erro');
    exit;
}