<?php

    /*
     * ajaxResponse: Respond with an ajax reply.
     * @param string $header // Header message
     * @param string $code // Code to use in http
     * @param string $string // Response string
     * @param bool $token // Use a token or not
     * @param mixed $xss // Mixed XSS actions
     * @returns void
     */
    public function ajaxResponse(string $header, string $code, string $string = '', bool $token = false, mixed $xss = null) : void {
    
        try {
    
            if ($this->ajaxHtml() !== 'ajax') throw new \Exception('Request method was not AJAX, when AJAX was specified as mandatory.');
            $data = array();
            $xss = (empty($xss) || !is_array($xss)) ? array('xss', 'string', 'tags') : $xss;
            $header = $this->format->cleaner($header, array('numeric'));
            $header = (!empty($header)) ? $header : 403;
            $data['html_header_code'] = $header;
            $code = $this->format->cleaner($code, array('numeric'));
            $code = (!empty($code)) ? $code : 403;
            $data['json']['rc'] = $code;
            $data['json']['rs'] = $this->format->cleaner($string, $xss);
            if ($token) $data['json']['ca'] = csrf_hash();
            $data['json'] = json_encode($data['json']);
            echo view('system/output_ajax', $data);
    
        } catch (\Exception $error) {
    
            $log = 'Error thrown while executing AJAX Response class (' . $error . ')';
            $this->events->log('system', null, 'Warning', NULL, $log, true, true);
            $this->sessions->logout();
            $this->htmlResponse(302, '', false);
    
        }
    
    }	