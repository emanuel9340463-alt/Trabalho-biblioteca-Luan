<?php

require_once 'config/conexao.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['livro_id']) || !ctype_digit($_GET['livro_id'])) {
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

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao preparar consulta.']);
    exit;
}

$stmt->bind_param('i', $livroId);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao consultar empréstimos.']);
    exit;
}

$resultado = $stmt->get_result();

$alunos = [];

while ($aluno = $resultado->fetch_assoc()) {
    $alunos[] = $aluno;
}

echo json_encode($alunos, JSON_UNESCAPED_UNICODE);

$stmt->close();
$conn->close();