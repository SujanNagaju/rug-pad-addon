<?php
/**
 * "Need a rugpad? Click Here" link + rug pad size form.
 *
 * @var WC_Product[] $products Purchasable rug pad products.
 * @var array        $settings Plugin settings.
 */
defined( 'ABSPATH' ) || exit;

$max   = (int) $settings['max_dimension'];
$multi = count( $products ) > 1;
?>
<div id="rpa-addon" class="rpa-addon" data-max="<?php echo esc_attr( $max ); ?>">

	<p class="rpa-prompt">
		<?php echo esc_html( $settings['prompt_text'] ); ?>
		<a href="#rpa-panel" class="rpa-toggle" role="button" aria-expanded="false" aria-controls="rpa-panel"><?php echo esc_html( $settings['link_text'] ); ?></a>
	</p>

	<div id="rpa-panel" class="rpa-panel" hidden>
		<?php if ( $settings['heading'] ) : ?>
			<h3 class="rpa-heading"><?php echo esc_html( $settings['heading'] ); ?></h3>
		<?php endif; ?>

		<?php if ( $multi ) : ?>
			<p class="rpa-choose">
				<label for="rpa-product"><strong><?php esc_html_e( 'Choose your rug pad', 'rug-pad-addon' ); ?></strong></label>
				<select id="rpa-product">
					<?php foreach ( $products as $p ) : ?>
						<option value="<?php echo esc_attr( $p->get_id() ); ?>"><?php echo esc_html( $p->get_name() ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
		<?php else : ?>
			<input type="hidden" id="rpa-product" value="<?php echo esc_attr( $products[0]->get_id() ); ?>">
		<?php endif; ?>

		<?php foreach ( $products as $i => $p ) : ?>
			<div class="rpa-product" data-product="<?php echo esc_attr( $p->get_id() ); ?>" <?php echo 0 === $i ? '' : 'hidden'; ?>>
				<div class="rpa-image">
					<?php echo $p->get_image( 'woocommerce_single' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>
				<div class="rpa-info">
					<h4 class="rpa-title"><?php echo esc_html( $p->get_name() ); ?></h4>
					<?php if ( $p->get_short_description() ) : ?>
						<div class="rpa-desc"><?php echo wp_kses_post( wpautop( $p->get_short_description() ) ); ?></div>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>

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
