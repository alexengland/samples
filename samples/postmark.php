<?php

    namespace App\Libraries;
    
    use CodeIgniter\Database\MySQLi\Connection;
    use App\Libraries\Format;
    use CodeIgniter\HTTP\RequestInterface;
    use CodeIgniter\Email\Email;
    use App\Libraries\Structures;
    use App\Libraries\Utilities;
    
    class postmarkEmail {
    
        protected $database;
        protected $format;
        protected $request;
        protected $events;
        protected $email;
        protected $structures;
        protected $utilities;
    
        public function __construct(Connection $database, Format $format, RequestInterface $request, Events $events, Email $email, Structures $structures, Utilities $utilities) {
    
            $this->db = $database;
            $this->format = $format;
            $this->request = $request;
            $this->events = $events;
            $this->email = $email;
            $this->structures = $structures;
            $this->utilities = $utilities;
            $this->ip = $this->request->getIPAddress();
    
        }

        /*
         * initiateResetEmail: Send a password reset email.
         * @param string $username // The target username
         * @param string $encryptedUsername // The username in encrypted string format
         * @param string $encryptedTime // The time in encrypted string format
         * @returns bool
         */
    
        public function initiateResetEmail(string $username, string $encryptedUsername, int $encryptedTime) : bool {
    
            try {
    
                if (empty($username) || !filter_var($username, FILTER_VALIDATE_EMAIL) || empty($encryptedUsername)) return false;
    
                $this->email->clear(true);
    
                // CONFIG
    
                $ip = $this->ip;
                $link = SAFEDOMAINFRONTEND . 'login/reset/' . $encryptedUsername . '/' . $encryptedTime;
    
                $path = '/home/mspanel/app/ThirdParty/templates/email/';
    
                // HTML
    
                $html = file_get_contents($path . 'initiate_password_reset.html');
                $html = preg_replace('{#LINK#}', $link, $html);
    
                // PLAIN TEXT
    
                $plain = file_get_contents($path . 'initiate_password_reset.txt');
                $plain = preg_replace('{#LINK#}', $link, $plain);
    
                // SEND
    
                $this->email->setFrom('support@mindsystems.com.au', 'Mindsystems');
                $this->email->setTo($username);
    
                $this->email->setSubject('Mindsystems Account Reset');
                $this->email->setMessage($html);
                $this->email->setAltMessage($plain);
    
                if (!$this->email->send()) {
    
                    // LOG FAILURE	
    
                    $log = 'Failed to send reset email (' . $username . ')';
                    $this->events->log('system', null, 'Critical', NULL, $log, true, true);
    
                }
    
                return true;
    
            } catch (\Exception $error) {
    
                $log = 'Communications library failure for MSP initiated reset code email (' . $error . ')';
                $this->events->log('system', null, 'Critical', NULL, $log, true, true);
                return false;
    
            }
    
        }
    
    }
