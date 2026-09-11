<?php
/*************************************************************
 Description: PHP Class for sending SMTP Mail
 Author     : halojoy  https://github.com/halojoy
 Copyright  : 2018 halojoy
 License    : MIT License  https://opensource.org/licenses/MIT

 Patched: 2026-07-22
 - Many shared-hosting providers silently block outbound SMTP
   ports (587/465/25) to external servers such as smtp.gmail.com,
   which makes fsockopen() hang and time out (not "refused").
 - This version fails fast, never calls exit() on a transport
   error (which used to kill the page instead of showing the
   normal "thanks" flow), and automatically falls back to PHP's
   built-in mail() (local sendmail/exim) if the raw SMTP socket
   can't connect or the handshake fails.
 *************************************************************/

Class SMTPMailer
{
    private $server = 'smtp.gmail.com';
    private $port   =  587;
    private $secure = 'tls';
    private $username = '';
    private $password = '';
    public $to       = array();
    public $from     = array();
    public $cc       = array();
    public $bcc      = array();
    public $reply_to = array();
    public $subject  = 'No subject';
    public $body     = '';
    public $text     = '';
    public $file     = array();
    public $charset  = 'UTF-8';
    public $transferEncoding = '8bit';
    public $lastError = '';
    private $headers;
    private $ahead;
    private $sock;
    private $hostname;
    private $local;
    private $log      = array();

    public function __construct($server=false, $port=false, $secure=false)
    {
        // Setup basic configuration
        if (file_exists('config_smtp.php')) {
            include 'config_smtp.php';
            $this->server   = $cfg_server;
            $this->port     = $cfg_port;
            $this->secure   = $cfg_secure;
            $this->username = $cfg_username;
            $this->password = $cfg_password;
        }
        if ($server !== false) {
            $this->server   = $server;
            $this->username = '';
            $this->password = '';
        }
        if ($port   !== false) $this->port   = $port;
        if ($secure !== false) $this->secure = $secure;

        // Define connection hostname and localhost
        $this->hostname = $this->server;
        $this->secure = strtolower($this->secure);
        if ($this->secure == 'tls') $this->hostname = 'tcp://'.$this->server;
        if ($this->secure == 'ssl') $this->hostname = 'ssl://'.$this->server;
        if (!empty($_SERVER['HTTP_HOST']))
            $this->local = $_SERVER['HTTP_HOST'];
        elseif (!empty($_SERVER['SERVER_NAME']))
            $this->local = $_SERVER['SERVER_NAME'];
        else
            $this->local = !empty($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : 'localhost';
        if ($this->username)
            $this->from = array($this->username, '');
        if (!defined('NL')) define("NL", "\r\n");
    }
 
    // Authentication Login
    public function Auth($user, $pass)
    {
        $this->username = $user;
        $this->password = $pass;
    }

    // Set from email address
    public function From($address, $name = '')
    {
        $this->from = array($address, $name);
    }

    // Add email reply to address
    public function addReplyTo($address, $name = '')
    {
        $this->reply_to[] = array($address, $name);
    }

    // Add recipient email address
    public function addTo($address, $name = '')
    {
        $this->to[] = array($address, $name);
    }

    // Add carbon copy email address
    public function addCc($address, $name = '')
    {
        $this->cc[] = array($address, $name);
    }

    // Add blind carbon copy email address
    public function addBcc($address, $name = '')
    {
        $this->bcc[] = array($address, $name);
    }

    // Set email subject
    public function Subject($subject)
    {
        $this->subject = $subject;
    }

    // Set email html body
    public function Body($html)
    {
        $this->body = $html;
    }

    // Set email plain text
    public function Text($text)
    {
        $this->text = $text;
    }

    // Add attachment file
    public function File($path)
    {
        $this->file[] = $path;
    }

    // Set charset. Default 'UTF-8'
    public function Charset($charset)
    {
        $this->charset = $charset;
    }

    // Set Content Transfer Encoding. Default '8bit'
    public function TransferEncoding($encode)
    {
        $this->transferEncoding = $encode;
    }

    // Display current log file
    public function ShowLog()
    {
        echo '<pre>';
        echo '<b>SMTP Mail Transaction Log</b><br>';
        print_r($this->log);
    }

    // Display current headers
    public function ShowHeaders()
    {
        echo '<pre>';
        echo '<b>SMTP Mail Headers</b><br>';
        echo htmlspecialchars($this->doHeaders(false));
    }

    // Send the mail: try direct SMTP first, fall back to local mail() on
    // any transport failure (e.g. host blocks outbound SMTP ports).
    public function Send()
    {
        $this->headers = $this->doHeaders();
        $user64 = base64_encode($this->username);
        $pass64 = base64_encode($this->password);
        $mailfrom = '<'.$this->from[0].'>';
        $mailto = array();
        foreach (array_merge($this->to, $this->cc, $this->bcc) as $address)
            $mailto[] = '<'.$address[0].'>';

        if ($this->sendViaSMTP($user64, $pass64, $mailfrom, $mailto)) {
            return true;
        }

        $this->log[] = 'SMTP send failed ('.$this->lastError.'); falling back to local mail()';
        error_log('[SMTPMailer] SMTP send failed: '.$this->lastError.' -- falling back to mail()');

        return $this->sendViaPHPMail();
    }

    // Attempt delivery over a raw SMTP socket. Returns false (never exits)
    // on any connection/handshake problem so Send() can fall back.
    private function sendViaSMTP($user64, $pass64, $mailfrom, $mailto)
    {
        // Fail fast (10s) instead of hanging for 30s when the port is
        // blocked outbound by the host's firewall.
        $this->sock = @fsockopen($this->hostname, $this->port, $enum, $estr, 10);
        if (!$this->sock) {
            $this->lastError = 'Could not connect to '.$this->hostname.':'.$this->port.
                                ' ('.$estr.'). This usually means the hosting '.
                                'provider is blocking outbound SMTP on this port.';
            $this->log[] = $this->lastError;
            return false;
        }
        $this->log[] = 'CONNECTION: fsockopen('.$this->hostname.')';

        try {
            $this->response('220');
            $this->logreq('EHLO '.$this->local, '250');

            if ($this->secure == 'tls') {
                $this->logreq('STARTTLS', '220');
                if (!@stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new Exception('TLS negotiation with '.$this->server.' failed');
                }
                $this->logreq('EHLO '.$this->local, '250');
            }

            $this->logreq('AUTH LOGIN', '334');
            $this->logreq($user64, '334');
            $this->logreq($pass64, '235');

            $this->logreq('MAIL FROM: '.$mailfrom, '250');
            foreach ($mailto as $address)
                $this->logreq('RCPT TO: '.$address, '250');

            $this->logreq('DATA', '354');
            $this->log[] = htmlspecialchars($this->doHeaders(false));
            $this->request($this->headers, '250');

            $this->logreq('QUIT', '221');
            fclose($this->sock);
            return true;

        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            $this->log[] = $this->lastError;
            if (is_resource($this->sock)) fclose($this->sock);
            return false;
        }
    }

    // Fallback transport: hands the message to the server's local
    // MTA (sendmail/exim/postfix) via PHP's mail(). This never leaves
    // the box over a network port, so it works even when the host
    // blocks outbound SMTP to external servers like Gmail.
    private function sendViaPHPMail()
    {
        if (empty($this->to) || !filter_var($this->to[0][0], FILTER_VALIDATE_EMAIL)) {
            $this->lastError = 'No valid "to" address for mail() fallback';
            $this->log[] = $this->lastError;
            return false;
        }

        $toList = array();
        foreach ($this->to as $addr) $toList[] = $this->formatAddress($addr);
        $toHeader = implode(', ', $toList);

        $headers   = array();
        $headers[] = 'From: '.$this->formatAddress(!empty($this->from) ? $this->from : array($this->username ?: 'noreply@'.$this->local, ''));
        if (!empty($this->cc))       $headers[] = 'Cc: '.$this->formatAddressList($this->cc);
        if (!empty($this->bcc))      $headers[] = 'Bcc: '.$this->formatAddressList($this->bcc);
        if (!empty($this->reply_to)) $headers[] = 'Reply-To: '.$this->formatAddressList($this->reply_to);
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset="'.$this->charset.'"';
        $headers[] = 'X-Mailer: PHP/'.phpversion();

        $subjectEncoded = '=?UTF-8?B?'.base64_encode($this->subject).'?=';
        $bodyContent = $this->body !== '' ? $this->body : nl2br(htmlspecialchars($this->text));

        $sent = @mail($toHeader, $subjectEncoded, $bodyContent, implode(NL, $headers));

        if (!$sent) {
            $this->lastError = 'PHP mail() fallback also failed (is a local mail transport agent configured on this server?)';
            $this->log[] = $this->lastError;
            return false;
        }

        $this->log[] = 'Sent via local mail() fallback';
        return true;
    }

    // Log command and do request
    private function logreq($cmd, $code)
    {
        $this->log[] = htmlspecialchars($cmd);
        $this->request($cmd, $code);
        return;
    }    

    // Send one command and test response
    private function request($cmd, $code)
    {
        fwrite($this->sock, $cmd.NL);
        $this->response($code);
        return;
    }

    // Read and verify response code. Throws instead of exit()ing so the
    // caller can close the socket cleanly and fall back to mail().
    private function response($code)
    {
        stream_set_timeout($this->sock, 8);
        $result = fread($this->sock, 768);
        $meta = stream_get_meta_data($this->sock);
        if ($meta['timed_out'] === true) {
            $this->log[] = 'Timeout waiting for server response';
            throw new Exception('SMTP timeout waiting for response (expected '.$code.')');
        }
        $this->log[] = $result;
        if (substr($result, 0, 3) == $code)
            return;
        throw new Exception('SMTP server returned unexpected response (expected '.$code.'): '.trim($result));
    }

    // Do create headers after precheck
    private function doHeaders($filedata = true)
    {
        // Precheck. Test if we have necessary data
        if (empty($this->username) || empty($this->password))
            exit('We need username and password for: <b>'.$this->server.'</b>');
        if (empty($this->from)) $this->from = array($this->username, '');
        if (empty($this->to) || !filter_var($this->to[0][0], FILTER_VALIDATE_EMAIL))
            exit('We need a valid email address to send to');
        if (strlen(trim($this->body)) < 3 && strlen(trim($this->text)) < 3)
            exit('We really need a message to send');

        // Create Headers
        $headerstring = '';
        $this->createHeaders($filedata);
        foreach($this->ahead as $val) {
            $headerstring .= $val.NL;
        }

        return rtrim($headerstring);
    }

    // Headers
    private function createHeaders($filedata)
    {
        $this->ahead = array();
        $this->ahead[] = 'Date: '.date('r');
        $this->ahead[] = 'To: '.$this->formatAddressList($this->to);
        $this->ahead[] = 'From: '.$this->formatAddress($this->from);
        if (!empty($this->cc)) {
            $this->ahead[] = 'Cc: '.$this->formatAddressList($this->cc);
        }
        if (!empty($this->bcc)) {
            $this->ahead[] = 'Bcc: '.$this->formatAddressList($this->bcc);
        }
        if (!empty($this->reply_to)) {
            $this->ahead[] = 'Reply-To: '.$this->formatAddressList($this->reply_to);
        }
        $this->ahead[] = 'Subject: '.'=?UTF-8?B?'.base64_encode($this->subject).'?=';
        $this->ahead[] = 'Message-ID: '.$this->generateMessageID();
        $this->ahead[] = 'X-Mailer: '.'PHP/'.phpversion();
        $this->ahead[] = 'MIME-Version: '.'1.0';

        $boundary = md5(uniqid());
        // Email contents
        if (empty($this->file) || !file_exists($this->file[0])) {
            if ($this->text && $this->body) {
                // add multipart
                $this->ahead[] = 'Content-Type: multipart/alternative; boundary="'
                                                            .$boundary.'"';
                $this->ahead[] = '';
                $this->ahead[] = 'This is a multi-part message in MIME format.';
                $this->ahead[] = '--'.$boundary;
                // add text
                $this->defContent('plain', 'text');
                $this->ahead[] = '--'.$boundary;
                // add html
                $this->defContent('html', 'body');
                $this->ahead[] = '--'.$boundary.'--';
            } elseif ($this->text) {
                // add text
                $this->defContent('plain', 'text');
            } else {
                // add html
                $this->defContent('html', 'body');
            }
        } else {
            // add multipart with attachment
            $this->ahead[] = 'Content-Type: multipart/mixed; boundary="'
                                                            .$boundary.'"';
            $this->ahead[] = '';
            $this->ahead[] = 'This is a multi-part message in MIME format.';
            $this->ahead[] = '--'.$boundary;
            if ($this->text) {
                // add text
                $this->defContent('plain', 'text');
                $this->ahead[] = '--'.$boundary;
            }
            if ($this->body) {
                // add html
                $this->defContent('html', 'body');
                $this->ahead[] = '--'.$boundary;
            }
            // spin thru attachments...
            foreach ($this->file as $path) {
                if (file_exists($path)) {
                    // add attachment
                    $this->ahead[] = 'Content-Type: application/octet-stream; '
                                                 .'name="'.basename($path).'"';
                    $this->ahead[] = 'Content-Transfer-Encoding: base64';
                    $this->ahead[] = 'Content-Disposition: attachment';
                    $this->ahead[] = '';
                    if ($filedata) {
                        // encode file contents
                        $contents = chunk_split(base64_encode(file_get_contents($path)));
                        $this->ahead[] = $contents;
                    }
                    $this->ahead[] = '--'.$boundary;
                }
            }   
            // add last "--"
            $this->ahead[count($this->ahead)-1] .= '--';
        }
        // final period
        $this->ahead[] = '.';

        return;
    }

    // Define and code the contents
    private function defContent($type, $msg)
    {
        $this->ahead[] = 'Content-Type: text/'.$type.'; charset="'.$this->charset.'"';
        $this->ahead[] = 'Content-Transfer-Encoding: '.$this->transferEncoding;
        $this->ahead[] = '';
        if ($this->transferEncoding == 'quoted-printable')
            $this->ahead[] = quoted_printable_encode($this->$msg);
        else
            $this->ahead[] = $this->$msg;
    }

    // Format email address (with name)
    private function formatAddress($address)
    {
        return ($address[1] == '') ? $address[0] : '"'.$address[1].'" <'.$address[0].'>';
    }

    // Format email address list
    private function formatAddressList($addresses)
    {
        $list = '';
        foreach ($addresses as $address) {
            if ($list) {
                $list .= ', '.NL."\t";
            }
            $list .= $this->formatAddress($address);
        }
        return $list;
    }

    private function generateMessageID()
    {
        return sprintf(
            "<%s.%s@%s>",
            base_convert(microtime(), 10, 36),
            base_convert(bin2hex(openssl_random_pseudo_bytes(8)), 16, 36),
            $this->local
        );
    }

}
