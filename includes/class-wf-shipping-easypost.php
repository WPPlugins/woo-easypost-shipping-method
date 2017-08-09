<?php

/**
 * WF_USPS_Easypost class.
 *
 * @extends WC_Shipping_Method
 */
class WF_Easypost extends WC_Shipping_Method {

    private $domestic = array("US");
    private $found_rates;
	private $carrier_list = array(
		'USPS' => 'USPS',
		'FedEx' => 'FedEx',
		'UPS' => 'UPS'
	);

    /**
     * Constructor
     */
    public function __construct() {
        $this->id = WF_EASYPOST_ID;
        $this->method_title = __('EasyPost (BASIC)', 'wf-easypost');
        $this->method_description = __('The <strong>Easypost.com </strong> plugin obtains rates for USPS, UPS and FedEx dynamically from the Easypost.com API during cart/checkout.', 'wf-easypost');
        $this->services = include( 'data-wf-services.php' );
        $this->init();
    }

    /**
     * init function.
     *
     * @access public
     * @return void
     */
    private function init() {
        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->enabled              = isset($this->settings['enabled']) ? $this->settings['enabled'] : $this->enabled;
        $this->title                = isset($this->settings['title']) ? $this->settings['title'] : $this->method_title;
        $this->availability         = 'all';
        $this->zip                  = isset($this->settings['zip']) ? $this->settings['zip'] : '';
        $this->senderName                   = isset($this->settings['name']) ? $this->settings['name'] : '';
        $this->senderCompanyName            = isset($this->settings['company']) ? $this->settings['company'] : '';
        $this->senderAddressLine1           = isset($this->settings['street1']) ? $this->settings['street1'] : '';
        $this->senderAddressLine2           = isset($this->settings['street2']) ? $this->settings['street2'] : '';
        $this->senderCity                   = isset($this->settings['city']) ? $this->settings['city'] : '';
        $this->senderState                  = isset($this->settings['state']) ? $this->settings['state'] : '';
        $this->senderEmail                  = isset($this->settings['email']) ? $this->settings['email'] : '';
        $this->senderPhone                  = isset($this->settings['phone']) ? $this->settings['phone'] : '';
     
        $this->api_key                      = isset($this->settings['api_key']) ? $this->settings['api_key'] : WF_USPS_EASYPOST_ACCESS_KEY;
        $this->packing_method               = 'per_item';
        $this->custom_services              = isset($this->settings['services']) ? $this->settings['services'] : array();
        $this->offer_rates                  = isset($this->settings['offer_rates']) ? $this->settings['offer_rates'] : 'all';
        $this->fallback                     = !empty($this->settings['fallback']) ? $this->settings['fallback'] : '';
        $this->mediamail_restriction        = isset($this->settings['mediamail_restriction']) ? $this->settings['mediamail_restriction'] : array();
        $this->mediamail_restriction        = array_filter((array) $this->mediamail_restriction);
        $this->enable_standard_services     = true;
        
        $this->debug                  = isset($this->settings['debug_mode']) && $this->settings['debug_mode'] == 'yes' ? true : false;
        $this->api_mode               = isset($this->settings['api_mode']) ? $this->settings['api_mode'] : 'Live';
		$this->carrier = (isset($this->settings['easypost_carrier']) && !empty($this->settings['easypost_carrier']))? $this->settings['easypost_carrier'] : array('USPS');
        
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'clear_transients'));
    }

    /**
     * environment_check function.
     *
     * @access public
     * @return void
     */
    private function environment_check() {
        global $woocommerce;

        $admin_page = version_compare(WOOCOMMERCE_VERSION, '2.1', '>=') ? 'wc-settings' : 'woocommerce_settings';

        if (get_woocommerce_currency() != "USD") {
            echo '<div class="error">
				<p>' . sprintf(__('Easypost.com requires that the <a href="%s">currency</a> is set to US Dollars.', 'wf-easypost'), admin_url('admin.php?page=' . $admin_page . '&tab=general')) . '</p>
			</div>';
        } elseif (!in_array($woocommerce->countries->get_base_country(), $this->domestic)) {
            echo '<div class="error">
				<p>' . sprintf(__('Easypost.com requires that the <a href="%s">base country/region</a> is the United States.', 'wf-easypost'), admin_url('admin.php?page=' . $admin_page . '&tab=general')) . '</p>
			</div>';
        } elseif (!$this->zip && $this->enabled == 'yes') {
            echo '<div class="error">
				<p>' . __('Easypost.com is enabled, but the zip code has not been set.', 'wf-easypost') . '</p>
			</div>';
        }

        $error_message = '';

        // Check for Easypost.com APIKEY
        if (!$this->api_key && $this->enabled == 'yes') {
            $error_message .= '<p>' . __('Easypost.com is enabled, but the Easypost.com API KEY has not been set.', 'wf-easypost') . '</p>';
        }

        if (!$error_message == '') {
            echo '<div class="error">';
            echo $error_message;
            echo '</div>';
        }
    }

    /**
     * admin_options function.
     *
     * @access public
     * @return void
     */
    public function admin_options() {
        // Check users environment supports this method
        $this->environment_check();

		?>
		<div class="wf-banner updated below-h2">
  			<p class="main">
			<ul>
				<li style='color:red;'><strong>Your Business is precious! Go Premium!</li></strong>
				<li><strong>WooForce EasyPost Premium version for WooCommerce streamlines your complete shipping process and saves time.</strong></li>
				<li><strong>- Timely compatibility updates and bug fixes.</strong ></li>
				<li><strong>- Premium Support:</strong> Faster and time bound response for support requests.</li>
				<li><strong>- More Features:</strong> Label Printing with Postage, Automatic Shipment Tracking, Box Packing and Many More..</li>
			</ul>
			</p>
			<p><a href="https://www.xadapter.com/product/woocommerce-easypost-shipping-method-plugin/" target="_blank" class="button button-primary">Upgrade to Premium Version</a> <a href="http://easypostwoodemo.wooforce.com/wp-admin/admin.php?page=wc-settings&tab=shipping&section=wf_easypost" target="_blank" class="button">Live Demo</a></p>
		</div>
		<style>
		.wf-banner img {
			float: right;
			margin-left: 1em;
			padding: 15px 0
		}
		</style>
		<?php
		
        // Show settings
        parent::admin_options();
    }

    /**
     * generate_services_html function.
     */
    public function generate_services_html() {
        ob_start();
        include( 'html-wf-services.php' );
        return ob_get_clean();
    }

    /**
     * validate_services_field function.
     *
     * @access public
     * @param mixed $key
     * @return void
     */
    public function validate_services_field($key) {
        $services           = array();
        $posted_services    = $_POST['easypost_service'];

        foreach ($posted_services as $code => $settings) {

            foreach ($this->services[$code]['services'] as $key => $name) {

                $services[$code][$key]['enabled']               = isset($settings[$key]['enabled']) ? true : false;
                $services[$code][$key]['order']                 = wc_clean($settings[$key]['order']);
            
            }
        }

        return $services;
    }

    /**
     * clear_transients function.
     *
     * @access public
     * @return void
     */
    public function clear_transients() {
        global $wpdb;

        $wpdb->query("DELETE FROM `$wpdb->options` WHERE `option_name` LIKE ('_transient_easypost_quote_%') OR `option_name` LIKE ('_transient_timeout_easypost_quote_%')");
    }

    /**
     * init_form_fields function.
     *
     * @access public
     * @return void
     */
    public function init_form_fields() {
        global $woocommerce;

        $shipping_classes = array();
        $classes = ( $classes = get_terms('product_shipping_class', array('hide_empty' => '0')) ) ? $classes : array();

        foreach ($classes as $class)
            $shipping_classes[$class->term_id] = $class->name;

        if (WF_EASYPOST_ADV_DEBUG_MODE == "on") { // Test mode is only for development purpose.
            $api_mode_options = array(
                'Live' => __('Live', 'wf-easypost'),
                'Test' => __('Test', 'wf-easypost'),
            );
        } else {
            $api_mode_options = array(
                'Live' => __('Live', 'wf-easypost'),
            );
        }

        $this->form_fields = array(
            'enabled'               => array(
                'title'             => __('Realtime Rates', 'wf-easypost'),
                'type'              => 'checkbox',
                'label'             => __('Enable', 'wf-easypost'),
                'description'       => __( 'Enable realtime rates on Cart/Checkout page.', 'wf-easypost' ),
                'default'           => 'no',
                'desc_tip'          => true,
            ),
            'title'                 => array(
                'title'             => __('Method Title', 'wf-easypost'),
                'type'              => 'text',
                'description'       => __('This controls the title which the user sees during checkout.', 'wf-easypost'),
                'default'           => __($this->method_title, 'wf-easypost'),
                'placeholder'       => __($this->method_title, 'wf-easypost'),
                'desc_tip'          => true,
            ),
			'easypost_carrier'       => array(
				'title'           => __( 'Easypost Carrier', 'wf-easypost' ),
				'type'            => 'multiselect',
				'description'	  => __( 'Select your Easypost Carriers.', 'wf-easypost' ),
				'default'         => array(),
				'css'			  => 'width: 450px;',
				'class'           => 'ups_packaging chosen_select',
				'options'         => $this->carrier_list,
                'desc_tip'        => true
			),
            'debug_mode'            => array(
                'title'             => __('Debug Mode', 'wf-easypost'),
                'label'             => __('Enable', 'wf-easypost'),
                'type'              => 'checkbox',
                'default'           => 'no',
                'description'       => __('Enable debug mode to show debugging information on your cart/checkout. Not recommended to enable this in live site with traffic.', 'wf-easypost'),
                'desc_tip'          => true,
            ),
            'api'                   => array(
                'title'             => __('Generic API Settings:', 'wf-easypost'),
                'type'              => 'title',
                'description'       => sprintf(__('You can obtain a Easypost.com API Key by %s.', 'wf-easypost'), '<a href="http://www.easypost.com">' . __('signing up on the Easypost.com website', 'wf-easypost') . '</a>'),
            ),
            'api_key'               => array(
                'title'             => __('API KEY', 'wf-easypost'),
                'type'              => 'password',
                'description'       => __('Obtained from <a href="http://www.easypost.com" target="_blank">www.easypost.com</a> after getting an account.', 'wf-easypost'),
                'default'           => '',
                'desc_tip'          => true,
            ),
            'api_mode'              => array(
                'title'             => __('API Mode', 'wf-easypost'),
                'type'              => 'select',
                'default'           => 'Live',
                'options'           => $api_mode_options,
                'description'       => __('Live mode is the strict choice for Customers as Test mode is strictly restricted for development purpose by Easypost.com.', 'wf-easypost'),
                'desc_tip'          => true,
            ),
            'name' => array(
                'title'             => __('Sender Name', 'wf-easypost'),
                'type'              => 'text',
                'description'       => __('Enter your name.', 'wf-easypost'),
                'default'           => '',
                'desc_tip'          => true,
            ),
            'company'               => array(
                'title'             => __('Sender Company Name', 'wf-easypost'),
                'type'              => 'text',
                'description'       => __('Enter your company name.', 'wf-easypost'),
                'default'           => '',
                'desc_tip'          => true,
            ),
            'street1'               => array(
                'title'             => __('Sender Address Line1', 'wf-easypost'),
                'type'              => 'text',
                'description'       => __('Enter your address line 1.', 'wf-easypost'),
                'default'           => '',
                'desc_tip'          => true,
            ),
            'street2'               => array(
                'title'             => __('Sender Address Line2', 'wf-easypost'),
                'type'              => 'text',
                'description'       => __('Enter your address line 2.', 'wf-easypost'),
                'default'           => '',
                'desc_tip'          => true,
            ),
            'city'                  => array(
                'title'             => __('Sender City', 'wf-easypost'),
                'type'              => 'text',
                'description'       => __('Enter your city.', 'wf-easypost'),
                'default'           => '',
                'desc_tip'          => true,
                
            ),
            'state'                 => array(
                'title'             => __('Sender State', 'wf-easypost'),
                'type'              => 'text',
                'description'       => __('Enter state short code (Eg: CA).', 'wf-easypost'),
                'default'           => '',
                'desc_tip'          => true,
            ),
            'email'                 => array(
                'title'             => __('Sender Email', 'wf-easypost'),
                'type'              => 'email',
                'description'       => __('Enter sender email', 'wf-easypost'),
                'default'           => '',
                'desc_tip'          => true,
            ),
            'zip'                   => array(
                'title'             => __('Zip Code', 'wf-easypost'),
                'type'              => 'text',
                'description'       => __('Enter the postcode for the sender', 'wf-easypost'),
                'default'           => '',
                'desc_tip'          => true,
            ),
            'phone'                 => array(
                'title'             => __('Sender Phone', 'wf-easypost'),
                'type'              => 'text',
                'description'       => __('Enter sender phone', 'wf-easypost'),
                'default'           => '',
                'desc_tip'          => true,
            ),
            'customs_description'   => array(
                'title'             => __('Customs Description', 'wf-easypost'),
                'type'              => 'textarea',
                'description'       => __('Product description for International shipping.', 'wf-easypost'),
                'default'           => '',
				'css'				=> 'width:39%;',
                'desc_tip'          => true,
            ),
            'services'              => array(
                'type'              => 'services',
            ),
            'fallback'              => array(
                'title'             => __('Fallback', 'wf-easypost'),
                'type'              => 'text',
                'description'       => __('If Easypost.com returns no matching rates, offer this amount for shipping so that the user can still checkout. Leave blank to disable.', 'wf-easypost'),
                'default'           => '',
                'desc_tip'          => true,
            ),
        );
    }
   

    /**
     * calculate_shipping function.
     *
     * @access public
     * @param mixed $package
     * @return void
     */
    public function calculate_shipping($package=array()) {
        global $woocommerce;

        $this->rates = array();
        $this->unpacked_item_costs = 0;
        $domestic = in_array($package['destination']['country'], $this->domestic) ? true : false;

        $this->debug(__('Easypost.com debug mode is on - to hide these messages, turn debug mode off in the settings.', 'wf-easypost'));

        if ($this->enable_standard_services) {
            // Get cart package details and proceed with GetRates.
            $package_requests = $this->get_package_requests($package);
            libxml_use_internal_errors(true);
            if ($package_requests) {

                require_once(plugin_dir_path(dirname(__FILE__)) . "/easypost.php");
                \EasyPost\EasyPost::setApiKey($this->settings['api_key']);

                $responses = array();
                foreach ($package_requests as $key => $package_request) {
 
                    // Get rates.
                    try {
                        $payload = array();
                        $payload['from_address'] = array(
                                        "name"    => $this->senderName,
                                        "company" => $this->senderCompanyName,
                                        "street1" => $this->senderAddressLine1,
                                        "street2" => $this->senderAddressLine2,
                                        "city"    => $this->senderCity,
                                        "state"   => $this->senderState,
                                        "zip"     => $this->zip,
                                        "phone"   => $this->senderPhone
                                    );
                        $payload['to_address'] =  array(
                                        // Name and Street1 are required fields for getting rates.
                                        // But, at this point, these details are not available.
                                        "name"    => "WooForce",
                                        "street1" => "-",
                                        "zip"     => $package_request['request']['Rate']['ToZIPCode'],
                                        "country" => $package_request['request']['Rate']['ToCountry']
                                    );
                        
                        if(!empty($package_request['request']['Rate']['WeightLb']) && $package_request['request']['Rate']['WeightOz'] == 0.00) {
                            $package_request['request']['Rate']['WeightOz'] = number_format($package_request['request']['Rate']['WeightLb']*16);
                        }
                            
                        $payload['parcel'] = array(
                                        'length'  => $package_request['request']['Rate']['Length'],
                                        'width'   => $package_request['request']['Rate']['Width'],
                                        'height'  => $package_request['request']['Rate']['Height'],
                                        'weight'  => $package_request['request']['Rate']['WeightOz']
                        );
						
						$payload['options'] = array(
								"special_rates_eligibility" => 'USPS.LIBRARYMAIL'
						);
                        
                        $shipment           = \EasyPost\Shipment::create($payload);
                        $shipment_data      = $shipment->get_rates();
                        $response           = json_decode($shipment_data);
                        $response_ele       = array();
                        
                        $response_ele['response'] = $response;
                        $response_ele['quantity'] = $package_request['quantity'];
                        $responses[] = $response_ele;
                    } catch (Exception $e) {
                        $this->debug(__('Easypost.com - Unable to Get Rates: ', 'wf-easypost') . $e->getMessage());
                        if (WF_EASYPOST_ADV_DEBUG_MODE == "on") {
                            $this->debug(print_r($e, true));
                        }
                        return false;
                    }
                }

                $found_rates = array();

                foreach ($responses as $response_ele) {
                    $response_obj = $response_ele['response'];
                    if ( isset($response_obj->rates) && !empty($response_obj->rates) ) {
						foreach ($this->carrier as $carrier_name) {
							foreach ($response_obj->rates as $easypost_rate) {
								if($carrier_name == $easypost_rate->carrier) {
									$service_type       = (string) $easypost_rate->service;
									$service_name = (string) ( isset($this->custom_services[$carrier_name][$service_type]['name']) && !empty($this->custom_services[$carrier_name][$service_type]['name']) ) ? $this->custom_services[$carrier_name][$service_type]['name'] :$this->services[$carrier_name]['services'][$service_type];
									$total_amount       = $response_ele['quantity'] * $easypost_rate->rate;

									if (isset($found_rates[$service_type])) {
										$found_rates[$service_type]['cost']     = $found_rates[$service_type]['cost'] + $total_amount;
									} else {
										$found_rates[$service_type]['label']    = $service_name;
										$found_rates[$service_type]['cost']     = $total_amount;
										$found_rates[$service_type]['carrier'] = $easypost_rate->carrier;
									}
								}
							}
						}
                    } else {

                        $this->debug(__('Easypost.com - Unknown error while processing Rates.', 'wf-easypost'));
                        return;
                    }
                }

                if ($found_rates) {
					foreach ($this->carrier as $carrier_name) {
						foreach ($found_rates as $service_type => $found_rate) {
							// Enabled check
							if($carrier_name == $found_rate['carrier']) {
								if (isset($this->custom_services[$carrier_name][$service_type]) && empty($this->custom_services[$carrier_name][$service_type]['enabled'])) {
									continue;
								}							
								$total_amount = $found_rate['cost'];
								// Cost adjustment %
								if (!empty($this->custom_services[$carrier_name][$service_type]['adjustment_percent'])) {
									$total_amount = $total_amount + ( $total_amount * ( floatval($this->custom_services[$carrier_name][$service_type]['adjustment_percent']) / 100 ) );
								}
								// Cost adjustment
								if (!empty($this->custom_services[$service_type][$service_type]['adjustment'])) {
									$total_amount = $total_amount + floatval($this->custom_services[$carrier_name][$service_type]['adjustment']);
								}
								$labelName = !empty( $this->settings['services'][$carrier_name][$service_type]['name'] ) 
												? $this->settings['services'][$carrier_name][$service_type]['name']
												: $this->services[$carrier_name]['services'][$service_type];
								$rate = array(
									'id'        => (string) $this->id . ':' . $service_type,
									'label'     =>  (string)$labelName,
									'cost'      => (string) $total_amount,
									'calc_tax'  => 'per_item',
								);
								// Register the rate
								$this->add_rate($rate);
							}
						}
					}
                }
                // Fallback
                elseif ($this->fallback) {
                    $this->add_rate(array(
                        'id'        => $this->id . '_fallback',
                        'label'     => $this->title,
                        'cost'      => $this->fallback,
                        'sort'      => 0
                    ));
                }
            }
        }
    }

    /**
     * prepare_rate function.
     *
     * @access private
     * @param mixed $rate_code
     * @param mixed $rate_id
     * @param mixed $rate_name
     * @param mixed $rate_cost
     * @return void
     */
    private function prepare_rate($rate_code, $rate_id, $rate_name, $rate_cost) {

        // Name adjustment
        if (!empty($this->custom_services[$rate_code]['name']))
            $rate_name = $this->custom_services[$rate_code]['name'];

        // Merging
        if (isset($this->found_rates[$rate_id])) {
            $rate_cost = $rate_cost + $this->found_rates[$rate_id]['cost'];
            $packages = 1 + $this->found_rates[$rate_id]['packages'];
        } else {
            $packages = 1;
        }

        // Sort
        if (isset($this->custom_services[$rate_code]['order'])) {
            $sort = $this->custom_services[$rate_code]['order'];
        } else {
            $sort = 999;
        }

        $this->found_rates[$rate_id] = array(
            'id'        => $rate_id,
            'label'     => $rate_name,
            'cost'      => $rate_cost,
            'sort'      => $sort,
            'packages'  => $packages
        );
    }

    /**
     * sort_rates function.
     *
     * @access public
     * @param mixed $a
     * @param mixed $b
     * @return void
     */
    public function sort_rates($a, $b) {
        if ($a['sort'] == $b['sort'])
            return 0;
        return ( $a['sort'] < $b['sort'] ) ? -1 : 1;
    }

    /**
     * get_request function.
     *
     * @access private
     * @return void
     */
    // WF - Changing function to public.
    public function get_package_requests($package) {

        // Choose selected packing
        switch ($this->packing_method) {
            case 'box_packing' :
                $requests = $this->box_shipping($package);
                break;
            case 'per_item' :
            default :
                $requests = $this->per_item_shipping($package);
                break;
        }

        return $requests;
    }

    /**
     * per_item_shipping function.
     *
     * @access private
     * @param mixed $package
     * @return void
     */
    private function per_item_shipping($package) {
        global $woocommerce;
       
        $requests = array();    
        $domestic = in_array($package['destination']['country'], $this->domestic) ? true : false;

        // Get weight of order
        foreach ($package['contents'] as $item_id => $values) {
            $values['data'] = $this->wf_load_product( $values['data'] );

            if (!$values['data']->needs_shipping()) {
                $this->debug(sprintf(__('Product # is virtual. Skipping.', 'wf-easypost'), $item_id));
                continue;
            }

            if (!$values['data']->get_weight()) {
                $this->debug(sprintf(__('Product # is missing weight. Using 1lb.', 'wf-easypost'), $item_id));

                $weight = 1;
                $weightoz = 1; // added for default
            } else {
                $weight = wc_get_weight($values['data']->get_weight(), 'lbs');
                $weightoz = wc_get_weight($values['data']->get_weight(), 'oz');
            }

            $size = 'REGULAR';

            if ($values['data']->length && $values['data']->height && $values['data']->width) {

                $dimensions = array(wc_get_dimension($values['data']->length, 'in'), wc_get_dimension($values['data']->height, 'in'), wc_get_dimension($values['data']->width, 'in'));

                sort($dimensions);

                if (max($dimensions) > 12) {
                    $size = 'LARGE';
                }

                $girth = $dimensions[0] + $dimensions[0] + $dimensions[1] + $dimensions[1];
            } else {
                $dimensions = array(0, 0, 0);
                $girth = 0;
            }

            $quantity = $values['quantity'];

            if ('LARGE' === $size) {
                $rectangular_shaped = 'true';
            } else {
                $rectangular_shaped = 'false';
            }
            if ($domestic) {
                $request['Rate'] = array(
                    'FromZIPCode'       => str_replace(' ', '', strtoupper($this->settings['zip'])),
                    'ToZIPCode'         => strtoupper(substr($package['destination']['postcode'], 0, 5)),
					'ToCountry'         => $package['destination']['country'],
                    'WeightLb'          => floor($weight),
                    'WeightOz'          => number_format($weightoz),
                    'PackageType'       => 'Package',
                    'Length'            => $dimensions[2],
                    'Width'             => $dimensions[1],
                    'Height'            => $dimensions[0],
                    'ShipDate'          => date("Y-m-d", ( current_time('timestamp') + (60 * 60 * 24))),
                    'InsuredValue'      => '0',
                    'RectangularShaped' => $rectangular_shaped
                );
            } else {
                $request['Rate'] = array(
                    'FromZIPCode'       => str_replace(' ', '', strtoupper($this->settings['zip'])),
                    'ToZIPCode'         => $package['destination']['postcode'],
                    'ToCountry'         => $package['destination']['country'],
                    'Amount'            => $values['data']->get_price(),
                    'WeightLb'          => floor($weight),
                    'WeightOz'          => number_format($weightoz),
                    'PackageType'       => 'Package',
                    'Length'            => $dimensions[2],
                    'Width'             => $dimensions[1],
                    'Height'            => $dimensions[0],
                    'ShipDate'          => date("Y-m-d", ( current_time('timestamp') + (60 * 60 * 24))),
                    'InsuredValue'      => '0',
                    'RectangularShaped' => $rectangular_shaped
                );
            }

            $request_ele                = array();
            $request_ele['request']     = $request;
            $request_ele['quantity']    = $quantity;

            $requests[]                 = $request_ele;
        }
        return $requests;
    }

    /**
     * Generate a package ID for the request
     *
     * Contains qty and dimension info so we can look at it again later when it comes back from USPS if needed
     *
     * @return string
     */
    public function generate_package_id($id, $qty, $length, $width, $height, $weight) {
        return implode(':', array($id, $qty, $length, $width, $height, $weight));
    }

    public function debug($message, $type = 'notice') {
        if ($this->debug && !is_admin()) { //WF: is_admin check added.
            if (version_compare(WOOCOMMERCE_VERSION, '2.1', '>=')) {
                wc_add_notice($message, $type);
            } else {
                global $woocommerce;
                $woocommerce->add_message($message);
            }
        }
    }

    private function wf_load_product( $product ){
        if( !$product ){
            return false;
        }
        return ( WC()->version < '2.7.0' ) ? $product : new wf_product( $product );
    }
}
