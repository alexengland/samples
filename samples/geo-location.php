<?php

    use GeoIp2\Database\Reader;

    class Geo {

        protected $events;

        public function __construct(Events $events) {

            $this->events = $events;

        }

        /*
         * geoFind: Locate the session's country of origin.
         * @param array $ip // Inbound IP address
         * @returns array
         */

        public function geoFind(string $ip) : array {

            $default = array('country' => 'Australia', 'iso' => 'AU');

            try {

                $production = '/home/user/geoip.mmdb';
                $reader = new Reader($production);
                $data = $reader->country($ip);
                $iso = $data->country->isoCode;
                $country = $data->country->name;
                if (empty($iso) || empty($country)) throw new \Exception('Geo lookup failed for IP ' . $ip . ' (AU default).');
                $default = array('country' => $country, 'iso' => $iso);

            } catch (\Exception $error) {

                $log = 'Geo lookup failed (' . $error . ')';
                $this->events->log('system', null, 'Critical', NULL, $log, true, true);
                return $default;

            }

            return $default;

        }

    }
