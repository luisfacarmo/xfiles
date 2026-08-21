# X-Files

[![License: AGPL-3.0](https://img.shields.io/badge/License-AGPL--3.0-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2+-purple.svg)](https://php.net/)
[![Nextcloud](https://img.shields.io/badge/Nextcloud-28--34-0082c9.svg)](https://nextcloud.com/)
[![Vue.js](https://img.shields.io/badge/Vue.js-2.7-4FC08D.svg)](https://vuejs.org/)

> A photo vault for Nextcloud — lock, hide, and protect your most sensitive images.
>
> *"Trust no one — not even your own Nextcloud."*
>
> — Named after the classified cases that nobody was supposed to access.

> [!WARNING]
> X-Files is in early development (pre-alpha). Not ready for production use.

## What is this?

X-Files is a Nextcloud app that provides a password-protected vault for sensitive photos. Images stored in the vault are completely invisible to Files, Photos, Memories, WebDAV, Search, and any other Nextcloud app — they only exist when you unlock the vault.

Think Google Photos Locked Folder or Samsung Secure Folder, but self-hosted.

## Key Concepts

- **Real isolation** — files stored in AppData, not in your user filesystem
- **Independent password** — vault password is separate from your Nextcloud login
- **Session-based unlock** — auto-locks after timeout or manual lock
- **Brute-force protected** — progressive throttling on failed attempts
- **Honest security** — protects against apps and sessions, not against server admins (documented clearly)

## Status

| Component | Status | Notes |
|-----------|--------|-------|
| Vault lifecycle (create/lock/unlock) | 📋 Planned | |
| Password (Argon2id) | 📋 Planned | |
| Image upload to AppData | 📋 Planned | |
| Image listing/gallery | 📋 Planned | |
| Image viewer (fullscreen) | 📋 Planned | |
| Image deletion | 📋 Planned | |
| Thumbnail generation | 📋 Planned | |
| Session timeout | 📋 Planned | |
| Brute-force protection | 📋 Planned | |
| Security PoC (isolation tests) | 📋 Planned | |
| App Store publication | 📋 Planned | |

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
│   │   ├── Migration/             # DB schema
│   │   └── BackgroundJob/         # ExpireSessionsJob
│   ├── src/                       # Vue.js frontend
│   │   ├── views/                 # LockedView, GalleryView, SettingsView
│   │   ├── components/            # UnlockForm, ImageGrid, ImageViewer, UploadArea
│   │   └── services/              # API client
│   ├── templates/                 # PHP template (Vue mount point)
│   ├── img/                       # App icon
│   └── tests/                     # PHPUnit (Unit + Integration)
├── docs/                          # Technical planning, audits
├── scripts/                       # Deploy, test automation
└── .github/                       # CI workflows
```

## Tech Stack

- **Backend:** PHP 8.2+ / Nextcloud App Framework 28+
- **Frontend:** Vue.js 2.7 / @nextcloud/vue / Webpack 5
- **Storage:** IAppData (private per-app filesystem, isolated from user files)
- **Auth:** Argon2id password hashing + ISession + BruteForceProtection
- **Database:** QBMapper + Entities (vaults, images metadata)

## Security Model

X-Files protects against:
- Other Nextcloud apps accessing your vault images
- Files / Photos / Memories / Search / WebDAV seeing vault content
- Compromised browser sessions (requires independent vault password)
- Brute-force unlock attempts (progressive throttling)

X-Files does **NOT** protect against:
- Server administrators with filesystem access
- Direct database access
- Server backups (files included as part of AppData)

For protection against admin access, E2EE (at-rest encryption) is planned for a future version.

## API Endpoints (planned)

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

Contributions welcome. This project is in early development — check the issues for areas where help is needed.

## License

[AGPL-3.0](LICENSE)
