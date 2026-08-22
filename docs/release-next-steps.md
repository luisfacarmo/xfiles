# X-Files — Release Next Steps

**Current status:** Waiting for certificate from Nextcloud team.

---

## What's done

- [x] Code complete (v0.1.0)
- [x] 34/34 API tests passing
- [x] PHPStan level 5 clean
- [x] Security PoC 12/12 PASS
- [x] Integration verified with Photos, Memories, Recognize, etc.
- [x] GitHub repo public: https://github.com/luisfacarmo/xfiles
- [x] CSR submitted: https://github.com/nextcloud/app-certificate-requests/pull/1179
- [x] Private key stored securely at `/opt/xfiles/.keys/xfiles.key`

---

## When certificate arrives

The PR will be merged and the signed certificate will appear at:
`https://github.com/nextcloud/app-certificate-requests/blob/master/xfiles/xfiles.crt`

### Steps to complete release:

```bash
# 1. Download the certificate
curl -o /opt/xfiles/.keys/xfiles.crt \
  https://raw.githubusercontent.com/nextcloud/app-certificate-requests/master/xfiles/xfiles.crt

# 2. Sign the app
sudo -u www-data php /var/www/nextcloud/occ integrity:sign-app \
  --privateKey=/opt/xfiles/.keys/xfiles.key \
  --certificate=/opt/xfiles/.keys/xfiles.crt \
  --path=/opt/xfiles/app

# 3. Verify signature.json was created
cat /opt/xfiles/app/appinfo/signature.json | head -5

# 4. Capture screenshots (3 needed)
#    - docs/screenshots/locked.png  (lock screen with EyeLock icon)
#    - docs/screenshots/gallery.png (gallery with images)
#    - docs/screenshots/viewer.png  (fullscreen viewer with image)

# 5. Commit screenshots + signature
cd /opt/xfiles
git add app/appinfo/signature.json docs/screenshots/
git commit -m "release: v0.1.0 signed + screenshots"
git push

# 6. Create release tag
git tag v0.1.0
git push origin v0.1.0

# 7. CI will automatically:
#    - Build frontend
#    - Package tarball (excludes node_modules, tests, src)
#    - Sign with OpenSSL
#    - Create GitHub Release
#    - Publish to Nextcloud App Store
```

### Manual publish (if CI doesn't run)

```bash
# Package manually
cd /opt/xfiles
mkdir -p dist
tar -czf dist/xfiles-0.1.0.tar.gz \
  --transform "s,^app/,xfiles/," \
  --exclude="app/node_modules" \
  --exclude="app/vendor" \
  --exclude="app/tests" \
  --exclude="app/src" \
  --exclude="app/composer.json" \
  --exclude="app/composer.lock" \
  --exclude="app/package.json" \
  --exclude="app/package-lock.json" \
  --exclude="app/webpack.config.js" \
  --exclude="app/phpstan.neon.dist" \
  app/

# Upload to GitHub release and submit to App Store manually
# at https://apps.nextcloud.com/developer/apps/releases/new
```

---

## While waiting: optional improvements

These don't block release but improve quality:

1. **Screenshots** — Can capture now, commit to repo
2. **i18n** — Extract strings to `l10n/` for future translations
3. **Multi-select** — Batch delete (planned for v0.2)
4. **FileAction "Send to X-Files"** — Integration with Files app (v0.2)

---

## Important files

| File | Purpose | Secret? |
|---|---|---|
| `/opt/xfiles/.keys/xfiles.key` | Private signing key | YES — never commit |
| `/opt/xfiles/.keys/xfiles.csr` | Certificate request (submitted) | No |
| `/opt/xfiles/.keys/xfiles.crt` | Certificate (when received) | No |
| `app/appinfo/signature.json` | App signature (after signing) | No — must be committed |

---

## Timeline estimate

- Certificate PR review: 1-3 weeks (based on DevNull PR #1152 still pending after 11 days)
- After certificate: ~1 hour to sign, package, and publish
