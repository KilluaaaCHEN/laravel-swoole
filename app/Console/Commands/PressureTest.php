<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PressureTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:pressure-test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'websocket压力测试';


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $s = time();
        $r = new \Redis();
        $r->connect(env('REDIS_HOST'), env('REDIS_PORT'));
        $count = 1000;//过大会造成浏览器卡死
        for ($i = 0; $i < $count; $i++) {
            $data = ['cmd' => 'case_status', 'datas' => ['case_id' => rand(1, 10000), 'status' => 6, 'agent_id' => 'a10', 'provider' => 'p10']];
            $r->lPush('de_queue', json_encode($data));
        }
        $e = time() - $s;
        return '成功写入' . $count . '条测试数据,耗时' . $e . '秒';
    }
}
