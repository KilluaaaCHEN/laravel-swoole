<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SwooleServer extends Command
{
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'command:swoole-socket-server';

    /**
     * The console command description.
     * @var string
     */
    protected $description = '启动一个Swoole WebSocket服务';

    /** @var \Redis */
    protected $redis = null;
    /** @var \swoole_websocket_server */
    protected $server = null;

    protected $key_login = 'login:';
    protected $key_fd = 'login:sys_fd:';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->redis = new \Redis();
        $this->redis->connect(env('REDIS_HOST'), env('REDIS_PORT'));
        $keys = $this->redis->keys($this->key_login . '*');
        $this->redis->del($keys);
        $this->server = new \swoole_websocket_server("0.0.0.0", env('SWOOLE_PORT'));
        $this->server->set(array(
            'task_worker_num' => 1,//task进程数量
            'dispatch_mode' => 2,
            'debug_mode' => 1,
            'daemonize' => false,//是否守护进程
            'log_file' => storage_path('logs/webs_swoole.log'),
        ));

        $this->server->on('finish', function ($ser, $task_id, $data) {
        });

        /**
         * 客户端打开连接
         */
        $this->server->on('open', function ($ser, $request) {
        });

        /**
         * 服务端收到消息
         */
        $this->server->on('message', function ($ser, $frame) {
            $this->doWork($frame);
        });

        /**
         * 客户端关闭连接
         */
        $this->server->on('close', function ($ser, $fd) {
            $fd_key = $this->_getSysFdKey($fd);
            $uid = $this->redis->get($fd_key);
            $this->redis->del($fd_key);
            $this->redis->lRem($this->_getLoginKey($uid), $fd, 0);
            $this->redis->lRem($this->_getLoginIdentityKeys($uid), $fd, 0);
        });

        /**
         * 异步发送消息
         */
        $this->server->on('task', function (\swoole_websocket_server $server, $task_id, $from_id, $data) {
            if (is_array($data['msg'])) {
                $data['msg'] = json_encode($data['msg']);
            }
            if (isset($data['fd'])) {
                if (!is_array($data['fd'])) {
                    $data['fd'] = [$data['fd']];
                }
                foreach ($data['fd'] as $fd) {
                    $server->push($fd, $data['msg']);
                }
            } else if (isset($data['uid'])) {
                if (!is_array($data['uid'])) {
                    $data['uid'] = [$data['uid']];
                }
                foreach ($data['uid'] as $uid) {
                    $list = $this->_getUserFdByUid($uid);
                    foreach ($list as $fd) {
                        $server->push($fd, $data['msg']);
                    }
                }

            }
        });

        $this->server->start();//启动服务
    }

    /**
     * 获取登录用户身份
     * @param $uid
     * @author Killua Chen
     * @return null|string
     */
    private function _getIdentity($uid)
    {
        $identity = substr($uid, 0, 1);
        switch ($identity) {
            case 'a':
                return 'agent';
            case 'p':
                return 'provider';
            case 'd':
                return 'driver';
        }
    }

    /**
     * 获取登录身份登陆键
     * @param $uid
     * @author Killua Chen
     * @return string
     */
    private function _getLoginIdentityKeys($uid)
    {
        return $this->key_login . $this->_getIdentity($uid) . '_list';
    }

    /**
     * 获取登录用户redis键
     * @param $uid
     * @author Killua Chen
     * @return string
     */
    private function _getLoginKey($uid)
    {
        return $this->key_login . $this->_getIdentity($uid) . ':' . $uid;
    }

    /**
     * 获取系统fd键
     * @param $fd
     * @author Killua Chen
     * @return string
     */
    private function _getSysFdKey($fd)
    {
        return $this->key_fd . $fd;
    }

    /**
     * 获取某用户所有fd列表,用于发送消息
     * @param $uid
     * @author Killua Chen
     * @return array
     */
    private function _getUserFdByUid($uid)
    {
        return $this->_getUserFdByLoginKey($this->_getLoginKey($uid));
    }

    /**
     * 根据用户登陆key,获取用户所有fd列表
     * @param $key
     * @author Killua Chen
     * @return array
     */
    private function _getUserFdByLoginKey($key)
    {
        return $this->redis->lRange($key, 0, -1);
    }

    /**
     * 根据身份获取所有用户fd列表
     * @param $identity
     * @author Killua Chen
     * @return array
     */
    private function _getAllUserFdByIdentity($identity)
    {
        return $this->redis->lRange($this->_getLoginIdentityKeys($identity), 0, -1);
    }

    /**
     * 处理业务逻辑
     * @param $frame
     * @author Killua Chen
     */
    public function doWork($frame)
    {
        $data = json_decode($frame->data, true);
        if (empty($data['cmd']) || empty($data['datas'])) {
            return;
        }
        $cmd = $data['cmd'];
        $data = $data['datas'];
        switch ($cmd) {
            case 'login':
                $this->redis->set($this->_getSysFdKey($frame->fd), $data['uid']);
                $this->redis->lPush($this->_getLoginKey($data['uid']), $frame->fd);
                $this->redis->lPush($this->_getLoginIdentityKeys($data['uid']), $frame->fd);
                break;
            case 'case_status':
                $this->doCaseStatus($frame, $data);
                break;
        }
    }


    /**
     * 处理案件状态
     * @param $frame
     * @param $data
     * @author Killua Chen
     */
    public function doCaseStatus($frame, $data)
    {
        $request = json_decode($frame->data, true);
        $request = $request['datas'];
        $msg = ['case_id' => $request['case_id'], 'status' => $request['status'], 'msg' => 'message from swoole...'];
        switch ($data['status']) {
            case '1':
                //给某一个agent发消息
                $this->server->task(['uid' => $data['agent_id'], 'msg' => $msg]);
                break;
            case '2':
                //给某一个provider发消息
                $this->server->task(['uid' => $data['provider_id'], 'msg' => $msg]);
                break;
            case '3':
                //给某一个agent和provider发消息,如果发送给多个人,但每个人数据不一样,请多次调用task
                $this->server->task([
                    'uid' => [$data['agent_id'], $data['provider_id']],
                    'msg' => $msg
                ]);
                break;
            case '4':
                //给所有agent发消息
                $agent_list = $this->_getAllUserFdByIdentity($this->_getIdentity('a'));
                $this->server->task(['fd' => $agent_list, 'msg' => $msg]);
                break;
            case '5':
                //给所有provider发消息
                $provider_list = $this->_getAllUserFdByIdentity($this->_getIdentity('p'));
                $this->server->task(['fd' => $provider_list, 'msg' => $msg]);
                break;
            case '6':
                //给所有人发消息
                //此处代码必须循环才能拿到fd,直接拿connections是一个swoole_connection_iterator类型
                $fd_list = [];
                foreach ($this->server->connections as $fd) {
                    array_push($fd_list, $fd);
                }
//                var_dump($fd_list, $this->server->connections);
                $this->server->task(['fd' => $fd_list, 'msg' => $msg]);
                break;
        }
    }
}
