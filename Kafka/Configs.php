<?php
namespace App\Services\Api\Mq\Lib;

/**
 * common config class
 * for params configuration or quota configuration etc
 */
class Configs extends Client
{
    const SH = 'kafka-configs.sh ';

    /**
     * list all override configs of a topic
     * can not access brokers default configs
     */
    public function list($type, $entity)
    {
        $this->_setCommandParams(
            $this->shellCommand,
            ($type === Params::CONST_ENTITY_TYPE_BROKERS ? Params::PARAM_BOOTSTRAP_SERVER : Params::PARAM_ZOOKEEPER),
            Params::PARAM_DESCRIBE,
            Params::PARAM_ENTITY_TYPE,
            Params::PARAM_ENTITY_NAME
        );
        return $this->_execCommand(
            ($type === Params::CONST_ENTITY_TYPE_BROKERS ? $this->bootstrapServer : $this->zkServer),
            $type,
            $entity
        );
    }

    /**
     * alter topic configs
     */
    public function alter($type, $topicName, $configs)
    {
        $configsString = rtrim(array_reduce(
            array_keys($configs),
            function ($carry, $key) use ($configs) {
                return $carry . "$key={$configs[$key]},";
            }
        ), ',');
        
        $this->_setCommandParams(
            $this->shellCommand,
            ($type === Params::CONST_ENTITY_TYPE_BROKERS ? Params::PARAM_BOOTSTRAP_SERVER : Params::PARAM_ZOOKEEPER),
            Params::PARAM_ALTER,
            Params::PARAM_ENTITY_TYPE,
            Params::PARAM_ENTITY_NAME,
            Params::PARAM_ADD_CONFIG
        );
            
        return $this->_execCommand(
            ($type === Params::CONST_ENTITY_TYPE_BROKERS ? $this->bootstrapServer : $this->zkServer),
            $type,
            $topicName,
            $configsString
        );
    }

    /**
     * delete topic configs
     */
    public function delete($type, $topicName, $configs)
    {
        $this->_setCommandParams(
            $this->shellCommand,
            ($type === Params::CONST_ENTITY_TYPE_BROKERS ? Params::PARAM_BOOTSTRAP_SERVER : Params::PARAM_ZOOKEEPER),
            Params::PARAM_ALTER,
            Params::PARAM_ENTITY_TYPE,
            Params::PARAM_ENTITY_NAME,
            Params::PARAM_DELETE_CONFIG
        );
    
        return $this->_execCommand(
            ($type === Params::CONST_ENTITY_TYPE_BROKERS ? $this->bootstrapServer : $this->zkServer),
            $type,
            $topicName,
            implode(',', $configs)
        );
    }
}
