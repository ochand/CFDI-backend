<?php
require_once 'core/PHPMailer/PHPMailerAutoload.php';
require_once 'core/PHPMailer/phpmailer.lang-es.php';


class Correo extends PHPMailer{
    
    

    function __construct($remitente) {
        $this->isSMTP();
        $this->SMTPDebug = 0;
        $this->Debugoutput = 'html';
        $this->Host = config::$mailHost;
        $this->Port = config::$mailPort;
        if(config::$mailSMTPSecure!=false)
            $this->SMTPSecure = config::$mailSMTPSecure;
        $this->SMTPAuth = true;
        $this->Username = config::$mailUser;
        $this->Password = config::$mailPasswd;
        $this->MailName = utf8_decode($remitente);        
        //$this->ReplyName = utf8_decode($respNombre);
        //$this->ReplyMail = $respEmail;
    }
    
    
    function Enviar($emisorNombre, $asunto, $respEmail, $respNombre, $dest, $factura, $cuerpo) {        
        $this->setFrom($this->Username, utf8_decode($emisorNombre));
        $this->addAddress($dest[0], $dest[1]);
        $this->addReplyTo($respEmail, utf8_decode($respNombre));        
        $this->Subject = utf8_decode($asunto);
        $html = file_get_contents('core/contents_header.html');
        $html .= $cuerpo;
        $html .= file_get_contents('core/contents_footer.html');
        $this->msgHTML($html);        
        $this->AltBody = 'Su visor de correo no soporta HTML';        
        $this->addAttachment($factura[0]);
        $this->addAttachment($factura[1]);
        
        if (!$this->send()) {
            return false;
        } else {
            return true;
        }
    }
    
}