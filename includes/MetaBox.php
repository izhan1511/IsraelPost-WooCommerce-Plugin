<?php
/**
 * Parcels management with IL-Post for https://www.israel.com/
 *
 * @category Parcels & Shipping
 * @package  slparcels
 * @author   Developer: Pniel Cohen
 * @author   Company: Trus (https://www.trus.co.il/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Trus_SLParcels_MetaBox Class.
 */
class Trus_SLParcels_MetaBox {

	/**
	 * Output the metabox.
	 *
	 * @param WP_Post $post
	 */
	public static function order_output( $post ) {
		?>
		<div class="order_israel_parcels wc-metaboxes-wrapper">

			<div class="wc-metaboxes">
				<?php
                $parcels_table = new Trus_SLParcels_Table_OrderParcels();
                $parcels_table->set_order_id($post->ID);
                $parcels_table->prepare_items();
                $parcels_table->display_table();
				?>
			</div>
		</div>
		<?php
	}
}
