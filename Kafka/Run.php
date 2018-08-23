<?php
namespace App\Services\Api\Mq\Lib;

class Run extends Client
{
    const SH = 'kafka-run-class.sh ';

    /**
     * get topic's partitions last offset
     */
    public function getTopicLastOffset($topic)
    {
        //construct command
        $this->_setCommandParams(
            $this->shellCommand,
            Params::PARAM_RUN_GET_OFFSET,
            Params::PARAM_RUN_GET_OFFSET_BROKER_LIST,
            Params::PARAM_TOPIC,
            Params::PARAM_RUN_GET_OFFSET_TIME
        );
        //execute command
        return $this->_execCommand($this->bootstrapServer, $topic, -1);
    }
}
