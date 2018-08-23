<?php
namespace App\Services\Api\Mq\Lib;

class Topics extends Client
{
    const SH = 'kafka-topics.sh ';
    /**
     * list all topic names
     */
    public function topics()
    {
        $this->_setCommandParams(
            $this->shellCommand,
            Params::PARAM_ZOOKEEPER,
            Params::PARAM_LIST
        );
        var_dump($this->_execCommand($this->zkServer));
    }

    /**
     * describe all topics
     */
    public function describeTopics()
    {
        $this->_setCommandParams(
            $this->shellCommand,
            Params::PARAM_ZOOKEEPER,
            Params::PARAM_DESCRIBE
        );
        var_dump($this->_execCommand($this->zkServer));
    }

    /**
     * describe specfic topic
     */
    public function describeTopic($topicName)
    {
        $this->_setCommandParams(
            $this->shellCommand,
            Params::PARAM_ZOOKEEPER,
            Params::PARAM_DESCRIBE,
            Params::PARAM_TOPIC
        );
        return $this->_execCommand($this->zkServer, $topicName);
    }

    /**
     * delete specific topic
     */
    public function deleteTopic($topicName)
    {
        $this->_setCommandParams(
            $this->shellCommand,
            Params::PARAM_ZOOKEEPER,
            Params::PARAM_DELETE,
            Params::PARAM_TOPIC
        );
        return $this->_execCommand($this->zkServer, $topicName);
    }

    /**
     * create a simple topic
     */
    public function createSimpleTopic($topicName, $partitions, $factor)
    {
        $this->_setCommandParams(
            $this->shellCommand,
            Params::PARAM_ZOOKEEPER,
            Params::PARAM_CREATE,
            Params::PARAM_TOPIC,
            Params::PARAM_PARTITIONS,
            Params::PARAM_REPLICATION_FACTOR
        );
        return $this->_execCommand($this->zkServer, $topicName, $partitions, $factor);
    }

    /**
     * create topic with config
     */
    public function createTopicWithConfig($topicName, $partitions, $factor, $config)
    {
        $this->_setCommandParams(
            $this->shellCommand,
            Params::PARAM_ZOOKEEPER,
            Params::PARAM_CREATE,
            Params::PARAM_TOPIC,
            Params::PARAM_PARTITIONS,
            Params::PARAM_REPLICATION_FACTOR,
            Params::PARAM_TOPICS_CONFIG
        );
        var_dump($this->_execCommand($this->zkServer, $topicName, $partitions, $factor, $config));
    }

    /**
     * only add partitions
     * can not delete partitions, you can and must re-create topic to achieve this
     */
    public function addTopicPartition($topicName, $partitions)
    {
        $this->_setCommandParams(
            $this->shellCommand,
            Params::PARAM_ZOOKEEPER,
            Params::PARAM_ALTER,
            Params::PARAM_TOPIC,
            Params::PARAM_PARTITIONS
        );

        return $this->_execCommand($this->zkServer, $topicName, $partitions);
    }
}
