<?php
	// Tools
	use util\tools as tools;

	// Nos posicionamos en la raiz para incluir las funciones del admin, ademas nos saltamos el forbidden
	chdir( '../../../' );
	$_SERVER['PHP_SELF'] = 'login.php';
	$_SERVER['SCRIPT_FILENAME'] = 'login.php';
	include( 'includes/application_top.php' );

	// Variables
	$sUrl = 'https://denox.es/iframe/';
	$aReturn = [];
	$sHtml = '';

	function DOMinnerHtml($node)
	{
		// Si el xpath no encontró el nodo (HTML vacío o sin el id buscado) devolvemos ''
		if (!($node instanceof DOMNode) || $node->childNodes === null) return '';
		return implode('', array_map([$node->ownerDocument,"saveHTML"], iterator_to_array($node->childNodes)));
	}

	function DOMclearHtml($node)
	{
		if (!($node instanceof DOMNode)) return '';
		return preg_replace('@<(\w+)\b.*?>.*?</\1>@si', '', (string) DOMinnerHTML( $node ) );
	}

	// Peticion curl a denox iframe
	$curl = curl_init();
	curl_setopt( $curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/32.0.1700.107 Chrome/32.0.1700.107 Safari/537.36' );
	curl_setopt( $curl, CURLOPT_AUTOREFERER, true );
	curl_setopt( $curl, CURLOPT_COOKIESESSION, true );
	curl_setopt( $curl, CURLOPT_FAILONERROR, false );
	curl_setopt( $curl, CURLOPT_FOLLOWLOCATION, false );
	curl_setopt( $curl, CURLOPT_FRESH_CONNECT, true );
	curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, 30 );
	curl_setopt( $curl, CURLOPT_URL, $sUrl );

	// Obtenemos html
	$sHtml = curl_exec( $curl );

	// Limpiamos html
	$sHtml = trim( (string) preg_replace( "/[\r\n\t\s]+/", " ", $sHtml ) );

	// Guardamos el checksum
	$nChecksum = crc32( $sHtml );

	// Insertamos configuracion
	tools::insertConfiguration( 'Checksum mensaje Alert Denox', 'MESSAGE_ALERT_DENOX', json_encode( [ 'checksum' => '0', 'change' => false] ), '', 1 );

	// Obtenemos numero
	$aRowChecksum = json_decode( (string) pharaonix_queryOne( 'SELECT configuration_value FROM configuration WHERE configuration_key = "MESSAGE_ALERT_DENOX"' )->records['configuration_value'], true );

	// Si es diferente guardamos
	if( $nChecksum != $aRowChecksum['checksum'] )
	{
		tep_db_perform( 'configuration', [ 'configuration_value' => json_encode( [ 'checksum' => $nChecksum, 'change' => true] ) ], 'update', 'configuration_key = "MESSAGE_ALERT_DENOX"' );
		$aRowChecksum['change'] = true;
	}

	// DOM
	$dcDocument = new DOMDocument();

	// Cargamos el HTML (DOMDocument::loadHTML lanza ValueError con string vacío en PHP 8; @ no lo suprime)
	if ( (string)$sHtml !== '' ) @$dcDocument->loadHTML( $sHtml );

	// Array
	$aReturn['new'] = [];
	$aReturn['blog'] = [];
	$aReturn['services'] = [];

	// Dom search
	$dcXpath = new DOMXPath( $dcDocument );

	// Novedades
	$aReturn['new'] = [
		'date' => $dcXpath->query("//*[@id='nvds']/span")->item(0)->nodeValue,
		'titu' => $dcXpath->query("//*[@id='nvds']/strong")->item(0)->nodeValue,
		'text' => DOMclearHtml( $dcXpath->query("//*[@id='nvds']")->item(0) )
	];

	// Blog
	$aReturn['blog'] = [];

	// Recorremos blog
	foreach( $dcXpath->query("//*[@id='blog']")->item(0)->childNodes as $node )
	{
		if (trim( (string) $node->nodeValue ) === '') {
            continue;
        }

		$node = $dcXpath->query('./a', $node)->item(0);
		$aReturn['blog'][] = [ 'link' => $node->getAttribute('href'), 'text' => $node->nodeValue ];
	}

	// Servicios
	$aReturn['services'] = [];

	// Recorremos servicios
	foreach( $dcXpath->query("//*[@id='srvcs']")->item(0)->childNodes as $node )
	{
		if (trim( (string) $node->nodeValue ) === '') {
            continue;
        }

		$node = $dcXpath->query('./a', $node)->item(0);
		$aReturn['services'][] = [ 'link' => $node->getAttribute('href'), 'text' => $node->nodeValue ];
	}

	// Obtenemos información
	$nodeInfo = $dcXpath->query("//*[@id='tlfn']")->item(0);

	// Información (si el HTML no trae el nodo #tlfn, $nodeInfo es null → getAttribute() on null)
	$aReturn['info'] = [
		'phone' => $nodeInfo ? $nodeInfo->getAttribute('data-telefono') : '',
		'time'  => $nodeInfo ? $nodeInfo->getAttribute('data-horario') : '',
		'mail'  => $nodeInfo ? $nodeInfo->getAttribute('data-mail') : ''
	];

	// Pintamos
	$sHtml = '<div id="alrt-denox" class="tabs">';
		$sHtml .= '<ul class="xtabs" data-tabs id="home-tab">';
			$sHtml .= '<li class="xtabs-title actv" data-tabs-link>' . MENU_WARNING . '</li>';
			$sHtml .= '<li class="xtabs-title" data-tabs-link>' . MENU_BLOG . '</li>';
			$sHtml .= '<li class="xtabs-title" data-tabs-link>' . MENU_SERVICES . '</li>';
			$sHtml .= '<li class="xtabs-title" data-tabs-link>' . MENU_ASSISTENCE . '</li>';
		$sHtml .= '</ul>';
		$sHtml .= '<div class="xtabs-content" data-tabs-content="home-tab">';
			$sHtml .= '<div class="xtabs-item">';
				$sHtml .= '<ul class="alrt-alert">';
					$sHtml .= '<li class="red">' . $aReturn['new']['titu'] . ' ' . $aReturn['new']['text'] . '</li>';
				$sHtml .= '</ul>';
			$sHtml .= '</div>';
			$sHtml .= '<div class="xtabs-item">';
				$sHtml .= '<ul class="alrt-alert">';
					foreach( $aReturn['blog'] as $aRow )
						$sHtml .= '<li><a target="_blank" href="' . $aRow['link'] . '">' . $aRow['text'] . '</a></li>';
				$sHtml .= '</ul>';
			$sHtml .= '</div>';
			$sHtml .= '<div class="xtabs-item">';
				$sHtml .= '<ul class="alrt-alert">';
					foreach( $aReturn['services'] as $aRow )
						$sHtml .= '<li><a target="_blank" href="' . $aRow['link'] . '">' . $aRow['text'] . '</a></li>';
				$sHtml .= '</ul>';
			$sHtml .= '</div>';
			$sHtml .= '<div class="xtabs-item" id="alrt-contacto">';
				$sHtml .= '<div>';
					$sHtml .= '<i class="fa fa-phone-square"></i>';
					$sHtml .= '<a href="tel://' . $aReturn['info']['phone'] . '" itemprop="telephone">' . $aReturn['info']['phone'] . '</a>';
				$sHtml .= '</div>';
				$sHtml .= '<div>';
					$sHtml .= '<i class="fa fa-clock-o"></i>';
					$sHtml .= $aReturn['info']['time'];
				$sHtml .= '</div>';
				$sHtml .= '<div>';
					$sHtml .= '<i class="fa fa-envelope-square"></i>';
					$sHtml .= '<a href="mailto:' . $aReturn['info']['mail'] . '" itemprop="telephone">' . $aReturn['info']['mail'] . '</a>';
				$sHtml .= '</div>';
			$sHtml .= '</div>';
		$sHtml .= '</div>';
	$sHtml .= '</div>';

	// Retornamos
	echo json_encode( ['html' => $sHtml, 'change' => $aRowChecksum['change']] );
?>
