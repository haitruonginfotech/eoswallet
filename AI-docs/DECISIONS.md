# Decisions

| # | Decision | Why |
|---|---|---|
| 1 | Generic plugin, not tied to the mklogistics theme | User choice. Site-specific tools go in the child theme via `wpmcp_register_tools`. |
| 2 | Dedicated MCP server (`wp-mcp-tools`) instead of only the adapter default server | User choice. Each ability is a first-class MCP tool (like emcp-tools); the default server would hide them behind discover/execute meta-tools. `expose_default_server` setting exists as an opt-in. |
| 3 | Theme writes allowed on both parent and child (active theme pair only) | User choice ("cả main + child"). Mitigated by backup, lint, loopback rollback, `edit_themes` cap, `DISALLOW_FILE_EDIT`. |
| 4 | SCF/ACF tools included although not ticked in the scope question | The original request explicitly listed SCF/ACF and the theme relies on SCF for all page content. The group can be switched off in settings. |
| 5 | Name-keyed "raw" value format for fields | Readable for agents, round-trips into `update_field()`, and avoids huge formatted image arrays. `mode=formatted` is available when needed. |
| 6 | Repeater/flexible = full replace, group = merge | ACF only writes the sub fields present in a row. Without filling them, omitted sub fields silently kept old values (seen in testing). |
| 7 | PHP lint via `token_get_all(TOKEN_PARSE)` rather than `php -l` | `exec`/PHP binary is unreliable on hosts (and Windows Local). This parses without executing. |
| 8 | Loopback check + auto-rollback after PHP writes | Syntax-valid code can still fatal (undefined function). Tested: an injected undefined call → HTTP 500 → restored. |
| 9 | Settings stored in one option `wpmcp_settings`; it is in the protected-options list | Prevents the MCP client from re-enabling disabled groups on its own. |
| 10 | No output_schema on abilities | Core validates output against it; loose/mixed outputs would turn successful calls into errors. |

## Failed approaches / gotchas (do not repeat)
- A GitHub clone of `mcp-adapter` without `vendor/autoload_packages.php` → the plugin shows as "active" but registers nothing (no `/mcp` routes). Run composer with the jetpack-autoloader plugin allowed.
- The single-worker `php -S` deadlocks on loopback checks. Use `PHP_CLI_SERVER_WORKERS`.
- `acf_get_value('option', …)` returns empty. The id must be normalised to `options`.
- Firing `wpmcp_register_tools` on `plugins_loaded` is too early for themes. It now runs on `after_setup_theme`.

## v0.2 — OAuth
| # | Decision | Why |
|---|---|---|
| 11 | Built-in OAuth 2.1 server (DCR + PKCE + refresh rotation), no external library | The user wants EMCP-style "sign in and approve". Claude.ai, ChatGPT, Claude Code and mcp-remote all expect MCP-spec discovery + DCR. A small, dependency-free implementation keeps the plugin self-contained. |
| 12 | Issuer = public base URL (site root); endpoints under `/wpmcp-oauth/*` served at `init` | They work with Apache/nginx WP rewrites without adding rewrite rules. `/.well-known/*` is reached through the same front controller. |
| 13 | Tokens are audience-bound to the MCP route only | A leaked MCP token cannot be used against the rest of the REST API (tested with `/wp/v2/users/me`). |
| 14 | Store only SHA-256 hashes of codes, tokens and client secrets | Database leaks do not expose usable credentials. |
| 15 | Transport permission returns `false` (not WP_Error) when logged out | The adapter logged an ERROR for every discovery probe. The OAuth layer adds the 401 + header instead. |
| 16 | Admin moved from Settings submenu to a top-level "MCP Tools" menu with Connection/Tools tabs | Mirrors the EMCP Tools UX the user referenced. |
| 18 | Master switch `enabled` gates the ability registration, `create_server` and OAuth (`WPMCP_OAuth_Server::enabled()` requires it) | One click fully closes the AI entry point (like EMCP's "Turn MCP off"). Tokens are kept so turning it back on does not force clients to re-authorize. |
| 17 | App password generated from the Connection page and shown once (5-minute transient) | Matches "Generate a password and paste into client config". The plain password is never stored beyond the one-time display. |

## v0.3 — Local file uploads

| # | Decision | Why |
|---|---|---|
| 19 | Upload link (secret-token URL on the front end, `?wpmcp_upload=<token>`) instead of more base64 | Base64 inside a tool call costs about 1.33× the file size in tokens and hits message limits (15 banner images for mklogistics were impossible). A link lets browser automation on the user's computer send the bytes directly over HTTP. |
| 20 | The token is the credential: 32 random bytes, only the SHA-256 hash is stored (transient), 5–60 min expiry, max files, closed after the first submit by default | No password or cookie is needed in the automated browser. A leaked link has a small blast radius (uploads only, same file-type rules, short-lived). |
| 21 | Query-var route on `init` (not REST, not a rewrite rule) | Works with plain permalinks and needs no rewrite flush. It is also not affected by WPS Hide Login. |
| 22 | Files are saved with `wp_set_current_user(session user)` + `media_handle_sideload` | Same capability and mime checks as a normal admin upload. The attachment author is the session creator. |
| 23 | The chunked base64 tool is kept as a fallback | Some clients have no browser or HTTP tool. It is fine for icons and logos. |
