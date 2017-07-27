<?php

/**
 *  已读未读bitmap计算
 *  @author renjianzhi<renjianzhi@100tal.com>
 *  @copyright xes
 */
function getStatus($index, $str)
{
    $strIndex = (int) (($index - 1) / 8);
    //如果索引在长度之外，直接返回'0'
    if (strlen($str) < ($strIndex + 1)) {
        return '0';
    }
    $binIndex = ($index - 1) % 8;
    $statusChar = $str[$strIndex];
    $statusInt = ord($statusChar);
    $statusBin = decbin($statusInt);
    $statusBin = str_pad($statusBin, 8, '0', STR_PAD_LEFT);
    $status = $statusBin[7 - $binIndex];
    return $status;
}

function setStatus($index, $status = '1', $str = '')
{
    if (!is_string($str) || !is_numeric($index) || $index < 1 || !in_array((int) $status, array(1, 0))) {
        return false;
    }
    //如果字符串为空，需要生成
    $genFlag = $str === '' ? true : false;
    $strIndex = (int) (($index - 1) / 8);
    $binIndex = ($index - 1) % 8;
    //如果字符串长度不够，先进行填充
    if ($genFlag || !$genFlag && strlen($str) < ($strIndex + 1)) {
        $str = str_pad($str, ($strIndex + 1), chr(0));
    }
    //获取索引位上的原字符
    $preChar = $str[$strIndex];
    $preInt = ord($preChar);
    $preBin = decbin($preInt);
    $preBin = str_pad($preBin, 8, '0', STR_PAD_LEFT);
    //替换原二进制位上的状态
    $preBin[(7 - $binIndex)] = $status;
    $newInt = bindec($preBin);
    $newChar = chr($newInt);
    $str[$strIndex] = $newChar;
    return $str;
}

$testStr = setStatus(1);
var_dump(getStatus(1, $testStr));
var_dump(getStatus(20, $testStr));
$testStr = setStatus(20, '1', $testStr);
var_dump(getStatus(20, $testStr));
$testStr = setStatus(300, '1', $testStr);
var_dump($testStr);
var_dump(getStatus(20, $testStr));
$testStr = setStatus(30000, '1', $testStr);
var_dump($testStr);
$testStr2 = setStatus(30000);
var_dump($testStr2);
var_dump($testStr === $testStr2);

