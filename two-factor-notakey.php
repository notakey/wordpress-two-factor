<?php

/**
 * Two Factor
 *
 * @package     Two_Factor_Notakey
 * @author      Notakey Latvia
 * @copyright   2022 Notakey Latvia
 * @license     GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: Notakey Two Factor extension
 * Plugin URI: https://github.com/notakey/wordpress-two-factor/
 * Description: Two-Factor Authentication with Notakey Authenticator mobile application using push notifications.
 * Version: 1.0.0
 * Author: Notakey Latvia
 * License: GPLv2
 * Author URI: https://github.com/notakey/wordpress-two-factor/graphs/contributors
 * Network: True
 * Text Domain: two-factor-notakey
 */

Ntk_Two_Factor_Core::register_hooks();

class Ntk_Two_Factor_Core
{
    public static $plugin_name = 'two-factor-notakey';
    private static $settings = [];
    private static $options = [];

    public static function get_config($key, $default = '')
    {
        $opts = self::get_options();

        $opt = null;

        if (isset($opts[$key])) {
            $opt = $opts[$key];
        } else {
            $opt = $default;
        }

        if (is_bool($default)) {
            $value = boolval($opt);
        } else if (is_string($default)) {
            $value = strval($opt);
        } else if (is_integer($default)) {
            $value = intval($opt);
        } else if (is_array($default)) {
            $value = $opt;
            if (!is_array($value)) {
                $value = $default;
            }
        } else {
            throw new Exception('Invalid setting type for key ' . $key);
        }

        return $value;
    }

    public static function two_factor_providers_filter($providers)
    {

        $providers['Two_Factor_Notakey'] = plugin_dir_path(__FILE__) . 'class-two-factor-notakey.php';

        if (self::get_config('provider_override_active', false)) {
            $enabled_providers = self::get_config('provider_override_list', []);

            foreach (array_keys($providers) as $p) {
                if (!in_array($p, $enabled_providers, true)) {
                    unset($providers[$p]);
                }
            }
        }

        return $providers;
    }

    public static function two_factor_enabled_providers_for_user_filter($enabled_providers, $user_id)
    {
        if (self::get_config('enable_notakey_for_all', false)) {
            if (!in_array('Two_Factor_Notakey', $enabled_providers)) {
                $enabled_providers[] = 'Two_Factor_Notakey';
            }
        }

        if (self::get_config('reject_login_without_mfa', false)) {
            if (count($enabled_providers) == 0) {
                throw new Exception("Login without 2FA not allowed.");
            }
        }

        return $enabled_providers;
    }

    public static function load_textdomain()
    {
        load_plugin_textdomain(self::td());
    }

    private static function get_ntk_instance()
    {
        require_once(plugin_dir_path(__FILE__) . 'class-two-factor-notakey.php');
        return Two_Factor_Notakey::get_instance();
    }

    public static function check_auth_status()
    {
        $uuid = sanitize_text_field($_POST['uuid']);

        $status = self::get_ntk_instance()->get_auth_status($uuid);

        wp_send_json_success($status);
    }

    public static function td()
    {
        return self::$plugin_name;
    }

    public static function ps()
    {
        return self::$plugin_name;
    }

    private static function register_settings()
    {
        if (is_array(self::$settings)) {

            register_setting(self::ps(), self::ps(), array(__CLASS__, 'validate_fields'));

            foreach (self::$settings as $section => $data) {

                // Add section to page
                add_settings_section($section, $data['title'], array(__CLASS__, 'settings_section'), self::ps());

                foreach ($data['fields'] as $field) {

                    // Add field to page
                    add_settings_field($field['id'], $field['label'], array(__CLASS__, 'display_field'), self::ps(), $section, array('field' => $field));
                }
            }
        }
    }

    public static function display_field($args)
    {

        $field = $args['field'];

        $html = '';

        $option_name = self::ps() . "[" . $field['id'] . "]";

        $data = (isset(self::$options[$field['id']])) ? self::$options[$field['id']] : '';

        switch ($field['type']) {

            case 'text':
            case 'password':
            case 'number':
                $html .= '<input id="' . esc_attr($field['id']) . '" type="' . $field['type'] . '" name="' . esc_attr($option_name) . '" placeholder="' . esc_attr($field['placeholder']) . '" value="' . $data . '"/>' . "\n";
                break;

            case 'text_secret':
                $html .= '<input id="' . esc_attr($field['id']) . '" type="text" name="' . esc_attr($option_name) . '" placeholder="' . esc_attr($field['placeholder']) . '" value=""/>' . "\n";
                break;

            case 'textarea':
                $html .= '<textarea id="' . esc_attr($field['id']) . '" rows="5" cols="50" name="' . esc_attr($option_name) . '" placeholder="' . esc_attr($field['placeholder']) . '">' . $data . '</textarea><br/>' . "\n";
                break;

            case 'checkbox':
                $checked = '';
                if ($data && 'on' == $data) {
                    $checked = 'checked="checked"';
                }
                $html .= '<input id="' . esc_attr($field['id']) . '" type="' . $field['type'] . '" name="' . esc_attr($option_name) . '" ' . $checked . '/>' . "\n";
                break;

            case 'checkbox_multi':
                foreach ($field['options'] as $k => $v) {
                    $checked = false;
                    if (is_array($data) && in_array($k, $data)) {
                        $checked = true;
                    }
                    $html .= '<label for="' . esc_attr($field['id'] . '_' . $k) . '"><input type="checkbox" ' . checked($checked, true, false) . ' name="' . esc_attr($option_name) . '[]" value="' . esc_attr($k) . '" id="' . esc_attr($field['id'] . '_' . $k) . '" /> ' . $v . '</label> ';
                }
                break;

            case 'radio':
                foreach ($field['options'] as $k => $v) {
                    $checked = false;
                    if ($k == $data) {
                        $checked = true;
                    }
                    $html .= '<label for="' . esc_attr($field['id'] . '_' . $k) . '"><input type="radio" ' . checked($checked, true, false) . ' name="' . esc_attr($option_name) . '" value="' . esc_attr($k) . '" id="' . esc_attr($field['id'] . '_' . $k) . '" /> ' . $v . '</label> ';
                }
                break;

            case 'select':
                $html .= '<select name="' . esc_attr($option_name) . '" id="' . esc_attr($field['id']) . '">';
                foreach ($field['options'] as $k => $v) {
                    $selected = false;
                    if ($k == $data) {
                        $selected = true;
                    }
                    $html .= '<option ' . selected($selected, true, false) . ' value="' . esc_attr($k) . '">' . $v . '</option>';
                }
                $html .= '</select> ';
                break;

            case 'select_multi':
                $html .= '<select name="' . esc_attr($option_name) . '[]" id="' . esc_attr($field['id']) . '" multiple="multiple">';
                foreach ($field['options'] as $k => $v) {
                    $selected = false;
                    if (in_array($k, $data)) {
                        $selected = true;
                    }
                    $html .= '<option ' . selected($selected, true, false) . ' value="' . esc_attr($k) . '" />' . $v . '</label> ';
                }
                $html .= '</select> ';
                break;
        }

        switch ($field['type']) {

            case 'checkbox_multi':
            case 'radio':
            case 'select_multi':
                $html .= '<br /><span class="description">' . $field['description'] . '</span>';
                break;

            default:
                $html .= '<label for="' . esc_attr($field['id']) . '"><span class="description">' . $field['description'] . '</span></label>' . "\n";
                break;
        }

        echo $html;
    }

    private static function is_two_factor_active()
    {
        return class_exists('Two_Factor_Core');
    }

    public static function settings_section($section)
    {
        $html = '<p> ' . self::$settings[$section['id']]['description'] . '</p>' . "\n";
        echo $html;
    }

    private static function settings_fields()
    {

        $settings['policy'] = [
            'title'                    => __('2FA Provider Policy', self::td()),
            'description'            => __('Authentication policy settings.', self::td()),
            'fields'                => array(
                array(
                    'id'             => 'enable_notakey_for_all',
                    'label'            => __('Enable Notakey 2FA provider for all users', self::td()),
                    'description'    => __('If user has registered other 2FA method, Notakey Authenticator will be shown as backup method.', self::td()),
                    'type'            => 'checkbox',
                    'default'        => 'off'
                ),
                array(
                    'id'             => 'provider_override_active',
                    'label'            => __('Enable 2FA provider override list below', self::td()),
                    'description'    => __('', self::td()),
                    'type'            => 'checkbox',
                    'default'        => 'off'
                ),
                array(
                    'id'             => 'provider_override_list',
                    'label'            => __('Global 2FA provider override list', self::td()),
                    'description'    => __('Select one or more 2FA providers that you wish to allow globally.', self::td()),
                    'type'            => 'checkbox_multi',
                    'options'        => array('Two_Factor_Email' => 'Email', 'Two_Factor_Totp' => 'OTP code', 'Two_Factor_FIDO_U2F' => 'FIDO U2F', 'Two_Factor_Backup_Codes' => 'Backup codes', 'Two_Factor_Notakey' => 'Notakey Authenticator',),
                    'default'        => array()
                ),
                array(
                    'id'             => 'enable_user_self_service',
                    'label'            => __('Allow users to provide onboarding details', self::td()),
                    'description'    => __('Permits regular users to update their profile with mobile phone or onboarding password (depends on onboarding requirements set in Authentication Server).', self::td()),
                    'type'            => 'checkbox',
                    'default'        => 'off'
                ),
                array(
                    'id'             => 'reject_login_without_mfa',
                    'label'            => __('Reject user login without 2FA verification', self::td()),
                    'description'    => __('Users without at least one enabled 2FA authentication provider will be prevented from logging on. Enable "Enable Notakey 2FA provider for all users" to force Notakey authentication as a fallback method.', self::td()),
                    'type'            => 'checkbox',
                    'default'        => 'off'
                ),

            )
        ];

        $settings['request'] = [
            'title'                    => __('Authentication Request', self::td()),
            'description'            => __('Authentication request settings.', self::td()),
            'fields'                => array(
                array(
                    'id'             => 'request_title',
                    'label'          => __('Request title', self::td()),
                    'description'    => __('Message title for authentication request.', self::td()),
                    'type'           => 'text',
                    'default'        => 'Wordpress authentication',
                    'placeholder'    => __('Wordpress authentication', self::td())
                ),
                array(
                    'id'             => 'request_message',
                    'label'          => __('Request message', self::td()),
                    'description'    => __('Message body for authentication request. Placeholder %user% will be filled with user login name.', self::td()),
                    'type'           => 'text',
                    'default'        => 'Proceed with login as user %user%?',
                    'placeholder'    => __('Proceed with login as user %user%?', self::td())
                ),
                array(
                    'id'             => 'request_ttl',
                    'label'          => __('Request time to live', self::td()),
                    'description'    => __('Time in seconds that request will be valid for.', self::td()),
                    'type'           => 'number',
                    'default'        => 300,
                    'placeholder'    => __('300', self::td())
                ),
            )
        ];

        $settings['server'] = [
            'title'                    => __('Authentication Server', self::td()),
            'description'            => __('Notakey Authentication server settings.', self::td()),
            'fields'                => array(
                array(
                    'id'             => 'service_url',
                    'label'            => __('Service URL', self::td()),
                    'description'    => __('Authentication server address.', self::td()),
                    'type'            => 'text',
                    'default'        => '',
                    'placeholder'    => __('https://mfa.example.com', self::td())
                ),
                array(
                    'id'             => 'client_id',
                    'label'            => __('Client ID', self::td()),
                    'description'    => __('Can be found in authentication server dashboard, "Access credentials" page. Requires urn:notakey:auth urn:notakey:usermanager urn:notakey:user urn:notakey:devicemanager scopes.', self::td()),
                    'type'            => 'text',
                    'default'        => '',
                    'placeholder'    => __('12345678-1234-1234-1234-123456789012', self::td())
                ),
                array(
                    'id'             => 'client_secret',
                    'label'            => __('Client Secret', self::td()),
                    'description'    => __('Secret for "Client ID" setting above.', self::td()),
                    'type'            => 'password',
                    'default'        => '',
                    'placeholder'    => __('', self::td())
                ),
                array(
                    'id'             => 'service_id',
                    'label'            => __('Service ID (Access ID)', self::td()),
                    'description'    => __('Notakey service identifier, can be found in authentication server dashboard service settings.', self::td()),
                    'type'            => 'text',
                    'default'        => '',
                    'placeholder'    => __('12345678-1234-1234-1234-123456789012', self::td())
                ),
                array(
                    'id'             => 'service_domain',
                    'label'            => __('Service Domain', self::td()),
                    'description'    => __('Service domain to announce to users. Requires DNS SRV record in zone entered here.', self::td()),
                    'type'            => 'text',
                    'default'        => '',
                    'placeholder'    => __('mycompany.com', self::td())
                ),
            )
        ];

        $settings = apply_filters('plugin_settings_fields', $settings);

        return $settings;
    }

    public static function get_options()
    {
        $options = get_option(self::ps());

        if (!$options && is_array(self::$settings)) {
            $options = array();
            foreach (self::$settings as $section => $data) {
                foreach ($data['fields'] as $field) {
                    $options[$field['id']] = $field['default'];
                }
            }

            add_option(self::ps(), $options);
        }

        return $options;
    }

    public static function admin_init_action()
    {
        self::$settings = self::settings_fields();
        self::$options = self::get_options();
        self::register_settings();
    }

    public static function settings_page()
    {
        $error_msg = '';
        if (!self::is_two_factor_active()) {
            $error_msg = __('Required Two-Factor plugin is not active, get it <a href="https://wordpress.org/plugins/two-factor/">here</a>', self::td());
        }

?>
        <div class="wrap" id="<?php echo self::ps(); ?>">
            <h2><?php _e('Notakey Multi-Factor Authentication Settings', self::td()); ?></h2>
            <!-- <p><?php _e('Add this description!!!.', self::td()); ?></p> -->
            <?php
            if (!empty($error_msg)) {
                echo '<div id="login_error"><strong>' . $error_msg . '</strong><br /></div>';
            }
            ?>
            <!-- Tab navigation starts -->
            <h2 class="nav-tab-wrapper settings-tabs hide-if-no-js">
                <?php
                foreach (self::$settings as $section => $data) {
                    echo '<a href="#' . $section . '" class="nav-tab">' . $data['title'] . '</a>';
                }
                ?>
            </h2>
            <?php self::do_script_for_tabbed_nav(); ?>
            <!-- Tab navigation ends -->

            <form action="options.php" method="POST">
                <?php settings_fields(self::ps()); ?>
                <div class="settings-container">
                    <?php do_settings_sections(self::ps()); ?>
                </div>
                <?php submit_button(); ?>
            </form>
        </div>
    <?php
    }

    private static function do_script_for_tabbed_nav()
    {
        // Very simple jQuery logic for the tabbed navigation.
        // Delete this function if you don't need it.
        // If you have other JS assets you may merge this there.
    ?>
        <script>
            jQuery(document).ready(function($) {
                var headings = jQuery('.settings-container > h2, .settings-container > h3');
                var paragraphs = jQuery('.settings-container > p');
                var tables = jQuery('.settings-container > table');
                var triggers = jQuery('.settings-tabs a');

                triggers.each(function(i) {
                    triggers.eq(i).on('click', function(e) {
                        e.preventDefault();
                        triggers.removeClass('nav-tab-active');
                        headings.hide();
                        paragraphs.hide();
                        tables.hide();

                        triggers.eq(i).addClass('nav-tab-active');
                        headings.eq(i).show();
                        paragraphs.eq(i).show();
                        tables.eq(i).show();
                    });
                })

                triggers.eq(0).click();
            });
        </script>
<?php
    }

    public static function validate_fields($data)
    {
        // $data array contains values to be saved:
        // either sanitize/modify $data or return false
        // to prevent the new options to be saved

        // Sanitize fields, eg. cast number field to integer
        // $data['number_field'] = (int) $data['number_field'];

        // Validate fields, eg. don't save options if the password field is empty
        // if ( $data['password_field'] == '' ) {
        // 	add_settings_error( self::ps(), 'no-password', __('A password is required.', self::td()), 'error' );
        // 	return false;
        // }

        return $data;
    }


    public static function admin_menu_action()
    {
        //add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position );
        // add_menu_page(self::$plugin_name, 'Plugin Name', 'administrator', self::$plugin_name, [__CLASS__, 'displayPluginAdminDashboard'], 'dashicons-chart-area', 26);

        //add_submenu_page( '$parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function );
        // add_submenu_page('options-general.php', 'Plugin Name Settings', 'Settings', 'administrator', self::$plugin_name . '-settings', [__CLASS__, 'displayPluginAdminSettings']);

        add_options_page(__('Notakey MFA', self::td()), __('Notakey MFA', self::td()), 'administrator', self::$plugin_name . '-settings', [__CLASS__, 'settings_page']);
    }

    public static function deleted_user_action($id, $reassign, $user)
    {
        if (isset($user->data->user_login)) {
            self::ntkas()->delete_user($user->data->user_login);
        }
    }

    public static function ntkas()
    {
        require_once(plugin_dir_path(__FILE__) . '/vendor/autoload.php');
        require_once(plugin_dir_path(__FILE__) . '/nas-api.php');
        return new NasApi(
            self::get_config('service_url', ''),
            self::get_config('client_id', ''),
            self::get_config('client_secret', ''),
            self::get_config('service_id', ''),
            self::get_config('service_domain', ''),
            self::get_ntk_instance()
        );
    }

    public static function register_hooks()
    {
        // two_factor_providers
        add_filter('two_factor_providers', [__CLASS__, 'two_factor_providers_filter'], 10, 2);

        // two_factor_enabled_providers_for_user
        add_filter('two_factor_enabled_providers_for_user', [__CLASS__, 'two_factor_enabled_providers_for_user_filter'], 10, 2);

        // register authentication status request API method
        add_action('wp_ajax_nopriv_ntk_check_auth_status', [__CLASS__, 'check_auth_status']);

        // plugins_loaded
        add_action('plugins_loaded', [__CLASS__, 'load_textdomain']);

        // admin_menu
        add_action('admin_menu', [__CLASS__, 'admin_menu_action']);

        // admin_init
        add_action('admin_init', [__CLASS__, 'admin_init_action']);

        // user deleted
        add_action('deleted_user', [__CLASS__, 'deleted_user_action'], 10, 3);
    }
}
