<?php

namespace util\adminBar;

use Oscdenox\Core\Cookie\Domain\Cookie;
use util\tools;

class AdminBar
{
	private $active;
	private $cookie;
	private static $instance;

	public static function getInstance(Cookie $cookie)
	{
		if( !(self::$instance instanceof self) )
			self::$instance = new self($cookie);

		return self::$instance;
	}

	/**
	 * Constructor de la clase
	 */
	public function __construct(Cookie $cookie)
	{
		$this->cookie = $cookie;
		$this->active = $this->cookie->hasAdmin;
	}

	/**
	 * Metodo que se llama al finalizar la petición mostrando la barra
	 */
	public function show()
	{
		global $cPath;

		// Si no esta activo no hacemos nada
		if( !$this->active )
			return false;

		$pathResources = 'includes/classes/adminBar';
		$linkAdmin = tools::getPathAdmin();

		// Debugbar
		echo '<div id="admin_bar" class="open" style="visibility:hidden;">';
			echo '<div class="admin_bar_close"><span class="fa fa-times"></span></div>';
			echo '<div class="admin_bar_vrsn"><img src="' . $pathResources . '/images/logo.jpg"/> Administrador</div>';
			echo '<div class="admin_bar_wrapper">';

				if (isset($_GET['products_id'])){
					$sGetProductsId = preg_replace( '/\{.+$/i', '', $_GET['products_id'] );

					echo '<a target="_blank" href="' . tep_href_link($linkAdmin . '/categories.php', 'pID=' . $sGetProductsId . '&action=new_product') . '" class="admin_bar_link"><i class="fa fa-paintbrush-pencil"></i> Editar producto</a>';

					echo '<a target="_blank" href="' . tep_href_link($linkAdmin . '/reviews.php') . '" class="admin_bar_link"><i class="fa fas fa-comments"></i> Comentarios</a>';
				}

				if (isset($cPath) && $cPath != '') {
					echo '<a target="_blank" href="' . tep_href_link($linkAdmin . '/categories.php', 'cPath=' . $cPath) . '" class="admin_bar_link"><i class="fa fas fa-store-alt"></i> Categoría</a>';
				}

				echo '<a target="_blank" href="' . tep_href_link($linkAdmin . '/customers.php') . '" class="admin_bar_link"><i class="fa fa-users"></i> Clientes</a>';
				echo '<a target="_blank" href="' . tep_href_link($linkAdmin . '/orders.php') . '" class="admin_bar_link"><i class="fa fa-shopping-basket"></i> Pedidos</a>';
			echo '</div>';
		echo '</div>';

		// CSS y JS
		echo '<link rel="stylesheet" type="text/css" href="' .  $pathResources . '/css/style.css"/>';
		echo '<script src="' .  $pathResources . '/js/javascript.js" type="text/javascript"></script>';
	}
}
?>
