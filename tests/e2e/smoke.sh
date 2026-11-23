#!/usr/bin/env bash
#
# Smoke test end-to-end Rangkui: instalasi via wizard, keamanan, login + CSRF, upload, OAI-PMH.
#
#   BASE=http://127.0.0.1:8080 DB_HOST=127.0.0.1 DB_USER=root DB_PASS=root DB_NAME=rangkui_e2e \
#     bash tests/e2e/smoke.sh
#
# Prasyarat: aplikasi BELUM terpasang & server sudah berjalan di $BASE; curl, xmllint, mysql client.
set -uo pipefail

BASE="${BASE:-http://127.0.0.1:8080}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-root}"
DB_NAME="${DB_NAME:-rangkui_e2e}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_PASS="${ADMIN_PASS:-Rangkui-E2E-123}"
HERE="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "$HERE/../.." && pwd)"
XSD="$ROOT/tests/_support/xsd/OAI-PMH.xsd"
JAR="$(mktemp)"
TMP="$(mktemp -d)"
FAILS=0

pass() { printf '  \033[32m✔\033[0m %s\n' "$1"; }
fail() { printf '  \033[31m✘\033[0m %s\n' "$1"; FAILS=$((FAILS + 1)); }
check() { if [ "$2" = "$3" ]; then pass "$1"; else fail "$1 (diharapkan '$3', didapat '$2')"; fi; }
# Request yang ditolak filter CSRF dibalas 403 (development) atau dialihkan 303 (production).
denied() { case "$2" in 403|303) pass "$1 ($2)" ;; *) fail "$1 (status $2)" ;; esac; }
# Ambil halaman lalu cari teks (tanpa pipe ke grep -q yang memicu SIGPIPE di bawah pipefail).
page_has() { local body; body="$(curl -s "${@:3}" "$1")"; [[ "$body" == *"$2"* ]]; }
code() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
csrf_from() { grep -o 'name="csrf-token" content="[^"]*"' "$1" | head -1 | sed 's/.*content="//;s/"$//'; }
csrf_name() { grep -o 'data-name="[^"]*"' "$1" | head -1 | sed 's/data-name="//;s/"$//'; }
sql() { mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -N -e "$1" 2>/dev/null; }

echo "== 1. Instalasi (wizard)"
check "Halaman wizard tampil" "$(code -c "$JAR" -b "$JAR" "$BASE/")" 200
curl -s -c "$JAR" -b "$JAR" "$BASE/install/fresh" -o /dev/null
WEAK=$(curl -s -c "$JAR" -b "$JAR" -X POST "$BASE/install/fresh" \
  --data-urlencode "host=$DB_HOST" --data-urlencode "port=$DB_PORT" --data-urlencode "username=$DB_USER" \
  --data-urlencode "password=$DB_PASS" --data-urlencode "database=$DB_NAME" \
  --data-urlencode "admin_username=$ADMIN_USER" --data-urlencode "admin_password=short")
[[ "$WEAK" == *"minimal 8"* ]] && pass "Password admin lemah ditolak" || fail "Password admin lemah tidak ditolak"
RESP=$(curl -s -c "$JAR" -b "$JAR" -X POST "$BASE/install/fresh" \
  --data-urlencode "host=$DB_HOST" --data-urlencode "port=$DB_PORT" --data-urlencode "username=$DB_USER" \
  --data-urlencode "password=$DB_PASS" --data-urlencode "database=$DB_NAME" \
  --data-urlencode "admin_username=$ADMIN_USER" --data-urlencode "admin_password=$ADMIN_PASS")
[[ "$RESP" == *"Connection success"* ]] && pass "Koneksi database OK" || fail "Koneksi database gagal: $RESP"
[[ -n "$DB_PASS" && "$RESP" == *"$DB_PASS"* ]] && fail "Password DB dikirim balik ke browser" || pass "Password DB tidak dikirim balik"
INST=$(curl -s -c "$JAR" -b "$JAR" -X POST "$BASE/install/process")
[[ "$INST" == *'"success"'* ]] && pass "Instalasi selesai" || { fail "Instalasi gagal: $INST"; exit 1; }
check "Wizard tertutup setelah instalasi" "$(code "$BASE/install/fresh")" 404

echo "== 2. Kontrol akses (tanpa login)"
for path in home membership profile export/download-counter bibliography sistem/pustakawan report/recap; do
  check "/$path dialihkan ke login" "$(code "$BASE/$path")" 302
done
printf '<?php echo "PWNED";' > "$TMP/x.php"
denied "Upload PHP tanpa login ditolak" "$(code -X POST "$BASE/membership/save" -F member_id=E1 -F member_name=x -F member_type_id=1 -F "member_image=@$TMP/x.php;type=image/png")"
check "  ...dan tidak ada anggota/berkas yang tersimpan" "$(sql "SELECT COUNT(*) FROM member WHERE member_id='E1'")" 0
denied "Reset password admin tanpa login ditolak" "$(code -X POST "$BASE/profile/save" -d 'user_id=1&passwd1=hacked123')"

echo "== 3. Injeksi SQL di pencarian publik"
sql "INSERT INTO search_biblio (biblio_id,title,author,topic) VALUES (999,'Dokumen Uji','Penulis','Topik')"
T=$(curl -s -o /dev/null -w '%{time_total}' -G "$BASE/beranda/search" --data-urlencode "s=x' OR SLEEP(2) OR '")
awk "BEGIN{exit !($T < 1.5)}" && pass "Payload SLEEP tidak dieksekusi (${T}s)" || fail "Kemungkinan SQL injection (${T}s)"
check "Nama kolom tak dikenal diabaikan" "$(code "$BASE/beranda/search?1=1%20OR%20SLEEP(2)%20OR%20title")" 200
page_has "$BASE/beranda/search?s=Dokumen" "Dokumen Uji" && pass "Pencarian sah menemukan hasil" || fail "Pencarian sah tidak menemukan hasil"

echo "== 4. Login + CSRF"
curl -s -c "$JAR" -b "$JAR" "$BASE/login" -o "$TMP/login.html"
TOKEN=$(csrf_from "$TMP/login.html"); TNAME=$(csrf_name "$TMP/login.html")
[ -n "$TOKEN" ] && pass "Token CSRF tersedia di halaman login" || fail "Token CSRF tidak ada"
LOC=$(curl -s -c "$JAR" -b "$JAR" -X POST "$BASE/login/auth" -d "username=$ADMIN_USER&password=$ADMIN_PASS" -o /dev/null -w '%{http_code} %{redirect_url}')
case "$LOC" in *"/home") fail "Login tanpa token CSRF diterima" ;; *) pass "Login tanpa token CSRF ditolak ($LOC)" ;; esac
LOC=$(curl -s -c "$JAR" -b "$JAR" -X POST "$BASE/login/auth" --data-urlencode "$TNAME=$TOKEN" \
  --data-urlencode "username=$ADMIN_USER" --data-urlencode "password=$ADMIN_PASS" -o /dev/null -w '%{redirect_url}')
case "$LOC" in */home) pass "Login berhasil" ;; *) fail "Login gagal (redirect: $LOC)" ;; esac
check "Dashboard dapat dibuka" "$(code -c "$JAR" -b "$JAR" "$BASE/home")" 200
curl -s -c "$JAR" -b "$JAR" "$BASE/membership/add" -o "$TMP/add.html"
TOKEN=$(csrf_from "$TMP/add.html")
sql "INSERT INTO member (member_id,member_name,gender,member_type_id,expire_date,input_date,last_update) VALUES ('CSRF1','Korban',1,1,'2099-01-01',NOW(),NOW())"
denied "POST admin tanpa token ditolak" "$(code -c "$JAR" -b "$JAR" -X POST "$BASE/membership/delete" -d 'mi=CSRF1')"
check "  ...dan data tidak terhapus" "$(sql "SELECT COUNT(*) FROM member WHERE member_id='CSRF1'")" 1

echo "== 5. Unggahan"
TYPE_ID=$(sql "SELECT member_type_id FROM mst_member_type ORDER BY member_type_id LIMIT 1")
[ -z "$TYPE_ID" ] && sql "INSERT INTO mst_member_type (member_type_name,loan_limit,loan_periode,enable_reserve,member_periode,reserve_limit,reborrow_limit,fine_each_day,grace_periode,input_date,last_update) VALUES ('Umum',2,7,0,365,0,0,0,0,NOW(),NOW())" && TYPE_ID=$(sql "SELECT member_type_id FROM mst_member_type LIMIT 1")
curl -s -c "$JAR" -b "$JAR" -X POST "$BASE/membership/save" -F "$TNAME=$TOKEN" -F member_id=E2E001 -F "member_name=Anggota O'Brien" \
  -F member_type_id="$TYPE_ID" -F gender=1 -F "member_image=@$HERE/pixel.png;type=image/png" -o /dev/null
check "Anggota tersimpan (nama ber-apostrof utuh)" "$(sql "SELECT member_name FROM member WHERE member_id='E2E001'")" "Anggota O'Brien"
IMG=$(sql "SELECT member_image FROM member WHERE member_id='E2E001'")
case "$IMG" in member-*.png) pass "Foto disimpan dengan nama acak ($IMG)" ;; *) fail "Nama foto tidak sesuai: '$IMG'" ;; esac
curl -s -c "$JAR" -b "$JAR" -X POST "$BASE/membership/save" -F "$TNAME=$TOKEN" -F member_id=E2E002 -F member_name=Evil \
  -F member_type_id="$TYPE_ID" -F gender=1 -F "member_image=@$TMP/x.php;type=image/png;filename=shell.php" -o /dev/null
if [ -n "$(find "$ROOT/public/uploads" -name '*.php' ! -name index.php)" ]; then fail "Berkas PHP berhasil diunggah"; else pass "Berkas PHP berkedok gambar ditolak"; fi

echo "== 6. OAI-PMH"
sql "INSERT INTO biblio (biblio_id,unique_id,title,item_type_id,code_ministry,publish_year,language_id,license_id,url_crossref,notes,opac_hide,promoted,input_date,last_update,uid) VALUES (1,1,'Skripsi <Uji> & Analisis',1,'',2025,'id',1,'','Abstrak',0,0,NOW(),NOW(),1),(2,2,'Tersembunyi',1,'',2025,'id',1,'','x',1,0,NOW(),NOW(),1)"
HOST=$(echo "$BASE" | sed -E 's#^[a-z]+://([^/:]+).*#\1#')
for q in "verb=Identify" "verb=ListMetadataFormats" "verb=ListSets" "verb=ListIdentifiers&metadataPrefix=oai_dc" \
         "verb=ListRecords&metadataPrefix=oai_dc" "verb=GetRecord&metadataPrefix=oai_dc&identifier=oai:$HOST:1" \
         "verb=Bogus" "verb=ListRecords&metadataPrefix=nope" "verb=ListRecords&resumptionToken=bad"; do
  curl -s "$BASE/oai?$q" > "$TMP/oai.xml"
  xmllint --noout --schema "$XSD" "$TMP/oai.xml" 2>/dev/null && pass "Valid XSD: $q" || fail "Tidak valid XSD: $q"
done
page_has "$BASE/oai?verb=GetRecord&metadataPrefix=oai_dc&identifier=oai:$HOST:2" 'code="idDoesNotExist"' \
  && pass "Rekaman tersembunyi (opac_hide) tidak terekspos" || fail "Rekaman tersembunyi terekspos"
page_has "$BASE/oai?verb=GetRecord&metadataPrefix=oai_dc&identifier=oai:$HOST:1" '<dc:title>Skripsi &lt;Uji&gt; &amp; Analisis</dc:title>' \
  && pass "Judul ter-escape dengan benar" || fail "Judul tidak ter-escape dengan benar"

rm -rf "$TMP" "$JAR"
echo
if [ "$FAILS" -eq 0 ]; then echo "SEMUA PEMERIKSAAN LULUS"; else echo "$FAILS PEMERIKSAAN GAGAL"; fi
exit "$FAILS"
