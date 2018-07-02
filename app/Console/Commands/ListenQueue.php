<?php

namespace App\Console\Commands;

use App\Helper\HRedis;
use App\Socket;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Console\Command;

class ListenQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:listen-queue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '启动一个服务监听队列';


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        try {
            $r = HRedis::getRedis();
            $client = new Socket(env('SWOOLE_HOST'), env('SWOOLE_PORT'));
            $client->connect();
            print 'Connected...' . PHP_EOL;
            while (true) {
                $val = $r->brPop(['de_queue'], 0);
                $client->send($val[1]);
            }
            $client->close();
        } catch (Exception $e) {
            print($e->getMessage() . PHP_EOL);
            \Log::error($e->getMessage(), $e->getTrace());
            sleep(10);
            $this->handle();
        }
    }
}
