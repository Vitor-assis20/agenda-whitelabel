# Histórico do desenvolvimento

Este documento resume o que foi construído no repositório **Agenda**, um SaaS multi-tenant e whitelabel de agendamento online para negócios de serviço (barbearias, clínicas, estúdios). O [README](../README.md) descreve o produto e as decisões técnicas; aqui está a ordem em que tudo foi feito e o que cada etapa entregou.

**Período:** 05/10/2026 a 06/10/2026 · **17 commits** · **178 testes** rodando em PostgreSQL real

**Stack:** Laravel 12 · PHP 8.4 · React 19 · TypeScript · Inertia.js · Tailwind CSS 4 · PostgreSQL 17 · Redis · Docker

---

## Visão geral por fase

| Fase | Commits | Resultado |
| --- | --- | --- |
| 1. Fundação | `bc0c0c6`, `7460607` | Projeto, ambiente Docker e modelo de dados multi-tenant |
| 2. Núcleo de agendamento | `6f51c2b` | Cálculo de horários livres e reserva segura contra concorrência |
| 3. Página pública | `dc4005c`, `d5610da` | Reserva pelo subdomínio do negócio, com a marca dele |
| 4. Painel | `62a7c52`, `792e089`, `5dbe8c6` | Agenda do dia, cadastros e configuração da marca |
| 5. Qualidade e documentação | `380f75c`, `5f59796` | CI com Postgres e linters; README com prints |
| 6. Comunicação com o cliente | `ec3bfb6`, `d246e2e`, `d4c0e5b` | E-mails por fila, lembretes agendados, botões na cor da marca |
| 7. Regras de agenda avançadas | `f031f1a`, `9cbfa7a`, `8795158` | Folgas, bloqueio de reservas simultâneas do cliente, remarcação |
| 8. Correções | `19821cb` | Cast de datas UTC sem cache de objeto |

---

## Detalhes de cada etapa

### 1. Fundação

**`bc0c0c6` — Setup inicial**
- Laravel com o starter kit React + TypeScript.
- Ambiente Docker: PHP 8.4-FPM, Nginx, PostgreSQL 17, Redis e Mailpit.

**`7460607` — Modelagem multi-tenant**
- Tabelas de negócios (tenants), profissionais, serviços, clientes, expediente, folgas e agendamentos.
- Isolamento por `tenant_id` com *global scope* que **falha fechado**: sem tenant definido, nenhuma consulta retorna dados ([`TenantScope`](../app/Support/Tenancy/TenantScope.php)).
- Chaves estrangeiras compostas `(tenant_id, id)`: o banco impede que um registro de um negócio aponte para outro.
- *Exclusion constraint* no Postgres impede dois agendamentos sobrepostos para o mesmo profissional.
- Testes rodando em Postgres e seeder com dados de demonstração.

### 2. Núcleo de agendamento

**`6f51c2b` — Disponibilidade e reserva**
- [`Availability`](../app/Scheduling/Availability.php): horários livres = expediente − folgas − agendamentos ativos, no fuso do negócio, em intervalos de 15 minutos.
- [`BookAppointment`](../app/Scheduling/BookAppointment.php): valida o horário e transforma a violação da constraint (duas reservas simultâneas) numa mensagem amigável.
- Cast [`UtcDateTime`](../app/Casts/UtcDateTime.php) e sessão do Postgres fixada em UTC, corrigindo o deslocamento de 3 horas que o Eloquent causaria ao gravar datas sem offset.

### 3. Página pública

**`dc4005c` — Agendamento por subdomínio**
- Middleware [`ResolveTenant`](../app/Http/Middleware/ResolveTenant.php) identifica o negócio por `{slug}.dominio`, antes do *route model binding* (agendamento de outro negócio → 404).
- Fluxo serviço → profissional → data/horário → dados do cliente.
- Confirmação por URL assinada; cliente recorrente reconhecido pelo e-mail.

**`d5610da` — Ajustes**
- Nome do negócio no título da aba.
- Login, cadastro e painel deixam de responder nos subdomínios.
- Erros de TypeScript herdados do starter kit corrigidos (`tsc` limpo).

### 4. Painel

**`62a7c52` — Agenda do dia**
- Negócio ativo guardado na sessão, com troca entre os negócios do usuário.
- Agenda por profissional, resumo do dia e faturamento previsto.
- Ciclo de vida do agendamento no enum `AppointmentStatus`: confirmar, concluir, marcar falta, cancelar (concluir e falta só depois do início).
- Papéis: dono/administrador gerenciam tudo; a equipe vê e altera só a própria agenda ([`AppointmentPolicy`](../app/Policies/AppointmentPolicy.php)).

**`792e089` — Cadastros**
- CRUD de serviços (preço em centavos, nome único por negócio).
- CRUD de profissionais, com vínculo aos serviços.
- Editor de expediente semanal com vários intervalos por dia e validação de sobreposição.
- Exclusão só para registros sem histórico; com agendamentos, apenas desativação.

**`5dbe8c6` — Marca do negócio**
- Nome, cor principal e logo, com prévia ao vivo da página pública.
- Cor do texto sobre a marca escolhida por contraste WCAG.
- Logo em PNG/JPG/WebP (SVG recusado por segurança); arquivo antigo removido na troca.

### 5. Qualidade e documentação

**`380f75c` — CI**
- Workflow de testes com Postgres 17 (antes tentava SQLite e falhava sempre).
- Linter em modo de verificação: `pint --test`, `prettier --check`, ESLint e `tsc`.

**`5f59796` — README**
- Funcionalidades, decisões técnicas, prints e instruções de setup com Docker.
- Correção: a cor da marca não era aplicada (variáveis do tema Tailwind resolvidas no `:root`).
- Dados de demonstração em pt_BR e licença MIT.

### 6. Comunicação com o cliente

**`ec3bfb6` — E-mails, lembretes e cancelamento**
- Confirmação, lembretes 24h e 2h antes e aviso de cancelamento, com a marca do negócio, enviados por fila (Redis).
- Notificações carregam um [`AppointmentSummary`](../app/Scheduling/AppointmentSummary.php) com dados prontos, porque o worker da fila não tem tenant no contexto.
- Comando [`SendAppointmentReminders`](../app/Console/Commands/SendAppointmentReminders.php) a cada 5 minutos; `UNIQUE (appointment_id, kind)` impede envio duplicado.
- Cliente cancela pelo link assinado até o início do atendimento.
- Serviços `queue` e `scheduler` adicionados ao Docker.

**`d246e2e` — Correção do `.gitignore`**
- A regra `vendor/` ignorava também `resources/views/vendor`, então o layout whitelabel do e-mail não era versionado e o CI falhava.

**`d4c0e5b` — Botões na cor da marca**
- [`BrandColor`](../app/Support/BrandColor.php): o mesmo cálculo de contraste do front, portado para PHP.

### 7. Regras de agenda avançadas

**`f031f1a` — Folgas e bloqueios**
- Folga de dia inteiro (até 90 dias) ou parte do dia.
- Aviso dos agendamentos já marcados no período; nada é cancelado automaticamente.
- Folgas exibidas na agenda de cada profissional.

**`9cbfa7a` — Cliente sem horários simultâneos**
- Segunda *exclusion constraint*, por `customer_id`; o nome da constraint violada define a mensagem.
- A migration verifica conflitos existentes e para listando-os, em vez de cancelar algo.

**`8795158` — Remarcação pelo cliente**
- Página de remarcação pelo link assinado (mesmo profissional e serviço).
- [`RescheduleAppointment`](../app/Scheduling/RescheduleAppointment.php): mesmas checagens e constraints da reserva, num `UPDATE`.
- Coluna `booked_at`: os lembretes recomeçam a contar a partir da remarcação.
- E-mail "Agendamento remarcado" com horário antigo e novo.
- Componentes de data e horário compartilhados entre reserva e remarcação.

### 8. Correções

**`19821cb` — Cast `UtcDateTime`**
- O Eloquent guardava em cache o objeto atribuído ao campo, que voltava mutável e no fuso de origem. `withoutObjectCaching` faz a leitura sempre passar pelo cast. Teste de regressão incluído.

---

## Estado atual

**Funciona hoje**
- Página pública por subdomínio: reservar, cancelar e remarcar.
- E-mails de confirmação, lembretes, remarcação e cancelamento, com a marca do negócio.
- Painel: agenda do dia, status dos agendamentos, serviços, profissionais, expediente, folgas e marca.
- Vários negócios por usuário, com papéis de dono, administrador e equipe.

**Garantias no banco**
- Nenhum profissional com dois agendamentos sobrepostos.
- Nenhum cliente com dois agendamentos sobrepostos.
- Nenhuma referência entre negócios diferentes.
- Nenhum lembrete enviado duas vezes.

**Próximos passos** (do README)
- Lembretes por WhatsApp.
- Cadastro self-service de novos negócios.
- Aviso de agendamentos afetados ao reduzir o expediente.
