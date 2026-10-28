#!/usr/bin/env bash
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
exec 3>&-

echo 'PASS: адреса, очистка, повторный запуск, ошибки этапов, итоговые доступы, пути и приватность памятки.'
