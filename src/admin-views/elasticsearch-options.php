<?php
unset( $settings['fields']['tribe_community_events_wrapper_closer'] );

$settings['fields']['tickets-heading'] = array(
	'type' => 'html',
	'html' => '<h3>' . esc_html__( 'Community Tickets', 'tribe-events-elasticsearch' ) . '</h3>',
);

$settings['fields']['enable_community_tickets'] = array(
	'type' => 'checkbox_bool',
	'label' => esc_html__( 'Enable Community Tickets', 'tribe-events-elasticsearch' ),
	'tooltip' => esc_html__( 'Check this box if you wish to turn on Community Tickets functionality', 'tribe-events-elasticsearch' ),
	'default' => false,
	'validation_type' => 'boolean',
	'parent_option' => self::OPTIONNAME,
);

$settings['fields']['edit_event_tickets_cap'] = array(
	'type' => 'checkbox_bool',
	'label' => esc_html__( 'Allow any user to create tickets', 'tribe-events-elasticsearch' ),
	'tooltip' => __( 'Check this box if you wish all Subscribers to receive the ability to create tickets. Uncheck it if you will be altering the <code>edit_event_tickets</code> capability either via a plugin or custom filter.', 'tribe-events-elasticsearch' ),
	'default' => true,
	'validation_type' => 'boolean',
	'parent_option' => self::OPTIONNAME,
);

$settings['fields']['enable_image_uploads'] = array(
	'type' => 'checkbox_bool',
	'label' => esc_html__( 'Enable ticket images', 'tribe-events-elasticsearch' ),
	'tooltip' => esc_html__( 'Check this box if you wish to allow community organizers to upload images for their tickets.', 'tribe-events-elasticsearch' ),
	'default' => false,
	'validation_type' => 'boolean',
	'parent_option' => self::OPTIONNAME,
);

$settings['fields']['purchase_limit'] = array(
	'type' => 'text',
	'label' => esc_html__( 'Default purchase limit', 'tribe-events-elasticsearch' ),
	'tooltip' => esc_html__( 'The maximum number of tickets per order. Leaving this blank or setting to 0 means there is no limit. Once a limit greater than 0 has been set, this value can be overridden when tickets are created.', 'tribe-events-elasticsearch' ),
	'default' => '0',
	'validation_type' => 'positive_int',
	'parent_option' => self::OPTIONNAME,
	'can_be_empty' => true,
);

$settings['fields']['site_fee_type'] = array(
	'type' => 'dropdown',
	'label' => esc_html__( 'Per-ticket fee type', 'tribe-events-elasticsearch' ),
	'tooltip' => esc_html__( 'What type of fee will be charged on a per-ticket basis?', 'tribe-events-elasticsearch' ),
	'default' => 'none',
	'validation_type' => 'options',
	'options' => array(
		'none' => esc_html__( 'None', 'tribe-events-elasticsearch' ),
		'flat' => esc_html__( 'Flat fee', 'tribe-events-elasticsearch' ),
		'percentage' => esc_html__( 'Percentage', 'tribe-events-elasticsearch' ),
		'flat-and-percentage' => esc_html__( 'Flat fee & percentage', 'tribe-events-elasticsearch' ),
	),
	'parent_option' => self::OPTIONNAME,
	'can_be_empty' => false,
);

$settings['fields']['site_fee_percentage'] = array(
	'type' => 'text',
	'label' => esc_html__( 'Fee percentage', 'tribe-events-elasticsearch' ),
	'tooltip' => esc_html__( 'The percentage fee charged to each ticket transaction.', 'tribe-events-elasticsearch' ),
	'default' => '',
	'validation_type' => 'positive_decimal_or_percent',
	'parent_option' => self::OPTIONNAME,
	'can_be_empty' => true,
);

$settings['fields']['site_fee_flat'] = array(
	'type' => 'text',
	'label' => esc_html__( 'Flat fee', 'tribe-events-elasticsearch' ),
	'tooltip' => esc_html__( 'The flat fee charged for each event in an order.', 'tribe-events-elasticsearch' ),
	'default' => '',
	'validation_type' => 'positive_decimal',
	'parent_option' => self::OPTIONNAME,
	'can_be_empty' => true,
);

$settings['fields']['payment_fee_setting'] = array(
	'type' => 'radio',
	'label' => esc_html__( 'Fee option defaults:', 'tribe-events-elasticsearch' ),
	'default' => 'absorb',
	'validation_type' => 'options',
	'options' => array(
		'absorb' => esc_html__( 'Include fees in ticket price', 'tribe-events-elasticsearch' ) .
			// spacing in front of the paragraph is intentional to make the auto-generated tooltip look OK
			' <p class="description tribe-style-selection">' .
				esc_html__( 'Fees will be subtracted from the cost of the ticket', 'tribe-events-elasticsearch' ) .
			'</p>',
		'pass' => esc_html__( 'Display fees in addition to subtotal on the Cart page', 'tribe-events-elasticsearch' ) .
			// spacing in front of the paragraph is intentional to make the auto-generated tooltip look OK
			' <p class="description tribe-style-selection">' .
				esc_html__( 'Additional fees will be added to the total ticket price', 'tribe-events-elasticsearch' ) .
			'</p>',
	),
	'parent_option' => self::OPTIONNAME,
	'can_be_empty' => false,
);

$settings['fields']['enable_split_payments'] = array(
	'type' => 'checkbox_bool',
	'label' => esc_html__( 'Enable split payments', 'tribe-events-elasticsearch' ),
	'tooltip' => sprintf(
		esc_html__(
			'Leaving this box unchecked means that all funds will go to the PayPal account configured in WooCommerce.
			Check this box if you wish money for tickets to be distributed to the site and the event
			organizer at the time of ticket purchasing. You will need a PayPal %1$sdeveloper account%2$s.
			Note: when split payments are enabled, ticket creators can override the "Fee option defaults".
			%3$s
			',
			'tribe-events-elasticsearch'
		),
		'<a href="https://developer.paypal.com/" target="_blank">',
		'</a>',
		'<a href="http://m.tri.be/18lt">' . esc_html__( 'Read more' ) . '</a>'
	),
	'default' => false,
	'validation_type' => 'boolean',
	'parent_option' => self::OPTIONNAME,
);

$settings['fields']['paypal-settings-start'] = array(
	'type' => 'html',
	'html' => '<div id="tribe-events-elasticsearch-paypal-settings">',
);

$settings['fields']['tickets-blurb'] = array(
	'type' => 'html',
	'html' => '<p>' . esc_html__( 'The following PayPal settings are required for enabling split payments.', 'tribe-events-elasticsearch' ) . '</p>',
);

$settings['fields']['paypal_sandbox'] = array(
	'type' => 'checkbox_bool',
	'label' => esc_html__( 'Use PayPal sandbox', 'tribe-events-elasticsearch' ),
	'tooltip' => esc_html__( 'Check this box if you wish all payments to be test payments via PayPal sandbox.', 'tribe-events-elasticsearch' ),
	'default' => false,
	'validation_type' => 'boolean',
	'parent_option' => self::OPTIONNAME,
);

$settings['fields']['paypal_api_username'] = array(
	'type' => 'text',
	'label' => esc_html__( 'PayPal API username', 'tribe-events-elasticsearch' ),
	'default' => '',
	'validation_type' => 'html',
	'parent_option' => self::OPTIONNAME,
	'can_be_empty' => true,
);

$settings['fields']['paypal_api_password'] = array(
	'type' => 'text',
	'label' => esc_html__( 'PayPal API password', 'tribe-events-elasticsearch' ),
	'default' => '',
	'validation_type' => 'html',
	'parent_option' => self::OPTIONNAME,
	'can_be_empty' => true,
);

$settings['fields']['paypal_api_signature'] = array(
	'type' => 'text',
	'label' => esc_html__( 'PayPal API signature', 'tribe-events-elasticsearch' ),
	'default' => '',
	'validation_type' => 'alpha_numeric_multi_line_with_dots_and_dashes',
	'parent_option' => self::OPTIONNAME,
	'can_be_empty' => true,
);

$settings['fields']['paypal_application_id'] = array(
	'type' => 'text',
	'label' => esc_html__( 'PayPal Application ID', 'tribe-events-elasticsearch' ),
	'default' => '',
	'validation_type' => 'html',
	'parent_option' => self::OPTIONNAME,
	'can_be_empty' => true,
);

$settings['fields']['paypal_receiver_email'] = array(
	'type' => 'text',
	'label' => esc_html__( 'Receiver email', 'tribe-events-elasticsearch' ),
	'tooltip' => esc_html__( 'This is the email address for the PayPal account that will receive payments.', 'tribe-events-elasticsearch' ),
	'default' => '',
	'validation_type' => 'html',
	'parent_option' => self::OPTIONNAME,
	'can_be_empty' => true,
);

$settings['fields']['paypal_invoice_prefix'] = array(
	'type' => 'text',
	'label' => esc_html__( 'Invoice prefix', 'tribe-events-elasticsearch' ),
	'tooltip' => esc_html__( 'Enter a prefix for your PayPal invoices. If you run multiple stores/event sites, entering a value here will ensure that your invoice numbers are unique - PayPal does not accept duplicate invoice numbers.', 'tribe-events-elasticsearch' ),
	'default' => 'CT-',
	'validation_type' => 'html',
	'parent_option' => self::OPTIONNAME,
	'can_be_empty' => true,
);

$settings['fields']['paypal-settings-end'] = array(
	'type' => 'html',
	'html' => '</div>',
);

$settings['fields']['tribe_community_events_wrapper_closer'] = array(
	'type' => 'html',
	'html' => '</div>',
);
