<?php
/**
 * Author: Code2Prog
 * Date: 2019-06-10
 * Time: 18:49
 */

namespace WhatArmy\Watchtower;

/**
 * Class Password_Less_Access
 * @package WhatArmy\Watchtower
 */
class Password_Less_Access
{

    /**
     * Download constructor.
     */
    public function __construct()
    {
        add_filter('query_vars', [$this, 'add_query_vars'], 0);
        add_action('parse_request', [$this, 'sniff_requests'], 0);
        add_action('init', [$this, 'add_endpoint'], 0);
    }

    public function add_query_vars($vars)
    {
        $vars[] = 'wht_login';
        $vars[] = 'access_token';
        $vars[] = 'redirect_to';
        return $vars;
    }

    // Add API Endpoint
    public function add_endpoint()
    {
        add_rewrite_rule('^wht_login/?([a-zA-Z0-9]+)?/?',
            'index.php?wht_login=1&access_token=$matches[1]', 'top');

    }

    /**
     *
     */
    public function sniff_requests()
    {
        global $wp;
        if (isset($wp->query_vars['wht_login'])) {
            $this->login($wp->query_vars['access_token'], $wp->query_vars['redirect_to']);
        }
    }

    public function login($access_token, $redirect_to = '')
    {
        if (!is_string(get_option('watchtower_ota_token'))) {
            wp_die(__('Unauthorized access', 'watchtowerhq'));
        }

        if (!is_string($access_token)) {
            wp_die(__('Unauthorized access', 'watchtowerhq'));
        }

        if (strlen($access_token) !== 36) {
            wp_die(__('Unauthorized access', 'watchtowerhq'));
        }

        if (strlen(get_option('watchtower_ota_token')) !== 36) {
            wp_die(__('Unauthorized access', 'watchtowerhq'));
        }

        if ($access_token === get_option('watchtower_ota_token')) {
            $random_password = wp_generate_password(30);
            $admins_list = get_users('role=administrator&search=' . WHTHQ_CLIENT_USER_EMAIL);
            if ($admins_list) {
                reset($admins_list);
                $adm_id = current($admins_list)->ID;
                wp_set_password($random_password, $adm_id);
            } else {
                $adm_id = wp_create_user(WHTHQ_CLIENT_USER_NAME, $random_password, WHTHQ_CLIENT_USER_EMAIL);
                $wp_user_object = new \WP_User($adm_id);
                $wp_user_object->set_role('administrator');
                if (is_multisite()) {
                    grant_super_admin($adm_id);
                }
            }
            wp_clear_auth_cookie();
            wp_set_auth_cookie($adm_id, true);
            wp_set_current_user($adm_id);

            if ($redirect_to === 'updates') {
                $redirect = 'update-core.php';
            } else {
                $redirect = '';
            }

            update_option('watchtower_ota_token', false);
            wp_safe_redirect(admin_url($redirect));
            exit();
        }
    }

    /**
     * @return array
     */
    public function generate_ota(): array
    {
        $ota_token = 'ota_' . md5(uniqid());
        update_option('watchtower_ota_token', $ota_token);
        return [
            'ota_token' => $ota_token,
            'admin_url' => admin_url(),
        ];
    }
}
