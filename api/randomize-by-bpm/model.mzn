include "globals.mzn";

array[int] of int: bpm;
array[int] of int: genres;
array[int, int] of int: ranges;
array[int] of int: min_bpm_distance;
set of int: RangeSet = index_set_1of2(ranges);
bool: dance_slot_same_genre;

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
array[DanceSlotSet] of var 0..card(RangeSet): slot_n;
array[DanceSlotSet] of var bool: slot_full;
var int: n_slot_full = sum(slot_full);

% Assert number of ranges is 1+ number of min BPM distances.
constraint
assert( card(index_set_1of2(ranges)) == card(index_set(min_bpm_distance))+1
      , "num(ranges) == num(min_bpm_distance)+1"
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
     ( has_track[i] /\ has_track[i+1] -> genres[order[i]] == genres[order[i+1]] )
else true
endif;

% Ensure that there is at least minimal BPM distance between two adjacent tracks
% in the same dance slot. A positive/negative BPM distance means that the
% following track must be faster/slower by at least that much. A BPM distance
% of 0 means this check is disabled.
constraint
forall (i in index_set(min_bpm_distance))
( let { int: d = min_bpm_distance[i] }
  in if d != 0
     then forall ( j in OrderSet
                   where (j mod card(RangeSet)) == (i mod card(RangeSet))
                 )
          ( (has_track[j] /\ has_track[j+1]) ->
            if d > 0
            then bpm[order[j+1]] - bpm[order[j]] >= d
            else bpm[order[j+1]] - bpm[order[j]] <= d
            endif
          )
     else true
     endif
);

% Count number of tracks in each slot.
constraint
let { int: N = card(RangeSet) }
in forall (i in DanceSlotSet)
   ( slot_n[i] = sum (j in ((i-1)*N)+1..((i-1)*N)+N) (has_track[j]) );

% Check whether a dance slot is full.
constraint
let { int: N = card(RangeSet) }
in forall (i in DanceSlotSet)
   ( slot_full[i] <-> slot_n[i] == N );

% Order dance slots in decreasing order of number of filled slots.
constraint
decreasing(slot_n);

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
