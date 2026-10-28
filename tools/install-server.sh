#!/usr/bin/env bash
# 2026-10-29 — итоговая памятка с доступами и путями повторяется после запуска служб.
# 2026-10-28 — интерактивная подготовка Ubuntu для веб-установщика MyDoMo.
set -Eeuo pipefail
umask 077

REPOSITORY=https://github.com/laiot-smarthome/laiot.git
BRANCH=beta
STATE=/root/mydomo-installer
OWNER=mydomo_usr
BASE=/var/www/mydomo_usr/data/mydomo
APP=$BASE/current
STEP='подготовка'

say() { printf '\n%b\n' "$*"; }
die() { say "Остановка: $*" >&2; exit 1; }
ask() {
    local input_value
    printf '\n%s [%s]: ' "$2" "$3" >&3
    IFS= read -r input_value <&3 || die 'Ввод закрыт. Запустите мастер снова в SSH.'
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
        say "✓ $title — уже выполнено"
        return
    fi
    say "→ $title. Это может занять несколько минут."
    "$@" >> "$STATE/details.log" 2>&1
    checkpoint "$id"
    say "✓ $title"
}
failed() {
    trap - ERR
    printf '\nУстановка остановилась на этапе «%s».\n' "$STEP" >&2
    printf 'Подробности: %s/details.log (доступен только root).\n' "$STATE" >&2
    printf 'После устранения причины запустите этот же файл: выполненные этапы сохраняются.\n' >&2
    exit 1
}
as_owner() { (umask 022; runuser -u "$OWNER" -- env -u TMPDIR PATH=/opt/mydomo-node/bin:/usr/local/bin:/usr/bin:/bin "$@"); }

questions() {
    local choice default_ip
    say 'Добро пожаловать! Подготовим сервер умного дома за несколько шагов.'
    say 'Нужна чистая Ubuntu 24.04 x86_64 с доступом в интернет.\nFastPanel и Redis обязательны. Настройку самого MyDoMo завершим в браузере.'
    printf '\n1 — Только домашняя сеть (без белого IP и проброса портов)\n2 — Доступ по собственному публичному домену\n'
    while :; do
        ask choice 'Как будете открывать умный дом?' 1
        case "$choice" in 1) MODE=local; break ;; 2) MODE=public; break ;; esac
        say 'Выберите 1 или 2.'
    done
    while :; do
        ask DOMAIN 'Имя сайта, без http:// и пути' mydomo.home.arpa
        valid_domain "$DOMAIN" && break
        say 'Нужно имя вида mydomo.home.arpa или home.example.ru, латиницей.'
    done
    if [[ $MODE == public && $DOMAIN == *.home.arpa ]]; then
        die 'Для публичного режима нужен ваш зарегистрированный домен. Запустите мастер снова.'
    fi
    default_ip=$(hostname -I | awk '{print $1}')
    say 'Адреса этого сервера:'
    ip -o -4 addr show scope global | awk '{print $2, $4}'
    while :; do
        ask SERVER_IP 'IPv4 сервера, к которому привязать сайт' "$default_ip"
        if valid_ip "$SERVER_IP" && ip -o -4 addr show | awk '{print $4}' | cut -d/ -f1 | grep -Fxq "$SERVER_IP"; then break; fi
        say 'Выберите IPv4, назначенный этому серверу (список выше).'
    done
    yesno MQTT 'Установить Mosquitto для MQTT-устройств?' нет
    MQTT_LAN=no
    if [[ $MQTT == yes && $SERVER_IP =~ ^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.) ]]; then
        yesno MQTT_LAN 'Разрешить MQTT-устройствам подключаться через домашнюю сеть с паролем?' да
    fi
    yesno ZIGBEE 'Установить Zigbee2MQTT для Zigbee-устройств? Нужен отдельный координатор' нет
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
    printf 'MODE=%q\nDOMAIN=%q\nSERVER_IP=%q\nMQTT=%q\nMQTT_LAN=%q\nZIGBEE=%q\nBROKER=%q\nBRANCH=%q\nCLEANUP=%q\n' \
        "$MODE" "$DOMAIN" "$SERVER_IP" "$MQTT" "$MQTT_LAN" "$ZIGBEE" "$BROKER" "$BRANCH" "$CLEANUP" > "$STATE/answers"
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
        apt-get purge -y "${candidates[@]}" >> "$STATE/details.log" 2>&1
        say 'Выбранные программы удалены. Общая очистка зависимостей не выполнялась.'
    fi
    checkpoint cleanup
}
panel() {
    apt-get update
    apt-get install -y ca-certificates curl wget openssl
    curl --fail --show-error --location --proto '=https' --tlsv1.2 \
        https://repo.fastpanel.direct/install_fastpanel.sh -o "$STATE/fastpanel.sh"
    # Вывод официального установщика содержит доступы: его видно только в SSH.
    bash "$STATE/fastpanel.sh" >&3 2>&3
    command -v mogwai
}
packages() {
    apt-get update
    apt-get install -y git unzip xz-utils build-essential libsystemd-dev redis-server \
        php8.3-cli php8.3-mysql php8.3-mbstring php8.3-intl php8.3-curl php8.3-xml php8.3-zip
    php8.3 -r 'exit(PHP_VERSION_ID >= 80300 && extension_loaded("pcntl") && extension_loaded("pdo_mysql") ? 0 : 1);'
    systemctl enable --now redis-server
    [[ $(redis-cli -h 127.0.0.1 ping) == PONG ]]
    # Проверяем фактический bind: Redis не должен стать публичным сервисом.
    if ss -ltnH | awk '$4 ~ /:6379$/ {print $4}' | grep -Evq '^(127\.0\.0\.1|\[::1\]|::1):6379$'; then
        die 'Redis слушает внешний интерфейс. Ограничьте bind loopback в /etc/redis/redis.conf и повторите запуск.'
    fi
}
site() {
    if ! id "$OWNER" >/dev/null 2>&1; then
        mogwai sites create --server-name="$DOMAIN" --owner="$OWNER" --create-user \
            --ip="$SERVER_IP" --handler=php_fpm --handler_version=8.3
    fi
    mogwai sites list | awk -v domain="$DOMAIN" -v owner="$OWNER" \
        '$2 == domain && $4 == owner {found=1} END {exit !found}'
    install -d -o "$OWNER" -g "$OWNER" -m 755 "$BASE"
    install -d -o "$OWNER" -g "$OWNER" -m 750 "$BASE/shared"
}
node_runtime() {
    local archive expected
    curl -fsSL --proto '=https' https://nodejs.org/dist/latest-v24.x/SHASUMS256.txt -o "$STATE/node-checksums"
    archive=$(awk '$2 ~ /^node-v[0-9.]+-linux-x64.tar.xz$/ {print $2}' "$STATE/node-checksums")
    [[ $archive =~ ^node-v24\.[0-9]+\.[0-9]+-linux-x64.tar.xz$ ]]
    expected=$(awk -v name="$archive" '$2 == name {print $1}' "$STATE/node-checksums")
    curl -fsSL --proto '=https' "https://nodejs.org/dist/latest-v24.x/$archive" -o "$STATE/$archive"
    printf '%s  %s\n' "$expected" "$STATE/$archive" | sha256sum --check -
    install -d -m 755 /opt/mydomo-node
    tar -xJf "$STATE/$archive" --strip-components=1 -C /opt/mydomo-node
    chmod -R a+rX /opt/mydomo-node
    local executable
    for executable in node npm npx; do
        if [[ -e /usr/local/bin/$executable || -L /usr/local/bin/$executable ]]; then
            [[ $(readlink -f "/usr/local/bin/$executable") == $(readlink -f "/opt/mydomo-node/bin/$executable") ]] || \
                die "Уже существует /usr/local/bin/$executable. Не перезаписываю чужую установку."
        else ln -s "/opt/mydomo-node/bin/$executable" "/usr/local/bin/$executable"; fi
    done
}
application() {
    if [[ ! -d $APP ]]; then
        as_owner git clone --depth 1 --branch "$BRANCH" "$REPOSITORY" "$APP"
    fi
    [[ -f $APP/src/Install/InstallService.php && -f $APP/public/index.php && -f $APP/composer.lock ]] || \
        die 'В загруженной ветке нет веб-установщика MyDoMo. Проверьте публикацию beta.'
    # Composer проверяет подпись установщика; зависимости и сборка идут без root.
    curl -fsSL --proto '=https' https://getcomposer.org/installer -o "$STATE/composer-setup.php"
    local expected actual
    expected=$(curl -fsSL --proto '=https' https://composer.github.io/installer.sig)
    actual=$(php8.3 -r 'echo hash_file("sha384", $argv[1]);' "$STATE/composer-setup.php")
    [[ $expected == "$actual" ]]
    php8.3 "$STATE/composer-setup.php" --install-dir=/usr/local/bin --filename=composer
    as_owner php8.3 /usr/local/bin/composer install --working-dir="$APP" --no-dev --prefer-dist --no-interaction --optimize-autoloader
    (cd "$APP"; as_owner npm ci; as_owner npm run build)
    # Ключ должен совпадать с контрактом веб-мастера; .env здесь не создаётся.
    install -d -o "$OWNER" -g "$OWNER" -m 750 "$APP/var"
    if [[ ! -f $APP/var/install.key && ! -f $APP/var/installed.lock ]]; then
        openssl rand -hex 32 > "$APP/var/install.key"
        chown "$OWNER:$OWNER" "$APP/var/install.key"
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
    if ! awk '$0 ~ /(^|[[:space:]])mydomo_app([[:space:]]|$)/ {found=1} END {exit !found}' <<< "$databases"; then
        mogwai databases create --server=1 --name=mydomo_app --owner="$OWNER" \
            --site="$DOMAIN" --username=mydomo_app --password="$password"
    fi
    # Убеждаемся, что существующая база действительно наша.
    DB_PASS="$password" php8.3 -r 'new PDO("mysql:host=127.0.0.1;dbname=mydomo_app", "mydomo_app", getenv("DB_PASS"));'
}
mosquitto() {
    apt-get install -y mosquitto mosquitto-clients
    if [[ ! -f $STATE/mqtt-password ]]; then secret > "$STATE/mqtt-password"; fi
    local password
    password=$(< "$STATE/mqtt-password")
    printf 'mydomo:%s\n' "$password" > /etc/mosquitto/mydomo-passwords
    mosquitto_passwd -U /etc/mosquitto/mydomo-passwords
    chown root:mosquitto /etc/mosquitto/mydomo-passwords
    chmod 640 /etc/mosquitto/mydomo-passwords
    cat > /etc/mosquitto/conf.d/mydomo.conf <<'EOF'
listener 1883 127.0.0.1
allow_anonymous false
password_file /etc/mosquitto/mydomo-passwords
EOF
    if [[ $MQTT_LAN == yes ]]; then
        printf '\nlistener 1883 %s\n' "$SERVER_IP" >> /etc/mosquitto/conf.d/mydomo.conf
    fi
    chmod 644 /etc/mosquitto/conf.d/mydomo.conf
    systemctl enable mosquitto
    systemctl restart mosquitto
    mosquitto_pub -h 127.0.0.1 -u mydomo -P "$password" -t mydomo/installer -m ready
}
zigbee() {
    local manager
    id zigbee2mqtt >/dev/null 2>&1 || useradd --system --create-home --home-dir /opt/zigbee2mqtt --groups dialout zigbee2mqtt
    if [[ ! -d /opt/zigbee2mqtt/app ]]; then
        runuser -u zigbee2mqtt -- git clone --depth 1 --branch 2.14.2 https://github.com/Koenkk/zigbee2mqtt.git /opt/zigbee2mqtt/app
    fi
    manager=$(/opt/mydomo-node/bin/node -p "require('/opt/zigbee2mqtt/app/package.json').packageManager")
    [[ $manager =~ ^pnpm@[0-9]+\.[0-9]+\.[0-9]+$ ]]
    (cd /opt/zigbee2mqtt/app
        runuser -u zigbee2mqtt -- env -u TMPDIR PATH=/opt/mydomo-node/bin:/usr/bin:/bin npm exec --yes --package="$manager" -- pnpm install --frozen-lockfile
        runuser -u zigbee2mqtt -- env -u TMPDIR PATH=/opt/mydomo-node/bin:/usr/bin:/bin npm exec --yes --package="$manager" -- pnpm run build)
    cat > /etc/systemd/system/zigbee2mqtt.service <<'EOF'
[Unit]
Description=Zigbee2MQTT
After=network.target mosquitto.service
[Service]
User=zigbee2mqtt
WorkingDirectory=/opt/zigbee2mqtt/app
ExecStart=/opt/mydomo-node/bin/node index.js
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
        "$BASE" "$OWNER" "$OWNER" "$(id -u "$OWNER")" > "$STATE/agent.json"
    if [[ -e /etc/mydomo/agent.json ]]; then
        cmp -s "$STATE/agent.json" /etc/mydomo/agent.json || die 'Конфигурация агента отличается. Не перезаписываю её.'
    fi
    install -d -m 755 /etc/mydomo
    install -m 600 "$STATE/agent.json" /etc/mydomo/agent.json
    for file in mydomo-core@.service mydomo-worker@.service mydomo-agent.service; do
        sed -e "s|@@ROOT@@|$BASE|g" -e "s|@@USER@@|$OWNER|g" -e "s|@@GROUP@@|$OWNER|g" -e 's|/usr/bin/php |/usr/bin/php8.3 |g' \
            "$APP/deploy/systemd/$file" > "/etc/systemd/system/$file"
        chmod 644 "/etc/systemd/system/$file"
    done
    systemctl daemon-reload
    systemctl enable --now mydomo-agent
}
web_setup() {
    say "Откройте FastPanel: https://$SERVER_IP:8888\nСайт $DOMAIN уже создан. В его настройках:"
    say "1. Выберите PHP 8.3, PHP-FPM и включите mbstring, intl, curl, pdo_mysql, xml, zip.\n2. Корневой каталог сайта: $APP/public\n3. В настройках обработки запросов включите перенаправление отсутствующих файлов на /index.php."
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
        printf 'MYDOMO — ДОСТУПЫ И ПУТИ\nСохраните эту памятку в надёжном месте.\n\n'
        printf 'Сервер: %s\nДомен: %s\nSSH: root@%s\n\n' "$SERVER_IP" "$DOMAIN" "$SERVER_IP"
        printf 'FastPanel: https://%s:8888\nЛогин: fastuser\nПароль: %s\n\n' "$SERVER_IP" "$(< "$STATE/panel-password")"
        if [[ $completed == yes ]]; then
            printf 'MyDoMo: %s://%s/login\nУстановка приложения завершена.\n' "$SCHEME" "$DOMAIN"
            printf 'Логин и пароль администратора MyDoMo заданы вами в веб-мастере; Bash их не получает.\n\n'
        else
            printf 'MyDoMo: %s://%s/install\n' "$SCHEME" "$DOMAIN"
            if [[ -f $APP/var/install.key ]]; then printf 'Ключ установки: %s\n' "$(< "$APP/var/install.key")"; fi
            printf 'Логин и пароль администратора MyDoMo задайте в веб-мастере.\n'
            printf 'Создавать БД в веб-мастере: НЕТ (она уже создана).\n\n'
        fi
        printf 'БД: 127.0.0.1:3306\nИмя БД и пользователь: mydomo_app\nПароль БД: %s\n' "$(< "$STATE/db-password")"
        printf 'Кэш: Redis, 127.0.0.1:6379, база 0, пароль пустой\n\n'
        if [[ $MQTT == yes ]]; then
            printf 'MQTT: mqtt://127.0.0.1:1883\nПользователь: mydomo\nПароль: %s\n' "$(< "$STATE/mqtt-password")"
            if [[ $MQTT_LAN == yes ]]; then
                printf 'Для устройств домашней сети: mqtt://%s:1883 (тот же логин и пароль).\n' "$SERVER_IP"
            else printf 'Mosquitto доступен только этому серверу.\n'; fi
            printf 'Конфигурация Mosquitto: /etc/mosquitto/conf.d/mydomo.conf\n\n'
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
        printf 'Общие файлы системного слоя: %s/shared\nКонфигурация агента: /etc/mydomo/agent.json\n' "$BASE"
        printf 'Службы systemd:\n  /etc/systemd/system/mydomo-agent.service\n  /etc/systemd/system/mydomo-core@.service\n  /etc/systemd/system/mydomo-worker@.service\n'
        printf 'Redis: /etc/redis/redis.conf\nNode.js: /opt/mydomo-node\n'
        printf 'Состояние установщика: %s\nЖурнал установки: %s/details.log\nЭта памятка: %s\n\n' "$STATE" "$STATE" "$target"
        printf 'ПРОВЕРКА И ПОВТОРНЫЙ ПРОСМОТР\n'
        printf 'Службы: systemctl status mydomo-agent mydomo-core@queue mydomo-core@events\n'
        printf 'Журнал событий: journalctl -u mydomo-core@events -n 100\n'
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
        pause 'Завершите мастер MyDoMo в браузере, затем вернитесь сюда'
        [[ -f $APP/var/installed.lock ]] || say 'MyDoMo ещё не отметила установку завершённой.'
    done
    for task in queue events timeouts maintenance system-events module.mqtt module.zigbee; do
        systemctl enable --now "mydomo-core@$task"
    done
    systemctl enable --now mydomo-worker@mqtt mydomo-worker@zigbee
    sleep 3
    for task in queue events timeouts maintenance system-events module.mqtt module.zigbee; do
        systemctl is-active --quiet "mydomo-core@$task"
    done
}
main() {
    if [[ ${1:-} == --help ]]; then
        printf 'MyDoMo: bash install-server.sh\nИнтерактивная установка Ubuntu 24.04 x86_64. Повторный запуск продолжает установку.\n'
        return
    fi
    [[ $# == 0 ]] || die 'Неизвестный параметр. Справка: --help'
    [[ $EUID == 0 && $(id -un) == root ]] || die 'Установку нужно запускать под пользователем root.'
    [[ -t 0 || -r /dev/tty ]] || die 'Нужен интерактивный SSH-сеанс.'
    exec 3<>/dev/tty
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
        command -v mogwai >/dev/null && die 'FastPanel уже установлена вне этого мастера. Нужна чистая Ubuntu.'
        if systemctl is-active --quiet nginx || systemctl is-active --quiet apache2 || systemctl is-active --quiet mysql || systemctl is-active --quiet mariadb; then
            die 'Обнаружен работающий веб-сервер или БД. Нужна чистая Ubuntu.'
        fi
        [[ ! -e $BASE && ! -e /etc/mydomo/agent.json && ! -e /opt/zigbee2mqtt ]] || die 'Обнаружены данные предыдущей установки. Автоматическая перезапись запрещена.'
        questions
    fi
    touch "$STATE/details.log"
    chmod 600 "$STATE/details.log"
    install -d -m 700 "$STATE/tmp"
    # Внешние утилиты root также используют временный каталог в /root.
    export TMPDIR="$STATE/tmp"
    trap failed ERR
    if [[ ${CLEANUP:-no} == yes && ! -f $STATE/cleanup.done ]]; then cleanup_apps; fi
    if [[ ! -f $STATE/panel.done ]]; then
        if command -v mogwai >/dev/null; then checkpoint panel
        else run_step panel '1/8 · Устанавливаем FastPanel' panel; fi
    fi
    if [[ ! -f $STATE/license.done ]]; then
        if [[ ! -f $STATE/panel-password ]]; then secret > "$STATE/panel-password"; fi
        mogwai chpasswd -u fastuser -p "$(< "$STATE/panel-password")" >> "$STATE/details.log" 2>&1
        say "Откройте https://$SERVER_IP:8888\nЛогин: fastuser\nПароль: $(< "$STATE/panel-password")\nАктивируйте лицензию по email. В разделе приложений установите PHP 8.3 для сайтов."
        pause 'После активации панели и установки PHP вернитесь сюда'
        checkpoint license
    fi
    run_step packages '2/8 · Устанавливаем PHP и Redis' packages
    run_step site '3/8 · Создаём сайт' site
    run_step node '4/8 · Устанавливаем Node.js 24 LTS' node_runtime
    run_step app '5/8 · Скачиваем и собираем MyDoMo' application
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
    say "Готово! Открывайте $SCHEME://$DOMAIN/login\nНастройки MQTT и Zigbee находятся в разделах модулей MyDoMo."
}

if [[ ${BASH_SOURCE[0]} == "$0" ]]; then main "$@"; fi
