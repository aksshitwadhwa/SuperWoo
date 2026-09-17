<?php
defined('ABSPATH') || exit;

class SuperWoo_Cart_Recovery_Admin {
    private $repository;
    public function __construct($repository) { $this->repository = $repository; }
    public function hooks() { add_action('admin_menu', [$this, 'menu']); }
    public function menu() { add_submenu_page('superwoo-settings', __('Cart Recovery','superwoo'), __('Cart Recovery','superwoo'), 'manage_woocommerce', 'superwoo-cart-recovery', [$this,'page']); }
    public function page() {
        if (!current_user_can('manage_woocommerce')) { return; }
        $cart_id = absint($_GET['cart_id'] ?? 0);
        if ($cart_id) { $this->detail($cart_id); return; }
        $tab = sanitize_key($_GET['tab'] ?? 'overview'); $tab = in_array($tab, ['overview','carts','analytics','automations','templates','settings'], true) ? $tab : 'overview';
        $from = gmdate('Y-m-d 00:00:00', strtotime('-30 days')); $to = gmdate('Y-m-d 23:59:59'); $summary = $this->repository->dashboard($from, $to);
        $metrics = ['active'=>['count'=>0,'revenue'=>0],'abandoned'=>['count'=>0,'revenue'=>0],'recovered'=>['count'=>0,'revenue'=>0]]; foreach ($summary as $row) { if (isset($metrics[$row['status']])) { $metrics[$row['status']] = $row; } }
        $rows = $tab === 'carts' ? $this->repository->carts(['page'=>absint($_GET['paged'] ?? 1),'status'=>sanitize_key($_GET['status'] ?? ''),'search'=>sanitize_text_field($_GET['s'] ?? '')]) : null;
        include SUPERWOO_PATH . 'admin/views/cart-recovery-page.php';
    }
    private function detail($cart_id) {
        $cart = $this->repository->find_cart($cart_id); if (!$cart) { wp_die(esc_html__('Recovery cart not found.','superwoo')); }
        $items = $this->repository->items($cart_id); $events = $this->repository->events($cart_id);
        $context = $cart['context'] ? json_decode($cart['context'], true) : [];
        include SUPERWOO_PATH . 'admin/views/cart-recovery-detail.php';
    }
}
