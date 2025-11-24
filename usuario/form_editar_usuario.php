<?php
include('../bd/conexao.php');
$id_usuario = $_GET['id_usuario'];

$sql = "SELECT * FROM usuarios WHERE id_usuario = '$id_usuario'";
$res = mysqli_query($id, $sql);

while($linha=mysqli_fetch_array($res)){?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conexão BD</title>
    <link rel="stylesheet" href="../../src/css/style.css">
</head>
<body>
    <div class="container">
    <div class="form">
        <form action="../../back_end/usuario/editar_usuario.php" method="post">
                <div class="title">
                <h3>Atualizar os dados</h3>
            </div>

            <!--Id usuario -->
            <div class="input-box">            
                <input type="hidden" name="id_usuario" value="<?php echo$linha['id_usuario']; ?>">
            </div>

            <!--Recebe Login do usuario-->
            <div class="input-box">            
                <label for="login">Login:</label>
                <input type="text" name="login" value="<?php echo$linha['Login']; ?> ">
            </div>

            <div class="input-box">
                <label for="senha">Senha:</label>
                <input type="text" name="senha" value="<?php echo$linha['senha']; ?> ">
            </div> 

            <div class="input-box">
                <label for="email">Email:</label>
                <input type="email" name="email" value="<?php echo$linha['email']; ?> ">
            </div> 
            
        <?php } ?>       
                
        <input type="submit" value="Enviar" id="btn">
        </form>
    </div>
</body>
</html>