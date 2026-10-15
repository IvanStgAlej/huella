# Huella

🇪🇸 [Versión en español](README.es.md)

A multi-tenant web platform for digital memorial pages, built with PHP 8, MySQL and vanilla JavaScript as a **portfolio project and technical demonstrator**.

Each memorial ("huella") shows a biography, a list of events with RSVP, and a condolence wall where visitors can leave messages with photos, videos, audio or links. Every organisation using the platform gets its own visual theme and language, and a TV mode turns the wall into a slideshow for public screens.

> [!NOTE]
> **About this project.** Huella was developed as a learning exercise and a technical demonstration. All data, processes and entities included in this repository are fictitious and do not represent third-party systems or information. It is not a commercial product: it runs locally on XAMPP and is not deployed.

![Memorial page, events, condolence wall and the same platform themed for a second organisation in Catalan](docs/screenshots/banner.png)

## Features

- **Multi-tenant:** tenant → company → branch hierarchy, with a visual theme per tenant (CSS variables editable from a style panel).
- **4 languages:** Spanish, Catalan, English and Basque (`config/lang/*.json`).
- **Events** (configurable event types, places and times) with attendance confirmation.
- **Condolence wall** with images, videos, audio and links, likes, replies, moderation and a profanity filter.
- **Roles:** owner, admin, collaborator, client, guest and applicant.
- **TV mode** (`tv.php`): slideshow of wall messages for public screens.
- **AI features:**
  - A biography assistant with voice input (Whisper speech-to-text and Llama 3.3, both through Groq).
  - Thank-you messages generated from the wall's messages.
- **Clean URLs** with Apache `mod_rewrite`, for example `/huella/{slug}/muro`.

## Screenshots

All data shown is fictitious demo data (`database/seed.sql`).

| Memorial | Events | Condolence wall |
|---|---|---|
| ![Memorial page](docs/screenshots/memorial.png) | ![Events with RSVP](docs/screenshots/events.png) | ![Condolence wall](docs/screenshots/wall.png) |

| Second tenant: own theme, in Catalan | Admin area | AI biography assistant |
|---|---|---|
| ![Memorial in Catalan with a blue theme](docs/screenshots/memorial-catalan.png) | ![Admin area](docs/screenshots/admin.png) | ![AI biography assistant](docs/screenshots/ai-biography.png) |

**TV mode** for public screens: a slideshow of the wall messages.

![TV mode](docs/screenshots/tv-mode.gif)

**Style panel**, where each tenant gets its own colours, fonts and logo, with a live preview:

![Style panel](docs/screenshots/style-panel.png)

## How it was built

I designed the **data model** (multi-tenant hierarchy, roles and permissions, events, wall content and media), the **architecture** (a `public/` web root, JSON endpoints, shared includes and per-tenant theming through CSS variables) and the **functional flows** (memorial editing, RSVP, wall moderation, TV slideshow, AI-assisted biography).

I used **generative AI as a development accelerator**, with a spec-driven workflow: each feature started as a written design spec and an implementation plan, which I then implemented and reviewed with an AI coding agent ([Claude Code](https://claude.com/claude-code)). You can see examples in [`docs/design/`](docs/design/). The AI did not replace the design work. It sped up the implementation, and I kept the responsibility for the decisions, the review and the testing.

AI is also part of the product itself: the biography assistant turns short written or spoken answers into a draft biography (Whisper speech-to-text and Llama 3.3 through the Groq API).

## Tech stack

PHP 8 (strict types, prepared statements) · MySQL / MariaDB · vanilla JS · Apache · Groq API (Llama 3.3, Whisper) · Gemini / Anthropic (optional)

## Getting started (XAMPP)

1. Clone the repo into `htdocs/huella`. The routes currently assume the `/huella/` base path.
2. Create the database and load the schema and reference data:
   ```bash
   mysql -u root -e "CREATE DATABASE huellas CHARACTER SET utf8mb4"
   mysql -u root huellas < database/schema.sql
   mysql -u root huellas < database/reference_data.sql
   mysql -u root huellas < database/seed.sql      # optional: fictitious demo data
   ```
   The demo creates two fictitious organisations and two memorials:
   - `http://localhost/huella/carmen-ruiz-navarro`, in Spanish with a warm theme.
   - `http://localhost/huella/josep-vidal-soler`, in Catalan with a blue theme.

   Admin login for both: **`demo@huella.test` / `demo1234`**. Demo images live in `public/uploads/demo/`.
3. Database credentials default to XAMPP's (`root` with no password). To change them, set the `DB_HOST`, `DB_USER`, `DB_PASS` and `DB_NAME` environment variables.
4. AI keys (optional). Without them, the AI features return a "not configured" message.
   - **Groq:** set `GROQ_API_KEY`, or copy `config/groq.example.php` to `config/groq.php` and add your key. That file is git-ignored.
   - **Gemini / Anthropic:** set `GEMINI_API_KEY` / `ANTHROPIC_API_KEY`. Never put real keys in `config/gemini.php` or `config/ia.php`, because those files are tracked.
5. Open `http://localhost/huella/`.

## Project structure

Only `public/` is reachable from the browser: the root `.htaccess` forwards every request there, so application code, configuration and SQL files can never be downloaded or executed directly.

```
huella/
├── .htaccess              forwards all requests to public/
├── public/                web root
│   ├── .htaccess          clean URLs: /{slug}, /{slug}/actos, /{slug}/muro…
│   ├── index.php          memorial home page
│   ├── actos.php          events and RSVP
│   ├── muro.php           condolence wall
│   ├── completar.php      memorial editor (admins)
│   ├── bio_asistente.php  AI biography assistant
│   ├── tv.php             TV mode for public screens
│   ├── panel_estilos.php  per-tenant theme editor
│   ├── admin_usuarios.php user and role management
│   ├── login.php · logout.php · politicas.php · lanzador.php (dev launcher)
│   ├── api/               JSON endpoints: auth, muro, asistencia, admin, panel_estilos, bio_asistente
│   ├── assets/            CSS and images
│   └── uploads/           user content (git-ignored except demo/; script execution disabled)
├── includes/              shared functions, session guard, uploads, AI clients (groq, gemini, ia)
├── config/                DB connection, languages, theme, word filters, AI config
├── database/              schema.sql, reference_data.sql and seed.sql (demo data)
└── docs/                  design/ (specs and plans) and screenshots/
```

## Known limitations / roadmap

- The `/huella/` base path is hard-coded in several places.
- No CSRF protection yet.
- `lanzador.php` is a development launcher that lists every memorial; remove or protect it before deploying.

## License

© 2026 Iván Santiago Alejano. Licensed under the [PolyForm Noncommercial License 1.0.0](LICENSE).

You may read, study, run and modify the code for **non-commercial purposes** (personal learning, research, education, evaluation). **Any commercial use is not permitted** without my prior written permission. For commercial licensing, contact me through my [GitHub profile](https://github.com/IvanStgAlej).
