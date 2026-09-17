<?php
defined('ABSPATH') || exit;
/** Evaluates structured, non-executable workflow conditions against current data. */
class SuperWoo_Cart_Recovery_Conditions {
    public function matches($cart, $items, $definition) {
        $definition = is_array($definition) ? $definition : []; $conditions = isset($definition['conditions']) && is_array($definition['conditions']) ? $definition['conditions'] : [];
        if (!$conditions) return true; $results=[];
        foreach($conditions as $condition){ $results[]=$this->condition($cart,$items,$condition); }
        return ('any'===strtolower($definition['mode']??'all')) ? in_array(true,$results,true) : !in_array(false,$results,true);
    }
    private function condition($cart,$items,$c) {
        $type=sanitize_key($c['type']??''); $value=$c['value']??null;
        if('cart_value_greater_than'===$type) return is_numeric($value)&&(float)$cart['total']>(float)$value;
        if('cart_value_less_than'===$type) return is_numeric($value)&&(float)$cart['total']<(float)$value;
        if('guest_customer'===$type) return empty($cart['customer_id']); if('logged_in_customer'===$type) return !empty($cart['customer_id']);
        if('email_available'===$type) return !empty($cart['email']); if('phone_available'===$type) return !empty($cart['phone']);
        if('contains_product'===$type){$id=absint($value);foreach($items as $item){if($id&&($id===(int)$item['product_id']||$id===(int)$item['variation_id']))return true;}return false;}
        if('contains_category'===$type){$id=absint($value);foreach($items as $item){$product=wc_get_product((int)($item['variation_id']?:$item['product_id']));$parent=$product&&$product->is_type('variation')?$product->get_parent_id():($product?$product->get_id():0);if($parent&&has_term($id,'product_cat',$parent))return true;}return false;}
        if('previous_customer'===$type&&function_exists('wc_get_orders')&&!empty($cart['email'])){return (bool)wc_get_orders(['billing_email'=>$cart['email'],'limit'=>1,'return'=>'ids','status'=>array_keys(wc_get_order_statuses())]);}
        return false;
    }
}
