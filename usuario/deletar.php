<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deletar</title>
    <link rel="stylesheet" href="../../src/css/style.css">
</head>
<body>
    <div class="cadastro">
        <?php
            include('../validacoes/verifica.php'); // Adiciona verificação de sessão
            include('../validacoes/conexao.php');

            $id_usuario = $_GET['id_usuario'];

            // Para segurança, deleta apenas se for o usuário logado
            if ($id_usuario != $_SESSION['usuario_id']) {
                echo "<h4>Você não tem permissão para deletar este usuário.</h4>";
            } else {
                // Primeiro, deleta professor associado (se existir)
                $sql_del_prof = "DELETE FROM professores WHERE id_usuario = " . $id_usuario;
                mysqli_query($id, $sql_del_prof);

                // Depois, deleta usuário
                $sql = "DELETE FROM usuarios WHERE id_usuario = " . $id_usuario;
                $res = mysqli_query($id, $sql);

                if($res){
                    echo"<h4>Deletado com sucesso</h4>";
                    // Destroy session e redirect
                    session_destroy();
                    echo"<script language='javascript'>
                        window.location.href='../../front_end/usuario/login.html'
                        </script>";
                }else{
                    echo"<h4>Erro ao Deletar: " . mysqli_error($id) . "</h4>";
                }
            }
        ?>
        
    </div>
</body>
</html>