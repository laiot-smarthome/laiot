#!/usr/bin/env bash
# 2026-10-29 — существующий сайт, безопасные пути и права APT без отключения подписей.
# 2026-10-29 — итоговые доступы, пути, приватность и повторная генерация памятки.
# 2026-10-28 — изолированные проверки мастера, без APT, сети и системных служб.
set -Eeuo pipefail
script=$(cd "$(dirname "$0")/.." && pwd)/tools/install-server.sh
source "$script"
test_directory=$(mktemp -d)
STATE=$test_directory
cleanup_test() {
    rm -f "$test_directory/one.done" "$test_directory/count" "$test_directory/details.log" \
        "$test_directory/failure.txt" "$test_directory/cleanup.done" \
        "$test_directory/cleanup-preview.txt" "$test_directory/purge" \
        "$test_directory/panel-password" "$test_directory/db-password" \
        "$test_directory/mqtt-password" "$test_directory/access.txt" \
        "$test_directory/terminal.txt" "$test_directory/report-output.txt" \
        "$test_directory/application/var/install.key" "$test_directory/application/var/installed.lock" \
        "$test_directory/application/.env"
    rm -f "$test_directory/apt-key.asc" "$test_directory/apt-source.list" \
        "$test_directory/child-mask" "$test_directory/fastpanel.sh" "$test_directory/site-trace" \
        "$test_directory/site-created" "$test_directory/answers" "$test_directory/terminal-panel.txt" \
        "$test_directory/data/link"
    if [[ -d $test_directory/data/busy ]]; then
        rm -f "$test_directory/data/busy/content"
        rmdir "$test_directory/data/busy"
    fi
    if [[ -d $test_directory/data ]]; then rmdir "$test_directory/data"; fi
    if [[ -d $test_directory/application/var ]]; then rmdir "$test_directory/application/var"; fi
    if [[ -d $test_directory/application ]]; then rmdir "$test_directory/application"; fi
    rmdir "$test_directory"
}
trap cleanup_test EXIT

for domain in mydomo.home.arpa home.example.ru a.b xn--e1afmkfd.ru; do
    valid_domain "$domain" || { echo "Не принят домен: $domain"; exit 1; }
done
for domain in '-a.ru' 'a-.ru' 'a..ru' 'a.ru/' 'a.ru.' 'a b.ru' '127.0.0.1' 'https://a.ru' 'a;reboot.ru'; do
    if valid_domain "$domain"; then echo "Принят некорректный домен: $domain"; exit 1; fi
done
for address in 192.168.1.10 10.0.0.1 172.16.0.2; do valid_ip "$address"; done
for address in 999.1.1.1 1.2.3 1.2.3.4.5 '1.2.3.4;reboot'; do
    if valid_ip "$address"; then echo "Принят некорректный IP: $address"; exit 1; fi
done
for package in libreoffice-writer thunderbird gnome-mines libreoffice-core:amd64; do optional_package "$package"; done
for package in ubuntu-desktop openssh-server network-manager linux-image-generic systemd snapd redis-server; do
    if optional_package "$package"; then echo "Опасный пакет в очистке: $package"; exit 1; fi
done

# Повторный запуск не выполняет уже завершённый этап.
increment() { printf 'run\n' >> "$STATE/count"; }
run_step one test increment
run_step one test increment
[[ $(wc -l < "$STATE/count") == 1 ]]
if bash -c 'source "$1"; STATE="$2"; run_step broken test bash -c "exit 7"' _ "$script" "$STATE" > "$STATE/failure.txt" 2>&1; then
    echo 'Ошибка этапа не остановила установку'; exit 1
fi
[[ ! -e $STATE/broken.done ]]

# APT и ввод подменены: тест никогда не удаляет настоящие пакеты.
dpkg-query() { printf 'ii libreoffice-writer\nii openssh-server\n'; }
apt-get() {
    if [[ $1 == --simulate ]]; then printf 'Remv libreoffice-writer [1]\nRemv ubuntu-desktop [1]\n'
    else touch "$STATE/purge"; fi
}
cleanup_apps
[[ ! -e $STATE/purge ]]
rm "$STATE/cleanup.done"

apt-get() {
    if [[ $1 == --simulate ]]; then printf 'Remv libreoffice-writer [1]\n'
    else touch "$STATE/purge"; fi
}
ask() { printf -v "$1" '%s' нет; }
cleanup_apps
[[ ! -e $STATE/purge ]]
rm "$STATE/cleanup.done"
ask() { printf -v "$1" '%s' да; }
cleanup_apps
[[ -f $STATE/purge ]]

# Таблица с пустыми aliases разбирается по заголовкам, а не номерам слов.
table_header() { printf '%-8s%-32s%-32s%-24s%-12s%-16s%-24s%s\n' ID SERVER_NAME ALIASES OWNER MODE PHP_VERSION IPS DOCUMENT_ROOT; }
table_site() { printf '%-8s%-32s%-32s%-24s%-12s%-16s%-24s%s\n' 7 mydomo.home.arpa '' siteuser php_fpm 83 192.168.1.10 /var/www/siteuser/data/www/mydomo.home.arpa; }
parsed=$({ table_header; table_site; } | site_rows)
[[ $(cut -f2 <<< "$parsed") == mydomo.home.arpa ]]
[[ $(cut -f3 <<< "$parsed") == siteuser ]]
[[ $(cut -f4 <<< "$parsed") == /var/www/siteuser/data/www/mydomo.home.arpa ]]
if site_rows <<< 'unexpected format'; then echo 'Принят неизвестный формат таблицы'; exit 1; fi

# Ни публичные корни, ни непустые каталоги, ни ссылки использовать нельзя.
mkdir -p "$STATE/data/busy"
touch "$STATE/data/busy/content"
ln -s "$STATE/data/busy" "$STATE/data/link"
valid_base "$STATE/data/new" "$STATE/data" "$STATE/data/www"
for rejected in "$STATE/data/../escape" "$STATE/data/busy" "$STATE/data/link/new" "$STATE/data/www/app" "$STATE/data" "$STATE/data2/app"; do
    if valid_base "$rejected" "$STATE/data" "$STATE/data/www"; then echo "Принят опасный путь $rejected"; exit 1; fi
done
valid_owner siteuser
if valid_owner root || valid_owner 'user;id'; then echo 'Принят опасный пользователь'; exit 1; fi

printf 'public-key' > "$STATE/apt-key.asc"
printf 'deb repository' > "$STATE/apt-source.list"
chmod 600 "$STATE/apt-key.asc" "$STATE/apt-source.list"
repair_panel_apt "$STATE/apt-key.asc" "$STATE/apt-source.list"
[[ $(stat -c %a "$STATE/apt-key.asc") == 644 && $(stat -c %a "$STATE/apt-source.list") == 644 ]]
[[ $(< "$STATE/apt-key.asc") == public-key ]]
(
    # Официальный установщик и APT подменены; проверяется наследование umask.
    repair_panel_apt() { :; }
    apt-get() { :; }
    curl() { touch "$STATE/fastpanel.sh"; }
    bash() { umask > "$STATE/child-mask"; }
    mogwai() { :; }
    exec 3> "$STATE/terminal-panel.txt"
    umask 077
    panel
    [[ $(umask) == 0077 && $(< "$STATE/child-mask") == 0022 ]]
    [[ $(stat -c %a "$STATE/fastpanel.sh") == 600 ]]
)
(
    # Существующий сайт не создаётся заново и не меняет учётную запись.
    DOMAIN=mydomo.home.arpa OWNER=siteuser TARGET_MODE=existing
    mogwai() {
        [[ $* == 'sites list' ]] || { echo 'Изменение готового сайта запрещено в тесте'; return 1; }
        table_header; table_site
    }
    id() { if [[ $1 == -gn ]]; then printf 'sitegroup\n'; else printf '1001\n'; fi; }
    valid_base() { return 0; }
    install() { printf '%s\n' "$*" >> "$STATE/site-trace"; }
    save_answers() { :; }
    site
    grep -Fq -- '-g sitegroup' "$STATE/site-trace"
)
(
    # Новый сайт под готовым владельцем создаётся без --create-user.
    DOMAIN=mydomo.home.arpa OWNER=siteuser TARGET_MODE=new SERVER_IP=192.168.1.10
    mogwai() {
        if [[ $* == 'sites list' ]]; then
            table_header
            if [[ -f $STATE/site-created ]]; then table_site; fi
        else printf '%s\n' "$*" >> "$STATE/site-trace"; touch "$STATE/site-created"; fi
    }
    id() { if [[ ${1:-} == -gn ]]; then printf 'sitegroup\n'; else printf '1001\n'; fi; }
    valid_base() { return 0; }
    install() { :; }
    save_answers() { :; }
    site
    grep -Fq -- 'sites create --server-name=mydomo.home.arpa --owner=siteuser' "$STATE/site-trace"
    ! grep -Fq -- '--create-user' "$STATE/site-trace"
)
rm "$STATE/site-created" "$STATE/site-trace"
(
    DOMAIN=mydomo.home.arpa OWNER=siteuser TARGET_MODE=new SERVER_IP=192.168.1.10
    mogwai() {
        if [[ $* == 'sites list' ]]; then
            table_header
            if [[ -f $STATE/site-created ]]; then table_site; fi
        else printf '%s\n' "$*" >> "$STATE/site-trace"; touch "$STATE/site-created"; fi
    }
    id() { if [[ ${1:-} == -gn ]]; then printf 'sitegroup\n'; else return 1; fi; }
    valid_base() { return 0; }
    install() { :; }
    save_answers() { :; }
    site
    grep -Fq -- '--create-user' "$STATE/site-trace"
)
(
    OWNER=siteuser SITE_GROUP=sitegroup BASE=/var/www/siteuser/data/apps/home APP=/var/www/siteuser/data/apps/home/current
    BRANCH='beta; echo never-execute' PANEL_EXISTING=yes
    save_answers
    [[ $(stat -c %a "$STATE/answers") == 600 ]]
    source "$STATE/answers"
    [[ $BRANCH == 'beta; echo never-execute' && $OWNER == siteuser && $PANEL_EXISTING == yes ]]
)
# Используются только вымышленные доступы; настоящие .env и службы не читаются.
APP=$test_directory/application
BASE=$test_directory/base
DOMAIN=mydomo.home.arpa
SERVER_IP=192.168.1.10
SCHEME=http
MQTT=yes
MQTT_LAN=yes
ZIGBEE=yes
BROKER=mqtt://127.0.0.1:1883
mkdir -p "$APP/var"
printf 'test-panel-password\n' > "$STATE/panel-password"
printf 'test-db-password\n' > "$STATE/db-password"
printf 'test-mqtt-password\n' > "$STATE/mqtt-password"
printf 'test-install-key\n' > "$APP/var/install.key"
printf 'APP_KEY=must-not-be-in-report\n' > "$APP/.env"
exec 3> "$STATE/terminal.txt"
credentials > "$STATE/report-output.txt"
grep -Fq "http://$DOMAIN/install" "$STATE/access.txt"
grep -Fq 'test-install-key' "$STATE/access.txt"
[[ $(stat -c %a "$STATE/access.txt") == 600 ]]
cmp -s "$STATE/access.txt" "$STATE/terminal.txt"

touch "$APP/var/installed.lock"
# Даже если одноразовый ключ остался после сбоя, итоговая памятка его не показывает.
exec 3> "$STATE/terminal.txt"
credentials > "$STATE/report-output.txt"
grep -Fq "http://$DOMAIN/login" "$STATE/access.txt"
for expected in test-panel-password test-db-password test-mqtt-password "$APP/var/files/modules/<id>" "$APP/.env" 'journalctl' 'zigbee2mqtt.service'; do
    grep -Fq "$expected" "$STATE/access.txt"
done
! grep -Fq 'test-install-key' "$STATE/access.txt"
! grep -Fq 'must-not-be-in-report' "$STATE/access.txt"
! grep -Fq 'test-db-password' "$STATE/details.log"
! grep -Fq 'test-db-password' "$STATE/report-output.txt"
cmp -s "$STATE/access.txt" "$STATE/terminal.txt"

MQTT=no
ZIGBEE=no
exec 3> "$STATE/terminal.txt"
credentials > "$STATE/report-output.txt"
! grep -Fq 'test-mqtt-password' "$STATE/access.txt"
! grep -Fq 'Zigbee2MQTT:' "$STATE/access.txt"
PANEL_EXISTING=yes
exec 3> "$STATE/terminal.txt"
credentials > "$STATE/report-output.txt"
! grep -Fq 'test-panel-password' "$STATE/access.txt"
grep -Fq 'прежние данные входа' "$STATE/access.txt"
exec 3>&-

echo 'PASS: адреса, очистка, повторный запуск, готовый/новый сайт, пути, группа владельца, umask FastPanel, права ключа APT и приватность памятки.'
