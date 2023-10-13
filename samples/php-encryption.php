<?php

    /*
     * encryptValue: Encrypt a value to use in web responses.
     * @param string $value // The value to encrypt
     * @param bool $uriSafe // Make the string URL safe or not?
     * @returns void
     */
    public function encryptValue(string $value, bool $uriSafe = false) : string {
    
        if (empty($value)) return '';
        $encrypter = \Config\Services::encrypter();
        $value = $encrypter->encrypt($value); // Encrypt to binary format.
        if (empty($value)) return '';
        $value = base64_encode($value); // Encode to safe usable format.
        if (empty($value)) return '';
        if ($uriSafe === true) $value = strtr($value, '+/=', '-_,');
        return $value;
    
    }

    /*
     * decryptValue: Decrypt a value to use in web responses.
     * @param string $value // The value to decrypt
     * @param bool $uriSafe // Is the value from a URL safe encryption?
     * @returns void
     */
    public function decryptValue(string $value, bool $uriSafe = false) : string {
    
        if (empty($value)) return '';
        if ($uriSafe === true) $value = strtr($value, '-_,', '+/=');
        $encrypter = \Config\Services::encrypter();
        $value = base64_decode($value); // Decode to binary format.
        if (empty($value)) return '';
        $value = $encrypter->decrypt($value); // Decrypt to text format.
        if (empty($value)) return '';
        return $value;
    
    }	

    