<?php

require_once __DIR__ . '/seguranca.php';
iniciarSessaoSegura();

// Dados usados pelo XAMPP para acessar o MySQL local.
$host = "localhost";
$usuario = "root";
$senha = "";
$banco = "biblioteca_luan";

// Cria a conexão compartilhada sem revelar detalhes internos em respostas HTTP.
try {
    $conn = new mysqli($host, $usuario, $senha, $banco);
    $conn->set_charset("utf8mb4");
} catch (Throwable $erro) {
    error_log('Falha ao conectar ao banco da biblioteca (código ' . (int) $erro->getCode() . ').');
    http_response_code(503);
    exit('Não foi possível conectar ao banco de dados. Verifique a configuração e tente novamente.');
}