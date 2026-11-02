<?php

require_once __DIR__ . '/../config/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "erro";
    exit;
}

$email = isset($_POST['email']) ? trim((string) $_POST['email']) : '';
$senha = isset($_POST['senha']) ? (string) $_POST['senha'] : '';

if ($email === '' || $senha === '') {
    echo "erro";
    exit;
}

$stmt = $conn->prepare('SELECT id FROM usuarios WHERE email = ? AND senha = ?');

if (!$stmt) {
    echo "erro";
    exit;
}

$stmt->bind_param('ss', $email, $senha);
$stmt->execute();
$resultado = $stmt->get_result();

echo $resultado->num_rows > 0 ? "sucesso" : "erro";

$stmt->close();
$conn->close();
?>