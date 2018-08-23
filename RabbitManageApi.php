<?php
namespace App\Services\Api\Mq;

use App\Services\Api\BaseApi;
use Exception;

class Rabbit extends BaseApi
{
    private $ip;
    private $user;
    private $password;
    private $urlMap;

    public function __construct($ip, $user, $password)
    {
        $this->ip = $ip;
        $this->user = $user;
        $this->password = $password;

        /**
         * 接口配置
         * [
         *      '接口名' => [
         *          'uri' => 'rabbitmq接口模板',
         *          'uriArgs' => '有此项时说明uri需要参数，此处写一个参数名字的数组，按照模板参数的顺序组织',
         *          'method' => 'GET/POST/PUT/DELETE等',
         *          'preHandler' => '在POST/PUT等方法需要有HTTP body的时候，指定预处理callable,接收参数数组,返回body体',
         *          'callback' => '在需要处理rabbitmq接口返回的数据时，指定回调callable,接收接口数据，返回最终数据'
         *      ]
         * ]
         */
        $this->uriMap = [
            'vhost' => [
                'uri' => 'vhosts/%s',
                'uriArgs' => ['vhost'],
                'method' => 'GET',
                'callback' => 'vhostCb',
            ],
            'exchanges' => [
                'uri' => 'exchanges/%s',
                'uriArgs' => ['vhost'],
                'method' => 'GET',
            ],
            'queues' => [
                'uri' => 'queues/%s',
                'uriArgs' => ['vhost'],
                'method' => 'GET',
                'callback' => 'queuesCb',
            ],
            'consumers' => [
                'uri' => 'consumers/%s',
                'uriArgs' => ['vhost'],
                'method' => 'GET',
            ],
            'queueConsumers' => [
                'uri' => 'consumers/%s',
                'uriArgs' => ['vhost'],
                'method' => 'GET',
                'callback' => function ($data, $args) {
                    $consumers = array_filter($data, function ($item) use ($args) {
                        return $item['queue']['name'] == $args['queue'];
                    });
                    return $this->_formatData(0, null, count($consumers), $consumers);
                }
            ],
            'nodes' => [
                'uri' => 'overview',
                'method' => 'GET',
                'callback' => function ($data) {
                    $nodes = array_column($data['contexts'], 'node');
                    return $this->_formatData(0, null, count($nodes), $nodes);
                },
            ],
            'newVhost' => [
                'uri' => 'vhosts/%s',
                'uriArgs' => ['vhost'],
                'method' => 'PUT',
                'preHandler' => function ($args) {
                    return json_encode(['name' => $args['vhost']]);
                },
            ],
            'newUser' => [
                'uri' => 'users/%s',
                'uriArgs' => ['username'],
                'method' => 'PUT',
                'preHandler' => function ($args) {
                    return json_encode([
                        'username' => $args['username'],
                        'password' => $args['password'],
                        'tags' => 'management',
                        ]);
                },
            ],
            'setAccess' => [
                'uri' => 'permissions/%s/%s',
                'uriArgs' => ['vhost', 'username'],
                'method' => 'PUT',
                'preHandler' => function ($args) {
                    return json_encode([
                        'username' => $args['username'],
                        'vhost' => $args['vhost'],
                        'configure' => '.*',
                        'write' => '.*',
                        'read' => '.*',
                    ]);
                },
            ],
            'deleteUser' => [
                'uri' => 'users/%s',
                'uriArgs' => ['username'],
                'method' => 'DELETE',
                'preHandler' => function ($args) {
                    return json_encode(['username' => $args['username']]);
                },
            ],
            'setHa' => [
                'uri' => 'policies/%s/%s',
                'uriArgs' => ['vhost', 'haName'],
                'method' => 'PUT',
                'preHandler' => function ($args) {
                    return json_encode([
                        'apply-to' => 'all',
                        'definition' => ['ha-mode' => 'all'],
                        'name' => $args['haName'],
                        'pattern' => '^',
                        'vhost' => $args['vhost'],
                    ]);
                }
            ],
            'getVhostShovels' => [
                'uri' => 'shovels/%s',
                'uriArgs' => ['vhost'],
                'method' => 'GET',
            ],
            'getAllShovels' => [
                'uri' => 'shovels',
                'method' => 'GET',
            ],
            'deleteShovel' => [
                'uri' => 'parameters/shovel/%s/%s',
                'uriArgs' => ['vhost', 'shovelName'],
                'method' => 'DELETE',
                'preHandler' => function ($args) {
                    return json_encode([
                        'component' => 'shovel',
                        'vhost' => $args['vhost'],
                        'name' => $args['shovelName'],
                    ]);
                }
            ],
            'addShovel' => [
                'uri' => 'parameters/shovel/%s/%s',
                'uriArgs' => ['vhost', 'shovelName'],
                'method' => 'PUT',
                'preHandler' => function ($args) {
                    $temp = [];
                    $temp['component'] = 'shovel';
                    $temp['vhost'] = $args['vhost'];
                    $data = [];
                    $data['add-forward-headers'] = false;
                    $data['ack-mode'] = 'on-confirm';
                    $data['delete-after'] = 'never';
                    $data['src-' . $args['src-type']] = $args['src'];
                    $data['dest-' . $args['dest-type']] = $args['dest'];
                    $data['src-uri'] = $args['src-uri'];
                    $data['dest-uri'] = $args['dest-uri'];
                    if ($args['src-type'] == 'exchange') {
                        $data['src-exchange-key'] = $args['src-route'];
                    }
                    if ($args['dest-type'] == 'exchange') {
                        $data['dest-exchange-key'] = $args['dest-route'];
                    }
                    $temp['value'] = $data;
                    
                    //dd($temp);
                    return json_encode($temp);
                }
            ],
            'purgeQueue' => [
                'uri' => 'queues/%s/%s/contents',
                'uriArgs' => ['vhost', 'queue'],
                'method' => 'DELETE',
                'preHandler' => function ($args) {
                    return json_encode([
                        'mode' => 'purge',
                        'vhost' => $args['vhost'],
                        'name' => $args['queue'],
                    ]);
                }
            ],
            'deleteQueue' => [
                'uri' => 'queues/%s/%s',
                'uriArgs' => ['vhost', 'queue'],
                'method' => 'DELETE',
                'preHandler' => function ($args) {
                    return json_encode([
                        'mode' => 'delete',
                        'vhost' => $args['vhost'],
                        'name' => $args['queue'],
                    ]);
                }
            ],
            'getMessages' => [
                'uri' => 'queues/%s/%s/get',
                'uriArgs' => ['vhost', 'queue'],
                'method' => 'POST',
                'preHandler' => function ($args) {
                    return json_encode([
                        'count' => $args['count'],
                        'encoding' => 'auto',
                        'vhost' => $args['vhost'],
                        'name' => $args['queue'],
                        'requeue' => true,
                        'truncate' => 50000,
                    ]);
                }
            ],
            'queueBindings' => [
                'uri' => 'queues/%s/%s/bindings',
                'uriArgs' => ['vhost', 'queue'],
                'method' => 'GET',
            ],
            'addBinding' => [
                'uri' => 'bindings/%s/e/%s/%s/%s',
                'uriArgs' => ['vhost', 'source', 'destination_type', 'destination'],
                'method' => 'POST',
                'preHandler' => function ($args) {
                    return json_encode($args);
                }
            ],
            'deleteBinding' => [
                'uri' => 'bindings/%s/e/%s/%s/%s/%s',
                'uriArgs' => ['vhost', 'source', 'destination_type', 'destination', 'properties_key'],
                'method' => 'DELETE',
                'preHandler' => function ($args) {
                    return json_encode($args);
                }
            ],
            'deleteExchange' => [
                'uri' => 'exchanges/%s/%s',
                'uriArgs' => ['vhost', 'exchange'],
                'method' => 'DELETE',
                'preHandler' => function ($args) {
                    return json_encode([
                        'mode' => 'delete',
                        'vhost' => $args['vhost'],
                        'name' => $args['exchange'],
                    ]);
                }
            ],
            'exchangeBindingsSource' => [
                'uri' => 'exchanges/%s/%s/bindings/source',
                'uriArgs' => ['vhost', 'exchange'],
                'method' => 'GET',
            ],
            'exchangeBindingsDestination' => [
                'uri' => 'exchanges/%s/%s/bindings/destination',
                'uriArgs' => ['vhost', 'exchange'],
                'method' => 'GET',
            ],
            'addQueue' => [
                'uri' => 'queues/%s/%s',
                'uriArgs' => ['vhost', 'queue'],
                'method' => 'PUT',
                'preHandler' => 'addQueuePre',
            ],
            'addExchange' => [
                'uri' => 'exchanges/%s/%s',
                'uriArgs' => ['vhost', 'exchange'],
                'method' => 'PUT',
                'preHandler' => 'addExchangePre',
            ],
        ];
    }

    /**
     * 新增队列预处理
     */
    private function addQueuePre($args)
    {
        $temp = [];
        $temp['auto_delete'] = boolval($args['auto_delete']);
        $temp['durable'] = boolval($args['durable']);
        $temp['name'] = $args['name'];
        $temp['node'] = $args['node'];
        $temp['vhost'] = $args['vhost'];
        $arguments = [];
        if (isset($args['x-message-ttl']) && !empty($args['x-message-ttl'])) {
            $arguments['x-message-ttl'] = $args['x-message-ttl'] * 1000;
        }
        if (isset($args['x-expires']) && !empty($args['x-expires'])) {
            $arguments['x-expires'] = $args['x-expires'] * 1000;
        }
        if (isset($args['x-max-length']) && !empty($args['x-max-length'])) {
            $arguments['x-max-length'] = $args['x-max-length'];
        }
        if (isset($args['x-max-length-bytes']) && !empty($args['x-max-length-bytes'])) {
            $arguments['x-max-length-bytes'] = $args['x-max-length-bytes'];
        }
        if (isset($args['x-dead-letter-exchange']) && !empty($args['x-dead-letter-exchange'])) {
            $arguments['x-dead-letter-exchange'] = $args['x-dead-letter-exchange'];
        }
        if (isset($args['x-dead-letter-routing-key']) && !empty($args['x-dead-letter-routing-key'])) {
            $arguments['x-dead-letter-routing-key'] = $args['x-dead-letter-routing-key'];
        }
        if (isset($args['x-max-priority']) && !empty($args['x-max-priority'])) {
            $arguments['x-max-priority'] = $args['x-max-priority'];
        }

        $temp['arguments'] = $arguments;
        return json_encode($temp);
    }

    /**
     * 新增Exchange预处理
     */
    private function addExchangePre($args)
    {
        $temp = [];
        $temp['auto_delete'] = boolval($args['auto_delete']);
        $temp['durable'] = boolval($args['durable']);
        $temp['name'] = $args['name'];
        $temp['type'] = $args['type'];
        $temp['vhost'] = $args['vhost'];
        $temp['internal'] = boolval($args['internal']);
        $arguments = [];
        if (isset($args['alternate-exchange']) && !empty($args['alternate-exchange'])) {
            $arguments['alternate-exchange'] = $args['alternate-exchange'];
        }
        $temp['arguments'] = $arguments;
        return json_encode($temp);
    }


    /**
     * queue数据过滤
     */
    private function queuesCb($data)
    {
        $queues = array_reduce($data, function ($carry, $item) {
            $queue = array_filter($item, function ($key) {
                return in_array($key, [
                    'name', 'node', 'slave_nodes', 'synchronised_slave_nodes', 'recoverable_slaves', 'policy',
                    'durable', 'exclusive', 'auto_delete', 'arguments', 'state', 'consumers', 'consumer_utilisation',
                    'messages', 'messages_ready', 'messages_unacknowledged',
                    ]);
            }, ARRAY_FILTER_USE_KEY);

            $carry[] = $queue;
            return $carry;
        }, []);

        return $this->_formatData(0, null, count($queues), $queues);
    }

    /**
     * vhost详情页数据处理回调
     */
    private function vhostCb($data)
    {
        if (isset($data['message_stats'])) {
            //过滤统计细节
            $vhostStatsInfo = array_filter($data['message_stats'], function ($key) {
                return !strpos($key, '_detail');
            }, ARRAY_FILTER_USE_KEY);
        } else {
            $vhostStatsInfo = [];
        }
        //过滤消息细节
        $vhostMessageInfo = array_filter($data, function ($key) {
            return !(strpos($key, '_detail') !== false || strpos($key, 'message_stats') !== false);
        }, ARRAY_FILTER_USE_KEY);

        $vhostInfo = array_merge($vhostStatsInfo, $vhostMessageInfo);

        //添加释义
        array_walk($vhostInfo, function (&$item, $key) {
            $keyName = '';
            switch ($key) {
                    case 'publish': $keyName = '发布总数';break;
                    case 'publish_in': $keyName = '发入总数';break;
                    case 'publish_out': $keyName = '发出总数';break;
                    case 'ack': $keyName = '消费确认数';break;
                    case 'deliver_get': $keyName = '消费投递数';break;
                    case 'confirm': $keyName = '发布确认数';break;
                    case 'return_unroutable': $keyName = '无法路由数';break;
                    case 'redeliver': $keyName = '重新投递数';break;
                    case 'deliver': $keyName = '投递总数';break;
                    case 'deliver_no_ack': $keyName = '投递未确认';break;
                    case 'get': $keyName = '消费拉取数';break;
                    case 'get_no_ack': $keyName = '拉取未确认';break;
                    case 'send_oct': $keyName = '发送字节数';break;
                    case 'recv_oct': $keyName = '接收字节数';break;
                    case 'messages': $keyName = '消息总数';break;
                    case 'messages_ready': $keyName = '就绪消息数';break;
                    case 'messages_unacknowledged': $keyName = '未确认消息';break;
                    case 'name': $keyName = '名称';break;
                    case 'tracing': $keyName = '追踪';break;
                    default:$keyName = $key;
                }
            $item = array(
                    'keyName' => $keyName,
                    'value' => $item
                );
        });

        return $this->_formatData(0, null, count($vhostInfo), $vhostInfo);
    }

    public function __call($fn, $args)
    {
        //检查ip,user,password
        if (empty($this->ip) || empty($this->user) || empty($this->password)) {
            throw new Exception('请先设置必要参数');
        }
        //对vhost进行urlencode
        //构造curl参数
        $uriArgs = [];
        if (isset($this->uriMap[$fn]['uriArgs'])) {
            $uriArgs = array_filter(
                $args[0],
                function ($key) use ($fn) {
                    return in_array($key, $this->uriMap[$fn]['uriArgs']);
                },
                ARRAY_FILTER_USE_KEY
            );
        }
        //调整参数顺序
        uksort($uriArgs, function ($a, $b) use ($fn) {
            $str = implode(',', $this->uriMap[$fn]['uriArgs']) . ',';
            return strpos($str, "$a,") > strpos($str, "$b,");
        });

        //构造请求
        $req = [
            'ip' => $this->ip,
            'method' => $this->uriMap[$fn]['method'],
            'uri' => isset($this->uriMap[$fn]['uriArgs']) ? vsprintf($this->uriMap[$fn]['uri'], array_values($uriArgs)) : $this->uriMap[$fn]['uri'],
            'user' => 'xeswx',
            'password' => 'xeswx'
        ];
        if ($this->uriMap[$fn]['method'] != 'GET') {
            //body预处理
            if (isset($this->uriMap[$fn]['preHandler']) && is_callable($this->uriMap[$fn]['preHandler'])) {
                $req['body'] = call_user_func($this->uriMap[$fn]['preHandler'], $args[0]);
            } elseif (isset($this->uriMap[$fn]['preHandler']) && is_string($this->uriMap[$fn]['preHandler'])) {
                $req['body'] = call_user_func(array($this, $this->uriMap[$fn]['preHandler']), $args[0]);
            } else {
                $req['body'] = $args[0]['body'];
            }
        }
        //发送请求
        try {
            $data = self::curlRabbit($req);
        } catch (Exception $e) {
            if ($e->getCode() == 404) {
                return $this->_formatData(1, '404', 0, null);
            } else {
                return $this->_formatData(1, $e->getMessage(), 0, null);
            }
        }
        
        //处理数据
        if (isset($this->uriMap[$fn]['callback']) && is_callable($this->uriMap[$fn]['callback'])) {
            $final = call_user_func($this->uriMap[$fn]['callback'], $data, $args[0]);
            return $final;
        } elseif (isset($this->uriMap[$fn]['callback']) && is_string($this->uriMap[$fn]['callback'])) {
            $final = call_user_func(array($this, $this->uriMap[$fn]['callback']), $data, $args[0]);
            return $final;
        } else {
            if ($data === null) {
                return $this->_formatData(0, null, 0, null);
            }
            return $this->_formatData(0, null, count($data), $data);
        }
    }

    /**
     * 统一输出
     */
    private function _formatData($status, $msg, $total, $data)
    {
        return ['status' => $status, 'msg' => $msg, 'total' => $total, 'data' => $data];
    }
}
