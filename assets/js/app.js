/* scripts gerais do sistema, tudo em JS puro mesmo, sem lib nenhuma */
(function () {
    'use strict';

    // TEMA CLARO / ESCURO  (preferencia salva no navegador)
    var TEMA_CHAVE = 'cc_tema';

    function aplicarTema(tema) {
        document.documentElement.setAttribute('data-tema', tema);
        var botao = document.getElementById('botaoTema');
        if (botao) botao.innerHTML = tema === 'escuro' ? '&#9788;' : '&#9789;';
    }

    aplicarTema(localStorage.getItem(TEMA_CHAVE) || 'claro');

    var botaoTema = document.getElementById('botaoTema');
    if (botaoTema) {
        botaoTema.addEventListener('click', function () {
            var atual = document.documentElement.getAttribute('data-tema');
            var novo = atual === 'escuro' ? 'claro' : 'escuro';
            localStorage.setItem(TEMA_CHAVE, novo);
            aplicarTema(novo);
        });
    }

    // MENU LATERAL NO CELULAR
    var botaoMenu = document.getElementById('botaoMenu');
    var lateral = document.getElementById('menuLateral');
    var sobreposicao = document.getElementById('sobreposicao');

    function alternarMenu() {
        if (!lateral) return;
        lateral.classList.toggle('aberto');
        if (sobreposicao) sobreposicao.classList.toggle('ativo');
    }

    if (botaoMenu) botaoMenu.addEventListener('click', alternarMenu);
    if (sobreposicao) sobreposicao.addEventListener('click', alternarMenu);

    // MENUS SUSPENSOS (usuario e alertas)
    function ligarSuspenso(idGatilho, idPainel) {
        var gatilho = document.getElementById(idGatilho);
        var painel = document.getElementById(idPainel);
        if (!gatilho || !painel) return;

        gatilho.addEventListener('click', function (ev) {
            ev.stopPropagation();
            // fecha os outros
            document.querySelectorAll('.aberto[id$="Painel"]').forEach(function (p) {
                if (p !== painel) p.classList.remove('aberto');
            });
            painel.classList.toggle('aberto');
        });
    }

    ligarSuspenso('menuUsuario', 'usuarioPainel');
    ligarSuspenso('sinoAlertas', 'sinoPainel');

    document.addEventListener('click', function () {
        document.querySelectorAll('[id$="Painel"].aberto').forEach(function (p) {
            p.classList.remove('aberto');
        });
    });

    // MASCARAS DE ENTRADA
    function soDigitos(v) { return v.replace(/\D/g, ''); }

    var mascaras = {
        telefone: function (v) {
            v = soDigitos(v).slice(0, 11);
            if (v.length <= 10) {
                return v.replace(/^(\d{0,2})(\d{0,4})(\d{0,4}).*/, function (t, a, b, c) {
                    return (a ? '(' + a : '') + (b ? ') ' + b : '') + (c ? '-' + c : '');
                });
            }
            return v.replace(/^(\d{0,2})(\d{0,5})(\d{0,4}).*/, function (t, a, b, c) {
                return (a ? '(' + a : '') + (b ? ') ' + b : '') + (c ? '-' + c : '');
            });
        },
        cpf: function (v) {
            v = soDigitos(v).slice(0, 11);
            return v.replace(/^(\d{0,3})(\d{0,3})(\d{0,3})(\d{0,2}).*/, function (t, a, b, c, d) {
                return a + (b ? '.' + b : '') + (c ? '.' + c : '') + (d ? '-' + d : '');
            });
        },
        cnpj: function (v) {
            v = soDigitos(v).slice(0, 14);
            return v.replace(/^(\d{0,2})(\d{0,3})(\d{0,3})(\d{0,4})(\d{0,2}).*/, function (t, a, b, c, d, e) {
                return a + (b ? '.' + b : '') + (c ? '.' + c : '') + (d ? '/' + d : '') + (e ? '-' + e : '');
            });
        },
        documento: function (v) {
            return soDigitos(v).length > 11 ? mascaras.cnpj(v) : mascaras.cpf(v);
        },
        cep: function (v) {
            v = soDigitos(v).slice(0, 8);
            return v.replace(/^(\d{0,5})(\d{0,3}).*/, function (t, a, b) {
                return a + (b ? '-' + b : '');
            });
        }
    };

    document.querySelectorAll('[data-mascara]').forEach(function (campo) {
        var tipo = campo.getAttribute('data-mascara');
        if (!mascaras[tipo]) return;

        var aplicar = function () { campo.value = mascaras[tipo](campo.value); };
        campo.addEventListener('input', aplicar);
        if (campo.value) aplicar();
    });

    // BUSCA DE ENDERECO PELO CEP (API publica ViaCEP)
    var campoCep = document.querySelector('[data-buscar-cep]');
    if (campoCep) {
        campoCep.addEventListener('blur', function () {
            var cep = soDigitos(campoCep.value);
            if (cep.length !== 8) return;

            campoCep.disabled = true;

            fetch('https://viacep.com.br/ws/' + cep + '/json/')
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.erro) { notificar('CEP nao encontrado.', 'aviso'); return; }
                    preencher('endereco', d.logradouro);
                    preencher('bairro', d.bairro);
                    preencher('cidade', d.localidade);
                    preencher('uf', d.uf);
                    var numero = document.querySelector('[name="numero"]');
                    if (numero) numero.focus();
                })
                .catch(function () {
                    notificar('Nao foi possivel consultar o CEP. Preencha manualmente.', 'aviso');
                })
                .finally(function () { campoCep.disabled = false; });
        });
    }

    function preencher(nome, valor) {
        var campo = document.querySelector('[name="' + nome + '"]');
        if (campo && valor) campo.value = valor;
    }

    // BUSCA INSTANTANEA NAS TABELAS
    document.querySelectorAll('[data-filtra-tabela]').forEach(function (entrada) {
        var alvo = document.querySelector(entrada.getAttribute('data-filtra-tabela'));
        if (!alvo) return;

        entrada.addEventListener('input', function () {
            var termo = entrada.value.toLowerCase().trim();
            var visiveis = 0;

            alvo.querySelectorAll('tbody tr').forEach(function (linha) {
                if (linha.hasAttribute('data-sem-resultado')) return;
                var achou = linha.textContent.toLowerCase().indexOf(termo) !== -1;
                linha.style.display = achou ? '' : 'none';
                if (achou) visiveis++;
            });

            var aviso = alvo.querySelector('[data-sem-resultado]');
            if (aviso) aviso.style.display = visiveis === 0 ? '' : 'none';
        });
    });

    // CONFIRMACAO ANTES DE EXCLUIR
    document.querySelectorAll('[data-confirmar]').forEach(function (el) {
        el.addEventListener('click', function (ev) {
            if (!confirm(el.getAttribute('data-confirmar'))) {
                ev.preventDefault();
            }
        });
    });

    // COPIAR TEXTO PARA A AREA DE TRANSFERENCIA
    document.querySelectorAll('[data-copiar]').forEach(function (botao) {
        botao.addEventListener('click', function () {
            var texto = botao.getAttribute('data-copiar');
            navigator.clipboard.writeText(texto).then(function () {
                notificar('Link copiado para a area de transferencia.', 'sucesso');
            }).catch(function () {
                notificar('Nao foi possivel copiar automaticamente.', 'aviso');
            });
        });
    });

    // PREENCHER LOGIN COM AS CONTAS DE DEMONSTRACAO
    document.querySelectorAll('[data-demo-email]').forEach(function (item) {
        item.addEventListener('click', function () {
            var email = document.getElementById('email');
            var senha = document.getElementById('senha');
            if (email) email.value = item.getAttribute('data-demo-email');
            if (senha) { senha.value = item.getAttribute('data-demo-senha'); senha.focus(); }
        });
    });

    // FECHAR ALERTAS AUTOMATICAMENTE
    setTimeout(function () {
        document.querySelectorAll('.alerta').forEach(function (a) {
            a.style.transition = 'opacity .4s, transform .4s';
            a.style.opacity = '0';
            a.style.transform = 'translateY(-8px)';
            setTimeout(function () { a.remove(); }, 400);
        });
    }, 6000);

    // NOTIFICACAO TEMPORARIA
    function notificar(mensagem, tipo) {
        var caixa = document.createElement('div');
        caixa.className = 'alerta alerta-' + (tipo || 'info');
        caixa.style.cssText = 'position:fixed;top:78px;right:20px;z-index:200;max-width:330px;box-shadow:0 12px 32px rgba(15,23,42,.14)';
        caixa.innerHTML = '<span>' + mensagem + '</span>';
        document.body.appendChild(caixa);

        setTimeout(function () {
            caixa.style.transition = 'opacity .4s';
            caixa.style.opacity = '0';
            setTimeout(function () { caixa.remove(); }, 400);
        }, 3500);
    }

    // Disponibiliza para outras paginas
    window.CC = { notificar: notificar, mascaras: mascaras, soDigitos: soDigitos };
})();
