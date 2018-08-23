<?php
namespace App\Services\Api\Mq\Lib;

class Params
{
    const KAFKA_BIN_PATH = '/usr/local/kafka/bin/';
    const PARAM_ZOOKEEPER = '--zookeeper %s ';
    const PARAM_LIST = '--list ';
    const PARAM_DESCRIBE = '--describe ';
    const PARAM_DELETE = '--delete ';
    const PARAM_CREATE = '--create ';
    const PARAM_ALTER = '--alter ';
    const PARAM_GENERATE = '--generate ';
    const PARAM_EXECUTE = '--execute ';
    const PARAM_VERIFY = '--verify ';

    const PARAM_TOPIC = '--topic %s ';
    const PARAM_PARTITIONS = '--partitions %s ';
    const PARAM_REPLICATION_FACTOR = '--replication-factor %s ';
    const PARAM_TOPICS_CONFIG = '--config %s ';

    const PARAM_BOOTSTRAP_SERVER = '--bootstrap-server %s ';
    const PARAM_NEW_CONSUMER = '--new-consumer ';
    const PARAM_GROUP = '--group %s ';
    const PARAM_ENTITY_TYPE = '--entity-type %s ';
    const PARAM_ENTITY_NAME = '--entity-name %s ';
    const PARAM_ADD_CONFIG = '--add-config %s ';
    const PARAM_DELETE_CONFIG = '--delete-config %s ';
    
    const CONST_ENTITY_TYPE_TOPICS = 'topics';
    const CONST_ENTITY_TYPE_CLIENTS = 'clients';
    const CONST_ENTITY_TYPE_USERS = 'users';
    const CONST_ENTITY_TYPE_BROKERS = 'brokers';

    const JSON_FILE_PREFIX_TOPICS_TO_MOVE = '/tmp/topics-to-move-json-file-';
    const JSON_FILE_PREFIX_REASSIGNMENT = '/tmp/reassignment-';
    const JSON_FILE_PREFIX_PREFERRED = '/tmp/preferred-';

    const PARAM_TOPICS_TO_MOVE_JSON_FILE = '--topics-to-move-json-file %s ';
    const PARAM_REASSIGNMENT_JSON_FILE = '--reassignment-json-file %s ';
    const PARAM_BROKER_LIST = '--broker-list %s ';
    const PARAM_PATH_TO_JSON_FILE = '--path-to-json-file %s ';

    const PARAM_RUN_GET_OFFSET = 'kafka.tools.GetOffsetShell ';
    const PARAM_RUN_GET_OFFSET_BROKER_LIST = '--broker-list %s ';
    const PARAM_RUN_GET_OFFSET_TIME = '--time %s';
}
