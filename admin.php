<?php
session_start();
$arquivo_usuarios = 'usuarios.json';
$arquivo_turmas = 'turmas.json';
$arquivo_provas = 'provas.json';

// SENHA MESTRE PARA ACESSAR ESSE PAINEL
$SENHA_ADMIN = 'admin123';

// Inicializa os arquivos caso não existam
if (!file_exists($arquivo_turmas)) {
    $turmas_iniciais = [
        "1001" => "1001 - Sem Apelido",
        "2001" => "2001 - Sem Apelido",
        "3001" => "3001 - Sem Apelido"
    ];
    file_put_contents($arquivo_turmas, json_encode($turmas_iniciais, JSON_PRETTY_PRINT));
}
if (!file_exists($arquivo_usuarios)) { file_put_contents($arquivo_usuarios, json_encode([])); }
if (!file_exists($arquivo_provas)) { file_put_contents($arquivo_provas, json_encode([])); }

$turmas_sistema = json_decode(file_get_contents($arquivo_turmas), true) ?: [];
$usuarios = json_decode(file_get_contents($arquivo_usuarios), true) ?: [];
$provas_sistema = json_decode(file_get_contents($arquivo_provas), true) ?: [];

function registrarLogAdmin($acao, $detalhes) {
    $arquivo_logs = 'logs.json';
    $logs = file_exists($arquivo_logs) ? json_decode(file_get_contents($arquivo_logs), true) : [];
    $logs[] = [
        'data' => date('Y-m-d H:i:s'),
        'acao' => $acao,
        'detalhes' => $detalhes
    ];
    file_put_contents($arquivo_logs, json_encode($logs, JSON_PRETTY_PRINT));
}

// Processa login do Admin (Nome, Cargo e Código)
if (!isset($_SESSION['admin_autenticado'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['senha_master'])) {
        $nome_admin = trim($_POST['nome_admin'] ?? '');
        $cargo_admin = trim($_POST['cargo_admin'] ?? '');
        
        if ($_POST['senha_master'] === $SENHA_ADMIN && !empty($nome_admin) && !empty($cargo_admin)) {
            $_SESSION['admin_autenticado'] = true;
            $_SESSION['admin_nome'] = $nome_admin;
            $_SESSION['admin_cargo'] = $cargo_admin;
            $_SESSION['usuario_nivel'] = 'admin'; // Para o sistema reconhecer privilégios globais
            $_SESSION['nome'] = $nome_admin; // Define o nome para agenda.php
        } else {
            $erro = "Credenciais incorretas ou campos vazios!";
        }
    }
}

if (isset($_SESSION['admin_autenticado']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ==========================================
    // 1. GERENCIAR USUÁRIOS
    // ==========================================
    if (isset($_POST['id_user']) && isset($_POST['acao_user'])) {
        $id_user = $_POST['id_user'];
        $acao = $_POST['acao_user'];

        if ($acao === 'salvar') {
            $nivel = $_POST['nivel'] ?? 'aluno';
            $turma = $_POST['turma'] ?? '';
            $novo_email = filter_input(INPUT_POST, 'email_user', FILTER_VALIDATE_EMAIL);
            $turmas_prof = $_POST['turmas_prof'] ?? [];
            $nova_senha = trim($_POST['nova_senha'] ?? '');

            foreach ($usuarios as &$u) {
                if ($u['id'] === $id_user) {
                    $u['nivel'] = $nivel;
                    if ($novo_email) { $u['email'] = $novo_email; }
                    if ($nova_senha !== '') { $u['senha'] = password_hash($nova_senha, PASSWORD_BCRYPT); }

                    if ($nivel === 'professor' || $nivel === 'admin') {
                        $u['turma'] = 'Docente/Admin';
                        $u['turmas'] = $turmas_prof;
                    } else {
                        $u['turma'] = $turma;
                        if (isset($u['turmas'])) { unset($u['turmas']); }
                    }
                    break;
                }
            }
            file_put_contents($arquivo_usuarios, json_encode($usuarios, JSON_PRETTY_PRINT));
            registrarLogAdmin("Editar Usuário", "O usuário ID {$id_user} foi modificado.");
            header("Location: admin.php?sucesso_acao=Usuário+salvo+com+sucesso!");
            exit;
            
        } elseif ($acao === 'deletar') {
            $usuarios = array_filter($usuarios, function($u) use ($id_user) { return $u['id'] !== $id_user; });
            file_put_contents($arquivo_usuarios, json_encode(array_values($usuarios), JSON_PRETTY_PRINT));
            registrarLogAdmin("Deletar Usuário", "Usuário ID {$id_user} deletado.");
            header("Location: admin.php?sucesso_acao=Usuário+deletado!");
            exit;
        }
    }

    // ==========================================
    // 2. GERENCIAR TURMAS
    // ==========================================
    if (isset($_POST['nova_turma'])) {
        $codigo = trim(filter_input(INPUT_POST, 'nova_turma', FILTER_SANITIZE_SPECIAL_CHARS));
        $apelido = trim(filter_input(INPUT_POST, 'apelido_turma', FILTER_SANITIZE_SPECIAL_CHARS)) ?: "Sem Apelido";
        if (!empty($codigo)) {
            $turmas_sistema[$codigo] = $apelido;
            file_put_contents($arquivo_turmas, json_encode($turmas_sistema, JSON_PRETTY_PRINT));
            registrarLogAdmin("Criar Turma", "Turma {$codigo} ('{$apelido}') criada.");
        }
        header("Location: admin.php?sucesso_acao=Turma+criada!");
        exit;
    }
    
    if (isset($_POST['editar_turma'])) {
        $codigo = trim($_POST['editar_turma']);
        $novo_apelido = trim(filter_input(INPUT_POST, 'novo_apelido', FILTER_SANITIZE_SPECIAL_CHARS));
        if (isset($turmas_sistema[$codigo]) && !empty($novo_apelido)) {
            $turmas_sistema[$codigo] = $novo_apelido;
            file_put_contents($arquivo_turmas, json_encode($turmas_sistema, JSON_PRETTY_PRINT));
            header("Location: admin.php?sucesso_acao=Apelido+atualizado!");
            exit;
        }
    }

    if (isset($_POST['excluir_turma'])) {
        $excluir = $_POST['excluir_turma'];
        if (isset($turmas_sistema[$excluir])) {
            unset($turmas_sistema[$excluir]);
            file_put_contents($arquivo_turmas, json_encode($turmas_sistema, JSON_PRETTY_PRINT));
        }
        header("Location: admin.php?sucesso_acao=Turma+excluída!");
        exit;
    }

    // ==========================================
    // 3. GERENCIAR EVENTOS (NOVO)
    // ==========================================
    if (isset($_POST['acao_evento'])) {
        $acao_ev = $_POST['acao_evento'];
        
        if ($acao_ev === 'salvar') {
            $nome = filter_input(INPUT_POST, 'nome_prova', FILTER_SANITIZE_SPECIAL_CHARS);
            $desc = filter_input(INPUT_POST, 'desc_prova', FILTER_SANITIZE_SPECIAL_CHARS);
            $data = filter_input(INPUT_POST, 'data_prova', FILTER_SANITIZE_SPECIAL_CHARS);
            $cor = filter_input(INPUT_POST, 'cor_prova', FILTER_SANITIZE_SPECIAL_CHARS);
            $tipo_alvo = $_POST['tipo_alvo'] ?? 'todas';

            $alvos = [];
            if ($tipo_alvo === 'todas') {
                $alvos = ['todas'];
            } elseif ($tipo_alvo === 'uma' && !empty($_POST['turma_unica'])) {
                $alvos = [$_POST['turma_unica']];
            } elseif ($tipo_alvo === 'multiplas' && !empty($_POST['turmas_multiplas'])) {
                $alvos = $_POST['turmas_multiplas'];
            }

            $nome_admin_formatado = ($_SESSION['admin_cargo'] ?? 'Admin') . ' - ' . ($_SESSION['admin_nome'] ?? 'Direção');

            foreach ($alvos as $t) {
                $nome_exibicao = ($t === 'todas') ? "Todas as Turmas" : ($turmas_sistema[$t] ?? $t);
                $provas_sistema[] = [
                    'id' => uniqid(),
                    'title' => $nome . " (" . $nome_exibicao . ")",
                    'description' => $desc,
                    'start' => $data,
                    'color' => $cor,
                    'turma' => $t,
                    'criador_id' => 'admin_panel',
                    'is_admin' => true,
                    'nome_admin' => $nome_admin_formatado,
                    'allDay' => true
                ];
            }
            file_put_contents($arquivo_provas, json_encode($provas_sistema, JSON_PRETTY_PRINT));
            registrarLogAdmin("Criar Evento", "Evento '{$nome}' criado.");
            header("Location: admin.php?sucesso_acao=Evento(s)+criado(s)+com+sucesso!");
            exit;
            
        } elseif ($acao_ev === 'deletar' && isset($_POST['id_evento'])) {
            $id_excluir = $_POST['id_evento'];
            $provas_sistema = array_filter($provas_sistema, function($p) use ($id_excluir) {
                return $p['id'] !== $id_excluir;
            });
            file_put_contents($arquivo_provas, json_encode(array_values($provas_sistema), JSON_PRETTY_PRINT));
            registrarLogAdmin("Excluir Evento", "Evento ID {$id_excluir} removido.");
            header("Location: admin.php?sucesso_acao=Evento+excluído!");
            exit;
        }
    }
}

// Contagem para listagem de turmas
$contagem_usuarios = [];
$contagem_docentes = 0;
foreach ($turmas_sistema as $cod => $apelido) { $contagem_usuarios[$cod] = 0; }
foreach ($usuarios as $u) {
    if (!empty($u['turma']) && isset($contagem_usuarios[$u['turma']])) {
        $contagem_usuarios[$u['turma']]++;
    }
    if (($u['turma'] ?? '') === 'Docente' || ($u['turma'] ?? '') === 'Docente/Admin') {
        $contagem_docentes++;
    }
}

if (!isset($_SESSION['admin_autenticado'])): ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>Skedd - Admin Login</title>
        <link rel="icon" type="image/x-icon" href="https://i.ibb.co/Rp7Dc0cq/Captura-de-tela-2026-05-19-100134.webp">
        <style>
            body { background-color: #111; color: white; font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .login-box { max-width: 340px; width: 100%; background: white; padding: 30px; border-radius: 15px; color: black; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.5); }
            input[type="text"], input[type="password"] { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ccc; border-radius: 8px; box-sizing: border-box; font-size: 15px; }
            button { width: 100%; padding: 12px; background: #168fff; color: white; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; font-weight: bold; margin-top: 10px; }
            button:hover { background: #1276d1; }
            .erro { color: #721c24; background: #f8d7da; padding: 10px; border-radius: 6px; font-size: 14px; font-weight: bold; margin-bottom: 10px; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <img src="https://i.ibb.co/ymJC5sNN/Captura-de-tela-2026-05-19-100134-1.webp" alt="Logo" width="60">
            <h3 style="margin: 10px 0 20px 0;">Acesso Administrativo</h3>
            <?php if(isset($erro)): ?> <div class="erro"><?= $erro ?></div> <?php endif; ?>
            <form method="POST">
                <input type="text" name="nome_admin" placeholder="Seu Nome Completo" required>
                <input type="text" name="cargo_admin" placeholder="Seu Cargo (Ex: Diretor, Coordenador)" required>
                <input type="password" name="senha_master" placeholder="Código de Acesso Admin" required>
                <button type="submit">Autenticar</button>
            </form>
        </div>
    </body>
    </html>
<?php exit; endif; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Skedd - Painel de Controle Admin</title>
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/ymJC5sNN/Captura-de-tela-2026-05-19-100134-1.webp">
    <style>
        body { background-color: #141414; color: #fff; font-family: Arial, sans-serif; padding: 20px; }
        .container-admin { max-width: 95%; margin: 0 auto; }
        .box { background: #222; border-radius: 12px; padding: 25px; margin-bottom: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.3); border: 1px solid #333; }
        h2 { margin-top: 0; color: #168fff; font-size: 22px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; color: #fff; background: #1a1a1a; border-radius: 8px; overflow: hidden; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #333; vertical-align: middle; }
        th { background-color: #2c2c2c; color: #168fff; font-weight: bold; }
        tr:hover { background-color: #252525; }
        
        select, input[type="text"], input[type="email"], input[type="date"], textarea { background: #2a2a2a; color: #fff; border: 1px solid #444; padding: 8px 12px; border-radius: 6px; font-size: 14px; outline: none; width: 100%; box-sizing: border-box; margin-bottom: 10px; }
        select:focus, input:focus, textarea:focus { border-color: #168fff; }
        
        .btn-salvar { background: #168fff; color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-weight: bold; transition: 0.2s; white-space: nowrap; }
        .btn-salvar:hover { background: #1276d1; }
        .btn-resetar { background: #e0a800; color: #111; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-weight: bold; transition: 0.2s; white-space: nowrap; }
        .btn-resetar:hover { background: #ffc107; }
        .btn-deletar { background: #c82333; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-weight: bold; transition: 0.2s; white-space: nowrap; }
        .btn-deletar:hover { background: #dc3545; }
        
        .alerta-sucesso { background: #155724; color: #d4edda; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; border: 1px solid #c3e6cb; }
        
        .tabs-header { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .tab-btn { background: transparent; color: #888; border: none; padding: 10px 20px; font-size: 16px; font-weight: bold; cursor: pointer; border-radius: 8px 8px 0 0; transition: 0.3s; position: relative; }
        .tab-btn:hover { color: #fff; background: rgba(255, 255, 255, 0.05); }
        .tab-btn.active { color: #168fff; }
        .tab-btn.active::after { content: ''; position: absolute; bottom: -12px; left: 0; width: 100%; height: 3px; background: #168fff; border-radius: 3px; }
        .tab-content { display: none; animation: fadeIn 0.3s ease; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .radio-group { display: flex; gap: 20px; margin-bottom: 15px; }
        .radio-group label { cursor: pointer; display: flex; align-items: center; gap: 5px; font-size: 15px; }
    </style>
</head>
<body>

<div class="container-admin">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h1 style="margin: 0; font-size: 28px; color: #fff;">Painel Administrativo Skedd</h1>
            <p style="margin: 5px 0 0 0; color: #888;">Logado como: <strong><?= htmlspecialchars($_SESSION['admin_nome']) ?></strong> (<?= htmlspecialchars($_SESSION['admin_cargo']) ?>) | <a href="agenda.php" style="color: #168fff;">Ir para Agenda</a></p>
        </div>
    </div>

    <?php if (isset($_GET['sucesso_acao'])): ?>
        <div class="alerta-sucesso">✔️ <?= htmlspecialchars($_GET['sucesso_acao']) ?></div>
    <?php endif; ?>

    <div class="tabs-header">
        <button class="tab-btn active" onclick="switchTab('turmas', this)">📚 Gerenciar Turmas</button>
        <button class="tab-btn" onclick="switchTab('eventos', this)">📅 Gerenciar Eventos</button>
        <button class="tab-btn" onclick="switchTab('usuarios', this)">👥 Gerenciar Usuários</button>
        <button class="tab-btn" onclick="switchTab('log', this)">📋 Logs do Sistema</button>
    </div>

    <div id="tab-eventos" class="tab-content ">
        <div class="box">
            <h2 style="border:none;">Criar Nova Avaliação / Evento</h2>
            <form method="POST" style="background: #1a1a1a; padding: 20px; border-radius: 8px; border: 1px solid #333;">
                <input type="hidden" name="acao_evento" value="salvar">
                
                <div style="display: flex; gap: 15px;">
                    <div style="flex: 2;">
                        <label>Nome do Evento</label>
                        <input type="text" name="nome_prova" placeholder="Ex: Prova de Matemática" required>
                    </div>
                    <div style="flex: 1;">
                        <label>Data</label>
                        <input type="date" name="data_prova" required>
                    </div>
                    <div style="flex: 0.5;">
                        <label>Cor</label>
                        <input type="color" name="cor_prova" value="#168fff" style="padding: 2px; height: 38px;">
                    </div>
                </div>
                
                <label>Descrição / Observações</label>
                <textarea name="desc_prova" placeholder="Detalhes opcionais..." rows="3"></textarea>

                <label style="display: block; margin: 15px 0 5px 0; font-weight: bold; color: #168fff;">Público Alvo (Turmas):</label>
                <div class="radio-group">
                    <label><input type="radio" name="tipo_alvo" value="todas" checked> Todas as Turmas (Global)</label>
                    <label><input type="radio" name="tipo_alvo" value="uma"> Apenas Uma Turma</label>
                    <label><input type="radio" name="tipo_alvo" value="multiplas"> Múltiplas Turmas Específicas</label>
                </div>

                <div id="box-turma-unica" style="display: none; margin-bottom: 15px;">
                    <select name="turma_unica">
                        <option value="" disabled selected>Selecione a turma...</option>
                        <?php foreach ($turmas_sistema as $cod => $apelido): ?>
                            <option value="<?= htmlspecialchars($cod) ?>"><?= htmlspecialchars($cod) ?> - <?= htmlspecialchars($apelido) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="box-turmas-multiplas" style="display: none; margin-bottom: 15px; max-height: 150px; overflow-y: auto; background: #222; padding: 15px; border: 1px solid #444; border-radius: 6px;">
                    <?php foreach ($turmas_sistema as $cod => $apelido): ?>
                        <label style="display: block; margin-bottom: 8px; cursor: pointer;">
                            <input type="checkbox" name="turmas_multiplas[]" value="<?= htmlspecialchars($cod) ?>"> 
                            <?= htmlspecialchars($cod) ?> - <?= htmlspecialchars($apelido) ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="btn-salvar" style="padding: 12px 20px; font-size: 15px;">Salvar Evento</button>
            </form>

            <h2 style="border:none; margin-top: 40px;">Eventos Ativos no Sistema</h2>
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Título do Evento</th>
                        <th>Turma(s) Alvo</th>
                        <th>Autor (Admin/Prof)</th>
                        <th style="text-align: center;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Ordena eventos por data
                    usort($provas_sistema, function($a, $b) { return strtotime($a['start']) - strtotime($b['start']); });
                    foreach ($provas_sistema as $prova): 
                    ?>
                    <tr>
                        <td style="font-weight: bold; color: #168fff;"><?= date('d/m/Y', strtotime($prova['start'])) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($prova['title']) ?></strong>
                            <?php if(!empty($prova['description'])): ?>
                                <div style="font-size: 12px; color: #888; margin-top: 4px;"><?= htmlspecialchars($prova['description']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($prova['turma'] === 'todas' ? 'Global (Todas)' : $prova['turma']) ?></td>
                        <td style="font-size: 13px;">
                            <?= htmlspecialchars($prova['nome_admin'] ?? ($prova['criador_id'] == 'admin_panel' ? 'Admin' : 'Professor')) ?>
                        </td>
                        <td style="text-align: center;">
                            <form method="POST" style="margin:0;" onsubmit="return confirm('Tem certeza que deseja excluir permanentemente este evento?');">
                                <input type="hidden" name="acao_evento" value="deletar">
                                <input type="hidden" name="id_evento" value="<?= htmlspecialchars($prova['id']) ?>">
                                <button type="submit" class="btn-deletar">🗑️ Excluir</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($provas_sistema)): ?>
                        <tr><td colspan="5" style="text-align: center; color: #888; padding: 20px;">Nenhum evento registrado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="tab-turmas" class="tab-content active">
        <div class="box">
            <h2 style="border:none;">Gerenciamento de Turmas e Apelidos</h2>
            <form method="POST" style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
                <input type="text" name="nova_turma" placeholder="Código da turma (Ex: 1001)" required style="width: 200px;">
                <input type="text" name="apelido_turma" placeholder="Apelido/Nome da Turma" style="flex: 1; min-width: 250px;">
                <button type="submit" class="btn-salvar" style="background: #28a745; font-size: 14px;">+ Adicionar Turma</button>
            </form>
            
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($turmas_sistema as $codigo => $apelido): ?>
                    <div style="background: #2c2c2c; padding: 12px 18px; border-radius: 12px; display: flex; align-items: center; justify-content: space-between; gap: 15px; border: 1px solid #444; flex-wrap: wrap;">
                        <div style="display: flex; align-items: center; gap: 12px; min-width: 180px;">
                            <span style="font-size: 18px; font-weight: bold; color: #168fff;"><?= htmlspecialchars($codigo) ?></span>
                            <span style="background: #444; padding: 4px 10px; border-radius: 6px; font-size: 12px; color: #fff; font-weight: bold;">
                                <?= $contagem_usuarios[$codigo] ?? 0 ?> Usuário(s)
                            </span>
                        </div>
                        
                        <form method="POST" style="margin: 0; display: flex; gap: 8px; flex: 1; min-width: 250px;">
                            <input type="hidden" name="editar_turma" value="<?= htmlspecialchars($codigo) ?>">
                            <input type="text" name="novo_apelido" value="<?= htmlspecialchars($apelido) ?>" required style="flex: 1; padding: 8px; font-size: 14px;" placeholder="Nome da Turma">
                            <button type="submit" class="btn-salvar" style="padding: 8px 12px;">✏️ Salvar</button>
                        </form>
                        
                        <div style="display: flex; gap: 8px;">
                            <form method="POST" style="margin: 0;" onsubmit="return confirm('Tem certeza que deseja excluir esta turma?');">
                                <input type="hidden" name="excluir_turma" value="<?= htmlspecialchars($codigo) ?>">
                                <button type="submit" class="btn-deletar" style="padding: 8px 12px;">🗑️ Excluir</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div id="tab-usuarios" class="tab-content">
        <div class="box">
            <h2 style="border:none;">Gerenciamento de Usuários</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Nome Completo</th>
                            <th>E-mail</th>
                            <th>Nível</th>
                            <th>Turma Vinculada</th>
                            <th>Turmas Ministradas (Professores)</th>
                            <th style="width: 290px; text-align: center;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                        <form method="POST" action="admin.php" style="margin: 0;">
                            <input type="hidden" name="id_user" value="<?= $u['id'] ?>">
                            <tr>
                                <td style="font-weight: bold; color: #168fff;"><?= htmlspecialchars($u['nome'] ?? 'Não informado') ?></td>
                                <td><input type="email" name="email_user" value="<?= htmlspecialchars($u['email'] ?? '') ?>" required style="font-size: 13px;"></td>
                                <td>
                                    <select name="nivel">
                                        <option value="aluno" <?= ($u['nivel'] ?? '') === 'aluno' ? 'selected' : '' ?>>Aluno</option>
                                        <option value="representante" <?= ($u['nivel'] ?? '') === 'representante' ? 'selected' : '' ?>>Representante</option>
                                        <option value="professor" <?= ($u['nivel'] ?? '') === 'professor' ? 'selected' : '' ?>>Professor</option>
                                    </select>
                                </td>
                                <td>
                                    <select name="turma">
                                        <option value="" <?= (empty($u['turma']) || strpos($u['turma'], 'Docente') !== false) ? 'selected' : '' ?>>Nenhuma/Docente</option>
                                        <?php foreach ($turmas_sistema as $codigo => $apelido): ?>
                                            <option value="<?= htmlspecialchars($codigo) ?>" <?= (isset($u['turma']) && (string)$u['turma'] === (string)$codigo) ? 'selected' : '' ?>><?= htmlspecialchars($codigo) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <div style="max-height: 80px; width: 180px; overflow-y: auto; border: 1px solid #444; padding: 5px; background:#1a1a1a;">
                                        <?php $user_turmas = isset($u['turmas']) ? $u['turmas'] : [];
                                        foreach ($turmas_sistema as $codigo => $apelido): ?>
                                            <label style="display:block; font-size:12px; cursor:pointer;">
                                                <input type="checkbox" name="turmas_prof[]" value="<?= htmlspecialchars($codigo) ?>" <?= in_array($codigo, $user_turmas) ? 'checked' : '' ?>> <?= htmlspecialchars($codigo) ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td style="text-align: center;">
                                    <button type="submit" name="acao_user" value="salvar" class="btn-salvar">Salvar</button>
                                    <button type="button" class="btn-resetar" onclick="document.getElementById('senha_<?= $u['id'] ?>').style.display='inline-block'; this.style.display='none';">🔑</button>
                                    <input type="text" name="nova_senha" id="senha_<?= $u['id'] ?>" placeholder="Nova senha" style="display: none; width: 80px;">
                                    <button type="submit" name="acao_user" value="deletar" class="btn-deletar" onclick="return confirm('Deletar usuário?');">🗑️</button>
                                </td>
                            </tr>
                        </form>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

  <div id="tab-log" class="tab-content">
    <table>
        <thead>
            <tr>
                <th>Data/Hora</th>
                <th>Ação</th>
                <th>Detalhes</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $arquivo_logs = 'logs.json';
            if (file_exists($arquivo_logs)) {
                $logs = json_decode(file_get_contents($arquivo_logs), true) ?: [];
                // Inverte para mostrar os mais novos primeiro
                $logs = array_reverse($logs); 
                
                foreach ($logs as $log): ?>
                <tr>
                    <td><?= htmlspecialchars($log['data'] ?? 'N/A') ?></td>
                    <td><strong><?= htmlspecialchars($log['acao'] ?? 'N/A') ?></strong></td>
                    <td><?= htmlspecialchars($log['detalhes'] ?? 'N/A') ?></td>
                </tr>
                <?php endforeach; 
            } else { ?>
                <tr><td colspan="3" style="text-align: center;">Nenhum log encontrado.</td></tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script>
    window.switchTab = function(tabName, element) {
        $('.tab-content').removeClass('active');
        $('.tab-btn').removeClass('active');
        $('#tab-' + tabName).addClass('active');
        $(element).addClass('active');
    };

    $('input[name="tipo_alvo"]').change(function() {
        var val = $(this).val();
        $('#box-turma-unica').hide();
        $('#box-turmas-multiplas').hide();
        
        if(val === 'uma') {
            $('#box-turma-unica').show();
        } else if(val === 'multiplas') {
            $('#box-turmas-multiplas').show();
        }
    });
</script>
</body>
</html>