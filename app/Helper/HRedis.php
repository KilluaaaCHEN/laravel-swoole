<?php

namespace App\Helper;
/**
 * Created by PhpStorm.
 * User: Killua Chen
 * Date: 18/7/2
 * Time: 10:15
 */
class HRedis
{
    /**
     * 获取Redis实例
     * @author Killua Chen
     * @return \Redis
     */
    public static function getRedis()
    {
        $r = new \Redis();
        $r->connect(env('REDIS_HOST'), env('REDIS_PORT'));
        $r->auth(env('REDIS_PASSWORD'));
        return $r;
    }
}