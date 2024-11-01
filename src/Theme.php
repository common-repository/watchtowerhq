<?php
/**
 * Author: Code2Prog
 * Date: 2019-06-07
 * Time: 16:05
 */

namespace WhatArmy\Watchtower;

/**
 * Class Theme
 * @package WhatArmy\Watchtower
 */
class Theme
{
    public \Theme_Upgrader $upgrader;

    /**
     * Plugin constructor.
     */
    public function __construct()
    {
        if (!function_exists('show_message')) {
            require_once ABSPATH . 'wp-admin/includes/misc.php';
        }
        if (!function_exists('request_filesystem_credentials')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (!class_exists('\Theme_Upgrader')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        }
        $this->upgrader = new \Theme_Upgrader(new Updater_Skin());
    }

    public function get(): array
    {
        do_action("wp_update_themes");
        $themes = wp_get_themes();
        $update_list = get_site_transient('update_themes');
        $themes_list = [];
        foreach ($themes as $theme_short_name => $theme) {
            $themes_list[] = [
                'name' => $theme['Name'],
                'version' => $theme['Version'],
                'theme' => $theme_short_name,
                'updates' => $this->check_updates($update_list->response, $theme_short_name, $theme['Version']),
            ];
        }
        return $themes_list;
    }

    /**
     * @param $updates_list
     * @param $theme
     * @param $current
     * @return array
     */
    private function check_updates($updates_list, $theme, $current): array
    {
        if (!empty($updates_list)) {
            if (isset($updates_list[$theme])) {
                if ($updates_list[$theme]['new_version'] != $current) {
                    return [
                        'required' => true,
                        'version' => $updates_list[$theme]['new_version']
                    ];
                } else {
                    return [
                        'required' => false,
                    ];
                }
            } else {
                return [
                    'required' => false,
                ];
            }
        } else {
            return [
                'required' => false,
            ];
        }
    }

    /**
     * @param $themes
     * @return array|false
     */
    public function doUpdate($themes)
    {
        $themes = explode(',', $themes);
        return $this->upgrader->bulk_upgrade($themes);
    }
}
