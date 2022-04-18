include "globals.mzn";

array[int] of int: bpm;
array[int] of int: lengths;
array[int] of int: genres;
array[int, int] of int: ranges;
array[int, int] of int: diffs;
set of int: RangeSet = index_set_1of2(ranges);
bool: dance_slot_same_genre;
int: min_length;
int: max_length;

int: Null = 0;
set of int: TrackSet = index_set(bpm);
set of int: TrackSetOrNull = TrackSet union {Null};

set of int: OrderSet =
  % Order length must be at least as long as TrackSet and a multiple of RangeSet
  1..card(TrackSet) + let { int: r = card(TrackSet) mod card(RangeSet) }
                      in if r > 0 then card(RangeSet) - r else 0 endif;

array[OrderSet] of var TrackSetOrNull: order;
array[OrderSet] of var bool: has_track;

set of int: DanceSlotSet = 1..card(OrderSet) div card(RangeSet);
array[DanceSlotSet] of var bool: slot_full;
var int: n_slot_full = sum(slot_full);

OrderSet: firstOrderInDanceSlot(DanceSlotSet: d) =
  (d-1)*card(RangeSet) + 1;

% Assert number of ranges is 1+ number of min BPM distances.
constraint
assert( card(index_set_1of2(ranges)) == card(index_set_1of2(diffs))+1
      , "num(ranges) != num(diffs)+1"
      , true
      );

% Check if a certain position has a track.
constraint
forall (i in OrderSet) (has_track[i] <-> order[i] != Null);

% No track can be appear twice in the order.
constraint
alldifferent_except_0(order);

% Only put tracks with BPM within the given range in each slot at appropriate
% position.
constraint
forall (i in RangeSet)
( let { int: min_bpm = ranges[i, 1]
      , int: max_bpm = ranges[i, 2]
      }
  in forall ( j in OrderSet
              where (j mod card(RangeSet)) == (i mod card(RangeSet))
            )
  ( has_track[j] -> (min_bpm <= bpm[order[j]] /\ bpm[order[j]] <= max_bpm) )
);

% Only tracks with same genre can be place in the same dance slot.
constraint
if dance_slot_same_genre
then forall (s in DanceSlotSet, i in (s-1)*card(RangeSet)+1..s*card(RangeSet)-1 )
     ( ( has_track[i] /\ has_track[i+1] ) ->
       genres[order[i]] == genres[order[i+1]]
     )
else true
endif;

% Ensure that there BPM difference between two adjacent tracks
% in the same dance slot is within the acceptable difference range. A
% positive/negative BPM distance means that the following track must be
% faster/slower by at least that much. A BPM difference range of 0 means this
% check is disabled.
constraint
forall (i in index_set_1of2(diffs))
( let { int: v1 = diffs[i, 1]
      , int: v2 = diffs[i, 2]
      , int: min_d = if v1 >= 0 /\ v2 > 0 then min(v1, v2) else max(v1, v2) endif
      , int: max_d = if v1 >= 0 /\ v2 > 0 then max(v1, v2) else min(v1, v2) endif
      }
  in forall ( j in OrderSet
              where (j mod card(RangeSet)) == (i mod card(RangeSet))
            )
     ( assert( v1*v2 >= 0
             , "differences have non-matching signs: \(v1), \(v2)"
             , true
             ) /\
       ( (has_track[j] /\ has_track[j+1]) ->
         if max_d > 0
         then bpm[order[j+1]] - bpm[order[j]] >= min_d
         else bpm[order[j+1]] - bpm[order[j]] <= min_d
         endif
       ) /\
       ( (has_track[j] /\ has_track[j+1]) ->
         if max_d > 0
         then bpm[order[j+1]] - bpm[order[j]] <= max_d
         else bpm[order[j+1]] - bpm[order[j]] >= max_d
         endif
       )
     )
);

% Either a dance slot is full, or it contains no tracks at all.
constraint
forall (i in DanceSlotSet)
( let { OrderSet: fo = firstOrderInDanceSlot(i) }
  in forall (j in fo+1..fo+card(RangeSet)-1)
     ( has_track[fo] <-> has_track[j] )
);

% If a dance slot has tracks, then the totalt length of the tracks must be within
% the specified range.
constraint
forall (i in DanceSlotSet)
( let { OrderSet: fo = firstOrderInDanceSlot(i) }
  in slot_full[i] ->
     let { var int: l = sum (j in fo..fo+card(RangeSet)-1) (lengths[order[j]]) }
     in ( l >= min_length /\ l <= max_length )
);

% Check whether a dance slot is full.
constraint
forall (i in DanceSlotSet)
( slot_full[i] <-> has_track[firstOrderInDanceSlot(i)] );

% Order dance slots in decreasing order of number of filled slots.
constraint
decreasing(slot_full);

% Number of filled dance slots cannot be more than there are tracks available
% to fill them.
constraint
n_slot_full <= card(TrackSet) div card(RangeSet);

% Maximize number of songs appearing in the order.
solve
  :: seq_search( [ int_search( [ [ has_track[i], order[i] ][k]
                               | i in OrderSet
                               , k in 1..2
                               ]
                             , input_order
                             , indomain_max
                             , complete
                             )
                 ]
               )
  maximize n_slot_full;

output
[ "{ \"trackOrder\": \(order) }\n" ];
