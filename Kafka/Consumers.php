<?php
namespace App\Services\Api\Mq\Lib;

class Consumers extends Client
{
    const SH = 'kafka-consumer-groups.sh ';

    /**
     * list all zookeeper comsumer group
     */
    public function listZkConsumer()
    {
        $this->_setCommandParams(
            $this->shellCommand,
            Params::PARAM_ZOOKEEPER,
            Params::PARAM_LIST
        );
        return $this->_execCommand($this->zkServer);
    }

    /**
     * list all kafka comsumer group
     */
    public function listKafkaConsumer()
    {
        $this->_setCommandParams(
            $this->shellCommand,
            // Params::PARAM_NEW_CONSUMER,
            Params::PARAM_BOOTSTRAP_SERVER,
            Params::PARAM_LIST
        );
        return $this->_execCommand($this->bootstrapServer);
    }

    /**
     * describe zookeeper comsumer group
     */
    public function describeZkConsumer($groupName)
    {
        $this->_setCommandParams(
            $this->shellCommand,
            Params::PARAM_ZOOKEEPER,
            Params::PARAM_DESCRIBE,
            Params::PARAM_GROUP
        );
        return $this->_execCommand($this->zkServer, $groupName);
    }

    /**
     * describe kafka comsumer group
     */
    public function describeKafkaConsumer($groupName)
    {
        $this->_setCommandParams(
            $this->shellCommand,
            // Params::PARAM_NEW_CONSUMER,
            Params::PARAM_BOOTSTRAP_SERVER,
            Params::PARAM_DESCRIBE,
            Params::PARAM_GROUP
        );
        return $this->_execCommand($this->bootstrapServer, $groupName);
    }

    /**
     * delete consumer group
     * you must close consumers before invoke this function
     * from kafka : Group deletion only works for old ZK-based consumer groups, and
     * one has to use it carefully to only delete groups that are not active.
     */
    public function deleteConsumerGroup($groupName)
    {
        $this->_setCommandParams(
            $this->shellCommand,
            Params::PARAM_ZOOKEEPER,
            Params::PARAM_DELETE,
            Params::PARAM_GROUP
        );
        var_dump($this->_execCommand($this->zkServer, $groupName));
    }
}
