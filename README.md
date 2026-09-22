# Calhas Colombo - Sistema de Orçamentos

TCC do curso de Análise e Desenvolvimento de Sistemas. É um sistema web pra
gestão de orçamentos de uma empresa (fictícia) de calhas, rufos e condutores
de alumínio. Feito em PHP puro + MySQL, sem framework — o objetivo era mostrar
que eu entendo o que tá acontecendo por baixo, não só usar um monte de lib
pronta.

A empresa que usei de base tinha (na ideia do trabalho) uma planilha de Excel
pra controlar tudo e vivia perdendo orçamento, então a proposta foi digitalizar
isso: cadastro de cliente, montar o orçamento com os produtos do catálogo,
mandar pro cliente aprovar pelo celular e acompanhar tudo num painel.

## Rodando localmente (XAMPP)

1. Instala o XAMPP se ainda não tiver.
2. Abre o painel de controle e dá Start no Apache e no MySQL.
3. Copia a pasta `calhas-colombo` pra dentro de `C:\xampp\htdocs\`.
4. Entra em `http://localhost/phpmyadmin`, aba Importar, escolhe o arquivo
   `banco/calhas_colombo.sql` e clica em Executar. Isso cria o banco com as
   tabelas e uns dados de teste já cadastrados.
5. Acessa `http://localhost/calhas-colombo/`.

Se o MySQL do seu XAMPP tiver senha no root (normalmente não tem), muda em
`inclui/config.php`, na constante `DB_SENHA`.

### Login pra testar

Na tela de login tem uns botões de "conta de teste" que já preenchem os
campos sozinho, mas os dados são esses aqui:

| Email | Senha | Perfil |
|---|---|---|
| admin@colombo.com | admin123 | Administrador (vê tudo) |
| gerente@colombo.com | gerente123 | Gerente (financeiro e relatórios) |
| pedro@colombo.com | 654321 | Calheiro |
| caua@colombo.com | 123456 | Técnico de campo (só vê os orçamentos dele) |

## Como o sistema é organizado

```
calhas-colombo/
├── index.php                 painel principal (indicadores e gráficos)
├── login.php / logout.php
├── perfil.php
│
├── clientes_listar.php
├── clientes_form.php         cadastro e edição no mesmo arquivo
├── clientes_excluir.php
│
├── orcamentos_listar.php
├── orcamentos_form.php       monta o orçamento (itens, medidas etc)
├── orcamentos_detalhes.php   visualizar / imprimir
├── orcamentos_excluir.php
├── proposta.php              essa é a página pública, sem login
│
├── produtos.php
├── insumos.php
├── fornecedores.php
├── relatorios.php
├── funcionarios.php
│
├── inclui/
│   ├── config.php
│   ├── conexao.php
│   ├── funcoes.php
│   ├── auth.php
│   ├── cabecalho.php
│   └── rodape.php
│
├── assets/
│   ├── css/estilo.css
│   └── js/app.js
│
└── banco/
    └── calhas_colombo.sql
```

## Funcionalidades principais

- CRUD de clientes, produtos, insumos e fornecedores
- Montagem de orçamento com itens vindos do catálogo (ou item avulso, quando
  não tem no catálogo ainda)
- Numeração automática do orçamento (tipo `ORC-2026-0001`, zera a cada ano)
- Link público pra o cliente aprovar ou recusar o orçamento pelo celular, sem
  precisar de login — foi a parte que mais gostei de fazer, porque na empresa
  que inspirou o projeto o dono falou que perde muito tempo ligando pra saber
  se o cliente viu a proposta ou não
- Controle de estoque simples: registrando a compra de um insumo vinculado a
  um produto, o estoque sobe sozinho
- Painel com uns gráficos (feitos em CSS puro mesmo, sem chart.js nem nada)
- Relatório de faturamento, desempenho por vendedor e ranking de produto
- Perfis de acesso diferentes (admin, gerente, calheiro, técnico de campo)
- Tema claro/escuro
- Busca de endereço automática pelo CEP (API do ViaCEP)

## Permissões por perfil

| | Admin | Gerente | Calheiro | Campo |
|---|:--:|:--:|:--:|:--:|
| Painel e clientes | x | x | x | x |
| Orçamentos | x | x | x | só os próprios |
| Ver valor financeiro | x | x | | |
| Produtos / fornecedores | x | x | | |
| Insumos | x | x | x | |
| Relatórios | x | x | | |
| Funcionários | x | | | |
| Excluir registro | x | | | |

## Configurações da empresa

Fica tudo em `inclui/config.php`: nome, CNPJ, telefone e endereço (que
aparecem na proposta impressa), número de WhatsApp, garantia padrão em meses,
validade do orçamento em dias, desconto pra pagamento à vista e valor padrão
de mão de obra.

Tem uma constante `MODO_DEBUG` lá também — deixa `true` enquanto tá
mexendo no código pra aparecer o erro na tela, e `false` antes de
apresentar/entregar (senão fica feio mostrando erro do PHP pra quem for
avaliar).

## Problemas que eu tive que corrigir de uma versão mais antiga

Comecei esse projeto de um jeito meio torto no começo do semestre e reescrevi
boa parte depois. Alguns problemas que apareceram no meio do caminho e que eu
corrigi:

- Tava fazendo query concatenando `$_GET` direto no SQL (`WHERE id = $id`),
  o que é abrir a porta pra SQL Injection. Troquei tudo pra PDO com
  prepared statement.
- Senha ficava salva em texto puro no banco. Agora usa `password_hash` /
  `password_verify` com bcrypt.
- Não tinha proteção nenhuma contra CSRF nos formulários. Adicionei um
  token que é validado em toda ação que muda alguma coisa no banco.
- A verificação de permissão rodava depois de já ter começado a imprimir
  HTML, então o `header()` de redirecionamento às vezes não funcionava
  (o PHP não deixa mandar header depois que já mandou algo pro navegador).
  Mudei pra checar tudo isso lá no topo do arquivo, antes de qualquer saída.
- Exclusão de cliente era um DELETE direto, e se o cliente já tinha
  orçamento vinculado isso ia bagunçar o histórico pros relatórios. Troquei
  pra exclusão lógica (marca como inativo em vez de apagar).
- Não era responsivo, ficava horrível no celular — e um dos pontos do
  projeto era justamente o pessoal usar em campo pelo celular, então
  arrumei isso.

## Se der algum erro

**"Não foi possível conectar ao banco"** — confere se o MySQL do XAMPP tá
rodando e se você importou o `.sql`.

**Tela em branco sem mensagem nenhuma** — ativa o `MODO_DEBUG` em
`config.php` pra aparecer o erro.

**CEP não preenche o endereço sozinho** — a busca usa a API do ViaCEP, então
precisa de internet. Sem conexão dá pra digitar manual mesmo.

**Esqueci a senha do admin** — roda isso no SQL do phpMyAdmin (o hash é de
`123456`):

```sql
UPDATE usuarios
SET senha = '$2y$10$cCz28tlrFCyQwggARnGIyOS8Aoic2WLkXB88AEoAG56ZYHmLxOu9q'
WHERE email = 'admin@colombo.com';
```

---

Testado com PHP 8.3 e MariaDB local via XAMPP. Ficou faltando algumas coisas
que eu queria ter colocado (relatório em PDF, notificação por e-mail quando o
cliente responde a proposta), fica como sugestão de trabalho futuro no TCC.
