<?php
## socket类
#1 建立连接功能
#2 支持多终端进行连接
#3 支持多终端进行实时通信
#####################################
#
#  int socket_select ( array &$read , array &$write , array &$except , int $tv_sec [, int $tv_usec = 0 ] )
#  socket_select() accepts arrays of sockets and waits for them to change status.
#  Those coming with BSD sockets background will recognize that those socket resource arrays are in fact the so-called file descriptor sets.
#  Three independent arrays of socket resources are watched.
#
#####################################
#
#
#
# /*
//这个函数是同时接受多个连接的关键，我的理解它是为了阻塞程序继续往下执行。
//socket_select ($sockets, $write = NULL, $except = NULL, NULL);

/*$sockets可以理解为一个数组，这个数组中存放的是文件描述符。当它有变化（就是有新消息到或者有客户端连接/断开）时，socket_select函数才会返回，继续往下执行。
            $write是监听是否有客户端写数据，传入NULL是不关心是否有写变化。
            $except是$sockets里面要被排除的元素，传入NULL是”监听”全部。
            最后一个参数是超时时间
            如果为0：则立即结束
            如果为n>1: 则最多在n秒后结束，如遇某一个连接有新动态，则提前返回
            如果为null：如遇某一个连接有新动态，则返回
*/
#
#
#
#####################################
class socket{
    private $sockets=array();//socket连接池
    private $masterResource;//socket建立时返回的资源
    private $maxClient=1000;//最大连接客户机
    private $users;//已经连接的用户库



    public function __construct($address,$port){

        $this->masterResource=$this->createSocket($address,$port);//初始化一个socket资源

        $this->sockets=array($this->masterResource);//将此socket资源加入socket池

    }

    /*
     * 保持无限运行的函数(用于监听端口信息)
     *
     */
    public function run(){

        while(1){

            $changes=$this->sockets;
            $write=NULL;
            $except=NULL;

            socket_select($changes,$write,$except,null);//阻塞程序,有新消息才往下继续执行

            foreach ($changes as $sock){

                //新用户
                if($sock==$this->masterResource){

                    $client=socket_accept($this->masterResource);//生成与当前客户端通信的资源

                    $key=uniqid();//生成唯一key

                    $this->sockets[]=$client;//将此资源加入socket池

                    $this->users[$key]=array(

                        'socket' => $client,

                        'handshake' => false
                    );  //将当前用户加入用户池

                }else{

                    //非新用户

                    $len = 0;
                    $buffer = '';
                    /*
                     * 循环读出数据帧
                     */
                    do{
                        $l=socket_recv($sock,$buf,1024,0);//按照每次读取1024byte来循环读取数据

                        $len += $l;
                        $buffer.=$buf;

                    }while($l==1024);


                    $key = $this->findUserKey($sock); //寻找当前的用户的key值


                    //此客户端退出连接(当收到的数据帧的长度小于9的时候,则说明此时客户端已经与服务器端断开了连接)
                    if($len<9){

                        $this->exitSend($key,$sock); //在用户池和socket池清除当前用户的信息以及socket资源

                        continue;  //此时继续下一次循环

                    }

                    //判断当前用户是否进行握手操作
                    if(!$this->users[$key]['handshake']){

                        $this->handshake($key,$buffer);//进行握手操作

                    }else{

                        //给所有客户端推送消息
                        $msg=$this->decode($buffer);

                        if($msg==false){
                               continue;
                        }
                        $this->sendmsg($msg,$sock);
                    }

                }
            }
        }
    }

    private function sendmsg($str,$sock){
        $msgArr=array();
        $msg='';
        parse_str($str,$arr);

        if($arr['type']=='add'){

            $name=$arr['name'];

            $color=$arr['color'];

            $time=date('Y-m-d H:i:s');

            $msgArr['type']='add';

            $msgArr['msg'] = $name.' 加入了世界群聊~';

            $msgArr['time'] = $time;

            $msg=json_encode($msgArr);

            $key=$this->findUserKey($sock);

            $this->users[$key]['name']=$name;

            $this->users[$key]['color']=$color;

            $this->sendMagToALLUserRemoveSelf($sock,$msg);

        }elseif ($arr['type']=='remove'){

            $time=date('Y-m-d H:i:s');

            $msgArr['type']='remove';

            $msgArr['msg'] = $arr['msg'];

            $msgArr['time'] = $time;

            $msg=json_encode($msgArr);

            $this->sendMagToALLUserRemoveSelf($sock,$msg);

        }elseif ($arr['type']=='sendMsg'){

            $time=date('Y-m-d H:i:s');

            $msgArr['type']='rmsg';

            $msgArr['name']=$arr['name'];

            $msgArr['msg'] = $arr['msg'];

            $key=$this->findUserKey($sock);

            $msgArr['color'] = $this->users[$key]['color'];;
//            $msgArr['obj'] = $arr['obj'];
            $msgArr['time'] = $time;

            $msg=json_encode($msgArr);

            $this->sendMagToALLUserRemoveSelf($sock,$msg);

        }


    }

    private function exitSend($key,$sock){
            $str="type=remove&msg={$this->users[$key]['name']} 退出了世界群聊~";
            $this->sendmsg($str,$sock);
            $this->close($key);
    }

    private function sendMagToALLUserRemoveSelf($sock,$msg){
        $str=$this->code($msg);
        foreach ($this->users as $k => $v){
            if($v['socket']!=$sock) {
                socket_write($v['socket'], $str, strlen($str));
            }
        }
    }
    
    /*
     * 关闭用户建立的连接 释放资源
     */
    private function close($key){

        //释放掉已经关闭连接的资源
        socket_close($this->users[$key]['socket']);

        //释放用户池中的资源
        unset($this->users[$key]);

        /*将socket连接池中的资源进行更新 即从用户池中去服务替换*/
        $this->sockets=array($this->masterResource);

        foreach ($this->users as $v){
            $this->sockets[]=$v['socket'];
        }

        $this->E('key:  '.$key.'   closed');

    }

    /*
     * 广播  将一个用户的信息广播给所有用户
     */
    private function sendMsgToAllUser($msg){
        $str=$this->code($msg);
        foreach ($this->users as $k => $v){
            socket_write($v['socket'],$str,strlen($str));
        }
    }

    /*
     * 根据当前socket资源找到对应的用户
     * @$sock resource socket 资源
     */
    private function findUserKey($sock){

        foreach ($this->users as $k=>$v){
            if($sock==$v['socket']){
                return $k;
            }
        }

        return false;

    }
    /*
     * 创建socket资源
     * @$address string IP地址
     * @$port int 端口号
     */
    private function createSocket($address,$port){
        try{

            //创建socket

# resource socket_create ( int $domain , int $type , int $protocol )
            /*
             * 可用的地址/协议 Domain 描述
               AF_INET IPv4 网络协议。TCP 和 UDP 都可使用此协议。
               AF_INET6 IPv6 网络协议。TCP 和 UDP 都可使用此协议。
               AF_UNIX 本地通讯协议。具有高性能和低成本的 IPC（进程间通讯）。

              * SOCK_STREAM 提供一个顺序化的、可靠的、全双工的、基于连接的字节流。支持数据传送流量控制机制。TCP 协议即基于这种流式套接字。
                SOCK_DGRAM 提供数据报文的支持。(无连接，不可靠、固定最大长度).UDP协议即基于这种数据报文套接字。
                SOCK_SEQPACKET 提供一个顺序化的、可靠的、全双工的、面向连接的、固定最大长度的数据通信；数据端通过接收每一个数据段来读取整个数据包。
                SOCK_RAW 提供读取原始的网络协议。这种特殊的套接字可用于手工构建任意类型的协议。一般使用这个套接字来实现 ICMP 请求（例如 ping）。
                SOCK_RDM 提供一个可靠的数据层，但不保证到达顺序。一般的操作系统都未实现此功能。

              * protocol 参数，是设置指定 domain 套接字下的具体协议。这个值可以使用 getprotobyname() 函数进行读取。如果所需的协议是 TCP 或 UDP，可以直接使用常量 SOL_TCP 和 SOL_UDP 。

                常见协议 名称 描述
                icmp Internet Control Message Protocol 主要用于网关和主机报告错误的数据通信。例如"ping"命令（在目前大部分的操作系统中）就是使用 ICMP 协议实现的。
                udp User Datagram Protocol 是一个无连接的、不可靠的、具有固定最大长度的报文协议。由于这些特性，UDP 协议拥有最小的协议开销。
                tcp Transmission Control Protocol 是一个可靠的、基于连接的、面向数据流的全双工协议。TCP 能够保障所有的数据包是按照其发送顺序而接收的。如果任意数据包在通讯时丢失，TCP 将自动重发数据包直到目标主机应答已接收。因为可靠性和性能的原因，TCP 在数据传输层使用 8bit 字节边界。因此，TCP 应用程序必须允许传送部分报文的可能。
             */
            $socket=socket_create(AF_INET,SOCK_STREAM,SOL_TCP);

            socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);//1表示接受所有的数据
            //绑定socket到端口主机
            //创建的socket资源绑定到IP地址和端口号
            socket_bind($socket, $address,$port);

            //启动socket监听
            //3为最大连接数
            //等待客户端的连接
            socket_listen($socket, $this->maxClient);

        }catch (Exception $e){

            die('error:'.$e.socket_last_error());

        }

        $this->E('Socket Run '.date('Y-m-d H:i:s'));

        $this->E('address:'.$address.'  port:'.$port);

        return $socket;
    }

    /*
     * 握手操作(将指定的协议信息返回给客户端)
     * @$k string 用户的key值
     * @buffer string 请求头
     *
     * @return bool
     */
    private function handshake($k,$buffer){

        $buf  = substr($buffer,strpos($buffer,'Sec-WebSocket-Key:')+18);

        $key  = trim(substr($buf,0,strpos($buf,"\r\n")));

        $new_key = base64_encode(sha1($key."258EAFA5-E914-47DA-95CA-C5AB0DC85B11",true));

        //按照协议组合信息进行返回
        $new_message = "HTTP/1.1 101 Switching Protocols\r\n";
        $new_message .= "Upgrade: websocket\r\n";
        $new_message .= "Sec-WebSocket-Version: 13\r\n";
        $new_message .= "Connection: Upgrade\r\n";
        $new_message .= "Sec-WebSocket-Accept: " . $new_key . "\r\n\r\n";

        socket_write($this->users[$k]['socket'],$new_message,strlen($new_message));//与浏览器进行第二次握手

        $this->users[$k]['handshake']=true;

        return true;
    }

    /*
     * 数据解密
     * $buffer string 数据帧
     */
    private function decode($buffer)  {
        $len = $masks = $data = $decoded = null;
        $len = ord($buffer[1]) & 127;

        if ($len === 126)  {
            $masks = substr($buffer, 4, 4);
            $data = substr($buffer, 8);
        } else if ($len === 127)  {
            $masks = substr($buffer, 10, 4);
            $data = substr($buffer, 14);
        } else  {
            $masks = substr($buffer, 2, 4);
            $data = substr($buffer, 6);
        }
        for ($index = 0; $index < strlen($data); $index++) {
            $decoded .= $data[$index] ^ $masks[$index % 4];
        }
        return $decoded;
    }

    /*
     * 加密
     * @$msg string 需要封装成数据帧的消息
     *
     * @return 返回数据帧
     */
    //加密成数据帧
    private function code($msg){
        $frame = array();
        $frame[0] = '81';
        $len = strlen($msg);
        if($len < 126){
            $frame[1] = $len<16?'0'.dechex($len):dechex($len);
        }else if($len < 65025){
            $s=dechex($len);
            $frame[1]='7e'.str_repeat('0',4-strlen($s)).$s;
        }else{
            $s=dechex($len);
            $frame[1]='7f'.str_repeat('0',16-strlen($s)).$s;
        }
        $frame[2] = $this->ord_hex($msg);
        $data = implode('',$frame);
        return pack("H*", $data);
    }

    private function ord_hex($data)  {
        $msg = '';
        $l = strlen($data);
        for ($i= 0; $i<$l; $i++) {
            $msg .= dechex(ord($data{$i}));
        }
        return $msg;
    }

    /*
     * 记录日志
     */
    private function E($msg){
        $msg=$msg."\n";
        $path=dirname(__FILE__).'/log.txt';
        file_put_contents($path,$msg,FILE_APPEND);
        echo iconv('utf-8','gbk',$msg."\r\n");
    }
}