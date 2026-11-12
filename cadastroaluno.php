<?php
require_once 'config/conexao.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Captura dos dados enviados pelo formulário
    $nome            = isset($_POST['nome']) ? trim($_POST['nome']) : '';
    $data_nascimento = isset($_POST['data_nascimento']) ? trim($_POST['data_nascimento']) : (isset($_POST['nascimento']) ? trim($_POST['nascimento']) : '');
    $turma           = isset($_POST['turma']) ? trim($_POST['turma']) : '';

    // Validação dos campos obrigatórios
    if (!empty($nome) && !empty($data_nascimento) && !empty($turma)) {
        
        // Verifica se o aluno já está cadastrado
        $sqlVerifica = "SELECT id FROM alunos WHERE nome = ? AND data_nascimento = ?";
        $stmtVerifica = $conn->prepare($sqlVerifica);

        if (!$stmtVerifica) {
            echo "Erro na consulta de verificação: " . $conn->error;
            exit;
        }

        $stmtVerifica->bind_param("ss", $nome, $data_nascimento);
        $stmtVerifica->execute();
        $resultado = $stmtVerifica->get_result();

        if ($resultado->num_rows > 0) {
            echo "Este aluno já está cadastrado.";
        } else {
            // Insere o novo aluno na tabela 'alunos'
            $sql = "INSERT INTO alunos (nome, data_nascimento, turma) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                echo "Erro na consulta de inserção: " . $conn->error;
                exit;
            }

            $stmt->bind_param("sss", $nome, $data_nascimento, $turma);

            if ($stmt->execute()) {
                echo "sucesso";
            } else {
                echo "Erro ao cadastrar: " . $stmt->error;
            }
            $stmt->close();
        }
        $stmtVerifica->close();
    } else {
        echo "Por favor, preencha todos os campos obrigatórios.";
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