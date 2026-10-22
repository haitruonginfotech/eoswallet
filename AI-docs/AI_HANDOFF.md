# AI HANDOFF — WP MCP Tools

## Project Overview
`wp-mcp-tools` is a generic WordPress plugin by Hai Truong IT. It registers Abilities API abilities and exposes them as a
dedicated MCP server via the official `mcp-adapter` plugin. It is a non-Elementor alternative to `emcp-tools`
and covers content, SCF/ACF values, media, menus, options and theme files. Clients connect with **OAuth 2.1**
(like EMCP Tools) or an **Application Password**.
It was first installed on the Local site `mklogistics.com.sg` (`D:\localwp\mklogistics.com.sg\app\public\wp-content\plugins\wp-mcp-tools`).

## Current Status
v0.3.1 (2026-10-14): admin UI redesign + new icon, no tool/API changes. 50 tools.
Installed on https://mylivorahome.sg (see "v0.3.1" below). Zip archived at `D:\wordpress\AI\wp-mcp-tools-0.3.1.zip`.
v0.3.0: 50 tools. v0.2.2 runs on the live site https://mklogistics.sg (connected, used for the 2026-09-28 client feedback work).
v0.3.0 adds local-file uploads (upload link + chunked base64). It is tested in the sandbox (WP 7.1.2, PHP 8.4, MCP Adapter 0.6.1, SCF 6.9.5).
**v0.3.0 is NOT yet installed on live.** The user updates the plugin from the zip.

## Current Task
None in progress.

## Completed
- v0.1: tools (see README), registry, dedicated server `/wp-json/wp-mcp-tools/mcp`, theme-file safety.
- v0.2: OAuth 2.1 authorization server (`includes/oauth/`):
  - discovery (RFC 9728 PRM + RFC 8414 AS metadata), 401 + `WWW-Authenticate resource_metadata`;
  - dynamic client registration (public and confidential clients);
  - authorize with WP login and a consent page, PKCE S256 required;
  - token endpoint with code exchange and refresh rotation; revocation.
  Tokens are bound to the MCP route (audience).
- v0.2: new admin menu **MCP Tools** (top level) with two tabs:
  - *Connection*: status pills, OAuth toggle, Server URL override, access capability, auth method cards, Application Password generator (shown once), Connected apps (Sign out / Remove), client picker (Claude Desktop, Claude.ai, Claude Code, Cursor, VS Code, ChatGPT, npx mcp-remote) with per-method steps and copy buttons, OAuth debug endpoints;
  - *Tools*: group toggles and the tool list.
- Verified by `oauth_test.py` (27 checks), including:
  - DCR; the loopback redirect port may differ (RFC 8252); deny/approve; state and iss;
  - bad PKCE; code replay; bearer MCP calls; audience binding (`/wp/v2/users/me` rejects the token); `invalid_token`;
  - refresh rotation invalidates the old tokens; revoke; `client_secret_basic`; unregistered `redirect_uri` shows an error page instead of redirecting;
  - OAuth disabled → 404 metadata and no `WWW-Authenticate`; the `public_url` override applies to metadata and the header;
  - Basic auth with a generated app password still works; admin UI rendered and clicked with Playwright.

- v0.2.1: master switch `enabled` plus an admin-bar **MCP** menu (front end and admin, `manage_options` only).
  - The menu shows a coloured dot (green = on, amber = on but the Abilities API or MCP Adapter is missing, grey = off), plus Abilities API, MCP Adapter, MCP exposure, Active tools, OAuth, a "Turn MCP on/off" link (admin-post `wpmcp_toggle` with nonce, confirmation before turning off) and "Connection settings →".
  - Off means no abilities, no MCP route (404) and no OAuth endpoints (404). Existing tokens stay stored and work again when MCP is turned back on.
  - Tested with Playwright: toggling off then on; MCP 404 → 401; `.well-known` 404; confirm dialog; the OAuth suite passes afterwards.

- 2026-09-28: **live check on https://mklogistics.sg** (production, WP 7.1.2, PHP 8.2, Wordfence, WPS Hide Login, gce2fa) with v0.2.1. The client was connected by the user (47 tools); site info reports `current_user` = `ad_mkl`.
  - OK: get-site-info, list-posts (12 pages), list-menus (3 menus / 3 locations), list-field-groups (front page + options groups, source db), get-fields raw (group with nested repeater, options repeater), read/list theme files, page templates, media.
  - Write test on a temporary draft page (id 424): create, update-fields (SEO), replace-in-post, delete with force. All OK and cleaned up. Theme writes were NOT tested on live on purpose.
  - Bug found: `list-field-groups` → `fields` came back as a JSON object when tab fields were filtered out (gaps in the keys). Fixed in v0.2.2 with `array_values`. The live site still runs v0.2.1 until the plugin is updated there.
  - Note: `get-fields` on `option` returns secrets stored in SCF fields (e.g. `recaptcha_secret_key`) to the connected admin. Consider masking (TODO).

- v0.3.0 (2026-09-28): `create-upload-session`, `get-upload-session`, `upload-media-chunk` (see README "Uploading local files").
  - Sandbox test `up_test.py`, 12 checks, all passed:
    - the form renders; a multipart POST adds 2 files and rejects `.php`; the link closes after the first submit and a reuse returns 410;
    - a bad token returns 404; an expired link returns 410; MCP off returns 404;
    - `max_files` is enforced; an HTML submit with `multiple_submits` keeps the form open;
    - a chunked upload (4 parts) sets width and alt correctly; chunked rejects `.php`; a finished `upload_id` is gone.
  - Playwright `setInputFiles` + clicking Upload works.
  - The OAuth suite still passes.
  - Intended flow on mklogistics: Claude calls `create-upload-session`, then device Playwright (`browser_run_code_unsafe`: `page.goto(upload_url)`, `setInputFiles` with `D:/...` paths, click `#wpmcp-submit`), then `get-upload-session` returns the IDs.

- v0.3.1 (2026-10-14): **admin UI + icon redesign** (request: "icon xấu, UX/UI lại cho đẹp hơn").
  - New icon: a plug with a spark cut-out (original glyph, `WPMCP_Admin::ICON_PATH`, 20×20, one evenodd path). Used as the admin-menu icon (base64 SVG data URI with `fill="black"` so WP's svg-painter recolours it), in the page header tile and in the admin-bar **MCP** item (icon + label + status dot; on mobile the label hides and the icon is 26px). `assets/icon.svg` is a coloured copy for docs/marketing.
  - Admin screen rebuilt (`includes/class-admin.php` render methods; inline `styles()`/`script()` removed → `assets/admin.css` + `assets/admin.js`, enqueued only on `toplevel_page_wp-mcp-tools`). Teal accent (#0f766e) on neutral greys. Header card (icon, version, "Online / Needs attention / Off" state, Save button using `form="wpmcp-settings"`), segmented tabs (Tools tab shows the active-tool count), 4 status cards, endpoint row with copy, Settings panel with switch rows (same field names as before), auth-method cards, empty state for Connected apps, client chips with monograms + numbered steps, collapsible debug table. Tools tab: group cards with switches + counts, tool list with search box and group filter chips; tools of disabled groups are dimmed.
  - New: "Settings saved." notice after saving (options.php does not show it on a custom top-level page) — flag transient `wpmcp_saved_<user>` set on `update_option_/add_option_wpmcp_settings` when `option_page=wpmcp`. "You have unsaved changes" hint in the panel footers. Remove-app confirm moved to `data-wpmcp-confirm`.
  - Tested on a scratch WP (WP 7.2-alpha + SQLite, MCP Adapter 0.6.1) with Playwright at 1440 and 390: no JS errors, no horizontal scroll; auth-method switch, client tabs, copy, save from the header (OAuth off + Server URL kept, then restored), Tools tab search/filter/empty state, group toggle save keeps the Connection settings (hidden inputs) and vice versa.
  - Settings option and all OAuth/MCP behaviour are unchanged — 0.3.1 is a drop-in replacement for 0.3.0.

## Pending
- TODO: after the user installs v0.3.0 on live, run one real upload-link test. **UNVERIFIED:** whether Wordfence or the host limits front-end multipart POSTs, and the live `upload_max_filesize`.
- TODO: activate on the mklogistics Local site. Test with a real client, e.g. Claude Code `claude mcp add --transport http …` + `/mcp` → Authenticate, or `npx mcp-remote`.
- UNVERIFIED: claude.ai / ChatGPT OAuth against a public HTTPS deployment (the sandbox cannot be reached from their cloud).
- TODO (ideas): field-group editing for DB-stored SCF groups; users tools; block-level editing helpers; `get-page-html`; PHPUnit tests; per-client scope or tool restrictions.

## Known Issues
- `update-fields` reports `saved: true` even when the value is unchanged. The values read back are the source of truth.
- Loopback verification after PHP writes only checks the home page. If the loopback fails, the check is `skipped` and nothing is rolled back.
- A fatal error during verification may trigger WP recovery-mode email (on Local it goes to Mailpit).
- Backups are in `wp-content/wpmcp-backups/` and protected only by `.htaccess` (Apache). On nginx, block the path manually.
- Backup ids flatten `/` to `__`, so file names that contain `__` restore to the wrong path.
- OAuth needs pretty permalinks: `/.well-known/*` and `/wpmcp-oauth/*` are served by WP's front controller at `init`. With plain permalinks the endpoints fall back to `?wpmcp_oauth=…`, but AS-metadata discovery at the issuer will fail.
- If WordPress runs in a sub-directory, the host-root `/.well-known/…` URL does not reach WP.
- A public URL (tunnel) that differs from `home_url` breaks the login cookie flow of the consent page unless the tunnel serves the same host WP thinks it is.
- Dynamic registration is open (standard for MCP). It is capped at 100 clients; the oldest client without tokens is evicted.

## Architecture
See `ARCHITECTURE.md`.

## Important Files
- `wp-mcp-tools.php`: constants, requires, activation hook (creates the OAuth table), deactivation hook (clears the purge cron).
- `includes/class-plugin.php`: modules, Abilities hooks, `create_server()`. The transport permission returns `false` when logged out, so there is no log noise and the OAuth server adds 401 + `WWW-Authenticate`.
- `includes/class-registry.php`: declares tools and builds abilities.
- `includes/class-settings.php`: option `wpmcp_settings` (groups, endpoint_capability, oauth_enabled, public_url, auth_method, expose_default_server) and sanitize.
- `includes/class-admin-bar.php`: admin-bar MCP status menu + on/off toggle.
- `includes/class-admin.php`: admin UI and admin-post actions (`wpmcp_oauth_signout`, `wpmcp_oauth_remove`, `wpmcp_app_password`).
- `includes/oauth/class-oauth-store.php`: clients in option `wpmcp_oauth_clients`; codes/tokens (SHA-256 hashes) in table `{prefix}wpmcp_oauth_tokens`; daily purge cron `wpmcp_oauth_purge`.
- `includes/oauth/class-oauth-server.php`: router, metadata, register/authorize/token/revoke, consent page, Bearer auth (`determine_current_user` priority 30), and the `rest_post_dispatch` header.
- `includes/abilities/class-*.php`: one module per tool group.

## Technical Decisions
See `DECISIONS.md`.

## Environment
- WordPress ≥ 6.9. The site runs WP 7.1.2 with `WP_ENVIRONMENT_TYPE=local` on Local (Apache + fcgid). Its `.htaccess` already passes `HTTP_AUTHORIZATION` and permalinks are pretty.
- `mcp-adapter` 0.6.1 must be active, with `vendor/autoload_packages.php` present.

## How To Run
Activate MCP Adapter, then WP MCP Tools. Open **MCP Tools → Connection**, choose OAuth or Application password, pick a client and copy the config.

## How To Test
Sandbox recipe:
1. WP 7.1.2 from GitHub tag `7.1.2` with MariaDB.
2. `mcp-adapter` v0.6.1 + `COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev` (allow the jetpack-autoloader plugin); SCF from GitHub + `composer install`.
3. `wp rewrite structure '/%postname%/'`.
4. Start `PHP_CLI_SERVER_WORKERS=4 php -S localhost:8080`.
5. Tools: JSON-RPC with Basic auth (`initialize` → `Mcp-Session-Id` → `tools/call`).
6. OAuth: a Python client performs discovery → DCR → wp-login → consent → token → bearer MCP → refresh → revoke. It lives in the sandbox as `oauth_test.py` and is not shipped with the plugin.

## Next Steps
1. On mklogistics: activate, open MCP Tools → Connection, confirm 47 tools and the OAuth pill.
2. Connect Claude Code (OAuth) or Claude Desktop via `npx mcp-remote` (Option B), approve, then call `wpmcp-get-site-info`.
3. Check that `wpmcp-list-field-groups` with a page ID returns the theme's SCF groups.
