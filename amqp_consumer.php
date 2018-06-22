<?php
    //AMQP_MANDATORY When publishing a message, the message must be routed to a valid queue. If it is not, an error will be returned.
    $conn = new AMQPConnection([
        'host' => '',
        'port' => ,
        'vhost' => '',
        'login' => '',
        'password' => '',
        'read_timeout' => 60,
        'write_timeout' => 60,
        'connect_timeout' => 60
    ]);

    $conn->connect();
    $channel = new AMQPChannel($conn);
    while (true) {
        try {
            if (false == $conn->isConnected()) {
                sleep(2);
                $conn->reconnect();
            }
        } catch (Exception $e) {
            var_dump('connect ', $e);
            continue;
        }

        try {
            if (false == $channel->isConnected()) {
                $channel = new AMQPChannel($conn);
            }
        } catch (Exception $e) {
            var_dump('channel ', $e);
            continue;
        }

        $exchangeName = 'test_exchange';
        $queueName = 'test_queue';
        $routeKey = 'test_route';
        /*$exchange = new AMQPExchange($channel);
        $exchange->setName($exchangeName);
        $exchange->setType(AMQP_EX_TYPE_DIRECT);
        $exchange->setFlags(AMQP_DURABLE);
        $exchange->declareExchange();*/

        $queue = new AMQPQueue($channel);
        $queue->setName($queueName);
        //$queue->setFlags(AMQP_DURABLE);
        //$queue->declareQueue();

        //$queue->bind($exchangeName, $routeKey);
        
        //consume callback
        $callback = function ($msg, $queue) {
            $msgbody = $msg->getbody();
            echo $msgbody;
            var_dump($msg->isRedelivery());
            $queue->ack($msg->getDeliveryTag());
        };
        
        while (true) {
            try {
                $queue->consume($callback);
            } catch (Exception $e) {
                if ($e instanceof AMQPQueueException && $e->getMessage() === 'Consumer timeout exceed') {
                    //如果连接和信道都是正常的，consume阻塞的超时处理，取消当前消费者，重新消费
                    //以便在服务器宕机重启后，不会出现假死现象
                    $queue->cancel();
                } elseif ($e instanceof AMQPQueueException && false !== strpos($e->getMessage(), 'NOT_FOUND')) {
                    //如果持久队列没有启用镜像，那么当队列所在的节点宕机，消费中的消费者可能会遇到NOT_FOUND错误
                    //这时连接和信道是没有问题的，只能等待节点恢复
                    //这里等待之后进入第二次内部循环，异常为Could not get channel. No channel available，但信道没有问题，所以外层不会sleep，需要在这里sleep
                    sleep(2);
                } else {
                    //连接错误和信道错误都只是进行外层重连逻辑，服务器宕机时在外层进行sleep，等待恢复
                    break;
                }
            }
        }
    }
