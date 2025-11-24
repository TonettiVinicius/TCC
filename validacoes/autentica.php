<?php
include("conexao.php");

// Verifica se o login OU senha estão vazias
if((empty($_POST['login']) || empty($_POST['senha']))){
    echo"<script language='javascript'>
        alert('Usuario e senha devem ser preenchidos.');
        window.location.href='../../front_end/usuario/login.html'
        </script>";
}else{
    $input = mysqli_real_escape_string($id, $_POST['login']);
    $senha = md5($_POST['senha']);
    $sql = "SELECT * FROM usuarios WHERE (Login = '$input' OR email = '$input') AND senha = '$senha'";
    $res = mysqli_query($id, $sql);
    if (!$res) {
        echo"<script language='javascript'>
            alert('Erro ao consultar o banco de dados: " . mysqli_error($id) . "');
            window.location.href='../../front_end/usuario/login.html'
            </script>";
    } else {
        $linha = mysqli_fetch_assoc($res); // Pega a linha como assoc
        if($linha){
            session_start();
            $_SESSION['usuario_id'] = $linha['id_usuario']; // Salva ID do usuário
            
            // Pega id_professor associado
            $sql_prof = "SELECT id_professor FROM professores WHERE id_usuario = " . $_SESSION['usuario_id'];
            $res_prof = mysqli_query($id, $sql_prof);
            if (!$res_prof) {
                echo"<script language='javascript'>
                    alert('Erro ao consultar perfil de professor: " . mysqli_error($id) . "');
                    window.location.href='../../front_end/usuario/login.html'
                    </script>";
            } else {
                $prof = mysqli_fetch_assoc($res_prof);
                if ($prof) {
                    $_SESSION['professor_id'] = $prof['id_professor'];
                } else {
                    // Se não houver professor, crie um usando Login como nome
                    $sql_user_login = "SELECT Login FROM usuarios WHERE id_usuario = " . $_SESSION['usuario_id'];
                    $res_user_login = mysqli_query($id, $sql_user_login);
                    $user_login = mysqli_fetch_assoc($res_user_login)['Login'];
                    $sql_insert_prof = "INSERT INTO professores (nome, id_usuario) VALUES ('" . mysqli_real_escape_string($id, $user_login) . "', " . $_SESSION['usuario_id'] . ")";
                    $ret_insert = mysqli_query($id, $sql_insert_prof);
                    if ($ret_insert) {
                        $_SESSION['professor_id'] = mysqli_insert_id($id);
                    } else {
                        echo"<script language='javascript'>
                            alert('Erro ao criar perfil de professor: " . mysqli_error($id) . "');
                            window.location.href='../../front_end/usuario/login.html'
                            </script>";
                    }
                }
                
                echo"<script language='javascript'>
                window.location.href='../../front_end/home/home_logado_teste.php';
                </script>";
            }
        } else {
            echo"<script language='javascript'>
            alert('Usuário ou senha incorretos!')
            window.location.href='../../front_end/usuario/login.html'; </script>";
        }
    }
}
?>