<?php

    namespace App\Libraries;

    use CodeIgniter\Database\MySQLi\Connection;
    use App\Libraries\Format;
    use CodeIgniter\HTTP\RequestInterface;
    use App\Libraries\Structures;
    use App\Libraries\Utilities;

    require '/home/xero/vendor/autoload.php';
    use XeroAPI\XeroPHP\AccountingObjectSerializer;

    class Xero {

        protected $database;
        protected $format;
        protected $request;
        protected $events;
        protected $structures;
        protected $utilities;

        public function __construct(Connection $database, Format $format, RequestInterface $request, Events $events, Structures $structures, Utilities $utilities) {

            $this->db = $database;
            $this->format = $format;
            $this->request = $request;
            $this->events = $events;
            $this->structures = $structures;
            $this->utilities = $utilities;
            $this->ip = $this->request->getIPAddress();

        }

        /*
         * authorisation: Get initial authorisation from Xero API.
         * @returns void
         */

        public function authorisation() : void {

            $provider = new \League\OAuth2\Client\Provider\GenericProvider([
                'clientId'                => $this->clientId,
                'clientSecret'            => $this->clientSecret,
                'redirectUri'             => '',
                'urlAuthorize'            => 'https://login.xero.com/identity/connect/authorize',
                'urlAccessToken'          => 'https://identity.xero.com/connect/token',
                'urlResourceOwnerDetails' => 'https://api.xero.com/api.xro/2.0/Organisation'
            ]);

            // Scope defines the data your app has permission to access.
            // Learn more about scopes at https://developer.xero.com/documentation/oauth2/scopes

            $options = [
                'scope' => ['openid email profile offline_access assets projects accounting.settings accounting.transactions accounting.contacts accounting.journals.read accounting.reports.read accounting.attachments']
            ];

            // This returns the authorizeUrl with necessary parameters applied (e.g. state).

            $authorizationUrl = $provider->getAuthorizationUrl($options);

            // Save the state generated.
            // For security, on callback we compare the saved state with the one returned to ensure they match.

            $_SESSION['oauth2state'] = $provider->getState();

            // Redirect the user to the authorization URL.

            header('Location: ' . $authorizationUrl);
            exit();

        }

        /*
         * callback: Handle Xero callback.
         * @returns void
         */

        public function callback() : void {

            $provider = new \League\OAuth2\Client\Provider\GenericProvider([
                'clientId'                => $this->clientId,
                'clientSecret'            => $this->clientSecret,
                'redirectUri'             => '',
                'urlAuthorize'            => 'https://login.xero.com/identity/connect/authorize',
                'urlAccessToken'          => 'https://identity.xero.com/connect/token',
                'urlResourceOwnerDetails' => 'https://api.xero.com/api.xro/2.0/Organisation'
            ]);

            // If we don't have an authorization code then get one.

            if (!isset($_GET['code'])) {

                echo "Something went wrong, no authorization code found";
                exit("Something went wrong, no authorization code found");

                // Check given state against previously stored one to mitigate CSRF attack.

            } elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {

                echo "Invalid State";
                unset($_SESSION['oauth2state']);
                exit('Invalid state');

            } else {

                try {

                    // Try to get an access token using the authorization code grant.

                    $accessToken = $provider->getAccessToken('authorization_code', [
                        'code' => $_GET['code']
                    ]);

                    $config = \XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken((string)$accessToken->getToken());

                    $identityApi = new \XeroAPI\XeroPHP\Api\IdentityApi(
                        new \GuzzleHttp\Client(),
                        $config
                    );

                    $result = $identityApi->getConnections();

                    // Save my tokens, expiration tenant_id etc

                    $update = array(

                        'token' => $accessToken->getToken(),
                        'expires' => $accessToken->getExpires(),
                        'tenant_id' => $result[0]->getTenantId(),
                        'refresh_token' => $accessToken->getRefreshToken(),
                        'id_token' => $accessToken->getValues()["id_token"]

                    );

                    $this->db->table('system_xero')->update($update, array('id' => '1'));

                    exit();

                } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {

                    echo "Xero Failed... Contact Support.";
                    exit();

                }

            }

        }

        /*
         * sendXero: Send transactions to Xero API.
         * @param array $orderIds // An array of Order IDs to be processed
         * @returns void
         */

        public function sendXero(array $orderIds = array()) : void {

            // Variables

            $failures = array();
            $token = $this->db->table('system_xero')->getWhere(array('id' => '1'), 1, 0)->getRowArray()['token'];
            $xeroTenantId = $this->db->table('system_xero')->getWhere(array('id' => '1'), 1, 0)->getRowArray()['tenant_id'];
            $system_user = $order = $this->db->table('system_users')->getWhere(array('id' => $_SESSION['auth']['id']), 1, 0)->getRowArray();

            // API Connection

            $config = \XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken((string)$token);
            $apiInstance = new \XeroAPI\XeroPHP\Api\AccountingApi(
                new \GuzzleHttp\Client(),
                $config
            );

            // Loop through Orders

            foreach ($orderIds as $order_id) {

                // Get the Order

                $order = $this->db->table('orders')->getWhere(array('id' => $order_id, 'archive' => '30', 'type' => '31'), 1, 0)->getRowArray();
                if (!empty($order)) $order = $this->format->cleaner($order, array('xss'));
                if (empty($order)) continue;

                // Get Account

                $account = $this->db->table('accounts')->getWhere(array('id' => $order['account_id']), 1, 0)->getRowArray();
                if (!empty($account)) $account = $this->format->cleaner($account, array('xss'));
                if (empty($account)) continue;

                // Get Primary User

                if ($account['primary_user']) {
                    $user = $this->db->table('accounts_users')->getWhere(array('id' => $account['primary_user']), 1, 0)->getRowArray();
                    if (!empty($user)) $user = $this->format->cleaner($user, array('xss'));
                    if (empty($user)) continue;
                } else {
                    $user = array();
                }

                // Get the Invoice

                $invoice = $this->db->table('orders_invoices')->getWhere(array('order_id' => $order['id']), 1, 0)->getRowArray();
                if (!empty($invoice)) $invoice = $this->format->cleaner($invoice, array('xss'));
                if (empty($invoice)) continue;

                // Get the Items

                $items = $this->db->table('orders_invoices_items')->getWhere(array('invoice_id' => $invoice['id']), 0, 0)->getResult('array');
                if (!empty($items)) $items = $this->format->cleaner($items, array('xss'));
                if (empty($items)) continue;

                // Get Options

                foreach ($items as $item) {

                    $options = $this->db->table('orders_invoices_items_options')->getWhere(array('item_id' => $item['id']), 0, 0)->getResult('array');
                    if (!empty($options)) $options = $this->format->cleaner($options, array('xss'));
                    if (empty($options)) continue;
                    $item['options'] = $options;

                }

                // Get Adjustments

                $adjustments = $this->db->table('orders_invoices_adjustments')->getWhere(array('invoice_id' => $invoice['id']), 0, 0)->getResult('array');
                if (!empty($adjustments)) $adjustments = $this->format->cleaner($adjustments, array('xss'));

                // Get Taxes

                $taxes = $this->db->table('orders_invoices_taxes')->getWhere(array('invoice_id' => $invoice['id']), 0, 0)->getResult('array');
                if (!empty($taxes)) $taxes = $this->format->cleaner($taxes, array('xss'));

                // Times

                date_default_timezone_set('Australia/Melbourne');
                $created = date('Y-m-d H:i:s', $order['created']);
                $invoice_due = date('Y-m-d H:i:s', $invoice['due']);

                // Check database first for Xero Contact ID
                // https://developer.xero.com/documentation/api/contacts#GET
                // https://developer.xero.com/documentation/api/contacts#optimised-parameters

                // Create / update contact in Xero and get ContactID

                if (empty($xero_contact_id)) $xero_contact_id = $this->createContact($order, $user);
                if (!empty($xero_contact_id) && $xero_contact_id == 401) return 401;

                $xero_contact_id = $xero_contact_id->getContacts()[0]->getContactId();

                // Check how much tax was charged, if it was empty then it is GST excempt in Xero (i.e. EXEMPTOUTPUT)

                $is_gst = false;

                if (!empty($taxes)) {

                    foreach ($taxes as $tax) {
                        if ($tax['type'] == '30') {
                            $is_gst = true;
                            break;
                        }
                    }

                }

                if ($is_gst === true) {

                    $taxable = "OUTPUT";

                } else {

                    $taxable = "EXEMPTOUTPUT";

                }

                // Create base invoice

                $line_items = [];

                foreach ($items as $item) {

                    $line_entry = new \XeroAPI\XeroPHP\Models\Accounting\LineItem;
                    $description = (!empty($item['sku'])) ? $item['name'] . ' (' . $item['sku'] . ')' : $item['name'];
                    $line_entry
                        ->setDescription($description)
                        ->setQuantity($item['quantity'])
                        ->setUnitAmount($item['unit'])
                        ->setAccountCode('200')
                        ->setTaxType($taxable);
                    array_push($line_items, $line_entry);

                }

                if (!empty($adjustments)) {

                    foreach ($adjustments as $adjustment) {

                        $adjustment_type = $this->structures->adjustment_type()[$adjustment['type']];
                        if (empty($adjustment_type)) $adjustment_type = 'Adjustment';

                        $line_entry = new \XeroAPI\XeroPHP\Models\Accounting\LineItem;
                        $line_entry
                            ->setDescription($adjustment_type)
                            ->setQuantity('1')
                            ->setUnitAmount($adjustment['subtotal'])
                            ->setAccountCode('200')
                            ->setTaxType($taxable);
                        array_push($line_items, $line_entry);

                    }

                }

                $contact2 = new \XeroAPI\XeroPHP\Models\Accounting\Contact;
                $contact2->setContactId($xero_contact_id);

                $arr_invoices = [];

                $invoice_1 = new \XeroAPI\XeroPHP\Models\Accounting\Invoice;
                $invoice_1
                    ->setInvoiceNumber($order['id'])
                    ->setReference($order['customer_reference'])
                    ->setDate(new \DateTime($created))
                    ->setDueDateAsDate(new \DateTime($invoice_due))
                    ->setContact($contact2)
                    ->setLineItems($line_items)
                    ->setStatus(\XeroAPI\XeroPHP\Models\Accounting\Invoice::STATUS_AUTHORISED)
                    ->setType(\XeroAPI\XeroPHP\Models\Accounting\Invoice::TYPE_ACCREC);

                // Check how much tax was charged, if it was empty then it is GST excempt in Xero (i.e. EXEMPTOUTPUT)

                if ($is_gst === true) {

                    $invoice_1->setLineAmountTypes(\XeroAPI\XeroPHP\Models\Accounting\LineAmountTypes::EXCLUSIVE);

                } else {

                    $invoice_1->setLineAmountTypes(\XeroAPI\XeroPHP\Models\Accounting\LineAmountTypes::INCLUSIVE);

                }

                array_push($arr_invoices, $invoice_1);

                $invoices = new \XeroAPI\XeroPHP\Models\Accounting\Invoices;
                $invoices->setInvoices($arr_invoices);

                try {

                    $result = $apiInstance->updateOrCreateInvoices($xeroTenantId, $invoices, $summarizeErrors, 2);
                    $this->db->table('orders')->update(array('xero' => '31'), array('id' => $order['id'], 'account_id' => $order['account_id']));
                    $log = 'Order ' . $order['id'] . ' was sent to Xero by ' . $system_user['firstname'] . ' ' . $system_user['lastname'];
                    $this->events->log('system', null, 'Information', NULL, $log, false, false);
                    $this->events->log('order', $order['id'], 'Information', NULL, $log, false, false);

                } catch (\Exception $error) {

                    $this->db->table('orders')->update(array('xero' => '30'), array('id' => $order['id'], 'account_id' => $order['account_id']));
                    $log = 'Order ' . $order['id'] . ' failed to send to Xero by ' . $system_user['firstname'] . ' ' . $system_user['lastname'];
                    $this->events->log('system', null, 'Warning', NULL, $log, true, false);
                    $this->events->log('order', $order['id'], 'Information', NULL, $log, false, false);
                    $failures[] = $order['id'];
                    continue;

                }


            }

            return $failures;

        }

        /*
         * createContact: Create a new contact in the Xero API.
         * @param array $order // The order array
         * @param string $user // The user to be actioned
         * @returns bool
         */

        public function createContact(array $order, string $user) : bool {

            $token = $this->db->table('system_xero')->getWhere(array('id' => '1'), 1, 0)->getRowArray()['token'];
            $xeroTenantId = $this->db->table('system_xero')->getWhere(array('id' => '1'), 1, 0)->getRowArray()['tenant_id'];

            $config = \XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken((string)$token);
            $apiInstance = new \XeroAPI\XeroPHP\Api\AccountingApi(
                new \GuzzleHttp\Client(),
                $config
            );

            $firstname = $order['billing_firstname'];
            $lastname = $order['billing_lastname'];
            $company = $order['billing_company'];
            if (empty($company)) $company = implode(' ', array($firstname, $lastname));
            $email = $order['billing_email'];
            $phone_1 = $order['billing_phone_1_number'];
            $phone_2 = $order['billing_phone_2_number'];
            $contact_number = 'No Primary User ID';
            if (!empty($order['billing_tax_id'])) $tax_id = $order['billing_tax_id']; else $tax_id = '';
            $phones = [];
            $addresses = [];

            if (!empty($phone_1)) {
                $phone = new \XeroAPI\XeroPHP\Models\Accounting\Phone;
                $phone->setPhoneNumber($phone_1);
                if ($order['billing_phone_1_type'] == '31') {
                    $phone->setPhoneType(\XeroAPI\XeroPHP\Models\Accounting\Phone::PHONE_TYPE_MOBILE);
                } else if ($order['billing_phone_1_type'] == '40') {
                    $phone->setPhoneType(\XeroAPI\XeroPHP\Models\Accounting\Phone::PHONE_TYPE_FAX);
                } else {
                    $phone->setPhoneType(\XeroAPI\XeroPHP\Models\Accounting\Phone::PHONE_TYPE__DEFAULT);
                }
                $phone->setPhoneType(\XeroAPI\XeroPHP\Models\Accounting\Phone::PHONE_TYPE__DEFAULT);
                array_push($phones, $phone);
            }

            if (!empty($phone_2)) {
                $phone = new \XeroAPI\XeroPHP\Models\Accounting\Phone;
                $phone->setPhoneNumber($phone_2);
                if ($order['billing_phone_2_type'] == '31') {
                    $phone->setPhoneType(\XeroAPI\XeroPHP\Models\Accounting\Phone::PHONE_TYPE_MOBILE);
                } else if ($order['billing_phone_2_type'] == '40') {
                    $phone->setPhoneType(\XeroAPI\XeroPHP\Models\Accounting\Phone::PHONE_TYPE_FAX);
                } else {
                    $phone->setPhoneType(\XeroAPI\XeroPHP\Models\Accounting\Phone::PHONE_TYPE__DEFAULT);
                }
                array_push($phones, $phone);
            }

            $address = new \XeroAPI\XeroPHP\Models\Accounting\Address;
            $address->setAddressType('POBOX');
            $address->setAddressLine1($order['billing_street_1']);
            $address->setCity($order['billing_city']);
            $address->setRegion($order['billing_state']);
            $address->setPostalCode($order['billing_postcode']);
            $address->setCountry($order['billing_country']);
            $address->setAttentionTo(implode(' ', array($firstname, $lastname)));
            array_push($addresses, $address);

            $address = new \XeroAPI\XeroPHP\Models\Accounting\Address;
            $address->setAddressType('STREET');
            $address->setAddressLine1($order['shipping_street_1']);
            $address->setCity($order['shipping_city']);
            $address->setRegion($order['shipping_state']);
            $address->setPostalCode($order['shipping_postcode']);
            $address->setCountry($order['shipping_country']);
            $address->setAttentionTo(implode(' ', array($order['shipping_firstname'], $order['shipping_lastname'])));
            array_push($addresses, $address);

            $contact = new \XeroAPI\XeroPHP\Models\Accounting\Contact;
            $contact->setAccountNumber($order['account_id']);

            if (!empty($user)) {
                $primary = 'Primary: ' . implode(' ', array($user['firstname'], $user['lastname'])) . ' (' . $user['username'] . ')';
                $primary = strlen($primary) > 50 ? substr($primary, 0, 47) . '...' : $primary;
                $contact->setContactNumber($primary);
            } else {
                $contact->setContactNumber('');
            }

            $contact->setFirstName($firstname);
            $contact->setLastName($lastname);
            $contact->setName($company);
            $contact->setEmailAddress($email);
            $contact->setPhones($phones);
            $contact->setAddresses($addresses);
            if (!empty($tax_id)) $contact->setTaxNumber($tax_id);

            $contacts = new \XeroAPI\XeroPHP\Models\Accounting\Contacts;
            $arr_contacts = [];
            array_push($arr_contacts, $contact);
            $contacts->setContacts($arr_contacts);

            try {

                $result = $apiInstance->updateOrCreateContacts($xeroTenantId, $contacts, $summarizeErrors);
                return $result;

            } catch (\Exception $error) {

                if ($error->getCode() == '401') {

                    $log = 'Order ' . $order['id'] . ' tried to create a contact but the session was unauthorized, so redirect to Xero for authentication.';
                    $this->events->log('system', null, 'Information', NULL, $log, true, false);
                    return 401;

                } else {

                    $log = 'Order ' . $order['id'] . ' was sent to Xero but there was an error (' . $error->getCode() . ') ' . $error->getMessage();
                    $this->events->log('system', null, 'Critical', NULL, $log, true, true);

                }

            }

            return false;

        }

        /*
         * refreshToken: Refresh the Xero API token.
         * @returns void
         */

        public function refreshToken() : void {

            $provider = new \League\OAuth2\Client\Provider\GenericProvider([
                'clientId'                => $this->clientId,
                'clientSecret'            => $this->clientSecret,
                'redirectUri'             => '',
                'urlAuthorize'            => 'https://login.xero.com/identity/connect/authorize',
                'urlAccessToken'          => 'https://identity.xero.com/connect/token',
                'urlResourceOwnerDetails' => 'https://api.xero.com/api.xro/2.0/Organisation'
            ]);

            $refresh_token = $this->db->table('system_xero')->getWhere(array('id' => '1'), 1, 0)->getRowArray()['refresh_token'];

            $newAccessToken = $provider->getAccessToken('refresh_token', ['grant_type'=>'refresh_token','refresh_token' => $refresh_token]);

            $update = array(

                'token' => $newAccessToken->getToken(),
                'expires' => $newAccessToken->getExpires(),
                'refresh_token' => $newAccessToken->getRefreshToken(),
                'id_token' => $newAccessToken->getValues()["id_token"]

            );

            $this->db->table('system_xero')->update($update, array('id' => '1'));

        }

    }