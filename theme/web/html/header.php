<?php

use util\event;

echo '<!DOCTYPE html>';
	echo '<html ' . HTML_PARAMS . '>';
		echo '<head>';
			getHeader();
		echo '</head>';
		echo '<body id="' . $language . '" class="' . getBodyClasses() . '">';
		echo join('', event::getInstance()->execute('front_office_header_body_after'));

			include( DIR_WS_INCLUDES . 'header.php' );

			if( isset( $_COOKIE['promotional_close'] ) )
			{
				if( strtotime( $_COOKIE['promotional_close'] ) <= strtotime( date( 'Y-m-d' ) ) )
				{
					unset( $_COOKIE['promotional_close'] );
					setcookie('promotional_close', null, -1, '/');
				}
			}

			echo '<nav id="menu-panel">';
				echo '<ul class="d-flex flex-column h-100">';
					echo '<li class="lnkfrs"><a class="link1 cl1" href="' . tep_href_link( FILENAME_PRODUCTS_NEW ) . '" title="' . TEXT_NEWS  . '"><i class="tt tt-43"></i>' . TEXT_NEWS . '</a></li>';
					echo '<li class="lnkfrs"><a class="link1 cl2" href="' . tep_href_link( FILENAME_SPECIALS ) . '" title="' . TEXT_SPECIALS . '"><i class="tt tt-41"></i>' . TEXT_SPECIALS . '</a></li>';
					echo '<li class="lnkfrs"><a class="link1 cl3 otlt" href="' . tep_href_link( FILENAME_ALLMANUFACTURERS ) . '" title="' . BOX_HEADING_MANUFACTURERS . '"><i class="tt tt-42"></i>' . BOX_HEADING_MANUFACTURERS . '</a></li>';

					echo printMenuCategories( $_aAllCategorias, 0 );

					if(defined('AFFILLIATES_STATUS') && AFFILLIATES_STATUS == 'true') {
						echo '<li><a class="link1 cl1 affiliates" href="' . tep_href_link('new-affiliate.php' ) . '" title="' . TEXT_AFFILIADOS . '"><i class="fas fa-user-circle"></i>' . TEXT_AFFILIADOS . '</a></li>';
					}

					echo '<li class="lnkfrs"><a class="link1 cl4" href="' . tep_href_link( FILENAME_INFORMATION ) . '" title="' . TEXT_INFORMATION . '"><i class="tt tt-14"></i>' . TEXT_INFORMATION . '</a></li>';

					echo '<li class="mt-auto infr-wrpr lnkfrs">';
						echo '<a class="row addr" href="' . tep_href_link( FILENAME_CONTACT_US ) . '" title="' . TEXT_CONTACT . '">';
							echo '<div class="icon tt tt-13"></div>';
							echo '<div>';
								echo '<div class="titu">910 60 71 03</div>';
								echo '<div class="text">';
									echo TEXT_FOOTER3;
								echo '</div>';
							echo '</div>';
						echo '</a>';
						echo '<a href="https://wa.me/910514987" class="row whsp" target="_blank">';
							echo '<div class="icon tt tt-36"></div>';
							echo '<div class="text">910 51 49 87</div>';
							echo '<div class="atnds">' . TEXT_ATENDEMOS . '<br/>Whatsapp</div>';
						echo '</a>';
						echo '<div class="row mail">';
							echo '<div class="icon tt tt-19"></div>';
							echo '<a class="text" href="mailto:info@francobordo.com">info@francobordo.com</a>';
						echo '</div>';
					echo '</li>';

				echo '</ul>';
			echo '</nav>';

			// Si no estamos registrados mostramos formulario de login
			if( !tep_session_is_registered( 'customer_id') )
			{
				echo '<div id="lgin" class="">';
					echo '<div class="web-cntd">';
						echo '<div class="close tright"><i class="far fa-times"></i></div>';
						echo '<div class="wrpr d-flex-dx">';
							echo '<div class="flex-grow-1 d-flex flex-column clnt-wrpr">';
								echo '<div class="stitu">' . TEXT_HE_COMPRADO . '</div>';
								echo '<div class="titu">' . TEXT_YA_CLIENTE . '</div>';
								echo '<form name="login" method="post" action="login.php?action=process">';
									echo '<div class="row">';
										echo '<input type="text" placeholder="E-mail" name="email_address"/>';
										echo '<input type="password" placeholder="' . substr( ENTRY_PASSWORD, 0, -1 ) . '" name="password"/>';
									echo '</div>';
									echo '<div class="frgt xform">';
										echo '<input type="checkbox" id="remember_me" name="remember_me"/><label for="remember_me"><span></span>' . ENTRY_REMEMBER_ME . '</label>';
										echo '<a href="' . tep_href_link( 'password_forgotten.php' ) . '" title="' . TEXT_OLVIDO . '" alt="' . TEXT_OLVIDO . '">' . TEXT_OLVIDO . '</a>';
									echo '</div>';
									echo '<input class="rdbt mt-auto" type="submit" value="' . TEXT_LOGIN_IN . '">';
								echo '</form>';
							echo '</div>';
							echo '<div class="sepa flex-grow-1"></div>';
							echo '<div class="flex-grow-1 d-flex flex-column new-wrpr">';
								echo '<div class="stitu">' . TEXT_QUIERO_REGISTR . '</div>';
								echo '<div class="titu">' . TEXT_NUEVO_CLIENTE . '</div>';
								echo '<div class="text">' . TEXT_NUEVO_INFO . '</div>';
								echo '<a href="' . tep_href_link( 'create_account.php' ) . '" title="' . HEADER_TITLE_CREATE_ACCOUNT . '" class="mt-auto bton">' . HEADER_TITLE_CREATE_ACCOUNT . '</a>';
							echo '</div>';
							echo '<div class="sepa flex-grow-1"></div>';
							echo '<div class="flex-grow-1 d-flex flex-column pro-wrpr">';
								echo '<div class="stitu">' . TEXT_ACCEDER . '</div>';
								echo '<div class="titu">' . TEXT_DISTRIBUIDOR . '</div>';
								echo '<div class="text">';
									echo '<p>' . TEXT_DISTRI_INFO . '</p>';
								echo '</div>';
								echo '<a href="' . tep_href_link('create_account_profesionales.php') . '" title="' . TEXT_REGISTRO_PROFES . '" class="mt-auto bton">' . TEXT_REGISTRO_PROFES . '</a>';
							echo '</div>';
						echo '</div>';
					echo '</div>';
				echo '</div>';
			}

		if( !isset( $_COOKIE['promotional_close'] ) && MODULE_SHIPPING_FREEAMOUNT_DISPLAY == 'True' )
			{
				echo '<div id="sphg-mess" class="actv">';
					echo '<div class="wrpr d-flex align-items-center justify-content-center web-cntd">';
						echo '<div class="tt tt-6 icon"></div>';
						echo '<div class="text">';
							echo '<span class="thide mhide">' . sprintf( TEXT_PORTES_GRATIS, ($customer_group_id == 0 ? MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER : MODULE_SHIPPING_FREEAMOUNT_AMOUNT_DISTRI), ($customer_group_id == 0 ? MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER : MODULE_SHIPPING_FREEAMOUNT_AMOUNT_DISTRI) ) . '</span>';
						echo '</div>';
						echo '<div class="close far fa-times"></div>';
					echo '</div>';
				echo '</div>';
			}

			echo '<div id="head">';
				echo '<div class="web-cntd d-flex align-items-center">';
					echo '<div id="main-fake" class="tt tt-21 main-fake"><i class="fal fa-bars"></i></div>';
					echo '<a href="' . tep_href_link( '/' ) . '" title="' . TITLE . '" alt="' . TITLE . '" class="logo"><img src="'.modifyImageForTheme('theme/web/images/custom/4' . ($languages_id == 3 ? '' : '_' . $languages_id) . '.png').'"/></a>';

					echo _getSearchForm();

					echo '<div class="pj thide mhide">';
						echo '<img src="' . modifyImageForTheme('theme/web/images/custom/3' . ($languages_id != 3 ? '_' . $languages_id : '') . '.png') . '">';
						echo '<img class="mnco" src="' . modifyImageForTheme('theme/web/images/custom/5.png') . '">';
					echo '</div>';

					echo '<div class="icons d-flex">';
						echo '<div class="srch dhide tt tt-24"></div>';

						if( tep_session_is_registered( 'customer_id') )
							echo '<a href="#my-account" class="mgp-inln lgin mhide"><div><i class="tt tt-17"></i><span>' . $customer_first_name . '</span></div></a>';
						else
							echo '<div class="lgin tt tt-17 mhide"></div>';

						echo '<a href="#chge-lnge" class="lnge mhide mgp-inln mhide" title="' . BOX_HEADING_LANGUAGES . '"><i class="tt tt-10"><span></span></i></a>';
						echo '<a href="' . tep_href_link( 'favoritos.php' ) . '" title="' . MY_WISH . '" class="fvrt tt tt-11 mhide"></a>';

						include( DIR_WS_COMPONENTS . 'shoppingCartDown.php' );

					echo '</div>';
				echo '</div>';
			echo '</div>';

			if( !ONLY_INDEX )
			{
				echo '<div id="titu1"' . (count( $breadcrumb->_trail ) > 2 || basename( $_SERVER['SCRIPT_NAME'] ) == 'search.php' ? ' class="scnd' . (basename( $_SERVER['SCRIPT_NAME'] ) == 'search.php' ? ' srch' : '') . '"' : '') . '>';
					echo '<div class="web-cntd d-flex align-items-center">';
						$bIcon = false;
						if( $current_category_id != 0 )
						{
							$aTrees = array_reverse( getRecursiveParentsCategories( $_aAllDatos, $current_category_id ) );

							foreach( $aTrees as $aTree )
							{
								if( $aTree['image'] != '' )
								{
									$sImagenCategoria = getImagenCategoria( $aTree['image'], 'menu', '', false );

									if( $sImagenCategoria && file_exists( DIR_WS_IMAGES . 'categorias/' . $sImagenCategoria ) )
									{
										$bIcon = true;
										echo tep_image( DIR_WS_IMAGES . 'categorias/' . $sImagenCategoria , $aCategoria['categories_name'], 45, 45, '', false, false )	;
										break;
									}
								}
							}
						}

						if( !$bIcon )
							echo '<div class="icon tt tt-' . (basename( $_SERVER['SCRIPT_NAME'] ) == 'search.php' ? '24' :'45') . '"></div>';

						echo '<div class="flex-wrap d-flex">';
							echo $breadcrumb->trailTitle('<span class="sepa">></span>', 1);
						echo '</div>';
					echo '</div>';
				echo '</div>';
			}

			if( ! preg_match( '/index|categories|product_info|search|^manufacturers|products_new|products_featured|best_sellers|specials/i', basename( $_SERVER['SCRIPT_NAME'] ) ) )
				echo '<div class="web-cntd prdt-cntd">';

			if (! preg_match( '/checkout/i', basename( $_SERVER['SCRIPT_NAME'] ) )  && isset($_GET['error_message']) && tep_not_null($_GET['error_message']))
				echo'<div class="mensaje web-cntd">'.htmlspecialchars(stripslashes(urldecode($_GET['error_message']))).'</div>';
?>
