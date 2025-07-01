<?php

/**
 * Description of modelBase
 *
 * @author godoy
 */
abstract class modelBase {
    
    protected $db;
    protected $emisor;
    protected $emisor_rfc;
    protected $ws_cfdi;
    protected $ws_login;
    protected $ws_passwd;
    protected $ws_metodoTimbrado;
    protected $ws_metodoTimbradoResult;
    protected $ws_metodoCancelar;
    protected $ws_metodoCancelarResult;
    protected $ws_metodoObtenerXML;
    protected $ws_metodoObtenerXMLResult;
    protected $usuario_id;
    protected $logo;
            
    function __construct() {                
        session_start();
        $this->db = sPDO::singleton();
        $this->emisor = $_SESSION['capricho']['emisor'];
        $this->emisor_rfc = $_SESSION['capricho']['emisor_rfc'];        
        $this->ws_cfdi = $_SESSION['capricho']['ws_cfdi'];
        $this->ws_login = $_SESSION['capricho']['ws_login'];
        $this->ws_passwd = $_SESSION['capricho']['ws_passwd'];        
        $this->ws_metodoTimbrado = $_SESSION['capricho']['ws_methods']['ws_metodoTimbrado'];
        $this->ws_metodoTimbradoResult = $_SESSION['capricho']['ws_methods']['ws_metodoTimbradoResult'];
        $this->ws_metodoCancelar = $_SESSION['capricho']['ws_methods']['ws_metodoCancelar'];
        $this->ws_metodoCancelarResult = $_SESSION['capricho']['ws_methods']['ws_metodoCancelarResult'];
        $this->ws_metodoObtenerXML = $_SESSION['capricho']['ws_methods']['ws_metodoObtenerXML'];
        $this->ws_metodoObtenerXMLResult = $_SESSION['capricho']['ws_methods']['ws_metodoObtenerXMLResult'];
        $this->usuario_id = $_SESSION['capricho']['id'];
        $this->logo = $_SESSION['capricho']['logo'];
    }

    protected function POST(){
        $error = array('error'=>array('code'=>500,'message'=>"JSON mal formado en petición post"));       
        $json = '';
        $raw = fopen("php://input", "r");
        while ($data = fread($raw, 1024)){
                $json .= $data;
        }
        fclose($raw);
        $arrayData = json_decode($json,true) or die(json_encode($error));
        
        return $arrayData;
    }
    
    //protected function paclogin($emisor){
    protected function paclogin($id_emisor){
        
        $this->db = sPDO::singleton();
        
        $SQL = "SELECT 
                    E.ID, E.RFC, E.WS_LOGIN, E.WS_PASSWD, 
                    E.PAC, P.WS_CFDI, E.LOGO
                FROM EMISORES E
                INNER JOIN PACS P ON E.PAC=P.RFC
                WHERE E.ID=:id_emisor";
        $stmt = $this->db->prepare($SQL);
        $stmt->bindParam(':id_emisor', $id_emisor, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();

        if($row){
            $_SESSION['capricho']=array();
            $this->emisor = $row['ID'];
            $this->emisor_rfc = $row['RFC'];
            $this->ws_cfdi = $row['WS_CFDI'];
            $this->ws_login = $row['WS_LOGIN'];
            $this->ws_passwd = $row['WS_PASSWD'];
            $this->logo = $row['LOGO'];
            $PAC = $row['PAC'];            
            
            $stmt->closeCursor();
            
            $SQL = "SELECT VARIABLE, METODO
                    FROM PAC_METODOS                
                    WHERE PAC=:PAC";
            $stmt = $this->db->prepare($SQL);
            $stmt->bindParam(':PAC', $PAC, PDO::PARAM_STR );
            $row = $stmt->execute();
            if(!$row){
                return false;
            }
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $key=>$val) {
                $ws_methods[$val['VARIABLE']] = $val['METODO'];
            }            
            $this->ws_metodoTimbrado = $ws_methods['ws_metodoTimbrado'];
            $this->ws_metodoTimbradoResult = $ws_methods['ws_metodoTimbradoResult'];
            $this->ws_metodoCancelar = $ws_methods['ws_metodoCancelar'];
            $this->ws_metodoCancelarResult = $ws_methods['ws_metodoCancelarResult'];
            
            return true;

        } else {        
            return false;
        }   
        
    }

    protected function PUT(){
        $error = array('error'=>array('code'=>500,'message'=>"JSON mal formado en petición post"));       
        $json = '';
        $raw = fopen("php://input", "r");
        while ($data = fread($raw, 1024)){
                $json .= $data;
        }
        fclose($raw);
        $arrayData = json_decode($json,true) or die(json_encode($error));
        
        return $arrayData;
    }
    
}

?>
