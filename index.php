<?php
session_start();
$arquivo_usuarios = 'usuarios.json';
$arquivo_turmas = 'turmas.json';

// --- INÍCIO: LÓGICA DE LEMBRAR DE MIM ---
if (!isset($_SESSION['usuario_id']) && isset($_COOKIE['skedd_lembrar'])) {
    if (file_exists($arquivo_usuarios)) {
        $usuarios = json_decode(file_get_contents($arquivo_usuarios), true) ?: [];
        $token_cookie = $_COOKIE['skedd_lembrar'];
        
        foreach ($usuarios as $u) {
            if (isset($u['lembrar_token']) && hash_equals($u['lembrar_token'], hash('sha256', $token_cookie))) {
                $_SESSION['id'] = $u['id']; 
                $_SESSION['usuario_id'] = $u['id'];
                $_SESSION['email'] = $u['email'];
                $_SESSION['turma'] = $u['turma'];
                $_SESSION['nivel'] = $u['nivel'];
                $_SESSION['usuario_nivel'] = $u['nivel'];
                $_SESSION['turmas_professor'] = $u['turmas'] ?? [];
                
                if (isset($u['nome'])) $_SESSION['nome'] = $u['nome'];
                if (isset($u['matricula'])) $_SESSION['matricula'] = $u['matricula'];
                
                header('Location: agenda.php');
                exit;
            }
        }
    }
}
// --- FIM: LÓGICA DE LEMBRAR DE MIM ---

// Se já estiver logado, redireciona para a agenda
if (isset($_SESSION['usuario_id']) || isset($_SESSION['email'])) {
    header('Location: agenda.php');
    exit;
}

if (!file_exists($arquivo_usuarios)) { file_put_contents($arquivo_usuarios, json_encode([])); }
if (!file_exists($arquivo_turmas)) {
    $turmas_iniciais = [
        "1001" => "1001 - Sem Apelido",
        "2001" => "2001 - Sem Apelido",
        "3001" => "3001 - Sem Apelido"
    ];
    file_put_contents($arquivo_turmas, json_encode($turmas_iniciais, JSON_PRETTY_PRINT));
}

$turmas_sistema = json_decode(file_get_contents($arquivo_turmas), true);
$erro = ''; 
$sucesso = ''; 
$CHAVE_MESTRE = 'SKEDE123';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuarios = json_decode(file_get_contents($arquivo_usuarios), true);

    // LÓGICA DE CADASTRO
    if (isset($_POST['acao']) && $_POST['acao'] === 'cadastrar') {
        $nivel = filter_input(INPUT_POST, 'nivel', FILTER_SANITIZE_SPECIAL_CHARS);
        $turma = ($nivel === 'professor') ? "Docente" : trim(filter_input(INPUT_POST, 'turma', FILTER_SANITIZE_SPECIAL_CHARS));
        
        // Mantém e-mail e senha
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $senha = $_POST['senha'];
        
        // Adiciona Matrícula e Nome
        $matricula = trim(filter_input(INPUT_POST, 'matricula', FILTER_SANITIZE_SPECIAL_CHARS));
        $nome = trim(filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_SPECIAL_CHARS));
        $chave = $_POST['chave'] ?? '';

        if (($nivel === 'professor' || !empty($turma)) && $email && $senha && !empty($matricula) && !empty($nome) && $nivel) {
            if ($nivel !== 'professor' && !array_key_exists($turma, $turmas_sistema)) {
                $erro = "O código de turma digitado não existe!";
            } else {
                $existe = false;
                foreach ($usuarios as $u) { 
                    if ($u['email'] === $email || (isset($u['matricula']) && $u['matricula'] === $matricula)) { 
                        $existe = true; 
                        break; 
                    } 
                }

                if ($existe) {
                    $erro = "Este e-mail ou matrícula já está cadastrado!";
                } else {
                    if (($nivel === 'professor' || $nivel === 'representante') && $chave !== $CHAVE_MESTRE) {
                        $erro = "Chave de validação institucional incorreta!";
                    } else {
                        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                        $novo_usuario = [
                            'id' => uniqid(),
                            'turma' => $turma,
                            'email' => $email,
                            'senha' => $senha_hash,
                            'matricula' => $matricula,
                            'nome' => $nome,
                            'nivel' => $nivel
                        ];
                        if ($nivel === 'professor') {
                            $novo_usuario['turmas'] = []; // Inicializa vazio até o administrador vincular
                        }
                        $usuarios[] = $novo_usuario;
                        file_put_contents($arquivo_usuarios, json_encode($usuarios, JSON_PRETTY_PRINT));
                        $sucesso = "Cadastro efetuado! Faça login. (Professores: solicitem liberação de turmas ao admin)";
                    }
                }
            }
        } else { 
            $erro = "Por favor, preencha todos os campos corretamente!"; 
        }
    }

    // LÓGICA DE LOGIN (Alterada para suportar o Lembrar de Mim)
    if (isset($_POST['acao']) && $_POST['acao'] === 'login') {
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $senha = $_POST['senha'];
        $lembrar = isset($_POST['lembrar']); // Captura se a caixa foi marcada

        if ($email && $senha) {
            $user_index = -1;
            foreach ($usuarios as $index => $u) { 
                if ($u['email'] === $email && password_verify($senha, $u['senha'])) { 
                    $user_index = $index; 
                    break; 
                } 
            }

            if ($user_index !== -1) {
                $user = $usuarios[$user_index];
                
                $_SESSION['id'] = $user['id']; 
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['turma'] = $user['turma'];
                $_SESSION['nivel'] = $user['nivel'];
                $_SESSION['usuario_nivel'] = $user['nivel'];
                $_SESSION['turmas_professor'] = $user['turmas'] ?? [];
                
                // Carrega nome e matrícula para a sessão se existirem
                if (isset($user['nome'])) $_SESSION['nome'] = $user['nome'];
                if (isset($user['matricula'])) $_SESSION['matricula'] = $user['matricula'];

                // --- INÍCIO: SALVAR COOKIE SE MARCOU A CAIXA ---
                if ($lembrar) {
                    $token_id = bin2hex(random_bytes(16));
                    $token_hash = hash('sha256', $token_id);
                    
                    $usuarios[$user_index]['lembrar_token'] = $token_hash;
                    file_put_contents($arquivo_usuarios, json_encode($usuarios, JSON_PRETTY_PRINT));
                    
                    setcookie('skedd_lembrar', $token_id, time() + (30 * 24 * 60 * 60), "/", "", false, true); // 30 dias
                }
                // --- FIM ---

                header('Location: agenda.php');
                exit;
            } else { 
                $erro = "E-mail ou senha incorretos!"; 
            }
        } else { 
            $erro = "Preencha todos os campos!"; 
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skedd - Entrar ou Cadastrar</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/ymJC5sNN/Captura-de-tela-2026-05-19-100134-1.webp" id="icone-redondo">

<style>
        * { box-sizing: border-box; }

        .container-login {
            background: white;
            color: black;
            padding: 28px 24px;
            border-radius: 20px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 10px 40px -10px rgba(0,0,0,0.1);
        }

        .container-login h2 {
            margin: 8px 0 0;
            font-size: 22px;
        }

        .form-group {
            margin-bottom: 14px;
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 6px;
            font-weight: bold;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            padding: 12px 14px;
            border: 1px solid #e0e5ea;
            border-radius: 12px;
            font-size: 16px; 
            outline: none;
            transition: box-shadow 0.2s, border-color 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #168fff;
            box-shadow: 0 0 0 3px rgba(22, 143, 255, 0.2);
        }

        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #168fff, #0077ff);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            cursor: pointer;
            font-weight: bold;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 4px;
            box-shadow: 0 10px 40px -10px rgba(0,0,0,0.08);
        }

        button:hover,
        button:active {
            background: linear-gradient(135deg, #1276d1, #0056b3);
            transform: translateY(-2px);
            box-shadow: 0 14px 45px -10px rgba(0,0,0,0.12);
        }

        .alternar {
            text-align: center;
            color: #168fff;
            cursor: pointer;
            margin-top: 16px;
            text-decoration: underline;
            font-size: 14px;
        }

        .msg {
            padding: 12px;
            margin-bottom: 14px;
            border-radius: 10px;
            text-align: center;
            font-weight: bold;
            font-size: 14px;
        }

        .msg-erro  { background: #f8d7da; color: #721c24; }
        .msg-sucesso { background: #d4edda; color: #155724; }

        @media (max-width: 360px) {
            .container-login { padding: 20px 16px; }
            .container-login h2 { font-size: 18px; }
        }
    </style>
</head>
<body class="login-page">

<div class="container-login">
    <div style="text-align: center; margin-bottom: 20px;">
        <img src="https://i.ibb.co/ymJC5sNN/Captura-de-tela-2026-05-19-100134-1.webp" alt="Logo" width="70">
        <h2>Skedd</h2>
    </div>

    <?php if(!empty($erro)): ?> <div class="msg msg-erro"><?= $erro ?></div> <?php endif; ?>
    <?php if(!empty($sucesso)): ?> <div class="msg msg-sucesso"><?= $sucesso ?></div> <?php endif; ?>

    <div id="box-login">
        <form method="POST" action="index.php">
            <input type="hidden" name="acao" value="login">
            <div class="form-group">
                <label>E-mail</label>
                <input type="email" name="email" placeholder="Digite seu e-mail" required>
            </div>
            <div class="form-group">
                <label>Senha</label>
                <input type="password" name="senha" placeholder="Digite sua senha" required>
            </div>
            
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 14px;">
                <input type="checkbox" name="lembrar" id="lembrar" style="width: auto; margin: 0; cursor: pointer;">
                <label for="lembrar" style="margin: 0; font-size: 14px; font-weight: normal; cursor: pointer; user-select: none;">Manter-me conectado</label>
            </div>
            
            <button type="submit">Entrar</button>
        </form>
        <p class="alternar" onclick="mudarForm(true)">Não tem conta? Cadastre-se</p>
    </div>

    <div id="box-cadastro" style="display: none;">
        <form method="POST" action="index.php">
            <input type="hidden" name="acao" value="cadastrar">
            
            <div class="form-group">
                <label>Tipo de Conta</label>
                <select name="nivel" id="selectNivel">
                    <option value="aluno" selected>Aluno(a)</option>
                    <option value="representante">Representante de Turma</option>
                    <option value="professor">Professor(a)</option>
                </select>
            </div>

            <div class="form-group" id="box-turma">
                <label>Código da Turma</label>
                <input type="text" name="turma" id="inputTurma" placeholder="Digite o código da sua turma (Ex: 1001)" required>
            </div>

            <div class="form-group">
                <label>Número de Matrícula</label>
                <input type="text" name="matricula" placeholder="Ex: 2024001" required>
            </div>
            <div class="form-group">
                <label>Nome Completo</label>
                <input type="text" name="nome" placeholder="Digite o seu nome completo" required>
            </div>

            <div class="form-group">
                <label>E-mail</label>
                <input type="email" name="email" placeholder="Digite seu e-mail" required>
            </div>
            <div class="form-group">
                <label>Senha</label>
                <input type="password" name="senha" placeholder="Crie uma senha" required>
            </div>

            <div class="form-group" id="box-chave" style="display: none;">
                <label>Chave de Validação da Escola</label>
                <input type="text" name="chave" id="inputChave" placeholder="Código institucional">
            </div>

            <button type="submit">Cadastrar</button>
        </form>
        <p class="alternar" onclick="mudarForm(false)">Já tem conta? Faça Login</p>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script>
    function mudarForm(mostrarCadastro) {
        if (mostrarCadastro) {
            $('#box-login').hide();
            $('#box-cadastro').show();
        } else {
            $('#box-cadastro').hide();
            $('#box-login').show();
        }
    }

    $('#selectNivel').change(function() {
        var nivel = $(this).val();
        if (nivel === 'professor') {
            $('#box-turma').hide();
            $('#inputTurma').removeAttr('required');
            $('#box-chave').show();
            $('#inputChave').attr('required', true);
        } else if (nivel === 'representante') {
            $('#box-turma').show();
            $('#inputTurma').attr('required', true);
            $('#box-chave').show();
            $('#inputChave').attr('required', true);
        } else {
            $('#box-turma').show();
            $('#inputTurma').attr('required', true);
            $('#box-chave').hide();
            $('#inputChave').removeAttr('required');
        }
    });
</script>
</body>
</html>