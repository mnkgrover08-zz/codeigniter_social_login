<?php

/**
 * Social Login implementation:
 *
 * PHP version 5
 *
 * @category Authentication
 * @package  Authentication_SocialLogin
 * @author   Mayank Grover <info@thetechnicalcircle.com>
 * @license  http://opensource.org/licenses/BSD-3-Clause 3-clause BSD
 * @link     https://github.com/mnkgrover08/social-login
 */

use Facebook\FacebookSession;
use Facebook\FacebookRedirectLoginHelper;
use Facebook\FacebookRequest;
use Facebook\GraphUser;
use Facebook\FacebookRequestException;
use Abraham\TwitterOAuth\TwitterOAuth;

class Social {

	public function facebook_connect($redirectUrl=null,$sessionHandler,$site_url,$fb_App_id,$fb_secret,$fb_scope,$error_description = 'error description') {

		$fbData = [];

		if (!isset($sessionHandler)) {
			$fbData['error'] = "Please set Session handler according to your Framework for session create and read";
			return $fbData;
		}

		if (!isset($site_url)) {
			$fbData['error'] = "Please set site main url with scheme";
			return $fbData;
		}

		if (!isset($fb_App_id)) {
			$fbData['error'] = "Please set Fb App Id";
			return $fbData;
		}

		if (!isset($fb_secret)) {
			$fbData['error'] = "Please set facebook secret key";
			return $fbData;
		}

		if (!isset($fb_scope)) {
			$fbData['error'] = "Please Set The desired Scope";
			return $fbData;
		}
		
		if (isset($_GET['code'])) {
			$code = $_GET['code'];
		}else{
			$code = NULL;
		}

		if (isset($_GET['error'])) {
			$error = $_GET['error'];
		}else{
			$error = NULL;
		}


		require_once('library/facebook/autoload.php');
		require_once('library/facebook/FbRedirectLoginHelper.php');
		
		FacebookSession::setDefaultApplication($fb_App_id, $fb_secret);

		if(empty($redirectUrl)) {
			$redirectUrl = $site_url.'/login/fb';
		}

		$helper = new \FbRedirectLoginHelper($redirectUrl);
		
		$helper->sessionHandler = $sessionHandler;

		
		if(isset($error)) {
			
			$fbData['error'] = $error_description;

		} else if(isset($code)) {
			try {
				$session = $helper->getSessionFromRedirect();
				if($session) {
					$request = new FacebookRequest($session, 'GET', '/me');
					$response = $request->execute();
					$graphObject = $response->getGraphObject();
					$fbData = $graphObject->asArray();
					if(!isset($fbData['location'])) {
						$fbData['location'] = '';
					}
					$fbData['picture'] = 'http://graph.facebook.com/'.$fbData['id'].'/picture?type=large';
					$fbData['logoutURL'] = $helper->getLogoutUrl($session, $site_url);
					$fbData['access_token'] = $session->getAccessToken()->extend()->__toString();

					$request = new FacebookRequest($session, 'GET', '/me/friends?fields=id,first_name,last_name,name');
					$response = $request->execute();
					$graphObject = $response->getGraphObject();
					$friends = $graphObject->asArray();
					if(!empty($friends['data'])) {
						$friends = $friends['data'];
					} else {
						$friends = array();
					}
					$fbData['friends'] = $friends;
				}
			} catch(FacebookRequestException $ex) {
				$fbData['error'] = "Exception occured, code: ".$ex->getCode()." with message: ".$ex->getMessage();
			} catch(\Exception $ex) {
				$fbData['error'] = "Exception occured, with message: ".$ex->getMessage();
			}
		} else {
			$fbData['redirectURL'] = $helper->getLoginUrl(['scope'=>$fb_scope])."&display=popup";
		}
		return $fbData;
	}

	public function twitter_connect($twt_app_id,$twt_secret,$site_url) {
		
		require_once('library/twitter/autoload.php');

		$twtData = [];
		if(!empty($_GET['oauth_verifier']) && !empty($_GET['oauth_token'])) {
			$request_token = [];
			$request_token['oauth_token'] = $_SESSION['oauth_token'];
			$request_token['oauth_token_secret'] = $_SESSION['oauth_token_secret'];
			if($request_token['oauth_token'] === $_GET['oauth_token']) {
				$connection = new TwitterOAuth($twt_app_id, $twt_secret, $request_token['oauth_token'], $request_token['oauth_token_secret']);
				$access_token = $connection->oauth('oauth/access_token', ['oauth_verifier'=>$_GET['oauth_verifier']]);

				$connection = new TwitterOAuth($twt_app_id, $twt_secret, $access_token['oauth_token'], $access_token['oauth_token_secret']);
				$user = $connection->get('account/verify_credentials');
				if(!isset($user->errors)) {
					$twtData = json_decode(json_encode($user),TRUE);
					$name = explode(' ', $twtData['name']);
					$twtData['first_name'] = $name[0];
					$twtData['last_name'] = '';
					if(isset($name[2])) {
						unset($name[0]);
						$twtData['last_name'] = implode(' ', $name);
					} else if(isset($name[1])) {
						$twtData['last_name'] = $name[1];
					}
					if(!isset($twtData['gender'])) {
						$twtData['gender'] = '';
					}
					if(!isset($twtData['location'])) {
						$twtData['location'] = '';
					}
					$twtData['username'] = $twtData['screen_name'];
					$twtData['picture'] = $twtData['profile_image_url'];
					$twtData['access_token'] = $access_token['oauth_token'];
					$twtData['access_secret'] = $access_token['oauth_token_secret'];
				} else {
					$twtData['error'] = $user->errors[0]->message;
				}
			} else {
				$twtData['error'] = 'Oauth token mis-matched';
			}
		} else if(!isset($_GET['denied'])) {
			$connection = new TwitterOAuth($twt_app_id, $twt_secret);
			$request_token = $connection->oauth('oauth/request_token', ['oauth_callback'=>$site_url.'login/twt']);
			$_SESSION['oauth_token'] = $request_token['oauth_token'];
			$_SESSION['oauth_token_secret'] = $request_token['oauth_token_secret'];
			$twtData['redirectURL'] = $connection->url('oauth/authorize', ['oauth_token'=>$request_token['oauth_token']]);
		} else if(isset($_GET['denied'])) {
			$twtData['error'] = 'User denied authorisation';
		}
		return $twtData;
	}



	public function linkedin_connect($redirectUrl=null,$ldn_api_key,$ldn_secret_key) {
		require_once('library/linkedin/src/LinkedIn/Client.php');
		if(empty($redirectUrl)) {
			$redirectUrl = $site_url.'login/ldn';
		}
		$client = new \Client($ldn_api_key, $ldn_secret_key);

		$ldnData = [];
		if(isset($_GET['error'])) {
			$ldnData['error'] = $_GET['error_description'];
		} else if(isset($_GET['code'])) {
			$access_token = $client->fetchAccessToken($_GET['code'], $redirectUrl);
			$userData = $client->fetch('/v1/people/~:(id,first-name,last-name,picture-url,email-address)');
			if(!empty($userData['emailAddress']) && !empty($userData['id'])) {
				$ldnData['id'] = $userData['id'];
				$ldnData['email'] = $userData['emailAddress'];
				$ldnData['first_name'] = $userData['firstName'];
				$ldnData['last_name'] = $userData['lastName'];
				$ldnData['name'] = $userData['firstName'].' '.$userData['lastName'];
				$ldnData['gender'] = (!empty($userData['gender'])) ? $userData['gender'] : '';
				$ldnData['picture'] = (!empty($userData['pictureUrl'])) ? $userData['pictureUrl'] : '';
				$ldnData['location'] = '';
				$ldnData['access_token'] = $access_token['access_token'];
			}
		} else {
			$ldnData['redirectURL'] = $client->getAuthorizationUrl($redirectUrl);
		}
		return $ldnData;
	}



	public function foursquare_connect($fs_client_id,$fs_client_secret) {
		require_once('library/foursquare/src/FoursquareAPI.class.php');
		$foursquare = new \FoursquareAPI($fs_client_id, $fs_client_secret);
		$redirect_uri = $site_url.'login/fs';

		$fsData = [];
		if(isset($_GET['error'])) {
			$fsData['error'] = 'User denied permission';
		} else if(isset($_GET['code'])) {
			$auth_token = $foursquare->GetToken($_GET['code'], $redirect_uri);
			$foursquare->SetAccessToken($auth_token);
			$response = $foursquare->GetPrivate('/users/self');
			$userData = json_decode($response, true);
			if(!empty($userData['response']['user']) && !empty($userData['response']['user']['id'])) {
				$fsData['id'] = $userData['response']['user']['id'];
				$fsData['email'] = $userData['response']['user']['contact']['email'];
				$fsData['first_name'] = $userData['response']['user']['firstName'];
				$fsData['last_name'] = $userData['response']['user']['lastName'];
				$fsData['name'] = $userData['response']['user']['firstName'].' '.$userData['response']['user']['lastName'];
				$fsData['gender'] = (!empty($userData['response']['user']['gender'])) ? $userData['response']['user']['gender'] : '';
				$fsData['picture'] = (!empty($userData['response']['user']['photo'])) ? $userData['response']['user']['photo']['prefix'].'original'.$userData['response']['user']['photo']['suffix'] : '';
				$fsData['location'] = '';
				$fsData['access_token'] = $auth_token;
			}
		} else {
			$fsData['redirectURL'] = $foursquare->AuthenticationLink($redirect_uri);
		}
		return $fsData;
	}



	public function gmail_connect($redirectUrl=null,$gmail_client_id,$gmail_client_secret,$gmail_api_key) {
		require_once('library/google/src/Google/autoload.php');
		if(empty($redirectUrl)) {
			$redirectUrl = $site_url.'login/gmail';
		}
		$client = new \Google_Client();
		$client->setClientId($gmail_client_id);
		$client->setClientSecret($gmail_client_secret);
		$client->setRedirectUri($redirectUrl);
		$client->setDeveloperKey($gmail_api_key);
		$client->addScope("https://www.googleapis.com/auth/userinfo.email");
		$objOAuthService = new \Google_Service_Oauth2($client);

		$gmailData = [];
		if(isset($_GET['error'])) {
			$gmailData['error'] = 'User denied permission';
		} else if(isset($_GET['code'])) {
			$client->authenticate($_GET['code']);
			$_SESSION['access_token'] = $client->getAccessToken();
		}
		if($client->getAccessToken()) {
			$userData = $objOAuthService->userinfo->get();
			if(!empty($userData)) {
				$gmailData['email'] = $userData->email;
				$gmailData['first_name'] = $userData->givenName;
				$gmailData['last_name'] = $userData->familyName;
				$gmailData['name'] = $userData->name;
				$gmailData['gender'] = (!empty($userData->gender)) ? $userData->gender : '';
				$gmailData['picture'] = (!empty($userData->picture)) ? $userData->picture : '';
				$gmailData['location'] = '';
			}
		} else {
			if(empty($gmailData['error'])) {
				$gmailData['redirectURL'] = $client->createAuthUrl();
			}
		}
		return $gmailData;
	}



	public function yahoo_connect() {
		require_once('library/openid/Lightopenid.php');
		$openid = new \Lightopenid($_SERVER['SERVER_NAME']);

		$yahooData = [];
		if($openid->mode == 'cancel') {
			$yahooData['error'] = 'You have cancelled authentication';
		} else if(isset($_GET['openid_mode'])) {
			$ret = $openid->getAttributes();
			if(isset($ret['contact/email']) && $openid->validate()) {
				$yahooData['email'] = $ret['contact/email'];
				$yahooData['name'] = $ret['namePerson'];
				if($ret['person/gender'] == 'F') {
					$yahooData['gender'] = 'female';
				} else {
					$yahooData['gender'] = 'male';
				}
				$name = explode(' ', $ret['namePerson']);
				$yahooData['first_name'] = $name[0];
				$yahooData['last_name'] = '';
				if(isset($name[2])) {
					unset($name[0]);
					$yahooData['last_name'] = implode(' ', $name);
				} else if(isset($name[1])) {
					$yahooData['last_name'] = $name[1];
				}
				$yahooData['picture'] = '';
				$yahooData['location'] = '';
			}
		} else {
			$openid->identity = "http://me.yahoo.com/";
			$openid->required = ['contact/email', 'namePerson', 'person/gender'];
			$openid->returnUrl = $site_url.'login/yahoo';
			$yahooData['redirectURL'] = $openid->authUrl();
		}
		return $yahooData;
	}
}																								
