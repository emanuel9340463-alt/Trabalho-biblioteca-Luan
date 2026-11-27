<?php
require_once __DIR__ . '/config/seguranca.php';
iniciarSessaoSegura();

if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$erro = isset($_GET['erro']) && is_string($_GET['erro']) ? $_GET['erro'] : '';
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#173F35">
    <title>Criar conta | Biblioteca</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=5">
</head>
<body class="app-body">
<main class="container min-vh-100 d-flex align-items-center justify-content-center py-4">
    <section class="surface-card w-100" style="max-width: 520px">
        <div class="mb-4">
            <p class="page-eyebrow">GESTÃO DA BIBLIOTECA</p>
            <h1 class="surface-title">Criar conta</h1>
            <p class="surface-subtitle">Cadastre seus dados para acessar o sistema.</p>
        </div>
        <?php if ($erro !== ''): ?>
            <div class="alert alert-danger" role="alert">
                <?php if ($erro === 'sessao'): ?>
                    O formulário expirou. Recarregue a página e tente novamente.
                <?php elseif ($erro === 'email'): ?>
                    Este e-mail já está cadastrado. Tente entrar ou use outro e-mail.
                <?php elseif ($erro === 'validacao'): ?>
                    Confira os dados: informe nome completo, telefone válido, e-mail válido e uma senha com pelo menos 12 caracteres.
                <?php else: ?>
                    Não foi possível criar a conta. Tente novamente mais tarde.
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <form action="php/actions/cadastro.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="mb-3">
                <label class="form-label" for="nome">Nome completo</label>
                <input class="form-control" type="text" name="nome" id="nome" maxlength="150" autocomplete="name" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="telefone">Telefone para contato</label>
                <input class="form-control" type="tel" name="telefone" id="telefone" maxlength="30" autocomplete="tel" placeholder="(31) 99999-9999" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="email">E-mail</label>
                <input class="form-control" type="email" name="email" id="email" maxlength="255" autocomplete="email" required>
            </div>
            <div class="mb-4">
                <label class="form-label" for="senha">Senha</label>
                <div class="input-group">
                    <input class="form-control" type="password" name="senha" id="senha" minlength="12" maxlength="4096" autocomplete="new-password" required>
                    <button class="btn btn-outline-primary px-3" type="button" data-password-toggle aria-controls="senha" aria-label="Mostrar senha" aria-pressed="false">
                        <svg data-password-icon="show" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <svg data-password-icon="hide" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" hidden>
                            <path d="m3 3 18 18"></path>
                            <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"></path>
                            <path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a14.8 14.8 0 0 1-3.1 3.9"></path>
                            <path d="M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7a10.5 10.5 0 0 0 4-.8"></path>
                        </svg>
                    </button>
                </div>
                <div class="form-text">Use pelo menos 12 caracteres.</div>
            </div>
            <button class="btn btn-primary w-100" type="submit">Criar conta</button>
        </form>
        <p class="text-center mt-4 mb-0">
            Já tem uma conta?
            <a href="login.php">Entrar</a>
        </p>
    </section>
</main>
<script src="js/visibilidade-senha.js"></script>
</body>
</html>
