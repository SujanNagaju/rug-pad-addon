<?php
/**
 * Cart / Checkout add-on section.
 *
 * @var WC_Product $product
 * @var array      $settings
 */
defined( 'ABSPATH' ) || exit;

$max = (int) $settings['max_dimension'];
?>
<div id="rpa-addon" class="rpa-addon" data-max="<?php echo esc_attr( $max ); ?>">
	<h3 class="rpa-heading"><?php echo esc_html( $settings['heading'] ); ?></h3>

	<div class="rpa-body">
		<div class="rpa-image">
			<?php echo $product->get_image( 'woocommerce_single' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>

		<div class="rpa-details">
			<h4 class="rpa-title"><?php echo esc_html( $product->get_name() ); ?></h4>

			<?php if ( $product->get_short_description() ) : ?>
				<div class="rpa-desc"><?php echo wp_kses_post( wpautop( $product->get_short_description() ) ); ?></div>
			<?php endif; ?>

			<p class="rpa-dim-label"><strong><?php esc_html_e( 'Product dimensions (Length × Width)', 'rug-pad-addon' ); ?></strong></p>

			<div class="rpa-fields">
				<fieldset class="rpa-field">
					<legend><?php esc_html_e( 'Length', 'rug-pad-addon' ); ?></legend>
					<label>
						<input type="number" id="rpa-length-ft" min="0" max="<?php echo esc_attr( $max ); ?>" step="1" inputmode="numeric" placeholder="0">
						<span><?php esc_html_e( 'ft', 'rug-pad-addon' ); ?></span>
					</label>
					<label>
						<select id="rpa-length-in">
							<?php for ( $i = 0; $i <= 11; $i++ ) : ?>
								<option value="<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $i ); ?></option>
							<?php endfor; ?>
						</select>
						<span><?php esc_html_e( 'in', 'rug-pad-addon' ); ?></span>
					</label>
				</fieldset>

				<fieldset class="rpa-field">
					<legend><?php esc_html_e( 'Width', 'rug-pad-addon' ); ?></legend>
					<label>
						<input type="number" id="rpa-width-ft" min="0" max="<?php echo esc_attr( $max ); ?>" step="1" inputmode="numeric" placeholder="0">
						<span><?php esc_html_e( 'ft', 'rug-pad-addon' ); ?></span>
					</label>
					<label>
						<select id="rpa-width-in">
							<?php for ( $i = 0; $i <= 11; $i++ ) : ?>
								<option value="<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $i ); ?></option>
							<?php endfor; ?>
						</select>
						<span><?php esc_html_e( 'in', 'rug-pad-addon' ); ?></span>
					</label>
				</fieldset>
			</div>

			<p class="rpa-note"><?php esc_html_e( 'Sizes are rounded up to the next whole foot (e.g. 11 ft 1 in is billed as 12 ft).', 'rug-pad-addon' ); ?></p>

			<div class="rpa-result" aria-live="polite">
				<p class="rpa-summary"></p>
				<p class="rpa-total"><?php esc_html_e( 'Rug pad total:', 'rug-pad-addon' ); ?> <strong class="rpa-total-amount"></strong></p>
			</div>

			<p class="rpa-message" role="alert" aria-live="assertive"></p>

			<button type="button" class="button alt rpa-add" disabled><?php echo esc_html( $settings['button_label'] ); ?></button>
		</div>
	</div>
</div>
