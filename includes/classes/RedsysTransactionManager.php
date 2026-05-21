<?php

namespace util;

use RedsysConsultasPHP\Client\Client as RedsysClient;
use DateTime;

class RedsysTransactionManager {
	private RedsysClient $client;

	private array $estados_map = [
		'P' => 'En proceso autorizar',
		'F' => 'Finalizada',
		'T' => 'Sin respuesta',
		'E' => 'Error de formato',
		'S' => 'Solicitada',
		'I' => 'Incidencia especial',
		'W' => 'Estado temporal',
		'A' => 'Esperando autenticación 3D Secure',
	];

	private array $codigos_respuesta_map = [
		'0000' => 'Denegada: Transacción autorizada',
		'0900' => 'Denegada: Autorizada para devoluciones/confirmaciones',
		'0101' => 'Denegada: Tarjeta caducada',
		'0102' => 'Denegada: Tarjeta en excepción/fraude',
		'0104' => 'Denegada: Operación no permitida',
		'9104' => 'Denegada: Operación no permitida',
		'0116' => 'Denegada: Disponible insuficiente',
		'0118' => 'Denegada: Tarjeta no registrada',
		'0129' => 'Denegada: CVV incorrecto',
		'0180' => 'Denegada: Tarjeta ajena al servicio',
		'0184' => 'Denegada: Error autenticación titular',
		'0190' => 'Denegada: Denegación sin especificar',
		'0191' => 'Denegada: Fecha caducidad errónea',
		'0202' => 'Denegada: Tarjeta excepción/fraude con retirada',
		'0912' => 'Denegada: Emisor no disponible',
		'9912' => 'Denegada: Emisor no disponible',
	];

	public function __construct(string $url, string $merchant_password) {
		$this->client = new RedsysClient($url, $merchant_password);
	}

	public function getTransactionsByDays(string $terminal, string $merchant_code, int $dias = 15): array {
		$formato       = 'Y-m-d-H.i.s.000000';
		$hoy           = new DateTime();
		$inicioPeriodo = (clone $hoy)->modify("-{$dias} days");

		// Define un intervalo seguro, por ejemplo 30 días
		$intervaloDias = 15;
		$transacciones = [];

		while ($inicioPeriodo < $hoy) {
			$finPeriodo = (clone $inicioPeriodo)->modify("+{$intervaloDias} days -1 second");

			if ($finPeriodo > $hoy) {
				$finPeriodo = clone $hoy;
			}

			try {
				$response = $this->client->getTransactionsByDateRange(
					$terminal,
					$merchant_code,
					$inicioPeriodo->format($formato),
					$finPeriodo->format($formato),
				);

				foreach ($response as $transaccion) {
					$transacciones[] = $transaccion;
				}
			} catch (\Exception $e) {
				error_log("Error Redsys entre " . $inicioPeriodo->format($formato) . " y " . $finPeriodo->format($formato) . ": " . $e->getMessage());
			}

			$inicioPeriodo = (clone $finPeriodo)->modify('+1 second');
		}

		// Ordenar transacciones por fecha, de la más reciente a la más antigua
		usort($transacciones, function ($a, $b) {
			$fecha_a = strtotime($a->getDsDate() . ' ' . $a->getDsHour());
			$fecha_b = strtotime($b->getDsDate() . ' ' . $b->getDsHour());

			return $fecha_b - $fecha_a;
		});

		return $transacciones;

	}

	public function mapTransactionData($transaccion): array {
		$response_code = str_pad((string)$transaccion->getDsResponse(), 4, '0', STR_PAD_LEFT);
		$state         = $transaccion->getDsState();

		return [
			'ds_order'            => $transaccion->getDsOrder(),
			'ds_response'         => $response_code,
			'ds_state'            => $state,
			'ds_state_msg'        => $this->getRedsysStateMessage($state),
			'ds_response_msg'     => $this->getRedsysResponseMessage($response_code),
			'ds_transaction_type' => $transaccion->getDsTransactionType(),
		];
	}

	public function getRedsysStateMessage(string $state_code): string {
		return $this->estados_map[$state_code] ?? 'Estado desconocido';
	}

	public function getRedsysResponseMessage(string $response_code): string {
		return $this->codigos_respuesta_map[$response_code] ?? 'Respuesta desconocida';
	}

}
