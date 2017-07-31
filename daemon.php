<?php

/**
 *
 *  @author renjianzhi<renjianzhi@100tal.com>
 *  @copyright xes
 */
class DaemonProcess
{

    /**
     * 调试模式
     * 该模式下会进行echo var_dump等输出
     * @var type
     */
    protected $debug = true;

    /**
     * 进程名称
     * @var type
     */
    protected $processName = 'default processName';

    /**
     * 根据文件路径计算的一个唯一标识，用于设置各种默认文件的名称
     * @var type
     */
    protected $uniqueNameDef;

    /**
     * 启动文件
     * @var type
     */
    protected $startFile;

    /**
     * PID文件
     * @var type
     */
    protected $pidFile;

    /**
     * 日志文件
     * @var type
     */
    protected $logFile;

    /**
     * 自定义输出文件
     * @var type
     */
    protected $outFile;

    /**
     * 是否守护进程化
     * @var type
     */
    protected $daemonize = false;

    /**
     * 进程PID
     * @var type
     */
    protected $pid;

    /**
     * 检查环境
     */
    public static function checkEnv()
    {
        if (php_sapi_name() != 'cli') {
            $this->_debugOutput('only support cli', true);
            exit;
        }
        if (!function_exists('pcntl_signal')) {
            $this->_debugOutput('require pcntl_signal', true);
            exit;
        }
        //PHP < 5.3 use ticks
        if (!function_exists('pcntl_signal_dispatch')) {
            $this->_debugOutput('PHP < 5.3 use declare', true);
            declare(ticks = 10);
        }
    }

    /**
     * 设置进程标题
     */
    public function setProcessTitle()
    {
        // >5.5
        if (function_exists('cli_set_process_title')) {
            cli_set_process_title($this->processName . ' start with ' . $this->startFile);
        } elseif (extension_loaded('proctitle') && function_exists('setproctitle')) {
            setproctitle($this->processName . ' start with ' . $this->startFile);
        }
    }

    /**
     * 解析命令
     * 除了start restart命令
     * 其他命令stop reload status都将exit
     * @global type $argv
     */
    public function parseCommand()
    {
        global $argv;
        $processFile = $argv[0];
        if (!isset($argv[1])) {
            $this->_debugOutput("Usage: php $processFile start | stop | restart | reload | status", true);
            exit;
        }

        $command = trim($argv[1]);
        $commandFirstParam = isset($argv[2]) ? trim($argv[2]) : '';

        //打印用户命令
        $mode = '';
        if ($command === 'start' & $commandFirstParam === '-d') {
            $mode = 'in daemon mode';
        } else {
            $mode = 'in debug mode';
        }

        $this->_debugOutput("$command $mode", true);
        /*
         * 检查命令是否可以运行
         * 检查PID文件
         * 1. 如果PID文件的程序在运行
         * start命令提示已经在运行
         * 2. 如果PID文件的程序不在运行
         * stop restart reload status命令提示程序没有运行
         */

        $masterPid = @file_get_contents($this->pidFile);
        $masterAlive = $masterPid && @posix_kill($masterPid, SIG_DFL);

        if ($masterAlive) {
            if ($command === 'start') {
                $this->_debugOutput('process running with PID' . $masterPid, true);
                exit;
            }
        } else {
            if ($command !== 'start') {
                $this->_debugOutput('process not run' . $masterPid, true);
                exit;
            }
        }
        //执行命令
        //除了start restart命令
        //其他命令stop reload status都将exit
        switch ($command) {
            case 'start':
                if ($commandFirstParam === '-d') {
                    $this->daemonize = true;
                }
                break;
            case 'status':
                posix_kill($masterPid, SIGUSR2);
                exit;
            case 'stop':
            case 'restart':
                $this->_debugOutput("process {$this->uniqueNameDef} stoping...", true);
                //向主进程发送中断信号
                $masterPid && posix_kill($masterPid, SIGINT);
                //在一定的超时时间范围之内，检测进程PID是否被杀掉
                $timeOut = 5;
                $start = time();
                while (1) {
                    $masterAlive = $masterPid && posix_kill($masterPid, SIG_DFL);
                    if ($masterAlive) {
                        if (time() - $start > $timeOut) {
                            $this->_debugOutput('process stop failed', true);
                            exit;
                        }
                        usleep(100000);
                        continue;
                    }
                    $this->_debugOutput("process {$this->uniqueNameDef} stop success", true);
                    //如果是stop命令，直接退出
                    if ($command === 'stop') {
                        exit;
                    }
                    //如果是restart命令
                    if ($commandFirstParam === '-d') {
                        $this->daemonize = true;
                    }
                    break;
                }
                break;
            case 'reload':
                $this->_debugOutput("process {$this->uniqueNameDef} reloading...", true);
                posix_kill($masterPid, SIGUSR1);
                exit;
            default :
                $this->_debugOutput('Usage: php yourfile.php start | stop | restart | reload | status', true);
                exit;
        }
    }

    /**
     * 守护进程化
     * 与其他语言（如C语言）的做法是一致的
     */
    public function daemonize()
    {
        if (!$this->daemonize) {
            return;
        }
        /*
         * 设置允许当前进程创建文件或者目录最大可操作的权限
         * 0取反再创建文件时权限相与，也就是：(~0) & mode 等于八进制的值0777 & mode
         * 这样就是给后面的代码调用函数mkdir给出最大的权限，避免了创建目录或文件的权限不确定性
         */
        umask(0);
        //fork子进程脱离终端，内存不足时会fork失败
        $pid = pcntl_fork();
        if ($pid === -1) {
            $this->_debugOutput('pcntl_fork failed', true);
            exit;
        } elseif ($pid > 0) {
            //主进程退出
            exit(0);
        }
        //Make the current process a session leader
        $setSid = posix_setsid();
        if ($setSid === -1) {
            $this->_debugOutput('posix_setsid failed', true);
            exit;
        }
        // Fork again avoid SVR4 system regain the control of terminal.
        $pid = pcntl_fork();
        if ($pid === -1) {
            $this->_debugOutput('pcntl_fork failed', true);
            exit;
        } elseif ($pid > 0) {
            //主进程退出
            exit(0);
        }
    }

    /**
     * 初始化程序
     * 设置进程名称
     * 设置启动文件名称
     * 设置默认PID文件
     * 设置日志文件
     */
    function init()
    {

        $backTrace = debug_backtrace();
        $this->startFile = array_pop($backTrace)['file'];
        //根据文件名转换出一个唯一标识
        $this->uniqueNameDef = str_replace('/', '_', $this->startFile);
        $this->setProcessTitle();

        if (empty($this->pidFile)) {
            $this->pidFile = sys_get_temp_dir() . $this->uniqueNameDef . '.pid';
        }

        if (empty($this->logFile)) {
            $this->logFile = sys_get_temp_dir() . "{$this->uniqueNameDef}.run.log";
        }

        if (empty($this->outFile)) {
            $this->outFile = sys_get_temp_dir() . "{$this->uniqueNameDef}.out.log";
        }

        touch($this->logFile);
        chmod($this->logFile, 0622);
        touch($this->outFile);
        chmod($this->outFile, 0622);
    }

    public function installSignal()
    {
        // stop
        pcntl_signal(SIGINT, function() {
            echo 'stop';
            exit;
        }, false);
        // reload
        pcntl_signal(SIGUSR1, function() {
            echo 'reload';
        }, false);
        // status
        pcntl_signal(SIGUSR2, function() {
            echo 'status';
        }, false);
        // ignore
        pcntl_signal(SIGPIPE, SIG_IGN, false);
        pcntl_signal_dispatch();
    }

    /**
     * 保存进程PID
     */
    public function savePid()
    {
        $this->pid = posix_getpid();
        file_put_contents($this->pidFile, $this->pid);
    }

    /**
     * 重定向输出
     */
    public function resetStd()
    {
        if (!$this->daemonize) {
            return true;
        }
        global $STDOUT, $STDERR;
        if (posix_access($this->outFile, POSIX_R_OK | POSIX_W_OK)) {
            @fclose(STDOUT);
            @fclose(STDERR);
            $STDOUT = fopen($this->outFile, "a");
            $STDERR = fopen($this->outFile, "a");
        } else {
            $this->_debugOutput('resetStd : check out file', true);
            exit;
        }
    }

    /**
     * 设置运行用户
     */
    public function setRunUser()
    {

    }

    public function runCode()
    {
        while (1) {
            sleep(1);
            echo "runnig\n";
        }
    }

    function run()
    {
        self::checkEnv();
        $this->init();
        $this->parseCommand();
        //start/restart命令进行下面的逻辑
        $this->daemonize();
        $this->installSignal();
        $this->savePid();
        $this->resetStd();
        $this->runCode();
    }

    /**
     * debug输出方法
     * @param type $message
     * @param type $always 任何情况都输出标志
     * @return boolean
     */
    private function _debugOutput($message, $always = false)
    {
        if ($always || !$this->daemonize && $this->debug) {
            var_dump($message);
        }
        return true;
    }

}

$process = new DaemonProcess();
$process->run();

