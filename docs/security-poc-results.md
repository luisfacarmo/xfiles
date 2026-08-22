# X-Files — Security Proof of Concept Results

**Date:** 2026-08-22
**Nextcloud:** 34.0.3
**PHP:** 8.4.24
**X-Files version:** 0.1.0

## Summary

**12/12 tests PASSED.** Vault images are confirmed invisible to all standard Nextcloud components.

## Test Environment

- Server: bare-metal, Linux
- Test user (vault owner): `mulder`
- Test user (cross-user): `scully`
- Vault password: Argon2id hashed
- Test image: `secret_photo.jpg` (800x600, 16.9KB JPEG)
- Storage location: `appdata_ocjtm1flm8tb/xfiles/mulder/`

## Results

| # | Vector | Method | Result | Details |
|---|--------|--------|--------|---------|
| 1 | WebDAV listing | `PROPFIND /remote.php/dav/files/mulder/` (Depth: infinity) | ✅ PASS | Image not found in any directory listing |
| 2 | WebDAV direct GET | GET on guessed paths (`.vault/`, `Photos/`, root) | ✅ PASS | All return 404 |
| 3 | Unified Search | Search API for "secret_photo" | ✅ PASS | No results returned |
| 4 | Preview API | `GET /core/preview?fileId=58009` (actual filecache ID) | ✅ PASS | Returns 404 (AppData not in user mount) |
| 5 | occ files:scan | `occ files:scan mulder` | ✅ PASS | No vault files appear in user's `files/` path |
| 6 | Endpoints while locked | GET list, thumb, download with vault locked | ✅ PASS | All return 403 VAULT_LOCKED |
| 7a | Cross-user (no vault) | Scully without vault tries to access image id=4 | ✅ PASS | 403 Forbidden |
| 7b | Cross-user (vault unlocked) | Scully with HER vault unlocked tries image id=4 | ✅ PASS | 404 Not Found (ownership check) |
| 8 | Photos app | Check `oc_photos_albums_files` and `oc_files_metadata` | ✅ PASS | Zero entries for vault fileIds |
| 9 | Memories app | Check `oc_memories` and `oc_memories_places` | ✅ PASS | Zero entries for vault fileIds |
| 10 | Anonymous access | No authentication header on all endpoints | ✅ PASS | All return 401 |
| 11 | Activity app | Check `oc_activity` for vault file references | ✅ PASS | Zero activity entries leaked |

## Technical Notes

### oc_filecache Presence

Vault files **do** have entries in `oc_filecache` — this is an internal implementation detail of Nextcloud's AppData storage. The entries exist under the path `appdata_ocjtm1flm8tb/xfiles/mulder/` which is:

- **NOT** part of the user's filesystem mount
- **NOT** accessible via `getUserFolder()`
- **NOT** returned by WebDAV PROPFIND on the user's files
- **NOT** renderable by the Preview API (returns 404)
- **NOT** indexed by Photos, Memories, or Search

This is the expected and documented behavior of `IAppData` storage.

### Security Guarantees

The X-Files vault provides **application-level isolation**:

| Protected against | Mechanism |
|---|---|
| Other Nextcloud apps (Files, Photos, Memories) | AppData storage (outside user filesystem) |
| WebDAV / desktop client / mobile client | Not mounted in WebDAV namespace |
| Search / indexing | Not in user's filecache path |
| Compromised browser session | Independent vault password + session timeout |
| Brute-force attacks | `#[BruteForceProtection]` + `#[UserRateLimit]` |
| Cross-user access | User ID ownership check on every operation |
| Anonymous access | Nextcloud session authentication required |

### Known Limitations (documented, not bugs)

| NOT protected against | Reason |
|---|---|
| Server administrator (filesystem access) | Files stored unencrypted in AppData |
| Direct database access | Metadata (filenames, sizes) visible in `oc_xfiles_images` |
| Server backups | AppData included in standard NC backup |
| Malicious PHP app in same process | Same PHP process can access any file (inherent to NC architecture) |

## Conclusion

The AppData-based architecture successfully isolates vault files from all standard Nextcloud access vectors. The isolation is a natural consequence of the storage architecture — not a hack or workaround that could break in future versions.

The security promise to users is honest and verifiable:

> "X-Files protects your images from other Nextcloud apps, the web interface, WebDAV, desktop/mobile clients, and compromised sessions. It does NOT protect against server administrators with direct filesystem or database access."
