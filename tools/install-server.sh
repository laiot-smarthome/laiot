#!/usr/bin/env bash
# 2026-10-29 — видимые ошибки, цвета, безопасное окружение APT и обнаружение PHP.
# 2026-10-29 — автоматический swap по RAM до установки панели, без замены чужого swap.
# 2026-10-29 — готовая FastPanel, выбор сайта/каталога и права публичного ключа APT.
# 2026-10-29 — итоговая памятка с доступами и путями повторяется после запуска служб.
# 2026-10-28 — интерактивная подготовка Ubuntu для веб-установщика LaIOT.
set -Eeuo pipefail
umask 077

REPOSITORY=https://github.com/laiot-smarthome/laiot.git
BRANCH=beta
STATE=/root/laiot-installer
OWNER=laiot_usr
BASE=/var/www/laiot_usr/data/laiot
APP=$BASE/current
SITE_GROUP=$OWNER
PANEL_EXISTING=no
TARGET_MODE=new
STEP='подготовка'
PHP_BIN=''
PHP_VERSION=''
UI_FD=2
UI_COLOR=no

ui_line() {
    local kind=$1 message=$2 output=${3:-1} color=''
    if [[ $UI_COLOR == yes && -t $output ]]; then
        case "$kind" in question) color=$'\033[1;36m' ;; success) color=$'\033[1;32m' ;; warning) color=$'\033[1;33m' ;; error) color=$'\033[1;31m' ;; *) color=$'\033[1;34m' ;; esac
        printf '\n%s%b\033[0m\n' "$color" "$message" >&"$output"
    else printf '\n%b\n' "$message" >&"$output"; fi
}
say() { ui_line info "$*"; }
die() { ui_line error "Остановка: $*" "$UI_FD"; exit 1; }
ask() {
    local input_value
    ui_line question "$2" 3
    printf 'Ответ [%s]: ' "$3" >&3
    if [[ $UI_COLOR == yes ]]; then printf '\033[1;32m' >&3; fi
    IFS= read -r input_value <&3 || die 'Ввод закрыт. Запустите мастер снова в SSH.'
    if [[ $UI_COLOR == yes ]]; then printf '\033[0m' >&3; fi
    printf -v "$1" '%s' "${input_value:-$3}"
}
yesno() {
    local choice_value
    while :; do
        ask choice_value "$2 (да/нет)" "$3"
        case "$choice_value" in
            да|д|yes|y|Y) printf -v "$1" '%s' yes; return ;;
            нет|н|no|n|N) printf -v "$1" '%s' no; return ;;
            *) say 'Введите да или нет.' ;;
        esac
    done
}
pause() {
    local answer
    ask answer "$1. Нажмите Enter для продолжения" готово
}
valid_domain() {
    local label
    [[ ${#1} -le 253 && $1 == *.* && $1 != *[!a-z0-9.-]* ]] || return 1
    [[ $1 != *..* && $1 != *. ]] || return 1
    local -a labels
    IFS=. read -ra labels <<< "$1"
    for label in "${labels[@]}"; do
        [[ ${#label} -le 63 && $label =~ ^[a-z0-9]([a-z0-9-]*[a-z0-9])?$ ]] || return 1
    done
    [[ ! $1 =~ ^[0-9.]+$ ]]
}
valid_ip() {
    local part
    local -a parts
    [[ $1 =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]] || return 1
    IFS=. read -ra parts <<< "$1"
    for part in "${parts[@]}"; do
        [[ ${#part} -le 3 ]] && (( 10#$part <= 255 )) || return 1
    done
}
secret() { openssl rand -hex 24; }
checkpoint() { touch "$STATE/$1.done"; }
run_step() {
    local id=$1 title=$2
    shift 2
    STEP=$title
    if [[ -f $STATE/$id.done ]]; then
        ui_line success "✓ $title — уже выполнено"
        return
    fi
    say "→ $title. Это может занять несколько минут."
    "$@" >> "$STATE/details.log" 2>&1
    checkpoint "$id"
    ui_line success "✓ $title"
}
failed() {
    local status=$?
    trap - ERR
    # Отдельный дескриптор не наследует перенаправление run_step в журнал.
    ui_line error "Не удалось завершить: $STEP (код $status)." "$UI_FD"
    printf 'Подробности: %s/details.log\n' "$STATE" >&"$UI_FD"
    printf 'Исправьте причину и запустите мастер снова: завершённые этапы сохраняются.\n' >&"$UI_FD"
    exit 1
}
as_owner() { (umask 022; runuser -u "$OWNER" -- env -u TMPDIR PATH=/opt/laiot-node/bin:/usr/local/bin:/usr/bin:/bin "$@"); }
system_apt() (
    # Пакетные скрипты запускают mysql/_apt: приватный TMPDIR root им недоступен.
    umask 022
    unset TMPDIR TMP TEMP
    export DEBIAN_FRONTEND=noninteractive
    apt-get "$@"
)
detect_php() {
    local candidate info
    local -a candidates=()
    if command -v php >/dev/null; then candidates+=("$(command -v php)"); fi
    candidates+=(/usr/bin/php[0-9]* /opt/php*/bin/php)
    for candidate in "${candidates[@]}"; do
        [[ -x $candidate ]] || continue
        if info=$("$candidate" -r 'if (PHP_SAPI !== "cli" || PHP_VERSION_ID < 80200) exit(1); echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;'); then
            [[ $info =~ ^[0-9]+\.[0-9]+$ ]] || continue
            PHP_BIN=$(readlink -f -- "$candidate")
            [[ $PHP_BIN =~ ^/[a-zA-Z0-9_./-]+$ ]] || continue
            PHP_VERSION=$info
            return 0
        fi
    done
    return 1
}
choose_php() {
    STEP='проверка установленного PHP'
    while ! detect_php; do
        ui_line warning 'PHP 8.2 или новее для приложения пока не найден. В FastPanel откройте «Приложения» и установите подходящий PHP.'
        pause 'После установки PHP вернитесь сюда'
    done
    ui_line success "Используем установленный PHP $PHP_VERSION."
    save_answers
}

swap_target_mib() {
    local ram_kib=$1 gib
    [[ $ram_kib =~ ^[1-9][0-9]*$ && ${#ram_kib} -le 12 ]] || die 'Не удалось определить объём оперативной памяти.'
    gib=$(( (ram_kib + 1048575) / 1048576 ))
    if (( gib < 2 )); then gib=2; fi
    if (( gib > 8 )); then gib=8; fi
    printf '%s\n' "$((gib * 1024))"
}
swap_total_bytes() {
    local sizes
    sizes=$(swapon --show=SIZE --bytes --noheadings --raw)
    awk '{total += $1} END {printf "%.0f\n", total}' <<< "$sizes"
}
persist_swap() {
    local file=$1 fstab=$2 check_only=${3:-no} entries
    [[ -f $fstab && ! -L $fstab ]] || die 'Для сохранения swap нужен обычный файл /etc/fstab.'
    entries=$(awk -v file="$file" '$1 == file {print $3 " " $4}' "$fstab")
    if [[ -n $entries ]]; then
        [[ $entries == 'swap '* && $entries != *$'\n'* ]] || die 'Конфликт записей swap в fstab.'
        [[ ,${entries#swap }, != *,noauto,* ]] || die 'Swap отключён через noauto в fstab. Измените это вручную.'
        return
    fi
    [[ $check_only != check ]] || return 0
    if [[ ! -e $STATE/fstab.before-swap ]]; then cp -p -- "$fstab" "$STATE/fstab.before-swap"; fi
    # Сохраняем все чужие строки; nofail не блокирует загрузку при проблеме с файлом.
    printf '\n%s none swap sw,nofail 0 0\n' "$file" >> "$fstab"
    systemctl daemon-reload
}
configure_swap() {
    local meminfo=${1:-/proc/meminfo} file=${2:-/swapfile.laiot} fstab=${3:-/etc/fstab}
    local target active names planned size type status free_bytes allocated needed fs
    local mib=1048576 reserve=5368709120
    STEP='настройка swap'
    target=$(swap_target_mib "$(awk '$1 == "MemTotal:" {print $2}' "$meminfo")")
    active=$(swap_total_bytes)
    names=$(swapon --show=NAME --noheadings --raw)
    # Заголовок swap занимает страницу: допуск 1 MiB предотвращает лишние файлы при повторе.
    if (( active + mib >= target * mib )) && { [[ ! -f $STATE/swap-plan ]] || ! grep -Fxq -- "$file" <<< "$names"; }; then
        printf 'Swap: уже достаточно, активно %s MiB, цель %s MiB.\n' "$((active / mib))" "$target" | tee "$STATE/swap-summary"
        return
    fi
    [[ $file =~ ^/[a-zA-Z0-9_./-]+$ && $(realpath -m -- "$file") == "$file" ]] || die 'Неподходящий путь файла swap.'
    # noauto и конфликтующие записи проверяем до активации, не меняя решение администратора.
    persist_swap "$file" "$fstab" check
    if [[ -f $STATE/swap-plan ]]; then
        [[ $(sed -n '1p' "$STATE/swap-plan") == "$file" ]] || die 'Сохранённый путь swap отличается от текущего.'
        planned=$(sed -n '2p' "$STATE/swap-plan")
        [[ $planned =~ ^[1-9][0-9]*$ && ${#planned} -le 4 && $planned -le 8192 ]] || die 'Повреждён план настройки swap.'
    else
        [[ ! -e $file && ! -L $file ]] || die "Файл $file уже существует и не создан этим мастером. Не перезаписываю его."
        planned=$(( (target * mib - active + mib - 1) / mib ))
        printf '%s\n%s\n' "$file" "$planned" > "$STATE/swap-plan.new"
        mv -- "$STATE/swap-plan.new" "$STATE/swap-plan"
    fi
    if [[ ! -e $file && ! -L $file ]]; then (set -o noclobber; : > "$file"); fi
    [[ -f $file && ! -L $file && $(stat -c %u "$file") == 0 && $(stat -c %h "$file") == 1 ]] || die 'Файл swap должен быть обычным файлом root без ссылок.'
    chmod 600 -- "$file"
    if ! grep -Fxq -- "$file" <<< "$names"; then
        if type=$(blkid -p -s TYPE -o value -- "$file"); then
            [[ $type == swap && $(stat -c %s "$file") == "$((planned * mib))" ]] || die 'Файл swap имеет неожиданный формат или размер.'
        else
            status=$?
            [[ $status == 2 ]] || die 'Не удалось проверить формат файла swap.'
            fs=$(findmnt --noheadings --output FSTYPE --target "$(dirname "$file")")
            case "$fs" in ext2|ext3|ext4|xfs) ;; *) die "Автоматический файл swap не поддержан для $fs. Настройте swap средствами этой файловой системы." ;; esac
            free_bytes=$(df -B1 --output=avail -- "$(dirname "$file")" | awk 'NR == 2 {print $1}')
            [[ $free_bytes =~ ^[0-9]+$ ]] || die 'Не удалось проверить свободное место для swap.'
            allocated=$(( $(stat -c %b "$file") * 512 ))
            needed=$((planned * mib - allocated))
            if (( needed < 0 )); then needed=0; fi
            (( free_bytes >= needed + reserve )) || die "Для swap нужно ещё $((needed / mib)) MiB и не менее 5 GiB свободного места после создания."
            say "Создаём swap: $planned MiB. Существующие swap-разделы и файлы сохраняются."
            # dd выделяет реальные блоки, в отличие от файлов с дырками и ряда fallocate-реализаций.
            dd if=/dev/zero of="$file" bs=1M count="$planned" conv=fsync status=none >> "$STATE/details.log" 2>&1
            mkswap -- "$file" >> "$STATE/details.log" 2>&1
        fi
        swapon -- "$file" >> "$STATE/details.log" 2>&1 || die "Не удалось включить $file. Проверьте журнал; повторный запуск сохранит существующий swap."
    fi
    persist_swap "$file" "$fstab"
    active=$(swap_total_bytes)
    (( active + mib >= target * mib )) || die 'Объём swap ниже требуемого. Активный файл автоматически не расширяется и не отключается.'
    printf 'Swap: активно %s MiB, цель %s MiB; файл %s, размер %s MiB; автозапуск в fstab.\n' "$((active / mib))" "$target" "$file" "$planned" | tee "$STATE/swap-summary"
}

save_answers() {
    local name
    local names=(MODE DOMAIN SERVER_IP MQTT MQTT_LAN ZIGBEE BROKER BRANCH CLEANUP PANEL_EXISTING TARGET_MODE OWNER SITE_GROUP BASE APP SCHEME PHP_BIN PHP_VERSION)
    for name in "${names[@]}"; do printf '%s=%q\n' "$name" "${!name-}"; done > "$STATE/answers.new"
    mv -- "$STATE/answers.new" "$STATE/answers"
}
# Пустые aliases в таблице не должны сдвигать колонку владельца.
site_rows() {
    LC_ALL=C expand | awk '
        function trim(v) {sub(/^[ \t]+/, "", v); sub(/[ \t]+$/, "", v); return v}
        /SERVER_NAME/ && /DOCUMENT_ROOT/ {
            name=index($0,"SERVER_NAME"); alias=index($0,"ALIASES"); owner=index($0,"OWNER");
            mode=index($0,"MODE"); ips=index($0,"IPS"); root=index($0,"DOCUMENT_ROOT");
            if (!(name && alias && owner && mode && ips && root)) exit 1;
            header=1; next
        }
        header && $1 ~ /^[0-9]+$/ {
            if (trim(substr($0,root)) !~ /^\//) exit 1;
            print $1 "\t" trim(substr($0,name,alias-name)) "\t" trim(substr($0,owner,mode-owner)) "\t" trim(substr($0,root)) "\t" trim(substr($0,ips,root-ips))
        }
        END {if (!header) exit 1}'
}
valid_owner() { [[ $1 =~ ^[a-z_][a-z0-9_-]{0,63}$ && $1 != root ]]; }
valid_base() {
    local candidate=$1 data=$2 root
    [[ $candidate == "$data/"* && $candidate =~ ^/[a-zA-Z0-9_./-]+$ ]] || return 1
    [[ $(realpath -m -- "$candidate") == "$candidate" ]] || return 1
    # Исходники, vendor и будущий .env не должны оказаться в открытом каталоге сайта.
    while IFS= read -r root; do
        [[ -n $root ]] || continue
        root=$(realpath -m -- "$root")
        [[ $candidate != "$root" && $candidate != "$root/"* && $root != "$candidate/"* ]] || return 1
    done <<< "${3:-}"
    [[ ! -e $candidate || -d $candidate && -z $(find "$candidate" -mindepth 1 -maxdepth 1 -print -quit) ]]
}
target_questions() {
    local choice listing rows row data default_base candidate root_list
    STEP='выбор сайта и каталога'
    listing=$(mogwai sites list)
    rows=$(site_rows <<< "$listing") || die 'Не удалось разобрать список сайтов FastPanel. Проверьте вывод mogwai sites list.'
    if [[ $PANEL_EXISTING == yes || -n $rows ]]; then
        say "$listing"
        while :; do
            ask choice '1 — выбрать существующий сайт, 2 — создать новый' 1
            case "$choice" in
                1)
                    [[ -n $rows ]] || { say 'В панели пока нет сайтов. Выберите создание нового.'; continue; }
                    ask choice 'ID сайта из списка выше' ''
                    row=$(awk -F '\t' -v id="$choice" '$1 == id' <<< "$rows")
                    [[ -n $row ]] || { say 'Такого ID нет в списке.'; continue; }
                    IFS=$'\t' read -r choice DOMAIN OWNER candidate root_list <<< "$row"
                    TARGET_MODE=existing
                    valid_owner "$OWNER" && id "$OWNER" >/dev/null 2>&1 || die 'У сайта неподходящий системный владелец.'
                    break ;;
                2) TARGET_MODE=new; break ;;
            esac
        done
    fi
    if [[ $TARGET_MODE == new ]]; then
        if [[ -z ${DOMAIN:-} ]]; then
            while :; do
                ask DOMAIN 'Домен нового сайта' "${DOMAIN:-laiot.home.arpa}"
                valid_domain "$DOMAIN" && break
                say 'Укажите корректное доменное имя.'
            done
        fi
        if awk -F '\t' -v domain="$DOMAIN" '$2 == domain {found=1} END {exit !found}' <<< "$rows"; then
            die 'Сайт с этим доменом уже есть. Выберите его в режиме существующего сайта.'
        fi
        say 'Можно указать существующего пользователя FastPanel или имя нового пользователя.'
        getent passwd | awk -F: '$3 != 0 && $6 ~ /^\/var\/www\// {print "  " $1 " — " $6}'
        while :; do
            ask OWNER 'Пользователь сайта (пароль не нужен)' laiot_usr
            valid_owner "$OWNER" && break
            say 'Нужно системное имя пользователя латиницей; root не допускается.'
        done
    fi
    valid_domain "$DOMAIN" || die 'Некорректный домен сайта.'
    [[ $MODE != public || $DOMAIN != *.home.arpa ]] || die 'Для публичного режима нужен зарегистрированный домен.'
    data="/var/www/$OWNER/data"
    if id "$OWNER" >/dev/null 2>&1; then
        [[ $(id -u "$OWNER") != 0 ]] || die 'Нельзя запускать сайт под UID 0.'
        [[ -d $data && $(stat -c %u "$data") == "$(id -u "$OWNER")" ]] || die 'Каталог data не принадлежит выбранному пользователю FastPanel.'
        SITE_GROUP=$(id -gn "$OWNER")
    else SITE_GROUP=$OWNER; fi
    default_base="$data/apps/$DOMAIN"
    root_list=$(cut -f4 <<< "$rows")
    # Даже для нового сайта стандартная папка www остаётся только публичным корнем.
    root_list+=$'\n'"$data/www"
    while :; do
        ask BASE 'Каталог установки (отдельный пустой каталог, вне www)' "$default_base"
        valid_base "$BASE" "$data" "$root_list" && break
        say "Нужен новый или пустой каталог внутри $data, вне корней сайтов и без символических ссылок."
    done
    APP=$BASE/current
    say "Сайт: $DOMAIN; владелец: $OWNER; каталог: $BASE\nВ панели корень сайта потребуется направить на $APP/public."
    yesno choice 'Использовать выбранный сайт и каталог?' нет
    [[ $choice == yes ]] || die 'Выбор отменён. Повторный запуск позволит выбрать заново.'
    save_answers
    checkpoint target
}
questions() {
    local choice default_ip
    say 'Добро пожаловать! Подготовим сервер умного дома за несколько шагов.'
    say 'Нужна Ubuntu 24.04 x86_64 с доступом в интернет: чистая или с готовой FastPanel.\nFastPanel и Redis обязательны. Настройку самого LaIOT завершим в браузере.'
    printf '\n1 — Только домашняя сеть (без белого IP и проброса портов)\n2 — Доступ по собственному публичному домену\n'
    while :; do
        ask choice 'Как будете открывать умный дом?' 1
        case "$choice" in 1) MODE=local; break ;; 2) MODE=public; break ;; esac
        say 'Выберите 1 или 2.'
    done
    if [[ $PANEL_EXISTING == no ]]; then
        while :; do
            ask DOMAIN 'Имя сайта, без http:// и пути' laiot.home.arpa
            valid_domain "$DOMAIN" && break
            say 'Нужно имя вида laiot.home.arpa или home.example.ru, латиницей.'
        done
        if [[ $MODE == public && $DOMAIN == *.home.arpa ]]; then
            die 'Для публичного режима нужен ваш зарегистрированный домен. Запустите мастер снова.'
        fi
    else DOMAIN=''; fi
    default_ip=$(hostname -I | awk '{print $1}')
    say 'Адреса этого сервера:'
    ip -o -4 addr show scope global | awk '{print $2, $4}'
    while :; do
        ask SERVER_IP 'IPv4 сервера, к которому привязать сайт' "$default_ip"
        if valid_ip "$SERVER_IP" && ip -o -4 addr show | awk '{print $4}' | cut -d/ -f1 | grep -Fxq "$SERVER_IP"; then break; fi
        say 'Выберите IPv4, назначенный этому серверу (список выше).'
    done
    if command -v mosquitto >/dev/null; then
        say 'Mosquitto уже установлен. Его конфигурация и пароль сохраняются; подключение задайте в веб-интерфейсе.'
        MQTT=no
    else yesno MQTT 'Установить Mosquitto для MQTT-устройств?' нет; fi
    MQTT_LAN=no
    if [[ $MQTT == yes && $SERVER_IP =~ ^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.) ]]; then
        yesno MQTT_LAN 'Разрешить MQTT-устройствам подключаться через домашнюю сеть с паролем?' да
    fi
    if [[ -e /opt/zigbee2mqtt || -e /etc/systemd/system/zigbee2mqtt.service ]]; then
        say 'Zigbee2MQTT уже установлен. Используйте существующий экземпляр, его файлы и службу сохраняем.'
        ZIGBEE=no
    else yesno ZIGBEE 'Установить Zigbee2MQTT для Zigbee-устройств? Нужен отдельный координатор' нет; fi
    yesno CLEANUP 'Предложить удалить необязательные программы Ubuntu (офис, почту, игры)?' нет
    if [[ $ZIGBEE == yes && $MQTT == no ]]; then
        say 'Zigbee2MQTT нужен MQTT-брокер. Можно использовать уже существующий.'
        ask BROKER 'Адрес существующего брокера, например mqtt://192.168.1.10:1883' mqtt://127.0.0.1:1883
        [[ $BROKER =~ ^mqtts?://[a-zA-Z0-9.:-]+$ ]] || die 'Некорректный адрес MQTT-брокера.'
    else BROKER=mqtt://127.0.0.1:1883; fi
    say "Итого: $DOMAIN → $SERVER_IP; ветка $BRANCH.\nFastPanel + Redis; Mosquitto: $MQTT; Zigbee2MQTT: $ZIGBEE."
    yesno choice 'Начать установку на этом сервере?' нет
    [[ $choice == yes ]] || exit 0
    install -d -m 700 "$STATE"
    # Файл принадлежит root; %q сохраняет ответы без исполнения введённого текста.
    save_answers
}
optional_package() {
    [[ ${1%%:*} =~ ^(libreoffice(-[a-z0-9.+-]+)?|thunderbird(-[a-z0-9.+-]+)?|aisleriot|gnome-mahjongg|gnome-mines|gnome-sudoku|rhythmbox(-[a-z0-9.+-]+)?|shotwell(-[a-z0-9.+-]+)?|simple-scan|transmission-gtk|totem(-[a-z0-9.+-]+)?|cheese(-[a-z0-9.+-]+)?|gnome-tour)$ ]]
}
cleanup_apps() {
    local package answer inventory removed status
    local -a candidates=() removal=()
    STEP='очистка необязательных программ'
    inventory=$(dpkg-query -W -f='${db:Status-Abbrev} ${binary:Package}\n')
    while read -r status package; do
        if [[ $status == ii ]] && optional_package "$package"; then candidates+=("$package"); fi
    done <<< "$inventory"
    if (( ${#candidates[@]} == 0 )); then
        say 'Предустановленных программ из списка очистки нет. Ничего удалять не нужно.'
        checkpoint cleanup
        return
    fi
    # Сначала APT вычисляет весь набор удаления: зависимости не должны расширить список.
    LC_ALL=C apt-get --simulate purge "${candidates[@]}" > "$STATE/cleanup-preview.txt"
    removed=$(awk '$1 == "Remv" || $1 == "Purg" {print $2}' "$STATE/cleanup-preview.txt")
    while read -r package; do
        [[ -n $package ]] || continue
        if ! optional_package "$package"; then
            say "Очистка отменена: APT также хочет удалить $package. Систему оставляем без изменений."
            checkpoint cleanup
            return
        fi
        removal+=("$package")
    done <<< "$removed"
    (( ${#removal[@]} > 0 )) || die 'APT не сформировал список очистки.'
    say 'Будут удалены только следующие пакеты:'
    printf '  %s\n' "${removal[@]}"
    say 'SSH, сеть и рабочий стол сохраняются. Это освобождает диск; память — только если эти приложения были запущены.'
    yesno answer 'Удалить перечисленные пакеты? Отмена оставит их на месте' нет
    if [[ $answer == yes ]]; then
        system_apt purge -y "${candidates[@]}" >> "$STATE/details.log" 2>&1
        say 'Выбранные программы удалены. Общая очистка зависимостей не выполнялась.'
    fi
    checkpoint cleanup
}
repair_panel_apt() {
    local key=${1:-/etc/apt/trusted.gpg.d/RPM-GPG-KEY-fastpanel.asc}
    local source_file=${2:-/etc/apt/sources.list.d/fastpanel2.list}
    local file
    # Это публичный ключ и список репозиториев, а не секреты установщика.
    for file in "$key" "$source_file"; do
        [[ ! -L $file ]] || die "Отказ менять права символической ссылки $file."
        if [[ -f $file ]]; then chmod 644 -- "$file"; fi
    done
}
panel() (
    # APT читает публичные файлы от _apt: режим 077 для них непригоден.
    # Маска меняется только в дочернем процессе официального установщика.
    umask 022
    unset TMPDIR TMP TEMP
    repair_panel_apt
    system_apt update
    system_apt install -y ca-certificates curl wget openssl
    curl --fail --show-error --location --proto '=https' --tlsv1.2 \
        https://repo.fastpanel.direct/install_fastpanel.sh -o "$STATE/fastpanel.sh"
    chmod 600 "$STATE/fastpanel.sh"
    # Вывод официального установщика содержит доступы: его видно только в SSH.
    bash "$STATE/fastpanel.sh" >&3 2>&3
    command -v mogwai
)
packages() {
    system_apt update
    system_apt install -y git unzip xz-utils build-essential libsystemd-dev redis-server
    if [[ $PHP_BIN == /usr/bin/php* ]]; then
        # Дополняем выбранную версию; другую ветку PHP автоматически не ставим.
        system_apt install -y "php$PHP_VERSION-fpm" "php$PHP_VERSION-mysql" "php$PHP_VERSION-mbstring" \
            "php$PHP_VERSION-intl" "php$PHP_VERSION-curl" "php$PHP_VERSION-xml" "php$PHP_VERSION-zip"
    fi
    "$PHP_BIN" -r '$missing = array_filter(["pdo_mysql","mbstring","intl","curl","dom","zip","pcntl","posix"], fn($e) => !extension_loaded($e)); if ($missing) {fwrite(STDERR,"Нет расширений PHP: ".implode(", ",$missing).PHP_EOL); exit(1);}'
    systemctl enable --now redis-server
    [[ $(redis-cli -h 127.0.0.1 ping) == PONG ]]
    # Проверяем фактический bind: Redis не должен стать публичным сервисом.
    if ss -ltnH | awk '$4 ~ /:6379$/ {print $4}' | grep -Evq '^(127\.0\.0\.1|\[::1\]|::1):6379$'; then
        die 'Redis слушает внешний интерфейс. Ограничьте bind loopback в /etc/redis/redis.conf и повторите запуск.'
    fi
}
site() {
    local listing rows selected
    local -a flags=()
    listing=$(mogwai sites list)
    rows=$(site_rows <<< "$listing")
    selected=$(awk -F '\t' -v domain="$DOMAIN" '$2 == domain' <<< "$rows")
    if [[ -z $selected && $TARGET_MODE == new ]]; then
        if ! id "$OWNER" >/dev/null 2>&1; then flags+=(--create-user); fi
        mogwai sites create --server-name="$DOMAIN" --owner="$OWNER" "${flags[@]}" \
            --ip="$SERVER_IP" --handler=php_fpm --handler_version="$PHP_VERSION"
        listing=$(mogwai sites list)
        rows=$(site_rows <<< "$listing")
        selected=$(awk -F '\t' -v domain="$DOMAIN" '$2 == domain' <<< "$rows")
    fi
    [[ $(cut -f3 <<< "$selected") == "$OWNER" ]] || die 'Сайт отсутствует в панели или принадлежит другому пользователю.'
    SITE_GROUP=$(id -gn "$OWNER")
    valid_base "$BASE" "/var/www/$OWNER/data" "$(cut -f4 <<< "$rows")" || die 'Каталог установки занят, содержит ссылку или пересекается с корнем сайта.'
    install -d -o "$OWNER" -g "$SITE_GROUP" -m 755 "$BASE"
    install -d -o "$OWNER" -g "$SITE_GROUP" -m 750 "$BASE/shared"
    save_answers
}
node_runtime() {
    local archive expected
    curl -fsSL --proto '=https' https://nodejs.org/dist/latest-v24.x/SHASUMS256.txt -o "$STATE/node-checksums"
    archive=$(awk '$2 ~ /^node-v[0-9.]+-linux-x64.tar.xz$/ {print $2}' "$STATE/node-checksums")
    [[ $archive =~ ^node-v24\.[0-9]+\.[0-9]+-linux-x64.tar.xz$ ]]
    expected=$(awk -v name="$archive" '$2 == name {print $1}' "$STATE/node-checksums")
    curl -fsSL --proto '=https' "https://nodejs.org/dist/latest-v24.x/$archive" -o "$STATE/$archive"
    printf '%s  %s\n' "$expected" "$STATE/$archive" | sha256sum --check -
    install -d -m 755 /opt/laiot-node
    tar -xJf "$STATE/$archive" --strip-components=1 -C /opt/laiot-node
    chmod -R a+rX /opt/laiot-node
    local executable
    for executable in node npm npx; do
        if command -v "$executable" >/dev/null || [[ -e /usr/local/bin/$executable || -L /usr/local/bin/$executable ]]; then
            # Приложение использует /opt через PATH; чужой Node.js не заменяем.
            continue
        else ln -s "/opt/laiot-node/bin/$executable" "/usr/local/bin/$executable"; fi
    done
}
application() {
    if [[ ! -d $APP ]]; then
        as_owner git clone --depth 1 --branch "$BRANCH" "$REPOSITORY" "$APP"
    fi
    [[ -f $APP/app/Services/Install/InstallService.php && -f $APP/public/index.php && -f $APP/composer.lock ]] || \
        die 'В загруженной ветке нет веб-установщика LaIOT. Проверьте публикацию beta.'
    # Composer проверяет подпись установщика; зависимости и сборка идут без root.
    curl -fsSL --proto '=https' https://getcomposer.org/installer -o "$STATE/composer-setup.php"
    local expected actual
    expected=$(curl -fsSL --proto '=https' https://composer.github.io/installer.sig)
    actual=$("$PHP_BIN" -r 'echo hash_file("sha384", $argv[1]);' "$STATE/composer-setup.php")
    [[ $expected == "$actual" ]]
    install -d -m 755 /opt/laiot-composer
    "$PHP_BIN" "$STATE/composer-setup.php" --install-dir=/opt/laiot-composer --filename=composer
    chmod 755 /opt/laiot-composer/composer
    if ! command -v composer >/dev/null; then ln -s /opt/laiot-composer/composer /usr/local/bin/composer; fi
    as_owner "$PHP_BIN" /opt/laiot-composer/composer install --working-dir="$APP" --no-dev --prefer-dist --no-interaction --optimize-autoloader
    as_owner "$PHP_BIN" /opt/laiot-composer/composer check-platform-reqs --no-dev --working-dir="$APP"
    (cd "$APP"; as_owner npm ci; as_owner npm run build)
    # Ключ должен совпадать с контрактом веб-мастера; .env здесь не создаётся.
    install -d -o "$OWNER" -g "$SITE_GROUP" -m 750 "$APP/var"
    if [[ ! -f $APP/var/install.key && ! -f $APP/var/installed.lock ]]; then
        openssl rand -hex 32 > "$APP/var/install.key"
        chown "$OWNER:$SITE_GROUP" "$APP/var/install.key"
        chmod 600 "$APP/var/install.key"
    fi
}
database() {
    if [[ ! -f $STATE/db-password ]]; then secret > "$STATE/db-password"; fi
    local password databases
    password=$(< "$STATE/db-password")
    # Пароль передаётся штатному CLI, не сохраняется в истории SSH.
    # При сбое после создания БД не меняем её пароль и не пересоздаём данные.
    databases=$(mogwai databases list)
    if ! awk '$0 ~ /(^|[[:space:]])laiot_app([[:space:]]|$)/ {found=1} END {exit !found}' <<< "$databases"; then
        mogwai databases create --server=1 --name=laiot_app --owner="$OWNER" \
            --site="$DOMAIN" --username=laiot_app --password="$password"
    fi
    # Убеждаемся, что существующая база действительно наша.
    DB_PASS="$password" "$PHP_BIN" -r 'new PDO("mysql:host=127.0.0.1;dbname=laiot_app", "laiot_app", getenv("DB_PASS"));'
}
mosquitto() {
    system_apt install -y mosquitto mosquitto-clients
    if [[ ! -f $STATE/mqtt-password ]]; then secret > "$STATE/mqtt-password"; fi
    local password
    password=$(< "$STATE/mqtt-password")
    printf 'laiot:%s\n' "$password" > /etc/mosquitto/laiot-passwords
    mosquitto_passwd -U /etc/mosquitto/laiot-passwords
    chown root:mosquitto /etc/mosquitto/laiot-passwords
    chmod 640 /etc/mosquitto/laiot-passwords
    cat > /etc/mosquitto/conf.d/laiot.conf <<'EOF'
listener 1883 127.0.0.1
allow_anonymous false
password_file /etc/mosquitto/laiot-passwords
EOF
    if [[ $MQTT_LAN == yes ]]; then
        printf '\nlistener 1883 %s\n' "$SERVER_IP" >> /etc/mosquitto/conf.d/laiot.conf
    fi
    chmod 644 /etc/mosquitto/conf.d/laiot.conf
    systemctl enable mosquitto
    systemctl restart mosquitto
    mosquitto_pub -h 127.0.0.1 -u laiot -P "$password" -t laiot/installer -m ready
}
zigbee() {
    local manager
    id zigbee2mqtt >/dev/null 2>&1 || useradd --system --create-home --home-dir /opt/zigbee2mqtt --groups dialout zigbee2mqtt
    if [[ ! -d /opt/zigbee2mqtt/app ]]; then
        runuser -u zigbee2mqtt -- git clone --depth 1 --branch 2.14.2 https://github.com/Koenkk/zigbee2mqtt.git /opt/zigbee2mqtt/app
    fi
    manager=$(/opt/laiot-node/bin/node -p "require('/opt/zigbee2mqtt/app/package.json').packageManager")
    [[ $manager =~ ^pnpm@[0-9]+\.[0-9]+\.[0-9]+$ ]]
    (cd /opt/zigbee2mqtt/app
        runuser -u zigbee2mqtt -- env -u TMPDIR PATH=/opt/laiot-node/bin:/usr/bin:/bin npm exec --yes --package="$manager" -- pnpm install --frozen-lockfile
        runuser -u zigbee2mqtt -- env -u TMPDIR PATH=/opt/laiot-node/bin:/usr/bin:/bin npm exec --yes --package="$manager" -- pnpm run build)
    cat > /etc/systemd/system/zigbee2mqtt.service <<'EOF'
[Unit]
Description=Zigbee2MQTT
After=network.target mosquitto.service
[Service]
User=zigbee2mqtt
WorkingDirectory=/opt/zigbee2mqtt/app
ExecStart=/opt/laiot-node/bin/node index.js
Environment=NODE_ENV=production
Environment=Z2M_ONBOARD_URL=http://127.0.0.1:8080
Environment=ZIGBEE2MQTT_CONFIG_FRONTEND_HOST=127.0.0.1
Restart=on-failure
RestartSec=10
[Install]
WantedBy=multi-user.target
EOF
    chmod 644 /etc/systemd/system/zigbee2mqtt.service
    systemctl daemon-reload
    systemctl enable --now zigbee2mqtt
}
units() {
    local file
    printf '{"root":"%s","user":"%s","group":"%s","allowed_uid":%s}\n' \
        "$BASE" "$OWNER" "$SITE_GROUP" "$(id -u "$OWNER")" > "$STATE/agent.json"
    if [[ -e /etc/laiot/agent.json ]]; then
        cmp -s "$STATE/agent.json" /etc/laiot/agent.json || die 'Конфигурация агента отличается. Не перезаписываю её.'
    fi
    install -d -m 755 /etc/laiot
    install -m 600 "$STATE/agent.json" /etc/laiot/agent.json
    for file in laiot-core@.service laiot-worker@.service laiot-agent.service; do
        sed -e "s|@@ROOT@@|$BASE|g" -e "s|@@USER@@|$OWNER|g" -e "s|@@GROUP@@|$SITE_GROUP|g" -e "s|/usr/bin/php |$PHP_BIN |g" \
            "$APP/deploy/systemd/$file" > "/etc/systemd/system/$file"
        chmod 644 "/etc/systemd/system/$file"
    done
    systemctl daemon-reload
    systemctl enable --now laiot-agent
}
web_setup() {
    say "Откройте FastPanel: https://$SERVER_IP:8888\nСайт $DOMAIN уже создан. В его настройках:"
    say "1. Выберите PHP $PHP_VERSION, PHP-FPM и включите mbstring, intl, curl, pdo_mysql, xml, zip.\n2. Корневой каталог сайта: $APP/public\n3. В настройках обработки запросов включите перенаправление отсутствующих файлов на /index.php."
    say 'Для nginx правило: try_files $uri $uri/ /index.php?$query_string;'
    if [[ $MODE == local ]]; then
        say "На роутере добавьте DNS-запись $DOMAIN → $SERVER_IP.\nИли на каждом устройстве добавьте в hosts строку: $SERVER_IP $DOMAIN\nПроброс портов на роутере не требуется."
        SCHEME=http
    else
        say "Направьте DNS домена на ваш внешний IP. При NAT пробросьте 80/443 на $SERVER_IP.\nВ FastPanel выпустите сертификат для $DOMAIN и включите HTTPS."
        SCHEME=https
    fi
    local result
    while :; do
        pause 'Сохраните настройки сайта в панели'
        # Проверяем маршрут и сертификат с этого сервера, независимо от локального DNS.
        result=$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' \
            --max-time 15 --resolve "$DOMAIN:80:$SERVER_IP" --resolve "$DOMAIN:443:$SERVER_IP" \
            "$SCHEME://$DOMAIN/install") || result=000
        [[ $result == 200 ]] && break
        say "Пока страница установки недоступна (HTTP $result). Проверьте корневой каталог, PHP и обработку адресов."
    done
    printf 'SCHEME=%q\n' "$SCHEME" >> "$STATE/answers"
    checkpoint web
}
credentials() {
    local target=$STATE/access.txt temporary credential_file completed=no
    local -a password_files=(panel-password db-password)
    [[ $MQTT != yes ]] || password_files+=(mqtt-password)
    for credential_file in "${password_files[@]}"; do
        [[ -f $STATE/$credential_file && ! -L $STATE/$credential_file && -s $STATE/$credential_file ]] || \
            die "Не найден сохранённый пароль: $STATE/$credential_file. Не создаю неполную памятку."
    done
    [[ ! -L $target && ( ! -e $target || -f $target ) ]] || die 'Файл памятки недоступен для безопасной записи.'
    [[ ! -f $APP/var/installed.lock ]] || completed=yes
    # Старый отчёт остаётся целым при сбое; временный файл сразу приватный.
    temporary=$(mktemp "$STATE/.access.XXXXXX")
    {
        printf 'LAIOT — ДОСТУПЫ И ПУТИ\nСохраните эту памятку в надёжном месте.\n\n'
        printf 'Сервер: %s\nДомен: %s\nSSH: root@%s\n\n' "$SERVER_IP" "$DOMAIN" "$SERVER_IP"
        if [[ -f $STATE/swap-summary ]]; then cat "$STATE/swap-summary"; printf '\n'; fi
        printf 'FastPanel: https://%s:8888\n' "$SERVER_IP"
        if [[ $PANEL_EXISTING == no ]]; then
            printf 'Логин: fastuser\nПароль: %s\n\n' "$(< "$STATE/panel-password")"
        else printf 'Используйте свои прежние данные входа в панель.\n\n'; fi
        if [[ $completed == yes ]]; then
            printf 'LaIOT: %s://%s/login\nУстановка приложения завершена.\n' "$SCHEME" "$DOMAIN"
            printf 'Логин и пароль администратора LaIOT заданы вами в веб-мастере; Bash их не получает.\n\n'
        else
            printf 'LaIOT: %s://%s/install\n' "$SCHEME" "$DOMAIN"
            if [[ -f $APP/var/install.key ]]; then printf 'Ключ установки: %s\n' "$(< "$APP/var/install.key")"; fi
            printf 'Логин и пароль администратора LaIOT задайте в веб-мастере.\n'
            printf 'Создавать БД в веб-мастере: НЕТ (она уже создана).\n\n'
        fi
        printf 'БД: 127.0.0.1:3306\nИмя БД и пользователь: laiot_app\nПароль БД: %s\n' "$(< "$STATE/db-password")"
        printf 'Кэш: Redis, 127.0.0.1:6379, база 0, пароль пустой\n\n'
        if [[ $MQTT == yes ]]; then
            printf 'MQTT: mqtt://127.0.0.1:1883\nПользователь: laiot\nПароль: %s\n' "$(< "$STATE/mqtt-password")"
            if [[ $MQTT_LAN == yes ]]; then
                printf 'Для устройств домашней сети: mqtt://%s:1883 (тот же логин и пароль).\n' "$SERVER_IP"
            else printf 'Mosquitto доступен только этому серверу.\n'; fi
            printf 'Конфигурация Mosquitto: /etc/mosquitto/conf.d/laiot.conf\n\n'
        elif [[ $ZIGBEE == yes ]]; then
            printf 'Внешний MQTT-брокер: %s\nЕго данные входа задаются вами в Zigbee2MQTT.\n\n' "$BROKER"
        fi
        if [[ $ZIGBEE == yes ]]; then
            printf 'Zigbee2MQTT: SSH-туннель ssh -L 8080:127.0.0.1:8080 root@%s\nОткройте http://127.0.0.1:8080 на своём ПК, задайте координатор и брокер %s.\n' "$SERVER_IP" "$BROKER"
            printf 'В настройках frontend Zigbee2MQTT оставьте host: 127.0.0.1, чтобы интерфейс не стал публичным.\n'
            printf 'Каталог Zigbee2MQTT: /opt/zigbee2mqtt/app\nСлужба: zigbee2mqtt.service\n\n'
        fi
        printf 'КАТАЛОГИ И КОНФИГУРАЦИЯ\n'
        printf 'Пользователь приложения и служб: %s\nБазовый каталог: %s\nПриложение: %s\n' "$OWNER" "$BASE" "$APP"
        printf 'Корень сайта nginx: %s/public\nНастройки и APP_KEY: %s/.env\n' "$APP" "$APP"
        printf 'Постоянные файлы ядра: %s/var/files/core\nФайлы модулей: %s/var/files/modules/<id>\n' "$APP" "$APP"
        printf 'Журналы приложения: %s/var/log\nКэш: %s/var/cache\n' "$APP" "$APP"
        printf 'Общие файлы системного слоя: %s/shared\nКонфигурация агента: /etc/laiot/agent.json\n' "$BASE"
        printf 'Службы systemd:\n  /etc/systemd/system/laiot-agent.service\n  /etc/systemd/system/laiot-core@.service\n  /etc/systemd/system/laiot-worker@.service\n'
        printf 'Redis: /etc/redis/redis.conf\nNode.js: /opt/laiot-node\n'
        printf 'Состояние установщика: %s\nЖурнал установки: %s/details.log\nЭта памятка: %s\n\n' "$STATE" "$STATE" "$target"
        printf 'ПРОВЕРКА И ПОВТОРНЫЙ ПРОСМОТР\n'
        printf 'Службы: systemctl status laiot-agent laiot-core@queue laiot-core@events\n'
        printf 'Журнал событий: journalctl -u laiot-core@events -n 100\n'
        printf 'Повторно открыть памятку под root: cat %s\n' "$target"
    } > "$temporary"
    chmod 600 "$temporary"
    mv -f -- "$temporary" "$target"
    cat "$target" >&3
    say "Параметры сохранены в $target — файл доступен только root."
}
finish() {
    local task
    while [[ ! -f $APP/var/installed.lock ]]; do
        pause 'Завершите мастер LaIOT в браузере, затем вернитесь сюда'
        [[ -f $APP/var/installed.lock ]] || say 'LaIOT ещё не отметила установку завершённой.'
    done
    for task in queue events timeouts maintenance system-events module.mqtt module.zigbee; do
        systemctl enable --now "laiot-core@$task"
    done
    systemctl enable --now laiot-worker@mqtt laiot-worker@zigbee
    sleep 3
    for task in queue events timeouts maintenance system-events module.mqtt module.zigbee; do
        systemctl is-active --quiet "laiot-core@$task"
    done
}
main() {
    if [[ ${1:-} == --help ]]; then
        printf 'LaIOT: bash install-server.sh\nИнтерактивная установка Ubuntu 24.04 x86_64. Повторный запуск продолжает установку.\n'
        return
    fi
    [[ $# == 0 ]] || die 'Неизвестный параметр. Справка: --help'
    [[ $EUID == 0 && $(id -un) == root ]] || die 'Установку нужно запускать под пользователем root.'
    [[ -t 0 || -r /dev/tty ]] || die 'Нужен интерактивный SSH-сеанс.'
    exec 3<>/dev/tty
    exec 4>/dev/tty
    UI_FD=4
    if [[ -t 3 && ${TERM:-dumb} != dumb && ! ${NO_COLOR+x} ]]; then UI_COLOR=yes; fi
    [[ -f /etc/os-release ]] || die 'Не удалось определить ОС.'
    # shellcheck disable=SC1091
    source /etc/os-release
    [[ $ID == ubuntu && $VERSION_ID == 24.04 && $(uname -m) == x86_64 ]] || die 'Эта версия мастера рассчитана на Ubuntu 24.04 x86_64.'
    [[ -d /run/systemd/system ]] || die 'Нужна система с systemd.'
    if [[ -e $STATE ]]; then
        [[ ! -L $STATE && $(stat -c %u "$STATE") == 0 && $(stat -c %a "$STATE") == 700 ]] || die 'Небезопасные права каталога установщика.'
    fi
    install -d -m 700 "$STATE"
    exec 9>"$STATE/run.lock"
    flock -n 9 || die 'Мастер уже запущен в другом SSH-сеансе.'
    if [[ -f $STATE/answers ]]; then
        # shellcheck disable=SC1091
        source "$STATE/answers"
        say "Продолжаем установку $DOMAIN."
    else
        if command -v mogwai >/dev/null; then
            PANEL_EXISTING=yes
            say 'Обнаружена готовая FastPanel. Переустановка и смена пароля панели не потребуются.'
        elif systemctl is-active --quiet nginx || systemctl is-active --quiet apache2 || systemctl is-active --quiet mysql || systemctl is-active --quiet mariadb; then
            die 'Есть серверные приложения, но нет CLI FastPanel. Для установки панели нужна чистая Ubuntu.'
        fi
        [[ ! -e /etc/laiot/agent.json && ! -e /etc/systemd/system/laiot-core@.service ]] || die 'Обнаружена другая установка LaIOT. Автоматическая перезапись запрещена.'
        questions
    fi
    touch "$STATE/details.log"
    chmod 600 "$STATE/details.log"
    install -d -m 700 "$STATE/tmp"
    # Собственные загрузки используют явные пути STATE; TMPDIR не навязываем чужим службам.
    trap failed ERR
    if [[ ${CLEANUP:-no} == yes && ! -f $STATE/cleanup.done ]]; then cleanup_apps; fi
    say 'Подготавливаем swap по объёму оперативной памяти.'
    configure_swap
    if [[ ! -f $STATE/panel.done ]]; then
        if command -v mogwai >/dev/null; then checkpoint panel
        else run_step panel '1/8 · Устанавливаем FastPanel' panel; fi
    fi
    if [[ ! -f $STATE/license.done ]]; then
        if [[ $PANEL_EXISTING == no ]]; then
            if [[ ! -f $STATE/panel-password ]]; then secret > "$STATE/panel-password"; fi
            mogwai chpasswd -u fastuser -p "$(< "$STATE/panel-password")" >> "$STATE/details.log" 2>&1
            say "Откройте https://$SERVER_IP:8888\nЛогин: fastuser\nПароль: $(< "$STATE/panel-password")\nАктивируйте лицензию по email."
        else
            say "Готовая FastPanel обнаружена. Прежние данные входа сохраняются."
        fi
        if [[ $PANEL_EXISTING == no ]]; then pause 'После активации панели вернитесь сюда'; fi
        checkpoint license
    fi
    choose_php
    if [[ ! -f $STATE/target.done ]]; then
        if [[ -f $STATE/site.done ]]; then
            # Состояние старой версии: не спрашиваем новый путь уже созданной установки.
            SITE_GROUP=$(id -gn "$OWNER")
            save_answers
            checkpoint target
        else target_questions; fi
    fi
    repair_panel_apt >> "$STATE/details.log" 2>&1
    run_step packages '2/8 · Устанавливаем PHP и Redis' packages
    run_step site '3/8 · Подготавливаем сайт и каталог' site
    run_step node '4/8 · Устанавливаем Node.js 24 LTS' node_runtime
    run_step app '5/8 · Скачиваем и собираем LaIOT' application
    run_step db '6/8 · Создаём базу данных' database
    if [[ $MQTT == yes ]]; then run_step mqtt 'Устанавливаем Mosquitto' mosquitto; fi
    if [[ $ZIGBEE == yes ]]; then run_step zigbee 'Устанавливаем Zigbee2MQTT' zigbee; fi
    run_step units '7/8 · Подготавливаем фоновые службы' units
    if [[ ! -f $STATE/web.done ]]; then web_setup; fi
    say '8/8 · Сервер подготовлен. Продолжим в браузере.'
    credentials
    run_step finish 'Запускаем службы после веб-установки' finish
    # Показ выполняется вне run_step: доступы идут в SSH, а не в details.log.
    credentials
    say "Готово! Открывайте $SCHEME://$DOMAIN/login\nНастройки MQTT и Zigbee находятся в разделах модулей LaIOT."
}

if [[ ${BASH_SOURCE[0]} == "$0" ]]; then main "$@"; fi
