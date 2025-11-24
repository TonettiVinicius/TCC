<?php
include('../validacoes/verifica.php');
include('../validacoes/conexao.php');

$id_questao = $_GET['id_questao'] ?? 0;
if ($id_questao == 0) {
    header("Location: ../../front_end/home/home_logado_teste.php?error=ID inválido");
    exit;
}

// Deleta apenas se pertencer ao professor
$stmt = $id->prepare("DELETE FROM questoes WHERE id_questao = ? AND id_professor = ?");
$stmt->bind_param("ii", $id_questao, $_SESSION['professor_id']);
$ret = $stmt->execute();
$stmt->close();

if ($ret && $stmt->affected_rows > 0) {
    header("Location: ../../front_end/home/home_logado_teste.php?success=Questão deletada com sucesso");
} else {
    header("Location: ../../front_end/home/home_logado_teste.php?error=Erro ao deletar questão");
}
exit;
?>