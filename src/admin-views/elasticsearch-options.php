<?php
$settings['fields']['events-elasticsearch-heading'] = array(
	'type' => 'html',
	'html' => '<h3>' . esc_html__( 'Elasticsearch Integration', 'tribe-events-elasticsearch' ) . '</h3>',
);

$settings['fields']['enable_events_elasticsearch'] = array(
	'type' => 'checkbox_bool',
	'label' => esc_html__( 'Enable Elasticsearch Integration', 'tribe-events-elasticsearch' ),
	'tooltip' => esc_html__( 'Check this box if you wish to turn on Elasticsearch Integration functionality', 'tribe-events-elasticsearch' ),
	'default' => false,
	'validation_type' => 'boolean',
	'parent_option' => self::OPTIONNAME,
);
