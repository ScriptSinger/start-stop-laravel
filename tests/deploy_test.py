import os, pathlib, tempfile, subprocess
script=str(pathlib.Path('docker/deploy.sh').resolve())
for pending,fail in [(0,''),(2,''),(1,''),(2,'db:backup'),(2,'migrate'),(0,'optimize')]:
 with tempfile.TemporaryDirectory() as tmp:
  root=pathlib.Path(tmp); bins=root/'bin'; bins.mkdir(); log=root/'calls'
  docker='''#!/bin/bash
printf '%s\\n' "$*" >> "$CALLS"
if [[ "$*" == *"migrate:status"* ]]; then exit "$PENDING"; fi
if [[ -n "$FAIL" && " $* " == *" $FAIL "* ]]; then exit 1; fi
exit 0
'''
  tar='''#!/bin/bash
if [[ "$*" == *"-xpzf"* ]]; then
 while [[ $# -gt 0 ]]; do
  if [[ "$1" == "-C" ]]; then shift; target="$1"; break; fi
  shift
 done
 mkdir -p "$target/database/migrations"
 touch "$target/artisan" "$target/docker-compose.prod.yml"
fi
'''
  for name,body in [('docker',docker),('tar',tar),('mv','#!/bin/bash\nexit 0\n')]:
   p=bins/name;p.write_text(body);p.chmod(0o755)
  env=dict(os.environ,PATH=str(bins)+':'+os.environ['PATH'],DEPLOY_ROOT=tmp,CALLS=str(log),PENDING=str(pending),FAIL=fail)
  for i in range(4):
   previous = root / f'.previous-old{i:05d}'
   previous.mkdir()
   (previous / '.successful').touch()
   os.utime(previous / '.successful', (100 + i, 100 + i))
  (root / '.previous-failed00').mkdir()
  result=subprocess.run(['bash',script,'archive','configuration'],env=env,capture_output=True,text=True)
  calls=log.read_text(); success=(pending in [0,2] and not fail)
  assert (result.returncode==0)==success,(pending,fail,result.stderr)
  assert not list(root.glob('.release-*')),(pending,fail,'staging directory was left behind')
  assert (root / '.previous-failed00').is_dir()
  assert len(list(root.glob('.previous-*/.successful'))) == (3 if success else 4)
  assert (' db:backup ' in calls)==(pending==2),(pending,fail,calls)
  if fail=='db:backup': assert ' artisan migrate ' not in calls
  if pending==1: assert ' artisan down ' not in calls
  if fail=='migrate': assert ' artisan up ' not in calls
  print('PASS',pending,fail or 'normal')

# Real archive extraction must preserve public permissions despite private umask.
with tempfile.TemporaryDirectory() as tmp:
 root=pathlib.Path(tmp); source=root/'source'; target=root/'target'
 (source/'public').mkdir(parents=True); target.mkdir()
 (source/'public'/'index.php').write_text('fixture')
 (source/'public').chmod(0o755); (source/'public'/'index.php').chmod(0o644)
 archive=root/'release.tar.gz'
 subprocess.run(['tar','-czf',str(archive),'-C',str(source),'public'],check=True)
 subprocess.run(['bash','-c','umask 077; tar -xpzf "$1" -C "$2"','bash',str(archive),str(target)],check=True)
 assert (target/'public').stat().st_mode & 0o777 == 0o755
 assert (target/'public'/'index.php').stat().st_mode & 0o777 == 0o644
 print('PASS real archive permissions with restrictive umask')

# A second process must stop before it touches the release or application.
import fcntl
with tempfile.TemporaryDirectory() as tmp:
 lock = pathlib.Path(tmp) / '.deploy.lock'
 with lock.open('w') as handle:
  fcntl.flock(handle, fcntl.LOCK_EX | fcntl.LOCK_NB)
  result = subprocess.run(['bash', script, 'unused-archive', 'unused-config'],
                          env=dict(os.environ, DEPLOY_ROOT=tmp), capture_output=True, text=True)
  assert result.returncode != 0
  assert 'Another deployment is running' in result.stderr
  assert sorted(p.name for p in pathlib.Path(tmp).iterdir()) == ['.deploy.lock']
 print('PASS concurrent deployment is rejected before modifying files')

# A real release is staged selectively: migration discovery must work without
# extracting production code and assets into the temporary directory.
with tempfile.TemporaryDirectory() as tmp:
 root = pathlib.Path(tmp)
 source = root / 'source'
 stage = root / 'stage'
 (source / 'database' / 'migrations').mkdir(parents=True)
 (source / 'public').mkdir()
 stage.mkdir()
 for name in ['artisan', 'docker-compose.prod.yml', 'database/migrations/example.php', 'public/index.php']:
  (source / name).write_text('fixture')
 archive = root / 'release.tar.gz'
 subprocess.run(['tar', '-czf', str(archive), '-C', str(source), 'artisan', 'docker-compose.prod.yml', 'database', 'public'], check=True)
 subprocess.run(['tar', '-xpzf', str(archive), '-C', str(stage), 'artisan', 'docker-compose.prod.yml', 'database/migrations'], check=True)
 assert (stage / 'database/migrations/example.php').exists()
 assert (stage / 'artisan').exists()
 assert not (stage / 'public').exists()
 print('PASS selective staging from a real archive')
