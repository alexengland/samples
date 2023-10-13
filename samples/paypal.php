<?php

    namespace App\Libraries;
    
    // Paypal SDK Version 
    
    require('/home/external/paypal/autoload.php');
    
    use PayPal\Api\Amount;
    use PayPal\Api\Details;
    use PayPal\Api\Item;
    use PayPal\Api\ItemList;
    use PayPal\Api\Payer;
    use PayPal\Api\Payment;
    use PayPal\Api\RedirectUrls;
    use PayPal\Api\Transaction;
    use PayPal\Api\ExecutePayment;
    use PayPal\Api\PaymentExecution;
    use CodeIgniter\Database\MySQLi\Connection;
    use App\Libraries\Format;
    use App\Libraries\Utilities;
    
    class Paypal {
    
        protected $database;
        protected $events;
        protected $format;
        protected $utilities;
    
        public function __construct(Connection $database, Events $events, Format $format, Utilities $utilities) {
    
            $this->db = $database;
            $this->events = $events;
            $this->format = $format;
            $this->utilities = $utilities;
    
            $this->apiContext = new \PayPal\Rest\ApiContext();
        
            }

        /*
         * getPayPalUrl: Get the PayPal signed URL.
         * @param array $order // Order array
         * @param array $invoice // Invoice array
         * @param array $items // Items array
         * @param array $taxes // Taxes array
         * @returns bool
         */
    
        public function getPayPalUrl(array $order, array $invoice, array $items, array $taxes) : bool {
    
            $this->apiContext->setConfig(array('mode' => 'live'));
    
            $payer = new Payer();
            $payer->setPaymentMethod("paypal");
    
            $item_number = 1;
            $item_array = array();
            $subtotal = 0;
    
            foreach($items as $item) {
    
                $item_subtotal = number_format($item['unit'] * $item['quantity'],2,'.','');
                if (!empty($item['sku'])) $item['sku'] = preg_replace('/\s/', '', $item['sku']);
                $item_sku = (!empty($item['sku'])) ? $item['sku'] : '1030030001';
    
                if (!empty($item_subtotal) AND $item_subtotal >= 1.00) {
    
                    $item_name = 'item' . $item_number;
                    $$item_name = new Item();
                    $$item_name->setName($item['name'])
                        ->setCurrency($invoice['currency'])
                        ->setQuantity($item['quantity'])
                        ->setSku(' ' . $item_sku)
                        ->setPrice($item['unit']);
    
                    $subtotal = $subtotal + $item_subtotal;
    
                    $item_array[] = $$item_name;
                    $item_number++;
    
                }
    
            }
    
            $itemList = new ItemList();
            $itemList->setItems($item_array);
    
            // HANDLE GST
    
            if (!empty($taxes)) {
    
                $tax = 0;
    
                foreach ($taxes as $tax_line) {
    
                    if (!empty($tax_line['amount']) && $tax_line['amount'] > 0.00) {
    
                        $tax += number_format($tax_line['amount'],2,'.','');
    
                    }
    
                }
    
            } else {
    
                $tax = 0;
    
            }
    
            $subtotal = number_format($subtotal,2,'.','');
            $finaltotal = number_format($subtotal + $tax,2,'.','');
    
            $details = new Details();
            $details
                ->setShipping(0)
                ->setTax($tax)
                ->setSubtotal($subtotal);
    
            $amount = new Amount();
            $amount
                ->setCurrency($invoice['currency'])
                ->setTotal($finaltotal)
                ->setDetails($details);
    
            $transaction = new Transaction();
            $transaction->setAmount($amount)
                ->setItemList($itemList)
                ->setDescription($order['summary'])
                ->setInvoiceNumber($order['id']);
    
            // SET SESSION STAMP
    
            $process = $this->utilities->encryptValue('process', true);
            $cancel = $this->utilities->encryptValue('cancel', true);
    
            $_SESSION['paypal_process'] = $process;
            $_SESSION['paypal_cancel'] = $cancel;
    
            $redirectUrls = new RedirectUrls();
            $redirectUrls
                ->setReturnUrl(SAFEDOMAIN . "checkout/paypal?paypal=" . $process)
                ->setCancelUrl(SAFEDOMAIN . "checkout/paypal?paypal=" . $cancel);
    
            $payment = new Payment();
            $payment->setIntent("sale")
                ->setPayer($payer)
                ->setRedirectUrls($redirectUrls)
                ->setTransactions(array($transaction));
    
            $request = clone $payment;
    
            try { $payment->create($this->apiContext); }
    
            catch (\Exception $ex) {
    
                $log = 'PayPal payment in the amount of $' . $finaltotal . ' ' . $invoice['currency'] . ' for ' . $order['summary'] . ' (' . $order['id'] . ') failed to process (Exception ' . $ex . ' - Request ' . $request . ').
                    ' . $ex->getCode() . '
                    ' . $ex->getData()
                ;
    
                $this->events->log('system', null, 'Critical', NULL, $log, true, true);
                if (!empty($order['account_id'])) $this->events->log('account', $order['account_id'], 'Critical', NULL, $log, false, false);
                if (!empty($order['id'])) $this->events->log('order', $order['id'], 'Critical', NULL, $log, false, false);
    
                return false;
    
            }
    
            $approvalUrl = $payment->getApprovalLink();
    
            if (!filter_var($approvalUrl, FILTER_VALIDATE_URL, FILTER_FLAG_QUERY_REQUIRED) === false) {
    
                $log = 'PayPal payment link in the amount of $' . $finaltotal . ' ' . $invoice['currency'] . ' for ' . $order['summary'] . ' (' . $order['id'] . ') was accepted by PayPal for verification.';
    
                $this->events->log('system', null, 'Information', NULL, $log, false, false);
                if (!empty($order['account_id'])) $this->events->log('account', $order['account_id'], 'Information', NULL, $log, false, false);
                if (!empty($order['id'])) $this->events->log('order', $order['id'], 'Information', NULL, $log, false, false);
    
                return $approvalUrl;
    
            } else {
    
                $log = 'PayPal payment in the amount of $' . $finaltotal . ' ' . $invoice['currency'] . ' for ' . $order['summary'] . ' (' . $order['id'] . ') could not be processed due to PayPal website failure (PayPal servers did not responding correctly).';
    
                $this->events->log('system', null, 'Critical', NULL, $log, true, true);
                if (!empty($order['account_id'])) $this->events->log('account', $order['account_id'], 'Critical', NULL, $log, false, false);
                if (!empty($order['id'])) $this->events->log('order', $order['id'], 'Critical', NULL, $log, false, false);
    
                return false;
    
            }
    
        }
    
    }
