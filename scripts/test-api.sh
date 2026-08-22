#!/bin/bash
# X-Files API Test Suite
# Usage: bash scripts/test-api.sh [APP_TOKEN]
# Requires: curl, mysql access, Nextcloud running on localhost (HTTPS)

set -euo pipefail

# Configuration
APP_TOKEN="${1:-IFYHS6rCRbopk55EXwyiFKCnVlFsOorKjrp4j6buKIg9xAPdXIPe44tR8cuhLccvCpeZoak1}"
BASE_OCS="https://localhost/ocs/v2.php/apps/xfiles/api/v1"
BASE_APP="https://localhost/index.php/apps/xfiles/api/v1"
USER="mulder"
VAULT_PASS="TrustNo1"
COOKIES="/tmp/xfiles_test_cookies"
CURL="curl -sk -u ${USER}:${APP_TOKEN} -H OCS-APIRequest:true"

# Counters
PASS=0
FAIL=0
TOTAL=0

# Helpers
pass() { PASS=$((PASS+1)); TOTAL=$((TOTAL+1)); echo "  ✅ PASS: $1"; }
fail() { FAIL=$((FAIL+1)); TOTAL=$((TOTAL+1)); echo "  ❌ FAIL: $1 — $2"; }

ocs_get() { $CURL -c $COOKIES -b $COOKIES "$BASE_OCS$1?format=json" 2>/dev/null; }
ocs_post() { $CURL -c $COOKIES -b $COOKIES -H "Content-Type: application/json" -X POST -d "$2" "$BASE_OCS$1?format=json" 2>/dev/null; }
app_get() { $CURL -c $COOKIES -b $COOKIES "$BASE_APP$1" 2>/dev/null; }
app_post_file() { $CURL -c $COOKIES -b $COOKIES -F "file=@$2" "$BASE_APP$1" 2>/dev/null; }
app_delete() { $CURL -c $COOKIES -b $COOKIES -X DELETE "$BASE_APP$1" 2>/dev/null; }

get_ocs_data() { echo "$1" | python3 -c "import json,sys; d=json.loads(sys.stdin.read()); print(json.dumps(d['ocs']['data']))" 2>/dev/null; }
get_ocs_status() { echo "$1" | python3 -c "import json,sys; d=json.loads(sys.stdin.read()); print(d['ocs']['meta']['statuscode'])" 2>/dev/null; }
get_json_field() { echo "$1" | python3 -c "import json,sys; d=json.loads(sys.stdin.read()); print(d.get('$2',''))" 2>/dev/null; }

echo "╔══════════════════════════════════════════════════╗"
echo "║       X-FILES API TEST SUITE v0.1.0             ║"
echo "╚══════════════════════════════════════════════════╝"
echo ""
echo "Server: localhost (HTTPS)"
echo "User: $USER"
echo "Date: $(date -Iseconds)"
echo ""

# ─────────────────────────────────────────────
# SETUP: Clean state
# ─────────────────────────────────────────────
echo "── Preparing clean state ──"
rm -f $COOKIES
sudo mysql -e "DELETE FROM oc_xfiles_images WHERE user_id='$USER';" nextcloud 2>/dev/null || true
sudo mysql -e "DELETE FROM oc_xfiles_vaults WHERE user_id='$USER';" nextcloud 2>/dev/null || true
echo "  Cleared vault + images for $USER"
echo ""

# Create test image
php -r "
\$img = imagecreatetruecolor(640, 480);
\$blue = imagecolorallocate(\$img, 0, 100, 200);
imagefill(\$img, 0, 0, \$blue);
imagestring(\$img, 5, 250, 230, 'QA TEST', imagecolorallocate(\$img, 255, 255, 255));
imagejpeg(\$img, '/tmp/xfiles_qa_test.jpg', 85);
imagedestroy(\$img);
" 2>/dev/null
echo "  Test image created: /tmp/xfiles_qa_test.jpg"
echo ""

# ─────────────────────────────────────────────
echo "══ 1. VAULT STATUS (no vault) ══"
# ─────────────────────────────────────────────
RESP=$(ocs_get "/vault/status")
STATUS=$(get_ocs_data "$RESP" | python3 -c "import json,sys; print(json.loads(sys.stdin.read()).get('status',''))" 2>/dev/null)
if [ "$STATUS" = "not_setup" ]; then pass "Status is 'not_setup' for new user"; else fail "Status should be not_setup" "got: $STATUS"; fi

echo ""
echo "══ 2. VAULT SETUP ══"
# ─────────────────────────────────────────────
RESP=$(ocs_post "/vault/setup" "{\"password\":\"$VAULT_PASS\"}")
SC=$(get_ocs_status "$RESP")
RECOVERY=$(get_ocs_data "$RESP" | python3 -c "import json,sys; print(json.loads(sys.stdin.read()).get('recovery_key',''))" 2>/dev/null)
if [ "$SC" = "200" ] && [ -n "$RECOVERY" ]; then pass "Vault created, recovery_key=$RECOVERY"; else fail "Vault setup" "sc=$SC"; fi

# Duplicate setup
RESP=$(ocs_post "/vault/setup" "{\"password\":\"$VAULT_PASS\"}")
SC=$(get_ocs_status "$RESP")
if [ "$SC" = "409" ]; then pass "Duplicate setup returns 409"; else fail "Duplicate setup" "sc=$SC"; fi

echo ""
echo "══ 3. VAULT STATUS (unlocked after setup) ══"
# ─────────────────────────────────────────────
RESP=$(ocs_get "/vault/status")
STATUS=$(get_ocs_data "$RESP" | python3 -c "import json,sys; print(json.loads(sys.stdin.read()).get('status',''))" 2>/dev/null)
if [ "$STATUS" = "unlocked" ]; then pass "Status 'unlocked' after setup"; else fail "Expected unlocked after setup" "got: $STATUS"; fi

echo ""
echo "══ 4. LOCK ══"
# ─────────────────────────────────────────────
RESP=$(ocs_post "/vault/lock" "{}")
SC=$(get_ocs_status "$RESP")
if [ "$SC" = "200" ]; then pass "Lock successful"; else fail "Lock" "sc=$SC"; fi

RESP=$(ocs_get "/vault/status")
STATUS=$(get_ocs_data "$RESP" | python3 -c "import json,sys; print(json.loads(sys.stdin.read()).get('status',''))" 2>/dev/null)
if [ "$STATUS" = "locked" ]; then pass "Status 'locked' after lock"; else fail "Expected locked" "got: $STATUS"; fi

echo ""
echo "══ 5. UNLOCK (wrong password) ══"
# ─────────────────────────────────────────────
RESP=$(ocs_post "/vault/unlock" '{"password":"WRONG"}')
SC=$(get_ocs_status "$RESP")
if [ "$SC" = "403" ]; then pass "Wrong password returns 403"; else fail "Wrong password" "sc=$SC"; fi

echo ""
echo "══ 6. UNLOCK (correct password) ══"
# ─────────────────────────────────────────────
RESP=$(ocs_post "/vault/unlock" "{\"password\":\"$VAULT_PASS\"}")
SC=$(get_ocs_status "$RESP")
if [ "$SC" = "200" ]; then pass "Correct password unlocks"; else fail "Unlock" "sc=$SC"; fi

echo ""
echo "══ 7. IMAGES (empty list) ══"
# ─────────────────────────────────────────────
RESP=$(app_get "/images")
TOTAL_IMG=$(get_json_field "$RESP" "total")
if [ "$TOTAL_IMG" = "0" ]; then pass "Image list empty (total=0)"; else fail "Expected 0 images" "got: $TOTAL_IMG"; fi

echo ""
echo "══ 8. IMAGE UPLOAD ══"
# ─────────────────────────────────────────────
RESP=$(app_post_file "/images/upload" "/tmp/xfiles_qa_test.jpg")
SUCCESS=$(get_json_field "$RESP" "success")
IMG_ID=$(echo "$RESP" | python3 -c "import json,sys; d=json.loads(sys.stdin.read()); print(d.get('image',{}).get('id',''))" 2>/dev/null)
if [ "$SUCCESS" = "True" ] && [ -n "$IMG_ID" ]; then pass "Upload successful (id=$IMG_ID)"; else fail "Upload" "resp=$RESP"; fi

echo ""
echo "══ 9. IMAGE LIST (has image) ══"
# ─────────────────────────────────────────────
RESP=$(app_get "/images")
TOTAL_IMG=$(get_json_field "$RESP" "total")
if [ "$TOTAL_IMG" = "1" ]; then pass "Image list has 1 image"; else fail "Expected 1 image" "got: $TOTAL_IMG"; fi

echo ""
echo "══ 10. THUMBNAIL ══"
# ─────────────────────────────────────────────
HTTP_CODE=$($CURL -c $COOKIES -b $COOKIES -o /tmp/xfiles_qa_thumb.jpg -w "%{http_code}" "$BASE_APP/images/$IMG_ID/thumb" 2>/dev/null)
THUMB_SIZE=$(stat -c%s /tmp/xfiles_qa_thumb.jpg 2>/dev/null || echo 0)
if [ "$HTTP_CODE" = "200" ] && [ "$THUMB_SIZE" -gt 100 ]; then pass "Thumbnail returned (${THUMB_SIZE}B)"; else fail "Thumbnail" "http=$HTTP_CODE size=$THUMB_SIZE"; fi

echo ""
echo "══ 11. DOWNLOAD ══"
# ─────────────────────────────────────────────
HTTP_CODE=$($CURL -c $COOKIES -b $COOKIES -o /tmp/xfiles_qa_download.jpg -w "%{http_code}" "$BASE_APP/images/$IMG_ID/download" 2>/dev/null)
DL_SIZE=$(stat -c%s /tmp/xfiles_qa_download.jpg 2>/dev/null || echo 0)
if [ "$HTTP_CODE" = "200" ] && [ "$DL_SIZE" -gt 1000 ]; then pass "Download successful (${DL_SIZE}B)"; else fail "Download" "http=$HTTP_CODE size=$DL_SIZE"; fi

echo ""
echo "══ 12. IMAGE DELETE ══"
# ─────────────────────────────────────────────
RESP=$(app_delete "/images/$IMG_ID")
SUCCESS=$(get_json_field "$RESP" "success")
if [ "$SUCCESS" = "True" ]; then pass "Delete successful"; else fail "Delete" "resp=$RESP"; fi

RESP=$(app_get "/images")
TOTAL_IMG=$(get_json_field "$RESP" "total")
if [ "$TOTAL_IMG" = "0" ]; then pass "Image list empty after delete"; else fail "Expected 0 after delete" "got: $TOTAL_IMG"; fi

echo ""
echo "══ 13. IMAGES BLOCKED WHEN LOCKED ══"
# ─────────────────────────────────────────────
# Upload one image first for locked tests
app_post_file "/images/upload" "/tmp/xfiles_qa_test.jpg" > /dev/null
RESP=$(app_get "/images")
IMG_ID2=$(echo "$RESP" | python3 -c "import json,sys; d=json.loads(sys.stdin.read()); print(d['images'][0]['id'] if d['images'] else '')" 2>/dev/null)

# Lock
ocs_post "/vault/lock" "{}" > /dev/null

# Try access while locked
HTTP_LIST=$($CURL -c $COOKIES -b $COOKIES -o /dev/null -w "%{http_code}" "$BASE_APP/images" 2>/dev/null)
HTTP_THUMB=$($CURL -c $COOKIES -b $COOKIES -o /dev/null -w "%{http_code}" "$BASE_APP/images/$IMG_ID2/thumb" 2>/dev/null)
HTTP_DL=$($CURL -c $COOKIES -b $COOKIES -o /dev/null -w "%{http_code}" "$BASE_APP/images/$IMG_ID2/download" 2>/dev/null)

if [ "$HTTP_LIST" = "403" ]; then pass "List blocked when locked (403)"; else fail "List while locked" "http=$HTTP_LIST"; fi
if [ "$HTTP_THUMB" = "403" ]; then pass "Thumbnail blocked when locked (403)"; else fail "Thumb while locked" "http=$HTTP_THUMB"; fi
if [ "$HTTP_DL" = "403" ]; then pass "Download blocked when locked (403)"; else fail "Download while locked" "http=$HTTP_DL"; fi

echo ""
echo "══ 14. SETTINGS UPDATE ══"
# ─────────────────────────────────────────────
# Unlock first
ocs_post "/vault/unlock" "{\"password\":\"$VAULT_PASS\"}" > /dev/null

RESP=$(ocs_post "/vault/settings" '{"auto_lock_seconds":900,"max_file_size_mb":75}')
SC=$(get_ocs_status "$RESP")
if [ "$SC" = "200" ]; then pass "Settings updated"; else fail "Settings update" "sc=$SC"; fi

RESP=$(ocs_get "/vault/status")
ALS=$(get_ocs_data "$RESP" | python3 -c "import json,sys; print(json.loads(sys.stdin.read()).get('auto_lock_seconds',''))" 2>/dev/null)
MFS=$(get_ocs_data "$RESP" | python3 -c "import json,sys; print(json.loads(sys.stdin.read()).get('max_file_size_mb',''))" 2>/dev/null)
if [ "$ALS" = "900" ] && [ "$MFS" = "75" ]; then pass "Settings persisted (timeout=900, max=75MB)"; else fail "Settings verify" "als=$ALS mfs=$MFS"; fi

echo ""
echo "══ 15. CHANGE PASSWORD ══"
# ─────────────────────────────────────────────
RESP=$(ocs_post "/vault/password" '{"current_password":"WRONG","new_password":"NewPass1"}')
SC=$(get_ocs_status "$RESP")
if [ "$SC" = "403" ]; then pass "Change password with wrong current → 403"; else fail "Change password wrong" "sc=$SC"; fi

RESP=$(ocs_post "/vault/password" "{\"current_password\":\"$VAULT_PASS\",\"new_password\":\"NewPass1\"}")
SC=$(get_ocs_status "$RESP")
if [ "$SC" = "200" ]; then pass "Change password successful"; else fail "Change password" "sc=$SC"; fi

# Verify new password works
RESP=$(ocs_post "/vault/unlock" '{"password":"NewPass1"}')
SC=$(get_ocs_status "$RESP")
if [ "$SC" = "200" ]; then pass "New password works"; else fail "New password unlock" "sc=$SC"; fi

echo ""
echo "══ 16. RECOVERY ══"
# ─────────────────────────────────────────────
# Lock first
ocs_post "/vault/lock" "{}" > /dev/null

RESP=$(ocs_post "/vault/recover" '{"recovery_key":"WRONG-KEY-HERE","new_password":"Recovered1"}')
SC=$(get_ocs_status "$RESP")
if [ "$SC" = "403" ]; then pass "Recovery with wrong key → 403"; else fail "Recovery wrong key" "sc=$SC"; fi

RESP=$(ocs_post "/vault/recover" "{\"recovery_key\":\"$RECOVERY\",\"new_password\":\"Recovered1\"}")
SC=$(get_ocs_status "$RESP")
if [ "$SC" = "200" ]; then pass "Recovery with correct key → 200"; else fail "Recovery" "sc=$SC resp=$(echo $RESP | head -c 200)"; fi

# Verify recovered password
RESP=$(ocs_post "/vault/unlock" '{"password":"Recovered1"}')
SC=$(get_ocs_status "$RESP")
if [ "$SC" = "200" ]; then pass "Recovered password works"; else fail "Recovered unlock" "sc=$SC"; fi

echo ""
echo "══ 17. CROSS-USER ISOLATION ══"
# ─────────────────────────────────────────────
# Get scully's token (must exist from security PoC)
SCULLY_TOKEN=$(sudo -u www-data OC_PASS="ScullyQA1!" php /var/www/nextcloud/occ user:auth-tokens:add scully --name "qa-test" --password-from-env 2>&1 | tail -1 | tr -d ' ')
SCULLY_CURL="curl -sk -u scully:${SCULLY_TOKEN} -H OCS-APIRequest:true"
rm -f /tmp/xfiles_scully_qa

# Scully unlocks her vault
$SCULLY_CURL -c /tmp/xfiles_scully_qa -b /tmp/xfiles_scully_qa -H "Content-Type: application/json" \
  -X POST -d '{"password":"Skeptic1"}' "$BASE_OCS/vault/unlock?format=json" > /dev/null 2>&1

# Scully tries mulder's image
HTTP_CROSS=$($SCULLY_CURL -c /tmp/xfiles_scully_qa -b /tmp/xfiles_scully_qa \
  -o /dev/null -w "%{http_code}" "$BASE_APP/images/$IMG_ID2/download" 2>/dev/null)
if [ "$HTTP_CROSS" = "404" ] || [ "$HTTP_CROSS" = "403" ]; then pass "Cross-user access blocked ($HTTP_CROSS)"; else fail "Cross-user" "http=$HTTP_CROSS"; fi

# Scully's list should be empty (not mulder's images)
SCULLY_LIST=$($SCULLY_CURL -c /tmp/xfiles_scully_qa -b /tmp/xfiles_scully_qa "$BASE_APP/images" 2>/dev/null)
SCULLY_TOTAL=$(echo "$SCULLY_LIST" | python3 -c "import json,sys; d=json.loads(sys.stdin.read()); print(d.get('total','?'))" 2>/dev/null)
if [ "$SCULLY_TOTAL" = "0" ]; then pass "Scully sees 0 images (not mulder's)"; else fail "Scully list" "total=$SCULLY_TOTAL"; fi

echo ""
echo "══ 18. WEBDAV / SEARCH / PHOTOS ISOLATION ══"
# ─────────────────────────────────────────────
# WebDAV
WD_CHECK=$($CURL -X PROPFIND -H "Depth: 1" \
  "https://localhost/remote.php/dav/files/$USER/" 2>/dev/null | grep -ci "xfiles_qa_test\|appdata.*xfiles" || true)
if [ "$WD_CHECK" = "0" ]; then pass "WebDAV: vault images not listed"; else fail "WebDAV leak" "matches=$WD_CHECK"; fi

# Search
SEARCH_CHECK=$($CURL "https://localhost/ocs/v2.php/search/providers/files/search?term=xfiles_qa_test&format=json" 2>/dev/null | grep -ci "xfiles_qa" || true)
if [ "$SEARCH_CHECK" = "0" ]; then pass "Search: vault images not found"; else fail "Search leak" "matches=$SEARCH_CHECK"; fi

# filecache check (AppData path, not user files)
FC_USER=$(sudo mysql -sN -e "SELECT COUNT(*) FROM oc_filecache WHERE path LIKE 'files/%' AND name LIKE '%xfiles_qa%';" nextcloud 2>/dev/null || echo "0")
if [ "$FC_USER" = "0" ]; then pass "Filecache: no vault files in user's files/ path"; else fail "Filecache leak" "count=$FC_USER"; fi

echo ""
echo "══ 19. ANONYMOUS ACCESS ══"
# ─────────────────────────────────────────────
ANON_LIST=$(curl -sk -o /dev/null -w "%{http_code}" "$BASE_APP/images" 2>/dev/null)
ANON_THUMB=$(curl -sk -o /dev/null -w "%{http_code}" "$BASE_APP/images/$IMG_ID2/thumb" 2>/dev/null)
if [ "$ANON_LIST" = "401" ]; then pass "Anonymous list → 401"; else fail "Anon list" "http=$ANON_LIST"; fi
if [ "$ANON_THUMB" = "401" ]; then pass "Anonymous thumb → 401"; else fail "Anon thumb" "http=$ANON_THUMB"; fi

echo ""
echo "══ 20. PHPSTAN ══"
# ─────────────────────────────────────────────
PHPSTAN_OUT=$(cd /opt/xfiles/app && vendor/bin/phpstan analyse --configuration=phpstan.neon.dist --no-progress 2>&1 | tail -3)
if echo "$PHPSTAN_OUT" | grep -q "No errors"; then pass "PHPStan level 5: 0 errors"; else fail "PHPStan" "$PHPSTAN_OUT"; fi

# ─────────────────────────────────────────────
echo ""
echo "╔══════════════════════════════════════════════════╗"
echo "║              TEST RESULTS                        ║"
echo "╠══════════════════════════════════════════════════╣"
printf "║  PASSED: %-3s                                    ║\n" "$PASS"
printf "║  FAILED: %-3s                                    ║\n" "$FAIL"
printf "║  TOTAL:  %-3s                                    ║\n" "$TOTAL"
echo "╚══════════════════════════════════════════════════╝"
echo ""

if [ "$FAIL" -eq 0 ]; then
  echo "🎉 ALL TESTS PASSED"
  exit 0
else
  echo "⚠️  SOME TESTS FAILED — review above"
  exit 1
fi
