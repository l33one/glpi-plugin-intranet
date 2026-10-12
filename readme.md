# GLPI Intranet Plugin

Portal de intranet corporativa dentro do GLPI — aba própria na tela principal com saudação personalizada, dados de contato do usuário, ativos atribuídos, cards pessoais de chamados, mural de avisos e links rápidos com ícones (Tabler ou imagem enviada).

> **Corporate intranet portal for GLPI**: a dedicated tab on the main screen with a personalized greeting, profile contact info, assigned assets, personal ticket cards, a notice board and quick links (Tabler icon or uploaded image).

- **Versão:** 1.4.0
- **Compatibilidade:** GLPI 10 e 11
- **Idiomas:** English (`en_GB`), Português (`pt_BR`)
- **Licença:** GPL-2.0-or-later

## Funcionalidades

| Área | Descrição |
|---|---|
| Aba Intranet | Aba própria na tela principal (Central), fora do dashboard |
| Saudação | Bom dia / Boa tarde / Boa noite + data, com dados do perfil: telefone(s), e-mail(s), nome do gestor e grupos do usuário |
| Meus ativos | Computadores, monitores, telefones, impressoras, periféricos e equipamentos de rede atribuídos ao usuário, com link direto |
| Cards de chamados | Meus chamados abertos · Aguardando minha aprovação · Chamados que observo · Meus pendentes · Meus solucionados (não fechados) · Pesquisas de satisfação pendentes (últimos 7 dias) |
| Mural de avisos | Categorias, fixação no topo, vigência (início/fim), busca e leitura em modal via AJAX |
| Links rápidos | URL interna (`/front/...`) ou externa `http(s)`, ícone Tabler **ou imagem enviada** (PNG/JPG/GIF/WEBP/ICO, máx. 2 MB), ordenação e ativação, exibidos como cartões visuais |
| Banners | Carrossel de banners com imagem, legenda e link opcional |
| Personalização | CSS extra nas configurações para ajustar cores e tamanhos |
| Portal completo | Página em Plugins → Intranet com busca e filtro por categoria |

## Instalação

1. A pasta do plugin **precisa** se chamar `intranet`:
   `glpi/plugins/intranet/`
2. No GLPI: Configuração → Plugins → **Intranet** → Instalar e Ativar.
3. Dê o direito aos perfis na aba **Intranet** do formulário do perfil (Administração → Perfis → perfil → aba Intranet): **Sem acesso**, **Visualizar** ou **Gerenciar conteúdo**.
4. O conteúdo é gerenciado em Configuração → Plugins → **Intranet** (abas Geral, Avisos e Links).

## Upgrade (1.0.x → 1.1.0)

Basta substituir os arquivos e clicar em **Atualizar** na lista de plugins. A migração é idempotente e adiciona a coluna `image` na tabela de links sem apagar nenhum dado.

> **Mudança visível:** a intranet saiu de dentro do dashboard e ganhou **aba própria** na tela principal. Se a aba não aparecer, ative "Exibir a aba Intranet" na aba Geral das configurações.

## Permissões

| Direito | Efeito |
|---|---|
| Intranet — Leitura | Vê a aba Intranet, o portal, avisos e links |
| Intranet — Atualização | Gerencia avisos e links (não precisa ser super-admin) |
| Configuração (GLPI) | Aba Geral das configurações (super-admin) |

Os cards de chamados só aparecem para perfis com direito de ver chamados, e os números respeitam as entidades ativas do usuário.

## Segurança

Auditoria executada contra os critérios do marketplace GLPI:

- Toda saída renderizada com `htmlspecialchars` (XSS); endpoint AJAX responde JSON com flags `JSON_HEX_*` e o JS insere via `textContent`
- Todas as consultas pelo query builder do GLPI com `getEntitiesRestrictCriteria()`; nenhum SQL concatena entrada do usuário (o único `DROP TABLE` usa lista fixa interna)
- CSRF: `csrf_compliant` + token `_glpi_csrf_token` em todos os formulários POST
- Todo `front/` e `ajax/` exige login + verificação de direito; toda classe em `inc/` tem guarda `GLPI_ROOT`
- Upload de imagens: allow-list de extensão, verificação MIME (`getimagesize`), limite de 2 MB, `is_uploaded_file` + nome aleatório, servido por script dedicado com `nosniff`
- **SVG bloqueado de propósito**: SVG servido na origem do GLPI permitiria XSS armazenado
- URLs de links restritas a `https?://` absolutas ou caminhos internos iniciados por `/` (sem `javascript:`, `data:`, `//`)
- Sem `eval`, `exec`, `unserialize`, `extract` ou desserialização em todo o código

## Estrutura

```text
intranet/
├── setup.php              # hooks, versão, registro da aba Central e do menu
├── hook.php               # install/upgrade idempotente + uninstall limpo
├── intranet.xml           # manifesto do marketplace
├── front/
│   ├── intranet.php       # portal completo (Plugins → Intranet)
│   ├── config.php         # configurações (Setup → Plugins → Intranet)
│   └── image.php          # serve imagens dos links com controle de acesso
├── ajax/announcement.php  # leitura de aviso (JSON, só visíveis)
├── inc/
│   ├── portal.class.php       # renderização: saudação, perfil, cards, mural, links
│   ├── central.class.php      # aba "Intranet" na Central
│   ├── announcement.class.php # modelo + validação dos avisos
│   ├── link.class.php         # modelo + upload das imagens dos links
│   ├── config.class.php       # telas de configuração
│   └── menu.class.php         # entrada Plugins → Intranet
├── locales/               # en_GB / pt_BR (.po + .mo compilado)
│   └── ...                # `php tools/compile_locales.php` recompila
├── public/css|js/         # estilos responsivos + modal do leitor (vanilla JS)
└── tools/                 # compile_locales.php, check_locales.php (CLI)
```

## Desenvolvimento

```powershell
php -l setup.php            # lint
php tools/check_locales.php # confere se os catálogos estão completos
php tools/compile_locales.php # recompila os .mo após editar os .po
```

## Changelog

### 1.4.0
- Novo layout em 2 colunas: barra lateral com logo do GLPI, links 2 por linha e ativos
- Barra de saudação com foto do usuário de um lado e dados cadastrais do outro
- Cards de chamados todos em uma única linha
- Listas de formulários padrão (fixados/destaques) e pesquisas salvas do usuário

### 1.3.0
- Layout compacto em 2 colunas (links + avisos lado a lado em telas largas) para caber mais na tela
- Nova aba **Ajuda de CSS** nas configurações com seletores e exemplos prontos

### 1.2.0
- Aba **Intranet** no formulário de Perfis para conceder visualização ou gerenciamento
- Botão "Editar meus dados" leva às preferências do usuário; botão de configuração de volta à lista de plugins
- Cards em grade 2×3; links rápidos como cartões visuais
- **Banners em carrossel** com upload de imagem, legenda e link
- Campo de **CSS personalizado** nas configurações

### 1.1.0
- Intranet movida do dashboard para **aba própria** na tela principal
- Links rápidos com **imagem de ícone** (upload) além do ícone Tabler
- Saudação com **contato do perfil** (telefones, e-mails, gestor, grupos) e **ativos atribuídos**
- Cards refeitos: só métricas pessoais (abertos, aguardando aprovação, observados, pendentes, solucionados e satisfação pendente de 7 dias)
- Correções: registro do menu Plugins, remoção de hooks/classes inexistentes, SVG bloqueado no upload

### 1.0.0
- Versão inicial: painel no dashboard, mural de avisos e links rápidos
