<?php
	function list_down($aArgumentos, $sContenido)
	{
		$sHtml = '';

		$sHtml .= '<div class="list-down' . (isset( $aArgumentos['class'] ) ? ' ' . $aArgumentos['class'] : '') . '">';
			$sHtml .= '<div class="titl">' . $aArgumentos['title'] . (isset( $aArgumentos['class'] ) ? '<span class="flx"></span>' : '') . '</div>';
			$sHtml .= '<div class="infr">' . $sContenido . '</div>';
		$sHtml .= '</div>';

		return $sHtml;
	}

	function list_backgr($aArgumentos, $sContenido)
	{
		$sHtml = '';

		$sHtml = '<div style="padding: ' . $aArgumentos['padding'] . '; background: ' . $aArgumentos['background'] . '">
			' . $sContenido . '
		</div>';

		return $sHtml;
	}

	function list_title($aArgumentos, $sContenido)
	{
		$sHtml = '';

		$sHtml = '<div class="info-hlvt' . (isset( $aArgumentos['class'] ) ? ' ' . $aArgumentos['class'] : '') . '">
			' . $sContenido . '
		</div>';

		return $sHtml;
	}

	function list_image($aArgumentos, $sContenido)
	{

		$sHtml = '';

		$sHtml = '<div class="info-list-imge" style="background-image: url(\'' . $aArgumentos['image'] . '\');' . (isset( $aArgumentos['position'] ) ? ' background-position: ' . $aArgumentos['position'] . ';' : '') . (isset( $aArgumentos['padding'] ) ? ' padding: ' . $aArgumentos['padding'] . ';' : '') . (isset( $aArgumentos['bgcolor'] ) ? ' background-color: ' . $aArgumentos['bgcolor'] . ';' : '') . '">
			' . $sContenido . '
		</div>';

		return $sHtml;
	}

	add_shortcode( 'list_image', 'list_image' );
	add_shortcode( 'list_title', 'list_title' );
	add_shortcode( 'list_backgr', 'list_backgr' );
	add_shortcode( 'list_down', 'list_down' );


function list_categories_comission()
{
    global $_aAllCategorias;

    $html = '<table class="table-comission">';
    $html .= '<tr><th>Categoría</th><th>Comisión ventas España</th><th>Comisión ventas resto de Europa</th></tr>';
    foreach ($_aAllCategorias[0] as $category) {
        $html .= '
		<tr>
			<td><strong>' . $category['categories_name'] . '</strong></td>
			<td>' . sprintf('%.2f %%', Affiliates::adminGetComissionFromCategory($category['categories_id'], 'comission')) . '</td>
			<td>' . sprintf('%.2f %%', Affiliates::adminGetComissionFromCategory($category['categories_id'], 'comission_eu')) . '</td>
		</tr>';

        /*$subcategories = [];
        $subcategoriesEU = [];
        foreach ($_aAllCategorias[$category['categories_id']] as $subcategory) {
            $comission = Affiliates::adminGetComissionFromCategory($subcategory['categories_id'], 'comission');
            $comissionEU = Affiliates::adminGetComissionFromCategory($subcategory['categories_id'], 'comission_eu');

            $subcategories[$comission][] = $subcategory['categories_name'];
            $subcategoriesEU[$comissionEU][] = $subcategory['categories_name'];
        }

        foreach (array_keys($subcategories[$comission]) as $comission) {
			if (empty($subcategories[$comission])) {
				continue;
			}

            $html .= '
			<tr>
				<td>' . implode(', ', $subcategories[$comission]) . '</td>
				<td>' . sprintf('%.2f %%', $comission) . '</td>
				<td>' . sprintf('%.2f %%', $comission) . '</td>
			</tr>';
        }*/

    }
    $html .= '</table>';
    return $html;
}

add_shortcode('list_categories_comission', 'list_categories_comission');
