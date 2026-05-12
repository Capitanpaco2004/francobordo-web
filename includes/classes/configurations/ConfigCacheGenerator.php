<?php
namespace util\configurations;

class ConfigCacheGenerator
{
	private static string $configCacheFile;

	public static function setConfigCacheFile(string $configCacheFile): void
	{
		self::$configCacheFile = $configCacheFile;
	}

	/**
	 * Genera el archivo de cache con definiciones de configuración.
	 * Escapa correctamente cadenas, incluyendo nuevas líneas y comillas.
	 *
	 * @param array<int, array{key:string,value:mixed}> $configurationData
	 * @param bool $compress
	 * @param int $compressionLevel
	 */
	public static function generateConfigCache(array $configurationData, bool $compress = false, int $compressionLevel = 1): void
	{
		$configCacheOutput = self::generateConfigDefinitions($configurationData);
		if ($compress) {
			$configCacheOutput = self::applyCompression($configCacheOutput, $compressionLevel);
		}
		self::saveToFile($configCacheOutput);
	}

	/**
	 * Construye las líneas define() escapando valores adecuadamente.
	 *
	 * @param array<int,array{key:string,value:mixed}> $configurationData
	 * @return string
	 */
	private static function generateConfigDefinitions(array $configurationData): string
	{
		$output = "<?php" . PHP_EOL;
		foreach ($configurationData as $config) {
			// Usar var_export para obtener representación válida de PHP
			$exportedValue = var_export($config['value'], true);
			$output .= sprintf("define('%s', %s);", addslashes($config['key']), $exportedValue) . PHP_EOL;
		}
		$output .= '?>';
		return $output;
	}

	/**
	 * Aplica compresión si se desea.
	 *
	 * @param string $content
	 * @param int $level
	 * @return string
	 */
	private static function applyCompression(string $content, int $level): string
	{
		$compressed = gzdeflate($content, $level);
		$encoded = base64_encode($compressed);
		return wordwrap($encoded, 80, PHP_EOL, true);
	}

	/**
	 * Guarda el contenido en el archivo de caché.
	 *
	 * @param string $content
	 */
	private static function saveToFile(string $content): void
	{
		$dir = dirname(self::$configCacheFile);
		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}
		file_put_contents(self::$configCacheFile, $content);
	}
}
