<?php

$host = "localhost";
$user = "root";
$pass = "";
$db   = "sistema_login";

$conexao = mysqli_connect($host, $user, $pass, $db);

if(!$conexao){
    die("Erro de conexão");
}

$email = $_POST['email'];
$senha = $_POST['senha'];

$sql = "SELECT * FROM usuarios 
        WHERE email='$email' 
        AND senha='$senha'";

$resultado = mysqli_query($conexao, $sql);

if(mysqli_num_rows($resultado) > 0){

    echo "sucesso";

}else{

    echo "erro";
}
?>