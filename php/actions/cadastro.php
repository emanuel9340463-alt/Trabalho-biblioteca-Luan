<?php
require_once __DIR__ . '/../../config/seguranca.php';
iniciarSessaoSegura();

function falharCadastro($motivo)
{
    header('Location: ../../cadastro.php?erro=' . rawurlencode($motivo));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log('Cadastro rejeitado: método HTTP inválido.');
    falharCadastro('sessao');
}

if (!validarTokenCsrf($_POST['csrf_token'] ?? null)) {
    error_log('Cadastro rejeitado: token CSRF inválido.');
    falharCadastro('sessao');
}

$nome = isset($_POST['nome']) && is_string($_POST['nome']) ? trim($_POST['nome']) : '';
$telefone = isset($_POST['telefone']) && is_string($_POST['telefone']) ? trim($_POST['telefone']) : '';
$email = isset($_POST['email']) && is_string($_POST['email']) ? strtolower(trim($_POST['email'])) : '';
$senha = isset($_POST['senha']) && is_string($_POST['senha']) ? $_POST['senha'] : '';
$quantidadeDigitosTelefone = preg_match_all('/\d/', $telefone);

$formularioValido = textoTamanhoValido($nome, 2, 150)
    && strlen($telefone) <= 30
    && preg_match('/\A[0-9+().\-\s]+\z/', $telefone) === 1
    && $quantidadeDigitosTelefone >= 8
    && $quantidadeDigitosTelefone <= 15
    && strlen($email) <= 255
    && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
    && strlen($senha) >= 12
    && strlen($senha) <= 4096;

if (!$formularioValido) {
    error_log('Cadastro rejeitado: dados de formulário inválidos.');
    falharCadastro('validacao');
}

require_once __DIR__ . '/../../config/conexao.php';

try {
    $hash = password_hash($senha, algoritmoHashSenha());
    if (!is_string($hash)) {
        throw new RuntimeException('Não foi possível gerar o hash da senha.');
    }

    $stmt = $conn->prepare('INSERT INTO usuarios (nome, telefone, email, senha) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('ssss', $nome, $telefone, $email, $hash);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    header('Location: ../../login.php?cadastro=sucesso');
    exit;
} catch (mysqli_sql_exception $erro) {
    if ((int) $erro->getCode() === 1062) {
        falharCadastro('email');
    }

    error_log('Falha ao criar conta da biblioteca (código ' . (int) $erro->getCode() . ').');
    falharCadastro('indisponivel');
} catch (Throwable $erro) {
    error_log('Falha ao criar conta da biblioteca (código ' . (int) $erro->getCode() . ').');
    falharCadastro('indisponivel');
}
