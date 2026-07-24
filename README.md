# WPPAPI for WooCommerce

Plugin WordPress que envia atualizações transacionais de pedidos do WooCommerce por WhatsApp via [WPPAPI](https://api.wpp-api.com) (API WhatsApp não-oficial gerenciada, gateway estilo Z-API por instância).

**Transacional por design:** só envia mensagens para clientes que deram opt-in explícito no checkout (LGPD) — nada de disparos de marketing.

- Repositório da API/servidor: [IPRoute-Tecnologia/wppapi](https://github.com/IPRoute-Tecnologia/wppapi)
- Licença: GPL-2.0+ (ver `LICENSE`)

## Requisitos

- WordPress 6.0+, PHP 7.4+, WooCommerce 8.0+
- Uma instância WPPAPI (Base URL, Instance ID e Token)

## Estrutura

```
wppapi-for-woocommerce/            ← pasta do plugin (copiar para wp-content/plugins/)
├── wppapi-for-woocommerce.php     ← bootstrap, header, helpers (settings, telefone, log)
├── uninstall.php              ← remove options e ações agendadas
├── readme.txt                 ← formato do diretório wordpress.org
├── includes/
│   ├── class-wppapi-wc-api.php       ← cliente HTTP (Client-Token no header, timeout 15s)
│   ├── class-wppapi-wc-messenger.php ← fila assíncrona (Action Scheduler / wp-cron) + retry
│   ├── class-wppapi-wc-order.php     ← hooks de pedido, opt-in no checkout, meta box de envio
│   └── class-wppapi-wc-settings.php  ← tela de configurações, teste de conexão, log
└── languages/
    └── wppapi-for-woocommerce.pot
```

## Eventos de pedido → WhatsApp

| Evento | Disparo |
|---|---|
| Pedido criado | `woocommerce_new_order` |
| Pagamento aprovado | `woocommerce_order_status_processing` |
| Pedido enviado | Meta box no pedido: botão "Salvar e notificar envio" (salva `_wppapi_tracking_code`) |
| Pedido concluído | `woocommerce_order_status_completed` |

Placeholders: `{nome}`, `{pedido}`, `{total}`, `{rastreio}`.

## Como testar localmente

1. Monte um ambiente WordPress + WooCommerce (ex.: [LocalWP](https://localwp.com), Docker `wordpress:php7.4`+ ou `wp-env`).
2. Copie a pasta `wppapi-for-woocommerce/` para `wp-content/plugins/` e ative o plugin.
3. Em **WooCommerce → WPPAPI**, configure Base URL, Instance ID e Token, salve e clique em **Testar conexão** (chama `GET /instances/{id}/token/_/status`).
4. Crie um pedido de teste marcando o opt-in no checkout e com um telefone de cobrança válido.
5. Acompanhe os envios agendados em **WooCommerce → Status → Scheduled Actions** (grupo `wppapi-for-woocommerce`) e o resultado no **log** da tela de configurações.
6. Para o evento "enviado", abra o pedido e use a meta box **WhatsApp (WPPAPI) — Envio**.

Dica: sem credenciais reais, aponte a Base URL para um mock (ex.: `wp-cli eval` + `wp_remote_post` interceptável ou um servidor local que responda 200) para validar o fluxo de fila, retry e log.

## Regenerar o .pot

O arquivo `languages/wppapi-for-woocommerce.pot` lista todas as strings traduzíveis (pt-BR inline via `__()`/`esc_html__()` etc.). Para regenerar com o WP-CLI:

```bash
wp i18n make-pot wppapi-for-woocommerce wppapi-for-woocommerce/languages/wppapi-for-woocommerce.pot
```

## Distribuição (wordpress.org)

- Plugin Check (PCP) roda em CI: **0 erros**. Os únicos warnings são os termos de trademark "wp"/"woocommerce" no slug `wppapi-for-woocommerce` — inerentes à marca WPPAPI.
- Decisão registrada (issue #15): manter o slug de marca `wppapi-for-woocommerce` na submissão e argumentar trademark próprio na revisão humana; fallback caso o diretório rejeite: submeter como `whatsapp-order-notifications-for-woocommerce` (sem "wp"/"woocommerce" no slug).
- A submissão ao wordpress.org exige conta no diretório (bloqueio externo); enquanto isso, o zip oficial fica em https://wpp-api.com/downloads/wppapi-for-woocommerce.zip com guia em https://wpp-api.com/guias/whatsapp-woocommerce.
