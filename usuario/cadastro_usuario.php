<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro</title>
    <link rel="stylesheet" href="../../src/css/style.css">
</head>
<body>
<div class="cadastro">
    <?php

        include('../validacoes/conexao.php');

            $login = $_POST['login'];
            $email = $_POST['email'];
            $senha = md5($_POST['senha']);

            // Verifica se login ou email já existem
            $sql_check = "SELECT * FROM usuarios WHERE Login = '" . mysqli_real_escape_string($id, $login) . "' OR email = '" . mysqli_real_escape_string($id, $email) . "'";
            $res_check = mysqli_query($id, $sql_check);
            if (mysqli_num_rows($res_check) > 0) {
                $row = mysqli_fetch_assoc($res_check);
                if ($row['Login'] === $login) {
                    $error_msg = 'Nome de usuário já em uso.';
                } elseif ($row['email'] === $email) {
                    $error_msg = 'Email já em uso.';
                } else {
                    $error_msg = 'Erro desconhecido ao verificar duplicatas.';
                }
                echo "<script language='javascript'>
                    alert('$error_msg Por favor, tente novamente!');
                    window.location.href='../../front_end/usuario/cadastrar.html'
                    </script>";
            } else {
                $sql = "INSERT INTO usuarios (Login, email, senha)
                        VALUES ('" . mysqli_real_escape_string($id, $login) . "',
                                '" . mysqli_real_escape_string($id, $email) . "',
                                '" . $senha . "')";

                $ret = mysqli_query($id, $sql);
                if($ret){
                    // Após cadastro, pega id_usuario e cria professor com login como nome
                    $usuario_id = mysqli_insert_id($id);
                    $sql_insert_prof = "INSERT INTO professores (nome, id_usuario) VALUES ('" . mysqli_real_escape_string($id, $login) . "', " . $usuario_id . ")";
                    $ret_prof = mysqli_query($id, $sql_insert_prof);
                    if ($ret_prof) {
                        echo"<script language='javascript'>
                        alert('Cadastro realizado com sucesso!');
                        window.location.href='../../front_end/usuario/login.html'
                        </script>";
                    } else {
                        echo"<script language='javascript'>
                        alert('Erro ao criar perfil de professor: " . mysqli_error($id) . ". Por favor, tente novamente!');
                        window.location.href='../../front_end/usuario/cadastrar.html'
                        </script>";
                    }
                }
                else{
                    echo"<script language='javascript'>
                    alert('Erro ao realizar o cadastro: " . mysqli_error($id) . ". Por favor, tente novamente!');
                    window.location.href='../../front_end/usuario/cadastrar.html'
                    </script>";
                }
            }

    ?>
</div>
</body>
</html>