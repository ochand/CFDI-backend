<?php
/**
 * Description of Usuario
 *
 * @author godoy
 */
class Usuario {
    
    
    private $db;


    public function __construct() {
        $this->db = sPDO::singleton();
    }
    
    public function getMenu(){
        $usuario = $_SESSION['capricho']['id'];
        $SQL = "select m.* from modulos m 
                inner join permisos p on p.id_modulo = m.id_modulo
                where p.id_usuario = :usuario";
        $stmt = $this->db->prepare($SQL);
        
        $stmt->bindParam(':usuario',$usuario,PDO::PARAM_STR);
        $stmt->execute();
        httpResp::setHeaders(200);
        return json_encode($stmt->fetchAll());
        
    }

    public function getPerfil() {
        $usuario = $_SESSION['capricho']['id'];

        $SQL = "SELECT u.ID, u.NAME, u.CARD, e.RFC, e.NOMBRE
                FROM USUARIOS u
                INNER JOIN EMISORES e on u.EMISOR=e.ID
                WHERE u.ID=:usuario";

        $stmt = $this->db->prepare($SQL);
        
        $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
        $stmt->execute();
        httpResp::setHeaders(200);
        return json_encode($stmt->fetch());

    }
    
    
    
}

?>
