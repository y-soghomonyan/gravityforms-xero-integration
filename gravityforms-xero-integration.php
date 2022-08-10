<?php
/*
Plugin Name: Gravity Form Xero Integration
Version: 1.0
Description: Extends the Gravity Forms plugin - adding Xero integration.
Author: Yervand Soghomonyan
Text Domain: gravityforms-xero-integration
*/
require_once __DIR__ . '/vendor/autoload.php';

if ( ! function_exists('write_log')) {
   function write_log ( $log )  {
      if ( is_array( $log ) || is_object( $log ) ) {
         error_log( print_r( $log, true ) );
      } else {
         error_log( $log );
      }
   }
}

require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/gravityforms-xero-settings.php';
require_once __DIR__ . '/gravityforms-submission.php';

use XeroAPI\XeroPHP\AccountingObjectSerializer;

add_action('plugins_loaded', array('GravityFormXeroIntegration', 'init'));

class GravityFormXeroIntegration{
    public $_client_id = "";

    public $_client_secret = "";

    public $_redirect_uri = "https://stmcorporate.group/order/?xero-auth=1";

    public $_xero_settings = array();

    public $_xero_invoice_account_code = 4000;

    public $_navigation_added = false;

    public static function init()
    {
        $class = __CLASS__;
        $class_instance = new $class;
        $class_instance->register_actions();
    }

    public function __construct()
    {
        if(!$this->_navigation_added) {

            $this->_navigation_added = true;
        }
        $this->_client_id = $this->get_setting('client_id');
        $this->_client_secret = $this->get_setting('client_secret');
    }

    public function register_actions()
    {
        add_action('admin_menu', function () {
            add_menu_page(
                __('GravityForms Xero Integration', 'textdomain'),
                'Xero Integration',
                'manage_options',
                'gravityforms-xero',
                array($this, 'xero_admin_page')
            );
        });
        
        add_action('init', array($this, 'save_xero_tokens'));
        add_action('init', array($this, 'update_token_if_expired'));
        add_action('admin_post_save_xero_settings', array($this, 'save_xero_settings'));
    }
    
    public function get_generic_provider()
    {
        return new \League\OAuth2\Client\Provider\GenericProvider([
            'clientId'                => $this->_client_id,
            'clientSecret'            => $this->_client_secret,
            'redirectUri'             => $this->_redirect_uri,
            'urlAuthorize'            => 'https://login.xero.com/identity/connect/authorize',
            'urlAccessToken'          => 'https://identity.xero.com/connect/token',
            'urlResourceOwnerDetails' => 'https://api.xero.com/api.xro/2.0/Organisation'
        ]);
    }

    public function save_xero_tokens()
    {

        if(!isset($_GET['xero-auth'])) return;
        if($_SERVER['HTTP_REFERER'] !== 'https://authorize.xero.com/') return;

        $storage = new StorageClass();        
        $provider = new \League\OAuth2\Client\Provider\GenericProvider([
            'clientId'                => $this->_client_id,
            'clientSecret'            => $this->_client_secret,
            'redirectUri'             => $this->_redirect_uri,
            'urlAuthorize'            => 'https://login.xero.com/identity/connect/authorize',
            'urlAccessToken'          => 'https://identity.xero.com/connect/token',
            'urlResourceOwnerDetails' => 'https://api.xero.com/api.xro/2.0/Organisation'
        ]);
        if (!isset($_GET['code'])) {

            $options = $this->get_xero_scopes();

            // Fetch the authorization URL from the provider; this returns the
            // urlAuthorize option and generates and applies any necessary parameters (e.g. state).
            $authorizationUrl = $provider->getAuthorizationUrl($options);

            // Get the state generated for you and store it to the session.
            $_SESSION['oauth2state'] = $provider->getState();

            // Redirect the user to the authorization URL.
            header('Location: ' . $authorizationUrl);
            exit();

            // Check given state against previously stored one to mitigate CSRF attack
        }  else {

            try {
                // Try to get an access token using the authorization code grant.
                $accessToken = $provider->getAccessToken('authorization_code', [
                    'code' => $_GET['code']
                ]);

                $config = XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken( (string)$accessToken->getToken() );
                $identityApi = new XeroAPI\XeroPHP\Api\IdentityApi(
                    new GuzzleHttp\Client(),
                    $config
                );

                $result = $identityApi->getConnections();

                // Save my tokens, expiration tenant_id
                $storage->setToken(
                    $accessToken->getToken(),
                    $accessToken->getExpires(),
                    $result[0]->getTenantId(),
                    $accessToken->getRefreshToken(),
                    $accessToken->getValues()["id_token"]
                );
                wp_redirect(admin_url('admin.php?page=gravityforms-xero'));
                exit();

            } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
                echo "Callback failed";
                exit();
            }
        }

    }

    public function update_token_if_expired()
    {
        
        $storage = new StorageClass();
        $xeroTenantId = (string)$storage->getSession()['tenant_id'];
        if ($storage->getHasExpired()) {
            $provider = $this->get_generic_provider();

            $newAccessToken = $provider->getAccessToken('refresh_token', [
                'refresh_token' => $storage->getRefreshToken()
            ]);
            // Save my token, expiration and refresh token
            $storage->setToken(
                $newAccessToken->getToken(),
                $newAccessToken->getExpires(),
                $xeroTenantId,
                $newAccessToken->getRefreshToken(),
                $newAccessToken->getValues()["id_token"]
            );
        }
    }

    public function _xero_create_invoice($contact, $xero_description, $quantity, $price, $currency, $ref = 'Ref-1234123')
    {
        $storage = new StorageClass();
        $this->update_token_if_expired();
        $xeroTenantId = (string)$storage->getSession()['tenant_id'];

        

        $config = XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken( (string)$storage->getSession()['token'] );
        $apiInstance = new XeroAPI\XeroPHP\Api\AccountingApi(
            new GuzzleHttp\Client(),
            $config
        );

        $lineitem = new XeroAPI\XeroPHP\Models\Accounting\LineItem;
        $lineitem->setDescription($xero_description)
            ->setQuantity($quantity)
            ->setUnitAmount($price)
            ->setAccountCode($this->_xero_invoice_account_code);

        $arr_lineitem = [];
        array_push($arr_lineitem, $lineitem);
        //        $contact = new XeroAPI\XeroPHP\Models\Accounting\Contact;
        //        $contact->setContactId($new_contact->getContactId());
        $arr_invoices = [];

        $invoice = new XeroAPI\XeroPHP\Models\Accounting\Invoice;
        $invoice->setReference($ref)
            ->setDueDate(new DateTime())
            ->setContact($contact)
            ->setCurrencyCode($currency)
            ->setLineItems($arr_lineitem)
            ->setStatus(XeroAPI\XeroPHP\Models\Accounting\Invoice::STATUS_AUTHORISED)
            ->setSentToContact(false)
            ->setType(XeroAPI\XeroPHP\Models\Accounting\Invoice::TYPE_ACCREC)
            ->setLineAmountTypes(\XeroAPI\XeroPHP\Models\Accounting\LineAmountTypes::EXCLUSIVE);
        array_push($arr_invoices, $invoice);

        $invoices = new XeroAPI\XeroPHP\Models\Accounting\Invoices;
        $invoices->setInvoices($arr_invoices);


        //[/Invoices:Create]
        try {
            $apiResponse = $apiInstance->createInvoices($xeroTenantId,$invoices);
            $invoice = $apiResponse->getInvoices()[0];
//            $this->dump($apiResponse);

            // Do the main payment
            $payment = new XeroAPI\XeroPHP\Models\Accounting\Payment;
            $account = new XeroAPI\XeroPHP\Models\Accounting\Account;
            $account->setAccountId('c8ca2baa-5092-4012-987d-d9eca1ce69dc');
            $payment->setInvoice($invoice)
                ->setCode($this->_xero_invoice_account_code)
                ->setReference($invoice->getReference())
                ->setAccount($account)
                ->setAmount($invoice->getAmountDue());

            try
            {
                $result = $apiInstance->createPayment($xeroTenantId, $payment);
//                $this->dump($result);die;
                trigger_error("createPayments: result = ".print_r($result, true));
            }
            catch (Exception $e)
            {
//                $this->dump($e);die;
                trigger_error('Exception when calling AccountingApi->createPayments: '.$e->getMessage());
                $res = $e->getMessage();
                write_log( __LINE__ );
                write_log( 'Exception when calling AccountingApi->createPayments: '.$e->getMessage() );
            }
        } catch (Exception $e) {
            $message = 'Exception when calling AccountingApi->createInvoices: ' . $e->getMessage() . PHP_EOL;
            write_log( __LINE__ );
            write_log( $message );
        }
    }

    public function _xero_contact_address_array($addressLine1 = '', $addressLine2 = '', $city = '', $postalCode = '', $region = '', $country = '')
    {
        $address = (object) array(
            "AddressType" => "POBOX",
            "AddressLine1" => $addressLine1,
            "AddressLine2" => $addressLine2,
            "City" => $city,
            "PostalCode" => $postalCode,
            "Region" => $region,
            "Country" => $country,
        );
        return array($address);
    }

    public function _xero_contact_phones_array($phoneNumber, $phoneAreaCode = '', $phoneCountryCode = '')
    {
        $phone = (object) array(
            "PhoneType" => "DEFAULT",
            "PhoneNumber" => $phoneNumber,
            "PhoneAreaCode" => $phoneAreaCode,
            "PhoneCountryCode" => $phoneCountryCode
        );
        return array($phone);
    }

    public function _xero_create_contact( $firstName, $lastName, $email, $contactName, $addresses = array(), $phones = array() )
    {
        
        $this->update_token_if_expired();
        $storage = new StorageClass();
        $xeroTenantId = (string)$storage->getSession()['tenant_id'];

        

        $config = XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken( (string)$storage->getSession()['token'] );

        $accountingApi = new XeroAPI\XeroPHP\Api\AccountingApi(
            new GuzzleHttp\Client(),
            $config
        );

        // Create Contact
        try {
            $person = new XeroAPI\XeroPHP\Models\Accounting\ContactPerson;
            $person->setFirstName($firstName)
                ->setLastName($lastName)
                ->setEmailAddress($email)
                ->setIncludeInEmails(true);

            $arr_persons = [];
            array_push($arr_persons, $person);





            $contact = new XeroAPI\XeroPHP\Models\Accounting\Contact;
            $contact->setName($contactName)
                ->setFirstName($firstName)
                ->setLastName($lastName)
                ->setEmailAddress($email)
                ->setAddresses($addresses)
                ->setPhones($phones)
                ->setContactPersons($arr_persons);

            $arr_contacts = [];
            array_push($arr_contacts, $contact);
            $contacts = new XeroAPI\XeroPHP\Models\Accounting\Contacts;
            $contacts->setContacts($arr_contacts);

            $apiResponse = $accountingApi->updateOrCreateContacts($xeroTenantId,$contacts);

            $contact = $apiResponse->getContacts()[0];
            return array('success' => true, 'contact' => $contact);
        } catch (\XeroAPI\XeroPHP\ApiException $e) {
            $error = AccountingObjectSerializer::deserialize(
                $e->getResponseBody(),
                '\XeroAPI\XeroPHP\Models\Accounting\Error',
                []
            );
            $message = "/home/texasrea/domains/stmcorporate.group/order/wp-content/plugins/gravityforms-xero-integration/gravityforms-xero-integration.php " . __LINE__ . " ApiException - " . $error->getElements()[0]["validation_errors"][0]["message"];
            write_log( $message );
        }
        return array('success' => false, 'message' => $message );
    }

    public function get_xero_scopes()
    {
        return [
            'scope' => ['openid email profile offline_access accounting.settings accounting.transactions accounting.contacts accounting.journals.read accounting.reports.read accounting.attachments']
        ];
    }

    public function xero_admin_page()
    {
        // check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        session_start();
        // $this->save_xero_tokens();
        $provider = $this->get_generic_provider();
        $options = $this->get_xero_scopes();
        if(isset($_GET['create_contact'])) {
            $storage = new StorageClass();
            $session = $storage->getSession();
            // $storage->setToken(
            //         "eyJhbGciOiJSUzI1NiIsImtpZCI6IjFDQUY4RTY2NzcyRDZEQzAyOEQ2NzI2RkQwMjYxNTgxNTcwRUZDMTkiLCJ0eXAiOiJKV1QiLCJ4NXQiOiJISy1PWm5jdGJjQW8xbkp2MENZVmdWY09fQmsifQ.eyJuYmYiOjE2NTI0MzI1ODAsImV4cCI6MTY1MjQzNDM4MCwiaXNzIjoiaHR0cHM6Ly9pZGVudGl0eS54ZXJvLmNvbSIsImF1ZCI6Imh0dHBzOi8vaWRlbnRpdHkueGVyby5jb20vcmVzb3VyY2VzIiwiY2xpZW50X2lkIjoiQzkzRjQyOTlCQTkzNEE3QTkxRDRGNzA0MjBCMEI0MkIiLCJzdWIiOiI3MmI2Yjk2ZjRiNGY1ZmRiOTA1MGVjZGI2NzlkZmNiNiIsImF1dGhfdGltZSI6MTY1MjQzMTM0NCwieGVyb191c2VyaWQiOiJkODZhMzhkOS0xMWQ2LTRjOGItYmQyYi05ZWI1MjVkNDUxZGQiLCJnbG9iYWxfc2Vzc2lvbl9pZCI6ImRiZTdkYjMxYWRlMzQxNjc5YjQzOGNhMjlkYjhhNTQ0IiwianRpIjoiMGVmMWYzMzRiMWQ2NWI0MzQ5N2VlMDNjMWQyY2I1NWYiLCJhdXRoZW50aWNhdGlvbl9ldmVudF9pZCI6IjBhYmZhNGRmLTJkOWQtNDBlMy1iZmRlLTZhN2I3MTJlMjhmZSIsInNjb3BlIjpbImVtYWlsIiwicHJvZmlsZSIsIm9wZW5pZCIsImFjY291bnRpbmcucmVwb3J0cy5yZWFkIiwiYWNjb3VudGluZy5zZXR0aW5ncyIsImFjY291bnRpbmcuYXR0YWNobWVudHMiLCJhY2NvdW50aW5nLnRyYW5zYWN0aW9ucyIsImFjY291bnRpbmcuam91cm5hbHMucmVhZCIsImFjY291bnRpbmcuY29udGFjdHMiLCJvZmZsaW5lX2FjY2VzcyJdLCJhbXIiOlsicHdkIiwibWZhIiwic3drIl19.B8P4Y6W3-9TVreTeszUqElb3QHvE89rHIpFXkdXZlO_eQ1VKrWt3Ucbf9W34OlAhgOJ5ydHvhKDEgFTIR-a9V5dGU-vHAxO5Fb6q_5vemgTr8GFNJVj1-89KIcdR45Q9R061_P6NhDs3HQm0E42kIC1wzKyLPJZOGUb79PzH1U8XeqWQmHMwablPkBRCTX4lZKYpWr_KsCeXkXo71fzfxab8zoIh8_9Ywt1sAmtxj3X_Xkb5mVzeielZSM7VnA4CTStve9zzYbwM-2TRNFU1pHBdRbO6iZMIQU9L33zoeBed3itTtg8yMaQM8FVMRGRm2JuldBDAIv0Iiorj6dEffg",
            //         time() - 100,
            //         "cafaa7ae-d24c-4adb-b63a-2219595161c4",
            //         "c9aa67c06b521d14e2b4abd902e066d2127323f5e276ad2ee4ff4b509672c283",
            //         "eyJhbGciOiJSUzI1NiIsImtpZCI6IjFDQUY4RTY2NzcyRDZEQzAyOEQ2NzI2RkQwMjYxNTgxNTcwRUZDMTkiLCJ0eXAiOiJKV1QiLCJ4NXQiOiJISy1PWm5jdGJjQW8xbkp2MENZVmdWY09fQmsifQ.eyJuYmYiOjE2NTI0MzI1ODEsImV4cCI6MTY1MjQzMjg4MSwiaXNzIjoiaHR0cHM6Ly9pZGVudGl0eS54ZXJvLmNvbSIsImF1ZCI6IkM5M0Y0Mjk5QkE5MzRBN0E5MUQ0RjcwNDIwQjBCNDJCIiwiaWF0IjoxNjUyNDMyNTgxLCJhdF9oYXNoIjoiRS1XYTN6WnA4SU9NOEplUFluZkVVZyIsInNpZCI6ImRiZTdkYjMxYWRlMzQxNjc5YjQzOGNhMjlkYjhhNTQ0Iiwic3ViIjoiNzJiNmI5NmY0YjRmNWZkYjkwNTBlY2RiNjc5ZGZjYjYiLCJhdXRoX3RpbWUiOjE2NTI0MzEzNDQsInhlcm9fdXNlcmlkIjoiZDg2YTM4ZDktMTFkNi00YzhiLWJkMmItOWViNTI1ZDQ1MWRkIiwiZ2xvYmFsX3Nlc3Npb25faWQiOiJkYmU3ZGIzMWFkZTM0MTY3OWI0MzhjYTI5ZGI4YTU0NCIsInByZWZlcnJlZF91c2VybmFtZSI6InllcnZhbmQub2Rlc2tAZ21haWwuY29tIiwiZW1haWwiOiJ5ZXJ2YW5kLm9kZXNrQGdtYWlsLmNvbSIsImdpdmVuX25hbWUiOiJZZXJ2YW5kIiwiZmFtaWx5X25hbWUiOiJTb2dob21vbnlhbiIsIm5hbWUiOiJZZXJ2YW5kIFNvZ2hvbW9ueWFuIiwiYW1yIjpbInB3ZCIsIm1mYSIsInN3ayJdfQ.jKll_1XeYYr1uSybA3t4f2WGqzvR0Op-Rl7ll3q3Wt5GdNW-Vg4H4jKfiwQbx9ZcvqhHii3q-Rf438Dv4FAc4RCd2sKjdRYLmI5iLvo2OQoGSr5eqhanybjNlhA7cKfAwbmaaPiKdfekvClrPGI0Y41_BCHodxH7WQERUQZzjcBrtYkJBlT1dnNDkNyqnVMbn8o1DVTRwUKb5fk7ueIynHsxEt6LLl4DY3fA1a-1Ad1MjQD-vkWoIBCFlDrSLbOHKEJZJXiyOua2HyDoekRWNu_A_EvptLoO1jiIpgVh-WibDdt9mJjXyAqVTiUX29MOC2tGrZMH5vPFG7s336lskQ"
            //     );
            
            $this->dump(time() - $session['expires']);die;
            $this->_xero_create_contact("Yervandd111d", "Soghomonyannn", "yervanddd.od111esk@gmail.com", "SourceCo11deee@");
        }
        if(isset($_GET['create_invoice'])){
            $contact_response = $this->_xero_create_contact("Yervand", "Soghomonyan", "yervand.odesk@gmail.com", "SourceCode");
            $contact = $contact_response['contact'] ?? false;
            if($contact) {
                $this->_xero_create_invoice($contact);
            }else{
//                $this->dump($contact_request);
            }
        }
        // Fetch the authorization URL from the provider; this returns the
        // urlAuthorize option and generates and applies any necessary parameters (e.g. state).
        $authorizationUrl = $provider->getAuthorizationUrl($options);

        // Get the state generated for you and store it to the session.
        $_SESSION['oauth2state'] = $provider->getState();
        ?>
        <div class="wrap">
               <!-- Print the page title -->
               <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <div class="wrap gforms_settings_wrap">
                <form action="<?=admin_url('admin-post.php')?>" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save_xero_settings">
                    <table class="form-table">
                        <tbody>
                        <tr>
                            <th><label for="client_id"><?=__('Client ID')?></label></th>
                            <td><input type="password" name="client_id" id="client_id" value="<?=$this->get_setting('client_id')?>"></td>
                        </tr>
                        <tr>
                            <th><label for="client_secret"><?=__('Client Secret')?></label></th>
                            <td><input type="password" name="client_secret" id="client_secret" value="<?=$this->get_setting('client_secret')?>"></td>
                        </tr>
                        <tr>
                            <th rowspan="2" colspan="2">
                                <a class="button" href="<?= $authorizationUrl?>">Authorize In Xero</a>
                                <button type="submit" class="button button-primary"><?= __("Save Settings")?></button>
                            </th>
                        </tr>

                        </tbody>
                    </table>
                </form>
            </div>
        </div>
        <?php
    }

    public function get_setting($key)
    {
        $settings = $this->_xero_settings;
        if(!$settings) {
            $settings = get_option('xero_integration_settings');
        }
        return $settings[$key] ?? '';
    }

    public function save_xero_settings()
    {
        $client_id = $_POST['client_id'] ?? '';
        $client_secret = $_POST['client_secret'] ?? '';
        $refresh_token = $this->get_setting('refresh_token');
        $access_token = $this->get_setting('access_token');
        $tenant_id = $this->get_setting('tenant_id');
        $xero_settings = array(
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'refresh_token' => $refresh_token,
            'access_token' => $access_token,
            'tenant_id' => $tenant_id,
        );
        update_option('xero_integration_settings', $xero_settings);
        wp_redirect($_SERVER['HTTP_REFERER']);
        exit;
    }

    public function dump(...$vars)
    {
        if(is_user_logged_in()) {
            echo "<pre>";
            var_dump(...$vars);
            echo "</pre>";
        }
    }
}
