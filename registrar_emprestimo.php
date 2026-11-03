```php
<?php
require_once 'config/conexao.php';

// Só permite acesso por envio de formulário POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: emprestimos.php');
    exit;
}

// Receber os dados do formulário
$alunoId = filter_input(INPUT_POST, 'aluno_id', FILTER_VALIDATE_INT);
$livroId = filter_input(INPUT_POST, 'livro_id', FILTER_VALIDATE_INT);

$dataEmprestimo = $_POST['data_emprestimo'] ?? '';
$dataDevolucao = $_POST['data_devolucao'] ?? '';

// Validar os IDs
if (!$alunoId || $alunoId <= 0 || !$livroId || $livroId <= 0) {
    header('Location: emprestimos.php?resultado=dados_invalidos');
    exit;
}

// Validar as datas
$dataEmprestimoObj = DateTime::createFromFormat('!Y-m-d', $dataEmprestimo);
$dataDevolucaoObj = DateTime::createFromFormat('!Y-m-d', $dataDevolucao);

if (
    !$dataEmprestimoObj ||
    $dataEmprestimoObj->format('Y-m-d') !== $dataEmprestimo ||
    !$dataDevolucaoObj ||
    $dataDevolucaoObj->format('Y-m-d') !== $dataDevolucao ||
    $dataDevolucao < $dataEmprestimo
) {
    header('Location: emprestimos.php?resultado=datas_invalidas');
    exit;
}

try {
    // Iniciar uma transação
    $conn->begin_transaction();

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

    header('Location: emprestimos.php?resultado=sucesso');
    exit;

} catch (Throwable $erro) {
    // Desfazer alterações caso algo dê errado
    $conn->rollback();

    header('Location: emprestimos.php?resultado=erro');
    exit;
}