# Changelog

All notable changes to `telegramSystem` will be documented in this file.

## Unreleased

### Added

- **DM support bridge** (`SupportBridge`): a turnkey "DM the bot, talk to a
  human" flow that needs no forum-enabled group. Contact private messages are
  copied into the support group beneath a per-ticket header; agent replies are
  copied straight back to the contact. Includes `/start` welcome, `/close` and
  receipt handling, all driven by configurable `messages` templates. Reachable
  as `TelegramSystem::supportBridge()` and wired into the webhook automatically
  (purely additive — the forum-topic flow and web-chat widget are unchanged).
- `Client::copyMessage()` and `Client::getWebhookInfo()`, plus a
  `MultiBotManager::copyMessage()` passthrough.
- `config('telegramsystem.support_bridge')` and `config('telegramsystem.messages')`.
- `TelegramMessageData` now captures the sender's `first_name` / `last_name`
  (with a `displayName()` helper) and falls back to a media `caption` for `text`.
