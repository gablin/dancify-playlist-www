#!/usr/bin/python3

import subprocess

import json
import sys

json_file = sys.argv[1]
time_limit = int(sys.argv[2])
num_workers = int(sys.argv[3])

workers = []
for _ in range(num_workers):
  p = subprocess.Popen([sys.executable,
                        'solve_worker.py',
                        json_file,
                        str(time_limit),
                        str(num_workers)],
                       stdout=subprocess.PIPE)
  workers.append(p)

best_order = []
best_score = None
for p in workers:
  (stdout, _) = p.communicate()
  data = json.loads(stdout)
  if best_score is None or data['score'] < best_score:
    best_order = data['order']
    best_score = data['score']

print(json.dumps({'order': best_order, 'score': best_score}))
