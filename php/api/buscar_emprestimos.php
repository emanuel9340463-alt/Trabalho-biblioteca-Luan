<?php

require_once __DIR__ . '/../../config/seguranca.php';
exigirAutenticacao(true);
require_once __DIR__ . '/../../config/conexao.php';

// Lista os alunos que possuem empréstimos ativos do livro informado.
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_GET['livro_id']) || !is_string($_GET['livro_id']) || !ctype_digit($_GET['livro_id'])) {
    http_response_code(400);
    echo json_encode(['erro' => 'ID do livro inválido.']);
    exit;
}

$livroId = (int) $_GET['livro_id'];

$sql = "SELECT
            alunos.nome,
            alunos.turma,
            emprestimos.data_emprestimo,
            emprestimos.data_devolucao
        FROM emprestimos
        INNER JOIN alunos
            ON emprestimos.aluno_id = alunos.id
        WHERE emprestimos.livro_id = ?
          AND emprestimos.data_devolucao_real IS NULL";

try {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $livroId);
    $stmt->execute();
    $alunos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();

    echo json_encode($alunos, JSON_UNESCAPED_UNICODE);
} catch (Throwable $erro) {
    error_log('Falha ao consultar empréstimos ativos do livro: ' . $erro->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Não foi possível consultar os empréstimos. Tente novamente.'], JSON_UNESCAPED_UNICODE);
}