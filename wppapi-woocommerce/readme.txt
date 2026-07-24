=== WPPAPI para WooCommerce ===
Contributors: iproute-tecnologia
Tags: whatsapp, woocommerce, order notifications, wppapi, lgpd
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
WC requires at least: 8.0
WC tested up to: 9.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sends transactional WooCommerce order updates to customers via WhatsApp (WPPAPI), with LGPD opt-in at checkout, async sending with retry and message log.

== Description ==

O **WPPAPI para WooCommerce** envia notificações de pedido por WhatsApp usando a API gerenciada da [WPPAPI](https://api.wpp-api.com) (gateway estilo Z-API por instância).

O plugin é **transacional por design**: só envia atualizações de pedido para clientes que deram opt-in explícito no checkout. Isso mantém sua loja em conformidade com a LGPD e protege o seu número de banimento por spam.

= Eventos disponíveis =

Cada evento pode ser ativado/desativado individualmente e tem template de mensagem editável:

1. **Pedido criado** — disparado quando um novo pedido é registrado.
2. **Pagamento aprovado** — quando o pedido muda para o status "Processando".
3. **Pedido enviado** — disparado pela meta box "WhatsApp (WPPAPI) — Envio" na tela do pedido: informe o código de rastreio e clique em "Salvar e notificar envio". Se o pedido já tiver rastreio de outro plugin (`_tracking_code` ou WooCommerce Shipment Tracking), ele é usado como fallback no placeholder `{rastreio}`.
4. **Pedido concluído** — quando o pedido muda para o status "Concluído".

= Placeholders =

`{nome}` (primeiro nome do cliente), `{pedido}` (número do pedido), `{total}` (valor formatado com moeda), `{rastreio}` (código de rastreio).

= Recursos =

* Opt-in LGPD no checkout ("Aceito receber atualizações do pedido por WhatsApp"), salvo no pedido e visível no admin. **Nenhuma mensagem sai sem opt-in.**
* Normalização automática do telefone de cobrança para E.164 brasileiro (55 + DDD + número, com tratamento do nono dígito).
* Envio assíncrono via Action Scheduler (do próprio WooCommerce), com fallback para wp-cron, e retry com backoff de 5 min, 30 min e 2 h.
* Botão "Testar conexão" que consulta o status da instância.
* Log das últimas 50 mensagens (data, pedido, telefone, evento, status, código HTTP, erro).
* Compatível com HPOS (Custom Order Tables). Token armazenado em option e nunca exibido em claro (apenas os últimos 4 caracteres).

== Installation ==

1. Faça upload da pasta `wppapi-woocommerce` para `/wp-content/plugins/` (ou instale o .zip pela tela de plugins).
2. Ative o plugin. O WooCommerce 8.0+ precisa estar ativo.
3. Vá em **WooCommerce → WPPAPI**, informe Base URL (padrão `https://api.wpp-api.com`), Instance ID e Token da sua instância WPPAPI.
4. Clique em **Salvar configurações** e depois em **Testar conexão**.
5. Ajuste os templates de mensagem de cada evento e salve.

== Frequently Asked Questions ==

= O plugin pode ser usado para marketing/disparos em massa? =

Não. O plugin é transacional por design: só envia atualizações de pedido para clientes que deram opt-in no checkout. Isso protege seu número de banimento por spam e mantém a loja em conformidade com a LGPD.

= Como funciona o evento "Pedido enviado"? =

O WooCommerce não tem um status "enviado" nativo. Por isso o plugin adiciona a meta box **"WhatsApp (WPPAPI) — Envio"** na tela do pedido: informe o código de rastreio e clique em **"Salvar e notificar envio"**. O código fica salvo no pedido (meta `_wppapi_tracking_code`) e a mensagem do evento é enfileirada. Se você usa outro plugin de rastreio (`_tracking_code` ou WooCommerce Shipment Tracking), o código dele é usado como fallback no placeholder `{rastreio}`.

= E se o cliente não marcar o opt-in? =

Nenhuma mensagem é enviada para aquele pedido. O log registra o evento como "Ignorado (sem opt-in)".

= O que acontece se a API falhar? =

O envio é assíncrono (Action Scheduler, com fallback para wp-cron). Em caso de falha, o plugin tenta de novo até 3 vezes, com intervalos de 5 minutos, 30 minutos e 2 horas. Todas as tentativas ficam registradas no log.

= Quais formatos de telefone são aceitos? =

O telefone de cobrança do pedido é normalizado para E.164 sem "+": entradas com ou sem DDI 55 e com ou sem o nono dígito são convertidas para 55 + DDD + número (celulares com 9 dígitos).

= O token fica seguro? =

O token é salvo em uma option do WordPress, enviado apenas no header `Client-Token` (nunca na URL) e nunca é exibido em claro na tela — apenas os últimos 4 caracteres.

== Screenshots ==

1. Tela de configurações (WooCommerce → WPPAPI): credenciais, teste de conexão, templates dos 4 eventos e texto do opt-in.
2. Checkbox de opt-in LGPD no checkout.
3. Meta box "WhatsApp (WPPAPI) — Envio" na tela do pedido, com campo de rastreio e botão "Salvar e notificar envio".
4. Log das últimas 50 mensagens com status, código HTTP e erro.

== Changelog ==

= 1.0.0 =
* Versão inicial: 4 eventos de pedido (criado, pagamento aprovado, enviado, concluído), templates editáveis com placeholders, opt-in LGPD no checkout, envio assíncrono com retry (Action Scheduler com fallback wp-cron), teste de conexão, log das últimas 50 mensagens e compatibilidade HPOS.

== Upgrade Notice ==

= 1.0.0 =
Versão inicial.
