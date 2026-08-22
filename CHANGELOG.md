# Changelog

All notable changes to X-Files will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [0.1.0] — 2026-08-22

### Added
- **Vault lifecycle** — setup with password, lock, unlock, auto-lock timeout
- **Recovery key** — generated on setup (XFLS-XXXX format), download as .txt, reset password flow
- **Image upload** — multi-file, MIME validation (finfo), configurable size limit, SHA-256 checksum
- **Image storage** — AppData-based (isolated from Files/Photos/Memories/WebDAV/Search)
- **Thumbnail generation** — 256x256 JPEG, generated on upload (GD)
- **Image gallery** — CSS grid, lazy loading, fullscreen viewer (NcModal)
- **Image deletion** — with confirmation, removes file + thumbnail + DB record
- **Image download** — from viewer, with original filename
- **Auto-lock on tab close** — visibilitychange listener locks vault when tab hidden
- **Settings** — configurable timeout (1min–never), max file size (1–500MB), change password
- **Brute-force protection** — `#[BruteForceProtection]` on unlock and recovery endpoints
- **Rate limiting** — `#[UserRateLimit]` on sensitive operations
- **VaultSessionMiddleware** — gates all image endpoints, returns 403 when locked
- **Cross-user isolation** — ownership check on every image operation
- **Keyboard accessibility** — tabindex + Enter on gallery tiles, focus outline
- **Recovery via icon click** — EyeLockOutline icon on lock screen triggers recovery dialog
- **Security PoC** — 12/12 isolation tests passed (see docs/security-poc-results.md)
- **Automated test suite** — 34 API tests (see scripts/test-api.sh)

### Security
- Password hashing: Argon2id (with automatic rehash on algorithm upgrade)
- Session: ISession with CryptoSessionData (encrypted at rest)
- Storage: IAppData (invisible to user filesystem, WebDAV, Photos, Memories, Search)
- MIME validation: finfo (server-side, never trusts client Content-Type)
- Filenames: UUID-based storage names (no path traversal possible)
- CSRF: OCS framework for vault ops, NoCSRFRequired only on GET image endpoints
- Ownership: user_id verified on every DB query

### Technical
- Nextcloud 28–34 compatible, PHP 8.2+
- Database: 2 tables (xfiles_vaults, xfiles_images) via SimpleMigrationStep
- Frontend: Vue 2.7 + @nextcloud/vue 8 + webpack 5
- Architecture: OCSController (vault), Controller (images), QBMapper + Entity
- PHPStan level 5: 0 errors
- Integration verified: coexists with Photos, Memories, Recognize, PreviewGenerator, Activity, Files
