<?php
require_once('core/tcpdf/config/lang/spa.php');
require_once('core/tcpdf/tcpdf.php');
require_once('core/EnLetras.php');


class XML2PDF extends TCPDF {
	
	
	private $EnLetras;
	private $totalPag=0;
	//private $id_factura=0;
	private $db;
        private $logo;
        private $metodoPago;
	
	public function __construct($logo, $metodoPago){
		
            $this->EnLetras = new EnLetras();
            $this->logo = $logo;
            $this->metodoPago = $metodoPago;
            parent::__construct("P", "mm", "Letter", true, 'UTF-8', false);

	}
	
	
	private function createBox($title, $x, $y, $w, $h){
	
		// cuadro emisor
		$this->RoundedRect($x,$y,$w,$h,'2',"1001");
		$this->RoundedRect($x,$y,$w,6,'2',"1001","F",array(),array(20,20,20));
		
		
		$this->SetXY($x+3,$this->GetY()-8);
		
		$this->SetTextColor(255);
		$this->SetFillColor(0);
		$this->SetXY($x+3,$y);
		$this->SetFont('helvetica','BI',10);
		$this->Cell($w-6,5.5,$title,0,0,'C','1');
		$this->SetFillColor();
		$this->SetTextColor();

	}
	
	
	/**	
	* Método que imprime el pie de la factura
	*/
	private function xmltopdffoot($datos){
				
		//$this->printGlobalDescription();
		
		//-----------------------------------------------   TIMBRADO		
				
		$this->Ln();
		$this->SetXY(55,226);
		$this->SetFont('helvetica','',6);
		$this->Cell(100,4,"Cadena original del complemento de certificación digital del SAT",0,1,'L',false);
		$cadena = "||". $datos['TimbreFiscalDigital']['UUID'] ."|".$datos['TimbreFiscalDigital']['FechaTimbrado']  ."|".
						$datos['TimbreFiscalDigital']['selloCFD']. "|".  $datos['TimbreFiscalDigital']['noCertificadoSAT'] ."||";
		$this->SetX(55);
		$this->MultiCell(145,6,$cadena,0,'L',false,1);
		
	
		$this->SetX(55);
		$this->Cell(100,4,"SELLO DIGITAL DEL CFDI",0,1,'L',false);
		$this->SetX(55);
		$this->MultiCell(145,6,$datos['Comprobante']['sello'],0,'L',false,1);
		
		
		$this->SetX(55);
		$this->Cell(100,4,"SELLO DEL SAT",0,1,'L',false);
		$this->SetX(55);
		$this->MultiCell(145,6,$datos['TimbreFiscalDigital']['selloSAT'],0,'L',false,1);
						
		// - - - - - - - - - - - - - - - - - - - - -  -
                		
		$codigo = 	"?re=".$datos['emisor']['rfc'].
					"&rr=".$datos['receptor']['rfc'].
					"&tt=".number_format($datos['Comprobante']['total'],6,'.','').
					"&id=".$datos['TimbreFiscalDigital']['UUID'];
		
		$this->SetXY(20, 228);
		$this->write2DBarcode($codigo, 'QRCODE,H', '', '', 27.5, 27.5, array(), 'N');
		
		$this->Ln();
		$this->SetXY(10,265);
		$this->SetFont('helvetica','B',8);
		$this->Cell(100,4,"* Este documento es una representación impresa de un CFDI.",0,1,'L',false);
		
		$this->SetXY(185, 267);
		$this->SetFont('helvetica','',7);
		$this->Cell(20,6,"Página ". $this->getPage(). " de ".$this->totalPag,0,0,"R");
		
		
	}	
	
	
	/**
	* Método que imprime toda la cabecera de la factura
	*/
	private function xmltopdfheader($datos,$cancel){
				
		//add a watermask		
		//if ($cancel==3){
		//	$this->Image("lib/cfdi/tcpdf/cancelado.gif", 30, 75, 150, 150,"" ,'', $align='', $resize=false, $dpi=72, $palign='', $ismask=false, $imgmask=false, 0);				
		//}

		$this->Image('assets/images/'.$this->logo,12,12,50);
                                 
		//$this->createBox("EMISOR:",70,12,105,30);                
                
		$this->SetFont('helvetica', 'B', 8);		
                $this->SetXY(70,14);
		$this->Cell(70,4,$datos['emisor']['nombre'],0,0);
		$this->SetFont('helvetica', '', 8);
                $this->SetXY(70,18);
                $emiCalleNumero = $datos['DomicilioFiscal']['calle'] .' NO. '. $datos['DomicilioFiscal']['noExterior'].' '.$datos['DomicilioFiscal']['noInterior'];
		$this->Cell(70,4, $emiCalleNumero,0,0);                
                $this->Cell(50,4,"COL. ".$datos['DomicilioFiscal']['colonia'],0,1);
		$this->SetXY(70,22);                
                if (!empty($datos['DomicilioFiscal']['localidad'])) 
                    $this->Cell(70,4, "LOC. " . $datos['DomicilioFiscal']['localidad'],0,0);                
		$this->Cell(50,4, "MPIO. " . $datos['DomicilioFiscal']['municipio'],0,1);                
                $this->SetX(70);
		$this->Cell(70,4,"ESTADO: ".$datos['DomicilioFiscal']['estado'],0,0);				
		$this->Cell(50,4,"C.P. ".$datos['DomicilioFiscal']['codigoPostal'],0,1);
		$this->SetX(70);
		$this->Cell(70,4,"PAÍS: ".$datos['DomicilioFiscal']['pais'],0,0);
		$this->Cell(50,4,"RFC: ".$datos['emisor']['rfc'],0,1);		
                if (!empty($datos['ExpedidoEn']['municipio'])) {
                    $this->SetX(70);
                    $this->MultiCell(110,4,'EXPEDIDO EN: '.$datos['ExpedidoEn']['calle'].' '.$datos['ExpedidoEn']['noExterior'].' '.$datos['ExpedidoEn']['colonia'].' '.$datos['ExpedidoEn']['codigoPostal'].' '.$datos['ExpedidoEn']['municipio'].' '.$datos['ExpedidoEn']['estado'].' '.$datos['ExpedidoEn']['pais'],0,"L");                
                }
                $this->SetX(70);
		$this->MultiCell(110,4,($datos['RegimenFiscal']['regimen']!="")? $datos['RegimenFiscal']['regimen']: "",0,"L");
		
		$this->Ln();		
		
		$this->createBox("SERIE:",180,12,24,13);		
		
		$this->SetXY(180,19);
		
		$this->SetFillColor();
		$this->SetTextColor();
		$this->SetFont('helvetica', '', 12);
		$this->Cell(24,4,$datos['Comprobante']['serie'],0,1,'C',false);		
		
		$this->createBox("FOLIO:",180,29,24,13);
		
		$this->SetXY(180,36);
		$this->SetFillColor();
		$this->SetTextColor();
		$this->SetFont('helvetica', '', 12);
		$this->Cell(24,4,$datos['Comprobante']['folio'],0,1,'C',false);
		
		
		$this->createBox("RECEPTOR:",12,47,125,32);
		
		$this->SetFont('helvetica', 'B', 8);
		
		// - -- - - - - -  NOMBRE
		$this->SetXY(14,55);
		$this->Cell(15,4,"NOMBRE:",0,0);
		
		/*MultiCell($w,$h,$txt,$border = 0,$align = 'J',$fill = false,$ln = 1,$x = '',$y = '',
		$reseth = true,$stretch = 0,$ishtml = false,$autopadding = true,$maxh = 0,$valign = 'T',$fitcell = false) 	*/
		$rowh = $this->getNumLines($datos['receptor']['nombre'],110);
		$this->MultiCell(110,$rowh,$datos['receptor']['nombre'],0,'L',false,1);		
		
		// - - - - - - -  CALLE Y NUMERO
		$this->SetFont('helvetica', '', 8);
		$this->SetX(14);		
		$this->Cell(15,4,"CALLE:",0,0);
                $recCalleNumero =  $datos['Domicilio']['calle'] . ' ' . $datos['Domicilio']['noExterior'] . ' ' . $datos['Domicilio']['noInterior'];                                
		$rowh = $this->getNumLines($recCalleNumero,110);
		$this->MultiCell(110,$rowh,$recCalleNumero,0,'L',false,1);
                
                
				
		//  - - - - -  COLONIA
		$this->SetX(14);
		$this->Cell(15,4,"COLONIA:",0,0);
		$this->Cell(60,4,$datos['Domicilio']['colonia'],0,0);
		
		// - - - -- - -  CP
		$this->Cell(15,4,"CP:",0,0);
		$this->Cell(30,4,$datos['Domicilio']['codigoPostal'],0,1);
		
		// - - - - LOCALIDAD
                
		$this->SetX(14);
		$this->Cell(15,4, empty($datos['Domicilio']['localidad']) ? "" : "LOC:" ,0,0);
		$this->Cell(60,4, empty($datos['Domicilio']['localidad']) ? "" : $datos['Domicilio']['localidad'],0,0);
		
		// - - - ESTADO
		$this->Cell(15,4,"ESTADO:",0,0);
		$this->Cell(30,4,$datos['Domicilio']['estado'],0,1);
		
		// - - - - - - MUN/DEL
                $this->SetX(14);
		$this->Cell(15,4,"MPO/DEL:",0,0);
		$this->Cell(60,4,$datos['Domicilio']['municipio'],0,0);	
		
		// - - - - - - - PAIS
		$this->Cell(15,4,"PAIS:",0,0);
		$this->Cell(30,4,$datos['Domicilio']['pais'],0,1);	
                
                // - - - RFC
                $this->SetX(14);
		$this->Cell(15,4,"RFC:",0,0);
		$this->Cell(60,4,$datos['receptor']['rfc'],0,0);	
		
		$this->Ln();
		
		//--------------------------
		
		//$this->createBox("FOLIO (UUID):",140,47,64,30);
		//$this->Rect(140,47,64,30);
		$this->SetFillColor(0);
		$this->SetTextColor(255);
		$this->SetXY(140,47);
		$this->Cell(64,4,"FOLIO (UUDI)",0,1,'C',true);
		
		$this->SetX(140);
		$this->SetFillColor();
		$this->SetTextColor();
		$this->SetFont('helvetica', '', 8);
		$this->Cell(64,4,$datos['TimbreFiscalDigital']['UUID'],0,1,'C',false);
		
		$this->SetX(140);
		$this->SetFillColor(0);
		$this->SetTextColor(255);
		$this->Cell(64,4,"FECHA DEL COMPROBANTE",0,1,'C',true);
		
		$this->SetX(140);
		$this->SetFillColor();
		$this->SetTextColor();
		$this->SetFont('helvetica', '', 8);
		$this->Cell(64,4,$datos['Comprobante']['fecha'],0,1,'C',false);
		
		
		$this->SetX(140);
		$this->SetFillColor(0);
		$this->SetTextColor(255);
		$this->Cell(64,4,"FECHA DE AUTORIZACIÓN DEL SAT",0,1,'C',true);
		
		$this->SetX(140);
		$this->SetFillColor();
		$this->SetTextColor();
		$this->SetFont('helvetica', '', 8);
		$this->Cell(64,4,$datos['TimbreFiscalDigital']['FechaTimbrado'],0,1,'C',false);
		
		
		
		$this->SetX(140);
		$this->SetFillColor(0);
		$this->SetTextColor(255);
		$this->Cell(64,4,"NUMERO DE CERTIFICADO",0,1,'C',true);
		
		$this->SetX(140);
		$this->SetFillColor();
		$this->SetTextColor();
		$this->SetFont('helvetica', '', 8);
		$this->Cell(4);
		$this->Cell(20,4,"EMISOR:",0,0,'L',false);
		$this->Cell(45,4,$datos['Comprobante']['noCertificado'],0,1,'L',false);
		$this->SetX(140);
		$this->Cell(4);
		$this->Cell(20,4,"SAT:",0,0,'L',false);
		$this->Cell(45,4,$datos['TimbreFiscalDigital']['noCertificadoSAT'],0,1,'L',false);
		
		
		$this->SetXY(12,80);
		$this->SetFont('helvetica','B',8);
		$this->SetTextColor(255);
		$this->SetFillColor(100);
		$this->Cell(40,6,"LUGAR DE EXPEDICION: ",0,0,'L',true);
		$this->SetTextColor();
		$this->SetFillColor();
		$this->Cell(55,6,$datos['Comprobante']['LugarExpedicion'],0,1,'L',false);
		
		$this->SetX(12);
		$this->SetTextColor(255);
		$this->SetFillColor(100);
		$this->Cell(40,6,"TIPO DE COMPROBANTE: ",0,0,'L',true);
		$this->SetTextColor();
		$this->SetFillColor();
		$this->Cell(55,6,$datos['Comprobante']['tipoDeComprobante'],0,0,'L',false);
		$this->SetTextColor(255);
		$this->SetFillColor(100);
		$this->Cell(40,6,"CONDICIONES DE PAGO: ",0,0,'L',true);
		$this->SetTextColor();
		$this->SetFillColor();
		$this->Cell(45,6,$datos['Comprobante']['condicionesDePago'],0,1,'L',false);
		
		$this->SetX(12);
		$this->SetTextColor(255);
		$this->SetFillColor(100);
		$this->Cell(40,6,"METODO DE PAGO: ",0,0,'L',true);
		$this->SetTextColor();
		$this->SetFillColor();
                $metodoDePago = $this->metodoPago=='' ? $datos['Comprobante']['metodoDePago'] : $datos['Comprobante']['metodoDePago'].' '.$this->metodoPago;
		$this->Cell(55,6,$metodoDePago,0,0,'L',false);
		$this->SetTextColor(255);
		$this->SetFillColor(100);
		$this->Cell(40,6,"FORMA DE PAGO: ",0,0,'L',true);
		$this->SetTextColor();
		$this->SetFillColor();
		$this->Cell(45,6,$datos['Comprobante']['formaDePago'],0,1,'L',false);
		
		
		
		$this->SetX(12);
		$this->SetTextColor(255);
		$this->SetFillColor(100);
		$this->Cell(40,6,"NO. CTA DE PAGO: ",0,0,'L',true);
		$this->SetTextColor();
		$this->SetFillColor();		
                
               
		$this->Cell(50,6,($datos['Comprobante']['NumCtaPago']=="")? "No aplica" : $datos['Comprobante']['NumCtaPago'],0,1,'L',false);
		
		
		//----------------------------------------------- CABECERA DETALLADO
		//$this->Ln();
		$this->SetX(12);
		$this->Cell(20,6,"CANT",'TB',0,'C',false);
		$this->Cell(20,6,"UNIDAD",'TB',0,'C',false);
		$this->Cell(100,6,"DESCRIPCION",'TB',0,'C',false);
		$this->Cell(25,6,"P.U.",'TB',0,'C',false);
		$this->Cell(25,6,"IMPORTE",'TB',1,'C',false);
	
	}
	
	
	/**
	* Método que imprime los totales de la factura
	*/
	private function xmltopdftotales($datos){
		
		//-----------------------------------------------   SUBTOTAL		
		
		switch($datos['Comprobante']['Moneda']){ 
			case "USD": $moneda = "USD DOLARES"; $MN= "M.E."; break;
			case "NZD": $moneda = "NZD DOLARES"; $MN= "M.E."; break;
			case "EUR": $moneda = "EUR EUROS"; $MN= "M.E."; break;
			case "MXN": $moneda = "PESOS"; $MN= "M.N."; break;
                        default:
                            $moneda = "PESOS"; $MN= "M.N.";
		}
		
		$this->SetX(12);		
		$this->Cell(190,1,"",'B',1);
		$this->SetX(12);				
		$this->Cell(120,6,strtoupper("(SON:".$this->EnLetras->ValorEnLetras($datos['Comprobante']['total'],$moneda )." $MN)"),0,0,'L',false);
		$this->Cell(40,6,"SUB-TOTAL",0,0,'R',false);
		$this->Cell(30,6,number_format($datos['Comprobante']['subTotal'],2),0,1,'R',false);
		$this->SetX(12);
		$this->Cell(120);
		
	
		//-----------------------------------------------   DESCUENTO
		
		//$this->SetX(12);
		//$this->Cell(120,6,"(TIPO DE CAMBIO: ".number_format($datos['Comprobante']['TipoCambio'],2).")",0,0,'L',false);		
		$this->Cell(40,6,"DESCUENTO",0,0,'R',false);
		$this->Cell(30,6,number_format($datos['Comprobante']['descuento'],2),0,1,'R',false);
		
		//-----------------------------------------------   IVAs
                
                if ( isset($datos['detalleImpuestos']) ) {
                    foreach ($datos['detalleImpuestos'] as $impuesto) {
                        $this->SetX(12);
                        $this->Cell(120);
                        $this->Cell(40,6,$impuesto['impuesto']. " ". $impuesto['tasa'] ." %",0,0,'R',false);
                        $this->Cell(30,6,number_format($impuesto['importe'],2),0,1,'R',false);                    
                    }
                }
		
		//----------------------------------------------- ISH
		if (isset($datos['ImpuestosLocales']['totalImpuestosTrasladadosLocales'])){
			$this->SetX(12);
			$this->Cell(120);
			$this->Cell(40,6,"ISH 2 %",0,0,'R',false);
			$this->Cell(30,6,number_format($datos['ImpuestosLocales']['totalImpuestosTrasladadosLocales'],2),0,1,'R',false);
			
			//------------------------------------------- TOTAL
			$this->SetX(12);
			$this->Cell(120);
			$this->Cell(40,6,"T O T A L",0,0,'R',false);
			$this->Cell(30,6,number_format($datos['Comprobante']['total'],2),0,1,'R',false);
		}else{
			
			//-----------------------------------------------   TOTAL		
			$this->SetX(12);
			$this->Cell(120);
			$this->Cell(40,6,"T O T A L",0,0,'R',false);
			$this->Cell(30,6,number_format($datos['Comprobante']['total'],2),0,1,'R',false);
		}
			
	}
	
		
	/**
	* Método que pasa un xml a formato PDF 
	*/
	public function xmltopdf($xmlString, $output='I'){

		$datos = $this->xmltoarray($xmlString);	
                
                $uuid =$datos['TimbreFiscalDigital']['UUID'];

                
		// remove default header/footer
		$this->setPrintHeader(false);
		$this->setPrintFooter(false);

		// set document information
		$this->SetAuthor('ICRONOK Sistemas');
		
		//set margins
		$this->SetMargins(15, 20, 15);
		
		//set auto page breaks
		$this->SetAutoPageBreak(TRUE, 5);
		
		//get total row
		$totalReg = count($datos["detalle"])-1;
		$totalReal = count($datos["detalle"]); 		 	
		
		//get total pages	
		if (($totalReal/28) < 1 ){
			$this->totalPag = 1; 
		}elseif($totalReal%28==0){
			$this->totalPag = intval($totalReal/28);
		}else{
			$this->totalPag = intval($totalReal/28)+1;
		}
		
		//$this->totalPag=$totalReal;
		
		//------------------- iniciamos el proceso de impresion de paginas
		$j=0;
		
		while($j <= $totalReg){
			// add a page
			$this->AddPage();
			
			//add all headers
			$this->xmltopdfheader($datos,$cancel=null);			
			
			//Se imprime el detalle
			for ($i=$j; $i<=$totalReg; $i++){
				
				$this->SetFont('helvetica','',8);	
				$this->SetX(12);
				$rowcount = max($this->getNumLines($datos["detalle"][$i]['cantidad'],20),$this->getNumLines($datos["detalle"][$i]['unidad'], 30),$this->getNumLines($datos["detalle"][$i]['descripcion'],90),$this->getNumLines($datos["detalle"][$i]['valorUnitario'],20),$this->getNumLines($datos["detalle"][$i]['importe'],20));			
							
				$this->MultiCell(20,$rowcount,$datos["detalle"][$i]['cantidad'],0,'C',false,0);
				$this->MultiCell(30,$rowcount,$datos["detalle"][$i]['unidad'],0,'L',false,0);
			
				$this->SetX(152);
				$this->MultiCell(25,$rowcount,number_format($datos["detalle"][$i]['valorUnitario'],2),0,'R',false,0);
				
				$this->SetX(177);
                                
				$this->MultiCell(25,$rowcount,number_format($datos["detalle"][$i]['importe'],2),0,'R',false,0);			
				
				$this->SetX(60);
				
				//if (($datos["detalle"][$i]['noIdentificacion'] >= 1020101 && $datos["detalle"][$i]['noIdentificacion'] <= 1020240) || ($datos["detalle"][$i]['noIdentificacion']>= 1020301 && $datos["detalle"][$i]['noIdentificacion'] <= 1020624))
					//$this->MultiCell(100,$rowcount,$datos["detalle"][$i]['descripcion'],0,'J',false,1);
				//else
				$this->MultiCell(90,$rowcount,$datos["detalle"][$i]['descripcion'],0,'L',false,1);				
													
				//si se llega al final del area de impresion se continua en la siguiente página
				if ($i == $totalReg){
					if ($this->GetY()>180){
						$this->totalPag++;
						$this->xmltopdffoot($datos);
						$this->AddPage();
						$this->xmltopdfheader($datos,$cancel=null);
						$this->xmltopdftotales($datos);
					}else{
						$this->xmltopdftotales($datos);
					}	
				}
				if ($this->GetY()>207){
					break; //rompemos para continuar en la siguiente hoja
				}							
			}
			$i++; //aumentamos i para continuar la impresión en la siguiente fila
			$j=$i; //asignamos i a j para actualizar el recorrido del primer ciclo				
			
			
			
			//se imprime el pie			
                        $this->xmltopdffoot($datos);
		}
                
									
		//Close and output PDF document
                
                return $this->Output("$uuid.pdf", $output);
	}
	
		
	
	
	public function xmltoarray($xml){
		//$xml = file_get_contents($url);
		$xr = new XMLReader();
		$xr->XML($xml);
		
		while ($xr->read()){
			if (XMLReader::ELEMENT == $xr->nodeType){
			
				switch($xr->localName){
					case "Comprobante" :
						$datos['Comprobante']['serie'] = $xr->getAttribute('serie');
						$datos['Comprobante']['folio'] = $xr->getAttribute('folio');
						$datos['Comprobante']['fecha'] = $xr->getAttribute('fecha');
						$datos['Comprobante']['formaDePago'] = $xr->getAttribute('formaDePago');
						$datos['Comprobante']['condicionesDePago'] = $xr->getAttribute('condicionesDePago');						
						$datos['Comprobante']['metodoDePago'] = $xr->getAttribute('metodoDePago');
						$datos['Comprobante']['LugarExpedicion'] = $xr->getAttribute('LugarExpedicion');
						$datos['Comprobante']['NumCtaPago'] = $xr->getAttribute('NumCtaPago');
						$datos['Comprobante']['noCertificado'] = $xr->getAttribute('noCertificado');
						$datos['Comprobante']['subTotal'] = $xr->getAttribute('subTotal');
						$datos['Comprobante']['TipoCambio'] = $xr->getAttribute('TipoCambio');
						$datos['Comprobante']['Moneda'] = $xr->getAttribute('Moneda');
						$datos['Comprobante']['total'] = $xr->getAttribute('total');
						$datos['Comprobante']['descuento'] = $xr->getAttribute('descuento');
						$datos['Comprobante']['tipoDeComprobante'] = $xr->getAttribute('tipoDeComprobante');
						
						$datos['Comprobante']['sello'] = $xr->getAttribute('sello');
						
						break;
						
					case "Emisor" :
						$datos['emisor']['rfc'] = $xr->getAttribute('rfc');
						$datos['emisor']['nombre'] = $xr->getAttribute('nombre');
						break;
						
					case "DomicilioFiscal" :
						$datos['DomicilioFiscal']['calle'] = $xr->getAttribute('calle');
						$datos['DomicilioFiscal']['noExterior'] = $xr->getAttribute('noExterior');
						$datos['DomicilioFiscal']['noInterior'] = $xr->getAttribute('noInterior');
						$datos['DomicilioFiscal']['colonia'] = $xr->getAttribute('colonia');
						$datos['DomicilioFiscal']['localidad'] = $xr->getAttribute('localidad');
						$datos['DomicilioFiscal']['municipio'] = $xr->getAttribute('municipio');
						$datos['DomicilioFiscal']['estado'] = $xr->getAttribute('estado');
						$datos['DomicilioFiscal']['pais'] = $xr->getAttribute('pais');
						$datos['DomicilioFiscal']['codigoPostal'] = $xr->getAttribute('codigoPostal');
						break;
                                        
                                        case "ExpedidoEn" :
						$datos['ExpedidoEn']['calle'] = $xr->getAttribute('calle');
						$datos['ExpedidoEn']['noExterior'] = $xr->getAttribute('noExterior');
						$datos['ExpedidoEn']['noInterior'] = $xr->getAttribute('noInterior');
						$datos['ExpedidoEn']['colonia'] = $xr->getAttribute('colonia');
						$datos['ExpedidoEn']['localidad'] = $xr->getAttribute('localidad');
						$datos['ExpedidoEn']['municipio'] = $xr->getAttribute('municipio');
						$datos['ExpedidoEn']['estado'] = $xr->getAttribute('estado');
						$datos['ExpedidoEn']['pais'] = $xr->getAttribute('pais');
						$datos['ExpedidoEn']['codigoPostal'] = $xr->getAttribute('codigoPostal');
						break;
                                            
					case "RegimenFiscal" :
						$datos['RegimenFiscal']['regimen'] = $xr->getAttribute('Regimen');
						break;
					case "Receptor" :
						$datos['receptor']['rfc'] = $xr->getAttribute('rfc');
						$datos['receptor']['nombre'] = $xr->getAttribute('nombre');
						break;
						
					case "Domicilio" :
						$datos['Domicilio']['calle'] = $xr->getAttribute('calle');
						$datos['Domicilio']['noExterior'] = $xr->getAttribute('noExterior');
						$datos['Domicilio']['noInterior'] = $xr->getAttribute('noInterior');
						$datos['Domicilio']['colonia'] = $xr->getAttribute('colonia');
						$datos['Domicilio']['localidad'] = $xr->getAttribute('localidad');
						$datos['Domicilio']['municipio'] = $xr->getAttribute('municipio');
						$datos['Domicilio']['estado'] = $xr->getAttribute('estado');
						$datos['Domicilio']['pais'] = $xr->getAttribute('pais');
						$datos['Domicilio']['codigoPostal'] = $xr->getAttribute('codigoPostal');
						break;
					
					case "Conceptos" :
						while ($xr->read() && $xr->depth >1  )
						{
							if (XMLReader::ELEMENT == $xr->nodeType){
								$datos['detalle'][]= 
									array(	'cantidad' => $xr->getAttribute("cantidad"),
											'unidad' => $xr->getAttribute("unidad"),
											'noIdentificacion' => $xr->getAttribute("noIdentificacion"),
											'descripcion' => $xr->getAttribute("descripcion"),
											'valorUnitario' => $xr->getAttribute("valorUnitario"),
											'importe' =>(float)$xr->getAttribute("importe"));
							}
						}
						break;
					
					case "Impuestos" :
						$datos['Impuestos']['totalImpuestosTrasladados'] = $xr->getAttribute('totalImpuestosTrasladados');
						break;
						
					case "Traslados":
						
						while ($xr->read() && $xr->depth >1 )
						{
							if (XMLReader::ELEMENT == $xr->nodeType){
								$datos['detalleImpuestos'][]= 
									array(	'impuesto' => $xr->getAttribute("impuesto"),
											'tasa' => $xr->getAttribute("tasa"),
											'importe' => $xr->getAttribute("importe"));
							}
						}
						break;
															
					case "ImpuestosLocales": 
						$datos['ImpuestosLocales']['totalImpuestosTrasladadosLocales'] = $xr->getAttribute('TotaldeTraslados');
						break;
						
					case "TrasladosLocales":												
						$datos['detalleImpuestosLocales'][]= 
						array(	'impuesto' => $xr->getAttribute("ImpLocTrasladado"),
								'tasa' => $xr->getAttribute("TasadeTraslado"),
								'importe' => $xr->getAttribute("Importe"));												
						break;
						
					case "TimbreFiscalDigital":
						$datos['TimbreFiscalDigital']['FechaTimbrado'] = $xr->getAttribute("FechaTimbrado");
						$datos['TimbreFiscalDigital']['UUID'] = $xr->getAttribute("UUID");
						$datos['TimbreFiscalDigital']['noCertificadoSAT'] = $xr->getAttribute("noCertificadoSAT");
						$datos['TimbreFiscalDigital']['selloCFD'] = $xr->getAttribute("selloCFD");
						$datos['TimbreFiscalDigital']['selloSAT'] = $xr->getAttribute("selloSAT");
						break;	
						
				}
			}
			
			
		}
		return $datos;
	}
	
}// end class

