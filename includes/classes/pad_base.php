<?php
/*
      QT Pro Version 4.1

      pad_base.php

      Contribution extension to:
        osCommerce, Open Source E-Commerce Solutions
        http://www.oscommerce.com

      Copyright (c) 2004, 2005 Ralph Day
      Released under the GNU General Public License

      Based on prior works released under the GNU General Public License:
        QT Pro prior versions
          Ralph Day, October 2004
          Tom Wojcik aka TomThumb 2004/07/03 based on work by Michael Coffman aka coffman
          FREEZEHELL - 08/11/2003 freezehell@hotmail.com Copyright (c) 2003 IBWO
          Joseph Shain, January 2003
        osCommerce MS2
          Copyright (c) 2003 osCommerce

      Modifications made:
          11/2004 - Created
          12/2004 - Fix _draw_js_stock_array to prevent error when all attribute combinations are
                    out of stock.

*******************************************************************************************

      QT Pro Product Attributes Display Plugin

      pad_base.php - Base Class

      Class Name: pad_base

      This base class, although functional, is not intended to be installed and used
      directly.  It is extended by other classes to provide different display options
      for product attributes on the product information page (product_info.php).


      Methods:

        pad_base                            constructor
        _SetConfigurationProperties         set local properties from DB config constants
        draw                                draw the product attributes
        _draw_table_start                   draw start of the table to enclose the attributes display
        _draw_stocked_attributes            draw attributes that stock is tracked for
        _draw_nonstocked_attributes         draw attributes that stock is not tracked for
        _draw_table_end                     draw end of the table to enclose the attributes display
        _draw_js_stock_array                draw a Javascript array of in stock attribute combinations
        _build_attributes_array             build an array of the attributes for the product
        _build_attributes_combinations      build an array of the attribute combinations for the product

      Properties:

        products_id                         the product id for attribute display
        products_tax_class_id               the products tax class id
        show_out_of_stock                   show out of stock attributes flag
        mark_out_of_stock                   mark out of stock attributes flag
        out_of_stock_msgline                show out of stock message line flag
        no_add_out_of_stock                 prevent add to cart of out of stock attributes combinations


*/
  class pad_base {
    var $products_id;
    var $products_tax_class_id;
    var $show_out_of_stock;
    var $mark_out_of_stock;
    var $out_of_stock_msgline;
    var $no_add_out_of_stock;
    public $products_original_price;


/*
    Method: pad_base

    Class constructor

    Parameters:

      $products_id      integer     The product id of the product attributes are to be displayed for

    Returns:

      nothing

*/
    function __construct($products_id=0) {
      $this->products_id = $products_id;
      if ($this->products_id != 0) {
        $tax_class_query = tep_db_query('SELECT p.products_tax_class_id, IF(s.status, s.specials_new_products_price, p.products_price) as products_price FROM ' . TABLE_PRODUCTS . " p left join " . TABLE_SPECIALS . " s on p.products_id = s.products_id WHERE p.products_id = '" . (int)$products_id . "'");
        $tax_class_array = tep_db_fetch_array($tax_class_query);
        $this->products_tax_class_id = $tax_class_array['products_tax_class_id'];
        $this->products_original_price = $tax_class_array['products_price'];
      }
      $this->_SetConfigurationProperties('PRODINFO_ATTRIBUTE_');
    }


/*
    Method: _SetConfigurationProperties

    Set local configuration properties from osCommerce configuration DB constants

    Parameters:

      $prefix      sting     Prefix for the osCommerce DB constants

    Returns:

      nothing

*/
    function _SetConfigurationProperties($prefix) {
      $this->show_out_of_stock    = constant($prefix . 'SHOW_OUT_OF_STOCK');
      $this->mark_out_of_stock    = constant($prefix . 'MARK_OUT_OF_STOCK');
      $this->out_of_stock_msgline = constant($prefix . 'OUT_OF_STOCK_MSGLINE');
      $this->no_add_out_of_stock  = constant($prefix . 'NO_ADD_OUT_OF_STOCK');
    }
/*
    Method: draw

    Draws the product attributes.  This is the only method other than the constructor that is
    intended to be called by a user of this class.

    Attributes that stock is tracked for are grouped first and drawn with one dropdown list per
    attribute.  All attributes are drawn even if no stock is available for the attribute and no
    indication is given that the attribute is out of stock.

    Attributes that stock is not tracked for are then drawn with one dropdown list per
    attribute.

    Parameters:

      none

    Returns:

      string:       HTML for displaying the product attributes

*/
    function draw() {
      $out=$this->_draw_table_start();
      $out.=$this->_draw_stocked_attributes();
      $out.=$this->_draw_nonstocked_attributes();
      $out.=$this->_draw_table_end();
      return $out;
    }
/*
    Method: _draw_table_start

    Draws the start of a table to wrap the product attributes display.
    Intended for class internal use only.

    Parameters:

      none

    Returns:

      string:       HTML for start of table

*/
    function _draw_table_start() {
      $out ='<div id="fich-atri" class="fich-bg"><div class="web-cntd fich-wrpr-rows">';
      return $out;
    }
/*
    Method: _draw_stocked_attributes

    Draws the product attributes that stock is tracked for.
    Intended for class internal use only.

    Attributes that stock is tracked for are drawn with one dropdown list per attribute.
    All attributes are drawn even if no stock is available for the attribute and no
    indication is given that the attribute is out of stock.

    Parameters:

      none

    Returns:

      string:       HTML for displaying the product attributes that stock is tracked for

*/
    function _draw_stocked_attributes() {
      $out = '';

      $attributes = $this->_build_attributes_array(true, false);

      if (sizeof($attributes)>0) {
        foreach ($attributes as $stocked) {
          $out .= '<tr><td align="right" class=main><b>' . $stocked['oname'] . ":</b></td><td class=main>" . tep_draw_pull_down_menu('id['.$stocked['oid'].']',array_values($stocked['ovals']),$stocked['default']) . "</td></tr>\n";
        }
      }
      return $out;
    }
/*
    Method: _draw_nonstocked_attributes

    Draws the product attributes that stock is not tracked for.
    Intended for class internal use only.

    Attributes that stock is not tracked for are drawn with one dropdown list per attribute.

    Parameters:

      none

    Returns:

      string:       HTML for displaying the product attributes that stock is not tracked for

*/
    function _draw_nonstocked_attributes()
	{
		global $languages_id, $sGetProductsId, $customer_group_id, $currencies, $aProductInfoAux;

		$attributes = $this->_build_attributes_array(false, true);

		if( count( $attributes ) == 0 )
			return '';

		$tmp_html = '<div class="wrpr-rows">';
			//if( $customer_group_id == '0' )
			{
				foreach( $attributes as $attr )
				{
					$products_options_query = tep_db_query( "select pov.products_options_values_id, pov.products_options_values_name, pa.options_values_price, pa.reference, pa.price_prefix, pa.products_attributes_id, pa.options_values_weight, pa.weight_prefix
															 from " . TABLE_PRODUCTS_ATTRIBUTES . " pa
															 inner join " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov on (pa.options_values_id = pov.products_options_values_id)
															 where pa.products_id = '" . (int)$sGetProductsId . "' and pa.options_id = '" . (int)$attr['oid'] . "' and pov.language_id = '" . (int)$languages_id . "' and find_in_set('".$customer_group_id."', attributes_hide_from_groups) = 0 order by pa.products_options_sort_order");

					if( !isset( $cart->contents[$sGetProductsId]['attributes'][$attr['oid']] ) )
						$no_value = true;

					while( $products_options_array = tep_db_fetch_array($products_options_query) )
					{
						if( $products_options_array['products_options_values_id'] == $cart->contents[$sGetProductsId]['attributes'][$attr['oid']] || $no_value )
							$no_value = false;

						// Obtenemos si hay que chequear stock
						$aCheck = tep_db_query( 'SELECT check_stock FROM products WHERE products_id = "' . $sGetProductsId . '";' );
						$aCheck = tep_db_fetch_array( $aCheck );

						// Control de stock POR VARIANTE (OR con el global)
						if (!(int)$aCheck['check_stock'] && function_exists('fb_variant_check_stock'))
							$aCheck['check_stock'] = fb_variant_check_stock($sGetProductsId, array((int)($attr['oid'] ?? 0) => (int)($products_options_array['products_options_values_id'] ?? 0)), 0);
						$nStock = stock_en_atributos($attr['oid'], $products_options_array['products_options_values_id'], (int)$sGetProductsId );
						$sClass = claseBotonComprar( $nStock, $aCheck['check_stock'] );

						// Variables
						$nAdd1 = 0;
						$nAdd2 = 24;
						$sEstimate = '';

						// Entre 2 y 6 días
						if( trim( $sClass ) == 'prdt-4dias' )
						{
							$nAdd1 = ( 24 * 2 );
							$nAdd2 = ( 24 * 6 );
						}
						// Entre 8 y 13 días
						else if( trim( $sClass ) == 'prdt-5dias' )
						{
							$nAdd1 = ( 24 * 8 );
							$nAdd2 = ( 24 * 13 );
						}
						// Bajo pedido / Agotado
						else if( trim( $sClass ) == 'prdt-bjpdd' || trim( $sClass ) == 'prdt-agtd' )
						{
							$nAdd1 = false;
							$nAdd2 = false;
							$sEstimate = '<span class="cl2">' . ucfirst( (trim( $sClass ) == 'prdt-bjpdd' ? TEXT_BAJO_PEDIDO : TEXT_SIN_STOCK) ) . '</span>';
						}

						// Si tenemos predicción
						if( $nAdd1 !== false )
						{
							// Obtenemos las dos estimaciones
							$aEstimate1 = getShippingEstimate( true, false, $nAdd1 );
							$aEstimate2 = getShippingEstimate( true, false, $nAdd2 );

							// Si las fechas son iguales, sumamos un día
							if( $aEstimate1['date'] == $aEstimate2['date'] )
								$aEstimate2 = addHoursToDate( $aEstimate2['year'] . '-' . $aEstimate2['month'] . '-' . $aEstimate2['day'], 24 );

							// Mostramos el mensaje
							$sEstimate = '<span class="cl2">' . str_replace( array( '%s1', '%s2' ), array( dateToSpanish( date( 'l j ' . SHIPPING_PREDICTION_FROM . ' F', strtotime( $aEstimate1['year'] . '-' . $aEstimate1['month'] . '-' . $aEstimate1['day'] ) ) ), dateToSpanish( date( 'l j ' . SHIPPING_PREDICTION_FROM . ' F', strtotime( $aEstimate2['year'] . '-' . $aEstimate2['month'] . '-' . $aEstimate2['day'] ) ) ) ), SHIPPING_PREDICTION_BUY_NOW ) . '.</span>';
						}

						$tmp_html .= '<div class="row d-flex flex-column-tx flex-column-mx align-items-center ' . $sClass . '">';
							$tmp_html .= '<div class="col-1 d-flex align-items-center"><div>';
								$tmp_html .= '<div class="titu">' . $products_options_array['products_options_values_name'] . '</div>';

								$tmp_html .= '<div class="ref">';
								if( $products_options_array['reference'] != '' )
									$tmp_html .= '<small>Ref.: ' . $products_options_array['reference'] . '</small>';
								if( $sEstimate != '' )
									$tmp_html .= $sEstimate;
								$tmp_html .= '</div>';
							$tmp_html .= '</div></div>';

							$ofertas_price_atributos = (tep_get_products_special_price($sGetProductsId));

							$tmp_html .= tep_draw_form('cart_quantity', tep_href_link('aProductInfoAux.php', tep_get_all_get_params(array('action')) . 'action=add_product'), 'post', 'onsubmit="return false;" class="xprdt ' . $sClass . ' col-2 d-flex align-items-center"');
								$tmp_html .= '<input data-min="' . $aProductInfoAux['products_min_order_qty'] . '" type="text" value="' . $aProductInfoAux['products_min_order_qty'] . '" class="cart_quantity" name="cart_quantity">';

								$tmp_html .= '<div class="prco' . ($ofertas_price_atributos>0 ? ' prdt-ofrt' : '') . '">';
									if( $products_options_array['options_values_price'] != '0' )
									{
										if ($ofertas_price_atributos>0)
										{
											if( $products_options_array['price_prefix'] == '-' )
												$nOferta = $currencies->display_price((-1 * abs( $products_options_array['options_values_price'] ))+$ofertas_price_atributos, tep_get_tax_rate($aProductInfoAux['products_tax_class_id']));
											else
												$nOferta = $currencies->display_price($products_options_array['options_values_price']+$ofertas_price_atributos, tep_get_tax_rate($aProductInfoAux['products_tax_class_id']));

											$tmp_html .= '<s>' . $nOferta . '</s>';
										}

										if( strpos( $aProductInfoAux['products_model'], 'CAG') !== FALSE or strpos( $aProductInfoAux['products_model'], 'CAA' ) !== FALSE )
										{
											if( $products_options_array['price_prefix'] == '-' )
												$nPrecio = $currencies->display_price((-1 * abs( $products_options_array['options_values_price'] ))+$aProductInfoAux['products_price'], tep_get_tax_rate($aProductInfoAux['products_tax_class_id']));
											else
												$nPrecio = $currencies->display_price($products_options_array['options_values_price']+$aProductInfoAux['products_price'], tep_get_tax_rate($aProductInfoAux['products_tax_class_id']));

											$tmp_html .= $nPrecio;
										}
										else
										{
											if( $products_options_array['price_prefix'] == '-' )
												$nPrecio = $currencies->display_price((-1 * abs( $products_options_array['options_values_price'] ))+$aProductInfoAux['products_price'], tep_get_tax_rate($aProductInfoAux['products_tax_class_id']));
											else
												$nPrecio = $currencies->display_price($products_options_array['options_values_price']+$aProductInfoAux['products_price'], tep_get_tax_rate($aProductInfoAux['products_tax_class_id']));

											$tmp_html .= $nPrecio;
										}
									}
									else
									{
										if( $ofertas_price_atributos > 0 )
										{
											$nOferta = explode( ',', $currencies->display_price($ofertas_price_atributos, tep_get_tax_rate($aProductInfoAux['products_tax_class_id'])) );

											$tmp_html .= '<s>' . $nOferta . '</s>';
										}

										if( strpos($aProductInfoAux['products_model'],'CAG') !== FALSE or strpos($aProductInfoAux['products_model'],'CAA') !== FALSE )
										{
											$nPrecio = $currencies->display_price($aProductInfoAux['products_price'], tep_get_tax_rate($aProductInfoAux['products_tax_class_id']));

											$tmp_html .= $nPrecio;
										}
										else
										{
											$nPrecio = $currencies->display_price($aProductInfoAux['products_price'], tep_get_tax_rate($aProductInfoAux['products_tax_class_id']));

											$tmp_html .= $nPrecio;
										}
									}
								$tmp_html .= '</div>';

								$tmp_html .= '<input type="hidden" name="products_id" value="' . $sGetProductsId . '" />';
								$tmp_html .= tep_draw_radio_field( 'id[' . $attr['oid'] . ']', $products_options_array['products_options_values_id'], true, 'style="display:none;"' );
								$tmp_html .= '<input type="submit" class="bton buy" data-form="true" data-id="' . $sGetProductsId . '" data-atribute="" data-qty="' . (preg_match( '/prdt-agtd/i', $sClass ) ? 0 : 1) . '" value="' . TEXT_ANADIR . '" />';
								
								// Popup bajo demanda
								if( preg_match( '/prdt-bjpdd/i', $sClass ) )
									$tmp_html .= '<a class="ajx-bjo" href="ajax_bajodemanda.php" class="mgp-ajax" style="display: none;"></a>';
							$tmp_html .= '</form>';
						$tmp_html .= '</div>';
					}

					$opciones_cont = $opciones_cont + 1;

					$tmp_html = '<div class="wrpr-titu">' . PULL_DOWN_DEFAULT . ' ' . $attr['oname'] . '</div>' . $tmp_html;
				}
			}

		$tmp_html .= '</div>';

      return $tmp_html;
    }
/*
    Method: _draw_table_end

    Draws the end of a table to wrap the product attributes display.
    Intended for class internal use only.

    Parameters:

      none

    Returns:

      string:       HTML for end of table

*/
    function _draw_table_end() {
      return '</div></div>';
    }
/*
    Method: _build_attributes_array

    Build an array of the attributes for the product

    Parameters:

      $build_stocked        boolean   Flag indicating if stocked attributes should be built.
      $build_nonstocked     boolean   Flag indicating if non-stocked attribute should be built.

    Returns:

      array:                Array of attributes for the product of the form:
                              'oid'       => integer: products_options_id
                              'oname'     => string:  products_options_name
                              'ovals'     => array:   option values for the option id of the form
                                             'id'    => integer:  products_options_values_id
                                             'text'  => string:   products_options_values_name
                              'default'   => integer: products_options_values_id that the product id
                                                      contains for this option id and should be the
                                                      default selection when this attribute is drawn.
                                                      Set to zero if the product id did not contain
                                                      this option.

*/
    function _build_attributes_array($build_stocked, $build_nonstocked) {
      global $languages_id;
      global $currencies;
      global $cart;
	  global $customer_group_id;

      if (!($build_stocked | $build_nonstocked)) return null;

      if ($build_stocked && $build_nonstocked) {
        $stocked_where='';
      }
      elseif ($build_stocked) {
        $stocked_where="and popt.products_options_track_stock = '1'";
      }
      elseif ($build_nonstocked) {
        $stocked_where="and popt.products_options_track_stock = '0'";
      }



      $products_options_name_query = tep_db_query("select popt.products_options_id, popt.products_options_name, popt.products_options_track_stock from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_ATTRIBUTES . " patrib where patrib.products_id='" . (int)$this->products_id . "' and patrib.options_id = popt.products_options_id and popt.language_id = '" . (int)$languages_id . "' " . $stocked_where . " GROUP BY popt.products_options_id order by popt.products_options_name");
	  $attributes=array();

      while ($products_options_name = tep_db_fetch_array($products_options_name_query)) {



        $products_options_array = array();
        $products_options_array[] = array('id' => '0', 'text' => PULL_DOWN_DEFAULT . '...');
        $products_options_query = tep_db_query("select pov.products_options_values_id, pov.products_options_values_name, pa.options_values_price, pa.price_prefix
												from " . TABLE_PRODUCTS_ATTRIBUTES . " pa, " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov
												where pa.products_id = '" . (int)$this->products_id . "' and pa.options_id = '" . (int)$products_options_name['products_options_id'] . "' and pa.options_values_id = pov.products_options_values_id and pov.language_id = '" . (int)$languages_id . "' order by pa.products_options_sort_order asc");

		$n = 0;
        while ($products_options = tep_db_fetch_array($products_options_query)) {

				$products_options_array[] = array('id' => $products_options['products_options_values_id'], 'text' => $products_options['products_options_values_name']);


				if(PRODINFO_ATTRIBUTE_ACTUAL_PRICE_PULL_DOWN == 'True'){
				//Option prices will displayed as a final product price. This can (currently) only be used with a satisfying result if you have only one option per product.


				  if ($products_options['price_prefix'] == '-') {// in case price lowers, don't add values, subtract.
					$show_price = 0.0 + $this->products_original_price - $products_options['options_values_price']; // force float (in case) using the 0.0;
				  } else {
					$show_price = 0.0 + $this->products_original_price + $products_options['options_values_price']; // force float (in case) using the 0.0;
				  }

				  $products_options_array[sizeof($products_options_array)-1]['text'] .= '&nbsp;'.$currencies->display_price( $show_price, tep_get_tax_rate($this->products_tax_class_id)).' ';

				}else{ //Display the option prices as differece prices with +/- prefix as usually
				  if ($products_options['options_values_price'] != '0') {

					$products_options_array[sizeof($products_options_array)-1]['text'] .= ' (' . $products_options['price_prefix'] . $currencies->display_price($products_options['options_values_price'], tep_get_tax_rate($this->products_tax_class_id)) .')';
				  }
				}
				++$n;

        }

        if (isset($cart->contents[$this->products_id]['attributes'][$products_options_name['products_options_id']]))
          $selected = $cart->contents[$this->products_id]['attributes'][$products_options_name['products_options_id']];
        else
          $selected = 0;
        $attributes[]=array('oid'=>$products_options_name['products_options_id'],
                            'oname'=>$products_options_name['products_options_name'],
                            'ovals'=>$products_options_array,
                            'default'=>$selected);
      }
      return $attributes;
    }


/*
    Method: _build_attributes_combinations

    A recursive method for building an array enumerating the attribute combinations for the product

    Parameters:

      $attributes             array     An array of the attributes that combinations will be built for.
                                        Format is as returned by _build_attributes_array.
      $showoos                boolean   Flag indicating if non-stocked attributes should be built.
      $markoos                string    'Left' if out of stock indication is to be appended in front of the
                                        attribute combination text.  'Right' if out of stock indication is
                                        to be appended at the end of the attribute combination text.
      $combinations           array     Array of the attribute combinations is returned in this parameter.
                                        Should be set to an empty array before an external call to this method.
                                          'comb'        => array:   array of a single attribute combination
                                                                      options_id => options_value_id
                                          'id'          => string:  options/values string for this
                                                                    combination in the form for the
                                                                    key of the products_stock table
                                                                      opt_id-val_id,opt_id-val_id,...
                                          'text'        => string:  Text for this combination.  Values text
                                                                    is as built by _build_attributes_array
                                                                     and contains the add/subtract price for
                                                                     the option value if applicable.  Form is:
                                                                       values_text, values_text
      $selected_combination   integer   Index into the $combinations array of the combination that should
                                        be the default selection when the combination is drawn is returned in
                                        this parameter.  Determined from product id.  Should be set to zero
                                        before an external call to this method.

    Parameters for internal recursion use only:

      $oidindex               integer   Index into the $attributes array of the option to operate on.
      $comb                   array     Array containing option id/values of combination built so far
                                          products_options_id => products_options_value_id
      $id                     string    Contains string of options/values built so far
      $text                   string    Text for the options values constructed so far.
      $isselected             boolean   Flag indicating if so far all option values in this combination
                                        were indicated to be defaults in the product id.


    Returns:

      see $combinations and $selected_combination parameters above
      no actual function return value.

*/
    function _build_attributes_combinations($attributes, $showoos, $markoos, &$combinations, &$selected_combination, $oidindex=0, $comb=array(), $id="", $text='', $isselected=true) {
      global $cart;

      foreach ($attributes[$oidindex]['ovals'] as $attrib) {
        $newcomb = $comb;
        $newcomb[$attributes[$oidindex]['oid']] = $attrib['id'];
        $newid=$id.','.$attributes[$oidindex]['oid'].'-'.$attrib['id'];
        $newtext = $text.", ".$attrib['text'];
        if (isset($cart->contents[$this->products_id]['attributes'][$attributes[$oidindex]['oid']]))
          $newisselected = ($cart->contents[$this->products_id]['attributes'][$attributes[$oidindex]['oid']] == $attrib['id']) ? $isselected : false;
        else
          $newisselected = false;
        if (isset($attributes[$oidindex+1])) {
          $this->_build_attributes_combinations($attributes, $showoos, $markoos, $combinations, $selected_combination, $oidindex+1, $newcomb, $newid, $newtext, $newisselected);
        }
        else {
          $is_out_of_stock=tep_check_stock(tep_get_prid($this->products_id),1,$newcomb);
          if (!$is_out_of_stock | ($showoos == true)) {
            switch ($markoos) {
              case 'Left':   $newtext=($is_out_of_stock ? TEXT_OUT_OF_STOCK.' - ' : '').substr($newtext,2);
                             break;
              case 'Right':  $newtext=substr($newtext,2).($is_out_of_stock ? ' - '.TEXT_OUT_OF_STOCK : '');
                             break;
              default:       $newtext=substr($newtext,2);
                             break;
            }
            $combinations[] = array('comb'=>$newcomb, 'id'=>substr($newid,1), 'text'=>$newtext);
            if ($newisselected) $selected_combination = sizeof($combinations)-1;
          }
        }
      }
    }


/*
    Method: _draw_js_stock_array

    Draw a Javascript array containing the given attribute combinations.
    Generally used to draw array of in-stock combinations for Javascript out of stock
    validation and messaging.

    Parameters:

      $combinations        array   Array of combinations to build the Javascript array for.
                                   Array must be of the form returned by _build_attributes_combinations
                                   Usually this array only contains in-stock combinations.

    Returns:

      string:                 Javacript array definition.  Excludes the "var xxx=" and terminating ";".  Form is:
                              {optval1:{optval2:{optval3:1,optval3:1}, optval2:{optval3:1}}, optval1:{optval2:{optval3:1}}}
                              For example if there are 3 options and the instock value combinations are:
                                opt1   opt2   opt3
                                  1      5      4
                                  1      5      8
                                  1     10      4
                                  3      5      8
                              The string returned would be
                                {1:{5:{4:1,8:1}, 10:{4:1}}, 3:{5:{8:1}}}

*/
    function _draw_js_stock_array($combinations) {
      if (!((isset($combinations)) && (is_array($combinations)) && (sizeof($combinations) > 0))){
        return '{}';
      }
      $out='';
      foreach ($combinations[0]['comb'] as $oid=>$ovid) {
        $out.='{'.$ovid.':';
        $opts[]=$oid;
      }
      $out.='1';

      for ($combindex=1; $combindex<sizeof($combinations); $combindex++) {
        $comb=$combinations[$combindex]['comb'];
        for ($i=0; $i<sizeof($opts)-1; $i++) {
          if ($comb[$opts[$i]]!=$combinations[$combindex-1]['comb'][$opts[$i]]) break;
        }
        $out.=str_repeat('}',sizeof($opts)-1-$i).',';
        if ($i<sizeof($opts)-1) {
          for ($j=$i; $j<sizeof($opts)-1; $j++)
            $out.=$comb[$opts[$j]].':{';
        }
        $out.=$comb[$opts[sizeof($opts)-1]].':1';
      }
      $out.=str_repeat('}',sizeof($opts));

      return $out;
    }

  }
?>
