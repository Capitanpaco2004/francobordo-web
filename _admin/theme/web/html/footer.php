<?php
// Librerias
use util\event;
?>

                    </td>
                </tr>
            </table>
        </div>

		<div id="alrt-modal" class="wind-mdal zoom-anim-dialog mfp-hide">
			<div class="titu ax row aflex amiddle">
				<div class="col">
					<div>Denox</div>
					<small><?php echo MENU_WE_ARE_WAITING_FOR_YOU; ?></small>
				</div>
				<div class="col afluid mhide imge"></div>
			</div>
			<div id="alrt-target" class="ax row aflex"><i class="load fa fa-spinner fa-pulse"></i></div>
		</div>

		<div id="my-account" class="wind-mdal wind-mdal-anchor zoom-anim-dialog mfp-hide">
			<div class="titu ax row aflex amiddle">
				<div class="col">
					<div><?php echo sprintf(MENU_WELCOME, $login_first_name); ?></div>
					<small><?php echo $login_email_address; ?></small>
				</div>
				<div class="col afluid mhide imge">
					<img src="<?php echo "https://www.gravatar.com/avatar/" . md5( strtolower( trim( (string) $login_email_address ) ) ) . "?d=404&s=95";?>" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
					<span class="avtr-initials" style="display:none;width:95px;height:95px;font-size:32px;border-radius:50%;background:<?php echo $sGradient; ?>;"><?php echo $sInitials; ?></span>
				</div>
			</div>
			<div class="ax row aflex">
				<a href="<?php echo tep_href_link('admin_account.php'); ?>" title="<?php echo MENU_MY_ACCOUNT; ?>" class="col a04 m06"><div class="imge fa fa-user"></div><span class="titl"><?php echo MENU_MY_ACCOUNT; ?></span></a>
				<a href="<?php echo HTTPS_CATALOG_SERVER; ?>" title="<?php echo MENU_MY_STORE; ?>" class="col a04 m06"><div class="imge fa fa-shopping-cart"></div><span class="titl"><?php echo MENU_MY_STORE; ?></span></a>
				<a href="<?php echo tep_href_link('customers.php'); ?>" title="<?php echo MENU_CUSTOMERS; ?>" class="col a04 m06"><div class="imge fa fa-users"></div><span class="titl"><?php echo MENU_CUSTOMERS; ?></span></a>
				<a href="<?php echo tep_href_link('orders.php'); ?>" title="<?php echo MENU_ORDERS; ?>" class="col a04 m06"><div class="imge fa fa-shopping-basket"></div><span class="titl"><?php echo MENU_ORDERS; ?></span></a>
				<a href="<?php echo tep_href_link('logoff.php'); ?>" title="<?php echo MENU_LOGOFF; ?>" class="col a04 m06"><div class="imge fa fa-power-off"></div><span class="titl"><?php echo MENU_LOGOFF; ?></span></a>
			</div>
		</div>

		<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
		<script src="<?php echo THEME; ?>js/jquery-migrate-1.0.0.js"></script>
		<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/jquery-ui.min.js"></script>

		<?php
			// Paginas donde no se mostrara uniform
			$aUniForm = array( 'rma.php', 'create_order.php', 'create_account.php', 'refund_methods.php', 'return_product.php', 'return_text.php', 'returns.php', 'returns_invoice.php', 'returns_packingslip.php', 'returns_reasons.php', 'returns_status.php', 'feedmachine_admin.php', 'ExU.php', 'ExU_CDBARCOS.php', 'specialsbycategory.php', 'products_specifications.php', 'kiala_orders.php', 'stats_products_orders.php', 'customers_groups.php', 'stats_ad_results.php', 'supertracker.php', 'stock.php', 'stats_recover_cart_sales.php', 'xsell.php', 'support_status.php', 'exportador.php', 'importador.php', 'support_priority.php', 'support_department.php', 'support_admin.php', 'faq.php', 'news2.php', 'support.php', 'optional_related_products.php', 'mail.php', 'premade_comments.php', 'quick_updates.php', 'edit_orders.php', 'reviews.php', 'amenddb.php', 'easypopulate.php', 'mail.php', 'stats_monthly_sales.php', 'stats_sales.php', 'coupons.php', 'coupons_exclusions.php', 'orders_check.php', 'products_multi.php', 'specials_avanzado.php', 'newsletters.php', 'specials.php', 'featured.php', 'products_home.php', 'edit_orders_add_product.php', 'ups_configure.php' );

			if( !in_array( basename( $_SERVER['SCRIPT_NAME'] ), $aUniForm ) )
				echo '<script src="' . THEME . 'js/jquery.uniform.js" type="text/javascript"></script>';
		?>

		<script src="js/globalize.js" type="text/javascript"></script>
		<script src="js/globalize.culture.de-DE.js" type="text/javascript"></script>

        <script src="<?php echo THEME; ?>js/bootstrap.js" type="text/javascript"></script>
        <script src="<?php echo THEME; ?>js/magnific-popup.js" type="text/javascript"></script>
        <script src="<?php echo THEME; ?>js/functions.js" type="text/javascript"></script>
		<script src="<?php echo THEME; ?>js/toolbar.js" type="text/javascript"></script>
		<script src="<?php echo THEME; ?>js/jquery.dataTables.min.js" type="text/javascript"></script>
<?php echo getScriptsTinyMce(); ?>
        <script type="text/javascript">
			$(function() {
				$( ".dxdatepicker" ).datepicker(
				{
					changeMonth: true,
					changeYear: true
				});
				$.datepicker.regional['es'] = {
				  closeText: 'Cerrar',
				  prevText: '<Ant',
				  nextText: 'Sig>',
				  currentText: 'Hoy',
				  monthNames: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
				  monthNamesShort: ['Ene','Feb','Mar','Abr', 'May','Jun','Jul','Ago','Sep', 'Oct','Nov','Dic'],
				  dayNames: ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
				  dayNamesShort: ['Dom','Lun','Mar','Mié','Juv','Vie','Sáb'],
				  dayNamesMin: ['Do','Lu','Ma','Mi','Ju','Vi','Sá'],
				  weekHeader: 'Sm',
				  dateFormat: 'dd/mm/yy',
				  firstDay: 1,
				  isRTL: false,
				  showMonthAfterYear: false,
				  yearSuffix: ''};

				$.datepicker.regional['en'] = {
				  closeText: 'Close',
				  prevText: '<Bef',
				  nextText: 'Next>',
				  currentText: 'Today',
				  monthNames: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
				  monthNamesShort: ['Jan','Feb','Mar','Apr', 'May','Jun','Jul','Aug','Sep', 'Oct','Nov','Dec'],
				  dayNames: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
				  dayNamesShort: ['Sun','Mon','Tue','Wed','Thu','Fry','Sat'],
				  dayNamesMin: ['Su','Mo','Tu','We','Th','Fr','Sa'],
				  weekHeader: 'Sm',
				  dateFormat: 'dd/mm/yy',
				  firstDay: 1,
				  isRTL: false,
				  showMonthAfterYear: false,
				  yearSuffix: ''};

				  $.datepicker.setDefaults($.datepicker.regional[<?php echo '"' . substr((string) $language, 0, 2) . '"'; ?>]);
			});

            $(document).ready(function() {
				if ( $.isFunction($.fn.uniform) ) {
					$(".check, .check :checkbox, input:radio").uniform();
					$("select:not(.skip)").uniform();
					$('input:file[class!="skip"]').uniform({fileDefaultText: <?php echo '"' . TEXT_INPUT_FILE_EMPTY . '"'; ?>});
				}

                $("#langtabs > ul").tabs();
                $("#qpbpp").tabs();

				xOffset = 10;
				yOffset = 30;

				$(".tltp").hover(function(e)
				{
					this.t = this.title;
					this.title = "";
					var c = (this.t != "") ? "<br/>" + this.t : "";

					$("body").append("<p id='screenshot'><img src='../product_thumb.php?img=images/productos/"+ $(this).attr("rel") +"&w=195&h=195' alt='preview' />"+ c +"</p>");

					$("#screenshot")
						.css("top",(e.pageY - xOffset) + "px")
						.css("left",(e.pageX + yOffset) + "px")
						.fadeIn("fast");
				},
				function()
				{
					this.title = this.t;
					$("#screenshot").remove();
				});

				$(".tltp").mousemove(function(e)
				{
					$("#screenshot").css("top",(e.pageY - xOffset) + "px").css("left",(e.pageX + yOffset) + "px");
				});
            });
        </script>
		<?php
			echo $sJavascript ?? '';
			// Evento
			echo implode('', event::getInstance()->execute('back_office_footer_after_scripts'));
		?>
    </body>
</html>
