<?php
require_once 'config/conexao.php';

$mensagem = "";
$erro = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Captura dos dados com verificação de existência
    $titulo       = isset($_POST['titulo']) ? trim($_POST['titulo']) : '';
    $autor        = isset($_POST['autor']) ? trim($_POST['autor']) : '';
    $categoria    = isset($_POST['genero']) ? trim($_POST['genero']) : '';
    $isbn         = isset($_POST['isbn']) ? trim($_POST['isbn']) : '';
    $codigo_bibli = isset($_POST['codigo_bibli']) ? trim($_POST['codigo_bibli']) : '';

    if (!empty($titulo) && !empty($isbn) && !empty($codigo_bibli)) {
        
        // Verifica se o livro já existe pelo título
        $sqlVerifica = "SELECT id FROM livros WHERE titulo = ?";
        $stmtVerifica = $conn->prepare($sqlVerifica);

        if (!$stmtVerifica) {
            die("Erro na consulta de verificação: " . $conn->error);
        }

        $stmtVerifica->bind_param("s", $titulo);
        $stmtVerifica->execute();
        $resultado = $stmtVerifica->get_result();

        if ($resultado->num_rows > 0) {
            $erro = "Este livro já está cadastrado.";
        } else {
            // Insere o novo livro
            $sql = "INSERT INTO livros (titulo, autor, genero, isbn, codigo_bibli) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                die("Erro na consulta de inserção: " . $conn->error);
            }

            $stmt->bind_param("sssss", $titulo, $autor, $categoria, $isbn, $codigo_bibli);

            if ($stmt->execute()) {
                $mensagem = "Livro cadastrado com sucesso!";
            } else {
                $erro = "Erro ao cadastrar: " . $stmt->error;
            }
            $stmt->close();
        }
        $stmtVerifica->close();
    } else {
        $erro = "Por favor, preencha os campos obrigatórios (Título, ISBN e Código da Biblioteca).";
    }
}

$conn->close();
?>

<!doctype html>
<html lang="pt-br">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cadastro de Livro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
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
                                <img src="img/cadastrolivro.jpg" width="400" alt="">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card-body p-5">
                                <h2 class="text-center mb-4">Cadastrar Livros</h2>
                                
                                <!-- FORMULÁRIO CORRIGIDO COM action E method -->
                                <form id="livroForm" action="php/cadastrolivro.php" method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Título do Livro</label>
                                        <input type="text" name="titulo" class="form-control" id="livro" placeholder="Digite o título do livro" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Autor</label>
                                        <input type="text" name="autor" class="form-control" id="autor" placeholder="Digite o nome do autor">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Gênero</label>
                                        <input type="text" name="genero" class="form-control" id="genero" placeholder="Digite o gênero do livro">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Código ISBN</label>
                                        <input type="text" name="isbn" class="form-control" id="isbn" placeholder="Digite o código ISBN" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Código da Biblioteca</label>
                                        <input type="text" name="codigo_bibli" class="form-control" id="codigo_bibli" placeholder="Digite o código da biblioteca" required>
                                    </div>

                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary btn-lg">Cadastrar Livro</button>
                                    </div>

                                    <!-- EXIBIÇÃO DAS MENSAGENS DE SUCESSO OU ERRO -->
                                    <div id="mensagem" class="mt-3 text-center">
                                        <?php if (!empty($mensagem)): ?>
                                            <div class="alert alert-success" role="alert">
                                                <?php echo $mensagem; ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($erro)): ?>
                                            <div class="alert alert-danger" role="alert">
                                                <?php echo $erro; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                </form>

                            </div>

                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/script.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
  </body>
</html>