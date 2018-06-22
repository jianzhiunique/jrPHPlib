<?php
var_dump(array_merge(['a' => 'a', 0 => 4], [1,2,3]));
/*
array(5) {
  ["a"]=>
  string(1) "a"
  [0]=>
  int(4)
  [1]=>
  int(1)
  [2]=>
  int(2)
  [3]=>
  int(3)
}
*/

var_dump(['a' => 'a', 0 => 4] + [1,2,3]);
/*
array(4) {
  ["a"]=>
  string(1) "a"
  [0]=>
  int(4)
  [1]=>
  int(2)
  [2]=>
  int(3)
*/
