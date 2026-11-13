import os
import pathlib
import subprocess
import tempfile
import textwrap

workflow = pathlib.Path('.github/workflows/deploy.yml').read_text()
scripts = []
for block in workflow.split("<< 'EOF'\n")[1:]:
    scripts.append(textwrap.dedent(block.split('\n          EOF', 1)[0]))

assert len(scripts) == 2
for script in scripts:
    subprocess.run(['bash', '-n'], input=script, text=True, check=True)

for script, pending, failure in [(scripts[0], 0, ''), (scripts[0], 0, 'stop'),
                                 (scripts[1], 0, ''), (scripts[1], 2, ''),
                                 (scripts[1], 2, 'db:backup'), (scripts[1], 2, 'migrate'),
                                 (scripts[1], 1, ''), (scripts[1], 0, 'wget')]:
    with tempfile.TemporaryDirectory() as tmp:
        root = pathlib.Path(tmp)
        bins = root / 'bin'
        bins.mkdir()
        log = root / 'calls'
        docker = bins / 'docker'
        docker.write_text('''#!/bin/bash
printf '%s\\n' "$*" >> "$CALLS"
if [[ "$*" == *"migrate:status"* ]]; then exit "$PENDING"; fi
if [[ -n "$FAIL" && " $* " == *" $FAIL "* ]]; then exit 1; fi
''')
        docker.chmod(0o755)
        for name in ['mv', 'rm']:
            command = bins / name
            command.write_text('#!/bin/bash\nexit 0\n')
            command.chmod(0o755)
        env = dict(os.environ, PATH=str(bins) + ':' + os.environ['PATH'],
                   DEPLOY_ROOT=tmp, CALLS=str(log), FAIL=failure, PENDING=str(pending))
        command = script.replace('cd ~/start-stop', 'cd "$DEPLOY_ROOT"')
        result = subprocess.run(['bash', '-s', '--', '123', '1'], input=command,
                                env=env, text=True, capture_output=True)
        calls = log.read_text()
        assert (result.returncode == 0) == (failure == '' and pending != 1), (failure, result.stderr)
        if script == scripts[1] and pending == 0:
            assert ' artisan db:backup ' not in calls
        if script == scripts[1] and pending == 2:
            assert ' artisan db:backup ' in calls
        if failure in ('db:backup', 'migrate'):
            assert ' artisan up ' not in calls
        if failure == 'wget':
            assert calls.count(' artisan down ') == 1
            assert ' up -d worker scheduler' not in calls

assert 'rsync -rlptzc --delete' in workflow
assert 'release/public release/bootstrap' in workflow

with tempfile.TemporaryDirectory() as tmp:
    root = pathlib.Path(tmp)
    release = root / 'release'
    target = root / 'target'
    (release / 'app').mkdir(parents=True)
    (release / 'public').mkdir()
    (target / 'app').mkdir(parents=True)
    (target / 'public' / 'storage').mkdir(parents=True)
    (release / 'app' / 'new.php').write_text('new')
    (target / 'app' / 'old.php').write_text('old')
    (release / 'public' / 'sitemap.xml').write_text('release')
    (target / 'public' / 'sitemap.xml').write_text('server')
    (target / 'public' / 'storage' / 'photo.jpg').write_text('upload')
    subprocess.run(['rsync', '-rlptc', '--delete', str(release / 'app'), str(target)], check=True)
    subprocess.run(['rsync', '-rlptc', '--exclude=/public/storage', '--exclude=/public/sitemap.xml',
                    str(release / 'public'), str(target)], check=True)
    assert not (target / 'app' / 'old.php').exists()
    assert (target / 'app' / 'new.php').exists()
    assert (target / 'public' / 'sitemap.xml').read_text() == 'server'
    assert (target / 'public' / 'storage' / 'photo.jpg').exists()

print('PASS deployment workflow syntax and failure handling')
