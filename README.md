# 🎲 CritMeet

**Plataforma web de matchmaking para jogadores de RPG de mesa**  
Trabalho de Conclusão de Curso — Análise e Desenvolvimento de Sistemas | FATEC Praia Grande

---

## Sobre

O CritMeet é uma aplicação web desenvolvida para aproximar jogadores de RPG de mesa que compartilham interesses em sistemas de jogo semelhantes e desejam participar de sessões presenciais. A plataforma utiliza um sistema de matchmaking com filtragem por localização, sistema de jogo preferido, disponibilidade e nível de experiência.

O projeto nasceu da observação direta de uma dificuldade real dentro da comunidade: a falta de uma ferramenta dedicada para formar grupos compatíveis. Após a pandemia, muitos jogadores se desconectaram da prática presencial e não encontraram meios organizados para retomar — o CritMeet propõe resolver isso.

---

## Funcionalidades

- 🔍 **Matchmaking** com filtros por localização, sistema de jogo e disponibilidade
- 🗺️ **Mapa interativo** com jogadores próximos via Leaflet + OpenStreetMap
- 📅 **Agendamento de sessões** com calendário interativo via FullCalendar
- 💬 **Chat em tempo real** entre usuários e grupos, com atualização via AJAX
- 👥 **Sistema de amizades** com solicitação e aceitação mútua
- 🛡️ **Moderação** com denúncias, bloqueios e controle de privacidade
- 🔐 **Autenticação segura** com criptografia Bcrypt, HTTPS e controle de sessões PHP

---

## Stack

| Camada | Tecnologia |
|---|---|
| Back-end | PHP 8.2 |
| Front-end | HTML5, CSS3, JavaScript (ES6+), Bootstrap 5.3, jQuery 3.6 |
| Banco de Dados | MySQL (engine InnoDB, charset UTF8MB4) |
| Mapas | Leaflet + OpenStreetMap |
| Calendário | FullCalendar API |
| Servidor | VPS Debian 12 — DigitalOcean Droplet (Apache2) |
| Segurança | HTTPS, SSH, Bcrypt, SHA-256, .htaccess/.htpasswd |
| Versionamento | Git + GitHub |

---

## Arquitetura

A aplicação segue uma estrutura modular por páginas, onde cada funcionalidade possui seu próprio diretório contendo `index.php`, estilos CSS e scripts JavaScript. Elementos compartilhados como `header.php` e `footer.php` são reutilizados em todas as páginas.

O roteamento é centralizado em `router.php`, que converte URLs amigáveis (ex: `/login`) para os caminhos físicos correspondentes no servidor, seguindo princípios RESTful e bloqueando acesso direto a arquivos sensíveis via `.htaccess`.

---

## Banco de Dados

Estruturado com foco em modularidade e integridade referencial. Principais tabelas:

- `users` — perfis, credenciais e preferências dos jogadores
- `sessions` — sessões de RPG com controle de capacidade, status e localização
- `session_members` — participação com fluxo de convite (pending / accepted / declined)
- `user_locations` — coordenadas geográficas para buscas por proximidade
- `friends` — relacionamentos sociais bidirecionais
- `chats` + `chat_members` + `messages` — sistema completo de mensageria
- `reports` — moderação com fluxo administrativo

Uma *view* materializada agrega dados de sessões e chats para otimizar consultas frequentes, incluindo cálculo automático de status real (lotada, encerrada, ativa).

---

## Segurança

- Senhas criptografadas com **Bcrypt** (salting + múltiplas iterações)
- Comunicação criptografada via **HTTPS**
- Acesso ao servidor via **SSH** com chaves **SHA-256**
- Proteção de diretórios administrativos com **.htaccess** e **.htpasswd**
- Prevenção de **SQL Injection** via prepared statements com `bind_param`
- Prevenção de **XSS** via `filter_input` e `htmlspecialchars()`
- Controle de sessões e permissões administrativas via **sessões PHP**

---

## Metodologia

O desenvolvimento foi conduzido com metodologia ágil **SCRUM**, em sprints de 2 a 4 semanas, com reuniões periódicas de alinhamento e revisão. A pesquisa adotou abordagem qualitativa, embasada em revisão de literatura e na vivência direta dos integrantes na comunidade de RPG local.

---

## Equipe

Desenvolvido por alunos do curso de **Análise e Desenvolvimento de Sistemas** da **FATEC Praia Grande** como Trabalho de Conclusão de Curso (TCC).

| Nome | GitHub |
|---|---|
| Gabriela Novo | [Gabriela-Novo](https://github.com/Gabriela-Novo) |
| Kaik Novais | [kaiknovais](https://github.com/kaiknovais) |
| Luana Teixeira | [lua06na](https://github.com/lua06na) |

---

> *"A essência de um RPG é que se trata de uma experiência coletiva e cooperativa."*  
> — Gary Gygax, co-criador de Dungeons & Dragons
