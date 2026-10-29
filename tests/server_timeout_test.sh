#!/usr/bin/env bash
# 2026-10-29 — реальный долгий FastCGI-запрос и параллельная доступность, без приложения/БД.
set -Eeuo pipefail
duration=${1:-602}
[[ $duration =~ ^[0-9]+$ ]] && (( duration >= 1 && duration <= 605 ))
repository=$(cd "$(dirname "$0")/.." && pwd)
directory=$(mktemp -d /tmp/laiot-timeout-test.XXXXXXXX)
[[ $(realpath -- "$directory") == /tmp/laiot-timeout-test.* ]]
cleanup() {
    if [[ -f $directory/nginx.pid ]]; then kill "$(< "$directory/nginx.pid")" 2>/dev/null || true; fi
    if [[ -f $directory/fpm.pid ]]; then kill "$(< "$directory/fpm.pid")" 2>/dev/null || true; fi
    [[ $(realpath -- "$directory") == /tmp/laiot-timeout-test.* ]] || return 1
    rm -rf -- "$directory"
}
trap cleanup EXIT
chmod 755 "$directory"
mkdir -m 755 "$directory/public"
cat > "$directory/public/index.php" <<'PHP'
<?php
$seconds = min(605, max(0, (int)($_GET['seconds'] ?? 0)));
$started = microtime(true);
if ($seconds > 0) sleep($seconds);
header('Content-Type: application/json');
echo json_encode(['elapsed'=>microtime(true)-$started,'limit'=>(int)ini_get('max_execution_time'),'uid'=>posix_geteuid()]);
PHP
chmod 644 "$directory/public/index.php"
mkdir -m 755 "$directory/public/.git"
printf 'dummy-private-value\n' > "$directory/public/.env"
printf 'dummy-repository-config\n' > "$directory/public/.git/config"
printf '<?php echo "must-not-execute";\n' > "$directory/public/other.php"
chmod 644 "$directory/public/.env" "$directory/public/.git/config" "$directory/public/other.php"
cat > "$directory/generate.php" <<'PHP'
<?php
require $argv[1].'/deploy/lib/FastPanel.php';
$d=$argv[2];
$backend="[laiot-probe]\nuser = www-data\ngroup = www-data\nlisten = $d/fpm.sock\nlisten.owner = www-data\nlisten.group = www-data\nlisten.mode = 0660\n";
file_put_contents($d.'/fpm.conf',"[global]\npid = $d/fpm.pid\nerror_log = $d/fpm.log\n".LaIOT\Deployment\FastPanel::fpm($backend,2));
$frontend=<<<'NGINX'
server {
    listen 127.0.0.1:18080;
    server_name localhost;
    set $root_path @@PUBLIC@@;
    root $root_path;
    location / { try_files $uri $uri/ /index.php?$args; }
    location ~ \.php$ {
        include /etc/nginx/fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_pass unix:@@SOCKET@@;
    }
}
NGINX;
$frontend=str_replace(['@@PUBLIC@@','@@SOCKET@@'],[$d.'/public',$d.'/fpm.sock'],LaIOT\Deployment\FastPanel::nginx($frontend));
file_put_contents($d.'/nginx.conf',"user www-data;\npid $d/nginx.pid;\nerror_log $d/nginx.log;\nevents { worker_connections 128; }\nhttp { access_log off; $frontend }\n");
PHP
php "$directory/generate.php" "$repository" "$directory"
fpm=''
for candidate in php-fpm php-fpm8.3; do if command -v "$candidate" >/dev/null; then fpm=$(command -v "$candidate"); break; fi; done
[[ -n $fpm ]]
"$fpm" --test --fpm-config "$directory/fpm.conf"
"$fpm" --daemonize --fpm-config "$directory/fpm.conf"
nginx -e "$directory/nginx.log" -t -c "$directory/nginx.conf"
nginx -e "$directory/nginx.log" -c "$directory/nginx.conf"
url=http://127.0.0.1:18080/laiot-probe
curl --noproxy '*' --fail --silent --max-time 5 "$url" > "$directory/ready.json"
php -r '$d=json_decode(file_get_contents($argv[1]),true,flags:JSON_THROW_ON_ERROR);if($d["limit"]!==600||$d["uid"]!==33)exit(1);' "$directory/ready.json"
for path in /.env /.git/config /other.php; do
    [[ $(curl --noproxy '*' --silent --max-time 5 -o /dev/null -w '%{http_code}' "http://127.0.0.1:18080$path") == 404 ]]
done
curl --noproxy '*' --fail --silent --show-error --max-time "$((duration + 30))" "$url?seconds=$duration" > "$directory/long.json" &
request=$!
samples=0
started=$SECONDS
interval=20
(( duration >= interval )) || interval=$duration
printf 'START: duration=%s; PHP=600s; FPM/nginx=660s; max_children=2\n' "$duration"
while kill -0 "$request" 2>/dev/null; do
    curl --noproxy '*' --fail --silent --show-error --max-time 3 "$url" > "$directory/short.json"
    samples=$((samples + 1))
    printf 'Responsive: elapsed=%ss, parallel PHP requests=%s\n' "$((SECONDS - started))" "$samples"
    sleep "$interval"
done
wait "$request"
php -r '$d=json_decode(file_get_contents($argv[1]),true,flags:JSON_THROW_ON_ERROR);if($d["elapsed"]<(int)$argv[2]||$d["limit"]!==600)exit(1);echo "PASS: long PHP request ",round($d["elapsed"],2)," seconds; HTTP 200; parallel PHP remains responsive",PHP_EOL;' "$directory/long.json" "$duration"
