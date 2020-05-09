<?php

class zoho_v2_api{

	public $tokensFile;
	private $zoho_token_expires, $zoho_Client_ID, $zoho_Client_Secret, $zoho_Code, $zoho_refresh_token, $zoho_tokens;
	
	function __construct() {
		$this->zoho_Client_ID		= "client ID here";
		$this->zoho_Client_Secret	= "client Secret here";
		$this->zoho_Code			= "one time code here";

		$this->check_zoho_tokens();
	}
	
	function get_option($meta_key, $bool = false){
		if($this->{$meta_key})
			return $this->{$meta_key};
		
		if(!$this->tokensFile)
			$this->read_token_file();
			
		$data = json_decode($this->tokensFile);
		$this->{$meta_key} = $data->{$meta_key};
		
		return $data->{$meta_key};
	}
	
	function update_token($meta_key, $meta_value){
		$this->{$meta_key} = $meta_value;
		
		$this->write_tokens_file();
	}
	
	function very_first_time(){
		$fields = array( 
						'grant_type'	=>	'authorization_code',
						'client_id'		=>	$this->zoho_Client_ID,
						'client_secret'	=>	$this->zoho_Client_Secret,
						'redirect_uri'	=>	'http://localhost/redirect.php',
						'code'			=>	$this->zoho_Code,
					);
		$postvars = '';
		foreach($fields as $key=>$value) {
			$postvars .= $key . "=" . $value . "&";
		}
		$url = "https://accounts.zoho.com/oauth/v2/token?".$postvars;
		
		$resp = $this->do_curl($url, 'POST');
		if($resp['status']){
			$this->update_token("zoho_tokens", json_decode($resp['response']));
			
			$access_token = json_decode($resp['response']);
			$this->update_refresh_token($access_token);
		}
	}
	
	function update_refresh_token($access_token){
		if(isset($access_token->access_token)){
			//$this->update_token("zoho_refresh_token", $access_token->refresh_token);		
			
			$t_current = time();
			$t_token_expire = $t_current + $access_token->expires_in;
			$t_token_expire = $t_token_expire - 60;	// refresh token in every 59 minutes
			
			$this->update_token("zoho_token_expires", $t_token_expire);
		}
	}
	
	function read_token_file(){
		if(!is_readable("./json_token.json"))
			$this->very_first_time();
		
		$myfile = fopen("json_token.json", "r");
		$this->tokensFile = fread($myfile,filesize("json_token.json"));
		fclose($myfile);
	}
	
	function write_tokens_file(){
		$data = array(
			"zoho_token_expires"	=> $this->zoho_token_expires,
			"zoho_refresh_token"	=> $this->zoho_refresh_token,
			"zoho_tokens"			=> $this->zoho_tokens,
			"zoho_Client_ID"		=> $this->zoho_Client_ID,
			"zoho_Client_Secret"	=> $this->zoho_Client_Secret,
			);
			
		$f = fopen("json_token.json","w") or die("Unable to open file!");
		fwrite($f,json_encode($data));
		fclose($f);
	}
	
	function check_zoho_tokens(){
		if(!$this->tokensFile)
			$this->read_token_file();
		
		$generate = false;
		$refresh = false;
		
		$zoho_token = $this->get_option("zoho_tokens");
		if(!$zoho_token)
			$generate = true;
			
		if(!$generate){
			$access_token = $zoho_token;//json_decode($zoho_token);
			
			$t_current = time();
			$t_token_expire = $this->get_option("zoho_token_expires");//$t + $access_token->expires_in;
			
			if( $t_current > $t_token_expire ){
				$refresh = true;
			}
		}
		else{
			$this->very_first_time();
		}

		if( $refresh || (isset($access_token->error) && $access_token->error == "invalid_code")){
			$this->refreshAccessToken();
		}

	}
	
	function refreshAccessToken(){
		$zoho_token = $this->get_option("zoho_tokens");
		
		if(!$zoho_token)
			$this->very_first_time();
		
		$ch = curl_init();
		$fields = array( 
						'grant_type'	=>	'refresh_token',
						'client_id'		=>	$this->get_option("zoho_Client_ID"),
						'client_secret'	=>	$this->get_option("zoho_Client_Secret"),
						'refresh_token'	=>	$this->get_option("zoho_refresh_token"),
					);
		$postvars = '';
		foreach($fields as $key=>$value) {
			$postvars .= $key . "=" . $value . "&";
		}
		$url = "https://accounts.zoho.com/oauth/v2/token?".$postvars;
		
		$resp = $this->do_curl($url, 'POST');
		if($resp['status']){
			$access_token = json_decode($resp['response']);
			
			$this->update_token("zoho_tokens", $access_token);
			$this->update_refresh_token($access_token);
		}
		
		$this->read_token_file();
	}

	function do_curl($url, $method = "GET", $headers = array(), $post = null){
		$curl = curl_init();

		curl_setopt_array($curl, array(
		  CURLOPT_URL => $url,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 30,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => $method,
		  CURLOPT_HTTPHEADER => $headers,
		));
		
		if(isset($post) && $post != null)
			curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
		
		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);
		
		$result = array();
		
		if ($err) {
			$result['status']	=	false;
			$result['errors']	=	$err;
		} else {
			$result['status']	=	true;
			$result['response']	=	$response;
		}
		
		return $result;
	}
}

/***** Use it like this *****

$obj = new zoho_v2_api();
$obj->read_token_file();

echo "<pre>"; print_r(json_decode($obj->tokensFile));

*****/

?>