<?php
require_once 'config/conexao.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Captura dos dados enviados pelo formulário
    $nome            = isset($_POST['nome']) ? trim($_POST['nome']) : '';
    $data_nascimento = isset($_POST['data_nascimento']) ? trim($_POST['data_nascimento']) : '';
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

<!doctype html>
<html lang="pt-br">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cadastro de Aluno</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
  </head>
  <body>
    <div class="container">
        <div class="row vh-100 justify-content-center align-items-center">
            <div class="col-lg-10">
                <div class="card login-card shadow-lg border-0 overflow-hidden">
                    <div class="row g-0">
                        
                        <div class="col-md-6 d-none d-md-block">
                            <div class="login-image">
                                <img src="img/cadastro.png" width="400" alt="Imagem de Cadastro">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card-body p-5">
                                <h2 class="text-center mb-4">Cadastrar Alunos</h2>

                                <!-- Formulário ajustado -->
                                <form id="alunoForm">
                                    <div class="mb-3">
                                        <label for="nome" class="form-label">Nome</label>
                                        <input type="text" class="form-control" id="nome" name="nome" placeholder="Digite seu nome" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="nascimento" class="form-label">Data de Nascimento</label>
                                        <input type="date" class="form-control" id="nascimento" name="nascimento" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="turma" class="form-label">Turma</label>
                                        <input type="text" class="form-control" id="turma" name="turma" placeholder="Digite sua turma" required>
                                    </div>

                                    <div class="d-grid mt-4">
                                        <button type="submit" class="btn btn-primary btn-lg">Cadastrar Aluno</button>
                                    </div>

                                    <!-- Div necessária para exibir o retorno do JS -->
                                    <div id="mensagem" class="text-center"></div>
                                </form>

                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/cadastro_aluno.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>