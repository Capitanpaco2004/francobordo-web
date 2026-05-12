<?php
	echo '<div id="cmnt" class="ax row aflex">';
		$sCommts = '';

		$aRating = array();
		$aRating[5] = 0;
		$aRating[4] = 0;
		$aRating[3] = 0;
		$aRating[2] = 0;
		$aRating[1] = 0;
		
		while( $aComentario = tep_db_fetch_array( $aComentarios ) )
		{
			$aRating[$aComentario['reviews_rating']] += 1;

			$sCommts .= '<div class="wrpr">';
				$sCommts .= '<div class="top">';
					$sCommts .= '<span class="name">' . $aComentario['customers_name'] . '</span>';
					$sCommts .= '<span class="date">' . $aComentario['date_added'] . '</span>';
					$sCommts .= '<span class="star st' . $aComentario['reviews_rating'] . '"><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i></span>';
					$sCommts .= '<b>' . $aComentario['reviews_rating'] . '</b>';
				$sCommts .= '</div>';
				$sCommts .= '<div class="cmmt">' . $aComentario['reviews_text'] . '</div>';
				$sCommts .= '<div class="qstn"><span>' . MODULE_COMENTARIO_VENTAJAS . ':</span> ' . $aComentario['reviews_pros'] . '</div>';
				$sCommts .= '<div class="qstn"><span>' . MODULE_COMENTARIO_DESVENTAJAS . ':</span> ' . $aComentario['reviews_contras'] . '</div>';
				$sCommts .= '<div class="qstn"><span>' . MODULE_COMENTARIO_RECOMENDAR . '</span> ' . ($aComentario['reviews_recomendar'] == 1 ? MODULE_COMENTARIO_YES : MODULE_COMENTARIO_NO) . '</div>';
			$sCommts .= '</div>';
		}

		echo '<div class="col opin-left">';
			echo '<div id="cmtr-wrte-bg"></div>';
			
			echo '<div class="titu">' . ($nTotalComentarios > 0 ? $nTotalComentarios . MODULE_COMENTARIO_CANTIDAD : MODULE_COMENTARIO_BE_THE_FIRST) . '</div>';

			echo '<span class="star st' . $aProductoInfo['review_rating'] . '"><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i> <span>' .( ceil($aProductoInfo['review_rating'] *10)/10) . '/5</span></span>';
			
			echo '<div class="infr"><span class="stars">5 ' . MODULE_COMENTARIO_STARS . '</span><div class="bar"><span style="width: ' . ($nTotalComentarios > 0 && $aRating[5] > 0 ? (($aRating[5] * 100) / $nTotalComentarios) : '0' ) . '%;"></span></div><span class="prcj">' . ($nTotalComentarios > 0 && $aRating[5] > 0 ? round(($aRating[5] * 100) / $nTotalComentarios) : '0' ) . '%</span></div>';
			echo '<div class="infr"><span class="stars">4 ' . MODULE_COMENTARIO_STARS . '</span><div class="bar"><span style="width: ' . ($nTotalComentarios > 0 && $aRating[4] > 0 ? (($aRating[4] * 100) / $nTotalComentarios) : '0' ) . '%;"></span></div><span class="prcj">' . ($nTotalComentarios > 0 && $aRating[4] > 0 ? round(($aRating[4] * 100) / $nTotalComentarios) : '0' ) . '%</span></div>';
			echo '<div class="infr"><span class="stars">3 ' . MODULE_COMENTARIO_STARS . '</span><div class="bar"><span style="width: ' . ($nTotalComentarios > 0 && $aRating[3] > 0 ? (($aRating[3] * 100) / $nTotalComentarios) : '0' ) . '%;"></span></div><span class="prcj">' . ($nTotalComentarios > 0 && $aRating[3] > 0 ? round(($aRating[3] * 100) / $nTotalComentarios) : '0' ) . '%</span></div>';
			echo '<div class="infr"><span class="stars">2 ' . MODULE_COMENTARIO_STARS . '</span><div class="bar"><span style="width: ' . ($nTotalComentarios > 0 && $aRating[2] > 0 ? (($aRating[2] * 100) / $nTotalComentarios) : '0' ) . '%;"></span></div><span class="prcj">' . ($nTotalComentarios > 0 && $aRating[2] > 0 ? round(($aRating[2] * 100) / $nTotalComentarios) : '0' ) . '%</span></div>';
			echo '<div class="infr"><span class="stars">1 ' . MODULE_COMENTARIO_STARS . '</span><div class="bar"><span style="width: ' . ($nTotalComentarios > 0 && $aRating[1] > 0 ? (($aRating[1] * 100) / $nTotalComentarios) : '0' ) . '%;"></span></div><span class="prcj">' . ($nTotalComentarios > 0 && $aRating[1] > 0 ? round(($aRating[1] * 100) / $nTotalComentarios) : '0' ) . '%</span></div>';
			echo '<div id="new-cmtr" class="butt">' . MODULE_COMENTARIO_ESCRIBIR . '</div>';	
		echo '</div>';
		
		echo '<div class="opin-righ">';
			echo '<div id="cmtr-crrt-ajax"></div>';
			echo '<div id="cmtr-wrte-ajax"></div>';

			echo '<form style="display: none" class="xform" id="cmtr-form" name="form" method="post" ' .  $sFormulario . '>';
				echo '<p><input type="text" placeholder="' . MODULE_COMENTARIO_NOMBRE . '" name="customers_name" value="' . $sNombre . '" /></p>';
				echo '<p><textarea name="reviews_text" placeholder="' . MODULE_COMENTARIO_COMENTARIO . '">' . (tep_session_is_registered( 'dxreviews_text' ) ? $dxreviews_text : '') . '</textarea></p>';
				echo '<p><textarea name="reviews_pros" placeholder="' . MODULE_COMENTARIO_VENTAJAS . '"></textarea></p>';
				echo '<p><textarea name="reviews_contras" placeholder="' . MODULE_COMENTARIO_DESVENTAJAS . '"></textarea></p>';
				echo '<p><input type="checkbox" name="reviews_recomendar" value="1" id="recomendar" checked="checked"><label for="recomendar">' . MODULE_COMENTARIO_RECOMENDAR2 . ': <span></span></label></p>';
				echo '<div class="cmtr-star">' . MODULE_COMENTARIO_PUNTUACION . ' &nbsp;&nbsp; <div class="xform-star" data-type="star" data-name="rating"></div></div>';
				echo '<div class="clearfix">';
					echo '<input class="xbutton fright sbmt" type="submit" name="enviar" value="' . MODULE_COMENTARIO_ENVIAR . '"/>';
				echo '</div>';
				echo '<input type="hidden" id="product_id" name="product_id" value="' .  $_GET['products_id'] . '"/>';
			echo '</form>';
			if( $nTotalComentarios == 0)
				echo $messageStack->show( array( 'class' => 'wrng', 'text' => MODULE_COMENTARIO_SIN_COMENTARIOS ) );
			else
				echo $sCommts;
		echo '</div>';
	echo '</div>';
?>