<?php
defined('ABSPATH') || exit;

/** Read-only observer contract. Implementations may report UI state only. */
interface SuperWoo_Meta_Tracking_Observer_Interface {
    /** Start observing and pass only a checkout type to the callback. */
    public function start($on_observation);

    /** Stop observing without changing checkout state. */
    public function stop();
}
