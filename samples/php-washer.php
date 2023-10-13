<?php

    /*
     * cleaner: Clean a string or array.
     * @param string $data // Data string to clean
     * @param array $formats // Array of cleaning formats
     * @returns array
     */
    public function cleaner(string $data = '', Array $formats = array()) : array {

        if (empty($data)) return '';

        if (is_array($data)) {

            foreach ($data as $key => $value) {

                if (is_array($value)) {

                    $clean_key = $this->sanitize($key, 'keys');
                    if (!is_bool($value) && !is_numeric($value)) $data[$clean_key] = $this->cleaner($value, $formats);
                    if ($key != $clean_key) unset($data[$key]);

                } else {

                    foreach ($formats as $format) {

                        $clean_key = $this->sanitize($key, 'keys');
                        if (!is_bool($value) && !is_numeric($value)) $data[$clean_key] = $this->sanitize($value, $format);
                        if ($key != $clean_key) unset($data[$key]);

                    }

                }

            }

        } else {

            if (!is_bool($data) && !is_numeric($data)) {

                if (is_array($formats)) foreach ($formats as $format) $data = $this->sanitize($data, $format);
                else $data = $this->sanitize($data, $formats);

            }

        }

        $data = (!empty($data)) ? $data : '';

        return $data;

    }
