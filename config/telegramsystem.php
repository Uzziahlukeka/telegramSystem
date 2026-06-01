<?php

declare(strict_types=1);

// Configuration for uzhlaravel/telegramsystem.
return [

    /*
    |--------------------------------------------------------------------------
    | Default bot
    |--------------------------------------------------------------------------
    |
    | The key (inside the "bots" array below) that should be treated as the
    | default bot. The default bot mirrors how uzhlaravel/telegramlogs works:
    | it reads its credentials from the standard TELEGRAM_* env vars and, when
    | enabled, delegates simple outbound sends to telegramlogs.
    |
    */
    'default_bot' => env('TELEGRAM_SYSTEM_DEFAULT_BOT', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Delegate default-bot sends to telegramlogs
    |--------------------------------------------------------------------------
    |
    | When true, simple text sends for the default bot are routed through the
    | uzhlaravel/telegramlogs TelegramMessage facade instead of this package's
    | own HTTP client. Named/extra bots always use this package's client.
    |
    */
    'use_telegramlogs_for_default' => (bool) env('TELEGRAM_SYSTEM_USE_TELEGRAMLOGS', true),

    /*
    |--------------------------------------------------------------------------
    | Bots
    |--------------------------------------------------------------------------
    |
    | Each bot entry supports: token, chat_id, topic_id, webhook_secret, label.
    | The default bot maps to the same env vars telegramlogs already uses.
    |
    */
    'bots' => [
        'default' => [
            'token' => env('TELEGRAM_BOT_TOKEN'),
            'chat_id' => env('TELEGRAM_CHAT_ID'),
            'topic_id' => env('TELEGRAM_TOPIC_ID'),
            'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
            'label' => 'Default',
        ],

        'support' => [
            'token' => env('TELEGRAM_SUPPORT_BOT_TOKEN'),
            'chat_id' => env('TELEGRAM_SUPPORT_CHAT_ID'),
            'topic_id' => env('TELEGRAM_SUPPORT_TOPIC_ID'),
            'webhook_secret' => env('TELEGRAM_SUPPORT_WEBHOOK_SECRET'),
            'label' => 'Support',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Administrators
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of Telegram user IDs that are treated as admins.
    | Admins may view, assign, close and reopen every ticket and never become
    | the assigned agent themselves.
    |
    */
    'admins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TELEGRAM_SYSTEM_ADMINS', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Visibility model
    |--------------------------------------------------------------------------
    |
    | Telegram has no native per-topic privacy, so confidentiality is enforced
    | at the application layer. Choose one model:
    |
    |   "group_topic" - The bot rejects (and optionally deletes) messages in a
    |                   ticket topic from anyone other than the owner, assigned
    |                   agent, or an admin. Already-posted messages cannot be
    |                   hidden retroactively.
    |
    |   "dm_routed"   - The real conversation happens in the owner's and agent's
    |                   private chats; the forum topic is an admin-only mirror.
    |
    */
    'visibility' => env('TELEGRAM_SYSTEM_VISIBILITY', 'group_topic'),

    /*
    |--------------------------------------------------------------------------
    | Forum topics
    |--------------------------------------------------------------------------
    */
    'topics' => [
        'enabled' => (bool) env('TELEGRAM_SYSTEM_TOPICS_ENABLED', true),
        'sync_status' => (bool) env('TELEGRAM_SYSTEM_TOPICS_SYNC', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Enforcement
    |--------------------------------------------------------------------------
    |
    | When delete_unauthorized is true, messages posted by users who are not
    | allowed to interact with a ticket are deleted from the group.
    |
    */
    'enforcement' => [
        'delete_unauthorized' => (bool) env('TELEGRAM_SYSTEM_DELETE_UNAUTHORIZED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook
    |--------------------------------------------------------------------------
    |
    | The inbound webhook route is registered as POST {path}/{bot}. The
    | X-Telegram-Bot-Api-Secret-Token header is validated against each bot's
    | webhook_secret.
    |
    */
    'webhook' => [
        'enabled' => (bool) env('TELEGRAM_SYSTEM_WEBHOOK_ENABLED', true),
        'path' => env('TELEGRAM_SYSTEM_WEBHOOK_PATH', 'telegram/webhook'),
        'middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP / API
    |--------------------------------------------------------------------------
    */
    'api_base' => env('TELEGRAM_API_BASE', 'https://api.telegram.org'),
    'timeout' => (int) env('TELEGRAM_TIMEOUT', 15),

    /*
    |--------------------------------------------------------------------------
    | Log channel (telegramlogs)
    |--------------------------------------------------------------------------
    |
    | The log channel used when writing through TelegramSystem::log(). Defaults
    | to the "telegram" Monolog channel provided by uzhlaravel/telegramlogs.
    |
    */
    'log_channel' => env('TELEGRAM_SYSTEM_LOG_CHANNEL', 'telegram'),

    /*
    |--------------------------------------------------------------------------
    | Web chat widget
    |--------------------------------------------------------------------------
    |
    | The optional website-facing chat widget. Visitor messages are mirrored
    | into the configured bot's chat (and topic, when set) as a threaded ticket,
    | and agent replies posted in Telegram flow back into the widget.
    |
    |   bot - which configured bot (and therefore which chat_id / topic_id)
    |         the web chat routes through. Defaults to the "support" bot.
    |
    */
    'web_chat' => [
        'enabled' => (bool) env('TELEGRAM_SYSTEM_WEB_CHAT_ENABLED', true),
        'bot' => env('TELEGRAM_SYSTEM_WEB_CHAT_BOT', 'support'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Direct-message support bridge
    |--------------------------------------------------------------------------
    |
    | A turnkey "DM the bot, talk to a human" support flow that does NOT rely on
    | forum topics, so it works in any group:
    |
    |   - A contact direct-messages the bot. A compact header is posted into the
    |     support group and the contact's message is copied beneath it.
    |   - An agent replies to one of those group messages; the reply is copied
    |     straight back into the contact's private chat.
    |   - "/close" (sent as a group reply) closes the ticket and notifies the
    |     contact.
    |
    | This mirrors the bespoke controller most apps end up writing by hand. It is
    | purely additive: the forum-topic flow and web-chat widget are untouched.
    |
    |   bot - which configured bot (its chat_id is the support group) the bridge
    |         routes through. Defaults to the "support" bot.
    |
    */
    'support_bridge' => [
        'enabled' => (bool) env('TELEGRAM_SYSTEM_SUPPORT_BRIDGE_ENABLED', true),
        'bot' => env('TELEGRAM_SYSTEM_SUPPORT_BRIDGE_BOT', 'support'),

        /*
         | Outbound copy-thread parse mode applied to the header / system notes
         | the bridge sends (the contact's own messages are copied verbatim).
         */
        'parse_mode' => env('TELEGRAM_SYSTEM_SUPPORT_BRIDGE_PARSE_MODE', 'HTML'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Message templates
    |--------------------------------------------------------------------------
    |
    | Templates used by the DM support bridge. Override any of them (e.g. to
    | translate, or to drop your brand in) without touching code. Placeholders:
    |
    |   :ticket - the human-friendly ticket reference (e.g. T-7F3A9C)
    |   :name   - the contact's display name
    |   :user   - "@username" when known, otherwise the bold display name
    |
    */
    'messages' => [
        'welcome' => env('TELEGRAM_SYSTEM_MSG_WELCOME',
            "👋 <b>Welcome to support!</b>\n\nSend us your question or describe your issue and our team will get back to you shortly."),
        'received' => env('TELEGRAM_SYSTEM_MSG_RECEIVED',
            "✅ Message received! Our team will get back to you soon.\n<i>Reference: :ticket</i>"),
        'closed' => env('TELEGRAM_SYSTEM_MSG_CLOSED',
            "🔒 Your request <i>:ticket</i> has been resolved and closed.\nThank you for contacting us!"),
        'header' => env('TELEGRAM_SYSTEM_MSG_HEADER',
            "🎫 <b>Ticket :ticket</b>\n👤 :user\n─────────────────"),
        'group_closed' => env('TELEGRAM_SYSTEM_MSG_GROUP_CLOSED',
            '✅ Ticket :ticket closed.'),
        'already_closed' => env('TELEGRAM_SYSTEM_MSG_ALREADY_CLOSED',
            '⚠️ This ticket is already closed.'),
        'reply_to_closed' => env('TELEGRAM_SYSTEM_MSG_REPLY_TO_CLOSED',
            '⚠️ This ticket is closed. Nothing was sent.'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'table' => env('TELEGRAM_SYSTEM_TABLE', 'telegramsystem_tickets'),
    'messages_table' => env('TELEGRAM_SYSTEM_MESSAGES_TABLE', 'telegramsystem_ticket_messages'),
];
