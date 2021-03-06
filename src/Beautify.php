<?php declare(strict_types=1);
/**
 * Created by PhpStorm.
 * User: Su
 * Date: 2019-12-20
 * Time: 9:24
 */

namespace Swoft\WebSocketBeautify;

use Swoft\Session\Session;

/**
 * Class Beautify
 * @package App\WebSocket\WebSocketBeautify
 */
class Beautify extends CacheOperation
{
    /**
     * 将fd与uid绑定
     * @param string $uid
     * @param int $fd
     * @return bool
     */
    public static function bindUid(string $uid, int $fd = 0): bool
    {
        $fd = $fd === 0 ? Session::mustGet()->getFd() : $fd;
        if (!self::isOnline($fd)) return false;

        // 获取因意外原因遗留被绑定的uid并解绑
        self::unbindFd($fd);

        // 获取uid之前已绑定的fd
        $pastFd = self::getFd($uid);
        $pastFd[] = $fd;
        self::setFd($uid, $pastFd);
        self::setUid((string)$fd, $uid);
        return true;
    }

    /**
     * 按uid解绑
     * @param string $uid
     * @param int $fd 指定解绑某个fd，如果默认则解绑全部
     */
    public static function unbindUid(string $uid, int $fd = 0): void
    {
        self::delFd($uid, $fd === 0 ? '' : (string)$fd);
        self::delUid((string)$fd);
    }

    /**
     * 按fd解绑
     * @param int $fd
     */
    public static function unbindFd(int $fd): void
    {
        $uid = self::getUid((string)$fd);
        self::unbindUid($uid, $fd);
    }

    /**
     * 判断fd是否在线
     * @param int $fd
     * @return bool
     */
    public static function isOnline(int $fd): bool
    {
        return server()->isEstablished($fd);
    }

    /**
     * 获取当前uid全部所在线连接数量
     * @param string $uid
     * @return int 在线数
     */
    public static function isUidOnline(string $uid): int
    {
        $sum = 0;
        foreach (self::getFd($uid) as $fd) {
            if (self::isOnline((int)$fd)) {
                $sum++;
            }
        }
        return $sum;
    }

    /**
     * 向uid绑定的所有在线fd发送数据
     * @param string $uid
     * @param string $data
     * @return int
     */
    public static function sendToUid(string $uid, string $data): int
    {
        $sum = 0;
        $receivers = [];
        foreach (self::getFd($uid) as $fd) {
            if (self::isOnline((int)$fd)) {
                $receivers[] = (int)$fd;
            }
        }
        if (!empty($receivers)) {
            $sum = server()->sendToSome($data, $receivers);
        }

        return $sum;
    }

    /**
     * 发送给某个fd客户端
     * @param int $fd
     * @param string $data
     * @return bool
     */
    public static function sendToFd(int $fd, string $data): bool
    {
        $is_success = false;
        if (self::isOnline($fd)) {
            $is_success = server()->push($fd, $data);
        }
        return $is_success;
    }
}