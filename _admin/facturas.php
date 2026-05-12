<?php
  require('includes/application_top.php');
?>
<?php require(THEME . 'html/header.php'); ?>
<link rel="stylesheet" href="css/datepicker.css" type="text/css" />
<script type="text/javascript" src="js/jquery.js"></script>
<script type="text/javascript" src="js/datepicker.js"></script>
<script type="text/javascript" src="js/eye.js"></script>
<script type="text/javascript" src="js/utils.js"></script>
<script type="text/javascript" src="js/layout.js?ver=1.0.2"></script>
<!-- body //-->

  <div>
	<div class="toolbarHead">
		<div class="hdr-tlbr">
			<h1 class="pageHeading">Listado de Facturas</h1>
			<div class="forms">
				<?php
					if (!$_POST){
						$dia1=date("01/m/Y");
						$fecha_actual=date("d/m/Y");
					}else{
						$dia1=$_POST['desde'];
						$fecha_actual=$_POST['hasta'];
					}
				?>
				<form name="portada" method="post" action="<?php echo tep_href_link('facturas.php'); ?>" class="SmallText" enctype="multipart/form-data">
					<ul>
						<li>Fecha Inicio:</label> <input id="inputDate" name="desde" class="dxdatepicker cal-TextBox" style="width: 90px!important" value="<?php echo $dia1;?>" /></li>
						<li>Fecha Fin:</label> <input id="inputDate2" name="hasta" class="dxdatepicker cal-TextBox" style="width: 90px!important" value="<?php echo $fecha_actual;?>" /></li>
						<li><a href="#" onclick="$(this).closest('form').submit()" title="" class="buttonL bBlack" style="margin-top: -20px;">Filtrar</a></li>
					</ul>
				</form>
			</div>
	</div>
</div>

<?php if ($_POST) { ?>

<?php
	$fecha_desde = explode('/', $_POST['desde']);
	$fecha_hasta = explode('/', $_POST['hasta']);
	$sql = "select * from facturas where facturas_fecha >= '".$fecha_desde[2]."-".$fecha_desde[1]."-".$fecha_desde[0]."' and facturas_fecha <= '".$fecha_hasta[2]."-".$fecha_hasta[1]."-".$fecha_hasta[0]."' order by facturas_serie, facturas_numero asc";
	$act = tep_db_query($sql) or die($sql);
	if (tep_db_num_rows($act) == 0)
		$title_heading='No hay facturas para el rango de fecha seleccionado';
	else
		$title_heading='Listado de facturas desde el '.$_POST['desde'].' hasta el '.$_POST['hasta'];
?>

<div class="clear"></div>
<div class="box-tbl" style="width: 100%">
	<div class="box-head">
		<h6><?php echo $title_heading;?></h6>
		<div class="clear"></div>
	</div>
        
	<table cellpadding="0" cellspacing="0" width="100%" class="tAlt wGeneral">
		<thead>
			<tr>
				<td width="100px">Acciones</td>
				<td width="60px">Factura</td>
				<td width="30px">Fecha</td>
				<td>Pedido</td>
				<td>Base Imponible</td>
				<td>IVA 4%</td>
				<td>IVA 10%</td>
				<td>IVA 21%</td>
				<td>Total Factura</td>
			</tr>
		</thead>
		<?php
		//Reiniciamos todos los valores
			$total_base_imponible = 0;
			$total_iva_4 = 0;
			$total_iva_10 = 0;
			$total_iva_21 = 0;
			$total_iva = 0;
			$total_facturas = 0;		
					
			while ($row = tep_db_fetch_array($act)) {
				$bi_productos = obtener_bi_factura_productos($row['facturas_pedido_id']); 
				$iva_4 = obtener_iva_factura_4($row['facturas_pedido_id']);
				$iva_10 = obtener_iva_factura_10($row['facturas_pedido_id']);
				$iva_21 = obtener_iva_factura_21($row['facturas_pedido_id']);
				$envios = obtener_bi_factura_envios($row['facturas_pedido_id']);
				$bi_productos_final = $bi_productos;
				$bi_envios = $envios;
				$recargo = obtener_bi_factura_recargo($row['facturas_pedido_id']);
				$bi_recargo = $recargo;
				$bi = $bi_productos_final + $bi_envios + $bi_recargo - $iva_4 - $iva_10 - $iva_21;
				$iva_envios_21 = $bi_envios * 0.21;
				$iva_recargo_21 = $bi_recargo * 0.21;
				$iva_21_total = $iva_21 + $iva_envios_21 + $iva_recargo_21;
				$bi_total = $bi_productos + $bi_envios + $bi_recargo ;
				$iva_total = $iva_21 + $iva_4;
			
			//Miramos si es una factura o un Abono
			if($row['facturas_abono'] !='0' ? $op = '-' : $op = '+');
			
			if($op=='+'){
			  $total_base_imponible = $total_base_imponible + $bi;
			  $total_iva_4 = $total_iva_4 + $iva_4;
			  $total_iva_10 = $total_iva_10 + $iva_10;
			  $total_iva_21 = $total_iva_21 + $iva_21;
			  $total_iva = $total_iva + $iva_total;
			  $total_facturas = $total_facturas + obtener_total_factura($row['facturas_pedido_id']);
			  $op='';
			  $class='';
			}elseif($op=='-'){
			  $total_base_imponible = $total_base_imponible - $bi;
			  $total_iva_4 = $total_iva_4 - $iva_4;
			  $total_iva_10 = $total_iva_10 - $iva_10;
			  $total_iva_21 = $total_iva_21 - $iva_21;
			  $total_iva = $total_iva - $iva_total;
			  $total_facturas = $total_facturas - obtener_total_factura($row['facturas_pedido_id']);
			$class='rojo';			  
				}			
		?>
		<tbody>
			<tr class="<?php echo $class ?>">
				<td align="center">
					<div class="btn-group" style="display: inline-block; margin-bottom: -4px;">
		                <a class="buttonS bDefault" data-toggle="dropdown" href="#">Acciones<span class="caret"></span></a>
		                <ul class="dropdown-menu">
		                    <li><a href="<?php echo tep_href_link('invoice.php', tep_get_all_get_params(array('oID', 'action')) . 'oID=' . $row['facturas_pedido_id'] . '&facturas_id='.$row['facturas_numero']);?>" target="_blank"><span class="icos-preview"></span>Ver factura</a></li>
		                    <li><a href="<?php echo tep_href_link('orders.php', tep_get_all_get_params(array('oID', 'action')) . 'oID=' . $row['facturas_pedido_id']).'&action=edit';?>" target="_blank"><span class="icos-preview"></span>Ver Pedido</a></li>
		                </ul>
		            </div>
				 </td>
				<td align="center"><?php echo $row['facturas_serie'].'-'.$row['facturas_numero']; ?></td>
				<td align="center"><?php echo tep_date_short($row['facturas_fecha']); ?></td>
				<td align="center"><?php echo $row['facturas_pedido_id']; ?></td>
				<td align="center"><?php echo $op.redondear_dos_decimal_plus($bi); ?>€</td>
				<td align="center"><?php echo $op.redondear_dos_decimal_plus($iva_4); ?>€</td>
				<td align="center"><?php echo $op.redondear_dos_decimal_plus($iva_10); ?>€</td>
				<td align="center"><?php echo $op.redondear_dos_decimal_plus($iva_21); ?>€</td>
				<td align="center"><?php echo $op.redondear_dos_decimal_plus(obtener_total_factura($row['facturas_pedido_id'])); ?>€</td>
			</tr>
		</tbody>
		<?php } ?>
		<tbody>
			<tr>
				<td align="center" colspan="4"><strong>Resumen Total:</strong></td>
				<td align="center"><strong><?php echo redondear_dos_decimal_plus($total_base_imponible);?>€</strong></td>
				<td align="center"><strong><?php echo $op.redondear_dos_decimal_plus($total_iva_4); ?>€</strong></td>
				<td align="center"><strong><?php echo $op.redondear_dos_decimal_plus($total_iva_10); ?>€</strong></td>
				<td align="center"><strong><?php echo $op.redondear_dos_decimal_plus($total_iva_21); ?>€</strong></td>
				<td align="center"><strong><?php echo redondear_dos_decimal_plus($total_facturas);?>€</strong></td>
			</tr>
		</tbody>  
	</table>      
</div>


<?php /*
<tr>
	<td><b>&nbsp;</b></td>
	<td><b>FACTURA</b></td>
	<td><b>FECHA</b></td>
	<td align="right"><b>PEDIDO</b></td>
	<td align="right"><b>B.I.</b></td>
	<td align="right"><b>IVA (4%)</b></td>
	<td align="right"><b>IVA (10%)</b></td>
	<td align="right"><b>IVA (21%)</b></td>
	<td align="right"><b>TOTAL FACTURA</b></td>
</tr>		

<?php
			echo '<tr>
							<td class="'.$class.'"><a href="' . tep_href_link('invoice.php', tep_get_all_get_params(array('oID', 'action')) . 'oID=' . $row['facturas_pedido_id'] . '&facturas_id='.$row['facturas_numero']) . '" target="_blank">' . tep_image(DIR_WS_ICONS . 'preview.png', ICON_PREVIEW) . '</a></td>
							<td class="'.$class.'">'.$row['facturas_serie'].'-'.$row['facturas_numero'].'</td>
							<td class="'.$class.'">'.tep_date_short($row['facturas_fecha']).'</td>
							<td align="right" class="'.$class.'">'.$row['facturas_pedido_id'].'</td>
							<td align="right" class="'.$class.'">'.$op.''.redondear_dos_decimal_plus($bi).'€</td>
							<td align="right" class="'.$class.'">'.$op.''.redondear_dos_decimal_plus($iva_4).'€</td>
							<td align="right" class="'.$class.'">'.$op.''.redondear_dos_decimal_plus($iva_10).'€</td>
							<td align="right" class="'.$class.'">'.$op.''.redondear_dos_decimal_plus($iva_21).'€</td>
							<td align="right" class="'.$class.'">'.$op.''.redondear_dos_decimal_plus(obtener_total_factura($row['facturas_pedido_id'])).'€</td>
						</tr>';
		
?>
<tr><td colspan="9"><hr></td></tr>
<tr>
	<td align="right" colspan="5"><font color="red"><b><?php echo redondear_dos_decimal_plus($total_base_imponible);?>€</td>
	<td align="right"><font color="red"><b><?php echo redondear_dos_decimal_plus($total_iva_4);?>€</td>
	<td align="right"><font color="red"><b><?php echo redondear_dos_decimal_plus($total_iva_10);?>€</td>
	<td align="right"><font color="red"><b><?php echo redondear_dos_decimal_plus($total_iva_21);?>€</td>
	<td align="right"><font color="red"><b><?php echo redondear_dos_decimal_plus($total_facturas);?>€</td>
</tr>
<?php
	}
}	
?>
*/?>
<?php } ?>
  

<?php require(THEME . 'html/footer.php'); ?>

<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
