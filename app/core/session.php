<?php
session_start();   
define("MAX_IDLE_TIME",1800); //tiempo en que expira la sesion 30 mins


if(isset($_GET['op']) and $_GET['op']=="cerrar") {
    $_SESSION = array();
    session_destroy();
}//


if (!isset($_SESSION['timeout_idle'])) {
    $_SESSION['timeout_idle'] = time() + MAX_IDLE_TIME;
} else {
    if ($_SESSION['timeout_idle'] < time()) {    
            $_SESSION = array();
            session_destroy();
    } else {
            $_SESSION['timeout_idle'] = time() + MAX_IDLE_TIME;
    }
}



if(!isset($_SESSION['Login']) || !isset($_SESSION['Login']['empleado'])){
    if(isset($_GET['ajaxRequest']) && $_GET['ajaxRequest']==true){  
        $res = array('responseType'=>'Redirect','responseMsg'=>'Sesión finalizada','responseUrl'=>'login.php');
        die(json_encode($res));
    }else{
        header("location:login.php");
        die("termino la sesion, ingrese de nuevo");
    }
}



    

    
    
  
    

    
