<?php

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

require 'JWT.php';

class plgAuthenticationaaf extends JPlugin
{
	/**
	 * Constructor
	 *
	 * For php4 compatability we must not use the __constructor as a constructor for plugins
	 * because func_get_args ( void ) returns a copy of all passed arguments NOT references.
	 * This causes problems with cross-referencing necessary for the observer design pattern.
	 *
	 * @param object $subject The object to observe
	 * @param array  $config  An array that holds the plugin configuration
	 */
	function plgAuthenticationJoomla(& $subject, $config)
	{
		parent::__construct($subject, $config);
	}

	/**
	 * Perform logout (not currently used)
	 *
	 * @access	public
	 * @return	void
	 */
	public function logout()
	{
		// Not implemented intentionally.
	}

	/**
	 * just adds stylesheet:
	 *
	 * @access	public
	 * @return	Array $status
	 */
	public function status()
	{
		$document = JFactory::getDocument();
		$document->addStyleSheet(JURI::base(). "plugins/authentication/aaf/assets/aaf.css");
	}

	/**
	 * Method to call when redirected back from AAF after authentication
	 * Grab the return URL if set and handle denial of app privileges from AAF
	 *
	 * @access	public
	 * @param   object	$credentials
	 * @param 	object	$options
	 * @return	void
	 */
	public function login(&$credentials, &$options)
	{
		$app = JFactory::getApplication();
		
		// Only authenticate on the front end.
		if($app->isAdmin()) {
			return;
		}
		
		$b64dreturn = "";

		if($return = JRequest::getVar('return', '', 'method', 'base64'))
		{
			$b64dreturn = base64_decode($return);
			if(!JURI::isInternal($b64dreturn))
			{
				$b64dreturn = '';
			}
		}

		$options['return'] = $b64dreturn;
		
		// The assertion field is returned by the AAF upon success. Pass it on for further processing. 
		$options['assertion'] = JRequest::getVar('assertion', '', 'method');
		
		// Check to make sure they didn't deny our application permissions
		if($options['assertion'] == null)
		{
			$com_user = (version_compare(JVERSION, '2.5', 'ge')) ? 'com_users' : 'com_user';
			
			// User didn't authorize our app or clicked cancel
			$app->redirect(JRoute::_('index.php?option=' . $com_user . '&view=login&return=' . $return),
				'Failed to login with AAF.', 
				'error');
		}
	}

	/**
	 * Method to setup AAF params and redirect to AAF auth URL
	 *
	 * @access	public
	 * @param   object	$view	view object
	 * @param 	object	$tpl	template object
	 * @return	void
	 */
	public function display($view, $tpl)
	{
		$app = JFactory::getApplication();

		// Redirect to the AAF login URL
		$app->redirect($this->params->get('aaf_login_url'));
	}

	/**
	 * This method should handle any authentication and report back to the subject
	 *
	 * @access	public
	 * @param   array 	$credentials Array holding the user credentials
	 * @param 	array   $options     Array of extra options
	 * @param	object	$response	 Authentication response object
	 * @return	boolean
	 */
	public function onAuthenticate( $credentials, $options, &$response )
	{
		return $this->onUserAuthenticate($credentials, $options, $response);
	}

	/**
	 * This method should handle any authentication and report back to the subject
	 *
	 * @access	public
	 * @param   array 	$credentials Array holding the user credentials
	 * @param 	array   $options     Array of extra options
	 * @param	object	$response	 Authentication response object
	 * @return	boolean
	 */
	public function onUserAuthenticate($credentials, $options, &$response)
	{
		$app = JFactory::getApplication();
		
		// Only authenticate on the front end.
		if($app->isAdmin()) {
			return;
		}
		
		if($options['assertion'] == NULL) {
			return;
		}
		
		// Decode the assertion field using JWT.
		$jwt = new JWT();
		$json = $jwt->decode($options['assertion'], $this->params->get('app_secret'));
		
		$aafResponse = json_decode($json);
		error_log(print_r($aafResponse, true), 3, "/tmp/hzm.log");

		if($aafResponse != null) {
			$juri =& JURI::getInstance();
			$service = trim($juri->base(), DS);
			
			// Add a trailing "/" to $service if the audience URL has one to allow them to match later on.
			if((substr($aafResponse->aud, strlen($aafResponse->aud) - 1)) == "/") {
				if((substr($service, strlen($service) - 1)) != "/") {
					$service = $service . "/";
				}
			}
			
			$now = strtotime("now");
			
			// Check the AAF response is valid.
			if($aafResponse->iss == $this->params->get('aaf_principal_issuer') && $aafResponse->aud == $service && $now > $aafResponse->nbf && $now < $aafResponse->exp) {
				$attributes = $aafResponse->{'https://aaf.edu.au/attributes'};
				
				// Get authenticated linked users with the specified email. 	Hubzero_Auth_Link, Hubzero_Auth_Domain, Hubzero_User_Profile,  is gone?
				$hzals = \Hubzero\Auth\Link::find_by_email($attributes->mail);
				
				if($hzals) {
					// Existing profile found - use that.
					$hzal = \Hubzero\Auth\Link::find_by_id($hzals[0]['id']);
				} else {
					// Get users with profiles that may not have been linked.
					$profiles = \Hubzero\User\Profile\Helper::find_by_email($attributes->mail);
					
					if($profiles) {
						// Existing profile found - try use that.
						$juser = JFactory::getUser();
						
						if(!$juser->get('guest')) {
							// We are linking accounts as the user is logged in to Hub Zero.
							$userProfile = new Hubzero\User\Profile($profiles[0]);
							
							$hzad = \Hubzero\Auth\Domain::getInstance('authentication', 'aaf', '');
							
							// Link the profile.
							$hzal = \Hubzero\Auth\Link::find_or_create('authentication', 'aaf', null, $profiles[0]);
							$hzal->user_id = $userProfile->get('uidNumber');
						} else {
							// We are creating a new account. Not linking to an available profile that already exists.
							
							// Username should be the string before the @ of the email.
							$sub_email = explode('@', $attributes->mail, 2);
							$username = $sub_email[0];
							
							// Create a new temp profile.
							$hzal = \Hubzero\Auth\Link::find_or_create('authentication', 'aaf', null, $username);
						}
					} else {
						// No existing profile found.
						
						// Username should be the string before the @ of the email.
						$sub_email = explode('@', $attributes->mail, 2);
						$username = $sub_email[0];
						
						// Create a new temp profile.
						$hzal = \Hubzero\Auth\Link::find_or_create('authentication', 'aaf', null, $username);
					}
				}
				
				$hzal->email = $attributes->mail;
	
				// Set response variables
				$response->auth_link = $hzal;
				$response->type      = 'aaf';
				$response->status    = JAUTHENTICATE_STATUS_SUCCESS;
				$response->fullname  = $attributes->cn;
				
				if (!empty($hzal->user_id)) {
					// User exists.
					$user = JUser::getInstance($hzal->user_id);
	
					$response->username = $user->username;
					$response->email    = $user->email;
					$response->fullname = $user->name;
				}
				else {
					// User doesn't exist. Create temp account for further processing.
					$response->username = '-' . $hzal->id;
					$response->email    = $response->username . '@invalid';
	
					// Also set a suggested username for their hub account
					JFactory::getSession()->set('auth_link.tmp_username', $username);
				}
	
				$hzal->update();
			} else {
				$response->status = JAUTHENTICATE_STATUS_FAILURE;
				$response->error_message = 'Invalid JWS.';
			}
		} else {
			$response->status = JAUTHENTICATE_STATUS_FAILURE;
			$response->error_message = 'Unauthorised to log in.';
		}
	}
}