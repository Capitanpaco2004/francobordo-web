<?php

class PromotionManager {
	private $cart;
	private $customer_group_id;
	public static $aAllPromotions = [];

	public function __construct($cart, $customer_group_id = 0) {
		$this->cart              = $cart;
		$this->customer_group_id = $customer_group_id;
		self::$aAllPromotions    = $this->loadGlobalPromotions();
	}

	public function applyPromotions() {
		if (empty($this->cart->contents)) {
			return [];
		}

		$aPromotions = [];
		$sCart       = '';

		foreach ($this->cart->contents as $nProduct => &$aCart) {
			$aCart['promotion'] = ['discount' => false, 'qty' => 0];
			$aCart['rest']      = $aCart['qty'];

			$aAuxs = tep_db_query(
				'SELECT pr.promotion_id, pr.promotion_discount_percent, pr.promotion_discount_type,
                        pr.promotion_quantity, pr.promotion_all, pr.promotion_extend, pr.promotion_special,
                        "' . (int)$nProduct . '" AS products_id, "' . $aCart['qty'] . '" AS qty
                 FROM promotions pr
                 INNER JOIN promotions_elements pe ON (pr.promotion_id = pe.promotion_id AND pr.promotion_status = 1)
                 WHERE (
                        (element_id = "' . (int)$nProduct . '" AND element_type = "p" AND element_operation = "p")
                     OR (element_id = (SELECT p.manufacturers_id FROM products p WHERE p.products_id = "' . (int)$nProduct . '")
                         AND element_type = "m" AND element_operation = "p")
                     OR (element_id IN (SELECT c.categories_id FROM products_to_categories c WHERE c.products_id = "' . (int)$nProduct . '")
                         AND element_type = "c" AND element_operation = "p")
                 )
                 AND DATE(NOW()) >= DATE(pr.promotion_start)
                 AND (DATE(NOW()) < DATE(pr.promotion_end) OR pr.promotion_end = "0000-00-00 00:00:00")
                 ORDER BY pr.promotion_discount_percent DESC',
			);

			if ($this->customer_group_id == 0 && tep_db_num_rows($aAuxs) > 0) {
				$nPriceById     = $this->cart->getPriceById($nProduct);
				$aCart['price'] = $nPriceById;

				while ($aAux = tep_db_fetch_array($aAuxs)) {
					if (!isset($aPromotions[$aAux['promotion_id']])) {
						$aPromotions[$aAux['promotion_id']]                = $aAux;
						$aPromotions[$aAux['promotion_id']]['products_id'] = [];
						$aPromotions[$aAux['promotion_id']]['qty']         = 0;
					}
					$aPromotions[$aAux['promotion_id']]['products_id'][] = [
						'id'    => $nProduct,
						'qty'   => $aCart['qty'],
						'price' => $nPriceById,
					];
					$aPromotions[$aAux['promotion_id']]['qty']           += $aAux['qty'];
				}
			}

			$sCart .= (int)$nProduct . ', ';
		}
		unset($aCart);

		$this->sortCartByPrice();

		foreach ($aPromotions as $id => &$aPromo) {
			if (count($aPromo['products_id']) > 1) {
				$this->sortPromoProductsByPrice($aPromo['products_id']);
			}
		}
		unset($aPromo);

		if (!empty($aPromotions) && $sCart !== '') {
			$this->applyDiscounts($aPromotions, $this->cart->contents, $sCart);
		}

		return [
			'applied' => $aPromotions,
			'all'     => self::$aAllPromotions,
			'cart'    => $this->cart->contents,
		];
	}

	private function sortCartByPrice() {
		if (empty($this->cart->contents)) return;
		$aPriceOrder = [];
		$aKeys       = array_keys($this->cart->contents);

		foreach ($this->cart->contents as $id => $prod) {
			$aPriceOrder[$id] = isset($prod['price']) ? $prod['price'] : 0;
		}

		array_multisort($aPriceOrder, SORT_ASC, $this->cart->contents, $aKeys);
		$this->cart->contents = array_combine($aKeys, $this->cart->contents);
	}

	private function sortPromoProductsByPrice(&$products) {
		$aPriceOrder = [];
		foreach ($products as $idx => $p) {
			$aPriceOrder[$idx] = $p['price'];
		}
		array_multisort($aPriceOrder, SORT_ASC, $products);
	}

	private function applyDiscounts(&$aPromotions, &$aCarts, $sCart) {
		foreach ($aPromotions as $nId => $aPromotion) {
			$promotionQty = (int)$aPromotion['promotion_quantity'];
			if ($promotionQty <= 0 || $aPromotions[$nId]['qty'] < $promotionQty) {
				continue;
			}

			// número de promos
			$nPromotions = (int)($aPromotions[$nId]['qty'] / $promotionQty);
			if ($aPromotion['promotion_extend'] == 0) {
				$nPromotions = min($nPromotions, 1);
			}

			$this->debug("PROMO {$aPromotion['promotion_id']} => qtyPromo={$aPromotions[$nId]['qty']} "
						 . "minimo={$aPromotion['promotion_quantity']} nPromotions=$nPromotions "
						 . "promotion_all={$aPromotion['promotion_all']} promotion_extend={$aPromotion['promotion_extend']}");

			// productos con descuento
			$discountIds = $this->getDiscountProductIds($aPromotion['promotion_id']);

			if (empty($discountIds)) {
				continue;
			}
			$sCartPromo = implode(',', $discountIds);

			$aAuxs = tep_db_query("SELECT p.products_id,
										p.products_price,
										s.specials_new_products_price
								 FROM products p
								 LEFT OUTER JOIN specials s
								   ON (p.products_id = s.products_id
									   AND s.customers_group_id = '" . $this->customer_group_id . "'
									   AND s.status = 1)
								 WHERE p.products_id IN (" . $sCartPromo . ")");


			if (tep_db_num_rows($aAuxs) == 0) continue;

			$aAuxsPromo = [];
			while ($aAux = tep_db_fetch_array($aAuxs)) {
				$aAuxsPromo[] = $aAux;
			}

			// ordenar por precio ascendente
			usort($aAuxsPromo, function ($a, $b) {
				$priceA = $a['specials_new_products_price'] !== '' ? $a['specials_new_products_price'] : $a['products_price'];
				$priceB = $b['specials_new_products_price'] !== '' ? $b['specials_new_products_price'] : $b['products_price'];
				return $priceA <=> $priceB;
			});

			if ($aPromotion['promotion_all'] == 1) {
				// aplicar $nPromotions veces a cada producto de descuento
				foreach ($aAuxsPromo as $aAux) {
					foreach ($aCarts as $nIdContnt => $aContent) {
						if ((int)$nIdContnt == $aAux['products_id'] && $this->cart->contents[$nIdContnt]['rest'] > 0) {
							$qtyToApply = min($this->cart->contents[$nIdContnt]['rest'], $nPromotions);
							if ($qtyToApply > 0) {
								$this->cart->contents[$nIdContnt]['promotion'][$aPromotion['promotion_id']]['qty'] =
									($this->cart->contents[$nIdContnt]['promotion'][$aPromotion['promotion_id']]['qty'] ?? 0) + $qtyToApply;
								$this->cart->contents[$nIdContnt]['promotion'][$aPromotion['promotion_id']]['discount'] = $aPromotion['promotion_discount_percent'];
								$this->cart->contents[$nIdContnt]['promotion'][$aPromotion['promotion_id']]['type']     = $aPromotion['promotion_discount_type'];
								$this->cart->contents[$nIdContnt]['rest'] -= $qtyToApply;

								$this->debug("ALL=1 aplicado producto={$nIdContnt} qtyToApply=$qtyToApply restFinal={$this->cart->contents[$nIdContnt]['rest']}");
							}
						}
					}
				}
			} else {
				// lógica normal (solo el más barato, pero si se agota pasa al siguiente)
				$nCantPromoRest = $nPromotions;

				$this->debug("Productos de descuento detectados para promo {$aPromotion['promotion_id']}:");
				foreach ($aAuxsPromo as $p) {
					$precio = $p['specials_new_products_price'] !== '' ? $p['specials_new_products_price'] : $p['products_price'];
					$this->debug("- productId={$p['products_id']} price=$precio");
				}


				foreach ($aAuxsPromo as $aAux) { // de más barato a más caro
					$productId = (int)$aAux['products_id'];

					if (!isset($aCarts[$productId])) {
						continue; // este producto no está en el carrito
					}

					$rest = $aCarts[$productId]['rest'];
					if ($rest <= 0) continue;

					$qtyToApply = min($rest, $nCantPromoRest);
					if ($qtyToApply > 0) {
						$this->cart->contents[$productId]['promotion'][$aPromotion['promotion_id']]['qty'] =
							($this->cart->contents[$productId]['promotion'][$aPromotion['promotion_id']]['qty'] ?? 0) + $qtyToApply;
						$this->cart->contents[$productId]['promotion'][$aPromotion['promotion_id']]['discount'] = $aPromotion['promotion_discount_percent'];
						$this->cart->contents[$productId]['promotion'][$aPromotion['promotion_id']]['type']     = $aPromotion['promotion_discount_type'];

						$this->cart->contents[$productId]['rest'] -= $qtyToApply;
						$nCantPromoRest -= $qtyToApply;

						$this->debug("ALL=0 aplicado producto=$productId qtyToApply=$qtyToApply restFinal={$this->cart->contents[$productId]['rest']} promosRest=$nCantPromoRest");
					}

					if ($nCantPromoRest <= 0) break; // ya se gastaron todas las promos
				}

			}
		}
	}

	private function getDiscountProductIds($promotionId) {
		$discountIds = [];
		$dQuery      = tep_db_query(
			"SELECT element_id, element_type
             FROM promotions_elements
             WHERE promotion_id = " . (int)$promotionId . "
               AND element_operation = 'd'",
		);

		while ($row = tep_db_fetch_array($dQuery)) {
			if ($row['element_type'] === 'p') {
				$discountIds[] = (int)$row['element_id'];
			} else if ($row['element_type'] === 'm') {
				$rs = tep_db_query("SELECT products_id FROM products WHERE manufacturers_id = " . (int)$row['element_id']);
				while ($p = tep_db_fetch_array($rs)) {
					$discountIds[] = (int)$p['products_id'];
				}
			} else if ($row['element_type'] === 'c') {
				$rs = tep_db_query("SELECT products_id FROM products_to_categories WHERE categories_id = " . (int)$row['element_id']);
				while ($p = tep_db_fetch_array($rs)) {
					$discountIds[] = (int)$p['products_id'];
				}
			}
		}
		return array_unique($discountIds);
	}

	private function loadGlobalPromotions() {
		$aAllPromotions = [];
		$aAuxPromotions = tep_db_query(
			'SELECT pr.promotion_id, pr.promotion_icon, pr.promotion_special,
                    pe.element_id, pe.element_type, pe.element_operation
             FROM promotions pr
             LEFT JOIN promotions_elements pe ON (pr.promotion_id = pe.promotion_id)
             WHERE pr.promotion_status = 1
               AND DATE(NOW()) >= DATE(pr.promotion_start)
               AND (DATE(NOW()) < DATE(pr.promotion_end) OR pr.promotion_end = "0000-00-00 00:00:00")',
		);

		while ($aAux = tep_db_fetch_array($aAuxPromotions)) {
			$aAllPromotions[$aAux['promotion_id']]['icon']       = $aAux['promotion_icon'];
			$aAllPromotions[$aAux['promotion_id']]['special']    = $aAux['promotion_special'];
			$aAllPromotions[$aAux['promotion_id']]['elements'][] = $aAux;
		}

		return $aAllPromotions;
	}

	public static function getAllPromotions() {
		return self::$aAllPromotions;
	}

	private function debug($msg) {
		return; // desactivar debug
		// activar solo para una IP concreta
		if ($_SERVER['REMOTE_ADDR'] === '79.117.146.226') {
			echo '[PROMO DEBUG] ' . $msg.'<br><br>';
		}
	}

}
