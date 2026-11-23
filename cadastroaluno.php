<?php
// Este bloco processa o formulário por POST e devolve texto simples ao JavaScript.
require_once __DIR__ . '/config/seguranca.php';
exigirAutenticacao();
require_once 'config/conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        echo 'Sua sessão expirou. Atualize a página e tente novamente.';
        exit;
    }

    // Captura os dados sem aceitar arrays ou outros tipos inesperados.
    $nome = isset($_POST['nome']) && is_string($_POST['nome']) ? trim($_POST['nome']) : '';
    $data_nascimento = isset($_POST['data_nascimento']) && is_string($_POST['data_nascimento'])
        ? trim($_POST['data_nascimento'])
        : (isset($_POST['nascimento']) && is_string($_POST['nascimento']) ? trim($_POST['nascimento']) : '');
    $turma = isset($_POST['turma']) && is_string($_POST['turma']) ? trim($_POST['turma']) : '';

    if (
        !textoTamanhoValido($nome, 1, 100) ||
        !dataIsoValida($data_nascimento) ||
        $data_nascimento > date('Y-m-d') ||
        !textoTamanhoValido($turma, 1, 5)
    ) {
        echo 'Confira o nome, a data de nascimento e a turma informados.';
        exit;
    }

    try {
        $stmtVerifica = $conn->prepare(
            'SELECT id FROM alunos WHERE nome = ? AND data_nascimento = ?'
        );
        if (!$stmtVerifica) {
            throw new mysqli_sql_exception($conn->error);
        }

        $stmtVerifica->bind_param('ss', $nome, $data_nascimento);
        if (!$stmtVerifica->execute()) {
            throw new mysqli_sql_exception($stmtVerifica->error);
        }
        $jaCadastrado = $stmtVerifica->get_result()->num_rows > 0;
        $stmtVerifica->close();

        if ($jaCadastrado) {
            echo 'Este aluno já está cadastrado.';
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO alunos (nome, data_nascimento, turma) VALUES (?, ?, ?)'
            );
            if (!$stmt) {
                throw new mysqli_sql_exception($conn->error);
            }

            $stmt->bind_param('sss', $nome, $data_nascimento, $turma);
            if (!$stmt->execute()) {
                throw new mysqli_sql_exception($stmt->error);
            }
            $stmt->close();
            echo 'sucesso';
        }
    } catch (Throwable $erro) {
        error_log('Falha ao processar cadastro de aluno: ' . $erro->getMessage());
        http_response_code(500);
        echo 'Não foi possível cadastrar o aluno. Tente novamente.';
    }

    $conn->close();
    exit; // Interrompe a execução para enviar apenas o resultado ao JS
}
?>
<?php
$pageTitle = 'Cadastrar aluno';
$pageDescription = 'Inclua novos leitores e mantenha os dados das turmas organizados.';
$activePage = 'alunos';
require 'includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-12 col-xl-8">
        <section class="surface-card">
            <div class="mb-4">
                <h2 class="surface-title">Dados do aluno</h2>
                <p class="surface-subtitle">Preencha as informações para registrar um novo leitor.</p>
            </div>
            <form id="alunoForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="mb-3">
                    <label for="nome" class="form-label">Nome completo</label>
                    <input type="text" class="form-control" id="nome" name="nome" placeholder="Ex.: Maria da Silva" autocomplete="name" maxlength="100" required>
                </div>
                <div class="row">
                    <div class="col-12 col-md-7 mb-3">
                        <label for="data_nascimento" class="form-label">Data de nascimento</label>
                        <input type="date" class="form-control" id="data_nascimento" name="data_nascimento" required>
                    </div>
                    <div class="col-12 col-md-5 mb-3">
                        <label for="turma" class="form-label">Turma</label>
                        <input type="text" class="form-control" id="turma" name="turma" placeholder="Ex.: 2A" maxlength="5" required>
                    </div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                    <button type="submit" class="btn btn-primary px-4">Cadastrar aluno</button>
                    <a href="index.php" class="btn btn-outline-primary">Voltar ao painel</a>
                </div>
                <div id="mensagem" class="mt-3" aria-live="polite"></div>
            </form>
        </section>
    </div>
</div>
<script src="js/cadastro_aluno.js?v=2"></script>
<?php require 'includes/footer.php'; ?>