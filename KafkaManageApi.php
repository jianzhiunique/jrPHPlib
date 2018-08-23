<?php
namespace App\Services\Api\Mq;

use App\Services\Api\Mq\Lib\Topics;
use App\Services\Api\Mq\Lib\Run;
use App\Services\Api\Mq\Lib\Configs;
use App\Services\Api\Mq\Lib\Params;
use App\Services\Api\Mq\Lib\Consumers;

class Kafka
{
    private $zkServer;
    private $bootstrapServer;
    private $version;
    private $path;

    const VERSION_MAP = [
        '0.10.2.1' => 'kafka_2.11-0.10.2.1',
    ];

    /**
     * constructor
     * @param zkServer zookeeper
     * @param bootstrapServer kafka server ip
     * @param version kafka version
     */
    public function __construct($zkServer, $bootstrapServer, $version)
    {
        $this->path = resource_path() . DIRECTORY_SEPARATOR . 'kafka'. DIRECTORY_SEPARATOR . self::VERSION_MAP[$version] . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR;
        $this->zkServer = $zkServer;
        $this->version = $version;
        $this->bootstrapServer = $bootstrapServer;
    }

    /**
     * Topic 分区信息
     * @param args ['topic'=> 'xxx']
     */
    public function describeTopic($args)
    {
        $client = new Topics($this->zkServer, $this->bootstrapServer, $this->version, $this->path);
        $topic = $client->describeTopic($args['topic']);
        if (empty($topic)) {
            return $this->_formatData(1, null, 0, null);
        }
        $topicInfos = array_filter(explode("\n", $topic));
        $client = null;
        $topic = null;
        //base info
        $base = explode("\t", array_shift($topicInfos));
        $baseInfo = array_reduce($base, function ($carry, $item) {
            $temp = explode(':', $item);
            switch ($temp[0]) {
                case 'Topic':$carry['name'] = $temp[1];break;
                case 'PartitionCount':$carry['partitionCount'] = $temp[1];break;
                case 'ReplicationFactor':$carry['replicationFactor'] = $temp[1];break;
                default:break;
            }
            return $carry;
        }, []);
        //last offset
        $offset = new Run($this->zkServer, $this->bootstrapServer, $this->version, $this->path);
        $offsets = array_filter(explode("\n", $offset->getTopicLastOffset($args['topic'])));
        if (empty($offsets)) {
            $offsetsArr = [];
        } else {
            $offsetsArr = array_reduce($offsets, function ($carry, $item) {
                $temp = explode(':', $item);
                $carry[$temp[1]] = $temp[2];
                return $carry;
            }, []);
        }
        
        $offset = null;
        $offsets = null;
        //partitions info
        $partitions = array_reduce($topicInfos, function ($carry, $item) use ($offsetsArr) {
            $partitionItem = array_filter(explode("\t", $item));
            $partitionInfo = array_reduce($partitionItem, function ($carry, $item) {
                $temp = explode(':', $item);
                switch ($temp[0]) {
                    case 'Partition':$carry['id'] = ltrim($temp[1]);break;
                    case 'Leader':$carry['leader'] = ltrim($temp[1]);break;
                    case 'Replicas':$carry['replicas'] = explode(',', ltrim($temp[1]));break;
                    case 'Isr':$carry['isr'] = explode(',', ltrim($temp[1]));break;
                    default:break;
                }
                
                return $carry;
            }, []);
            
            //offset
            if (!empty($offsetsArr)) {
                $partitionInfo['offset'] = !empty($offsetsArr[$partitionInfo['id']]) ? $offsetsArr[$partitionInfo['id']] : '';
            } else {
                $partitionInfo['offset'] = 'can not get offset';
            }
            $carry[] = $partitionInfo;
            return $carry;
        }, []);
        $baseInfo['partitions'] = $partitions;
        return $this->_formatData(0, null, 1, $baseInfo);
    }

    /**
     * Topic配置信息
     * 不含默认配置
     * @param args ['topic'=> 'xxx']
     */
    public function describeConfigs($args)
    {
        $client = new Configs($this->zkServer, $this->bootstrapServer, $this->version, $this->path);
        $config = rtrim($client->list(Params::CONST_ENTITY_TYPE_TOPICS, $args['topic']), "\n");
        if (empty($config)) {
            return $this->_formatData(1, 'failed to get connfig', 0, null);
        }
        
        $configstr = trim(substr($config, strpos($config, 'are')+3));
        $config = null;
        if (empty($configstr)) {
            return $this->_formatData(0, null, 0, []);
        } else {
            $configArr = array_reduce(explode(',', $configstr), function ($carry, $item) {
                $temp = explode('=', $item);
                $carry[$temp[0]] =  $temp[1];
                return $carry;
            }, []);
            return $this->_formatData(0, null, count($configArr), $configArr);
        }
    }

    /**
     * 更改Topic配置
     * @param args ['topic'=> 'xxx', 'configs' => [ 'xxx' => 'yyy']]
     */
    public function alterConfigs($args)
    {
        $client = new Configs($this->zkServer, $this->bootstrapServer, $this->version, $this->path);
        $config = $client->alter(Params::CONST_ENTITY_TYPE_TOPICS, $args['topic'], $args['configs']);
        if (strpos($config, 'Completed Updating config') !== false) {
            return $this->_formatData(0, $config, 0, null);
        } else {
            return $this->_formatData(1, $config, 0, null);
        }
    }

    /**
     * 删除Topic配置
     * @param args ['topic'=> 'xxx', 'configs' => [ 'xxx', 'yyy']]
     */
    public function deleteConfigs($args)
    {
        $client = new Configs($this->zkServer, $this->bootstrapServer, $this->version, $this->path);
        $config = $client->delete(Params::CONST_ENTITY_TYPE_TOPICS, $args['topic'], $args['configs']);
        if (strpos($config, 'Completed Updating config') !== false) {
            return $this->_formatData(0, $config, 0, null);
        } else {
            return $this->_formatData(1, $config, 0, null);
        }
    }

    /**
     * 新建Topic
     * @param args ['topic' => 'perf-test2', 'partitions' => 3, 'factor' => 3]
     */
    public function createTopic($args)
    {
        $client = new Topics($this->zkServer, $this->bootstrapServer, $this->version, $this->path);
        $topic = $client->createSimpleTopic($args['topic'], $args['partitions'], $args['factor']);
        //Created topic "perf-test2
        //Error while executing topic command : Topic 'perf-test2' already exists.
        if (strpos($topic, 'Created topic') !== false) {
            return $this->_formatData(0, $topic, 0, null);
        } elseif (strpos($topic, 'already exists') !== false) {
            return $this->_formatData(1, 'Topic already exists', 0, null);
        } else {
            return $this->_formatData(1, $topic, 0, null);
        }
    }

    /**
     * 删除Topic
     * @param args ['topic' => 'perf-test2']
     */
    public function deleteTopic($args)
    {
        $client = new Topics($this->zkServer, $this->bootstrapServer, $this->version, $this->path);
        $topic = $client->deleteTopic($args['topic']);
        if (strpos($topic, 'marked for deletion') !== false) {
            return $this->_formatData(0, $topic, 0, null);
        } else {
            return $this->_formatData(1, $topic, 0, null);
        }
    }

    /**
     * 增加Topic分区数
     * @param args ['topic' => 'perf-test2']
     */
    public function addTopicPartition($args)
    {
        $client = new Topics($this->zkServer, $this->bootstrapServer, $this->version, $this->path);
        $add = $client->addTopicPartition($args['topic'], $args['partitions']);

        if (strpos($add, 'succeeded') !== false) {
            return $this->_formatData(0, 'Adding partitions succeeded', 0, null);
        } elseif (strpos($add, 'can only be increased') !== false) {
            return $this->_formatData(1, 'The number of partitions for a topic can only be increased', 0, null);
        } else {
            return $this->_formatData(1, $add, 0, null);
        }
    }

    /**
     * 给定Topic下的消费者组及其详情
     * @param args ['topic' => 'perf-test']
     */
    public function consumers($args)
    {
        $client = new Consumers($this->zkServer, $this->bootstrapServer, $this->version, $this->path);
        $zkConsumers = $client->listZkConsumer();
        $zkConsumersArr = array_filter(explode("\n", $zkConsumers));
        $zkConsumers = null;
        $zkConsumersDetails = array_reduce($zkConsumersArr, function ($carry, $item) use ($client, $args) {
            $groupDetail = $client->describeZkConsumer($item);
            //过滤给定的Topic
            if (preg_match('/\s'.$args['topic'].'\s/', $groupDetail)) {
                $carry[$item] = ['name' => $item, 'type' => 'zookeeper', 'detail' => $groupDetail];
            }
            
            return $carry;
        }, []);

        $kafkaConsumers = $client->listKafkaConsumer();
        $kafkaConsumersArr = array_filter(explode("\n", $kafkaConsumers));
        $kafkaConsumers = null;
        $kafkaConsumersDetails = array_reduce($kafkaConsumersArr, function ($carry, $item) use ($client, $args) {
            $groupDetail = $client->describeKafkaConsumer($item);
            //过滤给定的Topic
            if (preg_match('/\s'.$args['topic'].'\s/', $groupDetail)) {
                $carry[$item] = ['name' => $item, 'type' => 'kafka', 'detail' => $groupDetail];
            }
            
            return $carry;
        }, []);

        $details = array_merge($kafkaConsumersDetails, $zkConsumersDetails);

        return $this->_formatData(0, null, count($details), $details);
    }

    /**
     * 统一输出
     */
    private function _formatData($status, $msg, $total, $data)
    {
        return ['status' => $status, 'msg' => $msg, 'total' => $total, 'data' => $data];
    }
}
