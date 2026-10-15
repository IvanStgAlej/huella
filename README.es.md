# Huella

🇬🇧 [English version](README.md)

Plataforma web multitenant de páginas memoriales digitales, desarrollada con PHP 8, MySQL y JavaScript sin frameworks como **proyecto de portfolio y demostrador técnico**.

Cada memorial ("huella") muestra una biografía, una lista de eventos con confirmación de asistencia y un muro de condolencias donde los visitantes pueden dejar mensajes con fotos, vídeos, audios o enlaces. Cada organización que usa la plataforma tiene su propio tema visual e idioma, y el modo TV convierte el muro en un pase de diapositivas para pantallas públicas.

> [!NOTE]
> **Sobre este proyecto.** Proyecto desarrollado como ejercicio de aprendizaje y demostración técnica. Los datos, procesos y entidades incluidos son ficticios y no representan sistemas ni información de terceros. No es un producto comercial: funciona en local con XAMPP y no está desplegado.

![Página memorial, eventos, muro de condolencias y la misma plataforma con el tema de una segunda organización, en catalán](docs/screenshots/banner.png)

## Funcionalidades

- **Multitenant:** jerarquía de tenant → empresa → delegación, con un tema visual por tenant (variables CSS editables desde un panel de estilos).
- **4 idiomas:** castellano, catalán, inglés y euskera (`config/lang/*.json`).
- **Eventos** (tipos, lugares y horarios configurables) con confirmación de asistencia.
- **Muro de condolencias** con imágenes, vídeos, audios y enlaces, «me gusta», respuestas, moderación y filtro de palabras malsonantes.
- **Roles:** propietario, administrador, colaborador, cliente, invitado y solicitante.
- **Modo TV** (`tv.php`): pase de diapositivas con los mensajes del muro para pantallas públicas.
- **Funciones con IA:**
  - Un asistente de biografías con entrada por voz (transcripción con Whisper y Llama 3.3, ambos a través de Groq).
  - Mensajes de agradecimiento generados a partir de los mensajes del muro.
- **URLs limpias** con `mod_rewrite` de Apache, por ejemplo `/huella/{slug}/muro`.

## Capturas

Todos los datos que aparecen son ficticios (`database/seed.sql`).

| Memorial | Eventos | Muro de condolencias |
|---|---|---|
| ![Página memorial](docs/screenshots/memorial.png) | ![Eventos con confirmación de asistencia](docs/screenshots/events.png) | ![Muro de condolencias](docs/screenshots/wall.png) |

| Segundo tenant: tema propio, en catalán | Área de administración | Asistente de biografías con IA |
|---|---|---|
| ![Memorial en catalán con tema azul](docs/screenshots/memorial-catalan.png) | ![Área de administración](docs/screenshots/admin.png) | ![Asistente de biografías con IA](docs/screenshots/ai-biography.png) |

**Modo TV** para pantallas públicas: un pase de diapositivas con los mensajes del muro.

![Modo TV](docs/screenshots/tv-mode.gif)

**Panel de estilos**, donde cada tenant define sus colores, tipografías y logo, con vista previa en directo:

![Panel de estilos](docs/screenshots/style-panel.png)

## Cómo se ha construido

He diseñado el **modelo de datos** (jerarquía multitenant, roles y permisos, eventos, contenido y archivos del muro), la **arquitectura** (`public/` como raíz web, endpoints JSON, código compartido en `includes/` y temas por tenant con variables CSS) y los **flujos funcionales** (edición del memorial, confirmación de asistencia, moderación del muro, pase de diapositivas de la TV y biografía asistida por IA).

He usado la **IA generativa como acelerador del desarrollo**, con un flujo dirigido por especificaciones: cada funcionalidad empezaba con una especificación de diseño y un plan de implementación escritos, que después implementaba y revisaba con un agente de programación con IA ([Claude Code](https://claude.com/claude-code)). Hay ejemplos en [`docs/design/`](docs/design/). La IA no sustituyó el trabajo de diseño: aceleró la implementación, y las decisiones, la revisión y las pruebas siguieron siendo responsabilidad mía.

La IA también forma parte del propio producto: el asistente de biografías convierte respuestas breves, escritas o habladas, en un borrador de biografía (Whisper y Llama 3.3 a través de la API de Groq).

## Tecnologías

PHP 8 (tipos estrictos, sentencias preparadas) · MySQL / MariaDB · JavaScript sin frameworks · Apache · API de Groq (Llama 3.3, Whisper) · Gemini / Anthropic (opcionales)

## Puesta en marcha (XAMPP)

1. Clona el repositorio en `htdocs/huella`. Por ahora las rutas dan por hecho la ruta base `/huella/`.
2. Crea la base de datos y carga el esquema y los datos de referencia:
   ```bash
   mysql -u root -e "CREATE DATABASE huellas CHARACTER SET utf8mb4"
   mysql -u root huellas < database/schema.sql
   mysql -u root huellas < database/reference_data.sql
   mysql -u root huellas < database/seed.sql      # opcional: datos de demo ficticios
   ```
   La demo crea dos organizaciones ficticias y dos memoriales:
   - `http://localhost/huella/carmen-ruiz-navarro`, en castellano y con tema cálido.
   - `http://localhost/huella/josep-vidal-soler`, en catalán y con tema azul.

   Acceso de administrador en los dos: **`demo@huella.test` / `demo1234`**. Las imágenes de demo están en `public/uploads/demo/`.
3. Por defecto se usan las credenciales de base de datos de XAMPP (`root` sin contraseña). Para cambiarlas, define las variables de entorno `DB_HOST`, `DB_USER`, `DB_PASS` y `DB_NAME`.
4. Claves de IA (opcionales). Sin ellas, las funciones de IA responden con un aviso de "no configurado".
   - **Groq:** define `GROQ_API_KEY`, o copia `config/groq.example.php` como `config/groq.php` y añade tu clave. Ese archivo está en `.gitignore`.
   - **Gemini / Anthropic:** define `GEMINI_API_KEY` / `ANTHROPIC_API_KEY`. No pongas nunca claves reales en `config/gemini.php` ni en `config/ia.php`, porque esos archivos sí se suben al repositorio.
5. Abre `http://localhost/huella/`.

## Estructura del proyecto

Solo `public/` es accesible desde el navegador: el `.htaccess` de la raíz reenvía ahí todas las peticiones, así que el código de la aplicación, la configuración y los archivos SQL no se pueden descargar ni ejecutar directamente.

```
huella/
├── .htaccess              reenvía todas las peticiones a public/
├── public/                raíz web
│   ├── .htaccess          URLs limpias: /{slug}, /{slug}/actos, /{slug}/muro…
│   ├── index.php          página principal del memorial
│   ├── actos.php          eventos y confirmación de asistencia
│   ├── muro.php           muro de condolencias
│   ├── completar.php      editor del memorial (administradores)
│   ├── bio_asistente.php  asistente de biografías con IA
│   ├── tv.php             modo TV para pantallas públicas
│   ├── panel_estilos.php  editor de temas por tenant
│   ├── admin_usuarios.php gestión de usuarios y roles
│   ├── login.php · logout.php · politicas.php · lanzador.php (lanzador de desarrollo)
│   ├── api/               endpoints JSON: auth, muro, asistencia, admin, panel_estilos, bio_asistente
│   ├── assets/            CSS e imágenes
│   └── uploads/           contenido subido por los usuarios (fuera de git salvo demo/; sin ejecución de scripts)
├── includes/              funciones compartidas, control de sesión, subidas y clientes de IA (groq, gemini, ia)
├── config/                conexión a la BD, idiomas, tema, filtros de palabras y configuración de IA
├── database/              schema.sql, reference_data.sql y seed.sql (datos de demo)
└── docs/                  design/ (especificaciones y planes) y screenshots/
```

## Limitaciones conocidas / próximos pasos

- La ruta base `/huella/` está escrita a mano en varios sitios.
- Todavía no hay protección CSRF.
- `lanzador.php` es una herramienta de desarrollo que lista todos los memoriales; hay que quitarla o protegerla antes de desplegar.

## Licencia

© 2026 Iván Santiago Alejano. Distribuido bajo la [PolyForm Noncommercial License 1.0.0](LICENSE).

Puedes leer, estudiar, ejecutar y modificar el código con **fines no comerciales** (aprendizaje personal, investigación, educación o evaluación). **No está permitido ningún uso comercial** sin mi permiso previo por escrito. Para una licencia comercial, contacta conmigo a través de mi [perfil de GitHub](https://github.com/IvanStgAlej).

*El texto legal que tiene validez es el de la licencia original en inglés ([LICENSE](LICENSE)).*
