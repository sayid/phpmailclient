<?php

namespace App\Libraries;

use GouuseCore\Helpers\OptionHelper;
use GouuseCore\Helpers\DateHelper;

class MailLib
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
	var $intConnSecond    = 120;
	var $intBuffSize    = 51200;
	var $resHandler        = NULL;
	var $bolIsLogin        = false;
	var $strRequest        = '';
	var $strResponse    = '';
	var $arrRequest        = array();
	var $arrResponse    = array();
	var $__command_line = 0;
	private $_command_str = '';
	var $Select_Box = '';
	var $total_mail = 0;
	var $command_status = false;
	var $_execute_time = 0;

	private $_excute_logs = [];
	
	function __construct($pro_type = 'imap') {
		$this->pro = $pro_type;
	}
	//---------------
	// 基础操作
	//---------------
	//构造函数
	public function getInstance($strLoginEmail, $strLoginPasswd, $strPopHost='', $intPort='', $is_ssl=false)
	{
		
		$this->resHandler = NULL;
		$this->__command_line = 0;
		$this->strEmail        = trim(strtolower($strLoginEmail));
		$this->strPasswd    = trim($strLoginPasswd);
		
		$this->strHost        = trim(strtolower($strPopHost));
		$this->is_ssl = $is_ssl;
		if ($this->is_ssl) {
			$this->strHost = 'ssl://'.$this->strHost;
		}
		if ($this->strEmail=='' || $this->strPasswd=='')
		{
			$this->setMessage('Email address or Passwd is empty', 1001);
		}
		if (!PReg_match("/^[\w-]+(\.[\w-]+)*@[\w-]+(\.[\w-]+)+$/i", $this->strEmail))
		{
			$this->setMessage('Email address invalid', 1002);
		}
		if ($this->strHost=='')
		{
			$this->strHost = substr(strrchr($this->strEmail, "@"), 1);
		}
		if ($intPort!='')
		{
			$this->intPort = $intPort;
		}
		$this->connectHost();
		return $this;
		
	}

	//连接服务器
	function connectHost()
	{
		if ($this->bolDebug)
		{
			echo "Connection ".$this->strHost." ...\r\n";
		}
		if (!$this->getIsConnect())
		{
			if ($this->strHost=='' || $this->intPort=='')
			{
				$this->setMessage('Imap host or Port is empty', 1003);
				return false;
			}
			//echo $this->strHost.', '.$this->intPort;
            $time_start = DateHelper::microtime_float();
			try {
                $this->resHandler = fsockopen($this->strHost, $this->intPort, $this->intErrorNum, $this->strMessage, $this->intConnSecond);
            } catch (\Exception $e) {
                $strErrMsg = 'Connection Imap host: '.$this->strHost.' failed';
                $intErrNum = 2001;
                $this->setMessage($strErrMsg, $intErrNum);
                return false;
			}
            if (env('OPTIMIZATION_DEBUG') == true) {
                $this->obj->LogLib->optimization_list[] = "execute time:" . (DateHelper::microtime_float() - $time_start) . "connect imap";
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
	//关闭连接
	function closeHost()
	{
		if ($this->resHandler)
		{
			@fclose($this->resHandler);
		}
		return true;
	}
	//发送指令
	function sendCommand($strCommand, $exit = false)
	{
		if (isset($this->debug) || $this->bolDebug)
		{
			if (!preg_match("/PASS/", $strCommand))
			{
				echo "Send Command: ".$strCommand."\r\n";
			}
			else
			{
				echo "Send Command: PASS ******\r\n";
			}
		}
		if (!$this->getIsConnect())
		{
			return false;
		}
		if (trim($strCommand)=='')
		{
			$this->setMessage('Request command is empty', 1004);
			return false;
		}
		$this->_command_str = $strCommand;
		$this->__command_line++;
		$strCommand = 'A' . $this->__command_line . ' ' . $strCommand;

		$this->strRequest = $strCommand."\r\n";
        if (isset($this->debug) || $this->bolDebug) {
            echo $this->strRequest;
        }
		$this->arrRequest[] = $strCommand;
        $this->_execute_time = DateHelper::microtime_float();
        try{
            $status = fputs($this->resHandler, $this->strRequest,strlen($this->strRequest));
		} catch (\Exception $e) {
            $this->setMessage($e->getMessage(), 1004);
			return false;
		}

		if ($exit) {
			var_dump($status);
		}

		return true;
	}
	//提取响应信息第一行
	function getLineResponse()
	{
		if (!$this->getIsConnect())
		{
			return false;
		}
		$this->strResponse = fgets($this->resHandler, $this->intBuffSize);
		if (isset($this->debug) || $this->bolDebug) {
            echo  mb_convert_encoding($this->strResponse, 'utf-8', 'gbk');
		}
		$this->arrResponse[] = $this->strResponse;
		return $this->strResponse;
	}
	
	function getsMore($bufferSize = 0) {
		if (!$this->getIsConnect())
		{
			return false;
		}
        if (!$bufferSize) {
            $bufferSize = $this->intBuffSize;
        }
		$this->strResponse = fread($this->resHandler, intval($bufferSize));
		if (isset($this->debug) || $this->bolDebug) {
            echo  mb_convert_encoding($this->strResponse, 'utf-8', 'gbk');
		}
		$this->arrResponse[] = $this->strResponse;
		return $this->strResponse;
	}

	/**
	 * 
	 * @param unknown $intReturnType
	 * @param number $intMail
	 * @param string $mail_log
	 * @param string $mail_uid
	 * @param string $commend_status  传入变量 标记是否取完成
	 * @return boolean|string|multitype:unknown Ambigous <boolean, string>
	 */
	function getRespMessage()
	{
		$this->command_status = false;
		if (!$this->getIsConnect())
		{	
			return false;
		}
		unset($this->debug);

		$strAllResponse = '';
        $last_tmp = '';//用于存储上一次缓冲内容
		while(!feof($this->resHandler))
		{
			$strLineResponse = $this->getsMore($this->intBuffSize);

            $temp_line = $last_tmp.$strLineResponse;
            $temp_line = strtoupper(substr($temp_line, strrpos($temp_line, "\r\n", -4)));
            $last_tmp = $strLineResponse;//存入缓存 用于判断数据流是否接收完成

			if (isset($this->test)) {
				echo $strLineResponse;exit();
			}

			if (preg_match("/A".$this->__command_line." NO(.*)\r\n$/Ui", $temp_line)
				|| strpos($temp_line, "* BAD COMMAND!\r\n$") === 0)
			{
				//出错了
				return false;
			}

			if (preg_match("/A".$this->__command_line." BAD(.*)\r\n$/Ui", $temp_line))
			{  //参数错误
				$this->setMessage('BAD invalid command or parameters', 1004);
				return false;
			}

			if (preg_match("/A".$this->__command_line." BAD(.*)\r\n$/Ui", $temp_line))
			{  //参数错误
				$this->setMessage('A2 BAD Select parameters!', 1004);
				return false;
			}
			$strAllResponse .= $strLineResponse;
			if (preg_match("/A".$this->__command_line."(.*)COMPLETE\r\n$/iU", $temp_line)
				|| preg_match("/A".$this->__command_line." OK(.*)\r\n$/iU", $temp_line))
			{
				$this->command_status = true;
				break;
			}
		}
		return $strAllResponse;
	}
	
	
	/**
	 *
	 * @param unknown $intReturnType
	 * @param number $intMail
	 * @param string $mail_log
	 * @param string $mail_uid
	 * @param string $commend_status  传入变量 标记是否取完成
	 * @return boolean|string|multitype:unknown Ambigous <boolean, string>
	 */
	function download($folder, $intMail, $mail_log)
	{
		$this->command_status = false;
		if (!$this->getIsConnect())
		{
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
            $handle_w = fopen($mail_log, "w");
        }

        $this->sendCommand("uid fetch ". $intMail." RFC822");

        $is_over = false;
		$last_tmp = '';//用于存储上一次缓冲内容
		while(!feof($this->resHandler))
		{
            $strLineResponse = $this->getsMore();

            $temp_line = $last_tmp.$strLineResponse;
            $temp_line = strtoupper(substr($temp_line, strrpos($temp_line, "\r\n", -4)));
            $last_tmp = $strLineResponse;//存入缓存 用于判断数据流是否接收完成

            if (preg_match("/A".$this->__command_line."(.*)COMPLETE(.*)\r\n$/iU", $temp_line))
            {
                $this->command_status = true;
                $is_over = true;
            } elseif (preg_match("/A".$this->__command_line." OK(.*)COMPLETED(.*)\r\n/iU", $temp_line))
            {
                $is_over = true;
            }
			if ($is_over) {
				$strLineResponse = substr($strLineResponse,0, strrpos($strLineResponse, "A".$this->__command_line));
			}
            if ($mail_log) {
                $s = fwrite($handle_w, $strLineResponse);
            }
            if ($is_over)
            {
                //结束
                $this->command_status = true;
                break;
            }
		}
        if ($mail_log) {
            fclose($handle_w);
        }
		$this->log();
	}
	
	//提取请求是否成功
	function getRestIsSucceed($strRespMessage='')
	{
		if (trim($strRespMessage)=='')
		{
			if ($this->strResponse=='')
			{
				$this->getLineResponse();
			}
			$strRespMessage = $this->strResponse;
		}
		if (trim($strRespMessage)=='')
		{
			$this->setMessage('Response message is empty', 2003);
			return false;
		}
        $strRespMessage = strtoupper($strRespMessage);
		if (stripos($strRespMessage, "A".$this->__command_line." OK")===0)
		{

		} elseif (stripos($strRespMessage, "*")===0) {

        }  elseif (stripos($strRespMessage, "*  BAD")===0) {
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
		if (!$this->resHandler)
		{
			$this->setMessage("Nonexistent availability connection handler", 2002);
			return false;
		}
		return true;
	}

	//设置消息
	function setMessage($strMessage, $intErrorNum)
	{
		if (trim($strMessage)=='' || $intErrorNum=='')
		{
			return false;
		}
		$this->strMessage    = $strMessage;
		$this->intErrorNum    = $intErrorNum;
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
        if (env('OPTIMIZATION_DEBUG') == true) {
            $this->obj->LogLib->optimization_list[] = "execute time:" . (DateHelper::microtime_float() - $this->_execute_time) . $this->_command_str;
        }
	}

	//---------------
	// 邮件原子操作
	//---------------
	//登录邮箱
	function login()
	{
		if (!$this->getIsConnect())
		{
			return false;
		}
		$this->sendCommand("LOGIN ".$this->strEmail." ".$this->strPasswd);
		$strResponse = $this->getLineResponse();
		$strResponse = mb_convert_encoding($strResponse, 'utf-8', 'gbk');
        $this->log();
		if (preg_match("/授权码/",$strResponse)) {
			//请使用授权码
			return -1;
		} else if (preg_match("/ssl/", $strResponse)) {
			//请使用ssl
			return -2;
		} else if (preg_match("/pop3/", $strResponse)) {
			//服务器未开启pop3
			return -3;
		} else if (preg_match("/suspended/", $strResponse)) {
            //服务器停了该协议
            return -3;
        }

		$bolUserRight = $this->getRestIsSucceed($strResponse);

		if (!$bolUserRight)
		{
			$this->setMessage($this->strResponse, 2004);
			return false;
		}
		$this->bolIsLogin = true;
		return true;
	}
	//退出登录
	function logout()
	{
		if (!$this->getIsConnect() && $this->bolIsLogin)
		{
			return false;
		}
        $this->sendCommand("LOGOUT");
		return true;
	}
	//获取是否在线
	function getIsOnline()
	{
		if (!$this->getIsConnect() && $this->bolIsLogin)
		{
			return false;
		}
		$this->sendCommand("NOOP");
        $strResponse = $this->getLineResponse();
		if (!$this->getRestIsSucceed($strResponse))
		{
			return false;
		}
		return true;
	}
	
	//获取邮件数量和字节数(返回数组)
	function getMailSum($folder)
	{
		if (!$this->getIsConnect() && $this->bolIsLogin)
		{
			return false;
		}
        return $this->selectFolder($folder);
	}
	//获取指定邮件得session Id
	function getMailSessId($intMailId, $intReturnType=2)
	{
		if (!$this->getIsConnect() && $this->bolIsLogin)
		{
			return false;
		}
		if (!$intMailId = intval($intMailId))
		{
			$this->setMessage('Mail message id invalid', 1005);
			return false;
		}
		$this->sendCommand("UIDL ". $intMailId);
        $strResponse = $this->getLineResponse();
		if (!$this->getRestIsSucceed($strResponse))
		{
			return false;
		}
		if ($intReturnType == 1)
		{
			return  $strResponse;
		}
		else
		{
			$arrResponse = explode(" ", $strResponse);
			if (!is_array($arrResponse) || count($arrResponse)<=0)
			{
				$this->setMessage('UIDL command response message is error', 2006);
				return false;
			}
			return array($arrResponse[1], $arrResponse[2]);
		}
	}
	//取得某个邮件的大小
	function getMailSize($intMailId)
	{
		if (!$this->getIsConnect() && $this->bolIsLogin)
		{
			return false;
		}

        if (empty($this->Select_Box)) {
            $this->selectFolder();
        }

        $this->sendCommand("FETCH ". $intMailId." RFC822.SIZE");
        $line = $this->getLineResponse();
        $line1 = $this->getLineResponse();
        if (preg_match("/FETCH \(UID (\d+) RFC822.SIZE (\d+)\)/", $line, $match)) {
            return $match[2];
        }
        return false;
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
        $cache_key = $this->strEmail.$this->pro.'getFolderList';
        if ($cache == true) {
            $results = $this->obj->CacheLib->get($cache_key);
            if (!empty($results)) {
                return $results;
            }
		}

        $this->sendCommand('LIST "" *');
        $response = $this->getRespMessage();
        preg_match_all('|"/" "(.*)"|iU', $response, $matchs);
    	$all_folders = $matchs[1] ?? [];
        $results = [];
        $folder_types = [];
    	foreach ($all_folders as $folder) {
            //$total = $this->selectFolder($folder);
            $total = 0;
            $foler_name = mb_convert_encoding($folder, "UTF-8", "UTF7-IMAP");
            $results[$folder] = ['folder' => $folder, 'folder_name' => $foler_name, 'total' => $total];
            $trans_folder_name = $this->obj->MailDecodeLib->folderToName($foler_name);
            if (!in_array($trans_folder_name, $folder_types)) {
                $folder_types[] = $trans_folder_name;
                $results[$folder]['folder_name'] = $trans_folder_name;
                $results[$folder]['folder_type'] = $this->obj->MailDecodeLib->folderType($trans_folder_name);
			} else {
                $results[$folder]['folder_type'] = "";
            }
    	}
		$this->obj->CacheLib->set($cache_key, $results, 3600);
        $this->log();
		return $results;
    }
	
	/**
	 * only for imap
	 * @param string $folder
	 * @param number $intReturnType
	 * @return boolean|Ambigous <boolean, multitype:boolean , string>
	 */
	function selectFolder($folder='INBOX')
	{
		if (!$this->getIsConnect() && $this->bolIsLogin)
		{
			return false;
		}
		if (strpos($folder, " ")) {
            $folder = '"' . $folder . '"';
		}
		$this->sendCommand('Select '.$folder);
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
	function getUidList($folder, $check_cache = false)
	{
		if (!$this->getIsConnect() && $this->bolIsLogin)
		{
			return false;
		}
		if ($folder != $this->Select_Box) {
            $select_result = $this->selectFolder($folder);
            if ($select_result === false) {
                //收取失败
                return $select_result;
            }
        }
        $redis_key = 'mail_uid'.$this->strEmail.$this->Select_Box.$this->pro;
        if ($check_cache && $data = $this->obj->CacheLib->get($redis_key)) {
			return $data;
		}
		$this->sendCommand("uid Search SINCE ".date('d-M-Y', strtotime("-3 months")));
        //$this->sendCommand("uid Search ALL");

        $data = $this->getRespMessage();

        $data = substr($data, 0, strpos($data, "\r\nA".$this->__command_line));
        $data = explode(" ", $data);
        $data = array_slice($data, 2);
        if($data && end($data) == "") {
            array_pop($data);
        }
        $results = [];
        /*$total = count($data);
        if ($total) {
        	$index = 0;
        	$unReceiveMail = $this->obj->getUnAbleReceive($this->account_id, $folder);
			while($total--) {
				if (in_array($unReceiveMail, $data[$total])) {
					break;
				}
                $results[$data[$total]] = ['uid' => $data[$total]];
                $index++;
                if ($index >= 1000) {
                    //最多同步1000封
                    break;
                }
			}
		}*/
        foreach ($data as $row) {
        	if (!intval($row)) {
				continue;
			}
            $results[$row] = ['uid' => $row];
        }
        $this->obj->CacheLib->set($redis_key, $results, 3600);
        $this->log();
        return $results;
	}

    function getUidUnseen($folder)
    {
        if (!$this->getIsConnect() && $this->bolIsLogin)
        {
            return false;
        }
        if ($folder != $this->Select_Box) {
            $select_result = $this->selectFolder($folder);
            if ($select_result === false) {
                //收取失败
                return $select_result;
            }
        }
        $this->sendCommand("uid Search unseen");
        $data = $this->getRespMessage();

        $data = substr($data, 0, strpos($data, "\r\nA".$this->__command_line));
        $data = explode(" ", $data);
        $data = array_slice($data, 2);

        if($data && end($data) == "") {
            array_pop($data);
		}

        $this->log();
        //设置过期时间
        return $data;
    }

    public function getUidIndex($uid)
    {
        $redis_key = 'mail_uid'.$this->strEmail.$this->Select_Box.$this->pro;
        $data = $this->obj->CacheLib->get($redis_key);
        return array_search($data, $uid);
    }

    public function clearCache($folder)
	{
        $redis_key = 'mail_uid'.$this->strEmail.$folder.$this->pro;
        $this->obj->CacheLib->delete($redis_key);
	}

	/**
	 * 得到邮件uid 已读未读，只有imap支持此扩展
	 * @param unknown $intMailId
	 * @return Ambigous <boolean, multitype:unknown boolean , string>
	 */
	function getMailIsUid($intMailId) {
        if (empty($this->Select_Box)) {
            $this->selectFolder();
        }
        $this->sendCommand("FETCH ". $intMailId." INTERNALDATE");
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
                return array(md5(str_replace(array("\n","\r", " "), "",$uid)), strtotime($match[2]));
            }
        }
        return array(0, 0);
	}

	//获取某邮件前指定行, $intReturnType 返回值类型，1是字符串，2是数组
	function getMailTopMessage($intMailId, $intReturnType=1)
	{
		
		if (!$this->getIsConnect() && $this->bolIsLogin)
		{
			return false;
		}
		if (!intval($intMailId))
		{
			$this->setMessage('Mail message id or Top lines number invalid', 1005);
			return false;
		}
        if (empty($this->Select_Box)) {
            $this->selectFolder();
        }
        $this->sendCommand("uid FETCH ". $intMailId." BODY[HEADER]");
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
	function realDelMail($folder, $intMailId)
	{
		if (!$this->getIsConnect() && $this->bolIsLogin)
		{
			return false;
		}
		if (!$intMailId=intval($intMailId))
		{
			$this->setMessage('Mail message id invalid', 1005);
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
		if (!$this->getRestIsSucceed())
		{
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
    function moveMail($folder, $intMailId, $to_folder)
    {
        if (!$this->getIsConnect() && $this->bolIsLogin)
        {
            return false;
        }
        if (!$intMailId=intval($intMailId))
        {
            $this->setMessage('Mail message id invalid', 1005);
            return false;
        }
        if ($folder != $this->Select_Box) {
            $status = $this->selectFolder($folder);
            if ($status === false) {
				return $status;
			}
        }
        if (strpos($to_folder, " ")) {
            $to_folder = '"' . $to_folder . '"';
        }
        $this->sendCommand("uid COPY ". $intMailId. " ". $to_folder);
        $str = $this->getsMore();
        if (!$this->getRestIsSucceed($str))
        {
            return false;
        }
        $this->log();
        //将原邮件标记删除
        $this->sendCommand("uid store ". $intMailId. " +FLAGS (\Deleted)");
        $str = $this->getsMore();
        if (!$this->getRestIsSucceed($str))
        {
            return false;
        }
        $this->log();
		//彻底删除原邮件
        $this->sendCommand("EXPUNGE ");
        $str = $this->getsMore();
        if (!$this->getRestIsSucceed($str))
        {
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
    public function store($folder, $mailUid, $flags)
    {
        if (!$this->getIsConnect() && $this->bolIsLogin)
        {
            return false;
        }
        if ($folder != $this->Select_Box) {
            $status = $this->selectFolder($folder);
            if ($status === false) {
                return $status;
            }
        }
        $this->sendCommand("uid store ".$mailUid." ".$flags);
        //$this->test = 1;
        $line = $this->getRespMessage();
        //echo $line;
        $this->log();
        if (!$this->getRestIsSucceed())
        {
            return false;
        }
        return true;
    }


    function delFolder($folder)
    {
        if (!$this->getIsConnect() && $this->bolIsLogin)
        {
            return false;
        }
        $this->sendCommand("DELETE ".$folder);
        $this->getLineResponse();
        if (!$this->getRestIsSucceed())
        {
            return false;
        }
        return true;
    }

    function createFolder($folder)
    {
        if (!$this->getIsConnect() && $this->bolIsLogin)
        {
            return false;
        }
        $this->sendCommand("CREATE ".$folder);
        $this->getLineResponse();
        if (!$this->getRestIsSucceed())
        {
            return false;
        }
        return true;
    }

    function closeFolder()
    {
        if (!$this->getIsConnect() && $this->bolIsLogin)
        {
            return false;
        }
        $this->sendCommand("CLOSE ");
        $this->getLineResponse();
        $this->log();
        if (!$this->getRestIsSucceed())
        {
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
        $str = $this->getsMore();
        if (!$this->getRestIsSucceed($str))
        {
            return false;
        }
        $this->log();
	}
    function renameFolder($oldfolder, $new_folder)
    {
        if (!$this->getIsConnect() && $this->bolIsLogin)
        {
            return false;
        }
        $this->sendCommand("RENAME ".$oldfolder." ".$new_folder);
        $this->getLineResponse();
        if (!$this->getRestIsSucceed())
        {
            return false;
        }
        return true;
    }

	//重置被删除得邮件标记为未删除
	function resetDeleMail()
	{
		if (!$this->getIsConnect() && $this->bolIsLogin)
		{
			return false;
		}
		$this->sendCommand("RSET");
		$this->getLineResponse();
		if (!$this->getRestIsSucceed())
		{
			return false;
		}
		return true;
	}

	//输出错误信息
	function printError()
	{
		echo "[Error Msg] : $strMessage     <br>\n";
		echo "[Error Num] : $intErrorNum <br>\n";
		exit;
	}
	//输出主机信息
	function printHost()
	{
		echo "[Host]  : $this->strHost <br>\n";
		echo "[Port]  : $this->intPort <br>\n";
		echo "[Email] : $this->strEmail <br>\n";
		echo "[Passwd] : ******** <br>\n";
		exit;
	}
	//输出连接信息
	function printConnect()
	{
		echo "[Connect] : $this->resHandler <br>\n";
		echo "[Request] : $this->strRequest <br>\n";
		echo "[Response] : $this->strResponse <br>\n";
		exit;
	}

	public function __destruct()
    {
		$this->logout();
		$this->closeHost();
    }
}
?>

