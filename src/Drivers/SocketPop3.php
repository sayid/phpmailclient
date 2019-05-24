<?php

namespace PhpMailClient\Drivers;

use GouuseCore\Helpers\OptionHelper;
use GouuseCore\Helpers\RpcHelper;

class SocketPop3Lib
{
	var $pro = 'pop3';
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
	var $Select_Box = '';
	var $total_mail = 0;
	var $command_status = false;

	function __construct() {
		$this->obj = OptionHelper::getGouuse();
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
			return false;
		}
		if (!PReg_match("/^[\w-]+(\.[\w-]+)*@[\w-]+(\.[\w-]+)+$/i", $this->strEmail))
		{
			$this->setMessage('Email address invalid', 1002);
			return false;
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
				$this->setMessage('POP3 host or Port is empty', 1003);
				return false;
			}
			//echo $this->strHost.', '.$this->intPort;
			$this->resHandler = @fsockopen($this->strHost, $this->intPort, $this->intErrorNum, $this->strMessage, $this->intConnSecond);
			if (!$this->resHandler)
			{
				$strErrMsg = 'Connection POP3 host: '.$this->strHost.' failed';
				$intErrNum = 2001;
				$this->setMessage($strErrMsg, $intErrNum);
				return false;
			}
			$this->getLineResponse();
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
			fclose($this->resHandler);
		}
		return true;
	}
	//发送指令
	function sendCommand($strCommand)
	{
		if ($this->bolDebug)
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

		$this->strRequest = $strCommand."\r\n";
		if (isset($this->debug) || $this->bolDebug) {
			echo $this->strRequest;
		}
		$this->arrRequest[] = $strCommand;
		$status = fputs($this->resHandler, $this->strRequest, strlen($this->strRequest));
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
			echo mb_convert_encoding($this->strResponse, 'utf-8', 'gbk');
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

		$this->strResponse = fread($this->resHandler, $bufferSize);
		if (isset($this->debug) || $this->bolDebug) {
			echo mb_convert_encoding($this->strResponse, 'utf-8', 'gbk');
		}
		$this->arrResponse[] = $this->strResponse;
		return $this->strResponse;
	}
	//提取若干响应信息,$intReturnType是返回值类型, 1为字符串, 2为数组
	/**
	 *
	 * @param unknown $intReturnType
	 * @param number $intMail
	 * @param string $mail_log
	 * @param string $mail_uid
	 * @param string $commend_status  传入变量 标记是否取完成
	 * @return boolean|string|multitype:unknown Ambigous <boolean, string>
	 */
	function getRespMessage($intReturnType)
	{
		$this->command_status = false;
		if (!$this->getIsConnect())
		{
			return false;
		}
		unset($this->debug);

		if ($intReturnType == 1)
		{
			$strAllResponse = '';

			while(!feof($this->resHandler))
			{
				$strLineResponse = $this->getsMore();
				$strAllResponse .= $strLineResponse;
				if (preg_match("/\r\n.\r\n$/Ui", $strAllResponse))
				{
					$this->command_status = true;
					break;
				}
			}
			return substr($strAllResponse, 0,-5);
		}
		else
		{
			$arrAllResponse = array();
			$i = 0;
			while(!feof($this->resHandler))
			{
				$i++;
				$strLineResponse = $this->getsMore();

				if (preg_match("/.\r\n$/Ui", $strLineResponse))
				{
					$this->command_status = true;
					break;
				}


				if (isset($handle_w)) {
					fwrite($handle_w, $strLineResponse);
				}
				$arrAllResponse[] = $strLineResponse;
			}
			return $arrAllResponse;
		}
	}


	/**
	 * @param int $intMail 邮件id
	 * @param $mail_log 存入临时文件，方便解析
	 * @return array|bool|string
	 */
	public function download($folder, $intMail, $mail_log)
	{
		$this->command_status = false;
		if (!$this->getIsConnect())
		{
			return false;
		}
		unset($this->debug);
		if ($mail_log) {
			@unlink($mail_log);
			$handle_w = fopen($mail_log, "w");
		}
		$this->sendCommand("RETR ". $intMail);
		$strLineResponse = $this->getLineResponse();
		if (!$this->getRestIsSucceed($strLineResponse)) {
			return false;
		}
		$last_tmp = '';//用于存储上一次缓冲内容
		while(!feof($this->resHandler))
		{
			$strLineResponse = $this->getsMore();
			$temp_line = $last_tmp.$strLineResponse;
			$last_tmp = $strLineResponse;
			$is_over = false;
			if (preg_match("/\r\n.\r\n$/Ui", $temp_line))
			{
				$strLineResponse = substr($strLineResponse, 0,-5);
				$is_over = true;
			}
			if ($mail_log) {
				//每次写入100行
				$s = fwrite($handle_w, $strLineResponse);
			}
			if ($is_over)
			{
				//结束
				$this->command_status = true;
				break;
			}
		}
		fclose($handle_w);
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

		if ($this->pro == 'smtp') {
			if (preg_match("/220/", $strRespMessage))
			{

			} else {
				$this->setMessage($strRespMessage, 2000);
				return false;
			}
		} else {
			if (!preg_match("/^\+OK/", $strRespMessage))
			{
				$this->setMessage($strRespMessage, 2000);
				return false;
			}
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
		$this->sendCommand("USER ".$this->strEmail);
		$this->getLineResponse();
		$bolUserRight = $this->getRestIsSucceed();
		$this->sendCommand("PASS ".$this->strPasswd);
		$this->getLineResponse();
		$bolPassRight = $this->getRestIsSucceed();
		if (!$bolUserRight || !$bolPassRight)
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
		$this->sendCommand("QUIT");
		$this->getLineResponse();
		if (!$this->getRestIsSucceed())
		{
			return false;
		}
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
		$this->getLineResponse();
		if (!$this->getRestIsSucceed())
		{
			return false;
		}
		return true;
	}

	//获取邮件数量和字节数(返回数组)
	function getMailSum($intReturnType=2)
	{
		if (!$this->getIsConnect() && $this->bolIsLogin)
		{
			return false;
		}
		if ($this->pro == 'imap') {
			if (empty($this->Select_Box)) {
				$this->selectFolder();
			}
			return array($this->total_mail, 0);
		} else {
			$this->sendCommand("STAT");
			$strLineResponse = $this->getLineResponse();
			if (!$this->getRestIsSucceed())
			{
				return false;
			}
			if ($intReturnType==1)
			{
				return     $this->strResponse;
			}
			else
			{
				$arrResponse = explode(" ", $this->strResponse);
				if (!is_array($arrResponse) || count($arrResponse)<=0)
				{
					$this->setMessage('STAT command response message is error', 2006);
					return false;
				}
				return array($arrResponse[1], $arrResponse[2]);
			}
		}
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
		$this->getLineResponse();
		if (!$this->getRestIsSucceed())
		{
			return false;
		}
		if ($intReturnType == 1)
		{
			return     $this->strResponse;
		}
		else
		{
			$arrResponse = explode(" ", $this->strResponse);
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
		if ($this->pro == 'imap') {
			if (empty($this->Select_Box)) {
				$this->selectFolder();
			}

			$this->sendCommand("FETCH ". $intMailId." RFC822.SIZE");
			$line = $this->getLineResponse();
			$line1 = $this->getLineResponse();
			//echo $line."\n";
			//echo $line1."\n";
			if (preg_match("/FETCH \(UID (\d+) RFC822.SIZE (\d+)\)/", $line, $match)) {
				return $match[2];
			}
			return false;

		} else {
			$this->sendCommand("LIST ".$intMailId);
			$this->getLineResponse();
			if (!$this->getRestIsSucceed())
			{
				return false;
			}
			$arrMessage = explode(' ', $this->strResponse);
			return $arrMessage[2];
		}
	}

	function getFolderList()
	{
		$folder = 'Inbox';
		$this->sendCommand("stat");
		$strResponse = $this->getLineResponse();
		if (!$this->getRestIsSucceed($strResponse))
		{
			return false;
		}
		$arr = explode(" ", $strResponse);
		return [
			[
				$folder => ['folder' => $folder, 'folder_type' => 'inbox',  'folder_name' => $this->obj->MailDecodeLib->folderToName($folder), 'total' => $arr[1]],
				'draft' => ['folder' => 'draft', 'folder_type' => 'draft',  'folder_name' => '草稿箱', 'total' => 0]
			]
		];
	}

	//获取邮件基本列表数组
	function getMailBaseList($intReturnType=2)
	{
		if (!$this->getIsConnect() && $this->bolIsLogin)
		{
			return false;
		}
		if ($this->pro == 'imap') {
			if (empty($this->Select_Box)) {
				$this->selectFolder();
			}
			$this->sendCommand("Search ALL");
			$data = $this->getRespMessage($intReturnType);
			$data = explode(" ", $data[0]);
			$data = array_slice($data, 2);
			rsort($data);
			return $data;
		} else {
			$this->sendCommand("LIST");
			echo $this->getLineResponse();
			if (!$this->getRestIsSucceed())
			{
				return false;
			}
			$results = $this->getRespMessage(2);
			$data = array();
			foreach ($results as $row) {
				$row_tmp =explode(" ", $row);
				$data[] = $row_tmp[0];
			}
			rsort($data);
			return $data;
		}

	}


	//获取邮件基本列表数组
	function getUidList($folder, $check_cache = false)
	{
		if (!$this->getIsConnect() && $this->bolIsLogin)
		{
			return false;
		}

		$redis_key = 'mail_uid'.$this->strEmail.$this->pro;
		$results = [];
		if ($check_cache && $data = $this->obj->CacheLib->get($redis_key)) {
			return $data;
		}
		$this->sendCommand("UIDL");
		$line = $this->getLineResponse();
		$data = $this->getRespMessage(1);
		$data = explode("\r\n", $data);
		$results = [];
		/*$total = count($data);
        if ($total) {
            $count = 0;
            $unReceiveMail = $this->obj->getUnAbleReceive($this->account_id, $folder);
            while($total--) {
                $index = substr($data[$total], 0, strpos($data[$total], " "));
                $uid = substr($data[$total], strpos($data[$total], " ")+1);
                if (in_array($unReceiveMail, $data[$total])) {
                    break;
                }
                if ($uid) {
                    $results[$uid] = ['uid' => $uid, 'index' => $index];
                }
                $count++;
                if ($count >= 1000) {
                    //最多同步1000封
                    break;
                }
            }
        }*/
		foreach ($data as $row) {
			$index = substr($row, 0, strpos($row, " "));
			$uid = substr($row, strpos($row, " ")+1);
			if ($uid) {
				$results[$uid] = ['uid' => $uid, 'index' => $index];
			}
		}
		//设置过期时间
		$this->obj->CacheLib->set($redis_key, $results, 300);
		return $results;
	}

	public function clearCache($folder = '')
	{
		$redis_key = 'mail_uid'.$this->strEmail.$this->pro;
		$this->obj->CacheLib->delete($redis_key);
	}

	public function getUidIndex($uid)
	{
		$redis_key = 'mail_uid'.$this->strEmail.$this->pro;
		$data = $this->obj->CacheLib->get($redis_key);
		return $data[$uid]['index'] ?? '';
	}

	/**
	 * 得到邮件uid 已读未读，只有imap支持此扩展
	 * @param unknown $intMailId
	 * @return Ambigous <boolean, multitype:unknown boolean , string>
	 */
	function getMailIsUid($intMailId) {
		if ($this->pro == 'imap') {
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
			return array(0, 0);;
		} else {
			$this->sendCommand("UIDL ". $intMailId);
			$line = $this->getLineResponse();
			$line = explode(" ", $line);
			return array(md5(str_replace(array("\n","\r", " "), "",end($line))), '');
		}
	}
	//获取指定邮件所有信息，intReturnType是返回值类型，1是字符串,2是数组
	function getMailMessage($intMailId, $intReturnType=1, $mail_log='', $need_return = false, $mail_size=0)
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
		if (is_file($mail_log)) {
			unlink($mail_log);
		}
		if ($this->pro == 'imap') {
			if (empty($this->Select_Box)) {
				$this->selectFolder();
			}
			$this->sendCommand("FETCH ". $intMailId." RFC822");
			$this->getLineResponse();
			if (!$this->getRestIsSucceed())
			{
				return false;
			}
			$data = $this->getDetail($intReturnType, $intMailId,$mail_log, $need_return, $mail_size);

			return $data;
		} else {
			$this->sendCommand("RETR ". $intMailId);
			$this->getLineResponse();
			if (!$this->getRestIsSucceed())
			{
				return false;
			}
			return $this->getDetail($intReturnType, $intMailId, $mail_log, $need_return, $mail_size);
		}
	}


	//获取某邮件前指定行, $intReturnType 返回值类型，1是字符串，2是数组
	function getMailTopMessage($intMailId, $intReturnType=1)
	{

		if (!$this->getIsConnect() && $this->bolIsLogin)
		{
			return false;
		}

		$this->sendCommand("TOP ". $intMailId." 0");
		$s = $this->getLineResponse();
		if (!$this->getRestIsSucceed())
		{
			return false;
		}
		$data = $this->getRespMessage($intReturnType);
		return $data;

	}


	//删除邮件
	function delMail($intMailId)
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
		$this->sendCommand("DELE ".$intMailId);
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

	//---------------
	// 调试操作
	//---------------
	//输出对象信息
	function printObject()
	{
		print_r($this);
		exit;
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
}
?>

