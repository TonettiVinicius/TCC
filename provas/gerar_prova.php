<?php
include('../validacoes/verifica.php');

require '../../src/vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;
require_once '../validacoes/conexao.php';

if (!isset($_POST['id_prova'])) {
    die('ID da prova não foi recebido.');
}

$id_prova = (int)$_POST['id_prova'];

// Buscar informações da prova
$sql = "SELECT * FROM prova WHERE id_prova = $id_prova";
$result = mysqli_query($id, $sql);

if (!$result) {
    die("Erro na consulta da prova: " . mysqli_error($id));
}

$prova = mysqli_fetch_assoc($result);

if (!$prova) {
    die('Prova não encontrada.');
}

// Buscar questões da prova
$sql = "
    SELECT q.id_questao, q.enunciado, q.alternativa_a, q.alternativa_b, q.alternativa_c, q.alternativa_d, q.alternativa_e
    FROM questoes_prova qp
    JOIN questoes q ON q.id_questao = qp.id_questao
    WHERE qp.id_prova = $id_prova
";
$result = mysqli_query($id, $sql);

if (!$result) {
    die("Erro na consulta das questões: " . mysqli_error($id));
}

$questoes = [];
while ($row = mysqli_fetch_assoc($result)) {
    $questoes[] = $row;
}

function gerarHTML($questoes, $prova) {
    $html = '<html><head><meta charset="UTF-8"><style>
        body { font-family: Arial, sans-serif; font-size: 12pt; }
        .questao { margin-bottom: 20px; }
        .enunciado { font-weight: bold; }
    </style></head><body>';

    $html .= '<h2> ' . htmlspecialchars($prova['titulo']) . '</h2>
            <h5> Data: __/__/____ </h5>
            <h5> Disciplina:______________________ </h5>
            <h5> Turma:_______________________ </h5>';

    $numero = 1;
    foreach ($questoes as $q) {
        $html .= '<div class="questao">';
        $html .= '<div class="enunciado">' . $numero . ') ' . nl2br(htmlspecialchars($q['enunciado'])) . '</div>';
        $html .= '<ul type="a">';
        
        $alternativas = [
            'a' => $q['alternativa_a'],
            'b' => $q['alternativa_b'],
            'c' => $q['alternativa_c'],
            'd' => $q['alternativa_d'],
            'e' => $q['alternativa_e'],
        ];

        foreach ($alternativas as $texto) {
            $html .= "<li>" . htmlspecialchars($texto) . "</li>";
        }

        $html .= '</ul>';
        $html .= '</div>';
        $numero++;
    }

    $html .= '</body></html>';
    return $html;
}

// Configurar DomPDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$dompdf = new Dompdf($options);

// Gerar PDF da prova
$html = gerarHTML($questoes, $prova);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$output = $dompdf->output();
file_put_contents("prova_{$id_prova}.pdf", $output);

// Exibir links
echo "<h3>PDF gerado com sucesso!</h3>";
echo "<a href='prova_{$id_prova}.pdf' target='_blank'>Abrir Prova</a>";
?>