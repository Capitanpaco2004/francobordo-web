<?php
// Alias
namespace util;

/**
 * Clase donde se encuentra herramientas para los strings, por ejemplo metodos para convertir a camelCase, obtener slug, etc
 */
class strings
{
	/**
     * Limpia el html que ha podido quedarse abierto
     *
     * @param string $string html
     * @return string
     */
	public static function cleanHTML($string)
	{
		if(self::isHTML($string)){
			global $purifier;
			return $purifier->purify(trim(stripslashes($string)));
		}
		else {
			return $string;
		}
	}

	/**
     * Verifica si una cadena tiene html
     *
     * @param string $string Cadena a comprobar
     * @return booleam
     */
	public static function isHTML($string)
	{
		return $string != strip_tags($string) ? true:false;
	}

    /**
     * De un array de n palabras, devuelve un array de n cadenas con todas las combinaciones posibles.
     *
     * @param array $words Palabras en array
     * @param string $separate Separación entre coincidencias
     * @return array
     */
    public static function wordcombos($words, $separate = ' ')
    {
        if (count($words) <= 1) {
            $result = $words;
        } else {
            $result = array();
            for ($i = 0; $i < count($words); ++$i) {
                $firstword = $words[$i];
                $remainingwords = array();
                for ($j = 0; $j < count($words); ++$j) {
                    if ($i != $j) {
                        $remainingwords[] = $words[$j];
                    }

                }
                $combos = self::wordcombos($remainingwords, $separate);
                for ($j = 0; $j < count($combos); ++$j) {
                    $result[] = $firstword . $separate . $combos[$j];
                }
            }
        }
        return $result;
    }

    /**
     * Devuelve un array o string con los singulares y plurales de las palabras pasadas por argumento
     *
     * @param string $sText Texto de palabras para convertirlas en singulares y plurales
     * @param string $sReturn Forma en la que queremos que nos devuelva el resultado
     * @return array
     */
    public static function getPluralSingular($sText, $sReturn = 'text')
    {
        // Variables
        $aSearchPlural = array();
        $aSearchSingular = array();
        $aWords = null;

        // Separamos las palabras
        $aWords = preg_split("/[\s]|[,]|[.]|[-]/", $sText, -1, PREG_SPLIT_NO_EMPTY);

        // Formateamos las palabras de la búsqueda
        foreach ($aWords as $aWord) {
            // Eliminamos las palabras demasiado cortas (pronombres, etc)
            if (strlen($aWord) > 1) {
                // Si es una preposición, artículo o nexo continuamos
                if (in_array($aWord, array('a', 'ante', 'bajo', 'cabe', 'con', 'contra', 'de', 'desde', 'en', 'entre', 'hacia', 'hasta', 'para', 'por', 'según', 'segun', 'sin', 'so', 'sobre', 'tras', 'del', 'al', 'el', 'la', 'las', 'los', 'un', 'unos', 'un', 'una', 'unas', 'y', 'u', 'o', 'e'))) {
                    continue;
                }

                // Si es un número
                if (is_numeric($aWord)) {
                    // Añadimos el número en singular y plural
                    $aSearchPlural[] = $aWord;
                    $aSearchSingular[] = $aWord;
                    continue;
                }

                // Comprobamos si la palabra está en plural (si termina en -s, -es, -ces), para obtener su singular
                if (preg_match('/s$/', $aWord) || preg_match('/es$/', $aWord) || preg_match('/ces$/', $aWord)) {
                    // Añadimos al array plural
                    $aSearchPlural[] = $aWord;

                    // Añadimos al array singular
                    if (preg_match('/ces$/', $aWord)) {
                        $aSearchSingular[] = preg_replace('/ces$/', 'z', $aWord);
                    } else if (preg_match('/es$/', $aWord)) {
                        $aSearchSingular[] = preg_replace('/es$/', '', $aWord);
                    } else if (preg_match('/s$/', $aWord)) {
                        $aSearchSingular[] = preg_replace('/s$/', '', $aWord);
                    }

                }
                // Si la palabra está en singular
                else {
                    // Añadimos al array singular
                    $aSearchSingular[] = $aWord;

                    // Obtenemos su plural
                    if (preg_match('/[a]$|[e]$|[o]$/', $aWord)) {
                        $aSearchPlural[] = $aWord . 's';
                    } else if (preg_match('/[z]$/', $aWord)) {
                        $aSearchPlural[] = preg_replace('/z$/', 'ces', $aWord);
                    } else {
                        $aSearchPlural[] = $aWord . 'es';
                    }

                }
            }
        }

		// Eliminamos duplicados en los arrays
		$aSearchSingular = array_unique($aSearchSingular);
		$aSearchPlural = array_unique($aSearchPlural);

        // Retornamos
        if ($sReturn == 'text') {
            $sReturn = '';

            foreach ($aSearchSingular as $nWord => $aWord) {
                $sReturn .= self::sanitizeDb($aSearchSingular[$nWord]) . ' ' . self::sanitizeDb($aSearchPlural[$nWord]) . ' ';
            }

            return substr($sReturn, 0, -1);
        } else {
            return array('singular' => $aSearchSingular, 'plural' => $aSearchPlural);
        }

    }

    /**
     * Devuelve una cadena convertida a slug, convirtiendo los espacios al caracter deseado
     *
     * @param string $sTexto Texto para ser convertido a slug
     * @param string $sSeparator Los espacios se convertiran en este separador
     * @return string
     */
    public static function getSlug($text, $sSeparator = '-')
    {
		// replace non letter or digits by -
		$text = preg_replace('~[^\pL\d]+~u', $sSeparator, $text);

		// transliterate
		$text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

		// remove unwanted characters
		$text = preg_replace('~[^-\w]+~', '', $text);

		// trim
		$text = trim($text, $sSeparator);

		// remove duplicate -
		$text = preg_replace('~-+~', $sSeparator, $text);

		// lowercase
		$text = strtolower($text);

		if (empty($text)) {
			return 'n-a';
		}

		return $text;
	}

	/**
	 * Devuelve una cadena limpia
	 *
	 * @param string $text Texto para limpiar
	 * @return string
	 */
	public static function cleanString($text)
	{
		$utf8 = array(
			'/[áàâãªä]/u'   =>   'a',
			'/[ÁÀÂÃÄ]/u'    =>   'A',
			'/[ÍÌÎÏ]/u'     =>   'I',
			'/[íìîï]/u'     =>   'i',
			'/[éèêë]/u'     =>   'e',
			'/[ÉÈÊË]/u'     =>   'E',
			'/[óòôõºö]/u'   =>   'o',
			'/[ÓÒÔÕÖ]/u'    =>   'O',
			'/[úùûü]/u'     =>   'u',
			'/[ÚÙÛÜ]/u'     =>   'U',
			'/ç/'           =>   'c',
			'/Ç/'           =>   'C',
			'/ñ/'           =>   'n',
			'/Ñ/'           =>   'N',
			'//'            =>   '-',
			'/[’‘]/u'       =>   ' ',
			'/[“”«»„]/u'    =>   ' ',
			'/ /'           =>   ' ',
		);

		return preg_replace(array_keys($utf8), array_values($utf8), $text);
	}

    /**
     * Convierte el string pasado a camelCase, por ejemplo "soy_ejemplo" a "soyEjemplo"
     * @param string $sString Cadena de texto para convertirla a camelCase
     * @param bool $bFirst Si deseamos el primer caracter en mayuscula
     * @return string Cadena devuelva al ser convertida a camelCase
     */
    public static function getCamelCase($sString, $bFirst = false)
    {
        // Camelcase
        $sString = preg_replace_callback('/[-|_| ](.?)/', function ($i) {return strtoupper($i[1]);}, $sString);

        // Si deseamos el primer caracter en mayuscula
        if ($bFirst) {
            $sString = ucfirst($sString);
        }

        // Retornamos
        return $sString;
	}

    /**
     * Convierte el string pasado camelCase a normal, por ejemplo "soyEjemplo" a "soy_ejemplo"
     * @param string $sString Cadena de texto camelCase
	 * @param string $replace Separacion de caracteres
     * @param bool $bFirst Si deseamos el primer caracter en mayuscula
     * @return string Cadena devuelva quitando camelCase
     */
    public static function undoCamelCase($string, $replace = '_')
	{

		$string = preg_replace('/([a-z])([A-Z])/', "\\1" . $replace . "\\2", $string);
    	$string = strtolower($string);
    	return $string;
	}

    /**
     * Devuelve el nombre del archivo de una URL. Por ejemplo si se le pasa /var/dump/index.php?a=1&b=2 devolvera index
     * @param string $sUrl Url desde donde se buscara el nombre del archivo
     * @return string $sUrl Devolvera el nombre del archivo sin extension
     */
    public static function cleanFileNameUrl($sUrl)
    {
        $sUrl = preg_replace('/\?.+$/', '', basename($sUrl));
        $sUrl = preg_replace('/\..+$/i', '', $sUrl, 1);

        return $sUrl;
    }

	/**
	 * Devuelve la URL pero el query param formateado. Por ejemplo si se le pasa https://www.xxxxxxx.com/product_info.php?products_id=4598{2}104 te
	 * de devuelve https://www.xxxxxxx.com/product_info.php?products_id=4598%7B2%7D104
	 * @param string $sUrl Url a formatear
	 * @return string $sUrl Url formateada su query param
	 */
	public static function encodeURI($url) {
		$unescaped = array(
			'%2D'=>'-','%5F'=>'_','%2E'=>'.','%21'=>'!', '%7E'=>'~',
			'%2A'=>'*', '%27'=>"'", '%28'=>'(', '%29'=>')'
		);
		$reserved = array(
			'%3B'=>';','%2C'=>',','%2F'=>'/','%3F'=>'?','%3A'=>':',
			'%40'=>'@','%26'=>'&','%3D'=>'=','%2B'=>'+','%24'=>'$'
		);
		$score = array(
			'%23'=>'#'
		);
		return strtr(rawurlencode($url), array_merge($reserved,$unescaped,$score));
	}

    /**
     * Limpia la cadena de texto pasado como agumento, preparada para usarla en base de datos
     * @param string $input string|array Cadena o array de cadenas para ser procesadas
     * @return string $output Cadena procesada limpia de caracteres extraños para usar en DB
     */
    public static function sanitizeDb($input)
    {
        // Si es un array
        if (is_array($input)) {
            $output = array();

            foreach ($input as $sKey => $sValue) {
                $output[$sKey] = self::sanitizeDb($sValue);
            }

        } else {
            $aSearch = array(
                '@<script[^>]*?>.*?</script>@si', // Limpiar javascript
                '@<[\/\!]*?[^<>]*?>@si', // Limpiar html
                '@<style[^>]*?>.*?</style>@siU', // Limpiar tags
                '@<![\s\S]*?--[ \t\n\r]*>@', // Limpiar saltos de linea
            );

            $input = preg_replace($aSearch, '', $input);

            // Realizamos un mysql_real_escape
            $output = str_replace(array('\\', "\0", "\n", "\r", "'", '"', "\x1a"), array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'), $input);
        }

        // Retornamos
        return $output;
    }
}
