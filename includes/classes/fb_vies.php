<?php
/**
 * #FB-VIES  Cliente de validacion de VAT intracomunitario (VIES REST API) + persistencia.
 *
 * Patron correos_express: curl, timeouts, retorno normalizado, NO lanza excepciones.
 * Decision del usuario (2026-07-02):
 *   - Activacion 0% (reverse charge) AUTOMATICA: grupo 1 (Profesionales) + VAT valido en VIES + entrega UE!=ES.
 *   - Fallo VIES (caido/timeout) => se conserva el ULTIMO ESTADO VALIDO CONOCIDO (no se degrada a invalido).
 *
 * La decision fiscal per-pedido la aplica tep_get_tax_rate() via la var de sesion sppc_vies_reverse_charge
 * (fijada en login desde fb_vies_status). Esta clase solo VALIDA y PERSISTE (registro/admin/cron).
 */
class fb_vies
{
    const ENDPOINT          = 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number';
    const CONNECT_TIMEOUT   = 10;
    const TIMEOUT           = 20;

    // VAT-ROI de Francobordo para obtener nº de consulta (prueba legal). Vacio = consulta sin requester
    // (igual devuelve valido/invalido, pero sin nº de consulta). TODO usuario: rellenar sin prefijo "ES".
    const REQUESTER_COUNTRY = 'ES';
    const REQUESTER_VAT     = 'B82574690'; // Francobordo SL (ROI/VIES verificado 2026-07-02) -> nº de consulta como prueba legal

    // Aviso por email cuando un cliente PASA a valido en VIES (con el nº de consulta). Vacio = desactivado.
    const NOTIFY_EMAIL      = 'marta@francobordo.com';
    const NOTIFY_NAME       = 'Marta';

    // Re-validacion (dias): tras exito y tras error transitorio (reintento antes).
    const RECHECK_DAYS_OK    = 30;
    const RECHECK_DAYS_ERROR = 1;

    // Tablas propias (evita tocar database_tables.php).
    const T_LOG    = 'fb_vies_log';
    const T_STATUS = 'fb_vies_status';

    // ---- Utilidades de formato ------------------------------------------------

    /** ISO2 de pais -> codigo de pais VAT (VIES usa EL para Grecia, XI para Irlanda del Norte). */
    public static function vatCountryCode($iso2)
    {
        $iso2 = strtoupper(trim((string) $iso2));
        if ($iso2 === 'GR') return 'EL'; // Grecia: el codigo de pais VAT es EL
        if ($iso2 === 'FX') return 'FR'; // Francia metropolitana -> FR
        return $iso2;
    }

    /** Normaliza un VAT a [A-Z0-9] mayusculas. */
    public static function normalizeVat($vat)
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $vat));
    }

    /** Separa un VAT en [countryCode, number]. Usa el prefijo de pais si es un codigo UE valido; si no, $isoFallback. */
    public static function splitVat($vat, $isoFallback = '')
    {
        $v = self::normalizeVat($vat);
        static $cc = array('AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','FX','DE','EL','GR','HU','IE','IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE','XI');
        if (strlen($v) > 2 && ctype_alpha(substr($v, 0, 2)) && in_array(substr($v, 0, 2), $cc, true)) {
            return array(self::vatCountryCode(substr($v, 0, 2)), substr($v, 2)); // normaliza prefijo GR->EL, FX->FR
        }
        return array(self::vatCountryCode($isoFallback), $v);
    }

    // ---- Llamada a VIES -------------------------------------------------------

    /**
     * Comprueba un VAT en VIES. Retorno normalizado:
     *  ['ok'=>bool, 'status'=>'valid'|'invalid'|'error', 'valid'=>bool, 'country_code'=>.., 'vat_number'=>..,
     *   'name'=>.., 'address'=>.., 'request_identifier'=>.., 'request_date'=>.., 'error'=>.., 'http'=>int, 'raw'=>str]
     * status 'error' = VIES caido/transitorio -> el llamador debe CONSERVAR el ultimo estado valido conocido.
     */
    public static function check($countryCode, $vatNumber, $useRequester = true)
    {
        $countryCode = self::vatCountryCode($countryCode);
        $vatNumber   = self::normalizeVat($vatNumber);
        $out = array('ok'=>false, 'status'=>'error', 'valid'=>false, 'country_code'=>$countryCode, 'vat_number'=>$vatNumber,
                     'name'=>'', 'address'=>'', 'request_identifier'=>'', 'request_date'=>'', 'error'=>'', 'http'=>0, 'raw'=>'');
        if ($countryCode === '' || $vatNumber === '') { $out['status']='invalid'; $out['error']='empty'; return $out; }

        $payload = array('countryCode'=>$countryCode, 'vatNumber'=>$vatNumber);
        if ($useRequester && self::REQUESTER_VAT !== '') {
            $payload['requesterMemberStateCode'] = self::REQUESTER_COUNTRY;
            $payload['requesterNumber']          = self::REQUESTER_VAT;
        }

        list($http, $body, $cerr) = self::post(json_encode($payload));
        $out['http'] = $http;
        $out['raw']  = $body;

        if ($cerr !== '' || $http === 0) { $out['error'] = 'curl:'.$cerr; return $out; }        // red caida -> error
        if ($http >= 500)               { $out['error'] = 'http'.$http;   return $out; }        // VIES 5xx -> error
        $j = json_decode($body, true);
        if (!is_array($j)) { $out['error'] = 'bad-json:http'.$http; return $out; }

        if (isset($j['actionSucceed']) && $j['actionSucceed'] === false) {
            $err = isset($j['errorWrappers'][0]['error']) ? (string) $j['errorWrappers'][0]['error'] : 'UNKNOWN';
            // requester invalido/no configurado -> reintenta sin requester para obtener al menos valido/invalido
            if ($err === 'INVALID_REQUESTER_INFO' && $useRequester) return self::check($countryCode, $vatNumber, false);
            $out['error']  = $err;
            $out['status'] = ($err === 'INVALID_INPUT') ? 'invalid' : 'error'; // INVALID_INPUT=numero mal; resto=transitorio
            return $out;
        }

        if (array_key_exists('valid', $j)) {
            $out['ok']                 = true;
            $out['valid']              = ($j['valid'] === true);
            $out['status']             = $out['valid'] ? 'valid' : 'invalid';
            $out['name']               = self::clean($j, 'name', 'traderName');
            $out['address']            = self::clean($j, 'address', 'traderStreet');
            $out['request_identifier'] = isset($j['requestIdentifier']) ? (string) $j['requestIdentifier'] : '';
            $out['request_date']       = isset($j['requestDate']) ? (string) $j['requestDate'] : '';
            return $out;
        }

        $out['error'] = 'unexpected-response';
        return $out;
    }

    private static function clean($j, $k1, $k2)
    {
        foreach (array($k1, $k2) as $k) {
            if (isset($j[$k]) && $j[$k] !== '' && $j[$k] !== '---') return (string) $j[$k];
        }
        return '';
    }

    private static function post($jsonBody)
    {
        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonBody,
            CURLOPT_HTTPHEADER     => array('Content-Type: application/json', 'Accept: application/json'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'francobordo-vies/1.0',
        ));
        $body = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = (string) curl_error($ch);
        return array($http, (string) $body, $cerr);
    }

    // ---- Persistencia ---------------------------------------------------------

    /**
     * Valida el VAT de un cliente y persiste (log + status).
     * Aplica "ultimo estado valido conocido": si VIES da 'error' NO se degrada el status previo (solo se
     * anota el intento). Devuelve el array de check() enriquecido con 'applied_status' (lo que quedo guardado).
     */
    public static function validateCustomer($customers_id, $source = 'admin')
    {
        $customers_id = (int) $customers_id;
        $q = tep_db_query("select c.entry_company_tax_id, c.customers_group_id, ab.entry_NIF, ab.entry_country_id, co.countries_iso_code_2 as iso
                             from customers c
                             left join address_book ab on ab.address_book_id = c.customers_default_address_id
                             left join countries co on co.countries_id = ab.entry_country_id
                            where c.customers_id = '" . $customers_id . "'");
        if (!tep_db_num_rows($q)) return array('ok'=>false, 'status'=>'error', 'error'=>'customer-not-found');
        $c = tep_db_fetch_array($q);

        // VAT canonico = entry_company_tax_id (empresa); si vacio, fallback al NIF de la direccion por defecto.
        $vatRaw = trim((string) $c['entry_company_tax_id']);
        if ($vatRaw === '') $vatRaw = trim((string) $c['entry_NIF']);
        if ($vatRaw === '') {
            self::saveStatus($customers_id, '', '', 'unchecked', 0, '', '', 'no-vat');
            return array('ok'=>false, 'status'=>'unchecked', 'error'=>'no-vat');
        }

        list($cc, $num) = self::splitVat($vatRaw, (string) $c['iso']);
        $r = self::check($cc, $num);

        // log siempre
        self::log($customers_id, $r, $source);

        if ($r['status'] === 'error') {
            // VIES caido/transitorio -> conservar status previo; solo tocar last_checked + next_recheck corto
            self::touchError($customers_id, $cc, $num, $r['error']);
            $r['applied_status'] = self::currentStatus($customers_id); // lo que sigue vigente
        } else {
            // valido/invalido -> es un resultado firme, se guarda
            $grpId = (int) ($c['customers_group_id'] ?? 0);
            $wasRC = self::reverseChargeAllowed($customers_id, $grpId); // ¿ya conseguia 0%? (estado PREVIO)
            self::saveStatus($customers_id, $cc, $num, $r['status'], $r['valid'] ? 1 : 0, $r['request_identifier'], $r['name'], '');
            $r['applied_status'] = $r['status'];
            // #FB-VIES: avisar por email SOLO cuando el cliente PASA a conseguir el 0% (reverse charge):
            // VAT valido de OTRO estado miembro UE (no ES nacional, no GB/XI) + grupo 0/1, y antes NO lo
            // tenia. Asi NO se avisa de validaciones nacionales ES, de UK, de marketplaces, ni de los
            // re-checks del cron de clientes que ya eran 0%.
            $getsRC = self::reverseChargeAllowed($customers_id, $grpId); // ¿lo consigue ahora? (estado NUEVO)
            // source='backfill' NO notifica (validacion masiva inicial de la cartera: evitaria decenas
            // de emails de golpe a NOTIFY_EMAIL). Las validaciones normales (signup/admin/cron) si.
            if ($getsRC && !$wasRC && $source !== 'backfill') {
                try { self::notifyValidated($customers_id, $r); } catch (\Throwable $e) { /* no romper la validacion */ }
            }
        }
        return $r;
    }

    /** #FB-VIES: notifica por email (NOTIFY_EMAIL) que un cliente ha pasado a valido, con el nº de consulta. */
    private static function notifyValidated($customers_id, $r)
    {
        if (self::NOTIFY_EMAIL === '' || !function_exists('tep_mail')) return;

        $q = tep_db_query("select c.customers_firstname, c.customers_lastname, c.customers_email_address, c.customers_group_id,
                                  ab.entry_company, co.countries_name
                             from customers c
                             left join address_book ab on ab.address_book_id = c.customers_default_address_id
                             left join countries co on co.countries_id = ab.entry_country_id
                            where c.customers_id = '" . (int) $customers_id . "'");
        $c = tep_db_num_rows($q) ? tep_db_fetch_array($q) : array();

        $nombre  = trim(((string) ($c['customers_firstname'] ?? '')) . ' ' . ((string) ($c['customers_lastname'] ?? '')));
        $empresa = trim((string) ($c['entry_company'] ?? ''));
        $vat     = ((string) ($r['country_code'] ?? '')) . ((string) ($r['vat_number'] ?? ''));
        $reqid   = trim((string) ($r['request_identifier'] ?? ''));
        $grp     = ((int) ($c['customers_group_id'] ?? 0) === 1) ? 'Profesional (G1)' : 'Retail con empresa (G0)';
        $rc      = !in_array(strtoupper((string) ($r['country_code'] ?? '')), array('ES', 'GB', 'XI', ''), true)
                   ? 'SI (0% intracomunitario en entregas UE != Espana/UK)' : 'No (VAT nacional ES o UK)';

        $subject = 'VIES: cliente validado #' . (int) $customers_id . ' - ' . $vat;
        $html  = '<p>Se ha <strong>validado en VIES</strong> el NIF-IVA de un cliente:</p><ul>';
        $html .= '<li><strong>Cliente:</strong> ' . htmlspecialchars($nombre) . ' (ID ' . (int) $customers_id . ', ' . $grp . ')</li>';
        if ($empresa !== '') $html .= '<li><strong>Empresa:</strong> ' . htmlspecialchars($empresa) . '</li>';
        $html .= '<li><strong>Email:</strong> ' . htmlspecialchars((string) ($c['customers_email_address'] ?? '')) . '</li>';
        $html .= '<li><strong>NIF-IVA:</strong> ' . htmlspecialchars($vat) . '</li>';
        $html .= '<li><strong>Razon social (VIES):</strong> ' . htmlspecialchars((string) ($r['name'] ?? '')) . '</li>';
        $html .= '<li><strong>Pais:</strong> ' . htmlspecialchars((string) ($c['countries_name'] ?? '')) . '</li>';
        $html .= '<li><strong>N&ordm; de consulta VIES (prueba):</strong> ' . htmlspecialchars($reqid !== '' ? $reqid : '(no disponible)') . '</li>';
        $html .= '<li><strong>Reverse charge aplicable:</strong> ' . $rc . '</li>';
        $html .= '</ul><p style="color:#888;font-size:12px">Aviso autom&aacute;tico del validador VIES.</p>';

        @tep_mail(self::NOTIFY_NAME, self::NOTIFY_EMAIL, $subject, $html, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
    }

    private static function log($customers_id, $r, $source)
    {
        $raw = mb_substr((string) ($r['raw'] ?? ''), 0, 60000);
        tep_db_query("insert into " . self::T_LOG . "
            (customers_id, country_code, vat_number, status, valid, request_identifier, trader_name, source, http_status, error_message, raw, checked_at)
            values ('" . (int) $customers_id . "',
                    '" . tep_db_input((string) ($r['country_code'] ?? '')) . "',
                    '" . tep_db_input((string) ($r['vat_number'] ?? '')) . "',
                    '" . tep_db_input((string) ($r['status'] ?? 'error')) . "',
                    '" . (int) (!empty($r['valid'])) . "',
                    '" . tep_db_input((string) ($r['request_identifier'] ?? '')) . "',
                    '" . tep_db_input((string) ($r['name'] ?? '')) . "',
                    '" . tep_db_input((string) $source) . "',
                    '" . (int) ($r['http'] ?? 0) . "',
                    '" . tep_db_input((string) ($r['error'] ?? '')) . "',
                    '" . tep_db_input($raw) . "',
                    now())");
    }

    private static function saveStatus($customers_id, $cc, $num, $status, $valid, $reqid, $name, $error)
    {
        $recheck = ($status === 'error') ? self::RECHECK_DAYS_ERROR : self::RECHECK_DAYS_OK;
        $success = ($status === 'valid' || $status === 'invalid') ? 'now()' : 'null';
        tep_db_query("insert into " . self::T_STATUS . "
            (customers_id, country_code, vat_number, status, valid, request_identifier, trader_name, last_checked, last_success, next_recheck, last_error)
            values ('" . (int) $customers_id . "',
                    '" . tep_db_input((string) $cc) . "',
                    '" . tep_db_input((string) $num) . "',
                    '" . tep_db_input((string) $status) . "',
                    '" . (int) $valid . "',
                    '" . tep_db_input((string) $reqid) . "',
                    '" . tep_db_input((string) $name) . "',
                    now(), " . $success . ", date_add(now(), interval " . (int) $recheck . " day),
                    '" . tep_db_input((string) $error) . "')
            on duplicate key update
                    country_code=values(country_code), vat_number=values(vat_number), status=values(status),
                    valid=values(valid), request_identifier=values(request_identifier), trader_name=values(trader_name),
                    last_checked=now(), last_success=values(last_success), next_recheck=values(next_recheck),
                    last_error=values(last_error)");
    }

    /** Error transitorio: conserva status/valid previos; solo actualiza intento y reprograma re-check corto. */
    private static function touchError($customers_id, $cc, $num, $error)
    {
        $exists = tep_db_query("select vat_number from " . self::T_STATUS . " where customers_id = '" . (int) $customers_id . "'");
        if (tep_db_num_rows($exists)) {
            $prev = tep_db_fetch_array($exists);
            if ((string) $prev['vat_number'] !== (string) $num) {
                // #FB-VIES fix D: el VAT cambio y VIES esta caido -> NO conservar la validez del numero
                // anterior (concederia 0% a un VAT sin validar). Reset a 'unchecked' hasta re-validar.
                self::saveStatus($customers_id, $cc, $num, 'unchecked', 0, '', '', $error);
                return;
            }
            tep_db_query("update " . self::T_STATUS . "
                             set last_checked=now(),
                                 next_recheck=date_add(now(), interval " . (int) self::RECHECK_DAYS_ERROR . " day),
                                 last_error='" . tep_db_input((string) $error) . "'
                           where customers_id = '" . (int) $customers_id . "'");
        } else {
            // nunca validado y VIES caido -> queda 'unchecked' (sin conceder 0%)
            self::saveStatus($customers_id, $cc, $num, 'unchecked', 0, '', '', $error);
        }
    }

    /** Devuelve el status vigente ('valid'|'invalid'|'error'|'unchecked') de un cliente. */
    public static function currentStatus($customers_id)
    {
        $q = tep_db_query("select status from " . self::T_STATUS . " where customers_id = '" . (int) $customers_id . "'");
        if (tep_db_num_rows($q)) { $row = tep_db_fetch_array($q); return (string) $row['status']; }
        return 'unchecked';
    }

    /**
     * ¿Este cliente tiene derecho a reverse charge? Grupo 0 (retail con empresa) o 1 (Profesionales) +
     * VAT VALIDO en VIES y de OTRO estado miembro UE (no ES nacional, no GB/XI post-Brexit). Es el valor
     * que se fija en la sesion (sppc_vies_reverse_charge). La condicion de PAIS DE ENTREGA (UE!=ES,!=UK)
     * la aplica tep_get_tax_rate por cada pedido.
     */
    public static function reverseChargeAllowed($customers_id, $customers_group_id)
    {
        // Grupo 0 (retail con empresa) o 1 (Profesionales); NO marketplaces (2=Amazon, 3=EBAY).
        if (!in_array((int) $customers_group_id, array(0, 1), true)) return false;
        $q = tep_db_query("select valid from " . self::T_STATUS . "
                            where customers_id = '" . (int) $customers_id . "'
                              and valid = '1'
                              and country_code not in ('ES', 'GB', 'XI', '')");
        return tep_db_num_rows($q) > 0;
    }

    // ---- Instalacion (idempotente) -------------------------------------------

    public static function ensureTables()
    {
        tep_db_query("create table if not exists " . self::T_STATUS . " (
            customers_id int(11) not null,
            country_code varchar(4) not null default '',
            vat_number varchar(20) not null default '',
            status enum('valid','invalid','error','unchecked') not null default 'unchecked',
            valid tinyint(1) not null default 0,
            request_identifier varchar(64) not null default '',
            trader_name varchar(255) not null default '',
            last_checked datetime null,
            last_success datetime null,
            next_recheck datetime null,
            last_error varchar(255) not null default '',
            primary key (customers_id),
            key idx_next_recheck (next_recheck),
            key idx_valid (valid)
        ) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_spanish_ci");

        tep_db_query("create table if not exists " . self::T_LOG . " (
            id int(11) not null auto_increment,
            customers_id int(11) not null,
            country_code varchar(4) not null default '',
            vat_number varchar(20) not null default '',
            status enum('valid','invalid','error','unchecked') not null default 'error',
            valid tinyint(1) not null default 0,
            request_identifier varchar(64) not null default '',
            trader_name varchar(255) not null default '',
            source varchar(20) not null default '',
            http_status int(11) not null default 0,
            error_message varchar(255) not null default '',
            raw longtext null,
            checked_at datetime null,
            primary key (id),
            key idx_customer (customers_id, checked_at)
        ) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_spanish_ci");
    }
}
