# SwoftWebSocketBeautify
## 目录

1. [概述](#1-概述)  
2. [环境需求](#2-环境需求)  
3. [安装](#3-安装)  
4. [使用](#4-使用)  
4.1. [将fd与uid绑定](#41-将fd与uid绑定)  
4.2. [按uid解绑](#42-按uid解绑)  
4.3. [按fd解绑](#43-按fd解绑)  
4.4. [判断fd是否在线](#44-判断fd是否在线)  
4.5. [判断uid是否在线](#45-判断uid是否在线)  
4.6. [向uid绑定的所有在线fd发送数据](#46-向uid绑定的所有在线fd发送数据)  
4.7. [发送给某个fd客户端](#47-发送给某个fd客户端)  
4.8. [按uid获取fd](#48-按uid获取fd)  
4.9. [获取全局所有uid-fd列表](#49-获取全局所有uid-fd列表)  
4.10. [按fd获取uid](#410-按fd获取uid)  
4.11. [获取全局所有fd-uid列表](#411-获取全局所有fd-uid列表)

## 1. 概述
- swoft/websocket-server 的补充与封装，因业务需要封装了一下，后来觉得可以抽离并开源出来为swoft生态贡献我卑微的一点力量:grimacing:（极其简单，大佬勿喷）  
（在此感谢swoft框架作者[@stelin](https://github.com/stelin "@stelin")提供如此优秀的框架，也感谢workerman框架作者[@walkor](https://github.com/walkor "@walkor")提供的思路）  
- 使用Redis hash实现的进程间共享内存
（为什么不用swoole table？因为无法动态扩容，与需求相驳）
- 现目前为开发测试版本，正待上线测试，但就目前测试结果来说并无问题
- 如有更好解决方案，烦请大佬指点

## 2. 环境需求
###### &emsp;2.1. 必须swoft
###### &emsp;2.2. swoft必须>2.0
###### &emsp;2.3. 最不可少swoft/websocket-server

## 3. 安装
>composer require sukerd/swoft-websocket-beautify:dev-master

## 4. 使用

#### &emsp;说明
> **下文不再做解释**

&emsp;&emsp;**$uid** 这里uid泛指用户id或者设备id，用来唯一确定一个客户端用户或者设备

&emsp;&emsp;**$fd** 是与客户端的连接 ID，它表明了不同的客户端

&emsp;&emsp;如果想实现分组啥的还请自行实现，因为我目前的业务暂时用不到（其实也挺简单:grimacing:），后续有需求了再说吧 :joy:

------------

##### &emsp;4.1. 将fd与uid绑定

&emsp;&emsp;&emsp;&emsp;`public static function bindUid(string $uid, int $fd = 0): bool`

###### &emsp;&emsp;参数说明：

&emsp;&emsp;&emsp;&emsp;**$uid** string 需要绑定的uid

&emsp;&emsp;&emsp;&emsp;**$fd** int 需要绑定的fd *（非必须，默认为本次连接fd）*

###### &emsp;&emsp;方法说明：
&emsp;&emsp;&emsp;&emsp;1. uid与fd是一对多的关系，允许一个uid下有多个fd。  
&emsp;&emsp;&emsp;&emsp;2. 一个fd只能绑定一个uid，如果绑定多次uid，则只有最后一次绑定有效。  
&emsp;&emsp;&emsp;&emsp;3. 如果业务需要一对一的关系，Beautify::getFd($uid)获得某uid已经绑定的所有fd，然后调用 Session::mustGet()->getServer()->disconnect($fd)踢掉之前的fd  
&emsp;&emsp;&emsp;&emsp;4. **因为使用Redis hash实现的进程间共享内存，如果服务端产生异常（如：stop、restart、等等）会导致之前产生的绑定数据依然留存于缓存中，但是不用担心程序重启后数据冲突的问题，一旦fd重新上线并进行绑定，Beautify会自己处理因意外原因遗留被绑定的uid、fd并解绑**

###### &emsp;&emsp;示例：

&emsp;&emsp;&emsp;&emsp;`Beautify::bindUid('10000');`

------------

##### &emsp;4.2. 按uid解绑

&emsp;&emsp;&emsp;&emsp;`public static function unbindUid(string $uid, int $fd = 0): void`

###### &emsp;&emsp;参数说明：

&emsp;&emsp;&emsp;&emsp;**$uid** string

&emsp;&emsp;&emsp;&emsp;**$fd** int 非必须，指定解绑某个fd，如果默认则解绑全部

###### &emsp;&emsp;示例：

&emsp;&emsp;&emsp;&emsp;`Beautify::unbindUid('10000', 1);`

------------

##### &emsp;4.3. 按fd解绑

&emsp;&emsp;&emsp;&emsp;`public static function unbindUid(string $uid, int $fd): void`

###### &emsp;&emsp;参数说明：

&emsp;&emsp;&emsp;&emsp;**$fd** int

###### &emsp;&emsp;方法说明：
&emsp;&emsp;&emsp;&emsp; **fd下线（连接断开）时不会自动执行解绑，开发者必需调用Beautify::unbindFd($fd)解绑。**

###### &emsp;&emsp;示例：

&emsp;&emsp;&emsp;&emsp;`Beautify::unbindFd(1);`

------------


##### &emsp;4.4. 判断fd是否在线

&emsp;&emsp;&emsp;&emsp;` public static function isOnline(int $fd): bool`

###### &emsp;&emsp;参数说明：

&emsp;&emsp;&emsp;&emsp;**$fd** int

###### &emsp;&emsp;方法说明：
&emsp;&emsp;&emsp;&emsp; 1.检查连接是否为有效的WebSocket客户端连接  
&emsp;&emsp;&emsp;&emsp; 2.如果是客户端断网断电等极端情况掉线，服务端就无法得知连接已经断开，需自己实现心跳检测*[（如果不知道请自行面向百度编程）](https://www.baidu.com/s?wd=websoket%E5%BF%83%E8%B7%B3%E6%A3%80%E6%B5%8B "（如果不知道请自行面向百度编程）")*

###### &emsp;&emsp;示例：

&emsp;&emsp;&emsp;&emsp;`Beautify::isOnline(1);`

------------


##### &emsp;4.5. 判断uid是否在线

&emsp;&emsp;&emsp;&emsp; `public static function isUidOnline(string $uid): int`

###### &emsp;&emsp;参数说明：

&emsp;&emsp;&emsp;&emsp;**$uid** string

###### &emsp;&emsp;方法说明：
&emsp;&emsp;&emsp;&emsp; 1.检查uid被绑定的fd连接是否为有效的WebSocket客户端连接并返回**有效连接数**  
&emsp;&emsp;&emsp;&emsp; 2.如果是客户端断网断电等极端情况掉线，服务端就无法得知连接已经断开，需自己实现心跳检测*[（如果不知道请自行面向百度编程）](https://www.baidu.com/s?wd=websoket%E5%BF%83%E8%B7%B3%E6%A3%80%E6%B5%8B "（如果不知道请自行面向百度编程）")*

###### &emsp;&emsp;示例：

&emsp;&emsp;&emsp;&emsp;`Beautify::isUidOnline('10000');`

------------


##### &emsp;4.6. 向uid绑定的所有在线fd发送数据

&emsp;&emsp;&emsp;&emsp; `public static function sendToUid(string $uid, string $data): int`

###### &emsp;&emsp;参数说明：

&emsp;&emsp;&emsp;&emsp;**$uid** string  
&emsp;&emsp;&emsp;&emsp;**$data** string

###### &emsp;&emsp;方法说明：
&emsp;&emsp;&emsp;&emsp; 默认uid与fd是一对多的关系，如果当前uid下绑定了多个fd，则多个fd对应的客户端都会收到消息

###### &emsp;&emsp;示例：

&emsp;&emsp;&emsp;&emsp;`Beautify::sendToUid('10000', 'hi, swoft!');`

------------


##### &emsp;4.7. 发送给某个fd客户端

&emsp;&emsp;&emsp;&emsp; `public static function sendToFd(int $fd, string $data): bool`

###### &emsp;&emsp;参数说明：

&emsp;&emsp;&emsp;&emsp;**$fd** int  
&emsp;&emsp;&emsp;&emsp;**$data** string

###### &emsp;&emsp;示例：

&emsp;&emsp;&emsp;&emsp;`Beautify::sendToFd(1, 'hi, swoft!');`

------------


##### &emsp;4.8. 按uid获取fd

&emsp;&emsp;&emsp;&emsp; ` public static function getFd(string $uid): array`

###### &emsp;&emsp;参数说明：

&emsp;&emsp;&emsp;&emsp;**$uid** string

###### &emsp;&emsp;方法说明：
&emsp;&emsp;&emsp;&emsp; 按uid获取与之绑定的fd

###### &emsp;&emsp;示例：

&emsp;&emsp;&emsp;&emsp;` vdump(Beautify::getFd('10000')); // [1,2,3]`

------------


##### &emsp;4.9. 获取全局所有uid-fd列表

&emsp;&emsp;&emsp;&emsp; `public static function getAllUidFd(): array`

###### &emsp;&emsp;方法说明：
&emsp;&emsp;&emsp;&emsp; 获取全局所有uid-fd列表

###### &emsp;&emsp;示例：

&emsp;&emsp;&emsp;&emsp;` vdump(Beautify::getAllUidFd()); // ['10000'=>'1,2,3' ,'10001'=>'4']`

------------


##### &emsp;4.10. 按fd获取uid

&emsp;&emsp;&emsp;&emsp; `public static function getUid(string $fd): string`

###### &emsp;&emsp;参数说明：

&emsp;&emsp;&emsp;&emsp;**$fd** string

###### &emsp;&emsp;方法说明：
&emsp;&emsp;&emsp;&emsp; 按fd获取与之绑定的uid

###### &emsp;&emsp;示例：

&emsp;&emsp;&emsp;&emsp;` vdump(Beautify::getFd('10000')); // [1,2,3]`

------------


##### &emsp;4.11. 获取全局所有fd-uid列表

&emsp;&emsp;&emsp;&emsp; `public static function getAllFdUid(): array`

###### &emsp;&emsp;方法说明：
&emsp;&emsp;&emsp;&emsp; 获取全局所有fd-uid列表

###### &emsp;&emsp;示例：

&emsp;&emsp;&emsp;&emsp;` vdump(Beautify::getAllFdUid()); // [1 => '10000', 2 => '10000', 3 => '10000', 4 => '10001']`
