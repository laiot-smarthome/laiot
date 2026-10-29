import {execFileSync, spawnSync} from 'node:child_process';

// 2026-10-29 — функции workflow; сообщения коммитов никогда не исполняются оболочкой.
const versionPattern = '(?:0|[1-9][0-9]*)\\.(?:0|[1-9][0-9]*)\\.(?:0|[1-9][0-9]*)(?:-[0-9A-Za-z-]+(?:\\.[0-9A-Za-z-]+)*)?(?:\\+[0-9A-Za-z-]+(?:\\.[0-9A-Za-z-]+)*)?';
const versionRegex = new RegExp(`^${versionPattern}$`);
const tagRegex = new RegExp(`^(main|beta)-(${versionPattern})$`);

export function releaseIdentity({refType, refName, message = '', version = ''}) {
    if (refType === 'tag') {
        const match = tagRegex.exec(refName);
        if (!match) throw new Error('Тег должен иметь вид main-X.Y.Z или beta-X.Y.Z.');
        return {channel: match[1], tag: refName};
    }
    if (!['main', 'beta'].includes(refName)) throw new Error('Выпуск разрешён только из main или beta.');
    if (!version) {
        const markers = [...message.matchAll(/\[release:\s*([^\]\r\n]*)\]/g)];
        if (markers.length !== 1) throw new Error('Укажите одну пометку [release: X.Y.Z] в последнем коммите.');
        version = markers[0][1].trim();
    }
    if (!versionRegex.test(version)) throw new Error('Номер выпуска должен иметь вид X.Y.Z, например 0.0.3.');
    return {channel: refName, tag: `${refName}-${version}`};
}

export function releaseNotes({channel, tag, sha, repository, releases, directory = process.cwd()}) {
    const git = args => execFileSync('git', args, {cwd: directory, encoding: 'utf8', maxBuffer: 32 * 1024 * 1024}).trimEnd();
    const candidates = releases.filter(release => !release.draft && release.tag_name !== tag && (
        (tagRegex.test(release.tag_name) && release.tag_name.startsWith(`${channel}-`))
        || (versionRegex.test(release.tag_name) && release.target_commitish === channel)
    )).sort((a, b) => Date.parse(b.published_at) - Date.parse(a.published_at));
    let previous = '';
    for (const release of candidates) {
        const ancestor = spawnSync('git', ['merge-base', '--is-ancestor', `refs/tags/${release.tag_name}`, sha], {cwd: directory, encoding: 'utf8'});
        if (ancestor.error) throw ancestor.error;
        if (ancestor.status === 1) continue;
        if (ancestor.status !== 0) throw new Error(`Недоступен тег предыдущего релиза ${release.tag_name}: ${ancestor.stderr}`);
        previous = release.tag_name;
        break;
    }
    const fields = git(['log', '--reverse', '--format=%H%x00%s%x00%b%x00', previous ? `${previous}..${sha}` : sha]).split('\0');
    const commits = [];
    const escape = text => text.replace(/[\\`*_{}\[\]<>#|]/g, '\\$&');
    for (let index = 0; index + 2 < fields.length; index += 3) {
        commits.push({hash: fields[index].trim(), subject: fields[index + 1], body: fields[index + 2].trim()});
    }
    const header = `# ${tag}\n\nКанал: **${channel}**. Коммитов: **${commits.length}**.\n\n`;
    const render = ({hash, subject, body}) => `- ${escape(subject)} ([${hash.slice(0, 8)}](https://github.com/${repository}/commit/${hash}))\n${body ? '\n' + body.split(/\r?\n/).map(line => `  > ${line}`).join('\n') + '\n' : ''}`;
    const compare = previous ? `\n[Все изменения с ${escape(previous)}](https://github.com/${repository}/compare/${encodeURIComponent(previous)}...${encodeURIComponent(tag)})\n` : '\nПервый выпуск канала: включена вся история до указанного коммита.\n';
    const changelog = header + '## Изменения\n\n' + (commits.map(render).join('\n') || 'Новых коммитов после предыдущего выпуска нет.\n') + compare;
    let notes = changelog;
    // GitHub ограничивает описание; полная история всегда остаётся отдельным файлом релиза.
    if (Buffer.byteLength(changelog, 'utf8') > 100000) {
        notes = header + `[Полный список коммитов — CHANGELOG.md](https://github.com/${repository}/releases/download/${encodeURIComponent(tag)}/CHANGELOG.md)\n\n## Последние изменения\n\n`;
        for (const commit of commits.slice(-50)) {
            const line = render({...commit, body: ''});
            if (Buffer.byteLength(notes + line, 'utf8') > 90000) break;
            notes += line;
        }
        notes += compare;
    }
    return {previous, notes, changelog};
}
