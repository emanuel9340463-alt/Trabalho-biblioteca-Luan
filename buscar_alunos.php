<?php
require_once 'config/conexao.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$termo = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';

if ($termo === '') {
    echo json_encode(['alunos' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$sql = "SELECT id, nome, turma
        FROM alunos
        WHERE nome LIKE CONCAT('%', ?, '%')
           OR turma LIKE CONCAT('%', ?, '%')
        ORDER BY nome
        LIMIT 25";

try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new mysqli_sql_exception($conn->error);
    }

    if (!$stmt->bind_param('ss', $termo, $termo)) {
        throw new mysqli_sql_exception($stmt->error);
    }
    if (!$stmt->execute()) {
        throw new mysqli_sql_exception($stmt->error);
    }

    $resultado = $stmt->get_result();
    $alunos = $resultado->fetch_all(MYSQLI_ASSOC);

    $stmt->close();
    $conn->close();

    echo json_encode(['alunos' => $alunos], JSON_UNESCAPED_UNICODE);
} catch (mysqli_sql_exception $erro) {
    error_log('Erro ao pesquisar alunos para empréstimo: ' . $erro->getMessage());
    http_response_code(500);
    echo json_encode(
        ['erro' => 'Não foi possível pesquisar alunos. Tente novamente.'],
        JSON_UNESCAPED_UNICODE
    );
}
