import test from 'node:test';
import assert from 'node:assert/strict';
import {mkdtempSync, rmSync} from 'node:fs';
import {tmpdir} from 'node:os';
import {join, resolve, dirname, basename} from 'node:path';
import {execFileSync} from 'node:child_process';
import {releaseIdentity, releaseNotes} from '../../.github/release.mjs';

// 2026-10-29 — отдельный локальный Git: без сети, рабочего checkout и настроек пользователя.
function repository(t) {
    const directory = mkdtempSync(join(tmpdir(), 'laiot-release-test-'));
    t.after(() => {
        assert.equal(dirname(resolve(directory)), resolve(tmpdir()));
        assert.ok(basename(directory).startsWith('laiot-release-test-'));
        rmSync(directory, {recursive: true, force: true});
    });
    const git = (args, input) => execFileSync('git', ['-c', `core.hooksPath=${join(directory, 'no-hooks')}`, ...args], {cwd: directory, input, encoding: 'utf8'}).trim();
    git(['init', '-b', 'beta']);
    git(['config', 'user.name', 'Release Test']);
    git(['config', 'user.email', 'release-test@example.invalid']);
    const commit = message => { git(['commit', '--allow-empty', '-F', '-'], message); return git(['rev-parse', 'HEAD']); };
    return {directory, git, commit};
}

test('канал берётся из ветки, номер — из пометки или ручного запуска', () => {
    assert.deepEqual(releaseIdentity({refType: 'branch', refName: 'beta', message: 'Обновление\n\n[release: 0.0.3]'}), {channel: 'beta', tag: 'beta-0.0.3'});
    assert.deepEqual(releaseIdentity({refType: 'branch', refName: 'main', version: '1.0.0'}), {channel: 'main', tag: 'main-1.0.0'});
    assert.deepEqual(releaseIdentity({refType: 'tag', refName: 'beta-1.1.0-beta.2'}), {channel: 'beta', tag: 'beta-1.1.0-beta.2'});
});

test('неверная ветка, неоднозначная пометка и команды вместо номера отклоняются', () => {
    for (const input of [
        {refType: 'branch', refName: 'feature', version: '1.0.0'},
        {refType: 'branch', refName: 'beta', message: '[release: 1.0.0] [release: 2.0.0]'},
        {refType: 'branch', refName: 'beta', version: '1.0.0\nextra=bad'},
        {refType: 'branch', refName: 'beta', version: '$(touch injected)'},
        {refType: 'tag', refName: 'latest'},
    ]) assert.throws(() => releaseIdentity(input));
});

test('в описание входят все коммиты после прошлого выпуска своего канала и полные тела', t => {
    const {directory, git, commit} = repository(t);
    commit('Старый код'); git(['tag', 'beta-0.0.1']);
    git(['checkout', '-b', 'side']); commit('Не включено в beta'); git(['tag', 'beta-9.0.0']); git(['checkout', 'beta']);
    commit('Первая правка\n\nПодробное описание\nВторая строка');
    const sha = commit('Вторая правка $(do-not-run) [release: 0.0.2]');
    git(['tag', 'main-1.0.0']); git(['tag', 'beta-0.0.9']);
    const releases = [
        {tag_name: 'beta-9.0.0', published_at: '2026-10-29'},
        {tag_name: 'main-1.0.0', published_at: '2026-10-29'},
        {tag_name: 'beta-0.0.9', draft: true, published_at: '2026-10-29'},
        {tag_name: 'beta-0.0.1', published_at: '2026-10-28'},
    ];
    const result = releaseNotes({channel: 'beta', tag: 'beta-0.0.2', sha, repository: 'example/repo', releases, directory});
    assert.equal(result.previous, 'beta-0.0.1');
    assert.ok(result.notes.includes('Коммитов: **2**'));
    assert.ok(result.notes.includes('Подробное описание\n  > Вторая строка'));
    assert.ok(result.notes.includes('$(do-not-run)'));
    assert.ok(!result.notes.includes('Старый код'));
    assert.ok(!result.notes.includes('Не включено в beta'));
    assert.ok(result.notes.includes('/compare/beta-0.0.1...beta-0.0.2'));
});

test('первый выпуск включает историю, существующий числовой тег учитывается только для своей ветки', t => {
    const {directory, git, commit} = repository(t);
    commit('Исходная версия'); git(['tag', '0.0.2']);
    const sha = commit('Следующая версия');
    const context = {channel: 'beta', tag: 'beta-0.0.3', sha, repository: 'example/repo', directory};
    assert.ok(releaseNotes({...context, releases: []}).notes.includes('Исходная версия'));
    const result = releaseNotes({...context, releases: [{tag_name: '0.0.2', target_commitish: 'beta', published_at: '2026-10-29'}]});
    assert.equal(result.previous, '0.0.2');
    assert.ok(!result.notes.includes('Исходная версия'));
});

test('длинная история остаётся полной в CHANGELOG, описание укладывается в лимит', t => {
    const {directory, commit} = repository(t);
    const body = 'Подробности изменения. '.repeat(7000);
    const sha = commit('Большое изменение\n\n' + body);
    const result = releaseNotes({channel: 'beta', tag: 'beta-0.0.1', sha, repository: 'example/repo', releases: [], directory});
    assert.ok(result.changelog.includes(body.trim()));
    assert.ok(result.notes.includes('/CHANGELOG.md'));
    assert.ok(Buffer.byteLength(result.notes, 'utf8') < 100000);
});
