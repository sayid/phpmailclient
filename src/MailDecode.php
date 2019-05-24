<?php
/**
 * Created by PhpStorm.
 * User: zhangyubo
 * Date: 2018/6/11
 * Time: 上午9:02
 */

namespace PhpMailClient;

/**
 * 解析邮件报文工具类
 * Class MailDecode
 * @package PhpMailClient
 */
class MailDecode {

    private $boundary_index = 0;

    private $boundary = [];

    private $_key_words = ['CONTENT-TYPE', 'CONTENT-DISPOSITION', 'CONTENT-ID', 'CONTENT-TRANSFER-ENCODING'];

    public function parseHeader($header_string, $folder, $mail_uid)
    {
        $headers = imap_rfc822_parse_headers($header_string);
        $results = [];
        $results['date'] = 0;
        if (isset($headers->date) || isset($headers->Date)) {
            $date = $headers->date ?? $headers->Date;
            if ($index = strpos($date, " (")) {
                $date = substr($date, 0, $index);
            }
            $results['date'] = strtotime($date);
        }
        //发送者ip
        $sender_ip = '';
        if(preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $header_string, $match)) {
            $sender_ip = $match[0];
        }
        $results['sender_ip'] = $sender_ip;

        $results['subject'] = '';
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
                if(isset($mail->host)){
                    $mail->mailbox = $this->decodeMimeStr($mail->mailbox);
                    $mail->host = $this->decodeMimeStr($mail->host);
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
        }
        $results['from'] = [];
        $results['fromaddress'] = '';
        if (isset($headers->from)) {
            foreach ($headers->from as $mail) {
                $mail->mailbox = $this->decodeMimeStr($mail->mailbox);
                $mail->host = $this->decodeMimeStr($mail->host);
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
                $mail->mailbox = $this->decodeMimeStr($mail->mailbox);
                $mail->host = $this->decodeMimeStr($mail->host);
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
                $mail->mailbox = $this->decodeMimeStr($mail->mailbox);
                $mail->host = $this->decodeMimeStr($mail->host);
                $user = [
                    'personal' => isset($mail->personal) ? $this->decodeMimeStr($mail->personal) : $mail->mailbox,
                    'mailbox' => $mail->mailbox,
                    'host' => $mail->host,
                    'email' => $mail->mailbox . '@' . $mail->host,

                ];
                $results['sender'][] = $user;
                $results['senderaddress'] = str_replace(["\r", "\n"], '', $user['personal'] . '<' . $user['email'] . '>');
            }
        }
        $results['cc'] = [];
        $results['ccaddress'] = '';
        if (isset($headers->cc)) {
            foreach ($headers->cc as $mail) {
                $mail->mailbox = $this->decodeMimeStr($mail->mailbox);
                $mail->host = $this->decodeMimeStr($mail->host);
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
        if (isset($headers->bcc)) {
            foreach ($headers->bcc as $mail) {
                $mail->mailbox = $this->decodeMimeStr($mail->mailbox);
                $mail->host = $this->decodeMimeStr($mail->host);
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
        $results['received_date'] = 0;
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
            $results['received_date'] =  strtotime($match[1]);
        }
        if (($results['received_date'] && $results['received_date'] < $results['date']) || empty($results['date'])) {
            $results['date'] = $results['received_date'];
        }
        if (empty($results['date'])) {
            $results['date'] = 0;
        }
        if ($results['date'] == 0) {
            $results['date'] = time();
        }
        //是否有附件
        $results['has_attach'] = strpos($header_string, 'multipart/mixed') ? 1 : 0;
        //构建唯一标示，不管imap和pop协议导致mail_uid不一致的问题
        $results['message_id'] = md5(
            $folder.$mail_uid.
            $results['subject']
            .$results['date']
            .$results['fromaddress']
            .$results['toaddress']
            .$results['ccaddress']
            .$results['reply_toaddress']
        );
        return $results;
    }

    public function getUid($header) {
        $first_line = strtoupper(substr($header, 0, strpos($header, "\r\n")));
        $old_uid = '';
        if (preg_match("/UID (\d+) BODY/", $first_line, $match)) {
            $old_uid = $match[1] ?? '';
        }
        if(empty($old_uid)) {
            if (preg_match("/UID (\d+)/", $header, $match)) {
                $old_uid = $match[1] ?? '';
            }
        }
        return $old_uid;
    }

    protected function decodeMimeStr($string, $toCharset = 'utf-8')
    {
        $newString = '';
        foreach (imap_mime_header_decode($string) as $element) {
            if (isset($element->text)) {
                $fromCharset = !isset($element->charset) || $element->charset == 'default' ? 'gbk' : $element->charset;
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
        $body = '';
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
            if (!empty($header['name']) && $attach_dir) {
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
            if ($header['content-type'] =='text/plain' || $header['content-type'] =='text/html') {
                $body = $header['body'];
            }
        }
        return ['content' => $result, 'body' => $body];
    }

    protected function parse_line($line, $current_size = 0, $max = 0, $line_length = 0) {
        /* make it a bit easier to find "atoms" */
        $this->allString = $line;
        $line = str_replace(')(', ') (', $line);
        /* will hold the line parts */
        $parts = array();

        /* flag to control if the line continues */
        $line_cont = false;

        /* line size */
        $len = strlen($line);

        /* walk through the line */
        for ($i=0;$i<$len;$i++) {

            /* this will hold one "atom" from the parsed line */
            $chunk = '';

            /* if we hit a newline exit the loop */
            if ($line{$i} == "\r" || $line{$i} == "\n") {
                $line_cont = false;
                break;
            }

            /* skip spaces */
            if ($line{$i} == ' ') {
                continue;
            }

            /* capture special chars as "atoms" */
            elseif ($line{$i} == '*' || $line{$i} == '[' || $line{$i} == ']' || $line{$i} == '(' || $line{$i} == ')') {
                $chunk = $line{$i};
            }

            /* regex match a quoted string */
            elseif ($line{$i} == '"') {
                if (preg_match("/^(\"[^\"\\\]*(?:\\\.[^\"\\\]*)*\")/", substr($line, $i), $matches)) {
                    $chunk = substr($matches[1], 1, -1);
                }
                $i += strlen($chunk) + 1;
            }

            /* IMAP literal */
            elseif ($line{$i} == '{') {
                $end = strpos(substr($line,  $i+1), '}');
                if ($end !== false) {
                    $literal_size = substr($line, ($i + 1), $end);
                    $chunk = substr($line, $end + $i+4, $literal_size);
                    $i += $literal_size + $end + 3;
                }
            }

            /* all other atoms */
            else {
                $marker = -1;

                /* don't include these three trailing chars in the atom */
                foreach (array(' ', ')', ']') as $v) {
                    $tmp_marker = strpos($line, $v, $i);
                    if ($tmp_marker !== false && ($marker == -1 || $tmp_marker < $marker)) {
                        $marker = $tmp_marker;
                    }
                }

                /* slice out the chunk */
                if ($marker !== false && $marker !== -1) {
                    $chunk = substr($line, $i, ($marker - $i));
                    $i += strlen($chunk) - 1;
                }
                else {
                    $chunk = rtrim(substr($line, $i));
                    $i += strlen($chunk);
                }
            }

            /* if we found a worthwhile chunk add it to the results set */
            if ($chunk) {
                $parts[] = $chunk;
            }
        }
        return array($line_cont, $parts);
    }


    /**
     * wrapper around fgets using $this->handle
     *
     * @param $len int max read length for fgets
     *
     * @return string data read from the IMAP server
     */
    protected function fgets($len=false) {
        if (is_resource($this->handle) && !feof($this->handle)) {
            if ($len) {
                return fgets($this->handle, $len);
            }
            else {
                return fgets($this->handle);
            }
        }
        return '';
    }


    /**
     * Read IMAP literal found during parse_line().
     *
     * @param $size int size of the IMAP literal to read
     * @param $max int max size to allow
     * @param $current int current size read
     * @param $line_length int amount to read in using fgets()
     *
     * @return array the data read and any "left over" data
     *               that was inadvertantly on the same line as
     *               the last fgets result
     */
    public function read_literal($size, $literal_data) {
        $left_over = false;
        $lit_size = strlen($literal_data);
        if ($size < strlen($literal_data)) {
            $left_over = substr($literal_data, $size);
            $literal_data = substr($literal_data, 0, $size);
        }
        return array($literal_data, $left_over);
    }


    public function getStructure($struct_response)
    {

        list($_, $struct_response) = $this->parse_line($struct_response, strlen($struct_response));
        while (isset($struct_response[0]) && isset($struct_response[1]) && $struct_response[0] == '*' && strtoupper($struct_response[1]) == 'OK') {
            array_shift($struct_response);
        }
        $struct_response = array_slice($struct_response, 7, -1);

        $ImapLib = new ImapLib();
        $s = $ImapLib->getStructure($struct_response);
        return $s;

    }

    public function parseStructure($struc)
    {
        $new_struct = [];
        foreach ($struc as $key=>$row) {
            if (empty($row)) {
                continue;
            }
            $new_key = str_replace('"', "", $key);
            $row = str_replace('"', "", $row);
            if ($new_key == "name") {
                $row = $this->decodeMimeStr($row);
            }
            $new_struct[$new_key] = $row;
        }
        return $new_struct;
    }

    public function folderToName($folder)
    {
        if ($folder == "[Gmail]/已发邮件") {
            return '发件箱';
        } elseif ($folder == "[Gmail]/草稿") {
            return '草稿箱';
        } elseif ($folder == "[Gmail]/垃圾邮件") {
            return '垃圾箱';
        } elseif ($folder == "[Gmail]/已删除邮件") {
            return '已删除';
        }
        switch (strtolower($folder)) {
            case 'inbox':
                $folder = '收件箱';
                break;
            case 'sent messages'://兼容qq
                //case 'outbox':
            case 'sent':
            case '[gmail]/已发邮件':
                $folder = '发件箱';
                break;
            case 'drafts':
            case 'draft':
                $folder = '草稿箱';
                break;
            case 'deleted messages':
            case 'deleted':
            case 'delete':
            case 'trash':
                $folder = '已删除';
                break;
            case 'spam':
            case 'bulk mail';//yahoo
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
            case '草稿夹':
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
            case '已发送的营销邮件':
                $folder_type = SocketImapLib::FOLDER_TYPE_SENT_SALE;
                break;
        }
        return $folder_type;
    }

    public function getTmpPath($mail_uid = '')
    {
        $tmp_file = sys_get_temp_dir() ."/". md5($mail_uid);
        return $tmp_file;
    }


    /**
     * parse a multi-part mime message part
     *
     * @param $array array low level parsed BODYSTRUCTURE response segment
     * @param $part_num int IMAP message part number
     *
     * @return array structure representing the MIME format
     */
    public function parse_multi_part($array, $part_num) {
        $struct = array();
        $index = 0;
        foreach ($array as $vals) {
            if ($vals[0] != '(') {
                break;
            }
            $type = strtolower($vals[1]);
            $sub = strtolower($vals[2]);
            $part_type = 1;
            switch ($type) {
                case 'message':
                    switch ($sub) {
                        case 'delivery-status':
                        case 'external-body':
                        case 'disposition-notification':
                        case 'rfc822-headers':
                            //case 'rfc822':
                            break;
                        default:
                            $part_type = 2;
                            break;
                    }
                    break;
            }
            if ($vals[0] == '(' && $vals[1] == '(') {
                $part_type = 3;
            }
            if ($part_type == 1) {
                $struct[$part_num] = $this->parse_single_part(array($vals));
                $part_num = $this->update_part_num($part_num);
            }
            elseif ($part_type == 2) {
                $parts = $this->split_toplevel_result($vals);
                $struct[$part_num] = $this->parse_rfc822($parts[0], $part_num);
                $part_num = $this->update_part_num($part_num);
            }
            else {
                $parts = $this->split_toplevel_result($vals);
                $struct[$part_num]['subs'] = $this->parse_multi_part($parts, $part_num.'.1');
                $part_num = $this->update_part_num($part_num);
            }
            $index++;
        }
        if (isset($array[$index][0])) {
            $struct['type'] = 'message';
            $struct['subtype'] = $array[$index][0];
        }
        return $struct;
    }

    /**
     * A single message part structure. This is a MIME type in the message that does NOT contain
     * any other attachments or additonal MIME types
     *
     * @param $array array low level parsed BODYSTRUCTURE response segment
     *
     * @return array strucutre representing the MIME format
     */
    public function parse_single_part($array) {
        $vals = $array[0];
        array_shift($vals);
        array_pop($vals);
        $atts = array('name', 'filename', 'type', 'subtype', 'charset', 'id', 'description', 'encoding',
            'size', 'lines', 'md5', 'disposition', 'language', 'location', 'att_size', 'c_date', 'm_date');
        $res = array();
        if (count($vals) > 7) {
            $res['type'] = strtolower(trim(array_shift($vals)));
            $res['subtype'] = strtolower(trim(array_shift($vals)));
            if ($vals[0] == '(') {
                array_shift($vals);
                while($vals[0] != ')') {
                    if (isset($vals[0]) && isset($vals[1])) {
                        $res[strtolower($vals[0])] = $vals[1];
                        $vals = array_splice($vals, 2);
                    }
                }
                array_shift($vals);
            }
            else {
                array_shift($vals);
            }
            $res['id'] = array_shift($vals);
            $res['description'] = array_shift($vals);
            $res['encoding'] = strtolower(array_shift($vals));
            $res['size'] = array_shift($vals);
            if ($res['type'] == 'text' && isset($vals[0])) {
                $res['lines'] = array_shift($vals);
            }
            if (isset($vals[0]) && $vals[0] != ')') {
                $res['md5'] = array_shift($vals);
            }
            if (isset($vals[0]) && $vals[0] == '(') {
                array_shift($vals);
            }
            if (isset($vals[0]) && $vals[0] != ')') {
                $res['disposition'] = array_shift($vals);
                if (strtolower($res['disposition']) == 'attachment' && $vals[0] == '(') {
                    array_shift($vals);
                    $len = count($vals);
                    $flds = array('filename' => 'name', 'size' => 'att_size', 'creation-date' => 'c_date', 'modification-date' => 'm_date');
                    $index = 0;
                    for ($i=0;$i<$len;$i++) {
                        if ($vals[$i] == ')') {
                            $index = $i;
                            break;
                        }
                        if (isset($vals[$i]) && isset($flds[strtolower($vals[$i])]) && isset($vals[($i + 1)]) && $vals[($i + 1)] != ')') {
                            $res[$flds[strtolower($vals[$i])]] = $vals[($i + 1)];
                            $i++;
                        }
                    }
                    if ($index) {
                        array_splice($vals, 0, $index);
                    }
                    else {
                        array_shift($vals);
                    }
                    while ($vals[0] == ')') {
                        array_shift($vals);
                    }
                }
            }
            if (isset($vals[0])) {
                $res['language'] = array_shift($vals);
            }
            if (isset($vals[0])) {
                $res['location'] = array_shift($vals);
            }
            foreach ($atts as $v) {
                if (!isset($res[$v]) || trim(strtoupper($res[$v])) == 'NIL') {
                    $res[$v] = false;
                }
                else {
                    if ($v == 'charset') {
                        $res[$v] = strtolower(trim($res[$v]));
                    }
                    else {
                        $res[$v] = trim($res[$v]);
                    }
                }
            }
            if (!isset($res['name'])) {
                $res['name'] = 'message';
            }
        }
        return $res;
    }

    /**
     * IMAP message part numbers are like one half integer and one half string :) This
     * routine "increments" them correctly
     *
     * @param $part string IMAP part number
     *
     * @return string part number incremented by one
     */
    public function update_part_num($part) {
        if (!strstr($part, '.')) {
            $part++;
        }
        else {
            $parts = explode('.', $part);
            $parts[(count($parts) - 1)]++;
            $part = implode('.', $parts);
        }
        return $part;
    }

    /**
     *  helper function for parsing bodystruct responses
     *
     *  @param $array array low level parsed BODYSTRUCTURE response segment
     *
     *  @return array low level parsed data split at specific points in the result
     */
    public function split_toplevel_result($array) {
        if (empty($array) || $array[1] != '(') {
            return array($array);
        }
        $level = 0;
        $i = 0;
        $res = array();
        foreach ($array as $val) {
            if ($val == '(') {
                $level++;
            }
            $res[$i][] = $val;
            if ($val == ')') {
                $level--;
            }
            if ($level == 1) {
                $i++;
            }
        }
        return array_splice($res, 1, -1);
    }


    /**
     * Parse a rfc822 message "container" part type
     *
     * @param $array array low level parsed BODYSTRUCTURE response segment
     * @param $part_num int IMAP message part number
     *
     * @return array strucutre representing the MIME format
     */
    protected function parse_rfc822($array, $part_num) {
        $res = array();
        array_shift($array);
        $res['type'] = strtolower(trim(array_shift($array)));
        $res['subtype'] = strtolower(trim(array_shift($array)));
        if ($array[0] == '(') {
            array_shift($array);
            while($array[0] != ')') {
                if (isset($array[0]) && isset($array[1])) {
                    $res[strtolower($array[0])] = $array[1];
                    $array = array_splice($array, 2);
                }
            }
            array_shift($array);
        }
        else {
            array_shift($array);
        }
        $res['id'] = array_shift($array);
        $res['description'] = array_shift($array);
        $res['encoding'] = strtolower(array_shift($array));
        $res['size'] = array_shift($array);
        $envelope = array();
        if ($array[0] == '(') {
            array_shift($array);
            $index = 0;
            $level = 1;
            foreach ($array as $i => $v) {
                if ($level == 0) {
                    $index = $i;
                    break;
                }
                $envelope[] = $v;
                if ($v == '(') {
                    $level++;
                }
                if ($v == ')') {
                    $level--;
                }
            }
            if ($index) {
                $array = array_splice($array, $index);
            }
        }
        //$res = $this->parse_envelope($envelope, $res);

        $parts = $this->split_toplevel_result($array);
        $res['subs'] = $this->parse_multi_part($parts, $part_num.'.1', $part_num);
        return $res;
    }
    /**
     * parse a message envelope
     *
     * @param $array array parsed message envelope from a BODYSTRUCTURE response
     * @param $res current BODYSTRUCTURE representation
     *
     * @return array updated $res with message envelope details
     */
    protected function parse_envelope($array, $res) {
        $flds = array('date', 'subject', 'from', 'sender', 'reply-to', 'to', 'cc', 'bcc', 'in-reply-to', 'message_id');
        foreach ($flds as $val) {
            if (strtoupper($array[0]) != 'NIL') {
                if ($array[0] == '(') {
                    array_shift($array);
                    $parts = array();
                    $index = 0;
                    $level = 1;
                    foreach ($array as $i => $v) {
                        if ($level == 0) {
                            $index = $i;
                            break;
                        }
                        $parts[] = $v;
                        if ($v == '(') {
                            $level++;
                        }
                        if ($v == ')') {
                            $level--;
                        }
                    }
                    if ($index) {
                        $array = array_splice($array, $index);
                        $res[$val] = $this->parse_envelope_address($parts);
                    }
                }
                else {
                    $res[$val] = array_shift($array);
                }
            }
            else {
                $res[$val] = false;
            }
        }
        return $res;
    }
}