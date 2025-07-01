<?php

final class config {
    
    
    /* CONEXIÓN A BASE DE DATOS */
    /* producción */
    public static $db_name = "icronokc_FACTURACION";
    public static $db_user = "icronokc_ACC2usr";
    public static $db_host = "localhost";
    public static $db_pass = "";
         
    /* desarrollo */
    /*
    public static $db_name = "FACTURACIONAB";
    public static $db_user = "chan";
    public static $db_host = "localhost";
    public static $db_pass = "";
    */
    /********************************/
    
    
    /* SERVIDOR DE CORREOS ELECTRÓNICOS */
    /* producción */
    public static $mailHost = "mail.icronok.com";
    public static $mailPort = 26;
    public static $mailSMTPSecure = false;
    public static $mailUser = "email@domain.com";
    public static $mailPasswd = "";
    public static $mailName =  "El Caprichito Mío";    
    public static $replyName = "Facturación - El Caprichito Mío";
    public static $replyMail = "pizzas.elcaprichito@gmail.com";
    /********************************/
    
