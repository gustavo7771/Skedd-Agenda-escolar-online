<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['id']) && !isset($_SESSION['admin_autenticado'])) { 
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

$banco_dados = 'provas.json';
if (!file_exists($banco_dados)) { file_put_contents($banco_dados, json_encode([])); }

$apelidos_turmas = file_exists('turmas.json') ? (json_decode(file_get_contents('turmas.json'), true) ?: []) : [];
$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_SPECIAL_CHARS);

// --- LISTAR AVALIAÇÕES ---
if ($action === 'listar') {
    $provas_todas = json_decode(file_get_contents($banco_dados), true) ?: [];
    $provas_filtradas = [];
    $nivel = $_SESSION['usuario_nivel'] ?? 'aluno';

    if ($nivel === 'professor' || $nivel === 'admin') {
        $turma_alvo = filter_input(INPUT_GET, 'turma', FILTER_SANITIZE_SPECIAL_CHARS);
        if (empty($turma_alvo)) { echo json_encode([]); exit; }

        $id_professor_logado = $_SESSION['usuario_id'] ?? $_SESSION['id'] ?? 'admin_panel';

        foreach ($provas_todas as $prova) {
            if ($turma_alvo === 'todas') {
                if ($nivel === 'admin') {
                    $provas_filtradas[] = $prova;
                } else {
                    if (isset($prova['criador_id']) && (string)$prova['criador_id'] === (string)$id_professor_logado) {
                        $provas_filtradas[] = $prova;
                    }
                }
            } else {
                if (isset($prova['turma']) && ($prova['turma'] === $turma_alvo || $prova['turma'] === 'todas')) {
                    $provas_filtradas[] = $prova;
                }
            }
        }
    } else {
        $turma_alvo = $_SESSION['turma'] ?? '';
        foreach ($provas_todas as $prova) {
            if ((isset($prova['turma']) && $prova['turma'] === $turma_alvo) || (isset($prova['turma']) && $prova['turma'] === 'todas')) {
                $provas_filtradas[] = $prova;
            }
        }
    }

    foreach ($provas_filtradas as &$prova) {
        $id_t = $prova['turma'] ?? '';
        if ($id_t && isset($apelidos_turmas[$id_t]) && $id_t !== 'todas') {
            $prova['title'] = str_replace("(".$id_t.")", "(".$apelidos_turmas[$id_t].")", $prova['title']);
        }
    }

    echo json_encode($provas_filtradas);
    exit;
}

// --- MARCAR NOVA PROVA ---
if ($action === 'salvar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nivel = $_SESSION['usuario_nivel'] ?? 'aluno';
    if ($nivel !== 'professor' && $nivel !== 'admin') { echo json_encode(['success' => false, 'message' => 'Permissão negada.']); exit; }

    $nome = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_SPECIAL_CHARS);
    $data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_URL);
    $cor  = filter_input(INPUT_POST, 'col', FILTER_SANITIZE_URL); 
    $descricao = filter_input(INPUT_POST, 'descricao', FILTER_SANITIZE_SPECIAL_CHARS);
    $turma = filter_input(INPUT_POST, 'turma', FILTER_SANITIZE_SPECIAL_CHARS);
    $nome_admin = filter_input(INPUT_POST, 'nome_admin', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';

    if ($nome && $data && $cor && !empty($turma)) {
        $provas_atuais = json_decode(file_get_contents($banco_dados), true) ?: [];
        $id_professor_logado = $_SESSION['usuario_id'] ?? $_SESSION['id'] ?? 'admin_panel';

        if ($turma === 'todas' && $nivel === 'professor') {
            $turmas_prof = $_SESSION['turmas_professor'] ?? [];
            foreach ($turmas_prof as $t_prof) {
                $nome_exibicao = $apelidos_turmas[$t_prof] ?? $t_prof;
                $provas_atuais[] = [
                    'id' => uniqid(),
                    'title' => $nome . " (" . $nome_exibicao . ")",
                    'description' => $descricao ?: '',
                    'start' => $data,
                    'color' => $cor,
                    'turma' => $t_prof, 
                    'criador_id' => $id_professor_logado,
                    'is_admin' => false,
                    'nome_admin' => '',
                    'allDay' => true
                ];
            }
            file_put_contents($banco_dados, json_encode($provas_atuais, JSON_PRETTY_PRINT));
        } else {
            $nome_exibicao_turma = $turma === 'todas' ? "Todas as Turmas" : ($apelidos_turmas[$turma] ?? $turma);
            $provas_atuais[] = [
                'id' => uniqid(),
                'title' => $nome . " (" . $nome_exibicao_turma . ")",
                'description' => $descricao ?: '',
                'start' => $data,
                'color' => $cor,
                'turma' => $turma, 
                'criador_id' => $id_professor_logado,
                'is_admin' => ($nivel === 'admin'),
                'nome_admin' => ($nivel === 'admin') ? $nome_admin : '',
                'allDay' => true
            ];
            file_put_contents($banco_dados, json_encode($provas_atuais, JSON_PRETTY_PRINT));
        }
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Campos ausentes.']);
    }
    exit;
}

// --- DESMARCAR PROVA ---
if ($action === 'deletar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nivel = $_SESSION['usuario_nivel'] ?? 'aluno';
    if ($nivel !== 'professor' && $nivel !== 'admin') { echo json_encode(['success' => false, 'message' => 'Acesso Negado.']); exit; }

    $id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_SPECIAL_CHARS);
    if ($id) {
        $provas_atuais = json_decode(file_get_contents($banco_dados), true) ?: [];
        $id_professor_logado = $_SESSION['usuario_id'] ?? $_SESSION['id'] ?? 'admin_panel';
        
        $autorizado = false;
        foreach ($provas_atuais as $key => $prova) {
            if (isset($prova['id']) && $prova['id'] === $id) {
                if ($nivel === 'admin' || !isset($prova['criador_id']) || (string)$prova['criador_id'] === (string)$id_professor_logado) {
                    $autorizado = true;
                    unset($provas_atuais[$key]);
                }
                break;
            }
        }

        if (!$autorizado) { echo json_encode(['success' => false, 'message' => 'Acesso negado.']); exit; }

        file_put_contents($banco_dados, json_encode(array_values($provas_atuais), JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
    }
    exit;
}
?>