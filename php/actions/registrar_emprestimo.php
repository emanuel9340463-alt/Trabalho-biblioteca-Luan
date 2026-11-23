<?php
require_once __DIR__ . '/../../config/seguranca.php';
exigirAutenticacao();
require_once __DIR__ . '/../../config/conexao.php';

// Só permite acesso por envio de formulário POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../emprestimos.php?resultado=dados_invalidos');
    exit;
}

if (!validarTokenCsrf($_POST['csrf_token'] ?? null)) {
    header('Location: ../../emprestimos.php?resultado=csrf_invalido');
    exit;
}

// Receber os dados do formulário
$alunoId = isset($_POST['aluno_id']) && is_string($_POST['aluno_id'])
    ? filter_var($_POST['aluno_id'], FILTER_VALIDATE_INT)
    : false;
$livroId = isset($_POST['livro_id']) && is_string($_POST['livro_id'])
    ? filter_var($_POST['livro_id'], FILTER_VALIDATE_INT)
    : false;
$dataEmprestimo = isset($_POST['data_emprestimo']) && is_string($_POST['data_emprestimo'])
    ? $_POST['data_emprestimo'] : '';
$dataDevolucao = isset($_POST['data_devolucao']) && is_string($_POST['data_devolucao'])
    ? $_POST['data_devolucao'] : '';

// Validar os IDs
if (
    !$alunoId || $alunoId <= 0 || $alunoId > 2147483647 ||
    !$livroId || $livroId <= 0 || $livroId > 2147483647
) {
    header('Location: ../../emprestimos.php?resultado=dados_invalidos');
    exit;
}

// Validar as datas
if (!datasEmprestimoValidas($dataEmprestimo, $dataDevolucao)) {
    header('Location: ../../emprestimos.php?resultado=datas_invalidas');
    exit;
}

$transacaoIniciada = false;
try {
    // Iniciar uma transação
    $conn->begin_transaction();
    $transacaoIniciada = true;

    // Verificar se o aluno existe
    $sqlAluno = "SELECT id FROM alunos WHERE id = ?";
    $stmtAluno = $conn->prepare($sqlAluno);
    $stmtAluno->bind_param("i", $alunoId);
    $stmtAluno->execute();

    if ($stmtAluno->get_result()->num_rows === 0) {
        throw new Exception("Aluno não encontrado.");
    }

    $stmtAluno->close();

    // Buscar e bloquear o registro do livro
    $sqlLivro = "SELECT quantidade FROM livros WHERE id = ? FOR UPDATE";
    $stmtLivro = $conn->prepare($sqlLivro);
    $stmtLivro->bind_param("i", $livroId);
    $stmtLivro->execute();

    $resultadoLivro = $stmtLivro->get_result();
    $livro = $resultadoLivro->fetch_assoc();

    if (!$livro) {
        throw new Exception("Livro não encontrado.");
    }

    $quantidade = (int) $livro['quantidade'];
    $stmtLivro->close();

    // Contar empréstimos que ainda não foram devolvidos
    $sqlAtivos = "SELECT COUNT(*) AS total
                  FROM emprestimos
                  WHERE livro_id = ?
                  AND data_devolucao_real IS NULL";

    $stmtAtivos = $conn->prepare($sqlAtivos);
    $stmtAtivos->bind_param("i", $livroId);
    $stmtAtivos->execute();

    $totalAtivos = (int) $stmtAtivos->get_result()->fetch_assoc()['total'];
    $stmtAtivos->close();

    // Verificar se ainda existe exemplar disponível
    if ($totalAtivos >= $quantidade) {
        throw new Exception("Não existem exemplares disponíveis.");
    }

    // Registrar o empréstimo
    $sqlInserir = "INSERT INTO emprestimos
                   (aluno_id, livro_id, data_emprestimo, data_devolucao)
                   VALUES (?, ?, ?, ?)";

    $stmtInserir = $conn->prepare($sqlInserir);
    $stmtInserir->bind_param(
        "iiss",
        $alunoId,
        $livroId,
        $dataEmprestimo,
        $dataDevolucao
    );

    $stmtInserir->execute();
    $stmtInserir->close();

    // Confirmar as alterações
    $conn->commit();

    header('Location: ../../emprestimos.php?resultado=sucesso');
    exit;

} catch (Throwable $erro) {
    // Desfazer alterações caso algo dê errado
    if ($transacaoIniciada) {
        $conn->rollback();
    }
    error_log('Falha ao registrar empréstimo: ' . $erro->getMessage());

    header('Location: ../../emprestimos.php?resultado=erro');
    exit;
}