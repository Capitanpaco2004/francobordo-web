<?php
namespace util;

/**
 * Clase Breadcrumb para gestionar rutas de migas de pan.
 */
class Breadcrumb
{
	/**
	 * @var array Almacena las migas de pan.
	 */
	private array $trail;

	/**
	 * @var string Separador para las migas de pan.
	 */
	private string $separator = '<span>></span>';

	/**
	 * Constructor de la clase Breadcrumb.
	 */
	public function __construct()
	{
		$this->reset();
	}

	/**
	 * Reinicia las migas de pan.
	 *
	 * @return $this
	 */
	public function reset(): self
	{
		$this->trail = [];
		return $this;
	}

	/**
	 * Agrega una miga de pan.
	 *
	 * @param string $title El título de la miga de pan.
	 * @param string $link  El enlace (opcional) de la miga de pan.
	 *
	 * @return $this
	 */
	public function add(string $title, string $link = ''): self
	{
		$this->trail[] = ['title' => $title, 'link' => $link];
		return $this;
	}

	/**
	 * Establece el separador para las migas de pan.
	 *
	 * @param string $separator El separador deseado.
	 *
	 * @return $this
	 */
	public function setSeparator(string $separator): self
	{
		$this->separator = $separator;
		return $this;
	}

	/**
	 * Obtiene las migas de pan como una cadena de HTML.
	 *
	 * @return string La cadena HTML con las migas de pan.
	 */
	public function getTrail(): string
	{
		$trailString = '';
		$count = count($this->trail);

		for ($i = 0; $i < $count; $i++) {
			$title = htmlspecialchars($this->trail[$i]['title']);
			$link = htmlspecialchars($this->trail[$i]['link']);

			if (!empty($link)) {
				$trailString .= '<a href="' . $link . '">' . $title . '</a>';
			} else {
				$trailString .= '<span>' . $title . '</span>';
			}

			if ($i < $count - 1) {
				$trailString .= ' ' . $this->separator . ' ';
			}
		}

		return $trailString;
	}

	/**
	 * Obtiene las migas de pan con el último elemento como <h1>.
	 *
	 * @param string $separator Separador entre elementos.
	 * @param int    $nCont     Índice desde el que empezar.
	 *
	 * @return string La cadena HTML con las migas de pan.
	 */
	public function trailTitle(string $separator = ' - ', int $nCont = 0): string
	{
		$trailString = '';
		$count = count($this->trail);

		for ($i = $nCont; $i < $count; $i++) {
			$link  = $this->trail[$i]['link'];
			$title = strip_tags($this->trail[$i]['title']);

			if (($i + 1) == $count) {
				if (!empty($link)) {
					$trailString .= '<h1><a href="' . $link . '" title="' . $title . '">' . $this->trail[$i]['title'] . '</a></h1>';
				} else {
					$trailString .= '<h1>' . $this->trail[$i]['title'] . '</h1>';
				}
			} else {
				$trailString .= '<a href="' . $link . '" title="' . $title . '">' . $this->trail[$i]['title'] . '</a>';
			}

			if (($i + 1) < $count) {
				$trailString .= $separator;
			}
		}

		return $trailString;
	}

	/**
	 * Método mágico para obtener propiedades inaccesibles directamente.
	 *
	 * @param string $name Nombre de la propiedad.
	 * @return mixed
	 */
	public function __get(string $name)
	{
		if ($name === '_trail') {
			return $this->trail;
		}

		throw new \InvalidArgumentException("La propiedad $name no existe en la clase Breadcrumb.");
	}

	/**
	 * Muestra las migas de pan en la página web.
	 *
	 * Este método imprime las migas de pan en la página web utilizando `echo`.
	 *
	 * @return void
	 */
	public function display(): void
	{
		echo $this->getTrail();
	}
}
