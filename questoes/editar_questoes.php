<?php
include('../validacoes/verifica.php');
include('../validacoes/conexao.php');

$id_questao = $_POST['id_questao'] ?? 0;
if ($id_questao == 0) {
    header("Location: ../../front_end/home/home_logado_teste.php?error=ID inválido");
    exit;
}

$enunciado = mysqli_real_escape_string($id, $_POST['enunciado']);
$alt_a = mysqli_real_escape_string($id, $_POST['alt_a']);
$alt_b = mysqli_real_escape_string($id, $_POST['alt_b']);
$alt_c = mysqli_real_escape_string($id, $_POST['alt_c']);
$alt_d = mysqli_real_escape_string($id, $_POST['alt_d']);
$alt_e = mysqli_real_escape_string($id, $_POST['alt_e']);
$assunto = mysqli_real_escape_string($id, $_POST['assunto']);
$correta = $_POST['opcao'];
$id_pasta = $_POST['id_pasta'];

$stmt = $id->prepare("UPDATE questoes SET 
    enunciado = ?, alternativa_a = ?, alternativa_b = ?, alternativa_c = ?, 
    alternativa_d = ?, alternativa_e = ?, assunto = ?, alternativa_correta = ?, id_pasta = ?
    WHERE id_questao = ? AND id_professor = ?");

$stmt->bind_param("ssssssssiii", $enunciado, $alt_a, $alt_b, $alt_c, $alt_d, $alt_e, $assunto, $correta, $id_pasta, $id_questao, $_SESSION['professor_id']);
$ret = $stmt->execute();
$stmt->close();

if ($ret && $stmt->affected_rows > 0) {
    header("Location: ../../front_end/home/home_logado_teste.php?success=Questão atualizada com sucesso");
} else {
    header("Location: ../../front_end/home/home_logado_teste.php?error=Erro ao atualizar questão");
}
exit;
?>