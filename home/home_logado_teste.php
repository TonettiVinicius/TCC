<?php
// home_logado_teste.php
// Versão final solicitada — usabilidade e correções aplicadas.
// Requisitos:
// - ././back_end/validacoes/verifica.php (inicia session e define $_SESSION['usuario_id'] e $_SESSION['professor_id'])
// - ././back_end/validacoes/conexao.php (cria $id como conexão mysqli)
// - composer autoload Dompdf em ././src/vendor/autoload.php
// - perfil_prova.cabecalho deve ser BLOB / LONGBLOB no DB
// - observe permissões de escrita para salvar os PDFs no diretório

error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../../back_end/validacoes/verifica.php');
include('../../back_end/validacoes/conexao.php');

require '../../src/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/* -------------------- INICIALIZAÇÃO -------------------- */

$message = '';
$warnings = [];
$user_data = ['nome' => 'Usuário', 'login' => '', 'email' => '', 'senha' => ''];
$perfil_prova_data = ['id_perfil_prova' => null, 'id_professor' => null, 'cabecalho' => '', 'instrucoes' => ''];
$id_professor = isset($_SESSION['professor_id']) ? (int) $_SESSION['professor_id'] : null;
$id_usuario = isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : 0;

if (!isset($id) || !($id instanceof mysqli)) {
    die("Erro: conexão com o banco inválida. Verifique ../../back_end/validacoes/conexao.php");
}

// seção ativa (controla qual aba fica mostrada após ações)
$active_section = 'dashboard'; // dashboard | questions | folders | exams | settings

// Aux helpers
function create_dompdf_options(): \Dompdf\Options {
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    return $options;
}

function render_and_save_pdf(string $html, string $path, \Dompdf\Options $options): bool {
    try {
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $output = $dompdf->output();
        if (file_put_contents($path, $output) === false) {
            $GLOBALS['message'] = "<p class='error'>Falha ao gravar PDF: $path</p>";
            return false;
        }
        return true;
    } catch (\Exception $e) {
        $GLOBALS['message'] = "<p class='error'>Erro ao gerar PDF: " . htmlspecialchars($e->getMessage()) . "</p>";
        return false;
    }
}

function blob_to_data_uri($blob): array {
    if ($blob === null || $blob === '') return ['image/png',''];
    $mime = 'image/png';
    $base64 = base64_encode($blob);
    if (function_exists('getimagesizefromstring')) {
        $info = @getimagesizefromstring($blob);
        if ($info && isset($info['mime'])) $mime = $info['mime'];
    } else if (function_exists('finfo_buffer')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $det = finfo_buffer($finfo, $blob);
        if ($det) $mime = $det;
        finfo_close($finfo);
    }
    return [$mime, $base64];
}

/* -------------------- GARANTIAS INICIAIS -------------------- */
if ($id_professor) {
    $sql_user = "SELECT p.nome, u.Login AS login, u.email, u.senha
                 FROM professores p
                 JOIN usuarios u ON p.id_usuario = u.id_usuario
                 WHERE p.id_professor = $id_professor LIMIT 1";
    $res_user = mysqli_query($id, $sql_user);
    if ($res_user && mysqli_num_rows($res_user) > 0) $user_data = mysqli_fetch_assoc($res_user);

    // perfil_prova
    $sql_perfil = "SELECT * FROM perfil_prova WHERE id_professor = $id_professor LIMIT 1";
    $res_perfil = mysqli_query($id, $sql_perfil);
    if ($res_perfil && mysqli_num_rows($res_perfil) > 0) {
        $perfil_prova_data = mysqli_fetch_assoc($res_perfil);
    } else {
        $sql_create_perfil = "INSERT INTO perfil_prova (id_professor, cabecalho, instrucoes) VALUES ($id_professor, '', '')";
        if (!mysqli_query($id, $sql_create_perfil)) {
            $warnings[] = "Falha ao criar perfil_prova inicial: " . mysqli_error($id);
        } else {
            $newid = mysqli_insert_id($id);
            $perfil_prova_data = ['id_perfil_prova' => $newid, 'id_professor' => $id_professor, 'cabecalho' => '', 'instrucoes' => ''];
        }
    }

    // pasta padrão
    $sql_default_pasta = "SELECT id_pasta FROM pastas WHERE nome = 'Sem pasta' AND id_professor = $id_professor LIMIT 1";
    $res_default_pasta = mysqli_query($id, $sql_default_pasta);
    if ($res_default_pasta && mysqli_num_rows($res_default_pasta) > 0) {
        $row = mysqli_fetch_assoc($res_default_pasta);
        $default_pasta_id = (int)$row['id_pasta'];
    } else {
        if (!mysqli_query($id, "INSERT INTO pastas (nome, id_professor) VALUES ('Sem pasta', $id_professor)")) {
            $warnings[] = "Falha ao criar pasta padrão: " . mysqli_error($id);
            $res_any = mysqli_query($id, "SELECT id_pasta FROM pastas WHERE id_professor = $id_professor LIMIT 1");
            if ($res_any && mysqli_num_rows($res_any) > 0) $default_pasta_id = (int)mysqli_fetch_assoc($res_any)['id_pasta'];
            else $default_pasta_id = 0;
        } else {
            $default_pasta_id = mysqli_insert_id($id);
        }
    }
} else {
    $default_pasta_id = 0;
}

/* -------------------- ESTATÍSTICAS -------------------- */
$num_questoes = $num_pastas = $num_provas = 0;
if ($id_professor) {
    $num_questoes = (int) mysqli_fetch_row(mysqli_query($id, "SELECT COUNT(*) FROM questoes WHERE id_professor = $id_professor"))[0];
    $num_pastas = (int) mysqli_fetch_row(mysqli_query($id, "SELECT COUNT(*) FROM pastas WHERE id_professor = $id_professor"))[0];
    $num_provas = (int) mysqli_fetch_row(mysqli_query($id, "SELECT COUNT(*) FROM prova p JOIN perfil_prova pp ON p.id_perfil_prova = pp.id_perfil_prova WHERE pp.id_professor = $id_professor"))[0];
}

/* -------------------- DETERMINAR SECTION ATIVA POR GET -------------------- */
if (isset($_GET['questao_mode'])) $active_section = 'questions';
if (isset($_GET['pasta_mode']) || isset($_GET['view_pasta_id'])) $active_section = 'folders';
if (isset($_GET['create_prova']) || isset($_GET['view_prova'])) $active_section = 'exams';
if (isset($_GET['settings']) || isset($_GET['perfil'])) $active_section = 'settings';

/* -------------------- PROCESSAMENTO DE POST -------------------- */
$auto_open_js = ''; // script para abrir PDFs em nova aba quando necessário

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // definir aba considerando ação enviada
    if (isset($_POST['user_action'])) $active_section = 'settings';
    if (isset($_POST['action'])) {
        $act = $_POST['action'];
        if (strpos($act, 'questao') !== false) $active_section = 'questions';
        elseif (strpos($act, 'pasta') !== false) $active_section = 'folders';
        elseif (strpos($act, 'prova') !== false || in_array($act, ['create_prova','selecionar_questoes','gerar_prova','delete_prova'])) $active_section = 'exams';
    }

    // USER ACTIONS (perfil)
    if (isset($_POST['user_action'])) {
        $ua = $_POST['user_action'];
        if ($ua === 'edit_user') {
            $nome  = mysqli_real_escape_string($id, $_POST['nome'] ?? $user_data['nome']);
            $login = mysqli_real_escape_string($id, $_POST['login'] ?? $user_data['login']);
            $email = mysqli_real_escape_string($id, $_POST['email'] ?? $user_data['email']);
            $senha_raw = $_POST['senha'] ?? '';
            $senha = !empty($senha_raw) ? md5($senha_raw) : $user_data['senha'];
            mysqli_query($id, "UPDATE professores SET nome = '$nome' WHERE id_usuario = $id_usuario");
            mysqli_query($id, "UPDATE usuarios SET Login = '$login', email = '$email', senha = '$senha' WHERE id_usuario = $id_usuario");
            $message = "<p class='success'>Perfil atualizado.</p>";
            $user_data['nome'] = $nome;
            $user_data['login'] = $login;
            $user_data['email'] = $email;
            $user_data['senha'] = $senha;
        } elseif ($ua === 'update_perfil_prova' && $id_professor) {
            $instrucoes_raw = $_POST['instrucoes'] ?? '';
            $instrucoes_escaped = mysqli_real_escape_string($id, $instrucoes_raw);
            $cabecalho_uploaded = false;
            if (isset($_FILES['cabecalho']) && $_FILES['cabecalho']['error'] === UPLOAD_ERR_OK) {
                $imgData = file_get_contents($_FILES['cabecalho']['tmp_name']);
                if ($imgData !== false) {
                    // prepared update with blob
                    $stmt = mysqli_prepare($id, "UPDATE perfil_prova SET cabecalho = ?, instrucoes = ? WHERE id_professor = ?");
                    // bind dummy then send long data
                    mysqli_stmt_bind_param($stmt, "bsi", $null = null, $instrucoes_escaped, $id_professor);
                    mysqli_stmt_send_long_data($stmt, 0, $imgData);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                    $cabecalho_uploaded = true;
                } else {
                    $warnings[] = "Falha ao ler arquivo enviado.";
                }
            }
            if (!$cabecalho_uploaded) {
                mysqli_query($id, "UPDATE perfil_prova SET instrucoes = '$instrucoes_escaped' WHERE id_professor = $id_professor");
            }
            $perfil_prova_data = mysqli_fetch_assoc(mysqli_query($id, "SELECT * FROM perfil_prova WHERE id_professor = $id_professor"));
            $message = "<p class='success'>Preferências de prova atualizadas.</p>";
            $active_section = 'settings';
        }
    }

    // ACTIONS
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        /* --------- QUESTÕES ---------- */
        if (strpos($action, 'questao') !== false && $id_professor) {
            $enunciado = mysqli_real_escape_string($id, $_POST['enunciado'] ?? '');
            $alt_a = mysqli_real_escape_string($id, $_POST['alt_a'] ?? '');
            $alt_b = mysqli_real_escape_string($id, $_POST['alt_b'] ?? '');
            $alt_c = mysqli_real_escape_string($id, $_POST['alt_c'] ?? '');
            $alt_d = mysqli_real_escape_string($id, $_POST['alt_d'] ?? '');
            $alt_e = mysqli_real_escape_string($id, $_POST['alt_e'] ?? '');
            $assunto = mysqli_real_escape_string($id, $_POST['assunto'] ?? '');
            $opcao = mysqli_real_escape_string($id, $_POST['opcao'] ?? '');
            $id_pasta_q = (isset($_POST['id_pasta']) && $_POST['id_pasta'] !== '') ? (int) $_POST['id_pasta'] : $default_pasta_id;

            if ($action === 'cadastrar_questao') {
                $sql = "INSERT INTO questoes (enunciado, alternativa_a, alternativa_b, alternativa_c, alternativa_d, alternativa_e, assunto, alternativa_correta, id_pasta, id_professor)
                        VALUES ('$enunciado','$alt_a','$alt_b','$alt_c','$alt_d','$alt_e','$assunto','$opcao',$id_pasta_q,$id_professor)";
                if (mysqli_query($id, $sql)) {
                    $message = "<p class='success'>Questão cadastrada.</p>";
                    // after creating, go back to list
                    $active_section = 'questions';
                } else {
                    $message = "<p class='error'>Erro ao cadastrar: " . mysqli_error($id) . "</p>";
                    $active_section = 'questions';
                }
            }

            if ($action === 'editar_questao' && !empty($_POST['id_questao'])) {
                $idq = (int) $_POST['id_questao'];
                $sql = "UPDATE questoes SET enunciado='$enunciado', alternativa_a='$alt_a', alternativa_b='$alt_b', alternativa_c='$alt_c',
                        alternativa_d='$alt_d', alternativa_e='$alt_e', assunto='$assunto', alternativa_correta='$opcao', id_pasta=$id_pasta_q
                        WHERE id_questao = $idq AND id_professor = $id_professor";
                if (mysqli_query($id, $sql)) {
                    $message = "<p class='success'>Questão atualizada.</p>";
                } else {
                    $message = "<p class='error'>Erro ao atualizar: " . mysqli_error($id) . "</p>";
                }
                // after edit, go back to list
                $active_section = 'questions';
            }

            if ($action === 'deletar_questao' && !empty($_POST['id_questao'])) {
                $idq = (int) $_POST['id_questao'];
                // remove from questoes_prova first to respect FK
                mysqli_query($id, "DELETE FROM questoes_prova WHERE id_questao = $idq");
                if (mysqli_query($id, "DELETE FROM questoes WHERE id_questao = $idq AND id_professor = $id_professor")) {
                    $message = "<p class='success'>Questão removida com sucesso.</p>";
                } else {
                    $message = "<p class='error'>Erro ao remover questão: " . mysqli_error($id) . "</p>";
                }
                $active_section = 'questions';
            }

            // cancel action (explicit)
            if ($action === 'cancel_edit_questao') {
                $active_section = 'questions';
            }
        }

        /* --------- PASTAS ---------- */
        if (strpos($action, 'pasta') !== false && $id_professor) {
            if ($action === 'cadastrar_pasta') {
                $nome_pasta = mysqli_real_escape_string($id, $_POST['nome_pasta'] ?? 'Nova pasta');
                if (mysqli_query($id, "INSERT INTO pastas (nome, id_professor) VALUES ('$nome_pasta', $id_professor)")) {
                    $message = "<p class='success'>Pasta criada.</p>";
                } else {
                    $message = "<p class='error'>Erro ao criar pasta: " . mysqli_error($id) . "</p>";
                }
                // after create go back to list
                $active_section = 'folders';
            } elseif ($action === 'editar_pasta' && !empty($_POST['id_pasta'])) {
                $idp = (int) $_POST['id_pasta'];
                $nome_pasta = mysqli_real_escape_string($id, $_POST['nome_pasta'] ?? '');
                if (mysqli_query($id, "UPDATE pastas SET nome = '$nome_pasta' WHERE id_pasta = $idp AND id_professor = $id_professor")) {
                    $message = "<p class='success'>Pasta atualizada.</p>";
                } else {
                    $message = "<p class='error'>Erro ao atualizar pasta: " . mysqli_error($id) . "</p>";
                }
                $active_section = 'folders';
            } elseif ($action === 'deletar_pasta' && !empty($_POST['id_pasta'])) {
                $idp = (int) $_POST['id_pasta'];
                if ($idp == $default_pasta_id) {
                    $message = "<p class='error'>Não é possível deletar a pasta padrão.</p>";
                } else {
                    mysqli_query($id, "UPDATE questoes SET id_pasta = $default_pasta_id WHERE id_pasta = $idp AND id_professor = $id_professor");
                    mysqli_query($id, "DELETE FROM pastas WHERE id_pasta = $idp AND id_professor = $id_professor");
                    $message = "<p class='success'>Pasta deletada. Questões movidas para 'Sem pasta'.</p>";
                }
                $active_section = 'folders';
            } elseif ($action === 'cancel_edit_pasta' || $action === 'cancel_create_pasta') {
                // cancel creation/edit => show list
                $active_section = 'folders';
            }
        }

        /* --------- PROVAS ---------- */
        if ($id_professor) {
            if ($action === 'create_prova') {
                $titulo = mysqli_real_escape_string($id, $_POST['titulo'] ?? 'Sem título');
                $descricao = mysqli_real_escape_string($id, $_POST['descricao'] ?? '');
                $res_pp = mysqli_query($id, "SELECT id_perfil_prova FROM perfil_prova WHERE id_professor = $id_professor LIMIT 1");
                if ($res_pp && mysqli_num_rows($res_pp) > 0) {
                    $id_perfil_prova = (int) mysqli_fetch_assoc($res_pp)['id_perfil_prova'];
                    mysqli_query($id, "INSERT INTO prova (id_perfil_prova, titulo, descricao) VALUES ($id_perfil_prova, '$titulo', '$descricao')");
                    $current_id_prova = mysqli_insert_id($id);
                    $_SESSION['temp_prova_id'] = $current_id_prova;
                    $message = "<p class='success'>Prova criada. Selecione as questões na aba Provas.</p>";
                } else {
                    $message = "<p class='error'>Perfil de prova não encontrado.</p>";
                }
                $active_section = 'exams';
            }

            if ($action === 'selecionar_questoes' && !empty($_POST['id_prova'])) {
                $id_prova = (int) $_POST['id_prova'];
                $selecionadas = $_POST['selecionadas'] ?? [];
                mysqli_query($id, "DELETE FROM questoes_prova WHERE id_prova = $id_prova");
                $ordem = 1;
                foreach ($selecionadas as $id_q_raw) {
                    $id_q = (int)$id_q_raw;
                    mysqli_query($id, "INSERT INTO questoes_prova (id_prova, id_questao, ordem) VALUES ($id_prova, $id_q, $ordem)");
                    $ordem++;
                }

                $res_qp = mysqli_query($id, "SELECT q.* FROM questoes_prova qp JOIN questoes q ON qp.id_questao = q.id_questao WHERE qp.id_prova = $id_prova ORDER BY qp.ordem ASC");
                $questoes = [];
                while ($row = mysqli_fetch_assoc($res_qp)) $questoes[] = $row;

                $prova = mysqli_fetch_assoc(mysqli_query($id, "SELECT * FROM prova WHERE id_prova = $id_prova LIMIT 1"));
                $perfil = mysqli_fetch_assoc(mysqli_query($id, "SELECT * FROM perfil_prova WHERE id_professor = $id_professor LIMIT 1"));

                $use_cabecalho = isset($_POST['use_cabecalho']);
                $use_instrucoes = isset($_POST['use_instrucoes']);
                $gd_loaded = extension_loaded('gd');
                if ($use_cabecalho && !$gd_loaded) {
                    $warnings[] = "A extensão GD não está ativa. Cabeçalho não será incluído no PDF.";
                }

                // montar HTML da prova
                $html_prova = '<!doctype html><html lang="pt-br"><head><meta charset="utf-8"><style>';
                $html_prova .= 'body{font-family: DejaVu Sans, sans-serif; font-size:12px;margin:30px;}';
                $html_prova .= 'header{display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #333;padding-bottom:10px;margin-bottom:12px;}';
                $html_prova .= '.cabecalho-img{max-height:80px;max-width:140px;}';
                $html_prova .= '.titulo{flex:1;text-align:center;}';
                $html_prova .= '.dados-aluno{font-size:11px;margin-top:6px;}';
                $html_prova .= '.instrucoes{background:#f6f6f6;border:1px solid #ddd;padding:8px;margin-bottom:12px;font-size:12px;}';
                $html_prova .= '.questao{margin-bottom:14px;}';
                $html_prova .= '.enunciado{font-weight:600;margin-bottom:4px;}';
                $html_prova .= 'ul{list-style: lower-alpha;margin-left:18px;}';
                $html_prova .= '.footer{margin-top:30px;font-size:11px;text-align:center;color:#666;}';
                $html_prova .= '</style></head><body>';

                $html_prova .= '<header>';
                if ($use_cabecalho && !empty($perfil['cabecalho']) && $gd_loaded) {
                    list($mime, $b64) = blob_to_data_uri($perfil['cabecalho']);
                    if (!empty($b64)) {
                        $html_prova .= '<div><img class="cabecalho-img" src="data:' . htmlspecialchars($mime) . ';base64,' . $b64 . '" alt="Logo" style="max-width:100%;height:auto;"></div>';
                    } else {
                        $html_prova .= '<div style="width:140px;"></div>';
                    }
                } else {
                    $html_prova .= '<div style="width:140px;"></div>';
                }
                $html_prova .= '<div class="titulo"><h2>' . htmlspecialchars($prova['titulo']) . '</h2>';
                $html_prova .= '<div class="dados-aluno"><span>Nome: ________________________</span>&nbsp;<span>Data: ____/____/_____</span></div></div>';
                $html_prova .= '<div style="width:140px;"></div>';
                $html_prova .= '</header>';

                if ($use_instrucoes && !empty(trim($perfil['instrucoes'] ?? ''))) {
                    $html_prova .= '<div class="instrucoes"><strong>Instruções:</strong><br>' . nl2br(htmlspecialchars($perfil['instrucoes'])) . '</div>';
                }

                $num = 1;
                foreach ($questoes as $q) {
                    $html_prova .= '<div class="questao">';
                    $html_prova .= '<div class="enunciado">' . $num . ') ' . nl2br(htmlspecialchars($q['enunciado'])) . '</div>';
                    $html_prova .= '<ul>';
                    $html_prova .= '<li>' . htmlspecialchars($q['alternativa_a']) . '</li>';
                    $html_prova .= '<li>' . htmlspecialchars($q['alternativa_b']) . '</li>';
                    $html_prova .= '<li>' . htmlspecialchars($q['alternativa_c']) . '</li>';
                    $html_prova .= '<li>' . htmlspecialchars($q['alternativa_d']) . '</li>';
                    if (!empty($q['alternativa_e'])) $html_prova .= '<li>' . htmlspecialchars($q['alternativa_e']) . '</li>';
                    $html_prova .= '</ul></div>';
                    $num++;
                }

                $html_prova .= '<div class="footer">Gerado automaticamente pelo sistema Write.Prof</div>';
                $html_prova .= '</body></html>';

                $options = create_dompdf_options();
                $file_prova = "prova_{$id_prova}.pdf";
                $ok_prova = render_and_save_pdf($html_prova, $file_prova, $options);

                // montar gabarito
                $html_gabarito = '<!doctype html><html lang="pt-br"><head><meta charset="utf-8"><style>';
                $html_gabarito .= 'body{font-family: DejaVu Sans, sans-serif; font-size:13px;margin:30px;}';
                $html_gabarito .= 'h2{text-align:center;margin-bottom:12px;}';
                $html_gabarito .= 'table{width:100%;border-collapse:collapse;}';
                $html_gabarito .= 'td,th{padding:8px;border:1px solid #ddd;text-align:left;}';
                $html_gabarito .= '.right{ text-align:right;}';
                $html_gabarito .= '</style></head><body>';
                $html_gabarito .= '<h2>Gabarito - ' . htmlspecialchars($prova['titulo']) . '</h2>';
                $html_gabarito .= '<table><thead><tr><th>#</th><th>Enunciado (resumo)</th><th>Resposta correta</th></tr></thead><tbody>';

                $num = 1;
                foreach ($questoes as $q) {
                    $resumo = strip_tags($q['enunciado']);
                    if (mb_strlen($resumo) > 80) $resumo = mb_substr($resumo, 0, 77) . '...';
                    $correta = htmlspecialchars($q['alternativa_correta']);
                    $html_gabarito .= '<tr><td>' . $num . '</td><td>' . htmlspecialchars($resumo) . '</td><td class="right">' . $correta . '</td></tr>';
                    $num++;
                }

                $html_gabarito .= '</tbody></table>';
                $html_gabarito .= '<div style="margin-top:20px;font-size:12px;color:#555;text-align:center;">Gabarito gerado automaticamente. Não distribuir aos alunos.</div>';
                $html_gabarito .= '</body></html>';

                $options2 = create_dompdf_options();
                $file_gabarito = "gabarito_{$id_prova}.pdf";
                $ok_gabarito = render_and_save_pdf($html_gabarito, $file_gabarito, $options2);

                if ($ok_prova && $ok_gabarito) {
                    // criar script para abrir automaticamente em nova aba
                    $auto_open_js = "<script>window.open('" . addslashes($file_prova) . "','_blank'); window.open('" . addslashes($file_gabarito) . "','_blank');</script>";
                    $message = "<p class='success'>Prova e gabarito gerados. Links: <a href='$file_prova' target='_blank'>Abrir prova</a> | <a href='$file_gabarito' target='_blank'>Abrir gabarito</a></p>";
                } else {
                    if (empty($message)) $message = "<p class='error'>Erro ao gerar PDFs.</p>";
                }

                unset($_SESSION['temp_prova_id']);
                $active_section = 'exams';
            }

            if ($action === 'gerar_prova' && !empty($_POST['id_prova'])) {
                // re-gerar prova existente
                $id_prova = (int) $_POST['id_prova'];
                $res_qp = mysqli_query($id, "SELECT q.* FROM questoes_prova qp JOIN questoes q ON qp.id_questao = q.id_questao WHERE qp.id_prova = $id_prova ORDER BY qp.ordem ASC");
                $questoes = [];
                while ($r = mysqli_fetch_assoc($res_qp)) $questoes[] = $r;
                $prova = mysqli_fetch_assoc(mysqli_query($id, "SELECT * FROM prova WHERE id_prova = $id_prova LIMIT 1"));
                $perfil = mysqli_fetch_assoc(mysqli_query($id, "SELECT * FROM perfil_prova WHERE id_professor = $id_professor LIMIT 1"));

                $use_cabecalho = isset($_POST['use_cabecalho']);
                $use_instrucoes = isset($_POST['use_instrucoes']);
                $gd_loaded = extension_loaded('gd');
                if ($use_cabecalho && !$gd_loaded) $warnings[] = "Extensão GD não ativa. Cabeçalho não será incluído ao re-gerar o PDF.";

                // montar e salvar prova + gabarito (reutiliza lógica)
                $html_prova = '<!doctype html><html lang="pt-br"><head><meta charset="utf-8"><style>';
                $html_prova .= 'body{font-family: DejaVu Sans, sans-serif; font-size:12px;margin:30px;}';
                $html_prova .= 'header{display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #333;padding-bottom:10px;margin-bottom:12px;}';
                $html_prova .= '.cabecalho-img{max-height:80px;max-width:140px;}';
                $html_prova .= '.titulo{flex:1;text-align:center;}';
                $html_prova .= '.dados-aluno{font-size:11px;margin-top:6px;}';
                $html_prova .= '.instrucoes{background:#f6f6f6;border:1px solid #ddd;padding:8px;margin-bottom:12px;font-size:12px;}';
                $html_prova .= '.questao{margin-bottom:14px;}';
                $html_prova .= '.enunciado{font-weight:600;margin-bottom:4px;}';
                $html_prova .= 'ul{list-style: lower-alpha;margin-left:18px;}';
                $html_prova .= '.footer{margin-top:30px;font-size:11px;text-align:center;color:#666;}';
                $html_prova .= '</style></head><body>';

                $html_prova .= '<header>';
                if ($use_cabecalho && !empty($perfil['cabecalho']) && $gd_loaded) {
                    list($mime, $b64) = blob_to_data_uri($perfil['cabecalho']);
                    if (!empty($b64)) $html_prova .= '<div><img class="cabecalho-img" src="data:' . htmlspecialchars($mime) . ';base64,' . $b64 . '" alt="Logo" style="max-width:100%;height:auto;"></div>';
                    else $html_prova .= '<div style="width:140px;"></div>';
                } else {
                    $html_prova .= '<div style="width:140px;"></div>';
                }
                $html_prova .= '<div class="titulo"><h2>' . htmlspecialchars($prova['titulo']) . '</h2>';
                $html_prova .= '<div class="dados-aluno"><span>Nome: ________________________</span>&nbsp;<span>Data: ____/____/_____</span></div></div>';
                $html_prova .= '<div style="width:140px;"></div>';
                $html_prova .= '</header>';

                if ($use_instrucoes && !empty(trim($perfil['instrucoes'] ?? ''))) $html_prova .= '<div class="instrucoes"><strong>Instruções:</strong><br>' . nl2br(htmlspecialchars($perfil['instrucoes'])) . '</div>';

                $num = 1;
                foreach ($questoes as $q) {
                    $html_prova .= '<div class="questao">';
                    $html_prova .= '<div class="enunciado">' . $num . ') ' . nl2br(htmlspecialchars($q['enunciado'])) . '</div>';
                    $html_prova .= '<ul>';
                    $html_prova .= '<li>' . htmlspecialchars($q['alternativa_a']) . '</li>';
                    $html_prova .= '<li>' . htmlspecialchars($q['alternativa_b']) . '</li>';
                    $html_prova .= '<li>' . htmlspecialchars($q['alternativa_c']) . '</li>';
                    $html_prova .= '<li>' . htmlspecialchars($q['alternativa_d']) . '</li>';
                    if (!empty($q['alternativa_e'])) $html_prova .= '<li>' . htmlspecialchars($q['alternativa_e']) . '</li>';
                    $html_prova .= '</ul></div>';
                    $num++;
                }

                $html_prova .= '<div class="footer">Gerado automaticamente pelo sistema Write.Prof</div>';
                $html_prova .= '</body></html>';

                $options = create_dompdf_options();
                $file_prova = "prova_{$id_prova}.pdf";
                $ok1 = render_and_save_pdf($html_prova, $file_prova, $options);

                // gabarito
                $html_gabarito = '<!doctype html><html lang="pt-br"><head><meta charset="utf-8"><style>';
                $html_gabarito .= 'body{font-family: DejaVu Sans, sans-serif; font-size:13px;margin:30px;}';
                $html_gabarito .= 'h2{text-align:center;margin-bottom:12px;}';
                $html_gabarito .= 'table{width:100%;border-collapse:collapse;}';
                $html_gabarito .= 'td,th{padding:8px;border:1px solid #ddd;text-align:left;}';
                $html_gabarito .= '.right{ text-align:right;}';
                $html_gabarito .= '</style></head><body>';
                $html_gabarito .= '<h2>Gabarito - ' . htmlspecialchars($prova['titulo']) . '</h2>';
                $html_gabarito .= '<table><thead><tr><th>#</th><th>Enunciado (resumo)</th><th>Resposta correta</th></tr></thead><tbody>';
                $num = 1;
                foreach ($questoes as $q) {
                    $resumo = strip_tags($q['enunciado']);
                    if (mb_strlen($resumo) > 80) $resumo = mb_substr($resumo, 0, 77) . '...';
                    $correta = htmlspecialchars($q['alternativa_correta']);
                    $html_gabarito .= '<tr><td>' . $num . '</td><td>' . htmlspecialchars($resumo) . '</td><td class="right">' . $correta . '</td></tr>';
                    $num++;
                }
                $html_gabarito .= '</tbody></table>';
                $html_gabarito .= '<div style="margin-top:20px;font-size:12px;color:#555;text-align:center;">Gabarito gerado automaticamente. Não distribuir aos alunos.</div>';
                $html_gabarito .= '</body></html>';

                $options_g = create_dompdf_options();
                $file_gabarito = "gabarito_{$id_prova}.pdf";
                $ok2 = render_and_save_pdf($html_gabarito, $file_gabarito, $options_g);

                if ($ok1 && $ok2) {
                    $auto_open_js = "<script>window.open('" . addslashes($file_prova) . "','_blank'); window.open('" . addslashes($file_gabarito) . "','_blank');</script>";
                    $message = "<p class='success'>Prova e gabarito re-gerados. Links: <a href='$file_prova' target='_blank'>Abrir prova</a> | <a href='$file_gabarito' target='_blank'>Abrir gabarito</a></p>";
                } else if (empty($message)) $message = "<p class='error'>Erro ao re-gerar PDFs.</p>";

                $active_section = 'exams';
            }

            if ($action === 'delete_prova' && !empty($_POST['id_prova'])) {
                $id_prova = (int) $_POST['id_prova'];
                mysqli_query($id, "DELETE FROM questoes_prova WHERE id_prova = $id_prova");
                mysqli_query($id, "DELETE FROM prova WHERE id_prova = $id_prova");
                $filep = "prova_{$id_prova}.pdf";
                $fileg = "gabarito_{$id_prova}.pdf";
                if (file_exists($filep)) unlink($filep);
                if (file_exists($fileg)) unlink($fileg);
                $message = "<p class='success'>Prova e gabarito removidos.</p>";
                $active_section = 'exams';
            }
        }
    } // fim if action
} // fim POST

/* -------------------- BUSCAS PARA RENDERIZAÇÃO -------------------- */
$questoes = [];
if ($id_professor) {
    $res_q = mysqli_query($id, "SELECT * FROM questoes WHERE id_professor = $id_professor ORDER BY id_questao DESC");
    while ($r = mysqli_fetch_assoc($res_q)) $questoes[] = $r;
}
$pastas = [];
if ($id_professor) {
    $res_p = mysqli_query($id, "SELECT * FROM pastas WHERE id_professor = $id_professor ORDER BY nome ASC");
    while ($r = mysqli_fetch_assoc($res_p)) $pastas[] = $r;
}
$provas_list = [];
if ($id_professor) {
    $res_pr = mysqli_query($id, "SELECT p.* FROM prova p JOIN perfil_prova pp ON p.id_perfil_prova = pp.id_perfil_prova WHERE pp.id_professor = $id_professor ORDER BY p.id_prova DESC");
    while ($r = mysqli_fetch_assoc($res_pr)) $provas_list[] = $r;
}
$questoes_sem_pasta = [];
if ($id_professor) {
    $res_qsp = mysqli_query($id, "SELECT * FROM questoes WHERE (id_pasta = 0 OR id_pasta IS NULL OR id_pasta = $default_pasta_id) AND id_professor = $id_professor");
    while ($r = mysqli_fetch_assoc($res_qsp)) $questoes_sem_pasta[] = $r;
}
$current_id_prova = $_SESSION['temp_prova_id'] ?? null;

/* -------------------- RENDERIZAÇÃO HTML -------------------- */
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Dashboard - Write.Prof</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<link rel="stylesheet" href="../../src/css/style_logado.css">
<link rel="stylesheet" href="../../src/css/pastas.css">
<link rel="stylesheet" href="../../src/css/questoes.css">
<link rel="stylesheet" href="../../src/css/provas.css">
<link rel="stylesheet" href="../../src/css/usuario.css">
<link rel="stylesheet" href="../../src/css/usuario_dashboard.css">
<link rel="shortcut icon" href="../../src/image/icon.png" type="image/x-icon">
<style>
body{font-family:Inter,Arial,sans-serif;background:#f4f6fb;margin:0;padding:0;}
.sidebar{width:240px;background:#1f2937;color:#fff;height:100vh;position:fixed;padding:18px;box-sizing:border-box;}
.main-content{margin-left:260px;padding:26px;}
.card{background:#fff;padding:16px;border-radius:8px;margin-bottom:12px;box-shadow:0 1px 4px rgba(0,0,0,0.06);}
.btn{display:inline-block;padding:8px 12px;border-radius:6px;background:#2563eb;color:#fff;text-decoration:none;border:none;cursor:pointer;}
.btn-secondary{background:#6b7280;}
.success{color:green;}
.error{color:red;}
table{width:100%;border-collapse:collapse;}
th,td{padding:8px;border-bottom:1px solid #eee;text-align:left;}
.message-area{margin-bottom:12px;}
</style>
</head>
<body>
<div class="sidebar">
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;">
    <img src="../../src/image/logo_navbar.png" alt="logo" style="height:36px;">
    <div style="font-weight:700;">Write.Prof</div>
  </div>
  <nav>
    <div style="margin-bottom:8px;font-weight:600;">Menu</div>
    <div><a href="#" class="menu-link" data-section="dashboard" style="color:#cbd5e1;text-decoration:none;display:block;padding:8px 0;">Dashboard</a></div>
    <div><a href="#" class="menu-link" data-section="questions" style="color:#cbd5e1;text-decoration:none;display:block;padding:8px 0;">Minhas Questões</a></div>
    <div><a href="#" class="menu-link" data-section="folders" style="color:#cbd5e1;text-decoration:none;display:block;padding:8px 0;">Pastas</a></div>
    <div><a href="#" class="menu-link" data-section="exams" style="color:#cbd5e1;text-decoration:none;display:block;padding:8px 0;">Minhas Provas</a></div>
    <div><a href="#" class="menu-link" data-section="settings" style="color:#cbd5e1;text-decoration:none;display:block;padding:8px 0;">Configurações</a></div>
  </nav>
  <div style="position:absolute;bottom:20px;left:18px;">
    <div style="font-weight:600;"><?php echo htmlspecialchars($user_data['nome']); ?></div>
    <div style="font-size:13px;color:#93c5fd;"><?php echo htmlspecialchars($user_data['email']); ?></div>
    <div style="font-weight:600;"><a href="../../back_end/validacoes/sair.php">Sair</a></div>
  </div>
</div>

<div class="main-content">
  <h1><img style="max-width: 225px;" src="../../src/image/logo_navbar_horizontal.png" alt="writeprof"></h1>

  <div class="message-area" id="messageArea">
    <?php
      if (!empty($message)) echo $message;
      if (!empty($warnings)) {
          foreach ($warnings as $w) echo "<p class='error'>" . htmlspecialchars($w) . "</p>";
      }
      if (!empty($auto_open_js)) echo $auto_open_js;
    ?>
  </div>

  <div class="card" id="dashboard">
    <div style="display:flex;gap:12px;align-items:flex-start;">
      <div style="flex:1;">
        <h2 style="color: black;">Menu Principal</h2>
        <h3>Estatísticas</h3>
        <div style="display:flex;gap:12px;">
          <div style="background:#fff;padding:12px;border-radius:8px;min-width:140px;">
            <div style="font-size:22px;font-weight:700;"><?php echo $num_questoes; ?></div>
            <div style="color:#6b7280;">Questões</div>
          </div>
          <div style="background:#fff;padding:12px;border-radius:8px;min-width:140px;">
            <div style="font-size:22px;font-weight:700;"><?php echo $num_pastas; ?></div>
            <div style="color:#6b7280;">Pastas</div>
          </div>
          <div style="background:#fff;padding:12px;border-radius:8px;min-width:140px;">
            <div style="font-size:22px;font-weight:700;"><?php echo $num_provas; ?></div>
            <div style="color:#6b7280;">Provas</div>
          </div>
        </div>
      </div>
      <div style="width:260px;">
        <h3>Atalhos</h3>
        <div style="display:flex;flex-direction:column;gap:8px;">
          <a href="?questao_mode=create" class="btn" onclick="location.href='?questao_mode=create'">Nova Questão</a>
          <a href="?create_prova=1" class="btn btn-secondary" onclick="showSection('exams')">Criar Prova</a>
          <a href="../../back_end/validacoes/sair.php" class="btn btn-secondary">Sair</a>
        </div>
      </div>
    </div>
  </div>

  <!-- QUESTIONS -->
  <div class="card" id="questions" style="display:none;">
    <h2>Minhas Questões</h2>
    <div style="margin-bottom:12px;">
      <a href="?questao_mode=create" class="btn" onclick="location.href='?questao_mode=create'">Cadastrar Questão</a>
      <a href="?questao_mode=list" class="btn btn-secondary" onclick="location.href='?questao_mode=list'">Listar Todas</a>
    </div>

    <?php
      $questao_mode = $_GET['questao_mode'] ?? '';
      $id_questao_edit = isset($_GET['id_questao']) ? (int) $_GET['id_questao'] : 0;
      $questao_data = [];
      if ($id_questao_edit) {
        $resqe = mysqli_query($id, "SELECT * FROM questoes WHERE id_questao = $id_questao_edit AND id_professor = $id_professor LIMIT 1");
        if ($resqe && mysqli_num_rows($resqe) > 0) $questao_data = mysqli_fetch_assoc($resqe);
      }
    ?>

    <?php if ($questao_mode == 'create' || $questao_mode == 'edit'): ?>
      <div class="card">
        <h3><?php echo $questao_mode == 'edit' ? 'Editar Questão' : 'Nova Questão'; ?></h3>
        <form method="post">
          <input type="hidden" name="action" value="<?php echo $questao_mode == 'edit' ? 'editar_questao' : 'cadastrar_questao'; ?>">
          <?php if ($questao_mode == 'edit'): ?>
            <input type="hidden" name="id_questao" value="<?php echo (int)$id_questao_edit; ?>">
          <?php endif; ?>

          <div style="margin-bottom:8px;">
            <label>Enunciado</label><br>
            <textarea name="enunciado" required style="width:100%;height:120px;"><?php echo htmlspecialchars($questao_data['enunciado'] ?? ''); ?></textarea>
          </div>

          <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;">
            <div>
              <label>A</label><input type="text" name="alt_a" required style="width:100%;" value="<?php echo htmlspecialchars($questao_data['alternativa_a'] ?? ''); ?>">
            </div>
            <div>
              <label>B</label><input type="text" name="alt_b" required style="width:100%;" value="<?php echo htmlspecialchars($questao_data['alternativa_b'] ?? ''); ?>">
            </div>
            <div>
              <label>C</label><input type="text" name="alt_c" required style="width:100%;" value="<?php echo htmlspecialchars($questao_data['alternativa_c'] ?? ''); ?>">
            </div>
            <div>
              <label>D</label><input type="text" name="alt_d" required style="width:100%;" value="<?php echo htmlspecialchars($questao_data['alternativa_d'] ?? ''); ?>">
            </div>
            <div style="grid-column:1/3;">
              <label>E (opcional)</label><input type="text" name="alt_e" style="width:100%;" value="<?php echo htmlspecialchars($questao_data['alternativa_e'] ?? ''); ?>">
            </div>
          </div>

          <div style="display:flex;gap:12px;margin-top:8px;align-items:center;">
            <div>
              <label>Assunto</label><br>
              <input type="text" name="assunto" value="<?php echo htmlspecialchars($questao_data['assunto'] ?? ''); ?>">
            </div>
            <div>
              <label>Alternativa correta</label><br>
              <select name="opcao" required>
                <option value="A" <?php echo ($questao_data['alternativa_correta'] ?? '') == 'A' ? 'selected' : ''; ?>>A</option>
                <option value="B" <?php echo ($questao_data['alternativa_correta'] ?? '') == 'B' ? 'selected' : ''; ?>>B</option>
                <option value="C" <?php echo ($questao_data['alternativa_correta'] ?? '') == 'C' ? 'selected' : ''; ?>>C</option>
                <option value="D" <?php echo ($questao_data['alternativa_correta'] ?? '') == 'D' ? 'selected' : ''; ?>>D</option>
                <option value="E" <?php echo ($questao_data['alternativa_correta'] ?? '') == 'E' ? 'selected' : ''; ?>>E</option>
              </select>
            </div>
            <div>
              <label>Pasta</label><br>
              <select name="id_pasta">
                <option value="">Sem pasta</option>
                <?php foreach ($pastas as $p): ?>
                  <option value="<?php echo $p['id_pasta']; ?>" <?php echo (isset($questao_data['id_pasta']) && $questao_data['id_pasta'] == $p['id_pasta']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($p['nome']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div style="margin-top:12px;">
            <button class="btn" type="submit">Salvar</button>
            <!-- cancelar volta para listagem de questões -->
            <button class="btn btn-secondary" type="button" onclick="location.href='?questao_mode=list'">Cancelar</button>
          </div>
        </form>
      </div>
    <?php else: ?>
      <div>
        <table>
          <thead><tr><th>ID</th><th>Enunciado</th><th>Assunto</th><th>Pasta</th><th>Ações</th></tr></thead>
          <tbody>
            <?php foreach ($questoes as $q): ?>
              <tr>
                <td><?php echo $q['id_questao']; ?></td>
                <td style="max-width:420px;"><?php echo htmlspecialchars(mb_strimwidth(strip_tags($q['enunciado']),0,220,'...')); ?></td>
                <td><?php echo htmlspecialchars($q['assunto']); ?></td>
                <td>
                  <?php
                    $nome_p = 'Sem pasta';
                    foreach ($pastas as $pp) {
                      if ($pp['id_pasta'] == $q['id_pasta']) { $nome_p = $pp['nome']; break; }
                    }
                    echo htmlspecialchars($nome_p);
                  ?>
                </td>
                <td>
                  <a href="?questao_mode=edit&id_questao=<?php echo $q['id_questao']; ?>" class="btn" onclick="location.href='?questao_mode=edit&id_questao=<?php echo $q['id_questao']; ?>'">Editar</a>
                  <form method="post" style="display:inline;">
                    <input type="hidden" name="action" value="deletar_questao">
                    <input type="hidden" name="id_questao" value="<?php echo $q['id_questao']; ?>">
                    <button type="submit" class="btn btn-secondary" onclick="return confirm('Excluir questão?')">Excluir</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($questoes)): ?>
              <tr><td colspan="5">Nenhuma questão cadastrada.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- PASTAS -->
  <div class="card" id="folders" style="display:none;">
    <h2>Pastas</h2>
    <div style="margin-bottom:12px;">
      <!-- abrir formulário de nova pasta sem esconder lista -->
      <a href="?pasta_mode=create" class="btn" onclick="location.href='?pasta_mode=create'">Nova Pasta</a>
    </div>

    <?php
      $pasta_mode = $_GET['pasta_mode'] ?? '';
      $id_pasta_edit = isset($_GET['id_pasta']) ? (int) $_GET['id_pasta'] : 0;
      $pasta_data = [];
      if ($id_pasta_edit) {
        $resp = mysqli_query($id, "SELECT * FROM pastas WHERE id_pasta = $id_pasta_edit AND id_professor = $id_professor LIMIT 1");
        if ($resp && mysqli_num_rows($resp) > 0) $pasta_data = mysqli_fetch_assoc($resp);
      }
    ?>

    <?php if ($pasta_mode == 'create' || $pasta_mode == 'edit'): ?>
      <div class="card">
        <h3><?php echo $pasta_mode == 'edit' ? 'Editar Pasta' : 'Nova Pasta'; ?></h3>
        <form method="post">
          <input type="hidden" name="action" value="<?php echo $pasta_mode == 'edit' ? 'editar_pasta' : 'cadastrar_pasta'; ?>">
          <?php if ($pasta_mode == 'edit'): ?><input type="hidden" name="id_pasta" value="<?php echo (int)$id_pasta_edit; ?>"><?php endif; ?>
          <label>Nome</label><br>
          <input type="text" name="nome_pasta" value="<?php echo htmlspecialchars($pasta_data['nome'] ?? ''); ?>" required><br><br>
          <button class="btn" type="submit">Salvar</button>
          <!-- cancelar volta para listagem de pastas -->
          <button class="btn btn-secondary" type="button" onclick="location.href='?pasta_mode=list'">Cancelar</button>
        </form>
      </div>
    <?php endif; ?>

    <!-- Lista de pastas sempre visível (fica abaixo do formulário se abrir) -->
    <div class="card" style="margin-top:12px;">
      <table>
        <thead><tr><th>ID</th><th>Nome</th><th>Ações</th></tr></thead>
        <tbody>
          <?php foreach ($pastas as $p): ?>
            <tr>
              <td><?php echo $p['id_pasta']; ?></td>
              <td><?php echo htmlspecialchars($p['nome']); ?></td>
              <td>
                <a href="?pasta_mode=edit&id_pasta=<?php echo $p['id_pasta']; ?>" class="btn" onclick="location.href='?pasta_mode=edit&id_pasta=<?php echo $p['id_pasta']; ?>'">Editar</a>
                <?php if ($p['id_pasta'] != $default_pasta_id): ?>
                  <form method="post" style="display:inline;">
                    <input type="hidden" name="action" value="deletar_pasta">
                    <input type="hidden" name="id_pasta" value="<?php echo $p['id_pasta']; ?>">
                    <button type="submit" class="btn btn-secondary" onclick="return confirm('Deletar pasta?')">Deletar</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($pastas)): ?>
            <tr><td colspan="3">Nenhuma pasta criada.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- PROVAS -->
  <div class="card" id="exams" style="display:none;">
    <h2>Minhas Provas</h2>
    <div style="margin-bottom:10px;">
      <button class="btn" onclick="document.getElementById('createProvaForm').style.display='block'">Criar Nova Prova</button>
    </div>

    <div id="createProvaForm" style="display:none;">
      <div class="card">
        <form method="post">
          <input type="hidden" name="action" value="create_prova">
          <label>Título</label><br><input type="text" name="titulo" required><br>
          <label>Descrição</label><br><input type="text" name="descricao"><br><br>
          <button class="btn" type="submit">Criar</button>
          <a href="#" class="btn btn-secondary" onclick="document.getElementById('createProvaForm').style.display='none';return false;">Cancelar</a>
        </form>
      </div>
    </div>

    <?php if ($current_id_prova): ?>
      <div class="card">
        <h3>Selecionar Questões para a Prova</h3>
        <form method="post">
          <input type="hidden" name="action" value="selecionar_questoes">
          <input type="hidden" name="id_prova" value="<?php echo (int)$current_id_prova; ?>">
          <label><input type="checkbox" name="use_cabecalho"> Usar cabeçalho (se cadastrado)</label>
          <label style="margin-left:12px;"><input type="checkbox" name="use_instrucoes"> Incluir instruções (se cadastradas)</label>

          <table style="margin-top:10px;">
            <thead><tr><th>Selecionar</th><th>ID</th><th>Enunciado</th><th>Assunto</th></tr></thead>
            <tbody>
              <?php foreach ($questoes as $q): ?>
                <tr>
                  <td><input type="checkbox" name="selecionadas[]" value="<?php echo $q['id_questao']; ?>"></td>
                  <td><?php echo $q['id_questao']; ?></td>
                  <td><?php echo htmlspecialchars(mb_strimwidth(strip_tags($q['enunciado']),0,160,'...')); ?></td>
                  <td><?php echo htmlspecialchars($q['assunto']); ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($questoes)): ?>
                <tr><td colspan="4">Nenhuma questão disponível.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
          <div style="margin-top:8px;">
            <button class="btn" type="submit">Gerar Prova e Gabarito</button>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <div class="card">
      <h3>Provas existentes</h3>
      <table>
        <thead><tr><th>ID</th><th>Título</th><th>Descrição</th><th>Ações</th></tr></thead>
        <tbody>
          <?php foreach ($provas_list as $p): ?>
            <tr>
              <td><?php echo $p['id_prova']; ?></td>
              <td><?php echo htmlspecialchars($p['titulo']); ?></td>
              <td><?php echo htmlspecialchars($p['descricao']); ?></td>
              <td>
                <?php $filep="prova_{$p['id_prova']}.pdf"; $fileg="gabarito_{$p['id_prova']}.pdf"; ?>
                <a href="<?php echo $filep; ?>" target="_blank" class="btn btn-secondary">Abrir Prova</a>
                <a href="<?php echo $fileg; ?>" target="_blank" class="btn btn-secondary">Abrir Gabarito</a>

                <form method="post" style="display:inline;">
                  <input type="hidden" name="action" value="delete_prova">
                  <input type="hidden" name="id_prova" value="<?php echo $p['id_prova']; ?>">
                  <button class="btn btn-secondary" type="submit" onclick="return confirm('Deletar prova e gabarito?')">Deletar</button>
                </form>

                <form method="post" style="display:inline;">
                  <input type="hidden" name="action" value="gerar_prova">
                  <input type="hidden" name="id_prova" value="<?php echo $p['id_prova']; ?>">
                  <label style="display:inline-block;margin-left:8px;">
                    <input type="checkbox" name="use_cabecalho"> Cabeçalho
                  </label>
                  <label style="display:inline-block;margin-left:8px;">
                    <input type="checkbox" name="use_instrucoes"> Instruções
                  </label>
                  <button class="btn" type="submit">Re-gerar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($provas_list)): ?>
            <tr><td colspan="4">Nenhuma prova criada.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>

  <!-- SETTINGS -->
  <div class="card" id="settings" style="display:none;">
    <h2>Configurações</h2>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div class="card">
        <h3>Perfil</h3>
        <form method="post">
          <input type="hidden" name="user_action" value="edit_user">
          <label>Nome</label><br><input type="text" name="nome" value="<?php echo htmlspecialchars($user_data['nome']); ?>" required><br>
          <label>Login</label><br><input type="text" name="login" value="<?php echo htmlspecialchars($user_data['login']); ?>" required><br>
          <label>E-mail</label><br><input type="email" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required><br>
          <label>Senha (deixe vazio para manter)</label><br><input type="password" name="senha"><br><br>
          <button class="btn" type="submit">Salvar perfil</button>
        </form>
      </div>

      <div class="card">
        <h3>Preferências de prova</h3>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="user_action" value="update_perfil_prova">
          <label>Instruções</label><br>
          <textarea name="instrucoes" style="width:100%;height:120px;"><?php echo htmlspecialchars($perfil_prova_data['instrucoes'] ?? ''); ?></textarea><br>
          <label>Cabeçalho (imagem)</label><br>
          <input type="file" name="cabecalho" accept="image/*"><br>
          <?php if (!empty($perfil_prova_data['cabecalho'])): ?>
            <div style="margin-top:8px;">
              <strong>Imagem atual:</strong><br>
              <?php list($mime, $b64) = blob_to_data_uri($perfil_prova_data['cabecalho']); ?>
              <?php if (!empty($b64)): ?>
                <img src="data:<?php echo htmlspecialchars($mime); ?>;base64,<?php echo $b64; ?>" style="max-width:180px;display:block;margin-top:6px;">
              <?php endif; ?>
            </div>
          <?php endif; ?>
          <div style="margin-top:8px;">
            <button class="btn" type="submit">Salvar preferências</button>
          </div>
        </form>
      </div>
    </div>
  </div>

</div> <!-- main-content -->

<script>
  function showSection(id){
    document.querySelectorAll('.card[id]').forEach(function(el){ el.style.display = 'none'; });
    var el = document.getElementById(id);
    if(el) el.style.display = 'block';
    window.scrollTo(0,0);
  }
  document.querySelectorAll('.menu-link').forEach(function(link){
    link.addEventListener('click', function(e){
      e.preventDefault();
      var sec = this.getAttribute('data-section');
      showSection(sec);
    });
  });
  // inicializa seção ativa definida pelo servidor
  showSection('<?php echo addslashes($active_section); ?>');
</script>

</body>
</html>
