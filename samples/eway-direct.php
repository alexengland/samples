<?php

    /*
     * ewayDirectPayment: Submit a direct payment to eWay.
     * @param array $data // The order transaction ID
     * @returns mixed
     */

    public function ewayDirectPayment($data = array()) : mixed {

        // BUILD PAYMENT ARRAY

        $transaction = [

            'Customer' => [
                'Title' => '',
                'FirstName' => $data['firstname'],
                'LastName' => $data['lastname'],
                'CompanyName' => $data['company'],
                'JobDescription' => '',
                'Street1' => $data['street'],
                'Street2' => '',
                'City' => $data['city'],
                'State' => $data['state'],
                'PostalCode' => $data['postcode'],
                'Country' => strtolower($data['country']),
                'Phone' => '',
                'Mobile' => '',
                'Email' => $data['email'],
                'CardDetails' => [
                    'Name' => $data['card_name'],
                    'Number' => $data['card_number'],
                    'ExpiryMonth' => $data['expiry_month'],
                    'ExpiryYear' => $data['expiry_year'],
                    'CVN' => $data['card_cvc']
                ]
            ],
            'Payment' => [
                'TotalAmount' => $data['total_amount'],
                'InvoiceNumber' => $data['order_id'],
                'InvoiceDescription' => substr(trim($data['summary']), 0, 32) . ' - New Unsaved Card',
                'InvoiceReference' => 'Account ID ' . $data['account_id'],
                'CurrencyCode' => $data['currency']
            ],
            'CustomerIP' => $data['ip'],
            'TransactionType' => \Eway\Rapid\Enum\TransactionType::PURCHASE,
            'Capture' => true

        ];

        // SUBMIT TRANSACTION

        $eway = $this->client->createTransaction(\Eway\Rapid\Enum\ApiMethod::DIRECT, $transaction);

        // RESPONSE FROM EWAY - Eway Codes: eway.io/api-v3/#transaction-response-messages

        if (!empty($eway->ResponseMessage) AND !empty($eway->TransactionID)) $response_code = $eway->ResponseMessage; else $response_code = 'Error';

        $response = $this->ewayResponseCodes($response_code);

        if (!empty($eway->TransactionID)) $transaction_id = $this->format->sanitize($eway->TransactionID, 'xss'); else $transaction_id = '';

        // SUCCESS

        $card_english = 'Card';

        if (!empty($data['card_type'])) {

            if ($data['card_type'] == '30') $card_english = 'Visa';
            else if ($data['card_type'] == '31') $card_english = 'Mastercard';
            else if ($data['card_type'] == '32') $card_english = 'Amex';
        }

        if (!empty($transaction_id) AND !empty($response['result']) AND $response['result'] == '1') {

            if (!empty($data['account_id'])) $this->events->log('account', $data['account_id'], 'Information', NULL, $log, false, false);
            if (!empty($data['order_id'])) $this->events->log('order', $data['order_id'], 'Information', NULL, $log, false, false);

        } else {

            if (!empty($data['account_id'])) $this->events->log('account', $data['account_id'], 'Warning', NULL, $log, false, false);
            if (!empty($data['order_id'])) $this->events->log('order', $data['order_id'], 'Warning', NULL, $log, false, false);

        }

        // RETURN RESULT

        return $response;

    }