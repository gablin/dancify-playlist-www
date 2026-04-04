include "globals.mzn";

int: num_slots;
array[int] of set of int: groups;
set of int: GroupSet = index_set(groups);
set of int: DistanceSet = 0..num_slots;
set of int: OrderSet = 1..num_slots;
set of int: PositionSet = 0..num_slots+1;

array[OrderSet] of var OrderSet: order;

set of int: groupWithEdges(GroupSet: g) =
  array2set(groups[g] ++ {0, max(PositionSet)});

% The distances also includes the distance to any of the two edges, which we
% also want to maximize. Otherwise the model will try to push everything into
% the edges to maximize the distances.
array[GroupSet, PositionSet, PositionSet] of var DistanceSet: distances;
array[GroupSet] of var DistanceSet: min_group_distances;

constraint
alldifferent(order);

constraint
forall (g in GroupSet)
( min_group_distances[g] = min([ distances[g, i, j] | i, j in PositionSet ]) );

% Set the distances.
constraint
forall ( g in GroupSet, i, j in PositionSet
         where i in groupWithEdges(g) /\ j in groupWithEdges(g)
       )
( if i < j
  then % Distance to the first edge
       if i == 0
       then if j < max(PositionSet)
            then distances[g, i, j] == order[j]
            else distances[g, i, j] == max(DistanceSet)
            endif
       else true
       endif
       /\
       % Distance to the second edge
       if j == max(PositionSet)
       then if i > 0
            then distances[g, i, j] == max(PositionSet) - order[i]
            else distances[g, i, j] == max(DistanceSet)
            endif
       else true
       endif
       /\
       % Distance between two slots
       if i > 0 /\ j < max(PositionSet)
       then distances[g, i, j] == abs(order[i] - order[j])
       else true
       endif
  else distances[g, i, j] == max(DistanceSet)
  endif
);

% Fix distances for slots not in group.
constraint
forall ( g in GroupSet, i, j in PositionSet
         where i >= j \/
               not (i in groupWithEdges(g)) \/
               not (j in groupWithEdges(g))
       )
( distances[g, i, j] == max(DistanceSet) );

solve
  maximize sum(min_group_distances);

output
[ "{ \"slotOrder\": \(order) }\n" ];
