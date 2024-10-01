<?php
/**
 * Class Flashyapp_Elementor
 * @see https://developers.elementor.com/custom-form-action/
 * Custom elementor form action after submit to add a subsciber to
 * Sendy list via API 
 */

use ElementorPro\Modules\Forms\Controls\Fields_Map;
use Flashy\Helper;

class Flashyapp_Elementor extends \ElementorPro\Modules\Forms\Classes\Action_Base {
	/**
	 * Get Name
	 *
	 * Return the action name
	 *
	 * @access public
	 * @return string
	 */
	public function get_name() {
		return 'flashyapp';
	}

	/**
	 * Get Label
	 *
	 * Returns the action label
	 *
	 * @access public
	 * @return string
	 */
	public function get_label() {
		return __( 'Flashy', 'flashy' );
	}

	/**
	 * Run
	 *
	 * Runs the action after submit
	 *
	 * @access public
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record $record
	 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler
	 */
	public function run( $record, $ajax_handler ) {
		$settings = $record->get( 'form_settings' );

		// Get sumitetd Form data
		$raw_fields = $record->get( 'fields' );

		// Normalize the Form Data
		$fields = [];

		foreach ( $raw_fields as $id => $field ) {
			$fields[ $id ] = $field['value'];
		}

		if( !isset($fields['email']) || !filter_var($fields['email'], FILTER_VALIDATE_EMAIL) )
			return;

		if( !isset($settings['flashy_list']) )
			return;

		$list_id = $settings['flashy_list'];

		$create = Helper::tryOrLog( function () use ($fields, $list_id) {
			return flashy()->api->contacts->subscribe($fields, $list_id, 'email');
		});

		flashy_log($create);

		return true;
	}

	/**
	 * Register Settings Section
	 *
	 * Registers the Action controls
	 *
	 * @access public
	 * @param \Elementor\Widget_Base $widget
	 */
	public function register_settings_section( $widget )
	{
		$widget->start_controls_section(
			'section_flashy',
			[
				'label' => __( 'Flashy', 'flashy' ),
				'condition' => [
					'submit_actions' => $this->get_name(),
				],
			]
		);

		if( ! get_option("flashy_key") )
		{
			$html = sprintf( __( 'Your Flashy API key is missing, click here to add it <a href="%1$s" target="_blank">Add API Key</a>.', 'flashy' ), get_admin_url() . "admin.php?page=flashy" );
			$content_classes = 'elementor-panel-alert elementor-panel-alert-warning';

			$widget->add_control(
				'_api_key_msg',
				[
					'type' => \Elementor\Controls_Manager::RAW_HTML,
					'raw' => $html,
					'content_classes' => $content_classes,
				]
			);
		}
		else
		{
			$lists = flashy()->lists();

			$widget->add_control(
				'flashy_list',
				[
					'label' => __( 'List', 'flashy' ),
					'type' => \Elementor\Controls_Manager::SELECT,
					'options' => $lists,
				]
			);

			$html = sprintf( __( 'Please make sure the form fields (inputs) are with the correct name on your Flashy account <a href="%1$s" target="_blank">Full Guide</a>.', 'flashy' ), get_admin_url() . "admin.php?page=flashy" );
			$content_classes = 'elementor-panel-alert elementor-panel-alert-warning';

			$widget->add_control(
				'_api_key_msg',
				[
					'type' => \Elementor\Controls_Manager::RAW_HTML,
					'raw' => $html,
					'content_classes' => $content_classes,
				]
			);
		}

		$widget->end_controls_section();
	}

	public function on_export( $element ) {
		return $element;
	}

}

