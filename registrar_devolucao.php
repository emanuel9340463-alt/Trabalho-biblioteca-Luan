<?php
require_once 'config/conexao.php';

// Aceitar somente requisições POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: emprestimos.php');
    exit;
}

// Receber o ID do empréstimo
$emprestimoId = filter_input(INPUT_POST, 'emprestimo_id', FILTER_VALIDATE_INT);

if (!$emprestimoId || $emprestimoId <= 0) {
    header('Location: emprestimos.php?resultado=dados_invalidos');
    exit;
}

try {
    // Iniciar transação
    $conn->begin_transaction();

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

    header('Location: emprestimos.php?resultado=devolucao_sucesso');
    exit;

} catch (Throwable $erro) {
    $conn->rollback();

    header('Location: emprestimos.php?resultado=erro');
    exit;
}