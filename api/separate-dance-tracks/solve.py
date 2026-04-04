#!/usr/bin/python3

import json
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

def scoreOrder(order):
  score = 0
  for group in data['conflictGroups']:
    gsize = len(group) + 2 # We iterate over the edges as well
    end = gsize - 1
    for i in range(gsize):
      for j in range(i + 1, gsize):
        if i == 0:
          if j == end:
            continue
          else:
            dist = order[group[j-1]]
        elif j == end:
          dist = num_slots - order[group[i-1]]
        else:
          dist = abs(order[group[i-1]] - order[group[j-1]])
        if dist < len(penalties) + 1:
          score += penalties[dist-1]

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

print(json.dumps({'slotOrder': order}))
