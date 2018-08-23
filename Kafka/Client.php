<?php
namespace App\Services\Api\Mq\Lib;

/**
 * base class client
 */
class Client
{
    protected $zkServer;
    protected $bootstrapServer;
    protected $command = '';
    protected $version = '';
    protected $shellCommand;

    public function __construct($zkServer, $bootstrapServer, $version, $kafkaBinPath)
    {
        $this->zkServer = $zkServer;
        $this->bootstrapServer = $bootstrapServer;
        //set version
        $this->version = $version;
        //gen shell command by version
        $this->shellCommand = $kafkaBinPath . static::SH;
        // $this->shellCommand = Params::KAFKA_BIN_PATH . "$version/" . static::SH;
    }

    /**
     * construct command
     */
    protected function _setCommandParams($command, ...$params)
    {
        $this->command = '';
        $this->command .= $command;
        foreach ($params as $param) {
            $this->command .= $param;
        }
        // var_dump($this->command);
    }

    /**
     * use shell_exec execute command
     */
    protected function _execCommand(...$params)
    {
        //var_dump(vsprintf($this->command, $params));
        return shell_exec(vsprintf($this->command, $params));
    }
}
