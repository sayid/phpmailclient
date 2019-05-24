<?php

namespace PhpMailClient\Drivers;

use GouuseCore\Helpers\OptionHelper;
use GouuseCore\Helpers\DateHelper;
use function Hprose\Future\run;
use PhpMailClient\Cache;
use PhpMailClient\Log;
use PhpMailClient\MailDecode;

class SocketImapLib
{
    var $pro = 'imap';
    var $is_ssl = false;
    var $strMessage        = '';
    var $intErrorNum    = 0;
    var $bolDebug        = false;

    var $strEmail        = '';
    var $strPasswd        = '';
    var $strHost        = '';
    var $intPort        = 110;
    var $intConnSecond    = 4;
    var $intBuffSize    = 8192;
    var $resHandler        = NULL;
    var $bolIsLogin        = false;
    var $strRequest        = '';
    var $strResponse    = '';
    var $arrRequest        = array();
    var $arrResponse    = array();
    var $__command_line = 0;
    private $_command_str = '';
    var $Select_Box = '';
    var $command_status = false;
    var $_execute_time = 0;
    var $cache_time = 180;
    public $exception;

    private $cachePrefix = '';
    private $OPTIMIZATION_DEBUG = false;
    //需要使用代理的邮箱
    public static $PROXY_MAIL = ['gmail.com', ];//, 'hotmail.com', 'outlook.com'
    private $socks5_host;
    private $socks5_port;
    private $socks5_username;
    private $socks5_password;

    private $CacheObj;

    private $MailDecodeObj;

    private $LogObj;

    function __construct() {
        $this->CacheObj = new Cache();
        $this->MailDecodeObj = new MailDecode();
        $this->LogObj = new Log();
    }
    //---------------
    // 基础操作
    //---------------
    //构造函数
    public static function getInstance($strLoginEmail, $strLoginPasswd, $strPopHost = '', $intPort = '', $is_ssl = false)
    {
        $obj = new self;
        $obj->cachePrefix = 'imap_' . $strLoginEmail;

        $obj->resHandler = NULL;
        $obj->__command_line = 0;
        $obj->strEmail = trim(strtolower($strLoginEmail));
        $obj->strPasswd = trim($strLoginPasswd);

        $obj->strHost = trim(strtolower($strPopHost));
        $obj->is_ssl = $is_ssl;

        if ($obj->strEmail == '' || $obj->strPasswd == '') {
            $obj->setMessage('Email address or Passwd is empty', 1001);
        }
        if (!PReg_match("/^[\w-]+(\.[\w-]+)*@[\w-]+(\.[\w-]+)+$/i", $obj->strEmail)) {
            $obj->setMessage('Email address invalid', 1002);
        }
        if ($obj->strHost == '') {
            $obj->strHost = substr(strrchr($obj->strEmail, "@"), 1);
        }
        if ($intPort != '') {
            $obj->intPort = $intPort;
        }
        if (!$obj->connectHost()) {
            //第一次连接失败 重连一次
            $obj->reconnect();
        }
        return $obj;
    }

    /**
     * 设置socket代理
     * @param string $host
     * @param int $port
     * @param string|null $username
     * @param string|null $password
     */
    public function setSocks5(string $host,int $port, string $username = null, string $password = null)
    {
        $this->socks5_host = $host;
        $this->socks5_port = $port;
        $this->socks5_username = $username;
        $this->socks5_password = $password;
    }

    //连接服务器
    function connectHost()
    {
        if ($this->bolDebug)
        {
            echo "Connection ".($this->is_ssl ? 'ssl://': '').$this->strHost." ...\r\n";
        }

        if (!$this->getIsConnect())
        {
            if ($this->strHost=='' || $this->intPort=='')
            {
                $this->setMessage('Imap host or Port is empty', 1003);
                return false;
            }
            //echo $this->strHost.', '.$this->intPort;
            $time_start = microtime_float();
            try {
                if (runInSwoole()) {
                    if ($this->is_ssl) {
                        //启用ssl
                        $this->resHandler = new \Swoole\Client(SWOOLE_SOCK_TCP | SWOOLE_SSL);
                    } else {
                        $this->resHandler = new \Swoole\Client(SWOOLE_SOCK_TCP);
                    }
                    if (!$this->resHandler ||  $this->resHandler->errCode) {
                        if (is_object($this->resHandler)) {
                            $this->resHandler->close();
                        }
                        return false;
                    }
                    $email_host = substr($this->strEmail, strpos($this->strEmail, "@")+1);
                    $client_setting = [
                        'package_max_length' => 2000000
                    ];
                    if (in_array($email_host, self::$PROXY_MAIL) && $this->socks5_host) {
                        //需要使用代理
                        $client_setting['socks5_host'] = $this->socks5_host;
                        $client_setting['socks5_port'] = $this->socks5_port;
                        if ($this->socks5_username) {
                            $client_setting['socks5_username'] = $this->socks5_username;
                            $client_setting['socks5_password'] = $this->socks5_password;
                        }
                    }
                    if ($client_setting) {
                        $this->resHandler->set($client_setting);
                    }

                    if (!$this->resHandler->connect($this->strHost, $this->intPort, $this->intConnSecond)) {
                        //连接失败 断开重连一次，再不行就退出
                        return false;
                    }

                    if ($this->is_ssl) {
                        $this->strHost = 'ssl://'.$this->strHost;
                    }
                } else {
                    if ($this->is_ssl) {
                        $this->strHost1 = 'ssl://' . $this->strHost;
                    } else {
                        $this->strHost1 = $this->strHost;
                    }
                    $this->resHandler = fsockopen($this->strHost1, $this->intPort, $this->intErrorNum, $this->strMessage, $this->intConnSecond);
                }
            } catch (\Exception $e) {
                $intErrNum = 2001;
                $this->setMessage($e->getMessage(), $intErrNum);
                return false;
            }
            if ($this->OPTIMIZATION_DEBUG == true) {
                $this->LogObj->optimization_list[] = "execute time:" . (microtime_float() - $time_start) . "connect imap ".$this->strHost;
            }
            if (!$this->resHandler)
            {
                $strErrMsg = 'Connection Imap host: '.$this->strHost.' failed';
                $intErrNum = 2001;
                $this->setMessage($strErrMsg, $intErrNum);
                return false;
            }
            if (!$this->getRestIsSucceed())
            {
                return false;
            }
        }
        return true;
    }

    public function reconnect() {
        $this->closeHost();
        $this->connectHost();
    }

    //关闭连接
    function closeHost()
    {
        if ($this->resHandler)
        {
            $this->_execute_time = microtime_float();
            if (is_swoole() && is_object($this->resHandler)) {
                $this->resHandler->close();
            } elseif (is_resource($this->resHandler)) {
                fclose($this->resHandler);
            }
            $this->_command_str = 'close host';
            $this->log();
        }
        $this->resHandler = null;
        unset($this->resHandler);
        return true;
    }

    //发送指令
    function sendCommand($strCommand)
    {
        if (!$this->getIsConnect()) {
            return false;
        }
        if (trim($strCommand) == '') {
            $this->setMessage('Request command is empty', 1004);
            return false;
        }
        $this->_command_str = $strCommand;
        $this->__command_line++;
        $strCommand = 'A' . $this->__command_line . ' ' . $strCommand;
        $this->strRequest = $strCommand . "\r\n";
        if (isset($this->debug) || $this->bolDebug) {
            echo "command:".$this->strRequest;
        }
        $this->arrRequest[] = $strCommand;
        if ($this->OPTIMIZATION_DEBUG == true) {
            $this->_execute_time = microtime_float();
        }
        try {
            if (runInSwoole()) {
                $status = $this->resHandler->send($this->strRequest);
                if (!$status) {
                    return false;
                }
            } else {
                $status = fputs($this->resHandler, $this->strRequest, strlen($this->strRequest));
            }
        } catch (\Exception $e) {
            $this->setMessage($e->getMessage(), 1004);
            return false;
        }
        return true;
    }

    //提取响应信息第一行
    function getLineResponse()
    {
        if (!$this->getIsConnect()) {
            return false;
        }
        if (runInSwoole()) {
            try {
                $this->strResponse = $this->resHandler->recv();
            } catch (\Exception $e) {
                $this->exception = $e->getMessage();
                $this->strResponse = false;
            }
            if ($this->strResponse === false) {
                $this->resHandler->errCode;
            }
        } else {
            $this->strResponse = fgets($this->resHandler, $this->intBuffSize);
        }
        if (isset($this->debug) || $this->bolDebug) {
            echo mb_convert_encoding($this->strResponse, 'utf-8', 'gbk');
        }
        return $this->strResponse;
    }

    function getsMore($bufferSize = 0)
    {
        if (!$this->getIsConnect()) {
            return false;
        }
        if (!$bufferSize) {
            $bufferSize = $this->intBuffSize;
        }

        if (runInSwoole()) {
            try {
                if ($this->resHandler->errCode) {
                    $this->setMessage($this->resHandler->errCode, $this->resHandler->errCode);
                    return false;
                } else {
                    $this->strResponse = $this->resHandler->recv();
                }
            } catch (\Exception $e) {
                $this->setMessage($e->getMessage(), $e->getMessage());
                return false;
            }
        } else {
            $this->strResponse = fread($this->resHandler, intval($bufferSize));
        }

        if (isset($this->debug) || $this->bolDebug) {
            echo mb_convert_encoding($this->strResponse, 'utf-8', 'gbk')."----";
        }
        return $this->strResponse;
    }

    /**
     *
     * @param unknown $intReturnType
     * @param number $intMail
     * @param string $mail_log
     * @param string $mail_uid
     * @param string $commend_status 传入变量 标记是否取完成
     * @return boolean|string|multitype:unknown Ambigous <boolean, string>
     */
    function getRespMessage()
    {
        $this->command_status = false;
        if (!$this->getIsConnect() || !$this->resHandler) {
            return false;
        }
        unset($this->debug);

        $strAllResponse = '';
        $last_tmp = '';//用于存储上一次缓冲内容
        while (1) {
            $strLineResponse = $this->getsMore($this->intBuffSize);
            if (!$strLineResponse) {
                break;
            }
            if (!is_swoole() && $strLineResponse === '') {
                $strLineResponse = $this->retryCommand();
            }

            $temp_line = $last_tmp . $strLineResponse;
            $temp_line = strtoupper(substr($temp_line, strrpos($temp_line, "\r\n", -4)));
            $last_tmp = $strLineResponse;//存入缓存 用于判断数据流是否接收完成
            if (isset($this->test)) {
                echo $strLineResponse;
                exit();
            }
            $checkResponse = $this->checkResponse($temp_line);

            if ($checkResponse === false) {
                return false;
            }

            $strAllResponse .= $strLineResponse;
            if ($checkResponse === 'is_end') {
                break;
            }
        }

        return $strAllResponse;
    }

    /**
     * 超时重新发送
     * @return bool|string
     */
    public function retryCommand()
    {
        $old_command = $this->_command_str;
        $this->reconnect();
        $this->login();
        if ($this->Select_Box) {
            $this->selectFolder();
        }
        $this->sendCommand($old_command);
        $strLineResponse = $this->getsMore($this->intBuffSize);
        return $strLineResponse;
    }

    private function checkResponse($temp_line)
    {
        if (preg_match("/A" . $this->__command_line . " NO(.*)\r\n$/Ui", $temp_line)
            || strpos($temp_line, "* BAD COMMAND!\r\n$") === 0
        ) {
            //出错了
            return false;
        }

        if (preg_match("/A" . $this->__command_line . " BAD(.*)\r\n$/Ui", $temp_line)) {  //参数错误
            $this->setMessage('BAD invalid command or parameters', 1004);
            return false;
        }

        if (preg_match("/A" . $this->__command_line . " BAD(.*)\r\n$/Ui", $temp_line)) {  //参数错误
            $this->setMessage('A2 BAD Select parameters!', 1004);
            return false;
        }

        if (preg_match("/A" . $this->__command_line . "(.*)COMPLETE\r\n$/iU", $temp_line)
            || preg_match("/A" . $this->__command_line . " OK(.*)\r\n$/iU", $temp_line)
        ) {
            return 'is_end';
        }
        return 0;
    }

    public function getContent($part_num, $struc, &$part_bodys)
    {
        if (isset($struc['subs'])) {
            if (isset($struc['subtype']) && $struc['subtype'] == 'rfc822' && !empty($part_bodys)) {
                $part_bodys[$part_num] = [
                    'type' => $struc['type'],
                    'subtype' => $struc['subtype'],
                    'charset' => $struc['attributes']['charset'],
                    'name' => $struc['attributes']['name'],
                    'encoding' => $struc['encoding'],
                    'size' => $struc['size'],
                    'is_refuse' => 1,
                    'struct' => $struc
                ];
            } else {
                foreach ($struc['subs'] as $key => $struc1) {
                    $this->getContent($key, $struc1, $part_bodys);
                }
            }
        } else {
            $results = [];
            foreach ($struc as $key => $value) {
                if (empty($value)) {
                    continue;
                }
                $results[$key] = $value;
            }
            $part_bodys[$part_num] = $results;
        }
    }


    /**
     * 获取正文
     * @param $folder
     * @param $intMail
     * @return Ambigous|array|bool
     */
    function getBody($folder, $intMail)
    {
        $cache_key = 'getBody' . md5($this->strEmail . $folder . $this->pro . $intMail);
        $data = $this->CacheObj->get($cache_key);
        if (!empty($data)) {
            return $data;
        }
        $this->part_bodys = [];
        if ($folder != $this->Select_Box) {
            $status = $this->selectFolder($folder);
            if ($status === false) {
                return $status;
            }
        }

        $this->sendCommand("uid fetch " . $intMail . " BODYSTRUCTURE");

        $strcucs = $this->getRespMessage();
        //修复outlook这个大坑
        $replace = "FETCH (UID ".$intMail." BODYSTRUCTURE";
        $strcucs = preg_replace("/FETCH \((.*?)BODYSTRUCTURE/", $replace, $strcucs);
        $this->log();
        $MailDecodeLib = new MailDecodeLib();
        $strcucs = $MailDecodeLib->getStructure($strcucs);

        $data = $this->decodeStruct($intMail, $folder, $strcucs);
        $status = $this->CacheObj->set($cache_key, $data, 3600);
        return $data;
    }


    public function decodeStruct($intMail, $folder, $strcucs)
    {
        $cache_key = md5($this->cachePrefix . $folder . $intMail);
        $body_part = '';
        $body_charset = '';
        $body_encoding = '';
        $attachs = [];

        $part_bodys = [];
        foreach ($strcucs as $key => $struc) {
            $this->getContent($key, $struc, $part_bodys);
        }

        $is_refuse = 0;
        $refuse_num = 0;
        $refuse_struct = [];
        /*
                var_dump($part_bodys);
                die();*/
        foreach ($part_bodys as $key => $new_struc) {
            $old_key = $key;
            if (strpos($key, "0.") === 0) {
                $key = substr($key, 2);
            }
            //是否是退信
            if (isset($new_struc['is_refuse'])) {
                $is_refuse = 1;
                $refuse_struct = $new_struc['struct'];
            } elseif (isset($new_struc['subtype']) && $new_struc['subtype'] == 'rfc822') {
                $is_refuse = 1;
                $refuse_num = $key;
            }
            if ((isset($new_struc['utf-8']) && $new_struc['utf-8'] === 'filename')
                || isset($new_struc['filename'])
                || isset($new_struc['name'])
                || $is_refuse
                || isset($new_struc['attributes']['name'])
                ||(isset($new_struc['file_attributes']) && !in_array($new_struc['type'], ['text', 'image']))
            ) {
                //附件
                $new_struc['part_num'] = isset($new_struc['is_refuse']) && $new_struc['is_refuse'] == 1 ? $old_key : $key;
                if (!isset($new_struc['name'])) {
                    if(isset($new_struc['filename'])) {
                        $new_struc['name'] = $new_struc['filename'];
                    } elseif (isset($new_struc['attributes']['name'])) {
                        $new_struc['name'] = $new_struc['attributes']['name'];
                    } elseif (isset($new_struc['file_attributes']['attachment'][3])) {
                        $new_struc['name'] = $new_struc['file_attributes']['attachment'][3];
                        unset($new_struc['file_attributes']);
                    } elseif (isset($new_struc['file_attributes']['attachment'][1])) {
                        $new_struc['name'] = $new_struc['file_attributes']['attachment'][1];
                        unset($new_struc['file_attributes']);
                    } else {
                        $new_struc['name'] = $is_refuse ? 'att.eml' : md5($new_struc['part_num']);
                    }
                    $new_struc['name'] = $this->filenameDecode($new_struc['name']);
                }
                if(!isset($new_struc['size']) || is_array($new_struc['size'])) {
                    $new_struc['size'] = 0;
                }
                $attachs[] = $new_struc;
            } elseif ($new_struc['type'] === 'text' && $new_struc['subtype'] === 'html') {
                //正文
                $body_part = $key;
                if(isset($new_struc['charset'])) {
                    $body_charset = strtolower($new_struc['charset']);
                } elseif (isset($new_struc['attributes']['charset'])) {
                    $body_charset = strtolower($new_struc['attributes']['charset']);
                }

                $body_encoding = strtolower($new_struc['encoding'] ?? '');
            }
        }

        $content = '';
        if ($body_part) {
            $content = $this->getBodyPart($intMail, $body_part, $body_encoding, $body_charset);
        }
        $refuse_content = '';
        if ($is_refuse) {
            if(!empty($refuse_struct)) {
                $refuse_content = $this->decodeStruct($intMail, $folder, [$refuse_struct])['content'] ?? '';
            }
        }
        $data = ['content' => $content, 'refuse_content' => $refuse_content, 'attach' => $attachs, 'is_refuse' => $is_refuse];

        $status = $this->CacheObj->set($cache_key, $data, 86400);
        return $data;
    }

    public function filenameDecode($filename) {
        $arrStr = explode('?', $filename);
        if (isset($arrStr[1]) && in_array($arrStr[1], mb_list_encodings())) {
            switch (strtolower($arrStr[2])) {
                case 'b': //base64 encoded
                    $filename = base64_decode($arrStr[3]);
                    break;

                case 'q': //quoted printable encoded
                    $filename = quoted_printable_decode($arrStr[3]);
                    break;
            }
        }
        return $filename;
    }

    public function getPartContent($folder, $mail_uid, $part_num)
    {
        if ($folder != $this->Select_Box) {
            $status = $this->selectFolder($folder);
            if ($status === false) {
                return $status;
            }
        }

        $this->sendCommand("uid fetch " . $mail_uid . " BODY[" . $part_num . "]");

        $last_tmp = '';//用于存储上一次缓冲内容
        $content = '';
        while (1) {
            $strLineResponse = $this->getsMore($this->intBuffSize);
            if (empty($last_tmp)) {
                //第一次
                $strLineResponse = substr($strLineResponse, strpos($strLineResponse, "\r\n") + 2);
            }
            $temp_line = $last_tmp . $strLineResponse;
            $temp_line = strtoupper(substr($temp_line, strrpos($temp_line, "\r\n", -4)));
            $last_tmp = $strLineResponse;//存入缓存 用于判断数据流是否接收完成
            $checkResponse = $this->checkResponse($temp_line);
            if ($checkResponse === false) {
                return false;
            }
            if ($checkResponse === 'is_end') {
                $content .= $strLineResponse;
                break;
            }
            $content .= $strLineResponse;
        }

        return $content;
    }

    /**
     * 从body中获取部分
     * @param $intMail
     * @param $body_part
     * @param string $body_encoding
     * @param string $body_charset
     * @return multitype|bool|mixed|string
     */
    public function getBodyPart($intMail, $body_part, $body_encoding = '', $body_charset = '', $folder = '')
    {
        if ($folder) {
            $status = $this->selectFolder($folder);
            if ($status === false) {
                return $status;
            }
        }
        $this->sendCommand("uid fetch " . $intMail . " BODY[" . $body_part . "]");
        $content = $this->getRespMessage();
        if(!empty($content)) {
            //修复outlook
            $content = preg_replace("/\r\n.*?\)\r\n/", "\r\n)\r\n", $content);
        }
        $this->log();
        $content = substr($content, strpos($content, "\r\n") + 2);
        $content = substr($content, 0, stripos($content, ")\r\n"));

        if ($body_encoding) {
            if ($body_encoding == 'quoted-printable') {
                $content = quoted_printable_decode($content);
            } elseif ($body_encoding == 'base64') {
                $content = base64_decode($content);
            }
        }
        if ($body_charset) {
            $content = mb_convert_encoding($content, 'UTF-8', $body_charset);
        }
        return $content;
    }

    /**
     *
     * @param unknown $intReturnType
     * @param number $intMail
     * @param string $mail_log
     * @param string $mail_uid
     * @param string $commend_status 传入变量 标记是否取完成
     * @return boolean|string|multitype:unknown Ambigous <boolean, string>
     */
    function download($folder, $intMail, $mail_log)
    {
        $this->command_status = false;
        if (!$this->getIsConnect() || !$this->resHandler) {
            return false;
        }
        if ($folder != $this->Select_Box) {
            $status = $this->selectFolder($folder);
            if ($status === false) {
                return $status;
            }
        }
        unset($this->debug);
        if ($mail_log) {
            @unlink($mail_log);
            // if (!is_swoole()) {
            $handle_w = fopen($mail_log, "w");
            //}
        }

        $this->sendCommand("uid fetch " . $intMail . " RFC822");

        $is_over = false;
        $last_tmp = '';//用于存储上一次缓冲内容
        $allLines = [];
        while (1) {
            $strLineResponse = $this->getsMore();
            $temp_line = $last_tmp . $strLineResponse;
            $temp_line = strtoupper(substr($temp_line, strrpos($temp_line, "\r\n", -4)));
            $last_tmp = $strLineResponse;//存入缓存 用于判断数据流是否接收完成
            if (preg_match("/A" . $this->__command_line . "(.*)COMPLETE(.*)\r\n$/iU", $temp_line)) {
                $this->command_status = true;
                $is_over = true;
            } elseif (preg_match("/A" . $this->__command_line . " OK(.*)COMPLETED(.*)\r\n/iU", $temp_line)) {
                $is_over = true;
            }
            if ($is_over) {
                $strLineResponse = substr($strLineResponse, 0, strrpos($strLineResponse, "A" . $this->__command_line));
            }
            $allLines[] = $strLineResponse;
            if ($mail_log) {
                $s = fwrite($handle_w, $strLineResponse);
            }
            if ($is_over) {
                //结束
                $this->command_status = true;
                break;
            }
        }
        if ($mail_log) {
            fclose($handle_w);
        }
        $this->log();
        return $allLines;
    }

    //提取请求是否成功
    function getRestIsSucceed($strRespMessage = '')
    {
        if (trim($strRespMessage) == '') {
            if ($this->strResponse == '') {
                $this->getLineResponse();
            }
            $strRespMessage = $this->strResponse;
        }
        if (trim($strRespMessage) == '') {
            $this->setMessage('Response message is empty', 2003);
            return false;
        }
        $strRespMessage = strtoupper($strRespMessage);
        if (stripos($strRespMessage, "A".$this->__command_line." OK")===0
            || stripos($strRespMessage, "\r\nA".$this->__command_line." OK")!==false//修复yahoo邮箱
        )
        {

        } elseif (stripos($strRespMessage, "*  BAD")===0) {
            return false;
        } elseif (strpos($strRespMessage, "* BAD")===0) {
            return false;
        } elseif (strpos($strRespMessage, "* NO")===0) {
            return false;
        } elseif (stripos($strRespMessage, "*")===0) {

        } elseif (stripos($strRespMessage, "A".$this->__command_line." NO")===0
            && (stripos($strRespMessage, 'login') || stripos($strRespMessage, 'password')) ) {
            //密码错误
            $this->setMessage($strRespMessage, 2005);
            return false;
        } else {
            $this->setMessage($strRespMessage, 2000);
            return false;
        }
        return true;
    }
    //获取是否已连接
    function getIsConnect()
    {
        if (is_swoole())
        {
            if (!isset($this->resHandler) || !is_object($this->resHandler) || $this->resHandler->errCode) {
                if (isset($this->resHandler->errCode)) {
                    $msg = socket_strerror($this->resHandler->errCode);
                } else {
                    $msg = "Nonexistent availability connection handler";
                }
                if ($this->getMessage()) {
                    $msg = $this->getMessage();
                }
                $this->setMessage($msg, 2002);
                return false;
            }
        } elseif (!isset($this->resHandler) || !$this->resHandler) {
            if ($this->getMessage()) {
                $msg = $this->getMessage();
            } else {
                $msg = "Nonexistent availability connection handler";
            }

            $this->setMessage($msg, 2002);
            return false;
        }
        return true;
    }

    //设置消息
    function setMessage($strMessage, $intErrorNum)
    {
        if (trim($strMessage) == '' || $intErrorNum == '') {
            return false;
        }
        $this->strMessage = $strMessage;
        $this->intErrorNum = $intErrorNum;
        return true;
    }

    //获取消息
    function getMessage()
    {
        return $this->strMessage;
    }

    //获取错误号
    public function getErrorNum()
    {
        return $this->intErrorNum;
    }

    //获取请求信息
    function getRequest()
    {
        return $this->strRequest;
    }

    //获取响应信息
    function getResponse()
    {
        return $this->strResponse;
    }

    private function log()
    {
        if ($this->OPTIMIZATION_DEBUG == true) {
            $this->LogObj->optimization_list[] = "execute time:" . sprintf("%.5f", DateHelper::microtime_float() - $this->_execute_time) . $this->_command_str;
        }
    }

    //---------------
    // 邮件原子操作
    //---------------
    //登录邮箱
    function login()
    {
        $bool = $this->innerLogin();
        if($bool !== true) {
            return $this->innerLogin();
        }
        return $bool;
    }

    public function innerLogin() {
        if (!$this->getIsConnect()) {
            return false;
        }
        if (!$this->sendCommand("LOGIN " . $this->strEmail . " " . $this->strPasswd)) {
            $this->sendCommand("LOGIN " . $this->strEmail . " " . $this->strPasswd);
        }

        $strResponse = $this->getsMore();
        if (empty($strResponse)) {
            return -4;
        }
        $strResponse = mb_convert_encoding($strResponse, 'utf-8', 'gbk');
        $this->log();
        if (preg_match("/授权码/", $strResponse)) {
            //请使用授权码
            $this->setMessage($strResponse, 2004);
            return -1;
        } else if (preg_match("/ssl/", $strResponse)) {
            //请使用ssl
            $this->setMessage($strResponse, 2004);
            return -2;
        } else if (preg_match("/pop3/", $strResponse)) {
            //服务器未开启pop3
            $this->setMessage($strResponse, 2004);
            return -3;
        } else if (preg_match("/suspended/", $strResponse)) {
            //服务器停了该协议
            $this->setMessage($strResponse, 2004);
            return -3;
        }

        $bolUserRight = $this->getRestIsSucceed($strResponse);
        if (!$bolUserRight) {
            if($this->intErrorNum) {
                $this->setMessage($strResponse, $this->intErrorNum);
            }
            $this->bolIsLogin = false;
            return false;
        }
        $this->bolIsLogin = true;
        return true;
    }

    //退出登录
    function logout()
    {
        if (!$this->getIsConnect() && $this->bolIsLogin) {
            return false;
        }
        if ($this->resHandler) {
            $this->sendCommand("LOGOUT");
            $this->log();
        }
        return true;
    }

    //获取是否在线
    function getIsOnline()
    {
        if (!$this->getIsConnect() && $this->bolIsLogin) {
            return false;
        }
        $this->sendCommand("NOOP");
        $strResponse = $this->getLineResponse();
        if (!$this->getRestIsSucceed($strResponse)) {
            return false;
        }
        return true;
    }

    //获取邮件数量和字节数(返回数组)
    function getMailSum($folder)
    {
        if (!$this->getIsConnect() && $this->bolIsLogin) {
            return false;
        }
        return $this->selectFolder($folder);
    }

    //获取指定邮件得session Id
    function getMailSessId($intMailId, $intReturnType = 2)
    {
        if (!$this->getIsConnect() && $this->bolIsLogin) {
            return false;
        }
        if (!$intMailId = intval($intMailId)) {
            $this->setMessage('Mail message id invalid', 1005);
            return false;
        }
        $this->sendCommand("UIDL " . $intMailId);
        $strResponse = $this->getLineResponse();
        if (!$this->getRestIsSucceed($strResponse)) {
            return false;
        }
        if ($intReturnType == 1) {
            return $strResponse;
        } else {
            $arrResponse = explode(" ", $strResponse);
            if (!is_array($arrResponse) || count($arrResponse) <= 0) {
                $this->setMessage('UIDL command response message is error', 2006);
                return false;
            }
            return array($arrResponse[1], $arrResponse[2]);
        }
    }

    //取得某个邮件的大小
    function getMailSize($intMailId)
    {
        if (!$this->getIsConnect() && $this->bolIsLogin) {
            return false;
        }

        if (empty($this->Select_Box)) {
            $this->selectFolder();
        }

        $this->sendCommand("FETCH " . $intMailId . " RFC822.SIZE");
        $line = $this->getLineResponse();
        $line1 = $this->getLineResponse();
        if (preg_match("/FETCH \(UID (\d+) RFC822.SIZE (\d+)\)/", $line, $match)) {
            return $match[2];
        }
        return false;
    }


    public static function folderCacheKey($email)
    {
        return $email . 'imap' . 'getFolderList';
    }

    /**
     * 获取所有文件夹
     * @return array|bool
     */
    function getFolderList($cache = false)
    {
        if (!$this->getIsConnect() && $this->bolIsLogin) {
            return false;
        }
        $cache_key = self::folderCacheKey($this->strEmail);
        if ($cache == true) {
            $results = $this->CacheObj->get($cache_key);
            if (!empty($results)) {
                return $results;
            }
        }

        $this->Select_Box = '';
        $this->sendCommand('LIST "" *');
        //$this->sendCommand('LIST (HasNoChildren) "/" ');
        $response = $this->getRespMessage();
        preg_match_all("/\"\/\" (.*?)\r\n/i", $response, $matchs);
        $all_folders = empty($matchs[1]) ? [] : array_map(function($v) {
            return trim($v, '"');
        }, $matchs[1]);

        $results = [];
        $folder_types = [];
        $scrm_sent_created = false;

        if(empty($all_folders)) {
            return [];
        }

        foreach ($all_folders as $key => $folder) {
            $is_diy = $this->obj->ThirdMailLib->isDiyFolder($this->strEmail, $folder);
            $foler_name = mb_convert_encoding($folder, "UTF-8", "UTF7-IMAP");
            if ($foler_name == '[Gmail]/已加星标' || $foler_name == '[Gmail]'  || $foler_name == '[Gmail]/所有邮件' || $foler_name == '[Gmail]/重要') {
                //gmail邮箱 不显示星标 所有邮件
                continue;
            }
            $results[$folder] = ['folder' => $folder, 'folder_name' => $foler_name];
            $trans_folder_name = $this->obj->MailDecodeLib->folderToName($foler_name);
            if (!in_array($trans_folder_name, $folder_types)) {
                $folder_types[] = $trans_folder_name;
                $results[$folder]['folder_name'] = $trans_folder_name;
                $results[$folder]['folder_type'] = $this->MailDecodeObj->folderType($trans_folder_name);
            } else {
                $results[$folder]['folder_type'] = "";
            }
        }

        $this->CacheObj->set($cache_key, $results, 3600);
        $this->log();
        return $results;
    }

    /**
     * only for imap
     * @param string $folder
     * @param number $intReturnType
     * @return boolean|Ambigous <boolean, multitype:boolean , string>
     */
    function selectFolder($folder = 'INBOX')
    {
        if (!$this->getIsConnect() || !$this->bolIsLogin) {
            return false;
        }
        if (strpos($folder, " ")) {
            $folder = '"' . $folder . '"';
        }
        $this->sendCommand('Select ' . $folder);
        $this->Select_Box = $folder;
        //$this->test = true;
        $strResponse = $this->getRespMessage();
        $this->log();
        if (preg_match("/\* (.*) EXISTS/iU", $strResponse, $match)) {
            return $match[1];
        }
        return false;
    }


    //获取邮件基本列表数组
    function getUidList($folder, $check_cache = false, $after = 0)
    {
        if (!$this->getIsConnect() && $this->bolIsLogin) {
            return false;
        }
        if ($folder != $this->Select_Box) {
            $select_result = $this->selectFolder($folder);
            if ($select_result === false) {
                //收取失败
                return $select_result;
            }
        }
        $group_key = 'mail_uid' . md5($this->strEmail . $this->Select_Box . $this->pro);
        $redis_key = $group_key . "all" . $after;
        if ($check_cache && $data = $this->CacheObj->get($redis_key)) {
            return $data;
        }

        if (empty($after)) {
            $after = strtotime("-3 months");
        }
        $this->sendCommand("uid Search SINCE " . date('d-M-Y', $after));
        $data = $this->getRespMessage();
        $data = substr($data, 0, strpos($data, "\r\nA" . $this->__command_line));
        $data = explode(" ", $data);
        $data = array_slice($data, 2);
        if ($data && end($data) == "") {
            array_pop($data);
        }
        $results = [];
        foreach ($data as $row) {
            if (!intval($row)) {
                continue;
            }
            $results[$row] = ['uid' => $row];
        }
        if ($check_cache) {
            $this->CacheObj->saveWithKey($group_key, $redis_key, $results, $this->cache_time);
        }
        $this->log();
        return $results;
    }

    function getUidByMark($folder, $mark_type = 'unseen', $check_cache = false)
    {
        if (!$this->getIsConnect() && $this->bolIsLogin) {
            return false;
        }
        $group_key = 'mail_uid' . md5($this->strEmail . $folder . $this->pro);
        $redis_key = $group_key . "getUidByMark" . $mark_type;
        if ($check_cache && $data = $this->CacheObj->get($redis_key)) {
            return $data;
        }
        if ($folder != $this->Select_Box) {
            $select_result = $this->selectFolder($folder);
            if ($select_result === false) {
                //收取失败
                return $select_result;
            }
        }
        /*if ($mark_type == 'unseen') {
            $after = strtotime("-3 months");
            $mark_type = $mark_type .  " SINCE ".date('d-M-Y', $after);
		}*/
        $this->sendCommand("uid Search " . $mark_type);
        $data = $this->getRespMessage();

        $data = substr($data, 0, strpos($data, "\r\nA" . $this->__command_line));
        $data = explode(" ", $data);
        $data = array_slice($data, 2);

        if ($data && end($data) == "") {
            array_pop($data);
        }

        $this->log();
        //设置过期时间
        if ($check_cache) {
            $this->CacheObj->saveWithKey($group_key, $redis_key, $data, $this->cache_time);
        }
        return $data;
    }

    public function getUidIndex($uid)
    {
        $redis_key = 'mail_uid' . md5($this->strEmail . $this->Select_Box . $this->pro);
        $data = $this->CacheObj->get($redis_key);
        return array_search($data, $uid);
    }

    /**
     * 清理文件夹缓存
     * @param $folder
     */
    public function clearCache($folder)
    {
        $redis_key = 'mail_uid' . md5($this->strEmail . $folder . $this->pro);
        $this->CacheObj->delWithKey($redis_key);
    }

    /**
     * 得到邮件uid 已读未读，只有imap支持此扩展
     * @param unknown $intMailId
     * @return Ambigous <boolean, multitype:unknown boolean , string>
     */
    function getMailIsUid($intMailId)
    {
        if (empty($this->Select_Box)) {
            $this->selectFolder();
        }
        $this->sendCommand("FETCH " . $intMailId . " INTERNALDATE");
        $line = $this->getLineResponse();
        $line1 = $this->getLineResponse();
        //	echo $line."\n";
        //	echo $line1."\n";
        if (preg_match("/FETCH \(UID (\d+) INTERNALDATE \"(.*)\"/", $line, $match)) {
            if ($start = strpos($match[2], '(')) {
                //过滤 Date: Tue, 28 Jun 2016 17:30:34 +0800 (GMT+08:00) 格式
                $match[2] = substr($match[2], 0, $start);
            }

            if (isset($match[1])) {
                $uid = $match[1];
                return array(md5(str_replace(array("\n", "\r", " "), "", $uid)), strtotime($match[2]));
            }
        }
        return array(0, 0);
    }

    //获取某邮件前指定行, $intReturnType 返回值类型，1是字符串，2是数组
    function getMailTopMessage($intMailId, $intReturnType = 1)
    {

        if (!$this->getIsConnect() && $this->bolIsLogin) {
            return false;
        }

        if (empty($this->Select_Box)) {
            $this->selectFolder();
        }
        $this->sendCommand("uid FETCH " . $intMailId . " BODY.PEEK[HEADER]");
        //$this->test = 1;
        $data = $this->getRespMessage();
        $this->log();
        if (strlen($data) < 100) {
            return false;
        }
        return $data;
    }


    /**
     * 彻底删除邮件
     * @param $folder
     * @param $intMailId
     * @return bool
     */
    function realDelMail($folder)
    {
        if (!$this->getIsConnect() && $this->bolIsLogin) {
            return false;
        }

        if ($folder != $this->Select_Box) {
            $status = $this->selectFolder($folder);
            if ($status === false) {
                return $status;
            }
        }
        $this->sendCommand("EXPUNGE");
        $this->getLineResponse();
        $this->log();
        if (!$this->getRestIsSucceed()) {
            return false;
        }
        return true;
    }

    /**
     * 移动到新的邮箱
     * @param $folder
     * @param $intMailId
     * @param $to_folder
     * @return bool
     */
    function moveMail($folder, $mail_uids, $to_folder)
    {
        if (!$this->getIsConnect() && $this->bolIsLogin) {
            return false;
        }
        /*if (!$intMailId=intval($intMailId))
        {
            $this->setMessage('Mail message id invalid', 1005);
            return false;
        }*/
        if ($folder != $this->Select_Box) {
            $status = $this->selectFolder($folder);
            if ($status === false) {
                return $status;
            }
        }
        if (strpos($to_folder, " ")) {
            $to_folder = '"' . $to_folder . '"';
        }
        $this->sendCommand("uid COPY " . $mail_uids . " " . $to_folder);
        $str = $this->getsMore();
        if (!$this->getRestIsSucceed($str)) {
            return false;
        }
        $this->log();
        //将原邮件标记删除
        $this->sendCommand("uid store " . $mail_uids . " +FLAGS (\Deleted)");
        $str = $this->getsMore();
        if (!$this->getRestIsSucceed($str)) {
            return false;
        }
        $this->log();
        //彻底删除原邮件
        $this->sendCommand("EXPUNGE ");
        $str = $this->getsMore();
        if (!$this->getRestIsSucceed($str)) {
            return false;
        }
        $this->log();
        return true;
    }

    /**
     * 标记邮件
     * @param $mailUid
     * @param $flags + - \Answered \Flagged \Deleted \Draft \Seen
     * @return bool
     */
    public function store($folder, $mailUids, $flags)
    {
        if (!$this->getIsConnect() && $this->bolIsLogin) {
            return false;
        }
        if ($folder != $this->Select_Box) {
            $status = $this->selectFolder($folder);
            if ($status === false) {
                return $status;
            }
        }
        $this->sendCommand("uid store " . $mailUids . " " . $flags);
        //$this->test = 1;
        $line = $this->getRespMessage();
        $this->log();
        if (!$this->getRestIsSucceed()) {
            return false;
        }
        return true;
    }


    function delFolder($folder)
    {
        if (!$this->getIsConnect() && $this->bolIsLogin) {
            return false;
        }
        $this->CacheObj->delete($this->strEmail . $this->pro . 'getFolderList');
        $this->sendCommand("DELETE " . $folder);
        $this->getLineResponse();
        if (!$this->getRestIsSucceed()) {
            return false;
        }
        return true;
    }

    function createFolder($folder)
    {
        if (!$this->getIsConnect() && $this->bolIsLogin) {
            return false;
        }
        $this->CacheObj->delete($this->strEmail . $this->pro . 'getFolderList');
        $this->sendCommand("CREATE " . $folder);
        $this->getLineResponse();
        $this->log();
        if (!$this->getRestIsSucceed()) {
            return false;
        }
        return true;
    }

    function closeFolder()
    {
        if (!$this->getIsConnect() && $this->bolIsLogin) {
            return false;
        }
        $this->sendCommand("CLOSE ");
        $this->getLineResponse();
        $this->log();
        if (!$this->getRestIsSucceed()) {
            return false;
        }
        return true;
    }


    public function clearFolder($folder)
    {
        if ($folder != $this->Select_Box) {
            $status = $this->selectFolder($folder);
            if ($status === false) {
                return $status;
            }
        }
        //将原邮件标记删除
        $this->sendCommand("store 1:* +FLAGS (\Deleted)");
        $this->sendCommand("EXPUNGE");
        $this->getLineResponse();
        $str = $this->getsMore();
        if (!$this->getRestIsSucceed($str)) {
            return false;
        }
        $this->log();
    }

    function renameFolder($oldfolder, $new_folder)
    {
        if (!$this->getIsConnect() && $this->bolIsLogin) {
            return false;
        }
        $this->CacheObj->delete($this->strEmail . $this->pro . 'getFolderList');
        $this->sendCommand("RENAME " . $oldfolder . " " . $new_folder);
        $this->getLineResponse();
        if (!$this->getRestIsSucceed()) {
            return false;
        }
        return true;
    }

    //重置被删除得邮件标记为未删除
    function resetDeleMail()
    {
        if (!$this->getIsConnect() && $this->bolIsLogin) {
            return false;
        }
        $this->sendCommand("RSET");
        $this->getLineResponse();
        if (!$this->getRestIsSucceed()) {
            return false;
        }
        return true;
    }

    public function getPart($folder, $mail_uid, $part_num, $save_path = '', $body = null)
    {
        if ($folder != $this->Select_Box) {
            $status = $this->selectFolder($folder);
            if ($status === false) {
                return $status;
            }
        }

        if (!$body) {
            $body = $this->getBody($folder, $mail_uid);
        }

        $body_encoding = '';
        $body_charset = '';
        $return_attach = null;
        foreach ($body['attach'] as $attach) {
            if ($attach['part_num'] == $part_num) {
                $body_encoding = strtolower($attach['encoding'] ?? '');
                $body_charset = strtolower($attach['charset'] ?? '');
                $return_attach = $attach;
                break;
            }
        }

        if (empty($body_encoding) && !$body['is_refuse']) {
            //邮件已删除
            return false;
        }
        $this->sendCommand("uid fetch " . $mail_uid . " BODY[" . $part_num . "]");
        $has_encode = 0;

        if ($save_path) {
            if (is_file($save_path)) {
                @unlink($save_path);
            }
            $fp = fopen($save_path, "w+");
            if ($body_encoding) {
                if ($body_encoding == 'quoted-printable') {
                    $has_encode = 1;
                    stream_filter_append($fp, "convert.quoted-printable-decode", STREAM_FILTER_WRITE);
                } elseif ($body_encoding == 'base64') {
                    $has_encode = 1;
                    stream_filter_append($fp, "convert.base64-decode", STREAM_FILTER_WRITE);
                }
            }
        }
        $last_tmp = '';//用于存储上一次缓冲内容
        while (1) {
            /**
             * 2019.1.10 : 人生就像outlook，你永远猜不透他下次返回的报文是什么，祝好！
             */
            $strLineResponse = $this->getsMore($this->intBuffSize);
            //var_dump($strLineResponse);
            if($has_encode) {
                if(!empty($strLineResponse)) {
                    //修复outlook
                    $strLineResponse = preg_replace("/\r\n.*?\)\r\n/", "\r\n)\r\n", $strLineResponse);
                    //修复yahoo
                }

                if (empty($last_tmp)) {
                    $strLineResponse = preg_replace("/.*?FETCH \($/", '', $strLineResponse);
                    if(empty($strLineResponse)) {
                        continue;
                    }
                    //第一次
                    $strLineResponse = substr($strLineResponse, strpos($strLineResponse, "\r\n") + 2);
                }
            }

            $temp_line = $last_tmp . $strLineResponse;
            $temp_line = strtoupper(substr($temp_line, strrpos($temp_line, "\r\n", -4)));
            $last_tmp = $strLineResponse;//存入缓存 用于判断数据流是否接收完成
            if (isset($this->test)) {
                echo $strLineResponse;
                exit();
            }
            $checkResponse = $this->checkResponse($temp_line);
            if ($checkResponse === false) {
                return false;
            }
            if ($checkResponse === 'is_end') {
                if($has_encode) {
                    $strLineResponse = substr($strLineResponse, 0, stripos($strLineResponse, ")\r\n"));
                    while (1) {
                        if (substr($strLineResponse, strlen($strLineResponse) - 4) == "\r\n\r\n") {
                            $strLineResponse = substr($strLineResponse, 0, strlen($strLineResponse) - 2);
                        } else {
                            break;
                        }
                    }
                    //strpos用于修复outlook
                    if (isset($fp) && strpos($strLineResponse, ' ') === false) {
                        fwrite($fp, $strLineResponse);
                    }
                } else {
                    if (isset($fp)) {
                        fwrite($fp, $strLineResponse);
                    }
                }
                break;
            }
            if (isset($fp)) {
                fwrite($fp, $strLineResponse);
            }
        }

        if ($save_path) {
            fclose($fp);
        }

        $this->log();
        return $return_attach;
    }


    //输出错误信息
    function printError()
    {
        echo "[Error Msg] : $this->strMessage     <br>\n";
        echo "[Error Num] : $this->intErrorNum <br>\n";
    }

    //输出主机信息
    function printHost()
    {
        echo "[Host]  : $this->strHost <br>\n";
        echo "[Port]  : $this->intPort <br>\n";
        echo "[Email] : $this->strEmail <br>\n";
        echo "[Passwd] : ******** <br>\n";
    }

    //输出连接信息
    function printConnect()
    {
        echo "[Connect] : $this->resHandler <br>\n";
        echo "[Request] : $this->strRequest <br>\n";
        echo "[Response] : $this->strResponse <br>\n";
    }

    public function __destruct()
    {
        if (isset($this->resHandler) && $this->resHandler) {
            $this->logout();
            $this->closeHost();
        }
    }
}

?>

