<?php declare(strict_types=1);
/**
 * Created by PhpStorm.
 * User: Su
 * Date: 2019-12-20
 * Time: 14:37
 */

namespace Swoft\WebSocketBeautify;

use Swoft\Redis\Redis;

/**
 * Class CacheOperation
 * @package App\WebSocket\WebSocketBeautify
 */
class CacheOperation
{
    /**
     * @var string
     */
    protected static $kenName = 'WebSocketBind';

    /**
     * 按uid获取fd
     * @param $uid
     * @return mixed
     */
    public static function getFd(string $uid): array
    {
        $fd = self::get('Uid-Fd', $uid);
        return $fd ? explode(',', $fd) : [];
    }

    /**
     * 获取全局所有uid-fd列表
     * @return array
     */
    public static function getAllUidFd(): array
    {
        return Redis::hGetAll(self::$kenName . ':' . 'Uid-Fd');
    }

    /**
     * 按fd获取uid
     * @param $fd
     * @return mixed
     */
    public static function getUid(string $fd): string
    {
        return (string)self::get('Fd-Uid', $fd);
    }

    /**
     * 获取全局所有fd-uid列表
     * @return array
     */
    public static function getAllFdUid(): array
    {
        return Redis::hGetAll(self::$kenName . ':' . 'Fd-Uid');
    }

    /**
     * 绑定uid by fd
     * @param string $uid
     * @param array $fd
     */
    protected static function setFd(string $uid, array $fd): void
    {
        self::set('Uid-Fd', $uid, implode(',', $fd));
    }

    /**
     * 绑定fd by uid
     * @param string $fd
     * @param string $uid
     */
    protected static function setUid(string $fd, string $uid): void
    {
        self::set('Fd-Uid', $fd, $uid);
    }

    /**
     * 按uid删除fd
     * @param string $uid
     * @param string $fd
     */
    protected static function delFd(string $uid, string $fd): void
    {
        $pastFd = self::getFd($uid);
        if (empty($pastFd)) return;
        $legacyFd = array_filter($pastFd, function ($v) use ($fd) {
            return $v != $fd;
        });

        if (empty($legacyFd)) {
            self::del('Uid-Fd', $uid);
        } else {
            self::setFd($uid, $legacyFd);
        }
    }

    /**
     * 按fd删除uid
     * @param string $fd
     */
    protected static function delUid(string $fd): void
    {
        self::del('Fd-Uid', $fd);
    }

    /**
     * @param string $to
     * @param string|int $key
     * @return mixed
     */
    private static function get(string $to, string $key)
    {
        return Redis::hGet(self::$kenName . ':' . $to, $key);
    }

    /**
     * @param string $to
     * @param string|int $key
     * @param string $value
     * @return int
     */
    private static function set(string $to, string $key, string $value)
    {
        return Redis::hSet(self::$kenName . ':' . $to, $key, $value);
    }

    /**
     * @param string $to
     * @param string|int $key
     * @return mixed
     */
    private static function del(string $to, string $key)
    {
        return Redis::hDel(self::$kenName . ':' . $to, $key);
    }
}