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
    public static $version = '0.1.20250521'; // 当前版本号
    public static $update_urls = array(
        'https://z-y.site/works/Slog',
        'https://raw.githubusercontent.com/DDZH-DEV/works/refs/heads/master/socket-debug-tool/Slog.php'
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
