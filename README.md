# Huella

Multi-tenant digital memorial platform for funeral homes, built with PHP 8, MySQL and vanilla JavaScript.

Each memorial ("huella") shows the obituary and biography of the deceased, the funeral services with RSVP, and a condolence wall where visitors can leave messages with photos, videos, audio or links. Funeral homes get their own visual theme, and a TV mode displays the wall on the screens of the funeral home.

> **Status:** prototype developed in 2026 and presented to a company in the funeral sector. It runs locally on XAMPP and is not deployed.

## Features

- **Multi-tenant:** client (funeral home) → company → branch, with a visual theme per client (CSS variables editable from a style panel).
- **4 languages:** Spanish, Catalan, English and Basque (`config/lang/*.json`).
- **Funeral services** (wake, ceremony, cremation, burial…) with attendance confirmation.
- **Condolence wall** with images, videos, audio and links, likes, replies, moderation and a profanity filter.
- **Roles:** owner, admin, collaborator, client, guest and applicant.
- **TV mode** (`tv.php`): slideshow of wall messages for the funeral home screens.
- **AI features:**
  - A biography assistant with voice input (Whisper speech-to-text and Llama 3.3, both through Groq).
  - Thank-you messages generated from the wall's messages.
- **Clean URLs** with Apache `mod_rewrite`, for example `/huella/{slug}/muro`.

## Tech stack

PHP 8 (strict types, prepared statements) · MySQL / MariaDB · vanilla JS · Apache · Groq API (Llama 3.3, Whisper) · Gemini / Anthropic (optional)

## Getting started (XAMPP)

1. Clone the repo into `htdocs/huella`. The routes currently assume the `/huella/` base path.
2. Create the database and load the schema and reference data:
   ```bash
   mysql -u root -e "CREATE DATABASE huellas CHARACTER SET utf8mb4"
   mysql -u root huellas < database/schema.sql
   mysql -u root huellas < database/reference_data.sql
   ```
3. Database credentials default to XAMPP's (`root` with no password). To change them, set the `DB_HOST`, `DB_USER`, `DB_PASS` and `DB_NAME` environment variables.
4. AI keys (optional). Without them, the AI features return a "not configured" message.
   - **Groq:** set `GROQ_API_KEY`, or copy `config/groq.example.php` to `config/groq.php` and add your key. That file is git-ignored.
   - **Gemini / Anthropic:** set `GEMINI_API_KEY` / `ANTHROPIC_API_KEY`. Never put real keys in `config/gemini.php` or `config/ia.php`, because those files are tracked.
5. Open `http://localhost/huella/`.

## Project structure

```
├── *.php              pages (index, actos, muro, completar, tv…) and JSON APIs (*_api.php)
├── includes/          shared functions, session guard, uploads, AI clients (groq, gemini, ia)
├── config/            DB connection, languages, theme, word filters, AI config (blocked from the web)
├── assets/            CSS and images
├── database/          schema.sql and reference_data.sql
├── docs/              design specs and implementation plans
└── uploads/           user content (git-ignored)
```

## Known limitations / roadmap

- The `/huella/` base path is hard-coded in several places.
- No CSRF protection yet.
- No demo seed data yet.

## License

[MIT](LICENSE) © 2026 Iván Santiago Alejano
