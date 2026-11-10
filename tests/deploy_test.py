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
if [[ "$*" == *"-xzf"* ]]; then
 last="${!#}"
 mkdir -p "$last/database/migrations"
 touch "$last/artisan" "$last/docker-compose.prod.yml"
fi
'''
  for name,body in [('docker',docker),('tar',tar),('mv','#!/bin/bash\nexit 0\n')]:
   p=bins/name;p.write_text(body);p.chmod(0o755)
  env=dict(os.environ,PATH=str(bins)+':'+os.environ['PATH'],DEPLOY_ROOT=tmp,CALLS=str(log),PENDING=str(pending),FAIL=fail)
  result=subprocess.run(['bash',script,'archive','configuration'],env=env,capture_output=True,text=True)
  calls=log.read_text(); success=(pending in [0,2] and not fail)
  assert (result.returncode==0)==success,(pending,fail,result.stderr)
  assert (' db:backup ' in calls)==(pending==2),(pending,fail,calls)
  if fail=='db:backup': assert ' artisan migrate ' not in calls
  if pending==1: assert ' artisan down ' not in calls
  if fail=='migrate': assert ' artisan up ' not in calls
  print('PASS',pending,fail or 'normal')
