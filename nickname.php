<?php

/**
 *  昵称生成
 *  @author renjianzhi<renjianzhi@100tal.com>
 *  @copyright xes
 */
function _getAutoNicknameStr($num)
{
    if ($num < 0) {
        return false;
    }
    $result = array();
    while ($num > 0) {
        $temp = $num % 52;
        if ($temp >= 10 && $temp < 36) {
            $temp += 87;
            $temp = chr($temp);
        }
        if ($temp >= 36) {
            $temp += 29;
            $temp = chr($temp);
        }
        $result[] = $temp;
        $num = (int) ($num / 52);
    }
    $resultArr = array_reverse(array_pad($result, 6, 0));
    return '学员' . implode($resultArr);
}
