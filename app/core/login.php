<?php 
session_start();


if(!isset($_SESSION['Login']))
    $_SESSION['Login'] = array();

if( isset($_SESSION['Login']['empleado']) )
    header("Location:default.php");

if(isset($_POST['btnEntrar']) && !empty($_POST['txtUsuario']) && !empty($_POST['txtPassword'])  ) {
    $Mensaje="Clave de Acceso Incorrecta";
    require_once 'config.php';
    require_once 'Password.php';
    require_once 'sPDO.php';
    
    $db= sPDO::singleton();
    $db instanceof PDO;
    $pwd = new Password();

    $Login	= $_POST['txtUsuario'];
    $Password	= $_POST['txtPassword'];
    
    if( is_numeric($Login) ){ 
        
            $SQL = "select cu.C_USUARIO as usuario, e.nomcompleto, c.C_UO as uo, 
                    cu.C_CAJA as caja, c.D_CAJA as caja_descripcion,  
                    o.descripcion as uo_descripcion, u.password,
                    u.puesto
                    from CUO_USUARIOS cu
                    inner join CUO_CAJAS c on cu.C_CAJA = c.C_CAJA
                    INNER JOIN SIIA_CG..Padron_FE e ON cu.C_USUARIO = e.empleado
                    INNER JOIN SIIA_AF..siia_usuarios u ON u.id_user = convert(varchar(15),cu.C_USUARIO)
                    inner join SIIA_CG..Unidad_ORG o on c.C_UO = o.uorg
                    WHERE cu.VIGENCIA = 'A' and cu.C_USUARIO = :login";
            $stmt = $db->prepare($SQL);
            $stmt->bindParam(':login', $Login, PDO::PARAM_INT );
            $stmt->execute();
            $row = $stmt->fetch();
            
            if($row){
                $pass =  $pwd->decode( trim($row['password']),0  );
                if( strtoupper($Password) == strtoupper($pass) ){
                        $_SESSION['Login']=array();
                        $_SESSION['Login']['empleado']=$row['usuario'];
                        $_SESSION['Login']['nomcompleto']= $row['nomcompleto'];
                        $_SESSION['Login']['caja'] = $row['caja'];
                        $_SESSION['Login']['puesto'] = $row['puesto'];
                        $_SESSION['Login']['caja_descripcion'] = $row['caja_descripcion'];
                        $_SESSION['Login']['UO'] = $row['uo'];
                        $_SESSION['Login']['sistema'] = 5;
                        header("Location:../cuo/default.php");
                }
            }
    } 
}//end post

?>