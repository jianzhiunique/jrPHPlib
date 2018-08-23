<?php
namespace XesLib\phpKafkaManager;

use \XesLib\phpKafkaManager\Params;

/**
 * Election's purpose is making preferred replica be leader.
 * electAll method is for all topics and partitions, sometimes it is slow and block;
 * electPart method is quicker, you should specify topic and partition like this :
 *      [['topic' => 'renjianzhitest','partition' => 2],[], ...]
 * election has no effect on users, because they can find the new leader and follow it.
 */
class Election extends Client
{
    const SH = 'kafka-preferred-replica-election.sh ';

    public function electAll()
    {
        //construct command
        $this->_setCommandParams(
            $this->shellCommand,
            Params::PARAM_ZOOKEEPER
        );
        //execute command
        var_dump($this->_execCommand($this->zkServer));
    }

    public function electPart($partitions)
    {
        //save json file
        $json = ['partitions' => $partitions];
        $fileName = Params::JSON_FILE_PREFIX_PREFERRED . time() . rand(1, 100) . '.json';
        file_put_contents($fileName, json_encode($json));
        
        $this->_setCommandParams(
            $this->shellCommand,
            Params::PARAM_ZOOKEEPER,
            Params::PARAM_PATH_TO_JSON_FILE
        );

        var_dump($this->_execCommand(
            $this->zkServer,
            $fileName
        ));
        unlink($fileName);
    }
}
