<?php

    namespace App\Libraries;
    
    class Sms {

        /*
         * newSms: Send a new SMS.
         * @param string $sender // Sender ID
         * @param string $phone // Sender mobile number
         * @param string $country // Country of origin (usually auto detect)
         * @param string $body // Body of message
         * @param string $logPurpose // Logging type
         * @returns bool
         */
    
        public function newSms(string $sender = 'Support', string $phone = '61400100200', string $country = 'Auto', string $body = 'Default', string $logPurpose = 'SMS') : bool {
    
            // First try to wash the number, if ok then we can roll forward
    
            $phone = $this->number_wash($phone, $country);
            if ($phone === false) return false;
    
            // Call the MessageBird class
    
            require_once('/home/messagebird/php-rest-api/autoload.php');
    
            // Assemble the message and try to send
    
            $sms = new \MessageBird\Objects\Message();
            $sms->originator = $sender;
            $sms->reference = $logPurpose;
            $sms->recipients = $phone['washed_number'];
            $sms->body = $body;
    
            try {

                $smsresult = $messagebird->messages->create($sms);
    
            } catch (\MessageBird\Exceptions\AuthenticateException $e) { // Invalid access key
    
                $log = "MessageBird Access Key Error thrown by system.";
                mail('', 'SMS Alert', $log);
                return false;
    
            } catch (\MessageBird\Exceptions\BalanceException $e) { // Out of credit
    
                $log = "MessageBird API out of credit.";
                mail('', 'SMS Alert', $log);
                return false;
    
            } catch (\Exception $e) {
    
                // General error
                $log = "Error sending to +" . $phone['washed_number'] . " (" . $phone['country'] . ") - MessageBird API error: " . $e->getMessage();
                return false;
    
            }
    
            return true;
    
        }
    
    }
