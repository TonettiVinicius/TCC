<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atualização</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="cadastro">
        <?php
        include('../bd/conexao.php');

            $id_usuario   = $_POST['id_usuario'];
            $login = $_POST['login'];
            $senha = $_POST['senha'];
            $email = $_POST['email'];

        $sql = "UPDATE usuarios SET 
                    Login  = '$login',
                    senha = '$senha',
                    email = '$email'
                    WHERE id_usuario = $id_usuario";

        $ret = mysqli_query($id, $sql);

        if ($ret) {
            echo "<h4>Usuario atualizado com sucesso</h4>";
        } else {
            echo "<h4>Erro ao atualizar: </h4>";
        }
        ?>

        
        <button id='voltar'><a href="listar.php">Voltar</a></button>   
    </div>
</body>
</html>