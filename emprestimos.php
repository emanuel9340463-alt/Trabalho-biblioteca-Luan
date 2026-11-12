<?php
require_once 'config/conexao.php';

// Buscar alunos cadastrados
$sqlAlunos = "SELECT id, nome, turma FROM alunos ORDER BY nome";
$resultadoAlunos = $conn->query($sqlAlunos);
// Buscar empréstimos que ainda não foram devolvidos
$sqlEmprestimos = "SELECT
                    emprestimos.id,
                    alunos.nome AS aluno,
                    alunos.turma,
                    livros.titulo AS livro,
                    emprestimos.data_emprestimo,
                    emprestimos.data_devolucao,
                    CASE
                        WHEN emprestimos.data_devolucao < CURDATE()
                        THEN 'Atrasado'
                        ELSE 'Em dia'
                    END AS situacao
                   FROM emprestimos
                   INNER JOIN alunos
                       ON emprestimos.aluno_id = alunos.id
                   INNER JOIN livros
                       ON emprestimos.livro_id = livros.id
                   WHERE emprestimos.data_devolucao_real IS NULL
                   ORDER BY emprestimos.data_devolucao ASC";

$resultadoEmprestimos = $conn->query($sqlEmprestimos);

// Buscar livros com exemplares disponíveis

$sqlLivros = "SELECT
                livros.id,
                livros.titulo,
                livros.autor,
                GREATEST(
                    0,
                    CAST(livros.quantidade AS SIGNED) - (
                        SELECT COUNT(*)
                        FROM emprestimos
                        WHERE emprestimos.livro_id = livros.id
                          AND emprestimos.data_devolucao_real IS NULL
                    )
                ) AS disponiveis
              FROM livros
              HAVING disponiveis > 0
              ORDER BY titulo";


$resultadoLivros = $conn->query($sqlLivros);
$cadastrosCompletos = $resultadoAlunos->num_rows > 0 && $resultadoLivros->num_rows > 0;
$pageTitle = 'Empréstimos';
$pageDescription = 'Registre novas retiradas e acompanhe devoluções previstas e atrasos.';
$activePage = 'emprestimos';
?>
<?php require 'includes/header.php'; ?>
<div class="container-fluid p-0">
    <?php
    $mensagensResultado = [
        'sucesso' => ['success', 'Empréstimo registrado com sucesso.'],
        'devolucao_sucesso' => ['success', 'Devolução registrada com sucesso.'],
        'dados_invalidos' => ['danger', 'Os dados enviados são inválidos. Confira e tente novamente.'],
        'datas_invalidas' => ['danger', 'Confira as datas informadas para o empréstimo.'],
        'erro' => ['danger', 'Não foi possível concluir a operação. Verifique os dados e tente novamente.'],
    ];
    $resultadoAcao = isset($_GET['resultado']) && is_string($_GET['resultado'])
        ? $_GET['resultado'] : '';
    if (isset($mensagensResultado[$resultadoAcao])):
    ?>
        <div class="alert alert-<?= $mensagensResultado[$resultadoAcao][0] ?>" role="status">
            <?= htmlspecialchars($mensagensResultado[$resultadoAcao][1], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <section class="surface-card mb-4">

        <h2 class="h4 mb-3">Novo empréstimo</h2>
        <p class="surface-subtitle mb-4">Selecione o aluno, o exemplar e a data prevista para devolução.</p>

        <?php if ($resultadoAlunos->num_rows === 0 || $resultadoLivros->num_rows === 0): ?>
            <div class="alert alert-warning" role="status">
                Antes de registrar um empréstimo, cadastre ao menos um aluno e um livro disponível.
                <?php if ($resultadoAlunos->num_rows === 0): ?>
                    <a href="cadastroaluno.php">Cadastrar aluno</a>
                <?php endif; ?>
                <?php if ($resultadoLivros->num_rows === 0): ?>
                    <a href="cadastrolivro.php">Cadastrar livro</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form action="registrar_emprestimo.php" method="post">

            <div class="mb-3">
                <label for="aluno" class="form-label">Aluno</label>

                <select
                    name="aluno_id"
                    id="aluno"
                    class="form-select"
                    required
                >
                    <option value="">Selecione um aluno</option>

                    <?php while ($aluno = $resultadoAlunos->fetch_assoc()): ?>
                        <option value="<?= (int) $aluno['id'] ?>">
                            <?= htmlspecialchars($aluno['nome']) ?>
                            - <?= htmlspecialchars($aluno['turma']) ?>
                        </option>
                    <?php endwhile; ?>

                </select>
            </div>

            <div class="mb-3">
                <label for="livro" class="form-label">Livro</label>

                <select
                    name="livro_id"
                    id="livro"
                    class="form-select"
                    required
                >
                    <option value="">Selecione um livro</option>

                    <?php while ($livro = $resultadoLivros->fetch_assoc()): ?>
                        <option value="<?= (int) $livro['id'] ?>">
                            <?= htmlspecialchars($livro['titulo']) ?>
                            - <?= (int) $livro['disponiveis'] ?> disponível(is)
                        </option>
                    <?php endwhile; ?>

                </select>
            </div>

            <div class="row">

                <div class="col-md-6 mb-3">
                    <label for="data_emprestimo" class="form-label">
                        Data do empréstimo
                    </label>

                    <input
                        type="date"
                        name="data_emprestimo"
                        id="data_emprestimo"
                        class="form-control"
                        value="<?= date('Y-m-d') ?>"
                        required
                    >
                </div>

                <div class="col-md-6 mb-3">
                    <label for="data_devolucao" class="form-label">
                        Devolução prevista
                    </label>

                    <input
                        type="date"
                        name="data_devolucao"
                        id="data_devolucao"
                        class="form-control"
                        value="<?= date('Y-m-d', strtotime('+7 days')) ?>"
                        required
                    >
                </div>

            </div>

            <button type="submit" class="btn btn-primary" <?= !$cadastrosCompletos ? 'disabled' : '' ?>>
                Registrar empréstimo
            </button>

        </form>

    </section>
    
    <section class="surface-card">

        <h2 class="h4 mb-3">Empréstimos ativos</h2>

        <?php if ($resultadoEmprestimos->num_rows > 0): ?>

            <div class="table-responsive">

                <table class="table table-striped table-hover align-middle">

                    <thead>
                        <tr>
                            <th>Aluno</th>
                            <th>Turma</th>
                            <th>Livro</th>
                            <th>Empréstimo</th>
                            <th>Devolução prevista</th>
                            <th>Situação</th>
                            <th>Ação</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php while ($emprestimo = $resultadoEmprestimos->fetch_assoc()): ?>

                            <tr>
                                <td>
                                    <?= htmlspecialchars($emprestimo['aluno']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($emprestimo['turma']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($emprestimo['livro']) ?>
                                </td>

                                <td>
                                    <?= date('d/m/Y', strtotime($emprestimo['data_emprestimo'])) ?>
                                </td>

                                <td>
                                    <?= date('d/m/Y', strtotime($emprestimo['data_devolucao'])) ?>
                                </td>

                                <td>
                                    <?php if ($emprestimo['situacao'] === 'Atrasado'): ?>
                                        <span class="badge bg-danger">Atrasado</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Em dia</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <form action="registrar_devolucao.php" method="post">
                                        <input
                                            type="hidden"
                                            name="emprestimo_id"
                                            value="<?= (int) $emprestimo['id'] ?>"
                                        >

                                        <button type="submit" class="btn btn-sm btn-primary">
                                            Devolver
                                        </button>
                                    </form>
                                </td>
                            </tr>

                        <?php endwhile; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <p class="text-secondary mb-0">
                Não existem empréstimos ativos no momento.
            </p>

        <?php endif; ?>

    </section>
```
    </div>
<?php require 'includes/footer.php'; ?>