<?php

session_start();
error_reporting(0);
//setlocale(LC_ALL, 'es_MX.UTF8');
//date_default_timezone_set('America/Mazatlan');
define("MAX_IDLE_TIME",1800); //tiempo en que expira la sesion 30 mins

try{
    
    require_once 'core/config.php';
    require_once 'core/sPDO.php';
    require_once 'core/httpResp.php';
    require_once 'core/Oauth2.php';

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
    
    if($server->verifyResourceRequest(OAuth2\Request::createFromGlobals()) || isset($_SESSION['capricho']['usuario']) ){
        
        require_once 'router.php';
        list($action, $model) = explode('@', $match['target']);
        $file = "models/$model.php";
        if(file_exists($file) ){
            require_once $file;
            if(class_exists($model)){
                $callClass = new $model();
                if(is_callable(array($callClass, $action))){
                    httpResp::setHeaders (202);
                    echo $callClass->$action($match['params']);
                }
                else{
                    httpResp::setHeaders(404);
                    echo json_encode(array('error'=>array('code'=>404,'message'=>"El metodo $action, no existe en el controlador $model")));
                }
            }
            else{
                httpResp::setHeaders(404);
                echo json_encode(array('error'=>array('code'=>404,'message'=>"El modelo de datos $model no existe")));
            }
        }else{
           httpResp::setHeaders(404);
           echo json_encode(array('error'=>array('code'=>404,'message'=>"El archivo: $file no existe")));
        }
    }else{
        httpResp::setHeaders(403);
        echo json_encode(array('error'=>array('code'=>403,'message'=>"La sesión a caducado ")));
        die();
    }
    
}catch(Exception  $e){
    
    /**
     *  Aqui llegan los errorres no controlados si el manejador de base de datos
     *  tiene una transaccion abierta la cierra e imprime la descripcion del error,
     *  con el código de error 500 (Internal Server Error).
     */
    
    $error = array();
    
    httpResp::setHeaders(500);
    
    if(sPDO::singleton()->inTransaction()){
        sPDO::singleton()->rollback();
        $error = array('error'=>array('code'=>500,'message'=>"ROLBACK: No se efectuaron cambios, "));       
        
    }
    
    /* GUARDAR EN LA BITÁCORA */ 
    $descArr = explode("<json>", $e->getMessage());
    
    if(count($descArr)>1) {
        $eArr = json_decode($descArr[1],true);
        $msj = "Error en el metodo  $action, de la clase $model, Descripcion: " . $descArr[0];
        
        
        $eMetadata = array('Fecha'=>date('Y-m-d H:i:s'), 'Usuario'=>'', 'Mensaje'=>$msj, 'Debug'=>$e->getTrace(), 'Excepcion'=>$eArr);
        $text = json_encode($eMetadata).PHP_EOL;
        $bitacoraFile = 'temp/bitacora.txt';
        $bytes = file_put_contents($bitacoraFile, $text, FILE_APPEND | LOCK_EX);
    }
    
    $error['error']['message'] .=  "Error en el metodo  $action, de la clase $model, Descripcion: " . $descArr[0];
    $error['error']['debug'] = $e->getTrace();
    echo json_encode($error);
}





