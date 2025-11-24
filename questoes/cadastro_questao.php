<?php
include('../validacoes/verifica.php');
include('../validacoes/conexao.php');

$enunciado = mysqli_real_escape_string($id, $_POST['enunciado']);
$alt_a = mysqli_real_escape_string($id, $_POST['alt_a']);
$alt_b = mysqli_real_escape_string($id, $_POST['alt_b']);
$alt_c = mysqli_real_escape_string($id, $_POST['alt_c']);
$alt_d = mysqli_real_escape_string($id, $_POST['alt_d']);
$alt_e = mysqli_real_escape_string($id, $_POST['alt_e']);
$assunto = mysqli_real_escape_string($id, $_POST['assunto']);
$correta = $_POST['opcao'];
$id_pasta = $_POST['id_pasta'];
$id_professor = $_SESSION['professor_id'];

$stmt = $id->prepare("INSERT INTO questoes (enunciado, alternativa_a, alternativa_b, alternativa_c, alternativa_d, alternativa_e, assunto, alternativa_correta, id_pasta, id_professor)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$stmt->bind_param("ssssssssii", $enunciado, $alt_a, $alt_b, $alt_c, $alt_d, $alt_e, $assunto, $correta, $id_pasta, $id_professor);
$ret = $stmt->execute();
$stmt->close();

if ($ret) {
    header("Location: ../../front_end/home/home_logado_teste.php?success=Questão cadastrada com sucesso");
} else {
    header("Location: ../../front_end/home/home_logado_teste.php?error=Erro ao cadastrar questão: " . $id->error);
}
exit;
?>