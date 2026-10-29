#!/usr/bin/env bash
# 2026-10-29 — настоящий Unix-сокет агента и разные UID, без systemd и рабочих сервисов.
set -Eeuo pipefail
[[ $(id -u) == 0 ]] || { printf 'Тест требуется запускать от root внутри изолированного Linux-контейнера.\n'; exit 1; }
repository=$(cd "$(dirname "$0")/.." && pwd)
directory=$(mktemp -d /tmp/laiot-agent-test.XXXXXXXX)
[[ $(realpath -- "$directory") == /tmp/laiot-agent-test.* ]]
pid=''
cleanup() {
    if [[ -n $pid ]] && kill -0 "$pid" 2>/dev/null; then kill "$pid"; wait "$pid" || true; fi
    [[ $(realpath -- "$directory") == /tmp/laiot-agent-test.* ]] || return 1
    rm -rf -- "$directory"
}
trap cleanup EXIT
chmod 755 "$directory"
mkdir -p "$directory/app/modules/core/demo" "$directory/units"
printf 'protected-worker\n' > "$directory/units/laiot-worker@.service"
printf 'protected-core\n' > "$directory/units/laiot-core@.service"
chmod 644 "$directory/units/"*.service
cat > "$directory/systemctl" <<'SH'
#!/usr/bin/env bash
printf '%s %s\n' "$(id -u)" "$*" >> "$(dirname "$0")/calls"
if [[ $1 == show ]]; then printf 'ActiveState=active\nSubState=running\nMainPID=123\nExecMainStartTimestamp=test\n'; fi
SH
chmod 700 "$directory/systemctl"
php -r '$d=$argv[1];file_put_contents($d."/agent.json",json_encode(["root"=>$d."/app","user"=>"www-data","group"=>"www-data","allowed_uid"=>(int)$argv[2],"socket"=>$d."/runtime/agent.sock","systemctl"=>$d."/systemctl","unit_dir"=>$d."/units"],JSON_THROW_ON_ERROR));' "$directory" "$(id -u www-data)"
chmod 600 "$directory/agent.json"
LAIOT_AGENT_CONFIG="$directory/agent.json" php "$repository/bin/laiot-agent" serve > "$directory/agent.log" 2>&1 &
pid=$!
for attempt in {1..40}; do [[ ! -S $directory/runtime/agent.sock ]] || break; sleep 0.1; done
[[ -S $directory/runtime/agent.sock ]] || { cat "$directory/agent.log"; exit 1; }
cat > "$directory/client.php" <<'PHP'
<?php
require $argv[1].'/vendor/autoload.php';
$client=new LaIOT\Services\Systemd\SystemdAgentClient($argv[2].'/runtime/agent.sock',2);
if (($argv[3]??'')==='deny') {
    try {$client->coreStatus('websocket');throw new LogicException('Unrelated UID reached the agent');}
    catch (RuntimeException) {echo "DENIED\n";exit;}
}
foreach ([$client->coreStart('websocket'),$client->coreStop('websocket'),$client->coreStart('module.demo')] as $result) {
    if (($result['exit_code']??1)!==0) throw new LogicException('Core action failed');
}
if ($client->coreStatus('websocket')['active']!=='active') throw new LogicException('Status failed');
try {$client->coreStart('websocket; reboot');throw new LogicException('Unsafe task accepted');}
catch (RuntimeException) {}
$units=$client->request(['action'=>'worker.install'])['units'];
if (count($units)!==2) throw new LogicException('Protected units not found');
echo "AUTHORIZED\n";
PHP
chmod 644 "$directory/client.php"
runuser -u www-data -- php "$directory/client.php" "$repository" "$directory"
runuser -u nobody -- php "$directory/client.php" "$repository" "$directory" deny
grep -Fxq '0 start laiot-core@websocket' "$directory/calls"
grep -Fxq '0 stop laiot-core@websocket' "$directory/calls"
grep -Fxq '0 start laiot-core@module.demo' "$directory/calls"
! grep -Eq 'daemon-reload|reboot' "$directory/calls"
[[ $(< "$directory/units/laiot-core@.service") == protected-core ]]
printf 'PASS: UID сайта управляет core через root-агент; чужой UID и произвольные команды запрещены; юниты не перезаписываются.\n'
