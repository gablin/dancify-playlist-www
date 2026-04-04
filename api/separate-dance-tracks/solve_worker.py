#!/usr/bin/python3

import json
import math
import os
import random
import sys
import time

data = json.load(open(sys.argv[1], 'r'))
time_limit = int(sys.argv[2])

penalties = [ 1000,
               100,
                75,
                50,
                40,
                30,
                20,
                10,
                 9,
                 8,
                 7,
                 6,
                 5,
                 4,
                 3,
                 2,
                 1 ]

num_slots = data['numSlots']

groups = data['conflictGroups']
largest_gsize = max([len(vs) for vs in groups])
group_penalty_factors = [math.ceil(largest_gsize / len(vs)) for vs in groups]

def scoreOrder(order):
  score = 0
  for (g, group) in enumerate(groups):
    gsize = len(group)
    for i in range(gsize):
      for j in range(i + 1, gsize):
        dist = abs(order[group[i]] - order[group[j]])
        if dist < len(penalties) + 1:
          score += penalties[dist - 1] * group_penalty_factors[g]

  return score

timeout = time.time() + time_limit
order = list(range(num_slots))

best_order = order
best_score = None
while time.time() < timeout:
  random.shuffle(order)
  score = scoreOrder(order)
  if best_score is None or score < best_score:
    best_order = order
    best_score = score

print(json.dumps({'order': best_order, 'score': best_score}))
