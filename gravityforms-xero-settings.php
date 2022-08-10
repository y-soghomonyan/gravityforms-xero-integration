<?php

add_action('plugins_loaded', array('GravityFormXeroSettings', 'init'));

class GravityFormXeroSettings extends GravityFormXeroIntegration {

    public $_xero_description_class = 'ys-global-xero-description';

    public $_currency_field_class = 'ys-xero-currency';

    public $_xero_contact_field_class = 'ys-xero-contact';

    public $_xero_account_type_field_class = 'ys-xero-account-type';

    public $_xero_country_name_field_class = 'ys-xero-country-name';

    public $_gravity_form = array();

    public static function init()
    {
        $class = __CLASS__;
        new $class;
    }

    public function __construct()
    {
        parent::__construct();
        $this->register_actions();
    }

    public function register_actions()
    {
        // register actions
        if (self::is_gravityforms_installed()) :
            add_action( 'wp_head', array($this, 'add_styles'), 10, 2 );
            add_action( 'wp_footer', array($this, 'add_scripts'), 10, 2 );

            add_filter( 'gform_form_post_get_meta', array($this, 'create_custom_gravity_fields') );

            add_filter( 'gform_form_settings', array($this, 'add_form_settings'), 20, 2 );
            add_filter( 'gform_pre_form_settings_save', array($this, 'save_form_settings'), 20, 2 );
        endif;

    }

    public function save_form_settings($form)
    {
        $form['xero_product_description'] = rgpost( 'xero_product_description' );
        $form['xero_enabled'] = rgpost( 'xero_enabled' );
        return $form;
    }

    public function add_form_settings( $settings, $form )
    {
        ob_start();
        ?>
        <tr>
            <th><label for="xero_enabled"><?= __("Enable Xero Integration", 'gform') ?></label></th>
            <td><input type="checkbox" name="xero_enabled" id="xero_enabled" value="yes" <?= rgar($form, 'xero_enabled') === 'yes' ? 'checked' : ''; ?> /></td>
        </tr>
        <tr>
            <th><label for="xero_product_description"><?= __("Xero Product Description", 'gform') ?></label></th>
            <td><textarea name="xero_product_description" id="xero_product_description"><?= rgar($form, 'xero_product_description'); ?></textarea></td>
        </tr>
        <?php
        $settings[ 'Form Custom Settings' ]['xero_integration_settings'] = ob_get_clean();

        return $settings;
    }

    public function global_field_exists($fields, $class)
    {
        foreach ($fields as $field){
            $classes = explode(' ', $field['cssClass']);
            if(in_array($class, $classes)) return $field['id'];
        }
        return false;
    }

    public function create_custom_gravity_fields($form)
    {
        if(rgar($form, 'xero_enabled') !== 'yes') return $form;
        $this->_gravity_form = $form;
        $_xero_description_field_id = $this->global_field_exists($form['fields'], $this->_xero_description_class);
        $xero_description = rgar($form, 'xero_product_description');
//        empty(trim($xero_description)) && $xero_description = 'Description is not specified.';
//        if (!$_xero_description_field_id) {
//            $new_field_id = $this->get_new_field_id($form['fields']);
//            $props = array(
//                'id' => $new_field_id,
//                'type' => 'text',
//                'inputType' => 'text',
//                'label' => 'Xero Product Description',
//                'adminLabel' => 'Xero Product Description',
//                'defaultValue' => $xero_description,
//                'cssClass' => $this->_xero_description_class
//            );
//
//            // Create new gravity forms field and add it to the form object
//            $nf = GF_Fields::create($props);
//            array_splice($form['fields'], 0, 0, array($nf));
//
//        } else {
//            $updated_fields = $this->update_field_value( $form['fields'], $_xero_description_field_id, $xero_description );
//            $form['fields'] = $updated_fields;
//        }

//        $_currency_field_id = $this->global_field_exists($form['fields'], $this->_currency_field_class);
//        $currency = apply_filters('gform_currency', get_option("rg_gforms_currency"));
//        if (!$_currency_field_id) {
//            $new_field_id = $this->get_new_field_id($form['fields']);
//            $props = array(
//                'id' => $new_field_id,
//                'type' => 'text',
//                'inputType' => 'text',
//                'label' => 'Currency of payment',
//                'adminLabel' => 'Currency of payment',
//                'defaultValue' => $currency,
//                'cssClass' => $this->_currency_field_class
//            );
//
//            // Create new gravity forms field and add it to the form object
//            $nf = GF_Fields::create($props);
//            array_splice($form['fields'], 3, 0, array($nf));
//
//        } else {
//            $updated_fields = $this->update_field_value( $form['fields'], $_currency_field_id, $currency );
//            $form['fields'] = $updated_fields;
//        }

        $_xero_account_type_field_id = $this->global_field_exists($form['fields'], $this->_xero_account_type_field_class);
        if (!$_xero_account_type_field_id) {
            $new_field_id = $this->get_new_field_id($form['fields']);
            $props = array(
                'id' => $new_field_id,
                'type' => 'hidden',
                'inputType' => 'hidden',
                'label' => 'Xero Account Type',
                'adminLabel' => 'Xero Account Type',
                'defaultValue' => '4000',
                'value' => '4000',
                'cssClass' => $this->_xero_account_type_field_class,

            );

            // Create new gravity forms field and add it to the form object
            $nf = GF_Fields::create($props);
            array_splice($form['fields'], 3, 0, array($nf));

        }

        $_xero_country_name_field_id = $this->global_field_exists($form['fields'], $this->_xero_country_name_field_class);
        if (!$_xero_country_name_field_id) {
            $new_field_id = $this->get_new_field_id($form['fields']);
            $props = array(
                'id' => $new_field_id,
                'type' => 'hidden',
                'inputType' => 'hidden',
                'label' => 'Country Name',
                'adminLabel' => 'Country Name',
                'defaultValue' => 'Deutschland',
                'value' => 'Deutschland',
                'cssClass' => $this->_xero_country_name_field_class,

            );

            // Create new gravity forms field and add it to the form object
            $nf = GF_Fields::create($props);
            array_splice($form['fields'], 3, 0, array($nf));

        }

        $_xero_contact_field_id = $this->global_field_exists($form['fields'], $this->_xero_contact_field_class);
        if (!$_xero_contact_field_id) {
            $new_field_id = $this->get_new_field_id($form['fields']);
            $props = array(
                'id' => $new_field_id,
                'type' => 'hidden',
                'inputType' => 'hidden',
                'label' => 'Contact Name for Xero',
                'adminLabel' => 'Contact Name for Xero',
                'defaultValue' => 'NOT SPECIFIED',
                'value' => 'NOT SPECIFIED',
                'cssClass' => $this->_xero_contact_field_class
            );

            // Create new gravity forms field and add it to the form object
            $nf = GF_Fields::create($props);
            array_splice($form['fields'], 3, 0, array($nf));

        }
//        $this->dump($form['fields']);
        return $form;
    }

    public function update_field_value($fields, $field_id, $value )
    {
        foreach ($fields as $key=>$field){
            if($field['id'] === $field_id){
                $field['defaultValue'] = $value;
                $fields[$key] = $field;
            }
        }
        return $fields;
    }

    public function get_new_field_id($fields)
    {
        $count = 0;
        foreach( $fields as $field ) {
            if( $field->id > $count ) {
                $count = $field->id;
            }
        }
        $count++;
        return $count;
    }

    public function add_styles()
    {
        ?>
        <style>
            .<?= $this->_xero_description_class?>, .<?= $this->_currency_field_class?> {
                position: absolute;
                left: -100vw;
            }
            .gform_legacy_markup_wrapper li.gfield.field_description_below.gform_hidden+li.gsection {
                margin-top: 16px !important;
            }
        </style>
        <?php
    }

    public function add_scripts()
    {
        $this->get_land_dropdown_id();
        ?>
        <script defer>
            jQuery(function ($) {
                let firma_field = $('.field-firma input'),
                    xero_contact = $('.ys-xero-contact input'),
                    vorname_field = $('.field-vorname input'),
                    nachname_field = $('.field-nachname input');
                if(xero_contact.length && (vorname_field.length || firma_field.length || nachname_field.length)){
                    xero_contact.parents('form').on('submit', function(e){
                        let xero_contact_name = firma_field.val() !== '' ? firma_field.val() : `${vorname_field.val()} ${nachname_field.val()}`;
                        if(xero_contact_name.length) {
                            xero_contact.val(xero_contact_name);
                        }
                    });
                }
                <?php if(!empty($this->_gravity_form)){ ?>
                    let land_field_id = '<?= $this->get_land_dropdown_id()?>',
                        form_id = '<?= $this->_gravity_form['id'] ?? 0?>',
                        land_dropdown = $(`#input_${form_id}_${land_field_id}`);
                    if(land_dropdown.length) {
                        land_dropdown.on('change', function () {
                            let country_nicename  = $(this).find('option:selected').text();
                            $('.<?= $this->_xero_country_name_field_class?> input').val(country_nicename);
                        });
                    }
                <?php } ?>
            });
        </script>
        <?php
    }

    public function get_land_dropdown_id()
    {
        $fields = $this->_gravity_form['fields'] ?? array();
        foreach ($fields as $key=>$field){
            if(trim($field['label']) === 'Land' && $field['type'] === 'select' && $field['isRequired'] == true){
                return $field['id'];
            }
        }
        return false;
    }

    public function dump(...$vars)
    {
        if(is_user_logged_in()){
            echo "<pre>";
            var_dump(...$vars);
            echo "</pre>";
        }
    }

    private static function is_gravityforms_installed()
    {
        if ( !function_exists( 'is_plugin_active' ) || !function_exists( 'is_plugin_active_for_network' ) ) :

            require_once(ABSPATH . '/wp-admin/includes/plugin.php');

        endif;

        if (is_multisite()) :

            return (
                is_plugin_active_for_network( 'gravityforms/gravityforms.php' ) ||
                is_plugin_active( 'gravityforms/gravityforms.php' )
            );

        else :

            return is_plugin_active( 'gravityforms/gravityforms.php' );

        endif;

    }
}
