<?php
namespace XesLib\phpKafkaManager;

use \XesLib\phpKafkaManager\Params;

/**
 * This class moves topic partitions between replicas.
 * Adding replicas factor is not obvious but it can be done.
 */
class Reassign extends Client
{
    const SH = 'kafka-reassign-partitions.sh ';

    public function generate($topicName, $brokerList)
    {
        //generate json file then save to file
        $json = [];
        $json['topics'] = [['topic' => $topicName]];
        $json['version'] = 1;
        $fileName = Params::JSON_FILE_PREFIX_TOPICS_TO_MOVE . time() . rand(1, 100) . '.json';
        file_put_contents($fileName, json_encode($json));
        //construct command
        $this->_setCommandParams(
            $this->shellCommand,
            Params::PARAM_ZOOKEEPER,
            Params::PARAM_BROKER_LIST,
            Params::PARAM_TOPICS_TO_MOVE_JSON_FILE,
            Params::PARAM_GENERATE
        );
        //execute command then save plan
        var_dump($this->_execCommand(
            $this->zkServer,
            $brokerList,
            $fileName
        ));
        unlink($fileName);
    }

    public function execute($json)
    {
        //save json file
        $fileName = Params::JSON_FILE_PREFIX_REASSIGNMENT . time() . rand(1, 100) . '.json';
        file_put_contents($fileName, $json);
        
        $this->_setCommandParams(
            $this->shellCommand,
            Params::PARAM_ZOOKEEPER,
            Params::PARAM_REASSIGNMENT_JSON_FILE,
            Params::PARAM_EXECUTE
        );

        var_dump($this->_execCommand(
            $this->zkServer,
            $fileName
        ));
        unlink($fileName);
    }

    public function verify($json)
    {
        //save json file
        $fileName = Params::JSON_FILE_PREFIX_REASSIGNMENT . time() . rand(1, 100) . '.json';
        file_put_contents($fileName, $json);
        
        $this->_setCommandParams(
            $this->shellCommand,
            Params::PARAM_ZOOKEEPER,
            Params::PARAM_REASSIGNMENT_JSON_FILE,
            Params::PARAM_VERIFY
        );

        var_dump($this->_execCommand(
            $this->zkServer,
            $fileName
        ));
        unlink($fileName);
    }
}
