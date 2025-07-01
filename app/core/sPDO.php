<?php

class sPDO {
    
    private static $instance = null;
    private static $contador = 0;
    private static $llamadas = 0;
    
    private function __construct() {}
    private function __clone() {
        trigger_error("no se puede clonar el objeto utilize singleton");     
    }


    public static function check() {
        return array("llamadas"=>self::$llamadas,"instancias"=>self::$contador);
    }
    
    public static function singleton() {
        $dsn =  "mysql:host=" .config::$db_host. ";";
        $dsn .= "dbname=" . config::$db_name;
        $user = config::$db_user;
        $password = config::$db_pass;
        self::$llamadas++;
        if( self::$instance == null ){
            self::$contador++;
            try{
                $attrs = array(PDO::MYSQL_ATTR_INIT_COMMAND=>"SET NAMES utf8");
                self::$instance = new PDO($dsn,$user,$password,$attrs);
                self::$instance->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);               
                self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);               
            }catch (PDOException $e){
                echo "No se puede conectar a la BD";
            }
        }
        return self::$instance;
    }
	
}