<?php
/** In-process simulation of the Digtize metadata and ZIP endpoints; never contacts the network. */
final class SuperWoo_Update_Server_Fixture {
    public $metadata_status = 200;
    public $metadata_body = '';
    public $metadata_error = false;
    public $package_status = 200;
    public $package_file = '';
    public $package_error = false;
    public $requests = [];

    public function respond($url, $args) {
        $this->requests[] = [$url, $args];
        if ('https://digtize.com/plugins/superwoo/update.json' === $url) {
            if ($this->metadata_error) {
                return new WP_Error('fixture_metadata_network_failure');
            }
            return [
                'response' => ['code' => $this->metadata_status],
                'headers' => ['content-length' => (string) strlen($this->metadata_body)],
                'body' => $this->metadata_body,
            ];
        }
        if (0 === strpos((string) $url, 'https://digtize.com/plugins/superwoo/')) {
            if ($this->package_error) {
                return new WP_Error('fixture_package_network_failure');
            }
            if (!empty($args['stream']) && !empty($args['filename']) && is_file($this->package_file)) {
                copy($this->package_file, $args['filename']);
            }
            $size = is_file($this->package_file) ? filesize($this->package_file) : 0;
            return [
                'response' => ['code' => $this->package_status],
                'headers' => ['content-length' => (string) $size],
                'body' => '',
            ];
        }
        return new WP_Error('fixture_unexpected_url');
    }
}
