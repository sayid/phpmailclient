<?php
/**
 * Created by PhpStorm.
 * User: zhangyubo
 * Date: 2018/7/20
 * Time: 下午1:40
 */

if (! function_exists('microtimeFloat')) {
    function microtimeFloat()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }
}

if (! function_exists('runInSwoole')) {
    function runInSwoole()
    {
        return \Swoole\Coroutine::getuid() ? true : false;
    }
}