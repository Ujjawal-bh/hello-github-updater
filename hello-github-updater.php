<?php
/*
Plugin Name: Hello GitHub Updater
Plugin URI: https://github.com/Ujjawal-bh/hello-github-updater
Description: A simple plugin that says "Hello World" with automatic GitHub updates
Version: 1.0.0
Author: Ujjawal Bhandare
Author URI: https://yourwebsite.com
Update URI: https://github.com/Ujjawal-bh/hello-github-updater
*/

// Display "Hello World" on the front-end
add_action('wp_footer', function() {
    echo '<div style="text-align: center; padding: 20px; background: #f0f0f0;">Hello World! (Version: ' . get_plugin_data(__FILE__)['Version'] . ')</div>';
});

// GitHub Auto-Updater Class
if (!class_exists('GitHub_Plugin_Updater')) {
    class GitHub_Plugin_Updater {
        private $file;
        private $plugin;
        private $basename;
        private $github_response;
        
        public function __construct($file) {
            $this->file = $file;
            add_action('admin_init', [$this, 'set_plugin_properties']);
            add_filter('pre_set_site_transient_update_plugins', [$this, 'modify_transient'], 10, 1);
            add_filter('plugins_api', [$this, 'plugin_popup'], 10, 3);
        }
        
        public function set_plugin_properties() {
            $this->plugin = get_plugin_data($this->file);
            $this->basename = plugin_basename($this->file);
        }
        
        private function get_repository_info() {
            $plugin_uri = $this->plugin['PluginURI'];
            $parsed = parse_url($plugin_uri);
            $path = trim($parsed['path'] ?? '', '/');
            list($owner, $repo) = explode('/', $path);
            
            $request_uri = sprintf('https://api.github.com/repos/%s/%s/releases', $owner, $repo);
            
            $args = [];
            // Uncomment for private repos:
            // $args['headers'] = ['Authorization' => 'token YOUR_GITHUB_TOKEN'];
            
            $response = wp_remote_get($request_uri, $args);
            $body = wp_remote_retrieve_body($response);
            $this->github_response = @json_decode($body, true)[0];
        }
        
        public function modify_transient($transient) {
            if (property_exists($transient, 'checked')) {
                if ($checked = $transient->checked) {
                    $this->get_repository_info();
                    
                    if ($this->github_response && version_compare(
                        $this->github_response['tag_name'], 
                        $checked[$this->basename], 
                        'gt'
                    )) {
                        $plugin = [
                            'slug' => dirname($this->basename),
                            'new_version' => $this->github_response['tag_name'],
                            'url' => $this->plugin['PluginURI'],
                            'package' => $this->github_response['zipball_url']
                        ];
                        
                        $transient->response[$this->basename] = (object) $plugin;
                    }
                }
            }
            return $transient;
        }
        
        public function plugin_popup($result, $action, $args) {
            if ($action == 'plugin_information' && 
                isset($args->slug) && 
                $args->slug === dirname($this->basename)) {
                
                $this->get_repository_info();
                
                $plugin = [
                    'name' => $this->plugin['Name'],
                    'slug' => $this->basename,
                    'version' => $this->github_response['tag_name'],
                    'author' => $this->plugin['Author'],
                    'author_profile' => $this->plugin['AuthorURI'],
                    'last_updated' => $this->github_response['published_at'],
                    'homepage' => $this->plugin['PluginURI'],
                    'short_description' => $this->plugin['Description'],
                    'download_link' => $this->github_response['zipball_url']
                ];
                
                return (object) $plugin;
            }
            return $result;
        }
    }
}

// Initialize the updater
new GitHub_Plugin_Updater(__FILE__);