<?php 
/**
 * slog专用函数
 *
 * @param        $log
 * @param string $type
 * @param string $css
 *
 * @return mixed
 * @throws Exception
 */
if (!function_exists('slog')) {
    function slog()
    {
        $args = func_get_args();
        $count = count($args);
        
        // 如果没有参数，直接返回
        if ($count === 0) {
            return;
        }
        
        // 获取最后一个参数，判断是否为日志类型
        $last_arg = end($args);
        $type = 'log';
        if (in_array($last_arg, array('log', 'info', 'error', 'warn', 'alert', 'sql'))) {
            $type = $last_arg;
            array_pop($args); // 移除最后一个参数
        }
        
        // 合并所有参数为日志内容
        $log = '';
        foreach ($args as $arg) {
            if (is_array($arg) || is_object($arg)) {
                $log .= print_r($arg, true) . "\n";
            } else {
                $log .= $arg . "\n";
            }
        }
        
        return call_user_func(array('SocketLog', $type), $log);
    }
}

if (!function_exists('p')) {
    /**
     * 打印数据
     * @param mixed ...$data
     * */
    function p(...$data) {
        $arg_list = func_get_args();

        $arg_list = func_num_args() == 1 ? $arg_list[0] : $arg_list;

        echo '<pre>' . print_r($arg_list, true) . '</pre>' . "\r\n\r\n";
    }
}


class SocketLog
{
    public static $start_time = 0;
    public static $start_memory = 0; // 初始化为0
    public static $port = 1116; //SocketLog 服务的http的端口号
    public static $version = '0.1.20250417'; // 当前版本号
    public static $update_urls = array(
        'https://z-y.site/works/SocketLog',
        'https://raw.githubusercontent.com/DDZH-DEV/works/refs/heads/master/socket-debug-tool/SocketLog.php'
    ); // 更新检查地址
    public static $check_urls = array(
        'https://z-y.site/version-update?alias=socket-debug-tool&version_alias=client',
        'https://raw.githubusercontent.com/DDZH-DEV/works/refs/heads/master/socket-debug-tool/latest.json'
    ); // 版本检查地址
    public static $log_types = array('log', 'info', 'error', 'warn', 'table', 'group', 'groupCollapsed', 'groupEnd', 'alert');

    protected static $_instance;

    public static $configFile=__DIR__.'/.slog-config.json';

    public static $config = array(
        'enable' => true, //是否记录日志的开关 
        'optimize' => false,
        'show_included_files' => true,
        'error_handler' => true,
        'slog_post' => 1,
        'http_port' => 1116, // 添加http端口配置
        //日志强制记录到配置的client_id
        'slog_client_id' => '',
        'force_client_id' => '',
        //限制允许读取日志的client_id
        'allow_client_ids' => array()
    );

    protected static $logs = array();

    protected static $css = array(
        'log' => 'color:#333;background:#f5f5f5;padding:2px 5px;border-radius:3px;',
        'info' => 'color:#1890ff;background:#e6f7ff;padding:2px 5px;border-radius:3px;',
        'error' => 'color:#f5222d;background:#fff1f0;padding:2px 5px;border-radius:3px;',
        'warn' => 'color:#faad14;background:#fffbe6;padding:2px 5px;border-radius:3px;', 
        'alert' => 'color:#eb2f96;background:#fff0f6;padding:2px 5px;border-radius:3px;',
        'sql' => 'color:#009bb4;background:#e6fffb;padding:2px 5px;border-radius:3px;', 
        'page' => 'color:#FFF;background:#1487F6;padding:8px 10px;'
    );
 
    public static function __callStatic($method, $args)
    {
        if (in_array($method, self::$log_types)) {
            array_unshift($args, $method);
            return call_user_func_array(array(self::getInstance(), 'record'), $args);
        }
    }
    /**
     * 接管报错
     */
    public static function registerErrorHandler()
    {
        set_error_handler(array(__CLASS__, 'error_handler'));
        set_exception_handler(array(__CLASS__, 'exception_handler'));
        register_shutdown_function(array(__CLASS__, 'fatalError'));
    }

    public static function error_handler($errno, $errstr, $errfile, $errline, $title = '')
    {
        switch ($errno) {
            case E_WARNING:
                $severity = 'E_WARNING';
                break;
            case E_NOTICE:
                $severity = 'E_NOTICE';
                break;
            case E_USER_ERROR:
                $severity = 'E_USER_ERROR';
                break;
            case E_USER_WARNING:
                $severity = 'E_USER_WARNING';
                break;
            case E_USER_NOTICE:
                $severity = 'E_USER_NOTICE';
                break;
            case E_RECOVERABLE_ERROR:
                $severity = 'E_RECOVERABLE_ERROR';
                break;
            case E_DEPRECATED:
                $severity = 'E_DEPRECATED';
                break;
            case E_USER_DEPRECATED:
                $severity = 'E_USER_DEPRECATED';
                break;
            case E_ERROR:
                $severity = 'E_ERR';
                break;
            case E_PARSE:
                $severity = 'E_PARSE';
                break;
            case E_CORE_ERROR:
                $severity = 'E_CORE_ERROR';
                break;
            case E_COMPILE_ERROR:
                $severity = 'E_COMPILE_ERROR';
                break;
            default:
                $severity = 'E_UNKNOWN_ERROR_' . $errno;
                break;
        }
        $msg = "{$severity}: {$errstr} in {$errfile} on line {$errline}";

        $title = is_string($title) ? $title : 'PHP ERROR';

        self::$logs[] = array(
            'type' => 'group',
            'msg' => $title,
            'css' => 'color:#f2f2f2;background:#d60000;'
        );
        self::$logs[] = array(
            'type' => 'log',
            'msg' => $msg,
            'css' => 'color:#d60000;'
        );
        self::$logs[] = array(
            'type' => 'groupEnd',
            'msg' => '',
            'css' => '',
        );

        if (php_sapi_name() == "cli") {
            // 表明当前运行方式是 cli-mode
            self::sendLog();
        }
    }

    public static function exception_handler($e)
    {

        $message = $e->getMessage() . "\r\n" . $e->getTraceAsString();
        self::error_handler($e->getCode(),  $message, $e->getFile(), $e->getLine(), 'PHP Exception');
        //self::sendLog(); //此类终止不会调用类的 __destruct 方法，所以此处手动sendLog
    }

    public static function fatalError()
    {
        // 保存日志记录
        if ($e = error_get_last()) {
            self::error_handler($e['type'], $e['message'], $e['file'], $e['line']);
            self::sendLog(); //此类终止不会调用类的 __destruct 方法，所以此处手动sendLog
        }
    }

    /**
     * 加载配置文件
     */
    protected static function loadConfig()
    { 
        if (file_exists(self::$configFile)) {
            $config = json_decode(file_get_contents(self::$configFile), true);
            if (is_array($config)) {
                self::$config = array_merge(self::$config, $config);
            }
        }
    }

    /**
     * 获取单例
     */
    public static function getInstance()
    {
        if (!self::$_instance) {
            self::$_instance = new self();
            self::loadConfig(); // 加载配置文件
            self::$start_memory = memory_get_usage();
            self::$start_time = microtime(true);
        }
        return self::$_instance;
    }

    public function __destruct()
    {
        self::sendLog();
    }
 

    protected static function check()
    {
        if (!self::getConfig('enable')) {
            return false;
        }
        $tabid = self::getClientArg('tabid');

        $slog_client_id = self::getConfig('slog_client_id');

        //是否记录日志的检查
        if (!$tabid && !$slog_client_id && !self::getConfig('force_client_id')) {
            return false;
        }
        //用户认证
        $allow_client_ids = self::getConfig('allow_client_ids');

        if (!empty($allow_client_ids)) {
            if (!in_array($slog_client_id, $allow_client_ids)) {
                return false;
            }
        }
        return true;
    }

    protected static function getClientArg($name)
    {
        static $args = array();

        $key = 'HTTP_USER_AGENT';

        if (isset($_SERVER['HTTP_SOCKETLOG'])) {
            $key = 'HTTP_SOCKETLOG';
        }

        if (!isset($_SERVER[$key])) {
            return null;
        }
        if (empty($args)) {
            if (!preg_match('/SocketLog\((.*?)\)/', $_SERVER[$key], $match)) {
                $args = array('tabid' => null);
                return null;
            }
            parse_str($match[1], $args);
        }
        if (isset($args[$name])) {
            return $args[$name];
        }
        return null;
    }


    //获得配置
    public static function getConfig($name)
    {
        if (isset(self::$config[$name]))
            return self::$config[$name];
        return null;
    }

    protected static function _log($type, $logs, $css = '')
    {
        self::getInstance()->record($type, $logs, $css);
    }

    //记录日志
    public function record($type, $msg = '', $css = '')
    {

        if (!self::check()) {
            return;
        }

        if(!$css && isset(self::$css[$type]) && self::$css[$type]){
            $css=self::$css[$type];
        }

        self::$logs[] = array(
            'type' => $type,
            'msg' => $msg,
            'css' => $css
        ); 

        if (php_sapi_name() == "cli") {
            // 表明当前运行方式是 cli-mode
            self::sendLog();
        }
    }

    public static function sendLog()
    {
        if (!self::$logs || !self::check()) {
            return;
        }

        $end_time = microtime(true);
        $time_str = '';
        $memory_str = '';

        $runtime = self::$start_time ? $end_time - self::$start_time : $end_time - $_SERVER['REQUEST_TIME'];
        $reqs = number_format(1 / $runtime, 2);
        $time_str = "[耗时:" . number_format($runtime, 2) . "s][吞吐率:" . $reqs . "req/s]";

        if (self::$start_memory) {
            $current_memory = memory_get_usage();
            $memory_use = $current_memory - self::$start_memory;
            $memory_str = "[内存:" . self::formatMemory($memory_use) . "]";
        }

        if (isset($_SERVER['HTTP_HOST'])) {
            $current_uri =  $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        } else {
            $current_uri = "cmd:" . implode(' ', $_SERVER['argv']);
        }
        array_unshift(self::$logs, array(
            'type' => 'group',
            'msg' =>  $current_uri . '|' . $time_str . $memory_str,
            'url' => $current_uri,
            'css' => self::$css['page']
        ));

        if (self::getConfig('show_included_files')) {

            self::$logs[] = array(
                'type' => 'group',
                'msg' => 'PHP INCLUDED FILES',
                'css' => 'color:#f2f2f2;background:#7d440e;'
            );
            self::$logs[] = array(
                'type' => 'log',
                'msg' => implode("\n", get_included_files()),
                'css' => 'color:#7d440e;'
            );
            self::$logs[] = array(
                'type' => 'groupEnd',
                'msg' => '',
                'css' => '',
            );
        }

        self::$logs[] = array(
            'type' => 'groupEnd',
            'msg' => '',
            'css' => '',
        );

        $tabid = self::getClientArg('tabid');

        $slog_client_id = self::getConfig('slog_client_id');

        $logs = array(
            'tabid' => $tabid,
            'slog_client_id' => $slog_client_id,
            'logs' => self::$logs,
            'force_client_id' => self::getConfig('force_client_id'),
        ); 

        $msg = @json_encode($logs);
        $address = '/' . $slog_client_id; //将client_id作为地址， server端通过地址判断将日志发布给谁
        self::send(self::getConfig('address'), $msg, $address);

        self::$logs = array();
    }


    /**
     * @param string   $host    - $host of socket server
     * @param string $message - 发送的消息
     * @param string $address - 地址
     *
     * @return bool
     */
    public static function send($host, $message = '', $address = '/')
    {
        static $Curl, $_flag;

        $host = explode(':', $host)[0];
        
        // 使用配置的端口
        $port = self::getConfig('http_port') ?: self::$port;
        $url = 'http://' . $host . ':' . $port . $address;  
        $Curl = $Curl ? $Curl : curl_init();

        if ($_flag == 1) {
            curl_setopt($Curl, CURLOPT_POSTFIELDS, $message);
            $response = curl_exec($Curl);
            return $response;
        } else {
            curl_setopt($Curl, CURLOPT_URL, $url);
            curl_setopt($Curl, CURLOPT_POST, true);
            curl_setopt($Curl, CURLOPT_POSTFIELDS, $message);
            curl_setopt($Curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($Curl, CURLOPT_CONNECTTIMEOUT, 1);
            curl_setopt($Curl, CURLOPT_TIMEOUT, 3);
            $headers = array(
                "Content-Type: application/json;charset=UTF-8"
            );
            curl_setopt($Curl, CURLOPT_HTTPHEADER, $headers); //设置header
            $response = curl_exec($Curl);
            $_flag = 1;
            return $response;
        }
    }

    static function listen()
    {
        if (!self::getInstance() && !self::check()) {
            return;
        }
        if (isset(self::$config['slog_post']) && self::$config['slog_post']) {
            //默认调试$_POST数据 
            $__post=($_POST ? $_POST : file_get_contents("php://input"));
            slog('==================================POST======================================',$__post); 
        }
    }

    /**
     * 从多个URL中获取内容，任意一个成功即返回
     * @param array $urls URL数组
     * @param string $type 请求类型：'json' 或 'raw'
     * @return array ['success' => bool, 'data' => mixed, 'error' => string]
     */
    public static function getContentFromUrls($urls, $type = 'raw')
    {
        $result = array(
            'success' => false,
            'data' => null,
            'error' => ''
        );

        foreach ($urls as $url) {
            try {
                $content = @file_get_contents($url); 
                if ($content === false) {
                    continue;
                } 
                if ($type === 'json') {
                    $data = json_decode($content, true);
                    if ($data === null && json_last_error() !== JSON_ERROR_NONE) { 
                        continue;
                    }
                    $result['data'] = $data;
                } else {
                    $result['data'] = $content;
                }

                $result['success'] = true;
                return $result;
            } catch (Exception $e) {
                continue;
            }
        }

        $result['error'] = "所有URL都无法访问";
        return $result;
    }

    private static function formatMemory($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . $units[$pow];
    }

 
} 
 
if (isset($_GET['slog'])) {

    $slog_client_id = $_COOKIE['slog_client_id'] ?? uniqid();

    if (!isset($_COOKIE['slog_client_id']) || !$_COOKIE['slog_client_id']) {
        setcookie('slog_client_id', $slog_client_id, time() + 3600 * 24 * 30);
    }

    if (isset($_GET['save'])) {
        file_put_contents(SocketLog::$configFile, json_encode($_POST, JSON_UNESCAPED_UNICODE));
        die(json_encode(array('status' => 1)));
    }


    // 检查更新
    if (isset($_GET['check_update'])) {
        $response = array('status' => 0,'version'=>SocketLog::$version, 'need_update' => false, 'msg' => ''); 
        // 从多个检查URL获取版本信息
        $result = SocketLog::getContentFromUrls(SocketLog::$check_urls, 'json'); 

        if ($result && isset($result['data']) && isset($result['data']['version'])) {
            $latest_version = $result['data']['version'];
            if (version_compare($latest_version, SocketLog::$version, '>')) {
                $response['need_update'] = true;
                $response['msg'] = "发现新版本：" . $latest_version;
            } else {
                $response['msg'] = "当前已是最新版本";
            }
            $response['status'] = 1;
        } else {
            $response['msg'] = isset($result['error']) ? $result['error'] : '';
        }
        
        header('Content-Type: application/json');
        die(json_encode($response, JSON_UNESCAPED_UNICODE));
    }

    // 执行更新
    if (isset($_GET['update'])) {
        $response = array('status' => 0, 'msg' => '');
        
        // 从多个更新URL获取新版本内容
        $result = SocketLog::getContentFromUrls(SocketLog::$update_urls);
 
        if ($result['success']) {
            try {
                // 获取当前文件路径
                $current_file = __FILE__;
                
                // 先备份当前文件
                $backup_file = $current_file . '.bak.' . date('YmdHis');
 
                if (!copy($current_file, $backup_file)) {
                    throw new Exception("创建备份文件失败");
                }
                
                // 写入新内容
                if (file_put_contents($current_file, $result['data']) === false) {
                    throw new Exception("写入新文件失败");
                }
                
                $response['status'] = 1;
                $response['msg'] = "升级成功！原文件已备份为：" . basename($backup_file);
            } catch (Exception $e) {
                $response['msg'] = "升级失败：" . $e->getMessage();
                // 如果有备份文件，尝试恢复
                if (isset($backup_file) && file_exists($backup_file)) {
                    @copy($backup_file, $current_file);
                    @unlink($backup_file);
                }
            }
        } else {
            $response['msg'] = $result['error'];
        }
        
        header('Content-Type: application/json');
        die(json_encode($response, JSON_UNESCAPED_UNICODE));
    }
    $html=gzinflate(base64_decode('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'));
    die($html);
}


SocketLog::listen();
