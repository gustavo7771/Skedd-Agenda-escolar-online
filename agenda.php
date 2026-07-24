<?php
session_start();

// Se não estiver logado, redireciona para o login
if (!isset($_SESSION['id']) && !isset($_SESSION['usuario_id']) && !isset($_SESSION['admin_autenticado'])) {
    header("Location: index.php");
    exit();
}

$id_usuario = $_SESSION['usuario_id'] ?? $_SESSION['id'] ?? 'admin';
$arquivo_usuarios = 'usuarios.json';
$arquivo_turmas = 'turmas.json';
$turmas_professor = [];
$nivel_usuario = $_SESSION['usuario_nivel'] ?? 'aluno';
$turma_usuario = $_SESSION['turma'] ?? '';
$nome_usuario = $_SESSION['nome'] ?? 'Usuário';
$matricula_usuario = $_SESSION['matricula'] ?? 'Sem Matrícula';

// Carrega os apelidos das turmas
$apelidos_turmas = [];
if (file_exists($arquivo_turmas)) {
    $apelidos_turmas = json_decode(file_get_contents($arquivo_turmas), true);
}

// Atualiza os dados da sessão em tempo real buscando no banco JSON
if (file_exists($arquivo_usuarios) && $id_usuario !== 'admin') {
    $usuarios_sistema = json_decode(file_get_contents($arquivo_usuarios), true);
    foreach ($usuarios_sistema as $u) {
        if ($u['id'] === $id_usuario) {
            $nivel_usuario = $u['nivel'] ?? 'aluno';
            $turma_usuario = $u['turma'] ?? '';
            $nome_usuario = $u['nome'] ?? $nome_usuario;
            $matricula_usuario = $u['matricula'] ?? $matricula_usuario;
            
            if ($nivel_usuario === 'professor' || $nivel_usuario === 'admin') {
                $turmas_professor = $u['turmas'] ?? [];
                $_SESSION['turmas_professor'] = $turmas_professor;
            }
            $_SESSION['usuario_nivel'] = $nivel_usuario;
            $_SESSION['turma'] = $turma_usuario;
            break;
        }
    }
}

if ($nivel_usuario === 'admin') {
    $turmas_professor = array_keys($apelidos_turmas);
}

// Define o nome de exibição da turma
$nome_turma_exibicao = $apelidos_turmas[$turma_usuario] ?? $turma_usuario;
if ($nivel_usuario === 'admin') { $nome_turma_exibicao = "Visualização Global (Administração)"; }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skedd: Agenda escolar</title>
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/ymJC5sNN/Captura-de-tela-2026-05-19-100134-1.webp" id="icone-redondo">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .header-left { display: flex; align-items: center; gap: 15px; }
        .header-right { display: flex; align-items: center; gap: 15px; }
        #turmaProva { max-width: 100%; }
    </style>
	<script>
        window.usuarioLogadoId = "<?= htmlspecialchars($id_usuario) ?>";
        window.usuarioNivel = "<?= htmlspecialchars($nivel_usuario) ?>";
    </script>
</head>
    
<body>

    <header>
        <div class="header-left">
            <img src="https://i.ibb.co/SX9K8y69/Captura-de-tela-2026-05-19-100134-removebg-preview.webp" alt="Logo Skedd" width="75">
            <h1 class="Titulo_header">Skedd</h1>
        </div>

        <div class="header-right" style="display: flex; align-items: center; gap: 15px;">
            <div class="user-info-header" style="text-align: right; display: flex; flex-direction: column; justify-content: center; line-height: 1.2;">
                <span style="font-weight: bold; font-size: 15px; color: var(--text, #333);"><?= htmlspecialchars($nome_usuario) ?></span>
                <span style="font-size: 12px; color: #888;"><?= $nivel_usuario === 'admin' ? 'Painel Administrativo' : 'Matrícula: ' . htmlspecialchars($matricula_usuario) ?></span>
            </div>

            <div style="position: relative; display: inline-block;">
                <button class="config-btn" id="btnNotif" style="margin: 0; position: relative;" title="Notificações">🔔<span id="notif-badge" class="notif-badge" style="display: none;">0</span></button>
                <div class="notif-menu" id="notifMenu">
                    <h3>Notificações</h3>
                    <ul id="listaNotificacoes" class="notif-list">
                        <li style="color: var(--secondary); font-style: italic; padding: 10px; text-align: center;">Nenhuma notificação no momento.</li>
                    </ul>
                </div>
            </div>

            <div style="position: relative; display: inline-block;">
                <button class="config-btn" id="btnConfig" style="margin: 0;" title="Configurações">⚙</button>
                <div class="config-menu" id="configMenu">
                    <h3>Configurações</h3>
                    <button class="tema-btn" id="toggleTheme">Alternar Tema</button>
                    <?php if($nivel_usuario === 'admin'): ?>
                        <br><br><a href="admin.php" style="color: #168fff; text-decoration: none; font-weight: bold; display: block; text-align: center;">Voltar ao Dashboard</a>
                    <?php endif; ?>
                    <br><br>
                    <a href="logout.php" style="color: red; text-decoration: none; font-weight: bold; display: block; text-align: center;">Sair da Conta</a>
                </div>
            </div>
        </div>
    </header>

    <div class="main-content">

        <?php if ($nivel_usuario === 'professor' || $nivel_usuario === 'admin'): ?>
        <div class="sidebar-left">
            <h2>Gerenciar Avaliações</h2>

            <?php if ($nivel_usuario === 'admin'): ?>
                <div style="background: rgba(22, 143, 255, 0.1); border: 1px solid #168fff; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <strong style="color: #168fff; display: block; margin-bottom: 10px;">Modo Administrador</strong>
                    <p style="font-size: 13px; margin: 0 0 10px 0; line-height: 1.4;">Para criar eventos para múltiplas turmas de forma avançada, utilize o painel de administração.</p>
                    <a href="admin.php" style="display: block; text-align: center; background: #168fff; color: white; padding: 10px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 14px;">Ir para o Dashboard Avançado</a>
                </div>
            <?php endif; ?>

            <label id="selecao">Selecione a Turma</label>
            <select id="turmaProva" style="width: 100%; padding: 10px; border-radius: 8px; font-size:14px; margin-bottom: 15px; background: var(--bg); color: var(--text); border: 1px solid var(--border);">
                <option value="todas">Todas as suas turmas</option>
                <?php if (!empty($turmas_professor)): ?>
                    <?php foreach ($turmas_professor as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($apelidos_turmas[$t] ?? 'Turma ' . $t) ?></option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option value="" disabled>Nenhuma turma vinculada</option>
                <?php endif; ?>
            </select>

            <hr style="border: 1px solid var(--border); margin: 15px 0;">
            <label>Nome do Evento</label>
            <input type="text" id="nomeProva" placeholder="Nome da Prova ou Evento">
            
            <?php if ($nivel_usuario === 'admin'): ?>
            <label>Publicar Como (Exibição)</label>
            <input type="text" id="nomeAdmin" placeholder="Ex: Direção, Coordenação..." value="<?= htmlspecialchars($_SESSION['admin_cargo'] ?? 'Administração') ?> - <?= htmlspecialchars($_SESSION['admin_nome'] ?? '') ?>">
            <?php endif; ?>

            <label>Descrição</label>
            <textarea id="descricaoProva" placeholder="Observações..." rows="3"></textarea>

            <label>Data</label>
            <input type="date" id="dataProva">

            <label>Cor da marcação</label>
            <input type="color" id="corProva" value="#ff0000">

            <button id="btnMarcar">Salvar Evento</button>

            <hr style="border: 1px solid var(--border); margin: 20px 0;">
            <h3 style="font-size: 16px; margin-bottom: 10px; color: var(--header);">Todas as Suas Provas</h3>
            <div id="listaGerenciarProvas" style="max-height: 250px; overflow-y: auto; padding-right: 5px;">
                <p style="font-style: italic; color: #888; font-size: 13px;">Carregando avaliações...</p>
            </div>
        </div>
        <?php else: ?>
        <div class="sidebar-left aluno-view">
            <h2>Área do Aluno</h2>
            <p>Visualizando a agenda da turma: <strong><?= htmlspecialchars($nome_turma_exibicao) ?></strong></p>
            <div id="painelDescricaoLateral" style="margin-top: 20px; padding: 15px; border: 1px solid var(--border); border-radius: 12px; display: none; background: var(--card);">
                <h3 style="margin-top: 0; font-size: 16px; color: var(--header);" id="tituloLateral">Título</h3>
                <p id="descricaoLateral" style="font-size: 14px; color: var(--text);"></p>
            </div>
            <div id="painelDescricaoAluno" style="margin-top: 25px; padding: 15px; border: 1px solid var(--border); border-radius: 20px; display: none; background: var(--card); text-align: left; width: 100%; box-sizing: border-box;">
                <h3 style="margin-top: 0; color: var(--header); font-size: 1.1rem; border-bottom: 1px solid var(--border); padding-bottom: 8px;" id="tituloDescricaoAluno">Nome da Prova</h3>
                <p id="textoDescricaoAluno" style="font-size: 14px; color: var(--text); line-height: 1.5; margin-top: 10px; white-space: pre-wrap;">A descrição aparecerá aqui.</p>
            </div>
        </div>
        <?php endif; ?>

        <div class="container-calendar">
            <div id="calendar"></div>
            
            <?php if ($nivel_usuario === 'professor' || $nivel_usuario === 'admin'): ?>
            <div class="container-descricao" id="painelDescricao" style="margin-top: 20px; padding: 15px; border: 1px solid var(--border); border-radius: 20px; display: none; background: var(--card); width: 100%; box-sizing: border-box;">
                <h3 style="margin-top: 0; color: var(--header);" id="tituloDescricaoProva">Nome da Prova</h3>
                <p id="textoDescricaoProva" style="margin-bottom: 15px;">A descrição aparecerá aqui.</p>
                <button id="btnDesmarcar" style="padding: 10px 20px; background-color: #ff3b30; color: white; border: none; border-radius: 30px; cursor: pointer; font-weight: bold; transition: 0.3s;">Desmarcar Prova</button>
                <button id="btnEditar" style="padding: 10px 20px; background-color: #168fff; color: white; border: none; border-radius: 30px; cursor: pointer; font-weight: bold; transition: 0.3s; margin-left: 10px;">Editar Prova</button>
            </div>
            <?php endif; ?>
        </div>

        <div class="sidebar-right">
            <h2>Avaliações do Dia</h2>
            <p id="dataSelecionada">Selecione um dia</p>
            <ul id="listaProvas">
                <li class="vazio">Nenhuma avaliação neste dia.</li>
            </ul>
        </div>
    </div>

    <footer>
        <div class="container-rodape">
            <p>&copy; 2026 skedd: Agenda digital escolar. Todos os direitos reservados.</p>
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/locale/pt-br.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/locale/pt-br.js"></script>
    <script src="script.js"></script>
</body>
</html>