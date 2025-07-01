<?php
header ("Expires: Thu, 27 Mar 1980 23:59:00 GMT"); //la pagina expira en una fecha pasada
header ("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); //ultima actualizacion ahora cuando la cargamos
header ("Cache-Control: no-cache, must-revalidate"); //no guardar en CACHE
header ("Pragma: no-cache");

require_once('core/libs/tcpdf/config/lang/spa.php');
require_once('core/libs/tcpdf/tcpdf.php');

class PDF extends TCPDF{
    
      var $subtitulo;
      var $datos;
      var $uo;
      
      
    
    
      function __construct($uo,  $subtitulo, $datos=array()){
          
          
          $l = array(
                'a_meta_charset'=>  'UTF-8'
                ,'a_meta_dir'=>     'ltr'
                ,'a_meta_language' => 'es'
                ,'w_page' => 'página'
            );
          
          
          $this->uo = $uo;
          $this->datos = $datos;
          $this->subtitulo = $subtitulo;
          
            
                
            parent::__construct('P', 'mm', 'Letter');
            
            $this->SetCreator("UNIVERSIDAD AUOTNOMA DE SINALOA");
            $this->SetAuthor("DEPARTAMENTO DE INFORMATICA");
            $this->SetTitle("Módulo de ingresos - Trámites Oficiales");
            $this->SetMargins(15, 35, 15);
            $this->SetAutoPageBreak(TRUE, 15);
            $this->setImageScale(1.25);
            $this->setLanguageArray($l);
            $this->SetHeaderMargin(5);
            $this->setHeaderFont(Array("helvetica", '', 10));
            $this->SetFooterMargin(20);
            $this->setFooterFont(Array('helvetica', '', 8)); 
      }
       
      function header(){
            
            $this->setCellPaddings(1, .3, 1, .3);
            $this->SetFillColor(205,205,205);
            
            $image_file = "assets/images/logouas.jpg";
            $this->Image($image_file, PDF_MARGIN_LEFT, PDF_MARGIN_HEADER+1,15,'', 'JPG', '', 'T', false, 400, '', false, false, 0, false, false, false);


            $this->Ln();    
            $this->cell(18);
            $this->SetFont('helvetica', 'B', 14);
            $this->Cell(100, 4, 'UNIVERSIDAD AUTONÓMA DE SINALOA', 0 , true, 'C', 0, '', 0, false);
            $this->cell(18);
            $this->SetFont('helvetica', '', 10);
            $this->Cell(100, 4,'SECRETARÍA DE ADMINISTRACIÓN Y FINANZAS', 0 , true, 'C', 0, '', 0, false);
            $this->SetFont('helvetica', 'B', 8);
            $this->cell(18);
            $this->Cell(100, 4,$this->uo, 0 , true, 'C', 0, '', 0, false);
            $this->SetFont('helvetica', '', 8);
            $this->cell(18);
            $this->Cell(100, 4,$this->subtitulo, 0 , true, 'C', 0, '', 0, false);
            
            // ----------------------------------------------
            $this->SetFont('helvetica', '', 8);
            $this->SetFillColor(240,240,240);
            $this->SetY(7);
            
            if(count($this->datos)>0){
                foreach($this->datos as $title => $value){
                    $this->Cell(125);
                    $this->Cell(20, 4,$title, 1 , false, 'L', 1, '', 0, false);
                    $this->Cell(45, 4,$value, 1 , true, 'L', 0, '', 0, false);
                }
            }
            
            $this->SetY(30);
            $this->Cell(190, 0, '', 'B',true);
                                 
        }
        
        
        
        function checkBreak(){
            
            return $this->PageBreakTrigger;
            

        }
        
        
        function footer($activo=true){
            if($activo){
                $this->Cell(190, 0, '', 'B',true);
                $this->Ln();
                $this->Cell(95, 4,"Desarrollado por UAS :: Dirección de Informática ® 2011", 0 , false, 'L', 0, '', 0, false); // <<-- campo fecha de impresion
                $this->Cell(95, 4,'Páginas ' . $this->AliasNumPage. ' / ' . $this->AliasNbPages, 0 , false, 'R', 0, '', 0, false); // <<-- campo fecha de impresion
            }
        }
        
        
        
       
        
    
}