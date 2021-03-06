<?php
date_default_timezone_set('America/New_York');
/**
 * TODO
 *
 * - Get site name from WP
 * - Build some kind of email builder
 * - Build some kind of validation system which will make an K->V array of Label / Message for email
 *
 **/
class form_builder {
	public $request;
	public $post;
	public $strings;
	public $tables;
	public $errorVals;
	public $placeholder;
	public $errors;
	public $lang;
	public $proceed;
	public $staticVars;
	public $date;
	public $userEntered;
	public $sendTo;
	public $sendFromAddr;
	public $sendFromName;


	public function __construct($lang) {
		$this->sendFromName       = 'Robin Kurtz';
		$this->sendFromAddr       = (function_exists('admin_email') ? get_bloginfo('admin_email') : 'robin@peoplelikeus.ca');
		$this->sendTo       = "robin@peoplelikeus.ca";
		$this->lang        = $lang;
		$this->userEntered = array(
			'entered' => false,
			'message' => ''
		);
		$this->proceed     = true;
		$this->date        = date("Y-m-d H:i:s");
		$this->request     = (isset($_REQUEST) ? $_REQUEST : false);
		$this->post        = (isset($_POST) ? $_POST : false);

		$this->recaptcha    = array(
			'site_key'   => '6LcAYBcUAAAAAFvIp_rn7aDa9Vh7OD6EivAKPKE1',
			'secret_key' => '6LcAYBcUAAAAADtpqIw7X7Ibh7k9vhYaRjmUEgtQ'
		);

		$this->errorVals = array(
			'form_fname'       => array(
				'required' => ($this->lang == 'en' ? "Please enter your first name" : "Veuillez indiquer votre prénom")
			),
			'form_lname'       => array(
				'required' => ($this->lang == 'en' ? "Please enter your last name" : "Veuillez indiquer votre nom")
			),
			'form_email'       => array(
				'required' => ($this->lang == 'en' ? "Please enter a valid e-mail address" : "Veuillez indiquer une adresse courriel valide"),
				'valid'    => ($this->lang == 'en' ? "Please enter a valid e-mail address" : "Veuillez indiquer une adresse courriel valide"),
			),
			'form_telephone'   => array(
				'required' => ($this->lang == 'en' ? "Please enter your telephone number with area code" : "Veuillez  indiquer votre numéro de téléphone avec l'indicatif régional")
			),
			'form_message'   => array(
				'required' => ($this->lang == 'en' ? "Please leave me a message" : "Veuillez mettre votre commentaites ou préoccupations s'il vous plaît")
			),
			'recaptcha_passed' => array(
				'required' => ($this->lang == 'en' ? "Please prove that you are not a robot!" : "Please prove that you are not a robot!")
			)
		);

		$this->placeholder = array(
			'form_fname'     => ($this->lang == 'en' ? 'First name' : 'Prénom'),
			'form_lname'     => ($this->lang == 'en' ? 'Last name' : 'Nom'),
			'form_email' => ($this->lang == 'en' ? 'Email' : 'Courriel'),
			'form_telephone'    => ($this->lang == 'en' ? 'Telephone' : 'Téléphone'),
			'form_message'    => ($this->lang == 'en' ? 'Message' : 'Message'),
		);

	}
	/**
	 * See WordPress' Docs
	 * @param $value
	 * @return array|string
	 */
	private function stripslashes_deep($value) {
		if ( is_array($value) ):
			$value = array_map('stripslashes_deep', $value);
		elseif ( is_object($value) ):
			$vars = get_object_vars( $value );
			foreach ($vars as $key=>$data) {
				$value->{$key} = $this->stripslashes_deep( $data );
			}
		elseif ( is_string( $value ) ):
			$value = stripslashes($value);
		endif;

		return $value;
	}
	/** See WordPress' Docs**/
	private function parse_str($string, &$array){
		parse_str( $string, $array );
		if ( get_magic_quotes_gpc() ):
			$array = $this->stripslashes_deep( $array );
		endif;
	}
	/** Merges arrays to provide a default**/
	private function parse_args( $args, $defaults = '' ) {
		if ( is_object( $args ) )
			$r = get_object_vars( $args );
		elseif ( is_array( $args ) )
			$r =& $args;
		else
			$this->parse_str( $args, $r );

		if ( is_array( $defaults ) )
			return array_merge( $defaults, $r );
		return $r;
	}

	private function less_one_day($date) {
		$lastEntry        = strtotime($date);
		$lastEntryPlusOne = $lastEntry + (24 * 60 * 60);
		$currentDate      = strtotime($this->date);

		return ($lastEntryPlusOne <= $currentDate);
	}

	public function get_ip() {
		$return_ip = null;
		if ( $_SERVER['HTTP_X_FORWARDED_FOR'] !== null ):
			$return_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		elseif ( $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'] !== null ):
			$return_ip = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
		else:
			$return_ip = $_SERVER['REMOTE_ADDR'];
		endif;

		return $return_ip;
	}

	private function is_type($needle, $haystack){
		$return = false;
		if(is_array($haystack)):
			$return = in_array($needle, $haystack);
		else:
			return ($needle == $haystack);
		endif;
		return $return;
	}


	private function different_day($date) {
		$lastEntry = array(
			'year' => date('Y', strtotime($date)),
			'month' => date('m', strtotime($date)),
		    'day' => date('d', strtotime($date))
		);
		$currentDate = array(
			'year' => date('Y', strtotime($this->date)),
			'month' => date('m', strtotime($this->date)),
		    'day' => date('d', strtotime($this->date))
		);
		$lastDay = intval($lastEntry['day']);
		$currentDay = intval($currentDate['day']);

		return ($lastDay !== $currentDay);
	}

	private function convert( $str ) {
		return iconv( "UTF-8", "ISO-8859-1", $str );
	}


	public function sanitize($field_name, $type = 'text') {
		$var = $this->request[$field_name];
		return trim($var);
	}

	public function has_error($field_name){
		if (isset($this->errors[$field_name]) && $this->errors[$field_name]):
			return true;
		else:
			return false;
		endif;
	}

	public function get_placeholder($field_name){
		return $this->placeholder[$field_name];
	}
	/** TODO: Enable retrieving of different error messages through use of $type and $this->has_error($field_name, $type)**/
	public function get_label($field_name, $type = false, $has_error = false, $hidden = false) {
		if($has_error):
			echo "<label for='$field_name' class='error'>{$this->errorVals[$field_name][$type]}</label>";
		else:
			if ($hidden) :
				echo "<label class='hidden' for='$field_name'>".($type ? '* ': '')."{$this->placeholder[$field_name]}</label>";
			else :
				echo "<label for='$field_name'>".($type ? '* ': '')."{$this->placeholder[$field_name]}</label>";
			endif;
		endif;
	}
	public function get_error_label($field_name, $type = 'required') {
		if ($this->has_error($field_name)):
			$this->get_label($field_name, $type, true);
			exit();
		endif;
		return false;
	}

	public function is_valid($field_name, $type = 'text') {
		$return = false;

		if ($type == 'email'):
			/** TODO: FIND A USE FOR THIS? **/
		endif;
		/**  TODO: a string of '0' could be valid in a given context**/
		if (isset($this->request[$field_name])) :
			if ($this->request[$field_name] !== ''
				&& $this->request[$field_name] !== 0
				&& $this->request[$field_name] !== '0'):
				$return = true;
			endif;
		endif;
		return $return;
	}

	private function create_internal_options($field_name, $extras){
		$return = '';
		if ($extras['placeholder']) $return .= "<option value=''>{$this->placeholder[$field_name]}</option>";
		foreach ($extras['options'] as $option):
			$return .= "<option value='{$option}'>{$option}</option>";
		endforeach;

		return $return;

	}
	private function create_internal($field_name, $extras) {
		$return = '';

		switch ($extras['type']):
			case 'phone':
				break;
		endswitch;
		if ($extras['placeholder'] && !$this->is_type($extras['type'], 'select')) $return .= " placeholder='{$this->placeholder[$field_name]}'";
		if ($extras['classes']) $return .= " class='{$extras['classes']}'";

		$return .= " name='$field_name' id='{$extras['field_id']}'";

		if ($this->is_valid($field_name, $extras['type']) && $this->is_type($extras['type'], array('text', 'phone','email'))):
			$return .= " value='{$this->request[$field_name]}'";
		endif;

		if ($this->is_valid($field_name, $extras['type']) && ($this->is_type($extras['type'], array('checkbox', 'radio')))):
			$return .= " checked='checked'";
		endif;
		return $return;

	}

	public function create($field_name, $optional = array()) {

		$defaults = array(
			'required'    => false,
			'field_id'    => false,
			'type'        => 'text',
			'placeholder' => false,
			'classes'     => false
		);

		if (!$optional['field_id']) $optional['field_id'] = $field_name . '_id';

		$extras = $this->parse_args($optional, $defaults);

		if (!$extras['field_id']) $extras['field_id'] = $field_name;
		$return = '';


		switch ($extras['type']):
			case 'textarea':
				$return .= "<textarea";
				$return .= $this->create_internal($field_name, $extras);
				$return .= '>';
				if($this->is_valid($field_name, $extras['type'])):
					$return .= $this->request[$field_name];
				endif;
				$return .= "</textarea>";
				break;

			case 'checkbox':
			case 'radio':
				/* TODO: This doesnt look finished? */
				$return .= "<input";
				$return .= " type='{$extras['type']}' ";
				$return .= $this->create_internal($field_name, $extras);
				$return .= "/>";
				break;

			case 'select':
				$return .= "<select";
				$return .= $this->create_internal($field_name, $extras);
				$return .= ">";
				if(isset($extras['options'])):
					$return .= $this->create_internal_options($field_name, $extras);
				endif;
				$return .="</select>";
				break;

			case 'email':
				$return .= "<input";
				$return .= " type='text' class='email-input' ";
				$return .= $this->create_internal($field_name, $extras);
				$return .= "/>";
				break;

			case 'text':
			case 'phone':
            default:
                $return .= "<input";
                $return .= " type='text' ";
                $return .= $this->create_internal($field_name, $extras);
                $return .= "/>";

                break;
		endswitch;

		echo $return;
	}
}

$form = new form_builder(get_lang_active());
