#!/usr/bin/env bash
#
# LaIOT — первичная настройка системной части на Ubuntu.
#
# Скрипт закрывает только системный слой: пользователя и права, каталоги
# var, systemd-юниты и административный агент. Данные установки (.env,
# миграции, администратор) остаются за /install и CLI `install`; поэтому
# повторный запуск не трогает базу, .env и файлы приложения.
#
# Рассчитан на Ubuntu с systemd и запуск от root. Все проверки окружения
# выполняются до первого изменения: при проблеме установка остановится с
# объяснением, а не оставит половину настроенной системы.
#
# 2026-10-28 — первая версия для ветки LaIOT.
set -euo pipefail

readonly SCRIPT_NAME="${0##*/}"
readonly ROOT_DEFAULT='/var/www/laiot'

# Ядровые процессы, которые обязательны по docs/laiot/runtime.md. Порядок
# в массиве — порядок включения; events обслуживает OnChange, историю и
# доставку модулям, без него очередь событий растёт.
CORE_TASKS=(queue events timeouts maintenance)

ROOT=''
USER_NAME=''
GROUP_NAME=''
SOCKET='/run/laiot/agent.sock'
SYSTEMD_DIR='/etc/systemd/system'
AGENT_CONFIG='/etc/laiot/agent.json'
START_NOW=0
DRY_RUN=0
FORCE=0

# Заполняются после разбора опций и проверки путей.
LAYOUT=''
APP_DIR=''
AGENT_DIR=''
USER_UID=''
PHP_BIN=''
SYSTEMCTL=''

say() {
    printf '[laiot] %s\n' "$*"
}

warn() {
    printf '[laiot] ВНИМАНИЕ: %s\n' "$*" >&2
}

die() {
    printf '[laiot] ОШИБКА: %s\n' "$*" >&2
    exit 1
}

on_error() {
    local status=$1 line=$2
    printf '[laiot] ОШИБКА: команда завершилась с кодом %s (строка %s).\n' "$status" "$line" >&2
}

trap 'on_error "$?" "$LINENO"' ERR

usage() {
    cat <<EOF
LaIOT — первичная настройка системной части Ubuntu.

Использование:
  $SCRIPT_NAME [опции]

Опции:
  --root PATH           каталог установки (по умолчанию $ROOT_DEFAULT)
  --user NAME           пользователь приложения и служб (по умолчанию владелец ROOT или laiot)
  --group NAME          группа (по умолчанию основная группа пользователя)
  --socket PATH         Unix-сокет агента (по умолчанию /run/laiot/agent.sock)
  --systemd-dir PATH    каталог юнитов (по умолчанию /etc/systemd/system)
  --agent-config PATH   конфигурация агента (по умолчанию /etc/laiot/agent.json)
  --start               сразу запустить core-службы (без флага — только enable)
  --force               перезаписать существующие agent.json и юниты
  --dry-run             напечатать команды, ничего не меняя
  -h, --help            эта справка

Примеры:
  sudo $SCRIPT_NAME --dry-run
  sudo $SCRIPT_NAME --root /var/www/laiot --user laiot --start
  sudo $SCRIPT_NAME --root /srv/laiot --force

Скрипт не создаёт .env, не выполняет миграции и не создаёт администратора:
это делает /install или CLI \`install\`. Запуск только от root.

Подсказка: chmod +x deploy/setup-ubuntu.sh — и скрипт можно запускать
напрямую как ./deploy/setup-ubuntu.sh.
EOF
}

# Возвращает значение опции, которая требует аргумент. Отдельная функция
# нужна, чтобы не проверять $2 после каждого case и не падать из-за set -u.
opt_value() {
    if (( $# < 2 )) || [[ -z ${2:-} ]]; then
        die "опция $1 требует непустое значение"
    fi
    printf '%s' "$2"
}

parse_options() {
    while (( $# > 0 )); do
        case "$1" in
            --root)
                ROOT=$(opt_value "$@")
                shift 2
                ;;
            --user)
                USER_NAME=$(opt_value "$@")
                shift 2
                ;;
            --group)
                GROUP_NAME=$(opt_value "$@")
                shift 2
                ;;
            --socket)
                SOCKET=$(opt_value "$@")
                shift 2
                ;;
            --systemd-dir)
                SYSTEMD_DIR=$(opt_value "$@")
                shift 2
                ;;
            --agent-config)
                AGENT_CONFIG=$(opt_value "$@")
                shift 2
                ;;
            --start)
                START_NOW=1
                shift
                ;;
            --dry-run)
                DRY_RUN=1
                shift
                ;;
            --force)
                FORCE=1
                shift
                ;;
            --help|-h)
                usage
                exit 0
                ;;
            --)
                shift
                break
                ;;
            -*)
                die "неизвестная опция: $1 (см. --help)"
                ;;
            *)
                die "лишний аргумент: $1 (скрипт не принимает позиционных аргументов)"
                ;;
        esac
    done

    if (( $# > 0 )); then
        die "лишний аргумент: $1"
    fi
}

assert_root() {
    if (( EUID != 0 )); then
        die "нужны права root: запустите через sudo. Скрипт меняет systemd, владельцев и права файлов."
    fi
}

# Пути подставляются в systemd и JSON, которые не экранируют пробелы и
# кавычки. Поэтому такие пути отвергаем заранее, а не получаем сломанный
# юнит после записи.
assert_absolute_path() {
    local label=$1 path=$2

    [[ $path == /* ]] || die "$label должен быть абсолютным путём: $path"
    [[ $path != *[[:space:]]* ]] || die "$label не должен содержать пробелов: $path"
    [[ $path != *'"'* && $path != *'\'* ]] || die "$label не должен содержать кавычек и обратных косых черт: $path"
    [[ $path != *'%'* ]] || die "$label не должен содержать %: systemd трактует его как спецификатор юнита"
    [[ $path != *$'\n'* ]] || die "$label не должен содержать переводов строк"
}

validate_option_paths() {
    SOCKET=${SOCKET%/}
    SYSTEMD_DIR=${SYSTEMD_DIR%/}
    AGENT_CONFIG=${AGENT_CONFIG%/}

    [[ -n $SOCKET ]] || die '--socket не может быть корнем файловой системы'
    [[ -n $SYSTEMD_DIR ]] || die '--systemd-dir не может быть корнем файловой системы'
    [[ -n $AGENT_CONFIG ]] || die '--agent-config не может быть корнем файловой системы'

    assert_absolute_path '--socket' "$SOCKET"
    [[ $SOCKET == */*/* ]] || die "--socket должен лежать в каталоге, а не в корне: $SOCKET"

    assert_absolute_path '--systemd-dir' "$SYSTEMD_DIR"
    [[ $SYSTEMD_DIR != '/' ]] || die "--systemd-dir не может быть корнем файловой системы"

    assert_absolute_path '--agent-config' "$AGENT_CONFIG"
    [[ $AGENT_CONFIG == */*/* ]] || die "--agent-config должен лежать в каталоге, а не в корне: $AGENT_CONFIG"
}

# Выбирает раскладку: прямой корень (код сразу в ROOT) или releases
# (ROOT/current + ROOT/shared). Шаблоны юнитов написаны под releases, поэтому
# во втором случае они берутся почти как есть, а в первом — адаптируются.
resolve_layout() {
    if [[ -z $ROOT ]]; then
        ROOT=$ROOT_DEFAULT
    fi
    ROOT=${ROOT%/}

    [[ -n $ROOT ]] || die "корень установки не может быть /"
    assert_absolute_path '--root' "$ROOT"
    [[ -d $ROOT ]] || die "каталог установки не найден: $ROOT. Сначала разверните код приложения."

    local resolved
    resolved=$(cd -- "$ROOT" && pwd -P) || die "не удалось определить реальный путь: $ROOT"
    ROOT=$resolved
    [[ $ROOT != '/' ]] || die "корень установки не может быть /"

    if [[ -d "$ROOT/current" ]]; then
        LAYOUT='releases'
        APP_DIR="$ROOT/current"
    else
        LAYOUT='direct'
        APP_DIR="$ROOT"
    fi

    [[ -f "$APP_DIR/bin/laiot" ]] || die "не найден $APP_DIR/bin/laiot. Проверьте --root и раскладку установки."
    [[ -f "$APP_DIR/bin/laiot-agent" ]] || die "не найден $APP_DIR/bin/laiot-agent (служба агента не запустится)."
    [[ -d "$APP_DIR/deploy/systemd" ]] || die "не найден каталог шаблонов $APP_DIR/deploy/systemd."
}

group_exists() {
    if command -v getent >/dev/null 2>&1; then
        getent group "$1" >/dev/null 2>&1
    else
        grep -q "^$1:" /etc/group
    fi
}

# Пользователь по умолчанию — владелец ROOT (часто это пользователь сайта),
# но не root: службы не должны работать от root. Если ROOT принадлежит root,
# берём laiot и требуем, чтобы администратор создал его заранее.
resolve_identity() {
    if [[ -z $USER_NAME ]]; then
        local owner
        owner=$(stat -c '%U' -- "$ROOT")
        if [[ $owner != 'root' ]]; then
            USER_NAME=$owner
        else
            USER_NAME='laiot'
        fi
    fi

    [[ $USER_NAME =~ ^[a-z_][a-z0-9_.-]{0,31}$ ]] || die "некорректное имя пользователя: $USER_NAME"
    [[ $USER_NAME != 'root' ]] || die "пользователь root запрещён: службы должны работать от отдельной учётной записи"
    [[ $USER_NAME != 'root' ]] || die 'службы не должны работать от root: укажите отдельного пользователя через --user'

    if ! id -u "$USER_NAME" >/dev/null 2>&1; then
        die "пользователь '$USER_NAME' не найден. Создайте системного пользователя и повторите:
  useradd --system --home-dir '$ROOT' --shell /usr/sbin/nologin '$USER_NAME'
Не запускайте сайт от root: пользователь должен совпадать с пользователем PHP-FPM или быть им разрешён."
    fi

    if [[ -z $GROUP_NAME ]]; then
        GROUP_NAME=$(id -gn "$USER_NAME")
    fi
    [[ $GROUP_NAME =~ ^[a-z_][a-z0-9_.-]{0,31}$ ]] || die "некорректное имя группы: $GROUP_NAME"
    group_exists "$GROUP_NAME" || die "группа '$GROUP_NAME' не найдена: создайте её или укажите существующую через --group"

    USER_UID=$(id -u "$USER_NAME")
}

resolve_tools() {
    SYSTEMCTL=$(command -v systemctl || true)
    PHP_BIN=$(command -v php || true)
}

# PHP проверяется тем же бинарником, который окажется в PATH. Версия и
# расширения — требование установщика и worker'ов; pcntl/posix нужны именно
# CLI-процессам, поэтому проверяются здесь, а не в FPM.
assert_php() {
    local version missing

    version=$("$PHP_BIN" -r 'echo PHP_VERSION;' 2>/dev/null || true)
    [[ -n $version ]] || die "не удалось запустить $PHP_BIN"

    if ! "$PHP_BIN" -r 'exit(PHP_VERSION_ID >= 80200 ? 0 : 1);'; then
        die "нужен PHP 8.2 или новее, найден $version ($PHP_BIN)"
    fi
    say "PHP $version: $PHP_BIN"

    missing=$("$PHP_BIN" -r '
        $required = ["pdo_mysql", "mbstring", "intl", "curl", "openssl", "pcntl", "posix"];
        $missing = array_values(array_filter(
            $required,
            static fn (string $name): bool => !extension_loaded($name)
        ));
        echo implode(", ", $missing);
    ')

    if [[ -n $missing ]]; then
        die "не загружены обязательные расширения PHP: $missing. Установите пакеты (например, php8.2-mysql, php8.2-mbstring, php8.2-intl, php8.2-curl) и повторите"
    fi
}

check_redis() {
    if ! command -v redis-cli >/dev/null 2>&1; then
        warn "redis-cli не найден: доступность Redis не проверена. Для production нужен Redis (docs/laiot/cache.md)."
        return 0
    fi

    local pong
    if command -v timeout >/dev/null 2>&1; then
        pong=$(timeout 3 redis-cli ping 2>/dev/null || true)
    else
        pong=$(redis-cli ping 2>/dev/null || true)
    fi

    if [[ $pong == 'PONG' ]]; then
        say 'Redis отвечает на ping'
    else
        warn "redis-cli ping не вернул PONG (${pong:-нет ответа}). Redis не обязателен для установки, но обязателен для production-кэша и блокировок."
    fi
}

check_writable_by_user() {
    local target=$1 label=$2

    [[ -e $target ]] || return 0

    if ! command -v runuser >/dev/null 2>&1; then
        warn "runuser не найден: не удалось проверить запись для $label"
        return 0
    fi

    if ! runuser -u "$USER_NAME" -- test -w "$target" 2>/dev/null; then
        warn "пользователь $USER_NAME не может писать в $label: $target"
    fi
}

check_environment_file() {
    local env_file="$APP_DIR/.env"

    if [[ -L $env_file ]]; then
        warn ".env — символическая ссылка, а установщик требует обычный файл: $env_file"
    elif [[ -f $env_file ]]; then
        say 'найден .env (скрипт его не читает и не меняет)'
        check_writable_by_user "$env_file" '.env'
    else
        warn "файла .env ещё нет — это нормально до /install. Скрипт его не создаёт."
        check_writable_by_user "$APP_DIR" "каталог установки (установщику нужно записать сюда .env)"
    fi
}

preflight() {
    if [[ -z $SYSTEMCTL ]]; then
        die "systemctl не найден: скрипт рассчитан на Ubuntu с systemd, а не на контейнер без init."
    fi
    [[ -d /run/systemd/system ]] || die "systemd не является init-системой (/run/systemd/system отсутствует). Запускать юниты некуда."

    if [[ -z $PHP_BIN ]]; then
        die "php не найден в PATH: установите php-cli версии 8.2 или новее."
    fi
    assert_php

    # Шаблоны жёстко вызывают /usr/bin/php; если php стоит в другом месте,
    # юниты запустятся и сразу упадут. Честнее предупредить заранее.
    if [[ ! -x '/usr/bin/php' ]]; then
        warn 'в шаблонах deploy/systemd указан /usr/bin/php, а его нет: службы не запустятся. Установите php-cli или создайте ссылку /usr/bin/php.'
    fi

    if [[ ! -f "$APP_DIR/vendor/autoload.php" ]]; then
        warn "нет $APP_DIR/vendor/autoload.php: выполните composer install. Скрипт зависимости не устанавливает."
    fi

    local unit
    for unit in laiot-agent.service 'laiot-worker@.service' 'laiot-core@.service'; do
        [[ -f "$APP_DIR/deploy/systemd/$unit" ]] || die "не найден шаблон systemd: $APP_DIR/deploy/systemd/$unit"
    done

    check_environment_file
    check_redis

    if (( START_NOW )) && [[ ! -f "$APP_DIR/.env" ]]; then
        warn '--start до /install: core-службы ещё не знают базу и будут перезапускаться. Обычно --start запускают после установки.'
    fi
}

run() {
    if (( DRY_RUN )); then
        printf '[dry-run]'
        printf ' %q' "$@"
        printf '\n'
        return 0
    fi
    "$@"
}

# Создаёт каталог или приводит владельца/права к ожидаемым. Режим сравниваем
# с stat, чтобы повторный запуск не дёргал chmod/chown зря.
ensure_dir() {
    local dir=$1 mode=$2 owner=$3 current_owner current_mode

    [[ $dir != '/' ]] || return 0

    if [[ -d $dir ]]; then
        current_owner=$(stat -c '%U:%G' -- "$dir")
        current_mode=$(stat -c '%a' -- "$dir")
        [[ $current_owner == "$owner" ]] || run chown -- "$owner" "$dir"
        [[ $current_mode == "$mode" ]] || run chmod "$mode" -- "$dir"
        return 0
    fi

    run mkdir -p -- "$dir"
    run chown -- "$owner" "$dir"
    run chmod "$mode" -- "$dir"
}

ensure_directories() {
    local dir

    ensure_dir "$SYSTEMD_DIR" 755 'root:root'

    # В раскладке releases шаблоны ссылаются на ROOT/shared. systemd не
    # запускает unit, если путь из ReadWritePaths не существует, поэтому
    # каталог создаётся до первого старта.
    if [[ $LAYOUT == 'releases' ]]; then
        ensure_dir "$ROOT/shared" 775 "$USER_NAME:$GROUP_NAME"
    fi

    for dir in "$APP_DIR/var" "$APP_DIR/var/log" "$APP_DIR/var/cache" "$APP_DIR/var/locks"; do
        ensure_dir "$dir" 775 "$USER_NAME:$GROUP_NAME"
    done

    if (( DRY_RUN == 0 )); then
        for dir in "$APP_DIR/var" "$APP_DIR/var/log" "$APP_DIR/var/cache" "$APP_DIR/var/locks"; do
            check_writable_by_user "$dir" 'каталог var'
        done
    fi
}

# Содержимое повторяет deploy/systemd/agent.json.example. root указывает на
# каталог приложения, потому что агент ищет modules рядом с ним; в releases
# это ROOT/current, а не ROOT.
agent_json() {
    cat <<EOF
{
    "root": "$APP_DIR",
    "user": "$USER_NAME",
    "group": "$GROUP_NAME",
    "socket": "$SOCKET",
    "systemctl": "$SYSTEMCTL",
    "unit_dir": "$SYSTEMD_DIR",
    "allowed_uid": $USER_UID
}
EOF
}

# Пишем через временный файл в том же каталоге и переименовываем: оборванная
# запись не оставит сломанный юнит или agent.json. Временный файл имеет
# предсказуемое имя и не удаляется рекурсивно.
write_file() {
    local path=$1 content=$2 mode=$3 owner=$4 temporary

    if [[ -L $path ]]; then
        die "$path — символическая ссылка: не перезаписываю через неё файл"
    fi

    temporary="${path}.laiot-tmp"

    if (( DRY_RUN )); then
        printf '[dry-run] записать %s (владелец %s, права %s)\n' "$path" "$owner" "$mode"
        return 0
    fi

    (
        umask 0077
        printf '%s\n' "$content" > "$temporary"
    )
    chmod "$mode" "$temporary"
    chown "$owner" "$temporary"
    mv -f -- "$temporary" "$path"
}

ensure_file_mode() {
    local path=$1 mode=$2 owner=$3 current_owner current_mode

    [[ -e $path ]] || return 0
    if [[ -L $path ]]; then
        warn "$path — символическая ссылка: владельца и права не меняю"
        return 0
    fi

    current_owner=$(stat -c '%U:%G' -- "$path")
    current_mode=$(stat -c '%a' -- "$path")
    [[ $current_owner == "$owner" ]] || run chown -- "$owner" "$path"
    [[ $current_mode == "$mode" ]] || run chmod "$mode" -- "$path"
}

install_agent_config() {
    ensure_dir "$AGENT_DIR" 750 "root:$GROUP_NAME"

    [[ ! -d $AGENT_CONFIG ]] || die "$AGENT_CONFIG — каталог, а нужен файл конфигурации"

    local content
    content=$(agent_json)

    if [[ -f $AGENT_CONFIG ]] && (( FORCE == 0 )); then
        if [[ $(<"$AGENT_CONFIG") == "$content" ]]; then
            say "agent.json уже актуален: $AGENT_CONFIG"
        else
            warn "agent.json уже существует и отличается; не перезаписываю без --force: $AGENT_CONFIG"
        fi
        ensure_file_mode "$AGENT_CONFIG" 640 "root:$GROUP_NAME"
        return 0
    fi

    write_file "$AGENT_CONFIG" "$content" 640 "root:$GROUP_NAME"

    if (( DRY_RUN == 0 )); then
        say "agent.json записан: $AGENT_CONFIG"
    fi
}

# Подставляет значения в шаблон. Для прямой раскладки дополнительно убирает
# /current и shared: приложение лежит прямо в ROOT, а var — в ROOT/var.
render_unit() {
    local template=$1 content

    [[ -f $template ]] || die "шаблон systemd не найден: $template"
    content=$(<"$template")

    content=${content//@@ROOT@@/$ROOT}
    content=${content//@@USER@@/$USER_NAME}
    content=${content//@@GROUP@@/$GROUP_NAME}

    if [[ $LAYOUT == 'direct' ]]; then
        content=${content//"ReadWritePaths=$ROOT/shared $ROOT/current/var"/"ReadWritePaths=$ROOT/var"}
        content=${content//"$ROOT/current"/"$ROOT"}
    fi

    if [[ $content =~ @@[A-Za-z_]+@@ ]]; then
        die "в шаблоне $template остались неизвестные маркеры: обновите скрипт вместе с шаблоном"
    fi

    if [[ $LAYOUT == 'direct' ]]; then
        [[ $content != *"$ROOT/current"* ]] || die "в шаблоне $template осталась ссылка на $ROOT/current при прямой раскладке"
        [[ $content != *"$ROOT/shared"* ]] || die "в шаблоне $template осталась ссылка на $ROOT/shared при прямой раскладке"
    else
        [[ $content == *"$ROOT/current"* ]] || warn "шаблон $template не содержит $ROOT/current: проверьте раскладку releases"
        [[ $content == *"$ROOT/shared"* ]] || warn "шаблон $template не содержит $ROOT/shared: проверьте раскладку releases"
    fi

    printf '%s\n' "$content"
}

# Сначала рендерим все три юнита и проверяем конфликты, и только потом пишем.
# Так при нехватке --force система не остаётся с частично обновлёнными
# службами.
install_units() {
    local names=(laiot-agent.service 'laiot-worker@.service' 'laiot-core@.service')
    local contents=()
    local conflicts=()
    local i name target rendered

    for i in "${!names[@]}"; do
        name=${names[$i]}
        rendered=$(render_unit "$APP_DIR/deploy/systemd/$name")
        contents[$i]=$rendered
        target="$SYSTEMD_DIR/$name"

        if [[ -f $target ]] && ! cmp -s <(printf '%s\n' "$rendered") "$target"; then
            if (( FORCE == 0 )); then
                conflicts+=("$target")
            fi
        fi
    done

    if (( ${#conflicts[@]} > 0 )); then
        if (( DRY_RUN == 0 )); then
            die "юниты отличаются от шаблонов: ${conflicts[*]}. Проверьте пути и повторите с --force, иначе службы будут ходить по старым путям."
        fi
        warn "dry-run: юниты отличаются (${conflicts[*]}); реальный запуск остановится без --force"
    fi

    for i in "${!names[@]}"; do
        name=${names[$i]}
        target="$SYSTEMD_DIR/$name"

        if [[ -f $target ]] && cmp -s <(printf '%s\n' "${contents[$i]}") "$target"; then
            say "юнит $name без изменений"
            ensure_file_mode "$target" 644 'root:root'
            continue
        fi

        # Без --force существующий отличающийся юнит не трогаем. В реальном
        # запуске до этого места дело не доходит — сработала проверка выше.
        if (( DRY_RUN )) && (( FORCE == 0 )) && [[ -f $target ]]; then
            printf '[dry-run] пропустить %s: содержимое отличается, нужен --force\n' "$target"
            continue
        fi

        write_file "$target" "${contents[$i]}" 644 'root:root'
        if (( DRY_RUN == 0 )); then
            say "юнит $name установлен"
        fi
    done
}

wait_for_agent_socket() {
    local attempt
    for attempt in 1 2 3 4 5 6 7 8 9 10; do
        if [[ -S $SOCKET ]]; then
            say "сокет агента готов: $SOCKET"
            return 0
        fi
        sleep 0.5
    done

    warn "сокет $SOCKET не появился за 5 секунд: проверьте systemctl status laiot-agent.service и journalctl -u laiot-agent.service"
}

find_fpm_units() {
    local file
    local units=()

    shopt -s nullglob
    for file in /lib/systemd/system/php*-fpm.service /etc/systemd/system/php*-fpm.service; do
        units+=("${file##*/}")
    done
    shopt -u nullglob

    if (( ${#units[@]} == 0 )); then
        return 0
    fi

    printf '%s\n' "${units[@]}" | sort -u
}

configure_fpm() {
    local units unit

    units=$(find_fpm_units)
    if [[ -z $units ]]; then
        say 'юнит php*-fpm не найден: перезагружать FPM нечего'
        return 0
    fi

    while IFS= read -r unit; do
        [[ -n $unit ]] || continue
        if (( START_NOW )); then
            # reload не должен валить уже выполненную настройку: FPM мог быть
            # остановлен или управляться панелью хостинга.
            if ! run "$SYSTEMCTL" reload "$unit"; then
                warn "не удалось перезагрузить $unit — проверьте вручную"
            fi
        else
            say "подсказка: $SYSTEMCTL reload $unit — применит настройки PHP-FPM (без --start скрипт этого не делает)"
        fi
    done <<< "$units"
}

configure_services() {
    run "$SYSTEMCTL" daemon-reload

    run "$SYSTEMCTL" enable laiot-agent.service
    run "$SYSTEMCTL" restart laiot-agent.service

    local task
    for task in "${CORE_TASKS[@]}"; do
        if (( START_NOW )); then
            run "$SYSTEMCTL" enable --now "laiot-core@${task}.service"
        else
            run "$SYSTEMCTL" enable "laiot-core@${task}.service"
        fi
    done

    if (( DRY_RUN == 0 )); then
        if ! "$SYSTEMCTL" is-active --quiet laiot-agent.service; then
            warn 'служба laiot-agent.service не активна: journalctl -u laiot-agent.service -n 50'
        fi
        wait_for_agent_socket
    fi

    configure_fpm
}

print_summary() {
    if (( DRY_RUN )); then
        say 'это был --dry-run: ни файлы, ни службы не изменены'
    else
        say 'системная часть настроена'
    fi

    cat <<EOF

Что дальше:
  1. Откройте https://<ваш-сайт>/install — мастер запишет .env, выполнит
     миграции и создаст администратора. Ключ установки лежит в
     $APP_DIR/var/install.key; прочитайте его по SSH или в файловом менеджере.
  2. Если скрипт запускали без --start, после /install запустите core-службы:
       $SYSTEMCTL start laiot-core@queue.service laiot-core@events.service \\
         laiot-core@timeouts.service laiot-core@maintenance.service
  3. Проверьте журналы и состояние:
       $SYSTEMCTL status laiot-core@events.service
       journalctl -u laiot-core@events.service -n 100
  4. nginx, PHP-FPM, MariaDB и Redis скрипт не настраивает. Что остаётся
     вручную — docs/laiot/deploy.md.

Подсказка: chmod +x deploy/setup-ubuntu.sh — и скрипт можно запускать как
./deploy/setup-ubuntu.sh.
EOF
}

main() {
    parse_options "$@"
    assert_root
    validate_option_paths
    resolve_layout
    AGENT_DIR=${AGENT_CONFIG%/*}
    resolve_identity
    resolve_tools
    preflight
    ensure_directories
    install_agent_config
    install_units
    configure_services
    print_summary
}

main "$@"

# Подсказка: chmod +x deploy/setup-ubuntu.sh — и запуск без явного bash.
