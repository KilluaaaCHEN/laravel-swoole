<?php

namespace App\Jobs;

use App\Socket;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class Test implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $name;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        date_default_timezone_set('Asia/Shanghai');
//        $cli = new \swoole_http_client('127.0.0.1', 9501);
//        $cli->on('message', function ($_cli, $frame) {
//        });
//        $cli->upgrade('/', function ($cli) {
//            $cli->push(date('Y-m-d H:i:s'));
//        });
//        $cli->close();
        $client = new Socket('127.0.0.1', 9501);
        $data = $client->connect();
        $client->send($this->name);
        $client->close();
        $recvData = "";
        //while(1) {
//        $tmp = $client->recv();
//        if (empty($tmp)) {
//            return;
//        }
//        $recvData .= $tmp;
        //}
//        echo $recvData . "size:" . strlen($recvData) . PHP_EOL;
        echo "===================================" . PHP_EOL;

        Log:: info(date('Y-m-d H:i:s'));


    }
}
