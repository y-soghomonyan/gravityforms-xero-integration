<?php

add_action('plugins_loaded', array('GravityFormSubmission', 'init'));

class GravityFormSubmission extends GravityFormXeroSettings {

    public static function init()
    {
        $class = __CLASS__;
        new $class;
    }

    public function __construct()
    {
        parent::__construct();
        add_action( 'gform_after_submission', array($this, 'init_xero_api'), 10, 2 );
        new StorageClass();
    }

    public function init_xero_api( $entry, $form )
    {
        // if(!($form['id'] == 24)) return $entry;
        if(rgar($form, 'xero_enabled') !== 'yes') return $entry;
//        if( !in_array(get_current_user_id(), $this->_stripe_test_mode_users) ) { // Todo uncomment this line
            if ($entry['payment_status'] === 'Paid') {
                $fields = $form['fields'] ?? array();
                $firstName = $entry[$this->global_field_exists($fields, 'field-vorname')] ?? '';
                $lastName = $entry[$this->global_field_exists($fields, 'field-nachname')] ?? '';
                $email = $entry[$this->global_field_exists($fields, 'field-email')] ?? ''; // Todo add class for email
                $firma = $entry[$this->global_field_exists($fields, 'field-firma')] ?? '';
                // start user addresses
                $address1 = $entry[$this->global_field_exists($fields, 'field-address1')] ?? ''; // Todo add class for this field
                $address2 = $entry[$this->global_field_exists($fields, 'field-address2')] ?? ''; // Todo add class for this field
                $city = $entry[$this->global_field_exists($fields, 'field-city')] ?? ''; // Todo add class for this field
                $postalCode = $entry[$this->global_field_exists($fields, 'field-zip')] ?? ''; // Todo add class for this field
                $region = $entry[$this->global_field_exists($fields, 'field-region')] ?? ''; // Todo add class for this field
                $country = $entry[$this->global_field_exists($fields, $this->_xero_country_name_field_class)] ?? ''; // Todo add class for this field
                // start phone
                $phone = $entry[$this->global_field_exists($fields, 'field-phone')] ?? ''; // Todo add class for this field

                $addresses = $this->_xero_contact_address_array($address1, $address2, $city, $postalCode, $region, $country);
                $phones = $this->_xero_contact_phones_array($phone, '', '');
                $contact_name = !empty($firma) ? $firma : "$firstName $lastName";
                $contact_object = $this->_xero_create_contact( $firstName, $lastName, $email, $contact_name, $addresses, $phones );
                $contact_object = $contact_object['contact'] ?? false;
                if($contact_object) {
                    $currency = $entry['currency'] ?? "GBP";
                    $transaction_id = $entry['transaction_id'] ?? "";
                    $xero_description = rgar($form, 'xero_product_description');
                    empty(trim($xero_description)) && $xero_description = 'Description is not specified.';
                    $price = $entry[$this->global_field_exists($fields, 'ys-global-price') . '.2'] ?? '0';
                    $price = preg_replace( '/[^0-9,"."]/', '', $price );
                    $quantity = $entry[$this->global_field_exists($fields, 'ys-global-quantity')] ?? 1;
                    $this->_xero_create_invoice($contact_object, $xero_description, $quantity, $price, $currency, $transaction_id);
                }
                // $this->dump($entry);die;
            }
//        }
        return $entry;
    }


}