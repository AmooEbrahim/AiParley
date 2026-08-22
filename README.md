# 🕊️ AiParley

A local, single-user Laravel app where multiple AI agents talk to each other in a group chat.
Create agents, group them into chats, then let them auto-converse up to a message budget or advance one message at a time. Messages, tokens, cost and latency are stored per message.

**MVP scope:** no auth, no streaming, no images — text only.

## Requirements

- PHP 8.3+ (8.5 recommended), Composer
- Node.js + npm
- MariaDB (or MySQL)

## Setup

```bash
composer install
npm install

cp .env.example .env      # then edit DB_* if your credentials differ
php artisan key:generate
php artisan migrate
```

Create the database first:

```sql
CREATE DATABASE ai_parley CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Default `.env` DB settings: `root` / `password` / `ai_parley` on `127.0.0.1:3306`, `QUEUE_CONNECTION=database`.

## Running

The app needs a web server, the Vite dev server (or a built manifest), and a queue worker:

```bash
composer dev        # runs all three via `php artisan dev`
```

or manually:

```bash
php artisan serve           # http://127.0.0.1:8000
npm run dev                 # Tailwind + Alpine via Vite (or: npm run build once)
php artisan queue:work      # REQUIRED — turns are processed by the queue
```

For tests (uses the `ai_parley_test` database, created automatically migrations-wise):

```bash
mysql -uroot -ppassword -e 'CREATE DATABASE IF NOT EXISTS ai_parley_test'
php artisan test
```

## How it works

- **Agents** — OpenAI-compatible endpoints (`base_url` + `api_key` + `model`) with a color, optional default system prompt, prices per 1M tokens, and a timeout.
- **Chats** — group agents (ordered positions, per-chat prompts). Round-robin turn taking: one in-flight turn per chat (`WithoutOverlapping` lock on `chat:{id}`).
- **Prompt composition** — system prompt = `global prompt → agent default prompt → chat-specific prompt` (empty parts skipped, stored in the `settings` table, editable at `/settings`).
- **Context per perspective** — each agent sees its own past messages as `assistant` and everyone else's as `user` with a `[Name]: ` prefix. History is append-only for prompt-cache friendliness.
- **Auto-run** — `Start` sets `message_limit = current + auto_budget`, dispatches `ProcessTurnJob`, which re-dispatches itself with a short delay until the budget is reached or `Stop` is pressed.
- **Manual mode** — `Next message ▶` dispatches a single manual turn (chat stays `idle`).
- **Failures** — `tries = 3`, backoff `5/15/30s`; on final failure the chat becomes `error` with the failing agent + message, and a *Reset to idle* button appears.
- **Frontend** — Blade + Tailwind + Alpine, polls `/api/chats/{id}/messages?after={lastId}` every 10s (pauses when the tab is hidden).
- **Swapping/removing agents mid-chat** — the old pivot is deactivated (`active=false`); the replacement gets a new pivot, and the previous agent's messages stay in history as `user`-role context.
- **Agent deletion** — soft delete; agents remain visible in existing chats' history.

## Roadmap

See `documents/roadmaps/` for the MVP roadmap and future phases.
