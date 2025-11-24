<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro</title>
    <link rel="stylesheet" href="../../src/css/questoes.css">
</head>
<body>
<div class="card">
    <?php
        include('../validacoes/verifica.php');
        include('../validacoes/conexao.php');

            $titulo = $_POST['titulo'];
            $desc = $_POST['desc'];

            $sql = "insert into prova(titulo, descricao)
            values('".$titulo."',
                    '".$desc."')";

            $ret = mysqli_query($id, $sql);
            if($ret){
                echo"<script language='javascript'>
                alert('Prova Criada com sucesso!');
                window.location.href='../../front_end/prova/criar_prova.php';
                </script>";

            }
            else{
                echo"<script language='javascript'>
                alert('Erro ao criar prova. Por favor, tente novamente!');
                window.location.href='../../front_end/usuario/login.html'
                </script>";
            }

    ?>
    <button id='voltar'><a href="../../front_end/questao_prova/selecionar_questoes.php">Selecionar questoes</a></button>
</div>
</body>
</html>