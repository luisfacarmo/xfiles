# Contributing

Contributions are welcome! Here's how to get started.

## How to contribute

1. Fork this repository
2. Create a branch for your change (`git checkout -b feat/my-feature`)
3. Make your changes
4. Test locally (see below)
5. Commit with a clear message (`feat: add X` or `fix: resolve Y`)
6. Open a Pull Request

## Local setup

### Requirements
- PHP 8.2+
- Node 20+
- Nextcloud 34 development environment
- Composer 2.x

### Install
```bash
cd app
npm install
composer install
```

### Build
```bash
npm run build
```

### Run checks
```bash
# Static analysis
./vendor/bin/phpstan analyse --level 5

# JS lint
npm run lint
```

### Test in Nextcloud
```bash
# Symlink the app into your NC instance
ln -s /path/to/xfiles/app /var/www/nextcloud/apps/xfiles

# Enable it
sudo -u www-data php occ app:enable xfiles
```

## Commit style

We follow [Conventional Commits](https://www.conventionalcommits.org/):
- `feat:` — new feature
- `fix:` — bug fix
- `docs:` — documentation only
- `chore:` — maintenance, deps, CI
- `refactor:` — code restructuring without behavior change

## What we accept

- Bug fixes with evidence (logs, screenshots, steps to reproduce)
- New features discussed in an Issue first
- Documentation improvements
- Translations (i18n) — see `app/l10n/`

## What we don't accept

- Breaking changes without prior discussion
- Code without testing instructions
- PRs that mix unrelated changes
- Direct commits to `master` — always use a PR

## Architecture notes

- **Backend**: PHP (OCP AppFramework), AppData storage for vault files
- **Frontend**: Vue 2 + @nextcloud/vue components, Webpack build
- **FileAction**: Standalone ESM module (`js/xfiles-init.mjs`) — not bundled by Webpack
- **Security**: AppData isolation, finfo MIME validation, SHA-256 checksums

## Questions?

Open an Issue. We'll respond as soon as possible.
