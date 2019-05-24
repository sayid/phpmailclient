<?php
/**
 * Created by PhpStorm.
 * User: zhangyubo
 * Date: 2018/7/20
 * Time: 下午1:40
 */


function microtimeFloat()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}