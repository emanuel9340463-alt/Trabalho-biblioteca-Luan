<?php

// Dados usados pelo XAMPP para acessar o MySQL local.
$host = "localhost";
$usuario = "root";
$senha = "";
$banco = "biblioteca_luan";

// Cria a conexão compartilhada pelas páginas do sistema.
$conn = new mysqli($host, $usuario, $senha, $banco);

if ($conn->connect_error) {
    die("Erro de conexão: " . $conn->connect_error);
}

// Garante o suporte a acentos e caracteres especiais em português.
$conn->set_charset("utf8");