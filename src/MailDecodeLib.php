<?php
/**
 * Created by PhpStorm.
 * User: zhangyubo
 * Date: 2018/6/11
 * Time: 上午9:02
 */

namespace PhpMailClient;


class MailDecodeLib
{
    private $boundary_index = 0;

    private $boundary = [];

    private $_key_words = ['CONTENT-TYPE', 'CONTENT-DISPOSITION', 'CONTENT-ID', 'CONTENT-TRANSFER-ENCODING'];

    private $_header_key_words = ['FROM','TO','CC','SUBJECT','BCC','DATE'];


    public function parseHeader($header_string)
    {
        $headers = imap_rfc822_parse_headers($header_string);

        $results = [];
        if (isset($headers->date) || isset($headers->Date)) {
            $date = $headers->date ?? $headers->Date;
            if ($index = strpos($date, " (")) {
                $date = substr($date, 0, $index);
            }
            $results['date'] = strtotime($date);
        }
        if (isset($headers->subject)) {
            $results['subject'] = $this->decodeMimeStr($headers->subject);
        }
        if (!isset($headers->subject) && isset($headers->Subject)) {
            $results['subject'] = $this->decodeMimeStr($headers->Subject);
        }
        if (isset($headers->message_id)) {
            $results['message_id'] = str_replace(["\r", "\n"], '', trim($headers->message_id));
        }
        $results['to'] = [];
        $results['toaddress'] = '';
        if (isset($headers->to)) {
            foreach ($headers->to as $mail) {
                $user = [
                    'personal' => isset($mail->personal) ? $this->decodeMimeStr($mail->personal) : $mail->mailbox,
                    'mailbox' => $mail->mailbox,
                    'host' => $mail->host,
                    'email' => $mail->mailbox . '@' . $mail->host
                ];
                $results['to'][] = $user;
                $results['toaddress'] = str_replace(["\r", "\n"], '', $user['personal'] . '<' . $user['email'] . '>');
            }
        }
        $results['from'] = [];
        $results['fromaddress'] = '';
        if (isset($headers->from)) {
            foreach ($headers->from as $mail) {
                $user = [
                    'personal' => isset($mail->personal) ? $this->decodeMimeStr($mail->personal) : $mail->mailbox,
                    'mailbox' => $mail->mailbox,
                    'host' => $mail->host,
                    'email' => $mail->mailbox . '@' . $mail->host
                ];
                $results['from'][] = $user;
                $results['fromaddress'] = trim(str_replace(["\r", "\n"], '', $user['personal'] . '<' . $user['email'] . '>'));
            }
        }
        $results['reply_to'] = [];
        $results['reply_toaddress'] = '';
        if (isset($headers->reply_to)) {
            foreach ($headers->reply_to as $mail) {
                $results['reply_to'][] = [
                    'personal' => isset($mail->personal) ? $this->decodeMimeStr($mail->personal) : $mail->mailbox,
                    'mailbox' => $mail->mailbox,
                    'host' => $mail->host,
                    'email' => $mail->mailbox . '@' . $mail->host
                ];
                $results['reply_to'][] = $user;
                $results['reply_toaddress'] = str_replace(["\r", "\n"], '', $user['personal'] . '<' . $user['email'] . '>');
            }
        }
        $results['sender'] = [];
        $results['senderaddress']  = '';
        if (isset($headers->sender)) {
            foreach ($headers->sender as $mail) {
                $user = [
                    'personal' => isset($mail->personal) ? $this->decodeMimeStr($mail->personal) : $mail->mailbox,
                    'mailbox' => $mail->mailbox,
                    'host' => $mail->host,
                    'email' => $mail->mailbox . '@' . $mail->host
                ];
                $results['sender'][] = $user;
                $results['senderaddress'] = str_replace(["\r", "\n"], '', $user['personal'] . '<' . $user['email'] . '>');
            }
        }
        $results['cc'] = [];
        $results['ccaddress'] = '';
        if (isset($headers->cc)) {
            foreach ($headers->cc as $mail) {
                $user = [
                    'personal' => isset($mail->personal) ? $this->decodeMimeStr($mail->personal) : $mail->mailbox,
                    'mailbox' => $mail->mailbox,
                    'host' => $mail->host,
                    'email' => $mail->mailbox . '@' . $mail->host
                ];
                $results['cc'][] = $user;
                $results['ccaddress'] = str_replace(["\r", "\n"], '', $user['personal'] . '<' . $user['email'] . '>');
            }
        }
        $results['bcc'] = [];
        $results['bccaddress'] = '';
        if (isset($headers->cc)) {
            foreach ($headers->cc as $mail) {
                $user = [
                    'personal' => isset($mail->personal) ? $this->decodeMimeStr($mail->personal) : $mail->mailbox,
                    'mailbox' => $mail->mailbox,
                    'host' => $mail->host,
                    'email' => $mail->mailbox . '@' . $mail->host
                ];
                $results['bcc'][] = $user;
                $results['bccaddress'] = str_replace(["\r", "\n"], '', $user['personal'] . '<' . $user['email'] . '>');
            }
        }
        $results['received'] = '';
        if (!isset($results['date'])) {
            //取不到时间信息
            $all_rows = explode("\r\n", $header_string);
            $summ_head = '';
            foreach ($all_rows as $row) {
                $pos = strpos($row, ":");
                if ($pos && trim(substr($row, 0, 1)) == "") {
                    $pos = 0;
                }
                if ($pos && strtoupper(substr($row, 0, $pos)) == 'RECEIVED') {
                    $summ_head = strtoupper(substr($row, 0, $pos));
                    $content = substr($row, $pos + 1);
                } else {
                    $content = $row;
                }
                if (empty($summ_head)) {
                    continue;
                }

                if ($results['received'] && $pos && strtoupper(substr($row, 0, $pos)) != 'RECEIVED') {
                    break;
                }
                if ($summ_head == 'RECEIVED') {
                    $results['received'] .= $content;
                }

            }
            preg_match("/;(.*)/", $results['received'], $match);
            if (isset($match[1])) {
                if ($index = strpos($match[1], " (")) {
                    $match[1] = substr($match[1], 0, $index);
                }
               $results['date'] =  strtotime($match[1]);
            }
        }
        if (empty($results['date'])) {
            $results['date'] = 0;
        }
        //是否有附件
        $results['has_attach'] = strpos($header_string, 'multipart/mixed') ? 1 : 0;
        //构建唯一标示，不管imap和pop协议导致mail_uid不一致的问题
        $results['message_id'] = md5(
            $results['subject']
            .$results['date']
            .$results['fromaddress']
            .$results['toaddress']
            .$results['ccaddress']
            .$results['reply_toaddress']
        );
        return $results;
    }

    protected function decodeMimeStr($string, $toCharset = 'utf-8')
    {
        $newString = '';
        foreach (imap_mime_header_decode($string) as $element) {
            if (isset($element->text)) {
                $fromCharset = !isset($element->charset) || $element->charset == 'default' ? 'iso-8859-1' : $element->charset;
                $newString .= $this->convertStringEncoding($element->text, $fromCharset, $toCharset);
            }
        }
        return $newString;
    }

    function isUrlEncoded($string)
    {
        $hasInvalidChars = preg_match('#[^%a-zA-Z0-9\-_\.\+]#', $string);
        $hasEscapedChars = preg_match('#%[a-zA-Z0-9]{2}#', $string);
        return !$hasInvalidChars && $hasEscapedChars;
    }

    protected function decodeRFC2231($string, $charset = 'utf-8')
    {
        if (preg_match("/^(.*?)'.*?'(.*?)$/", $string, $matches)) {
            $encoding = $matches[1];
            $data = $matches[2];
            if ($this->isUrlEncoded($data)) {
                $string = $this->convertStringEncoding(urldecode($data), $encoding, $charset);
            }
        }
        return $string;
    }

    /**
     * Converts a string from one encoding to another.
     * @param string $string
     * @param string $fromEncoding
     * @param string $toEncoding
     * @return string Converted string if conversion was successful, or the original string if not
     * @throws Exception
     */
    public function convertStringEncoding($string, $fromEncoding, $toEncoding)
    {
        if (!$string || $fromEncoding == $toEncoding) {
            return $string;
        }
        $convertedString = function_exists('iconv') ? @iconv($fromEncoding, $toEncoding . '//IGNORE', $string) : null;
        if (!$convertedString && extension_loaded('mbstring')) {
            $convertedString = @mb_convert_encoding($string, $toEncoding, $fromEncoding);
        }
        if (!$convertedString) {
            throw new Exception('Mime string encoding conversion failed');
        }
        return $convertedString;
    }

    /**
     * 获取正文头
     * @param $mail_string
     */
    public function buildContentHeader($mail_string)
    {
        echo "=======".$mail_string;
        $all_rows = explode("\r\n", $mail_string);
        $result = [];
        $content = '';
        $header_chars = 0;//头部的长度
        $key = '';
        $head_over = false;
        foreach ($all_rows as $index=>$row) {
            $header_chars = $header_chars+ strlen($row)+2;
            if (str_replace(array("\r\n", "\r", "\n", " "), "", trim($row)) == "" || $head_over) {
                //头部结束
                break;
            }
            $pos = strpos($row, ":");
            if ($pos && in_array(strtoupper(substr($row, 0, $pos)), $this->_key_words)) {
                $key = strtoupper(substr($row, 0, $pos));
                $content = substr($row, $pos + 1);
            } elseif ($key) {
                $content = $content . $row;
            }
            $line_over = false;
            if (isset($all_rows[$index+1])) {
                //echo $all_rows[$index+1]."--\r\n";

                $new_line_pos = strpos($all_rows[$index + 1], ":");
                $next_key = substr($all_rows[$index + 1], 0, $new_line_pos);

                if ($new_line_pos) {
                    $line_over = true;
                    if (isset($result[strtolower($next_key)])) {
                        $head_over = true;
                    } elseif (!in_array(strtoupper($next_key), $this->_key_words)) {
                        $head_over = true;
                       // echo "%%%";
                    }
                } elseif (str_replace(array("\r\n", "\r", "\n", " "), "", $all_rows[$index + 1]) == "") {
                    $line_over = true;
                    $head_over = true;
                }
            }
            if ($line_over == true && $key) {
                $result[strtolower($key)] = str_replace(array("\r\n", "\r", "\n", " "), "", $content);
                $content = '';
            }
        }
        print_r($result);
        //exit();
        unset($all_rows);
        if (isset($result['content-type'])) {
            $origin = $result['content-type'];
            //echo $origin;exit();

            $content = explode(';', $result['content-type']);
            $content = $content[0];
            if (strtolower($content) == 'text/html') {
                if (!isset($this->is_postback)) $this->is_postback = 1;
                else
                    $this->is_postback++;
            }
            if (isset($this->is_postback) && $this->is_postback == 2) {
                //如果是退信的 会有两个html正文内容;
                return;
            }
            $result['content-type'] = strtolower($content);

            if (preg_match("/charset=(.*)/i", $origin, $match)) {
                //本行
                $match = explode(";", $match[1]);
                $result['charset'] = str_replace(array("\"", "'", " "), "", $match[0]);
            }
            if (preg_match("/name=(.*)/i", $origin, $match)) {
                //本行
                //$encode = mb_detect_encoding($match[1], array("ASCII", 'UTF-8', "GB2312", "GBK", 'BIG5'));
                //if ($encode != '') {
                    $match[1] = $this->decodeMimeStr($match[1]);
                //}
                $result['name'] = str_replace(array("\"", "'", " "), "", $match[1]);
            }
            if (preg_match("/boundary=(.*)/i", $origin, $match)) {
                //本行
                $this->boundary[$this->boundary_index] = '';
                $match[1] = str_replace(array("\"", "'", " "), "", $match[1]);
                $this->boundary[$this->boundary_index] = $match[1];
                $this->boundary_index++;
                $result['boundary'] = $match[1];
            }
        }
        if (isset($result['content-disposition'])) {
            if (!isset($result['name'])) {
                if (preg_match("/name=(.*)/i", $result['content-disposition'], $match)) {
                    //本行
                    //$encode = mb_detect_encoding($match[1], array("ASCII", 'UTF-8', "GB2312", "GBK", 'BIG5'));
                    //if ($encode != '') {
                        $match[1] = $this->decodeMimeStr($match[1]);
                    //}
                    $result['name'] = str_replace(array("\"", "'", " "), "", $match[1]);
                }
            }
            $strpos = strpos($result['content-disposition'], 'attachment');
            if (is_numeric($strpos)) {
                $result['is_attach'] = 1;
            }
        }
        if (isset($result['content-description'])) {
            $result['is_attach'] = 1;
        }
        if (isset($result['content-id'])) {
            if ($left_tag_pos = strpos($result['content-id'], "<")) {
                $mail_lenth = strrpos($result['content-id'], ">") - $left_tag_pos - 1;
                $result['content_id'] = substr($result['content-id'], $left_tag_pos + 1, $mail_lenth);
            }
        }
        if (isset($result['content-transfer-encoding'])) {
            $pos = strpos($result['content-transfer-encoding'], ":");
            $result['encoding'] = strtolower(substr($result['content-transfer-encoding'], $pos));
        }
        //seek为文件操作要回溯到的位置
        return ['body' => $result, 'seek' => $header_chars];
    }


    /**
     * 获取正文内容，包括附件
     * @param $mail_string
     */
    public function buildContent($content, $header)
    {
        if ($header['content-type'] == "multipart/mixed" || $header['content-type'] == "multipart/alternative") {
            //这两种类型 不用采集
            return ['body' => false, 'seek' => 0, 'none' => 1];
        }
        if (isset($header['content-transfer-encoding']) && $header['content-transfer-encoding'] == 'base64') {
            //base64  发现了空白行白行
            $find_next_content = strpos($content, "\r\n\r\n");
        } else {
            $find_next_content = strpos($content, "\r\nContent-Type:");
        }

        //查找分隔符
        foreach ($this->boundary as $boundary_row) {
            $is_bound = strpos($content, $boundary_row);
            if ($is_bound !== false) {
                $content = substr($content, 0, $is_bound);
                $find_next_content = strrpos($content, "\r\n");
            }
        }
        $content = $find_next_content ? substr($content, 0, $find_next_content) : $content;
        if (isset($header['path'])) {
            file_put_contents($header['path'], $content, FILE_APPEND);
            $content = '';
        }
        return ['body' => $content, 'seek' => $find_next_content];

        if ($header['content-type'] == 'text/calendar') {
            //该部分是日历
            if ($pos) {
                $calendar_row1 = strtoupper($row);
                if (stripos($calendar_row1, "DTEND") === 0) {
                    if (strtotime($content)) {
                        $result['calendar']['DTEND'] = $content;
                    }
                } else if (stripos($calendar_row1, "DTSTART") === 0) {
                    if (strtotime($content)) {
                        $result['calendar']['DTSTART'] = $content;
                    }
                } else if (stripos($calendar_row1, "LOCATION") === 0) {
                    $result['calendar']["LOCATION"] = $content;
                }
            }
        }
    }

    public function decodeBody1($email, $folder, $mail_uid, $mail_log, $attach_dir = '')
    {
        $mime = mailparse_msg_parse_file($mail_log);    //mime 解析文件
        $struct = mailparse_msg_get_structure($mime);   //解析 结构
        $result = [];
        foreach ($struct as $k => $st) {
            $section = mailparse_msg_get_part($mime, $st);   //解析 根据 id 获取part
            $info = mailparse_msg_get_part_data($section);   // 获取 part 数据信息 array（）
            if ($info['content-type'] == 'multipart/mixed'
                || $info['content-type'] == 'multipart/related'
                || $info['content-type'] == 'multipart/alternative'
            ) {
                continue;
            }
            $header = [
                'content-id' => $info['content-id'] ?? '',
                'content-type' => strtolower($info['content-type'] ?? ''),
                'encoding' => strtolower($info['transfer-encoding'] ?? ''),
                'charset' => strtolower($info['charset'] ?? ''),
                'is_attach' => 0,
                'content-disposition' => strtolower($info['content-disposition'] ?? ''),
            ];
            if (isset($info['content-name'])) {
                $header['name'] = $this->decodeMimeStr($info['content-name']);
            }
            if (isset($info['disposition-filename'])) {
                $header['name'] = $this->decodeMimeStr($info['disposition-filename']);
            }
            if (empty($header['name']) && $header['content-id']) {
                $header['name'] = md5($info['content-id']);
            }

            ob_start();   // 打开缓冲区
            mailparse_msg_extract_part_file($section, $mail_log);   // 读取 制动part 内的数据
            $contents = ob_get_contents();   //获取数据
            if ($header['charset'] != 'utf-8' && empty($header['name'])) {
                $contents = $this->convertStringEncoding($contents, $header['charset'], 'utf-8');
            }
            ob_end_clean();
            if (!empty($header['name'])) {
                $header['file_name'] = md5($email . $folder . $mail_uid . $header['name'].$st);
                $file_hanle = fopen($attach_dir . $header['file_name'], 'w+');
                $header['path'] = $attach_dir . $header['file_name'];
                fwrite($file_hanle, $contents);
                fclose($file_hanle);
                if ($header['content-disposition'] == 'attachment' || empty($header['content-id'])) {
                    $header['is_attach'] = 1;
                }
            } else {
                $header['body'] = $contents;
            }
            unset($contents);
            $header['charset'] = '';
            $header['encoding'] = '';
            $result[] = $header;
        }
        return $result;
    }

    /**
     * @param $mail_uid 邮件id
     * @param $mail_log 邮件报文的路径
     * @param string $attach_dir 附件存储的目录
     * @return array
     */
    public function decodeBody($mail_uid, $mail_log, $attach_dir = '')
    {
        $handle = @fopen($mail_log, "r");
        $handle_array = [];
        if ($handle) {
            $mark = '';
            $content_index = 0;
            $key = '';
            $key_index = '';
            $file_index = 0;

            $result = [];
            //关键词，其他都过滤
            $body_start = false;
            while (!feof($handle)) {
                $row = fgets($handle);
                $pos = strpos($row, ":");

                if ($pos && in_array(strtoupper(substr($row, 0, $pos)), $this->_key_words)) {
                    $key_index = $key = strtoupper(substr($row, 0, $pos));
                    $content = substr($row, $pos + 1);
                    $body_start = true;//标记正文开始
                } else {
                    $content = $row;
                }
                if (!$body_start) {
                    continue;
                }
                $rows = fread($handle, 512);

                $content = $row.$rows;
                $str_length = strlen($content);
                ///echo "||||||".$content;
                $header_result = $this->buildContentHeader($content);
                //print_r($header_result);
                fseek($handle, -($str_length - $header_result['seek']), SEEK_CUR);
                $content = fread($handle, 512);
                //echo ">>>>".$content;

                $str_length = strlen($content);
                if (isset($header_result['body']['name']) || isset($header_result['body']['content-id'])) {
                    if (!isset($header_result['body']['name'])) {
                        $header_result['body']['name'] = $header_result['body']['content-id'];
                    }
                    $file_index++;
                    $header_result['body']['file_name'] = $file_index . md5($header_result['body']['name']);
                    $header_result['body']['path'] = $attach_dir . $file_index . md5($header_result['body']['name']);
                    if (is_file($header_result['body']['path'])) {
                        unlink($header_result['body']['path']);
                    }
                }
                //收集正文
                $body_result = $this->buildContent($content, $header_result['body']);
                print_r($body_result);

                $body_result['header'] = $header_result['body'];
                if ($body_result['seek'] === false) {
                    //该片段内找不到结束标示 则继续循环
                    while (!feof($handle)) {
                        $rows = fread($handle, 512);
                        $str_length = strlen($rows);
                        $body_result_while = $this->buildContent($rows, $header_result['body']);
                        $body_result['body'] .= $body_result_while['body'];
                        if ($body_result_while['seek'] === false) {
                            continue;
                        }
                        fseek($handle, -($str_length - $body_result_while['seek']), SEEK_CUR);
                        break;
                    }
                } else {
                    fseek($handle, -($str_length - $body_result['seek']), SEEK_CUR);
                }
                //print_r($body_result);
                $result[] = $body_result;
                $body_start = false;
            }
        }
        foreach ($handle_array as $handle_line) {
            fclose($handle_line);
        }
        fclose($handle);
        return $result;
    }

    public function folderToName($folder)
    {
        switch (strtolower($folder)) {
            case 'inbox':
                $folder = '收件箱';
                break;
            case 'sent messages'://兼容qq
            case 'outbox':
            case 'sent':
                $folder = '发件箱';
                break;
            case 'drafts':
            case 'draft':
                $folder = '草稿箱';
                break;
            case 'deleted messages':
            case 'delete':
            case 'trash':
                $folder = '已删除';
                break;
            case 'spam':
            case 'junk':
                $folder = '垃圾箱';
                break;
        }
        return $folder;
    }

    public function folderType($folder)
    {
        $folder_type = '';
        switch ($folder) {
            case '收件箱':
                $folder_type = 'inbox';
                break;
            case 'Sent Messages'://qq邮箱兼容
            case '已发送':
            case '发件箱':
                $folder_type = 'sent';
                break;
            case '草稿箱':
                $folder_type = 'draft';
                break;
            case '回收站':
            case '已删除':
                $folder_type = 'trash';
                break;
            case '垃圾邮件':
            case '垃圾箱':
                $folder_type = 'spam';
                break;
        }
        return $folder_type;
    }

    public function getTmpPath($mail_uid = '')
    {
        $tmp_file = sys_get_temp_dir() ."/". md5($mail_uid);
        return $tmp_file;
    }
}