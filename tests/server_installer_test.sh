#!/usr/bin/env bash
# 2026-10-29 — независимый вывод ошибок, окружение APT и обнаружение PHP.
# 2026-10-29 — swap: RAM, существующий объём, повтор/перезагрузка и отказ при нехватке диска.
# 2026-10-29 — существующий сайт, безопасные пути и права APT без отключения подписей.
# 2026-10-29 — итоговые доступы, пути, приватность и повторная генерация памятки.
# 2026-10-28 — изолированные проверки мастера, без APT, сети и системных служб.
set -Eeuo pipefail
script=$(cd "$(dirname "$0")/.." && pwd)/tools/install-server.sh
source "$script"
test_directory=$(mktemp -d)
STATE=$test_directory
cleanup_test() {
    if [[ -d $test_directory/swap ]]; then
        rm -f "$test_directory/swap/"{meminfo,fstab,swapfile,swap-plan,swap-plan.new,swap-summary,fstab.before-swap,calls,enabled,formatted,details.log}
        rmdir "$test_directory/swap"
    fi
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
        "$test_directory/failure-visible.txt" "$test_directory/apt-environment.txt" \
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

[[ $(swap_target_mib 524288) == 2048 ]]
[[ $(swap_target_mib 2097152) == 2048 ]]
[[ $(swap_target_mib 3900000) == 4096 ]]
[[ $(swap_target_mib 9437184) == 8192 ]]
[[ $(ipv4_network 192.168.74.10/24) == 192.168.74.0/24 ]]
[[ $(ipv4_network 10.20.30.40/16) == 10.20.0.0/16 ]]
[[ $(ipv4_network 172.19.0.3/32) == 172.19.0.3/32 ]]
if ipv4_network 192.168.0.1/33 || ipv4_network 300.168.0.1/24; then echo 'Принята неверная подсеть'; exit 1; fi
lan_fixture=$(printf '%s\n' \
    '2: enp1s0 inet 192.168.74.10/24 scope global' \
    '3: enp2s0 inet 177.203.58.73/24 scope global' \
    '4: docker0 inet 172.17.0.1/16 scope global' \
    '5: br-abcdef inet 172.19.0.1/16 scope global' \
    '6: wlan0 inet 10.20.30.40/16 scope global' | lan_addresses)
[[ $lan_fixture == $'10.20.30.40/16\n192.168.74.10/24' ]]
(
    STATE=$test_directory/swap
    mkdir "$STATE"
    file=$STATE/swapfile
    reset_swap_fixture() {
        rm -f "$STATE/"{swapfile,swap-plan,swap-summary,fstab.before-swap,calls,enabled,formatted,details.log}
        printf 'MemTotal:       2097152 kB\n' > "$STATE/meminfo"
        printf '# keep this line\n/dev/root / ext4 defaults 0 1\n' > "$STATE/fstab"
        EXISTING_BYTES=$((2046 * 1048576))
        LOW_DISK=no
        SWAPON_FAIL=no
    }
    # Меняются только файлы фикстуры. Реальный swapon/systemctl никогда не вызываются.
    swapon() {
        case "$*" in
            '--show=SIZE --bytes --noheadings --raw')
                printf '%s\n' "$EXISTING_BYTES"
                if [[ -f $STATE/enabled ]]; then stat -c %s "$file"; fi ;;
            '--show=NAME --noheadings --raw')
                printf '/dev/existing\n'
                if [[ -f $STATE/enabled ]]; then printf '%s\n' "$file"; fi ;;
            "-- $file")
                printf 'swapon\n' >> "$STATE/calls"
                [[ $SWAPON_FAIL == no ]] || return 1
                touch "$STATE/enabled" ;;
            *) echo 'Неожиданный вызов swapon'; return 1 ;;
        esac
    }
    blkid() { if [[ -f $STATE/formatted ]]; then printf 'swap\n'; else return 2; fi; }
    findmnt() { printf 'ext4\n'; }
    df() { printf 'Avail\n'; if [[ $LOW_DISK == yes ]]; then printf '0\n'; else printf '10737418240\n'; fi; }
    mkswap() { printf 'mkswap\n' >> "$STATE/calls"; touch "$STATE/formatted"; }
    systemctl() { [[ $* == daemon-reload ]]; }
    reset_swap_fixture
    configure_swap "$STATE/meminfo" "$file" "$STATE/fstab"
    [[ $(stat -c %s "$file") == $((2 * 1048576)) && $(stat -c %a "$file") == 600 ]]
    [[ $(grep -c '^mkswap$' "$STATE/calls") == 1 ]]
    configure_swap "$STATE/meminfo" "$file" "$STATE/fstab"
    [[ $(grep -c '^swapon$' "$STATE/calls") == 1 ]]
    [[ $(grep -Fc "$file none swap sw,nofail 0 0" "$STATE/fstab") == 1 ]]
    grep -Fq '# keep this line' "$STATE/fstab"
    # После перезагрузки готовый файл активируется без повторного форматирования.
    rm "$STATE/enabled"
    configure_swap "$STATE/meminfo" "$file" "$STATE/fstab"
    [[ $(grep -c '^mkswap$' "$STATE/calls") == 1 && $(grep -c '^swapon$' "$STATE/calls") == 2 ]]
    rm "$STATE/enabled"
    sed -i 's/sw,nofail/noauto/' "$STATE/fstab"
    if (configure_swap "$STATE/meminfo" "$file" "$STATE/fstab"); then echo 'Проигнорирован noauto'; exit 1; fi
    [[ $(grep -c '^swapon$' "$STATE/calls") == 2 && ! -e $STATE/enabled ]]
    reset_swap_fixture
    EXISTING_BYTES=$((4096 * 1048576))
    configure_swap "$STATE/meminfo" "$file" "$STATE/fstab"
    [[ ! -e $file && ! -e $STATE/calls && ! -e $STATE/fstab.before-swap ]]
    reset_swap_fixture
    printf 'foreign contents\n' > "$file"
    if (configure_swap "$STATE/meminfo" "$file" "$STATE/fstab"); then echo 'Чужой файл swap заменён'; exit 1; fi
    [[ $(< "$file") == 'foreign contents' && ! -e $STATE/calls ]]
    reset_swap_fixture
    LOW_DISK=yes
    if (configure_swap "$STATE/meminfo" "$file" "$STATE/fstab"); then echo 'Не проверено место на диске'; exit 1; fi
    [[ ! -e $STATE/calls && ! -e $STATE/fstab.before-swap ]]
    # Если администратор уже добавил swap после ошибки, старый план не создаёт лишний.
    EXISTING_BYTES=$((4096 * 1048576))
    configure_swap "$STATE/meminfo" "$file" "$STATE/fstab"
    [[ ! -e $STATE/calls && ! -e $STATE/fstab.before-swap ]]
    reset_swap_fixture
    SWAPON_FAIL=yes
    if (configure_swap "$STATE/meminfo" "$file" "$STATE/fstab"); then echo 'Сбой swapon не остановил этап'; exit 1; fi
    [[ ! -e $STATE/enabled && ! -e $STATE/fstab.before-swap ]]
    SWAPON_FAIL=no
    configure_swap "$STATE/meminfo" "$file" "$STATE/fstab"
    [[ -f $STATE/enabled && $(grep -c '^mkswap$' "$STATE/calls") == 1 ]]
)

for domain in laiot.home.arpa home.example.ru a.b xn--e1afmkfd.ru; do
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
if bash -c 'source "$1"; STATE="$2"; exec 4>"$STATE/failure-visible.txt"; UI_FD=4; trap failed ERR; broken_step() { echo "APT test failure" >&2; false; }; run_step hidden "Установка пакетов" broken_step' _ "$script" "$STATE" > "$STATE/failure.txt" 2>&1; then
    echo 'Ошибка вложенного этапа не остановила мастер'; exit 1
fi
grep -Fq 'Не удалось завершить: Установка пакетов' "$STATE/failure-visible.txt"
grep -Fq 'APT test failure' "$STATE/details.log"
[[ ! -e $STATE/hidden.done ]]
(
    export TMPDIR=/root/private TMP=/root/private TEMP=/root/private
    apt-get() { printf '%s %s %s %s' "$(umask)" "${TMPDIR-unset}" "${TMP-unset}" "${TEMP-unset}" > "$STATE/apt-environment.txt"; }
    system_apt update
    [[ $(< "$STATE/apt-environment.txt") == '0022 unset unset unset' ]]
    [[ $TMPDIR == /root/private && $(umask) == 0077 ]]
)
PHP_VERSION=8.3

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
table_site() { printf '%-8s%-32s%-32s%-24s%-12s%-16s%-24s%s\n' 7 laiot.home.arpa '' siteuser php_fpm 83 192.168.1.10 /var/www/siteuser/data/www/laiot.home.arpa; }
parsed=$({ table_header; table_site; } | site_rows)
[[ $(cut -f2 <<< "$parsed") == laiot.home.arpa ]]
[[ $(cut -f3 <<< "$parsed") == siteuser ]]
[[ $(cut -f4 <<< "$parsed") == /var/www/siteuser/data/www/laiot.home.arpa ]]
if site_rows <<< 'unexpected format'; then echo 'Принят неизвестный формат таблицы'; exit 1; fi

# Ни публичные корни, ни непустые каталоги, ни ссылки использовать нельзя.
mkdir -p "$STATE/data/busy"
touch "$STATE/data/busy/content"
ln -s "$STATE/data/busy" "$STATE/data/link"
valid_base "$STATE/data/new" "$STATE/data" "$STATE/data/www"
valid_base "$STATE/data/busy" "$STATE/data" "$STATE/data/busy" "$STATE/data/busy"
touch "$STATE/data/busy/composer.json"
if valid_base "$STATE/data/busy" "$STATE/data" "$STATE/data/busy" "$STATE/data/busy"; then echo 'Принята другая PHP-установка'; exit 1; fi
rm "$STATE/data/busy/composer.json"
if valid_base "$STATE/data/busy" "$STATE/data" "$STATE/data/busy" "$STATE/data/other"; then echo 'Принят корень чужого сайта'; exit 1; fi
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
    mogwai() { [[ $(umask) == 0022 && ! ${TMPDIR+x} && ! ${TMP+x} && ! ${TEMP+x} ]]; }
    export TMPDIR=/private/root TMP=/private/root TEMP=/private/root
    panel_cli sites create
    [[ $(umask) == 0077 && $TMPDIR == /private/root ]]
)
(
    # Существующий сайт не создаётся заново и не меняет учётную запись.
    DOMAIN=laiot.home.arpa OWNER=siteuser TARGET_MODE=existing
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
    DOMAIN=laiot.home.arpa OWNER=siteuser TARGET_MODE=new SERVER_IP=192.168.1.10
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
    grep -Fq -- 'sites create --server-name=laiot.home.arpa --owner=siteuser' "$STATE/site-trace"
    ! grep -Fq -- '--create-user' "$STATE/site-trace"
)
rm "$STATE/site-created" "$STATE/site-trace"
(
    DOMAIN=laiot.home.arpa OWNER=siteuser TARGET_MODE=new SERVER_IP=192.168.1.10
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
    OWNER=siteuser SITE_GROUP=sitegroup BASE=/var/www/siteuser/data/www/home APP=/var/www/siteuser/data/www/home
    BRANCH='beta; echo never-execute' PANEL_EXISTING=yes
    save_answers
    [[ $(stat -c %a "$STATE/answers") == 600 ]]
    source "$STATE/answers"
    [[ $BRANCH == 'beta; echo never-execute' && $OWNER == siteuser && $PANEL_EXISTING == yes ]]
)
# Используются только вымышленные доступы; настоящие .env и службы не читаются.
APP=$test_directory/application
BASE=$test_directory/base
DOMAIN=laiot.home.arpa
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
rm "$STATE/panel-password"
exec 3> "$STATE/terminal.txt"
credentials > "$STATE/report-output.txt"
! grep -Fq 'test-panel-password' "$STATE/access.txt"
grep -Fq 'прежние данные входа' "$STATE/access.txt"
exec 3>&-

echo 'PASS: swap по RAM, диск, повтор/перезагрузка, чужие файлы, адреса, очистка, сайты, пути, umask/APT и приватность памятки.'
