<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action( 'category_add_form_fields', 'yoohw_category_add_access_code_field' );
function yoohw_category_add_access_code_field( $taxonomy ) {
	$access_type = 'access_code';
	$access_code = '';
	$roles       = [];
	?>
	<div class="form-field yoohw-category-access-row">
		<?php wp_nonce_field( 'yoohw_save_category_access', 'yoohw_category_access_nonce' ); ?>
		<label for="yoohw_access_type"><?php esc_html_e( 'Access method', 'yoohw-support-portal' ); ?></label>
		<?php yoohw_category_render_access_type_select( $access_type ); ?>
		<p class="description"><?php esc_html_e( 'Choose how users are allowed to access and post in this category.', 'yoohw-support-portal' ); ?></p>
	</div>

	<div class="form-field term-access-code-wrap yoohw-category-access-panel" data-yoohw-access-panel="access_code">
		<label for="yoohw_support_access_code"><?php esc_html_e( 'Access code', 'yoohw-support-portal' ); ?></label>
		<input type="text" name="yoohw_support_access_code" id="yoohw_support_access_code" value="<?php echo esc_attr( $access_code ); ?>" />
		<p class="description"><?php esc_html_e( 'Users must have this active access code. Leave empty to allow all logged-in users.', 'yoohw-support-portal' ); ?></p>
	</div>

	<div class="form-field yoohw-category-access-panel" data-yoohw-access-panel="user_role">
		<label for="yoohw_access_roles"><?php esc_html_e( 'User roles', 'yoohw-support-portal' ); ?></label>
		<?php yoohw_category_render_roles_select( $roles ); ?>
		<p class="description"><?php esc_html_e( 'Users with any selected role can access and post in this category. Leave empty to allow all logged-in users.', 'yoohw-support-portal' ); ?></p>
	</div>
	<?php
}

add_action( 'category_edit_form_fields', 'yoohw_category_edit_access_code_field', 10, 2 );
function yoohw_category_edit_access_code_field( $term, $taxonomy ) {
	$access_code = get_term_meta( $term->term_id, 'yoohw_support_access_code', true );
	$access_type = yoohw_category_get_access_type( (int) $term->term_id );
	$roles       = yoohw_category_get_access_roles( (int) $term->term_id );
	?>
	<tr class="form-field yoohw-category-access-row">
		<th scope="row">
			<label for="yoohw_access_type"><?php esc_html_e( 'Access method', 'yoohw-support-portal' ); ?></label>
		</th>
		<td>
			<?php wp_nonce_field( 'yoohw_save_category_access', 'yoohw_category_access_nonce' ); ?>
			<?php yoohw_category_render_access_type_select( $access_type ); ?>
			<p class="description"><?php esc_html_e( 'Choose how users are allowed to access and post in this category.', 'yoohw-support-portal' ); ?></p>
		</td>
	</tr>

	<tr class="form-field term-access-code-wrap yoohw-category-access-panel" data-yoohw-access-panel="access_code">
		<th scope="row">
			<label for="yoohw_support_access_code"><?php esc_html_e( 'Access code', 'yoohw-support-portal' ); ?></label>
		</th>
		<td>
			<input type="text" name="yoohw_support_access_code" id="yoohw_support_access_code" value="<?php echo esc_attr( $access_code ); ?>" />
			<p class="description"><?php esc_html_e( 'Users must have this active access code. Leave empty to allow all logged-in users.', 'yoohw-support-portal' ); ?></p>
		</td>
	</tr>

	<tr class="form-field yoohw-category-access-panel" data-yoohw-access-panel="user_role">
		<th scope="row">
			<label for="yoohw_access_roles"><?php esc_html_e( 'User roles', 'yoohw-support-portal' ); ?></label>
		</th>
		<td>
			<?php yoohw_category_render_roles_select( $roles ); ?>
			<p class="description"><?php esc_html_e( 'Users with any selected role can access and post in this category. Leave empty to allow all logged-in users.', 'yoohw-support-portal' ); ?></p>
		</td>
	</tr>
	<?php
}

add_action( 'created_category', 'yoohw_category_save_access_code', 10, 2 );
add_action( 'edited_category',  'yoohw_category_save_access_code', 10, 2 );

function yoohw_category_save_access_code( $term_id, $tt_id ) {
	if ( ! current_user_can( YoOhw_Support_Capabilities::MANAGE_CATEGORIES ) ) {
		return;
	}

	if (
		empty( $_POST['yoohw_category_access_nonce'] ) ||
		! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['yoohw_category_access_nonce'] ) ),
			'yoohw_save_category_access'
		)
	) {
		return;
	}

	$access_type = isset( $_POST['yoohw_access_type'] ) ? sanitize_key( wp_unslash( $_POST['yoohw_access_type'] ) ) : 'access_code';

	if ( ! array_key_exists( $access_type, yoohw_category_access_types() ) ) {
		$access_type = 'access_code';
	}

	update_term_meta( $term_id, 'yoohw_access_type', $access_type );

	if ( isset( $_POST['yoohw_support_access_code'] ) ) {
		update_term_meta(
			$term_id,
			'yoohw_support_access_code',
			sanitize_text_field( wp_unslash( $_POST['yoohw_support_access_code'] ) )
		);
	}

	$roles = isset( $_POST['yoohw_access_roles'] )
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The helper sanitizes and validates every submitted role slug.
		? yoohw_category_sanitize_access_roles( wp_unslash( $_POST['yoohw_access_roles'] ) )
		: [];

	if ( empty( $roles ) ) {
		delete_term_meta( $term_id, 'yoohw_access_roles' );
	} else {
		update_term_meta( $term_id, 'yoohw_access_roles', $roles );
	}
}

function yoohw_category_access_types(): array {
	return [
		'access_code' => __( 'Access code', 'yoohw-support-portal' ),
		'user_role'   => __( 'User role', 'yoohw-support-portal' ),
	];
}

function yoohw_category_get_access_type( int $term_id ): string {
	$access_type = sanitize_key( (string) get_term_meta( $term_id, 'yoohw_access_type', true ) );

	if ( array_key_exists( $access_type, yoohw_category_access_types() ) ) {
		return $access_type;
	}

	$roles = yoohw_category_get_access_roles( $term_id );

	return empty( $roles ) ? 'access_code' : 'user_role';
}

function yoohw_category_get_access_roles( int $term_id ): array {
	$roles = get_term_meta( $term_id, 'yoohw_access_roles', true );

	return yoohw_category_sanitize_access_roles( is_array( $roles ) ? $roles : [] );
}

function yoohw_category_sanitize_access_roles( $roles ): array {
	$roles         = is_array( $roles ) ? $roles : [];
	$allowed_roles = array_keys( yoohw_category_role_options() );
	$clean         = [];

	foreach ( $roles as $role ) {
		$role = sanitize_key( (string) $role );

		if ( in_array( $role, $allowed_roles, true ) ) {
			$clean[] = $role;
		}
	}

	return array_values( array_unique( $clean ) );
}

function yoohw_category_role_options(): array {
	$wp_roles = wp_roles();
	$options  = [];

	foreach ( $wp_roles->roles as $role_key => $role ) {
		$options[ $role_key ] = translate_user_role( $role['name'] );
	}

	natcasesort( $options );

	return $options;
}

function yoohw_category_render_access_type_select( string $selected ): void {
	?>
	<select name="yoohw_access_type" id="yoohw_access_type" class="yoohw-access-type-select">
		<?php foreach ( yoohw_category_access_types() as $value => $label ) : ?>
			<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected, $value ); ?>>
				<?php echo esc_html( $label ); ?>
			</option>
		<?php endforeach; ?>
	</select>
	<?php
}

function yoohw_category_render_roles_select( array $selected_roles ): void {
	$selected_roles = yoohw_category_sanitize_access_roles( $selected_roles );
	?>
	<select
		name="yoohw_access_roles[]"
		id="yoohw_access_roles"
		class="yoohw-role-source-select"
		multiple="multiple"
		data-placeholder="<?php esc_attr_e( 'Select user roles', 'yoohw-support-portal' ); ?>"
	>
		<?php foreach ( yoohw_category_role_options() as $role_key => $role_label ) : ?>
			<option value="<?php echo esc_attr( $role_key ); ?>" <?php selected( in_array( $role_key, $selected_roles, true ) ); ?>>
				<?php echo esc_html( $role_label ); ?>
			</option>
		<?php endforeach; ?>
	</select>
	<div class="yoohw-role-select2" data-yoohw-role-select2 hidden>
		<div
			class="yoohw-role-select2-control"
			role="combobox"
			aria-haspopup="listbox"
			aria-expanded="false"
			tabindex="0"
		>
			<div class="yoohw-role-select2-values" aria-live="polite"></div>
			<input
				type="search"
				class="yoohw-role-select2-search"
				autocomplete="off"
				aria-label="<?php esc_attr_e( 'Search user roles', 'yoohw-support-portal' ); ?>"
			>
		</div>
		<div class="yoohw-role-select2-dropdown" role="listbox" hidden></div>
	</div>
	<?php
}

add_action( 'admin_enqueue_scripts', 'yoohw_category_access_enqueue_admin_assets' );
function yoohw_category_access_enqueue_admin_assets(): void {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( ! $screen || 'category' !== ( $screen->taxonomy ?? '' ) ) {
		return;
	}

	wp_enqueue_script( 'jquery' );
	wp_add_inline_script(
		'jquery',
		"(function($){
			function togglePanels() {
				var type = $('#yoohw_access_type').val() || 'access_code';
				$('[data-yoohw-access-panel]').each(function(){
					var active = $(this).attr('data-yoohw-access-panel') === type;
					$(this).toggle(active).attr('aria-hidden', active ? 'false' : 'true');
				});
			}

			function initRoleSelect2() {
				$('.yoohw-role-source-select').each(function(){
					var \$select = $(this);

					if (\$select.data('yoohwSelect2Ready')) {
						return;
					}

					var \$widget = \$select.next('[data-yoohw-role-select2]');
					var \$control = \$widget.find('.yoohw-role-select2-control');
					var \$values = \$widget.find('.yoohw-role-select2-values');
					var \$search = \$widget.find('.yoohw-role-select2-search');
					var \$dropdown = \$widget.find('.yoohw-role-select2-dropdown');
					var placeholder = \$select.data('placeholder') || 'Select user roles';

					\$select.data('yoohwSelect2Ready', true);
					\$select.addClass('yoohw-role-source-select-enhanced').attr('aria-hidden', 'true');
					\$widget.prop('hidden', false);
					\$search.attr('placeholder', placeholder);

					function getOption(value) {
						return \$select.find('option').filter(function(){
							return this.value === value;
						});
					}

					function selectedValues() {
						return \$select.val() || [];
					}

					function setSelected(value, selected) {
						getOption(value).prop('selected', selected);
						\$select.trigger('change');
					}

					function renderSelection() {
						var selected = \$select.find('option:selected');
						\$values.empty();

						if (!selected.length) {
							\$values.append($('<span/>', {
								'class': 'yoohw-role-select2-placeholder',
								text: placeholder
							}));
							\$search.attr('placeholder', '');
						} else {
							\$search.attr('placeholder', '');
							selected.each(function(){
								var \$option = $(this);
								var label = $.trim(\$option.text());

								$('<span/>', {
									'class': 'yoohw-role-select2-choice',
									'data-role': \$option.val()
								}).append($('<button/>', {
									type: 'button',
									'class': 'yoohw-role-select2-remove',
									'aria-label': 'Remove ' + label,
									text: 'x'
								})).append($('<span/>', {
									text: label
								})).appendTo(\$values);
							});
						}
					}

					function renderDropdown() {
						var term = $.trim(\$search.val()).toLowerCase();
						var selected = selectedValues();
						var count = 0;

						\$dropdown.empty();

						\$select.find('option').each(function(){
							var \$option = $(this);
							var label = $.trim(\$option.text());
							var value = \$option.val();
							var isSelected = selected.indexOf(value) !== -1;

							if (term && label.toLowerCase().indexOf(term) === -1) {
								return;
							}

							count++;

							$('<button/>', {
								type: 'button',
								'class': 'yoohw-role-select2-option' + (isSelected ? ' is-selected' : ''),
								'data-role': value,
								role: 'option',
								'aria-selected': isSelected ? 'true' : 'false',
								text: label
							}).appendTo(\$dropdown);
						});

						if (!count) {
							\$dropdown.append($('<div/>', {
								'class': 'yoohw-role-select2-empty',
								text: 'No matching roles.'
							}));
						}
					}

					function openDropdown() {
						\$control.attr('aria-expanded', 'true').addClass('is-open');
						\$dropdown.prop('hidden', false);
						renderDropdown();
						\$search.trigger('focus');
					}

					function closeDropdown() {
						\$control.attr('aria-expanded', 'false').removeClass('is-open');
						\$dropdown.prop('hidden', true);
						\$search.val('');
						renderDropdown();
					}

					\$control.on('click', function(){
						openDropdown();
					});

					\$control.on('keydown', function(event){
						if ('Escape' === event.key) {
							closeDropdown();
						}
					});

					\$search.on('input focus', function(){
						openDropdown();
						renderDropdown();
					});

					\$dropdown.on('mousedown', '.yoohw-role-select2-option', function(event){
						event.preventDefault();
					});

					\$dropdown.on('click', '.yoohw-role-select2-option', function(){
						var value = $(this).attr('data-role');
						var isSelected = selectedValues().indexOf(value) !== -1;

						setSelected(value, !isSelected);
						\$search.val('').trigger('focus');
						renderDropdown();
					});

					\$values.on('mousedown', '.yoohw-role-select2-remove', function(event){
						event.preventDefault();
						event.stopPropagation();
					});

					\$values.on('click', '.yoohw-role-select2-remove', function(event){
						event.preventDefault();
						event.stopPropagation();

						setSelected($(this).closest('.yoohw-role-select2-choice').attr('data-role'), false);
						openDropdown();
					});

					$(document).on('mousedown.yoohwRoleSelect2', function(event){
						if (!\$widget.is(event.target) && !\$widget.has(event.target).length) {
							closeDropdown();
						}
					});

					\$select.on('change', function(){
						renderSelection();
						renderDropdown();
					});

					renderSelection();
					renderDropdown();
				});
			}

			$(function(){
				togglePanels();
				initRoleSelect2();

				$(document).on('change', '#yoohw_access_type', togglePanels);
			});
		})(jQuery);"
	);

	wp_register_style( 'yoohw-category-access-admin', false, [], YOOHW_SUPPORT_PORTAL_VERSION );
	wp_enqueue_style( 'yoohw-category-access-admin' );
	wp_add_inline_style(
		'yoohw-category-access-admin',
		'.yoohw-access-type-select{min-width:25em;max-width:100%}.yoohw-role-source-select{min-width:25em;max-width:100%}.yoohw-role-source-select-enhanced{position:absolute!important;width:1px!important;height:1px!important;min-width:1px!important;overflow:hidden!important;clip:rect(1px,1px,1px,1px)!important;opacity:0!important}.yoohw-role-select2{position:relative;max-width:520px}.yoohw-role-select2-control{position:relative;display:flex;align-items:center;flex-wrap:wrap;gap:5px;min-height:36px;padding:4px 30px 4px 6px;border:1px solid #8c8f94;border-radius:4px;background:#fff;box-sizing:border-box;cursor:text}.yoohw-role-select2-control:after{content:"";position:absolute;right:10px;top:50%;width:0;height:0;margin-top:-2px;border-color:#50575e transparent transparent;border-style:solid;border-width:5px 4px 0}.yoohw-role-select2-control.is-open{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1}.yoohw-role-select2-values{display:flex;align-items:center;flex-wrap:wrap;gap:5px}.yoohw-role-select2-placeholder{color:#646970;padding:2px 4px}.yoohw-role-select2-choice{display:inline-flex;align-items:center;gap:5px;max-width:100%;min-height:24px;padding:2px 7px 2px 4px;border:1px solid #b8d4ff;border-radius:3px;background:#eef6ff;color:#1d4ed8;font-size:12px;font-weight:600;line-height:1.4}.yoohw-role-select2-remove{display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;margin:0;padding:0;border:0;background:transparent;color:#1d4ed8;cursor:pointer;font-size:13px;font-weight:700;line-height:1}.yoohw-role-select2-remove:hover,.yoohw-role-select2-remove:focus{color:#0f2f75}.yoohw-role-select2-search{flex:1 1 120px;min-width:90px;min-height:24px;margin:0!important;padding:2px!important;border:0!important;box-shadow:none!important;outline:0!important;background:transparent!important}.yoohw-role-select2-dropdown{position:absolute;z-index:100000;top:calc(100% + 3px);left:0;right:0;max-height:230px;overflow:auto;border:1px solid #8c8f94;border-radius:4px;background:#fff;box-shadow:0 8px 18px rgba(0,0,0,.12)}.yoohw-role-select2-option{position:relative;display:block;width:100%;margin:0;padding:8px 32px 8px 10px;border:0;border-radius:0;background:#fff;color:#1d2327;text-align:left;cursor:pointer}.yoohw-role-select2-option:hover,.yoohw-role-select2-option:focus{background:#f0f6fc;color:#0a4b78}.yoohw-role-select2-option.is-selected{background:#e7f2ff;color:#0a4b78;font-weight:600}.yoohw-role-select2-option.is-selected:after{content:"\\2713";position:absolute;right:10px;top:50%;transform:translateY(-50%);font-weight:700}.yoohw-role-select2-empty{padding:9px 10px;color:#646970}.yoohw-category-access-panel[aria-hidden=true]{display:none}'
	);
}
