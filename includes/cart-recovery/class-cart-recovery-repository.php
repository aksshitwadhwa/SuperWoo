<?php
defined('ABSPATH') || exit;

/** Small prepared-SQL repository for Cart Recovery operational data. */
class SuperWoo_Cart_Recovery_Repository {
    private $wpdb;
    private $prefix;

    public function __construct() { global $wpdb; $this->wpdb = $wpdb; $this->prefix = $wpdb->prefix . 'superwoo_recovery_'; }
    public function table($name) { return $this->prefix . $name; }
    public function now() { return gmdate('Y-m-d H:i:s'); }
    public function hash($value) { return hash_hmac('sha256', (string) $value, wp_salt('superwoo_cart_recovery')); }

    public function find_cart_by_session($session_id) {
        return $this->wpdb->get_row($this->wpdb->prepare('SELECT * FROM ' . $this->table('carts') . ' WHERE session_hash = %s LIMIT 1', $this->hash($session_id)), ARRAY_A);
    }
    public function find_cart_by_token($token) {
        return $this->wpdb->get_row($this->wpdb->prepare('SELECT * FROM ' . $this->table('carts') . ' WHERE recovery_token_hash = %s LIMIT 1', $this->hash($token)), ARRAY_A);
    }
    public function find_cart($id) { return $this->wpdb->get_row($this->wpdb->prepare('SELECT * FROM ' . $this->table('carts') . ' WHERE id = %d', absint($id)), ARRAY_A); }

    public function save_cart($session_id, $data) {
        $existing = $this->find_cart_by_session($session_id);
        $now = $this->now();
        $data['session_hash'] = $this->hash($session_id);
        $data['updated_at'] = $now;
        if ($existing) { $this->wpdb->update($this->table('carts'), $data, ['id' => (int) $existing['id']]); return $this->find_cart((int) $existing['id']); }
        $data = array_merge(['public_id' => wp_generate_uuid4(), 'status' => 'active', 'created_at' => $now, 'last_activity_at' => $now], $data);
        $this->wpdb->insert($this->table('carts'), $data);
        return $this->find_cart((int) $this->wpdb->insert_id);
    }
    public function replace_items($cart_id, $items) {
        $this->wpdb->delete($this->table('cart_items'), ['cart_id' => absint($cart_id)]);
        $now = $this->now();
        foreach ($items as $item) { $item['cart_id'] = absint($cart_id); $item['created_at'] = $now; $item['updated_at'] = $now; $this->wpdb->insert($this->table('cart_items'), $item); }
    }
    public function items($cart_id) { return $this->wpdb->get_results($this->wpdb->prepare('SELECT * FROM ' . $this->table('cart_items') . ' WHERE cart_id = %d ORDER BY id ASC', absint($cart_id)), ARRAY_A); }
    public function event($cart_id, $type, $data = [], $channel = '', $idempotency_key = '') {
        $key = $idempotency_key ? $this->hash($idempotency_key) : '';
        if ($key && $this->wpdb->get_var($this->wpdb->prepare('SELECT id FROM ' . $this->table('events') . ' WHERE idempotency_key = %s', $key))) { return false; }
        return false !== $this->wpdb->insert($this->table('events'), ['cart_id' => absint($cart_id), 'event_type' => sanitize_key($type), 'channel' => sanitize_key($channel), 'data' => wp_json_encode($data), 'idempotency_key' => $key, 'created_at' => $this->now()]);
    }
    public function abandoned_candidates($before, $limit = 100) {
        return $this->wpdb->get_results($this->wpdb->prepare('SELECT * FROM ' . $this->table('carts') . " WHERE status = 'active' AND last_activity_at <= %s AND total > 0 ORDER BY last_activity_at ASC LIMIT %d", $before, max(1, absint($limit))), ARRAY_A);
    }
    public function update_status($id, $status, $fields = []) { $fields = array_merge($fields, ['status' => sanitize_key($status), 'updated_at' => $this->now()]); return false !== $this->wpdb->update($this->table('carts'), $fields, ['id' => absint($id)]); }
    public function message($data) {
        $now = $this->now();
        $data = array_merge(['execution_id' => 0, 'workflow_step_id' => 0, 'provider_message_id' => '', 'subject' => '', 'payload' => '', 'error_message' => '', 'sent_at' => null, 'opened_at' => null, 'clicked_at' => null, 'created_at' => $now, 'updated_at' => $now], $data);
        return false !== $this->wpdb->insert($this->table('messages'), $data);
    }
    public function is_suppressed($channel, $identifier) {
        if (!$identifier) { return false; }
        return (bool) $this->wpdb->get_var($this->wpdb->prepare('SELECT id FROM ' . $this->table('suppressions') . ' WHERE channel = %s AND identifier_hash = %s AND (expires_at IS NULL OR expires_at > %s)', sanitize_key($channel), $this->hash(strtolower((string) $identifier)), $this->now()));
    }
    public function dashboard($from, $to) {
        $sql = 'SELECT status, COUNT(*) count, COALESCE(SUM(total),0) revenue, COALESCE(AVG(total),0) average_value FROM ' . $this->table('carts') . ' WHERE created_at BETWEEN %s AND %s GROUP BY status';
        return $this->wpdb->get_results($this->wpdb->prepare($sql, $from, $to), ARRAY_A);
    }
    public function carts($args = []) {
        $args = wp_parse_args($args, ['page' => 1, 'per_page' => 20, 'status' => '', 'search' => '']); $where = ['1=1']; $values = [];
        if ($args['status']) { $where[] = 'status = %s'; $values[] = sanitize_key($args['status']); }
        if ($args['search']) { $where[] = '(email LIKE %s OR phone LIKE %s OR public_id LIKE %s OR order_id = %d)'; $like = '%' . $this->wpdb->esc_like(sanitize_text_field($args['search'])) . '%'; $values = array_merge($values, [$like, $like, $like, absint($args['search'])]); }
        $where_sql = implode(' AND ', $where); $base = ' FROM ' . $this->table('carts') . ' WHERE ' . $where_sql;
        $total = (int) $this->wpdb->get_var($values ? $this->wpdb->prepare('SELECT COUNT(*)' . $base, $values) : 'SELECT COUNT(*)' . $base);
        $values[] = max(1, absint($args['per_page'])); $values[] = max(0, (absint($args['page']) - 1) * absint($args['per_page']));
        $rows = $this->wpdb->get_results($this->wpdb->prepare('SELECT *' . $base . ' ORDER BY last_activity_at DESC LIMIT %d OFFSET %d', $values), ARRAY_A);
        return ['items' => $rows, 'total' => $total];
    }
    public function events($cart_id) { return $this->wpdb->get_results($this->wpdb->prepare('SELECT * FROM ' . $this->table('events') . ' WHERE cart_id = %d ORDER BY created_at DESC', absint($cart_id)), ARRAY_A); }
    public function workflows($enabled_only = false) { $sql = 'SELECT * FROM ' . $this->table('workflows') . ($enabled_only ? " WHERE status = 'enabled'" : '') . ' ORDER BY updated_at DESC'; return $this->wpdb->get_results($sql, ARRAY_A); }
    public function steps($workflow_id) { return $this->wpdb->get_results($this->wpdb->prepare('SELECT * FROM ' . $this->table('workflow_steps') . ' WHERE workflow_id = %d ORDER BY step_order ASC', absint($workflow_id)), ARRAY_A); }
    public function create_execution($cart_id, $workflow_id) { $now = $this->now(); $key = $this->hash('execution:' . $cart_id . ':' . $workflow_id); $ok = $this->wpdb->query($this->wpdb->prepare('INSERT IGNORE INTO ' . $this->table('executions') . ' (cart_id,workflow_id,status,idempotency_key,created_at,updated_at) VALUES (%d,%d,%s,%s,%s,%s)', $cart_id,$workflow_id,'pending',$key,$now,$now)); return $ok ? (int) $this->wpdb->insert_id : (int) $this->wpdb->get_var($this->wpdb->prepare('SELECT id FROM '.$this->table('executions').' WHERE cart_id=%d AND workflow_id=%d',$cart_id,$workflow_id)); }
    public function execution($id) { return $this->wpdb->get_row($this->wpdb->prepare('SELECT * FROM '.$this->table('executions').' WHERE id=%d',absint($id)),ARRAY_A); }
    public function claim_execution($id) { $now=$this->now(); return 1 === (int) $this->wpdb->query($this->wpdb->prepare('UPDATE '.$this->table('executions')." SET status='running',locked_at=%s,updated_at=%s WHERE id=%d AND (locked_at IS NULL OR locked_at < %s) AND status IN ('pending','waiting','running')",$now,$now,$id,gmdate('Y-m-d H:i:s',time()-600))); }
    public function update_execution($id,$data) { $data['updated_at']=$this->now(); return false !== $this->wpdb->update($this->table('executions'),$data,['id'=>absint($id)]); }
}
