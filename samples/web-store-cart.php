<?php
    
    public function index() {
    
        try {
    
            $header = array();
            $body = array();
            $footer = array();
            $time = time();
            $existing_order = false;
            $isauth = $this->sessions->isAuth();
            $body['isauth'] = $isauth;
            $header['auth_menu'] = $isauth;
            $body['same_shipping'] = true;
            $body['geo_country'] = (!empty($_SESSION['geo']['iso'])) ? $_SESSION['geo']['iso'] : 'AU';
            $body['geo_country'] = $this->structures->geo_iso_country($body['geo_country'], false);
    
            // USER
    
            if ($isauth === true) {
    
                $session = $this->sessions->sessionUser(true, true);
                if (empty($session) || empty($session['auth']['id'])) throw new \Exception('User ID ' . $session['auth']['id'] . ' empty.');
                $user = $this->accountsModel->getUser(array('id' => $session['auth']['id'], 'status' => '31'));
                if (empty($user) || empty($user['account_id'])) throw new \Exception('User ID ' . $session['auth']['id'] . ' not found or is inactive.');
                $account = $this->accountsModel->getAccount(array('id' => $user['account_id'], 'status' => '31'));
                if (empty($account)) throw new \Exception('Account ID ' . $account['id'] . ' not found.');
    
                $data['order_id'] = array($this->format->inputs('uri', 2, array(), $this->uri), [0,1000]);
    
                $assessed = $this->format->assess($data);
                if (!empty($assessed['dirty'])) throw new \Exception($assessed['dirty']);
                if (empty($assessed['clean'])) throw new \Exception('Empty post or no clean key values passed through.');
                $clean = $assessed['clean'];
    
                if (!empty($clean['order_id'])) {
    
                    $decrypted = $this->utilities->decryptValue($clean['order_id'], true);
                    if (empty($decrypted) || !is_numeric($decrypted) || strlen($decrypted) !== 10) throw new \Exception('Decrypted value (' . $decrypted . ') is invalid.');
                    $body['encrypted_order_id'] = $clean['order_id'];
                    $clean['order_id'] = $decrypted;
    
                    $order = $this->ordersModel->getOrder(array('id' => $clean['order_id'], 'account_id' => $account['id'], 'type' => '31', 'archive' => '30'));
                    if (empty($order)) throw new \Exception('Order ID ' . $clean['order_id'] . ' not found or archived.');
    
                    // INVOICE
                    // If not unpaid, empty session invoice or too old redirect to account area.
    
                    $invoice = $this->ordersModel->getInvoice(array('order_id' => $clean['order_id']));
                    if (empty($invoice)) throw new \Exception('Unable to locate invoice for existing Order ID ' . $clean['order_id'] . ' in database.');
                    if (empty($invoice['status']) || $invoice['status'] != '32' || ($order['created'] < ($time - 3600)) || empty($session['invoice'])) { // Not in Unpaid Status, empty invoice or Created more than 1 hour ago
                        $this->utilities->htmlResponse(302, SAFEDOMAIN . 'account', false);
                        return;
                    }
    
                    $existing_order = true;
    
                    // Existing Order 
    
                    $billing_group = array();
                    $billing_group['billing_firstname'] = $order['billing_firstname'];
                    $billing_group['billing_lastname'] = $order['billing_lastname'];
                    $billing_group['billing_company'] = $order['billing_company'];
                    $billing_group['billing_email'] = $order['billing_email'];
                    $billing_group['billing_phone'] = $order['billing_phone_1_number'];
                    $billing_group['billing_mobile'] = $order['billing_phone_2_number'];
                    $billing_group['billing_street_1'] = $order['billing_street_1'];
                    $billing_group['billing_city'] = $order['billing_city'];
                    $billing_group['billing_state'] = $order['billing_state'];
                    $billing_group['billing_postcode'] = $order['billing_postcode'];
                    $billing_group['billing_country'] = $this->structures->geo_iso_country($order['billing_country'], false);
    
                    $shipping_group = array();
                    $shipping_group['shipping_firstname'] = $order['shipping_firstname'];
                    $shipping_group['shipping_lastname'] = $order['shipping_lastname'];
                    $shipping_group['shipping_company'] = $order['shipping_company'];
                    $shipping_group['shipping_email'] = $order['shipping_email'];
                    $shipping_group['shipping_phone'] = $order['shipping_phone_1_number'];
                    $shipping_group['shipping_mobile'] = $order['shipping_phone_2_number'];
                    $shipping_group['shipping_street_1'] = $order['shipping_street_1'];
                    $shipping_group['shipping_city'] = $order['shipping_city'];
                    $shipping_group['shipping_state'] = $order['shipping_state'];
                    $shipping_group['shipping_postcode'] = $order['shipping_postcode'];
                    $shipping_group['shipping_country'] = $this->structures->geo_iso_country($order['shipping_country'], false);
    
                    if (empty(array_diff($billing_group, $shipping_group))) $body['same_shipping'] = true;
                    else $body['same_shipping'] = false;
    
                    $body = array_merge($body, $billing_group);
                    if ($body['same_shipping'] === false) $body = array_merge($body, $shipping_group);
    
                    $body['tax_id_type'] = $order['billing_tax_id_type'];
                    $body['tax_id'] = $order['billing_tax_id'];
                    $body['customer_reference'] = $order['customer_reference'];
                    $body['additional_email_1'] = $order['billing_additional_email_1'];
                    $body['additional_email_2'] = $order['billing_additional_email_2'];
                    $body['additional_email_3'] = $order['billing_additional_email_3'];
                    
            // RENDER
    
            echo view('structure/header', $header);
            echo view('pages/checkout', $body);
            echo view('structure/footer', $footer);
    
        } catch (\Exception $error) {
    
            $log = 'Controller failure (' . $error . ')';
            $this->events->log('system', null, 'Critical', NULL, $log, true, true);
            $this->sessions->logout();
            if ($this->utilities->ajaxHtml() === 'ajax') $this->utilities->ajaxResponse(200, 403, '', false);
            else $this->utilities->htmlResponse(302, null, false);
    
        }
    
    }

