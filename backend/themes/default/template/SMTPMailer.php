<?php
/*************************************************************
 Description: PHP Class for sending SMTP Mail
 Author     : halojoy  https://github.com/halojoy
 Copyright  : 2018 halojoy
 License    : MIT License  https://opensource.org/licenses/MIT
 *************************************************************/

Class SMTPMailer
{
    private $server = 'smtp.gmail.com';
    private $port   = 587;
    private $secure = 'tls';
    private $username = '';
    private $password = '';
    public $to = array();
    public $from = array();
    public $cc = array();
    public $bcc = array();
    public $reply_to = array();
    public $subject = 'No subject';
    public $body = '';
    public $text = '';
    public $file = array();
    public $charset = 'UTF-8';
    public $transferEncoding = '8bit';
    private $headers;
    private $ahead;
    private $sock;
    private $hostname;
    private $local;
    private $log = array();

    public function __construct($server=false, $port=false, $secure=false)
    {
        if (file_exists(__DIR__ . '/config_smtp.php')) {
            include __DIR__ . '/config_smtp.php';
            $this->server = $cfg_server;
            $this->port = $cfg_port;
            $this->secure = $cfg_secure;
            $this->username = $cfg_username;
            $this->password = $cfg_password;
        }
        if ($server !== false) {
            $this->server = $server;
            $this->username = '';
            $this->password = '';
        }
        if ($port !== false) $this->port = $port;
        if ($secure !== false) $this->secure = $secure;

        $this->hostname = $this->server;
        $this->secure = strtolower($this->secure);
        if ($this->secure === 'tls') $this->hostname = 'tcp://' . $this->server;
        if ($this->secure === 'ssl') $this->hostname = 'ssl://' . $this->server;
        if (!empty($_SERVER['HTTP_HOST'])) $this->local = $_SERVER['HTTP_HOST'];
        elseif (!empty($_SERVER['SERVER_NAME'])) $this->local = $_SERVER['SERVER_NAME'];
        else $this->local = $_SERVER['SERVER_ADDR'] ?? 'localhost';
        if ($this->username) $this->from = array($this->username, '');
        if (!defined('NL')) define('NL', "\r\n");
    }

    public function Auth($user, $pass) { $this->username = $user; $this->password = $pass; }
    public function From($address, $name = '') { $this->from = array($address, $name); }
    public function addReplyTo($address, $name = '') { $this->reply_to[] = array($address, $name); }
    public function addTo($address, $name = '') { $this->to[] = array($address, $name); }
    public function addCc($address, $name = '') { $this->cc[] = array($address, $name); }
    public function addBcc($address, $name = '') { $this->bcc[] = array($address, $name); }
    public function Subject($subject) { $this->subject = $subject; }
    public function Body($html) { $this->body = $html; }
    public function Text($text) { $this->text = $text; }
    public function File($path) { $this->file[] = $path; }
    public function Charset($charset) { $this->charset = $charset; }
    public function TransferEncoding($encode) { $this->transferEncoding = $encode; }

    public function ShowLog()
    {
        echo '<pre><b>SMTP Mail Transaction Log</b><br>';
        print_r($this->log);
        echo '</pre>';
    }

    public function ShowHeaders()
    {
        echo '<pre><b>SMTP Mail Headers</b><br>';
        echo htmlspecialchars($this->doHeaders(false));
        echo '</pre>';
    }

    public function Send()
    {
        if (!$this->doHeaders()) return false;
        $user64 = base64_encode($this->username);
        $pass64 = base64_encode($this->password);
        $mailfrom = '<' . $this->from[0] . '>';
        $mailto = array();
        foreach (array_merge($this->to, $this->cc, $this->bcc) as $address) $mailto[] = '<' . $address[0] . '>';

        $this->sock = fsockopen($this->hostname, $this->port, $enum, $estr, 30);
        if (!$this->sock) {
            $this->log[] = 'Socket connection error: ' . $this->hostname;
            return false;
        }
        $this->log[] = 'CONNECTION: fsockopen(' . $this->hostname . ')';
        if (!$this->response('220')) return false;
        if (!$this->logreq('EHLO ' . $this->local, '250')) return false;

        if ($this->secure === 'tls') {
            if (!$this->logreq('STARTTLS', '220')) return false;
            if (!stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) return false;
            if (!$this->logreq('EHLO ' . $this->local, '250')) return false;
        }

        if (!$this->logreq('AUTH LOGIN', '334')) return false;
        if (!$this->logreq($user64, '334')) return false;
        if (!$this->logreq($pass64, '235')) return false;
        if (!$this->logreq('MAIL FROM: ' . $mailfrom, '250')) return false;
        foreach ($mailto as $address) {
            if (!$this->logreq('RCPT TO: ' . $address, '250')) return false;
        }
        if (!$this->logreq('DATA', '354')) return false;
        $this->log[] = htmlspecialchars($this->doHeaders(false));
        if (!$this->request($this->headers, '250')) return false;
        if (!$this->logreq('QUIT', '221')) return false;
        fclose($this->sock);
        return true;
    }

    private function logreq($cmd, $code)
    {
        $this->log[] = htmlspecialchars($cmd);
        return $this->request($cmd, $code);
    }

    private function request($cmd, $code)
    {
        if (!$this->sock || !is_resource($this->sock)) return false;
        if (fwrite($this->sock, $cmd . NL) === false) return false;
        return $this->response($code);
    }

    private function response($code)
    {
        stream_set_timeout($this->sock, 8);
        $result = fread($this->sock, 768);
        $meta = stream_get_meta_data($this->sock);
        if ($meta['timed_out'] === true) {
            fclose($this->sock);
            $this->log[] = '<b>Was a timeout in Server response</b>';
            return false;
        }
        $this->log[] = $result;
        if (substr($result, 0, 3) == $code) return true;
        fclose($this->sock);
        $this->log[] = '<b>SMTP Server response Error</b>';
        return false;
    }

    private function doHeaders($filedata = true)
    {
        if (empty($this->username) || empty($this->password)) return false;
        if (empty($this->from)) $this->from = array($this->username, '');
        if (empty($this->to) || !filter_var($this->to[0][0], FILTER_VALIDATE_EMAIL)) return false;
        if (strlen(trim($this->body)) < 3 && strlen(trim($this->text)) < 3) return false;
        $headerstring = '';
        $this->createHeaders($filedata);
        foreach ($this->ahead as $val) $headerstring .= $val . NL;
        $this->headers = rtrim($headerstring);
        return true;
    }

    private function createHeaders($filedata)
    {
        $this->ahead = array();
        $this->ahead[] = 'Date: ' . date('r');
        $this->ahead[] = 'To: ' . $this->formatAddressList($this->to);
        $this->ahead[] = 'From: ' . $this->formatAddress($this->from);
        if (!empty($this->cc)) $this->ahead[] = 'Cc: ' . $this->formatAddressList($this->cc);
        if (!empty($this->bcc)) $this->ahead[] = 'Bcc: ' . $this->formatAddressList($this->bcc);
        if (!empty($this->reply_to)) $this->ahead[] = 'Reply-To: ' . $this->formatAddressList($this->reply_to);
        $this->ahead[] = 'Subject: =?UTF-8?B?' . base64_encode($this->subject) . '?=';
        $this->ahead[] = 'Message-ID: ' . $this->generateMessageID();
        $this->ahead[] = 'X-Mailer: PHP/' . phpversion();
        $this->ahead[] = 'MIME-Version: 1.0';

        $boundary = md5(uniqid());
        if (empty($this->file) || !file_exists($this->file[0])) {
            if ($this->text && $this->body) {
                $this->ahead[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
                $this->ahead[] = '';
                $this->ahead[] = 'This is a multi-part message in MIME format.';
                $this->ahead[] = '--' . $boundary;
                $this->defContent('plain', 'text');
                $this->ahead[] = '--' . $boundary;
                $this->defContent('html', 'body');
                $this->ahead[] = '--' . $boundary . '--';
            } elseif ($this->text) {
                $this->defContent('plain', 'text');
            } else {
                $this->defContent('html', 'body');
            }
        } else {
            $this->ahead[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
            $this->ahead[] = '';
            $this->ahead[] = 'This is a multi-part message in MIME format.';
            $this->ahead[] = '--' . $boundary;
            if ($this->text) {
                $this->defContent('plain', 'text');
                $this->ahead[] = '--' . $boundary;
            }
            if ($this->body) {
                $this->defContent('html', 'body');
                $this->ahead[] = '--' . $boundary;
            }
            foreach ($this->file as $path) {
                if (file_exists($path)) {
                    $this->ahead[] = 'Content-Type: application/octet-stream; name="' . basename($path) . '"';
                    $this->ahead[] = 'Content-Transfer-Encoding: base64';
                    $this->ahead[] = 'Content-Disposition: attachment';
                    $this->ahead[] = '';
                    if ($filedata) $this->ahead[] = chunk_split(base64_encode(file_get_contents($path)));
                    $this->ahead[] = '--' . $boundary;
                }
            }
            $this->ahead[count($this->ahead)-1] .= '--';
        }
        $this->ahead[] = '.';
    }

    private function defContent($type, $msg)
    {
        $this->ahead[] = 'Content-Type: text/' . $type . '; charset="' . $this->charset . '"';
        $this->ahead[] = 'Content-Transfer-Encoding: ' . $this->transferEncoding;
        $this->ahead[] = '';
        $this->ahead[] = $this->transferEncoding == 'quoted-printable' ? quoted_printable_encode($this->$msg) : $this->$msg;
    }

    private function formatAddress($address) { return ($address[1] == '') ? $address[0] : '"' . $address[1] . '" <' . $address[0] . '>'; }
    private function formatAddressList($addresses)
    {
        $list = '';
        foreach ($addresses as $address) {
            if ($list) $list .= ', ' . NL . "\t";
            $list .= $this->formatAddress($address);
        }
        return $list;
    }
    private function generateMessageID()
    {
        return sprintf('<%s.%s@%s>', base_convert(microtime(), 10, 36), base_convert(bin2hex(openssl_random_pseudo_bytes(8)), 16, 36), $this->local);
    }
}
