$(document).ready(function () {
    window.onerror = function(msg, url, line) { alert("Erro JS: " + msg + "\nLinha: " + line); return false; };

    console.log("Skedd: DOM carregado e pronto para iniciar o FullCalendar.");

    // --- 1. LÓGICA DO MENU DE CONFIGURAÇÕES E NOTIFICAÇÕES ---
    $('#btnConfig').click(function (e) {
        e.stopPropagation();
        $('#notifMenu').hide();
        $('#configMenu').toggle();
    });

    $('#btnNotif').click(function (e) {
        e.stopPropagation();
        $('#configMenu').hide();
        $('#notifMenu').toggle();
    });

    $(document).click(function () {
        $('#configMenu').hide();
        $('#notifMenu').hide();
    });

    $('#configMenu, #notifMenu').click(function (e) {
        e.stopPropagation();
    });

    // --- 2. LÓGICA DO TEMA (DARK MODE) ---
    $('#toggleTheme').click(function() {
        $('body').toggleClass('dark-mode');
        if ($('body').hasClass('dark-mode')) {
            localStorage.setItem('tema', 'dark');
        } else {
            localStorage.setItem('tema', 'light');
        }
    });

    if (localStorage.getItem('tema') === 'dark') {
        $('body').addClass('dark-mode');
    }

    // --- 3. LÓGICA DO CALENDÁRIO E DEMAIS FUNÇÕES ---
    var dataSelecionadaGlobal = moment().format('YYYY-MM-DD');

    $('#calendar').fullCalendar({
        header: { left: 'prev,next', center: 'title', right: '' },
        columnHeader: false,
        editable: false,
        
        events: function(start, end, timezone, callback) {
            var isProfessor = $('#turmaProva').length > 0;
            var turmaDefinida = $('#turmaProva').val() || '';
            
            if (isProfessor && turmaDefinida === '') {
                callback([]);
                if ($('#listaGerenciarProvas').length) {
                    renderizarListaGerenciamentoProfessor([]);
                }
                return;
            }

            $.ajax({
                url: 'api.php',
                type: 'GET',
                data: { action: 'listar', turma: turmaDefinida },
                dataType: 'json',
                success: function(provas) { 
                    callback(provas); 
                    verificarProvasNotificacoes(provas);
                    
                    if ($('#listaGerenciarProvas').length) {
                        renderizarListaGerenciamentoProfessor(provas);
                    }
                },
                error: function(err) {
                    console.error("Erro ao buscar dados da api.php:", err);
                }
            });
        },
        
        locale: 'pt-br', 

        eventRender: function(event, element) {
            return false; // Desativa a renderização padrão do evento
        },

        eventAfterAllRender: function(view) {
            var todosEventos = $('#calendar').fullCalendar('clientEvents');
            
            // Remove os containers de dados antigos
            $('.dice-container').remove();

            // Agrupa os eventos por data
            var eventosPorDia = {};
            todosEventos.forEach(function(e) {
                var dataStr = e.start.format('YYYY-MM-DD');
                if(!eventosPorDia[dataStr]) eventosPorDia[dataStr] = [];
                eventosPorDia[dataStr].push(e);
            });

            // Para cada dia, adiciona o container de dado
            for(var dataStr in eventosPorDia) {
                var cell = $('.fc-day-top[data-date="' + dataStr + '"]');
                if(cell.length) {
                    var numEventos = Math.min(eventosPorDia[dataStr].length, 9);
                    var container = $('<div class="dice-container dice-' + numEventos + '"></div>');
                    eventosPorDia[dataStr].forEach(function(e, index) {
                        if (index < 9) { // Limite de 9 pontinhos
                            var dot = $('<span class="dice-dot"></span>');
                            dot.css('background-color', e.color || 'var(--header)');
                            container.append(dot);
                        }
                    });
                    cell.append(container);
                }
            }

            if (dataSelecionadaGlobal) {
                atualizarPainelDireito(dataSelecionadaGlobal);
            }
        },

        dayClick: function (date, jsEvent, view) {
            if (($('#turmaProva').length) && ($('#turmaProva').val() === '')) {
                return;
            }

            var dataClicada = date.format('YYYY-MM-DD');
            dataSelecionadaGlobal = dataClicada;
            $('#dataProva').val(dataClicada); // Sincroniza com o formulário
            var eventosDoDia = $('#calendar').fullCalendar('clientEvents', function(event) {
                return event.start.format('YYYY-MM-DD') === dataClicada;
            });

            if (eventosDoDia.length > 0) {
                exibirDescricaoNaLateral(eventosDoDia[0]);
            } else {
                $('#painelDescricaoAluno').fadeOut();
                $('#painelDescricao').fadeOut();
            }
            atualizarPainelDireito(dataClicada);
        },

        eventClick: function(event) {
            exibirDescricaoNaLateral(event);
        }
    });

    function exibirDescricaoNaLateral(event) {
        var corSelecionada = event.color ? event.color : 'var(--header)';

        if ($('#painelDescricao').length) {
            var tituloAdmin = event.is_admin ? '⭐ ' + event.title : event.title;
            $('#tituloDescricaoProva').text(tituloAdmin).css('color', corSelecionada);
            var autoriaAdmin = event.is_admin ? '<strong>Publicado por: ' + (event.nome_admin || 'Administração') + '</strong><br>' : '';
            var descricao = event.description ? event.description : "Nenhuma descrição fornecida.";
            $('#textoDescricaoProva').html(autoriaAdmin + descricao);
            $('#painelDescricao').data('id', event.id).fadeIn();

            // CORREÇÃO: Compara ambos como texto (String)
            if (!event.criador_id || String(event.criador_id) === String(window.usuarioLogadoId)) {
                $('#btnDesmarcar').show();
                $('#btnEditar').show();
                
                // Salvar os dados completos da prova no painel para facilitar a edição
                $('#painelDescricao').data('provaCompleta', event);
            } else {
                $('#btnDesmarcar').hide();
                $('#btnEditar').hide();
            }
        }
        if ($('#painelDescricaoAluno').length) {
            var tituloAdmin = event.is_admin ? '⭐ ' + event.title : event.title;
            $('#tituloDescricaoAluno').text(tituloAdmin).css('color', corSelecionada);
            var autoriaAdmin = event.is_admin ? '<strong>Publicado por: ' + (event.nome_admin || 'Administração') + '</strong><br>' : '';
            var descricao = event.description ? event.description : "Nenhuma descrição fornecida.";
            $('#textoDescricaoAluno').html(autoriaAdmin + descricao);
            $('#painelDescricaoAluno').fadeIn();
        }
    }

    $(document).on('click', '#btnDesmarcar', function() {
        var idProva = $('#painelDescricao').data('id');
        if (idProva && confirm("Deseja realmente desmarcar esta prova?")) {
            $.ajax({
                url: 'api.php?action=deletar',
                type: 'POST',
                data: { id: idProva },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#calendar').fullCalendar('refetchEvents');
                        $('#painelDescricao').fadeOut();
                        alert("Prova desmarcada com sucesso!");
                    } else {
                        alert("Erro: " + response.message);
                    }
                }
            });
        }
    });

    $(document).on('click', '#btnEditar', function() {
        var prova = $('#painelDescricao').data('provaCompleta');
        if (prova) {
            var nomeLimpo = prova.title.replace(/\s*\([^)]*\)/g, '');
            $('#nomeProva').val(nomeLimpo);
            $('#descricaoProva').val(prova.description || '');
            $('#dataProva').val(prova.start.format('YYYY-MM-DD'));
            $('#corProva').val(prova.color || '#ff0000');
            $('#turmaProva').val(prova.turma || '');
            
            $('#btnMarcar').text('Salvar Alterações')
                           .data('modo', 'editar')
                           .data('idProva', prova.id);
                           
            // Pisca a área de formulário para chamar atenção
            $('.sidebar-left').fadeTo(100, 0.5).fadeTo(100, 1);
        }
    });

    $('#turmaProva').on('change', function() {
        $('#painelDescricao').fadeOut();
        $('#painelDescricaoAluno').fadeOut();
        if ($(this).val() === '') {
            dataSelecionadaGlobal = '';
            $('#listaProvas').empty().append('<li class="vazio">Selecione uma turma para ver as avaliações.</li>');
        }
        $('#calendar').fullCalendar('refetchEvents');
    });

    $('#btnMarcar').click(function () {
        var nome = $('#nomeProva').val();
        var descricao = $('#descricaoProva').val(); 
        var data = $('#dataProva').val();
        var col = $('#corProva').val();
        var turma = $('#turmaProva').val() || '';
        
        var modo = $(this).data('modo') || 'salvar';
        var idEditado = $(this).data('idProva');

        if (turma === '') {
            alert("Por favor, selecione uma turma específica antes de marcar a prova.");
            return;
        }

        if (nome !== '' && data !== '') {
            var urlAction = modo === 'editar' ? 'api.php?action=editar' : 'api.php?action=salvar';
            var nomeAdmin = $('#nomeAdmin').length ? $('#nomeAdmin').val() : '';
            var ajaxData = { nome: nome, descricao: descricao, data: data, col: col, turma: turma, nome_admin: nomeAdmin };
            if (modo === 'editar') {
                ajaxData.id = idEditado;
            }

            $.ajax({
                url: urlAction,
                type: 'POST',
                data: ajaxData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#calendar').fullCalendar('refetchEvents');
                        alert(modo === 'editar' ? "Prova atualizada com sucesso!" : "Prova marcada com sucesso!");

                        $('#nomeProva').val('');
                        $('#descricaoProva').val('');
                        $('#dataProva').val('');
                        $('#corProva').val('#ff0000'); 
                        $('#turmaProva').val('');     
                        
                        $('#btnMarcar').text('Marcar Prova para a Turma Selecionada').data('modo', 'salvar').removeData('idProva');
                        $('#painelDescricao').fadeOut();
                        $('#listaProvas').empty().append('<li class="vazio">Selecione uma turma para ver as avaliações.</li>');
                    } else {
                        alert("Erro: " + response.message);
                    }
                }
            });
        } else {
            alert("Preencha o nome e a data da prova.");
        }
    });

    function atualizarPainelDireito(dataStr) {
        var dataFormatada = moment(dataStr).format('DD/MM/YYYY');
        $('#dataSelecionada').text(dataFormatada);
        var todosEventos = $('#calendar').fullCalendar('clientEvents');
        var listaHtml = $('#listaProvas');
        listaHtml.empty();
        var encontrouProva = false;

        todosEventos.forEach(function (evento) {
            if (evento.start.format('YYYY-MM-DD') === dataStr) {
                var tituloAdmin = evento.is_admin ? '⭐ ' + evento.title : evento.title;
                var conteudoHTML = '<strong>' + tituloAdmin + '</strong>';
                if(evento.description) {
                    var autoriaAdmin = evento.is_admin ? '<span style="color:#168fff; font-size: 11px;">[' + (evento.nome_admin || 'Administração') + ']</span> ' : '';
                    conteudoHTML += '<p class="desc-preview">' + autoriaAdmin + evento.description + '</p>';
                }
                var li = $('<li class="quadrado-prova"></li>').html(conteudoHTML);
                li.css('border-top', '8px solid ' + (evento.color || 'var(--header)'));
                
                li.on('click', function() {
                    exibirDescricaoNaLateral(evento);
                });
                
                listaHtml.append(li);
                encontrouProva = true;
            }
        });

        if (!encontrouProva) {
            if ($('#turmaProva').length && $('#turmaProva').val() === '') {
                listaHtml.append('<li class="vazio">Selecione uma turma para ver as avaliações.</li>');
            } else {
                listaHtml.append('<li class="vazio">Nenhuma avaliação nesta data.</li>');
            }
        }
    }

    function renderizarListaGerenciamentoProfessor(provas) {
        var container = $('#listaGerenciarProvas');
        container.empty();

        if ($('#turmaProva').val() === '') {
            container.append('<p style="font-style: italic; color: #888; font-size: 13px; text-align: center; margin-top: 10px;">Selecione uma turma para gerenciar.</p>');
            return;
        }

        if (!provas || provas.length === 0) {
            container.append('<p style="font-style: italic; color: #888; font-size: 13px; text-align: center; margin-top: 10px;">Nenhuma prova agendada.</p>');
            return;
        }

        provas.sort(function(a, b) {
            return moment(a.start).diff(moment(b.start));
        });

        provas.forEach(function(prova) {
            var dataFormatada = moment(prova.start).format('DD/MM/YYYY');
            
            // CORREÇÃO: Compara ambos como texto (String)
            var ehDono = (!prova.criador_id || String(prova.criador_id) === String(window.usuarioLogadoId));
            
            var botaoDeletar = ehDono 
                ? `<button class="btn-deletar-direto" data-id="${prova.id}" title="Desmarcar Prova" style="background: #ff3b30; color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; font-weight: bold; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 14px; line-height: 1;">&times;</button>`
                : `<span style="font-size: 11px; color: #888; font-style: italic; background: rgba(255,255,255,0.08); padding: 2px 6px; border-radius: 4px;">Outro Prof.</span>`;

            var tituloAdmin = prova.is_admin ? '⭐ ' + prova.title : prova.title;
            var itemHtml = `
                <div class="item-gerenciar-prova" style="background: rgba(0, 0, 0, 0.05); padding: 10px; border-radius: 8px; margin-bottom: 8px; border-left: 5px solid ${prova.color}; display: flex; justify-content: space-between; align-items: center; gap: 10px; opacity: ${ehDono ? '1' : '0.8'};">
                    <div style="flex: 1; min-width: 0;">
                        <strong style="font-size: 13px; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: ${prova.color};">${tituloAdmin}</strong>
                        <span style="font-size: 11px; color: #888;">📅 ${dataFormatada}</span>
                    </div>
                    ${botaoDeletar}
                </div>
            `;
            container.append(itemHtml);
        });
    }

    $(document).on('click', '.btn-deletar-direto', function(e) {
        e.preventDefault();
        var idProva = $(this).data('id');

        if (idProva && confirm("Deseja realmente desmarcar esta prova?")) {
            $.ajax({
                url: 'api.php?action=deletar',
                type: 'POST',
                data: { id: idProva },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#calendar').fullCalendar('refetchEvents');
                        $('#painelDescricao').fadeOut();
                        alert("Prova desmarcada com sucesso!");
                    } else {
                        alert("Erro: " + response.message);
                    }
                },
                error: function() {
                    alert("Erro interno na tentativa de desmarcar a avaliação.");
                }
            });
        }
    });

    // --- 4. SISTEMA DE NOTIFICAÇÃO E POPUPS ---
    $('body').append('<div id="toast-container" class="toast-container"></div>');
    var notificados = {};

    function showToast(title, body, type) {
        var cardClass = (type === 'today') ? 'alert-today' : 'alert-tomorrow';
        var toastId = 'toast-' + Date.now() + Math.floor(Math.random() * 1000);
        var toastHtml = `
            <div class="toast-card ${cardClass}" id="${toastId}">
                <img src="https://i.ibb.co/ymJC5sNN/Captura-de-tela-2026-05-19-100134-1.webp" style="width: 30px; height: 30px; border-radius: 50%; object-fit: cover;" alt="Logo">
                <div class="toast-content">
                    <div class="toast-title">${title}</div>
                    <div class="toast-body">${body}</div>
                </div>
                <button class="toast-close" onclick="$('#${toastId}').fadeOut(300, function(){ $(this).remove(); })">&times;</button>
            </div>
        `;
        $('#toast-container').append(toastHtml);
        
        setTimeout(function() {
            var toastEl = $('#' + toastId);
            if (toastEl.length) {
                toastEl.css('animation', 'fadeOutToast 0.5s ease forwards');
                setTimeout(function() {
                    toastEl.remove();
                }, 500);
            }
        }, 7000);
    }

    function verificarProvasNotificacoes(provas) {
        if (!Array.isArray(provas)) return;
        var hojeStr = moment().format('YYYY-MM-DD');
        var amanhaStr = moment().add(1, 'days').format('YYYY-MM-DD');
        
        var provasHoje = [];
        var provasAmanha = [];
        
        provas.forEach(function(prova) {
            var dataProva = moment(prova.start).format('YYYY-MM-DD');
            if (dataProva === hojeStr) {
                provasHoje.push(prova);
            } else if (dataProva === amanhaStr) {
                provasAmanha.push(prova);
            }
        });
        
        var listHtml = $('#listaNotificacoes');
        listHtml.empty();
        
        var totalNotifs = provasHoje.length + provasAmanha.length;
        
        if (totalNotifs > 0) {
            $('#notif-badge').text(totalNotifs).show();
            
            provasHoje.forEach(function(prova) {
                var tituloLimpo = prova.title.replace(/\s*\([^)]*\)/g, '');
                listHtml.append(`
                    <li class="notif-item" style="border-left: 4px solid #ff3b30; margin-bottom: 5px; background: rgba(255, 59, 48, 0.05);">
                        <strong>Hoje:</strong> ${tituloLimpo}
                        <div style="font-size: 11px; color: var(--secondary); margin-top: 2px;">
                            ${prova.description ? prova.description : 'Sem observações.'}
                        </div>
                    </li>
                `);
                
                if (!notificados[prova.id]) {
                    showToast(tituloLimpo, '⚠️ Prova marcada para HOJE!', 'today');
                    notificados[prova.id] = true;
                }
            });
            
            provasAmanha.forEach(function(prova) {
                var tituloLimpo = prova.title.replace(/\s*\([^)]*\)/g, '');
                listHtml.append(`
                    <li class="notif-item" style="border-left: 4px solid #ffcc00; margin-bottom: 5px; background: rgba(255, 204, 0, 0.05);">
                        <strong>Amanhã:</strong> ${tituloLimpo}
                        <div style="font-size: 11px; color: var(--secondary); margin-top: 2px;">
                            ${prova.description ? prova.description : 'Sem observações.'}
                        </div>
                    </li>
                `);
                
                if (!notificados[prova.id]) {
                    showToast(tituloLimpo, '📅 Prova marcada para amanhã!', 'tomorrow');
                    notificados[prova.id] = true;
                }
            });
        } else {
            $('#notif-badge').hide();
            listHtml.append(`
                <li style="color: var(--secondary); font-style: italic; padding: 10px; text-align: center;">
                    Nenhuma avaliação hoje ou amanhã.
                </li>
            `);
        }
    }

    // --- 5. SISTEMA DE PERMISSÃO E PUSH NOTIFICATIONS ---

    function getNomeBrowser() {
        var ua = navigator.userAgent;
        if (ua.indexOf('Chrome') !== -1 && ua.indexOf('Edg') === -1) return 'Chrome';
        if (ua.indexOf('Edg') !== -1)    return 'Edge';
        if (ua.indexOf('Firefox') !== -1) return 'Firefox';
        if (ua.indexOf('Safari') !== -1)  return 'Safari';
        return 'seu navegador';
    }

    function mostrarBannerNegado() {
        if ($('#notif-denied-banner').length) return; 
        var browser = getNomeBrowser();
        var instrucao;
        if (browser === 'Chrome' || browser === 'Edge') {
            instrucao = 'Clique no 🔒 cadeado na barra de endereço → <strong>Permissões</strong> → ative <strong>Notificações</strong>.';
        } else if (browser === 'Firefox') {
            instrucao = 'Clique no 🔒 cadeado → <strong>Mais informações</strong> → Permissões → ative <strong>Notificações</strong>.';
        } else if (browser === 'Safari') {
            instrucao = 'Vá em <strong>Ajustes → Safari → Notificações</strong> e ative o Skedd.';
        } else {
            instrucao = 'Ative as notificações nas configurações do seu navegador para este site.';
        }

        var banner = $(`
            <div class="notif-denied-banner" id="notif-denied-banner">
                <span class="banner-icon">🔔</span>
                <span>
                    <strong>Notificações bloqueadas.</strong><br>
                    Para receber alertas de provas, reative manualmente:<br>${instrucao}
                </span>
                <button class="notif-denied-close" title="Fechar">&times;</button>
            </div>
        `);

        $('header').after(banner);

        banner.find('.notif-denied-close').on('click', function() {
            banner.slideUp(200, function() { banner.remove(); });
        });

        setTimeout(function() {
            banner.slideUp(400, function() { banner.remove(); });
        }, 20000);
    }

    function mostrarModalPermissao(callback) {
        if ($('#notif-overlay').length) return;

        var overlay = $(`
            <div class="notif-overlay" id="notif-overlay">
                <div class="notif-modal">
                    <span class="notif-modal-icon">🔔</span>
                    <h3>Ativar notificações?</h3>
                    <p>Receba alertas automáticos quando uma nova prova for marcada para a sua turma, mesmo com o site fechado.</p>
                    <div class="notif-modal-btns">
                        <button class="btn-notif-sim" id="btn-notif-sim">✅ Sim, quero ser avisado!</button>
                        <button class="btn-notif-nao" id="btn-notif-nao">Agora não</button>
                    </div>
                </div>
            </div>
        `);

        $('body').append(overlay);

        $('#btn-notif-sim').on('click', function() {
            overlay.fadeOut(200, function() { overlay.remove(); });
            callback(true);
        });

        $('#btn-notif-nao').on('click', function() {
            overlay.fadeOut(200, function() { overlay.remove(); });
            var expiry = Date.now() + (3 * 24 * 60 * 60 * 1000); 
            localStorage.setItem('notif_adiado', expiry);
            callback(false);
        });

        overlay.on('click', function(e) {
            if ($(e.target).is('#notif-overlay')) {
                overlay.fadeOut(200, function() { overlay.remove(); });
                callback(false);
            }
        });
    }

    function iniciarPushNotifications() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
            console.info('Skedd: Push Notifications não suportado neste navegador.');
            return;
        }

        var permissaoAtual = Notification.permission;

        if (permissaoAtual === 'denied') {
            mostrarBannerNegado();
            return;
        }

        if (permissaoAtual === 'granted') {
            registrarPush();
            return;
        }

        var adiado = localStorage.getItem('notif_adiado');
        if (adiado && Date.now() < parseInt(adiado)) {
            return;
        }

        mostrarModalPermissao(function(confirmado) {
            if (!confirmado) return;

            Notification.requestPermission().then(function(permission) {
                if (permission === 'granted') {
                    registrarPush();
                } else if (permission === 'denied') {
                    setTimeout(mostrarBannerNegado, 500);
                }
            });
        });
    }

    function registrarPush() {
        navigator.serviceWorker.register('sw.js')
        .then(function(reg) {
            console.log('Skedd: Service Worker registrado.', reg.scope);
            return navigator.serviceWorker.ready;
        })
        .then(function(reg) {
            return reg.pushManager.getSubscription().then(function(sub) {
                if (sub) {
                    salvarInscricaoNoServidor(sub);
                    return;
                }
                return $.getJSON('api.php?action=get_public_key')
                .then(function(res) {
                    if (!res || !res.public_key) {
                        console.warn('Skedd: Chave VAPID não disponível.');
                        return;
                    }
                    return reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(res.public_key)
                    });
                })
                .then(function(newSub) {
                    if (newSub) {
                        salvarInscricaoNoServidor(newSub);
                        console.log('Skedd: Inscrito em Push Notifications.');
                    }
                });
            });
        })
        .catch(function(err) {
            console.warn('Skedd: Falha ao configurar push:', err);
        });
    }

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64  = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var rawData = window.atob(base64);
        var output  = new Uint8Array(rawData.length);
        for (var i = 0; i < rawData.length; i++) output[i] = rawData.charCodeAt(i);
        return output;
    }

    function salvarInscricaoNoServidor(subscription) {
        $.ajax({
            url: 'api.php?action=salvar_inscricao',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(subscription.toJSON()),
            dataType: 'json',
            success: function() { console.log('Skedd: Inscrição push salva.'); },
            error:   function(e){ console.warn('Skedd: Erro ao salvar inscrição:', e); }
        });
    }

    setTimeout(iniciarPushNotifications, 2500);
});

// Funções para controle dos Modais (Termos e Privacidade)
window.abrirModalPolitica = function(event) {
    if(event) event.preventDefault();
    $('#modalPolitica').css('display', 'flex');
};

window.abrirModalTermos = function(event) {
    if(event) event.preventDefault();
    $('#modalTermos').css('display', 'flex');
};

window.fecharModal = function(idModal) {
    $('#' + idModal).fadeOut(300);
};

// Fechar modal ao clicar fora do conteúdo
$(window).on('click', function(event) {
    if ($(event.target).hasClass('modal-skedd')) {
        $(event.target).fadeOut(300);
    }
});