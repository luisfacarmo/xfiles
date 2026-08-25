# X-Files

[![License: AGPL-3.0](https://img.shields.io/badge/License-AGPL--3.0-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2+-purple.svg)](https://php.net/)
[![Nextcloud](https://img.shields.io/badge/Nextcloud-28--35-0082c9.svg)](https://nextcloud.com/)
[![Vue.js](https://img.shields.io/badge/Vue.js-2.7-4FC08D.svg)](https://vuejs.org/)

> A photo vault for Nextcloud — lock, hide, and protect your most sensitive images.
>
> *"Trust no one — not even your own Nextcloud."*
>
> — Named after the classified cases that nobody was supposed to access.

> [!NOTE]
> X-Files v0.1.0 is the first stable release. App Store publication is pending certificate signing ([PR #1179](https://github.com/nextcloud/app-certificate-requests/pull/1179)).

## What is this?

X-Files is a Nextcloud app that provides a password-protected vault for sensitive photos. Images stored in the vault are completely invisible to Files, Photos, Memories, WebDAV, Search, and any other Nextcloud app — they only exist when you unlock the vault.

Think Google Photos Locked Folder or Samsung Secure Folder, but self-hosted.

## Key Concepts

- **Real isolation** — files stored in AppData, not in your user filesystem
- **Independent password** — vault password is separate from your Nextcloud login
- **Session-based unlock** — auto-locks after timeout, tab close, or manual lock
- **Brute-force protected** — progressive throttling on failed attempts
- **Files integration** — right-click any image in Files → "Send to X-Files" (classify action)
- **Honest security** — protects against apps and sessions, not against server admins (documented clearly)

## Features (v0.1.0)

| Component | Status | Notes |
|-----------|--------|-------|
| Vault lifecycle (create/lock/unlock) | 🟢 Working | Argon2id, session-based |
| Password hashing (Argon2id) | 🟢 Working | + automatic rehash on upgrade |
| Recovery key | 🟢 Working | XFLS-XXXX format, download .txt |
| Image upload (multi-file) | 🟢 Working | MIME validation (finfo), size limit, SHA-256 checksum |
| Drag & drop upload | 🟢 Working | With per-file progress bar |
| Image gallery | 🟢 Working | CSS grid, lazy loading |
| Image viewer (fullscreen) | 🟢 Working | NcModal, prev/next, keyboard arrows |
| Image download | 🟢 Working | Original filename preserved |
| Image deletion | 🟢 Working | File + thumbnail + DB |
| Multi-select + batch delete | 🟢 Working | Inside vault |
| Thumbnail generation | 🟢 Working | 256x256 JPEG on upload (GD) |
| Files integration (Classify) | 🟢 Working | Context menu, single or bulk |
| Safe move | 🟢 Working | Copy → checksum verify → delete original (no trashbin) |
| Auto-lock (timeout) | 🟢 Working | Configurable (1min–never, default 5min) |
| Auto-lock (tab close) | 🟢 Working | visibilitychange listener |
| Brute-force protection | 🟢 Working | Progressive throttling |
| Rate limiting | 🟢 Working | On sensitive operations |
| VaultSessionMiddleware | 🟢 Working | Gates all image endpoints (403 when locked) |
| Cross-user isolation | 🟢 Working | Ownership check on every query |
| Admin settings | 🟢 Working | IIconSection + ISettings |
| Keyboard accessibility | 🟢 Working | tabindex + Enter on tiles, focus outline |
| Structured logging | 🟢 Working | Operation IDs for audit trail |
| i18n (pt_BR) | 🟢 Working | |
| Security PoC (12/12 passed) | 🟢 Verified | See docs/security-poc-results.md |
| App Store publication | 🟡 Pending | Certificate signing in progress |
| PIN unlock | 📋 Planned | v0.2 |
| WebAuthn / FIDO2 | 📋 Planned | v0.3 |
| Encryption at-rest (E2EE) | 📋 Planned | Future |

## Architecture

```
xfiles/
├── app/                           # Nextcloud PHP app
│   ├── appinfo/                   # info.xml, routes.php
│   ├── lib/
│   │   ├── AppInfo/               # Bootstrap + DI (IBootstrap)
│   │   ├── Controller/            # OCS API (Vault, Image, Settings, Page)
│   │   ├── Service/               # VaultService, ImageService, SessionService, PasswordService
│   │   ├── Db/                    # Entities + QBMappers (Vault, VaultImage)
│   │   ├── Middleware/            # VaultSessionMiddleware (auth gate)
│   │   ├── Listener/             # Event listeners
│   │   ├── Migration/             # DB schema (xfiles_vaults, xfiles_images)
│   │   └── Settings/             # AdminSettings, AdminSection
│   ├── src/                       # Vue.js frontend
│   │   ├── views/                 # SetupView, LockedView, UnlockedView, SettingsView
│   │   ├── services/              # API client
│   │   ├── init.js               # FileAction registration (Classify)
│   │   └── main.js              # App entry point
│   ├── js/                        # Webpack build output
│   ├── l10n/                      # Translations (pt_BR)
│   ├── img/                       # App icon
│   └── templates/                 # PHP template (Vue mount point)
├── docs/                          # Architecture decisions, security audit, QA plan
├── scripts/                       # Deploy, test automation
└── .github/                       # CI workflows
```

## Tech Stack

- **Backend:** PHP 8.2+ / Nextcloud App Framework 28–34
- **Frontend:** Vue.js 2.7 / @nextcloud/vue 8 / Webpack 5
- **Storage:** IAppData (private per-app filesystem, isolated from user files)
- **Auth:** Argon2id password hashing + ISession (CryptoSessionData) + BruteForceProtection
- **Database:** QBMapper + Entities (xfiles_vaults, xfiles_images)
- **Integrity:** SHA-256 checksum on every stored image

## Security Model

X-Files protects against:
- Other Nextcloud apps accessing your vault images
- Files / Photos / Memories / Search / WebDAV seeing vault content
- Desktop and mobile clients (Nextcloud sync) accessing vault files
- Compromised browser sessions (requires independent vault password)
- Brute-force unlock attempts (progressive throttling + rate limiting)

X-Files does **NOT** protect against:
- Server administrators with filesystem access
- Direct database access
- Server backups (files included as part of AppData)

For protection against admin access, E2EE (at-rest encryption) is planned for a future version.

## API Endpoints

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/api/v1/vault/status` | User | Vault state (locked/unlocked/not_setup) |
| POST | `/api/v1/vault/setup` | User | Create vault with password |
| POST | `/api/v1/vault/unlock` | User | Unlock vault |
| POST | `/api/v1/vault/lock` | User | Lock vault |
| GET | `/api/v1/images` | User+Vault | List images |
| POST | `/api/v1/images` | User+Vault | Upload image |
| GET | `/api/v1/images/{id}` | User+Vault | Download image |
| GET | `/api/v1/images/{id}/thumb` | User+Vault | Get thumbnail |
| DELETE | `/api/v1/images/{id}` | User+Vault | Delete image |
| GET | `/api/v1/settings` | User | Get vault settings |
| PUT | `/api/v1/settings` | User | Update vault settings |

## Contributing

Contributions welcome. Check the [issues](https://github.com/luisfacarmo/xfiles/issues) for areas where help is needed.

## License

[AGPL-3.0](LICENSE)
