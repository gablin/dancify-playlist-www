include "globals.mzn";

int: num_slots;
array[int] of set of int: groups;

array[int] of int: PenaltyTableBase =
  [ 1000,
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
       1,
  ];

int: largest_group_size =
  max([ card(groups[g]) | g in index_set(groups) ]);
array[int] of int: group_penalty_factors =
  [ largest_group_size - card(groups[g]) + 1 | g in index_set(groups) ];

set of int: GroupSet = index_set(groups);
set of int: DistanceSet = 0..num_slots;
set of int: OrderSet = 1..num_slots;
set of int: PositionSet = 0..num_slots+1;

set of int: groupWithEdges(GroupSet: g) =
  array2set(groups[g] ++ {0, max(PositionSet)});

array[PositionSet] of int: PenaltyTable =
  array1d( PositionSet,
           [ if d > 0 /\ d in index_set(PenaltyTableBase)
             then PenaltyTableBase[d]
             else 0 endif
           | d in PositionSet
           ]
         );

array[OrderSet] of var OrderSet: order;

% The distances also includes the distance to any of the two edges, which we
% also want to maximize. Otherwise the model will try to push everything into
% the edges to maximize the distances.
array[GroupSet, PositionSet, PositionSet] of var DistanceSet: distances;

array[GroupSet, PositionSet, PositionSet] of var int: penalties;

constraint
alldifferent(order);

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
            else distances[g, i, j] == 0
            endif
       else true
       endif
       /\
       % Distance to the second edge
       if j == max(PositionSet)
       then if i > 0
            then distances[g, i, j] == max(PositionSet) - order[i]
            else distances[g, i, j] == 0
            endif
       else true
       endif
       /\
       % Distance between two slots
       if i > 0 /\ j < max(PositionSet)
       then distances[g, i, j] == abs(order[i] - order[j])
       else true
       endif
  else distances[g, i, j] == 0
  endif
);

% Fix distances for slots not in group.
constraint
forall ( g in GroupSet, i, j in PositionSet
         where i >= j \/
               not (i in groupWithEdges(g)) \/
               not (j in groupWithEdges(g))
       )
( distances[g, i, j] == 0 );

constraint
forall (g in GroupSet, i, j in PositionSet)
( penalties[g, i, j] == PenaltyTable[distances[g, i, j]] );

solve
  :: seq_search( [ int_search( order
                             , input_order
                             , indomain_max
                             , complete
                             )
                 ]
               )
  minimize sum(penalties);

output
[ "{ \"slotOrder\": \(order) }\n" ];
