<?php


class Configuration
{
	protected function get_config()
	{
		return json_decode(file_get_contents(dirname(__FILE__) . "/config/config.json"));
	}
	public static function get_ndli_api_url()
	{
		return self::get_config()->{"ndli_api_url"};
	}
	public static function get_ndli_api_pass()
	{
		return self::get_config()->{"ndli_api_pass"};
	}
	public static function get_api_document_search()
	{
		return self::get_config()->{"api_document_search"};
	}

	public static function get_api_token()
	{
		return self::get_config()->{"get_api_token"};
	}
}

class NDLI_API
{
	private $curl;
	private $url;
	//private $tokenUrl;
	private $pass;
	private $system_id;
	private $token = null;
	//private $ttl = 0;
	//private $loginStatus = false;

	/**
	 * [ NDLI API ]
	 */
	public function __construct()
	{
		$this->url = Configuration::get_ndli_api_url();
		$this->pass = Configuration::get_ndli_api_pass();
		$this->search = Configuration::get_api_document_search();
		$this->getToken = Configuration::get_api_token();
	}



	public function get_token()
	{
		$this->tokenUrl = $this->url . $this->getToken;
		$this->system_id = '9800074842';
		$this->curl      = curl_init($this->tokenUrl);

		$curl_options = array(
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_POST => 1,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_TIMEOUT => 1500,
			CURLOPT_HTTPHEADER => array(
				'id: ' . $this->system_id,
				"pass:" . $this->pass
			),
		);


		curl_setopt_array($this->curl, $curl_options);
		$response = curl_exec($this->curl);
		curl_close($this->curl);
		if ($response === false) {
			return false;
		} else {
			$token_response = json_decode($response);
			$this->token = $token_response->key->token;
			return true;
		}
	}


	public function get_response($request)
	{
		$this->url   = $this->url . $this->search;
		$this->curl  = curl_init($this->url);
		if ($this->token !== NULL) {
			$curl_options = array(
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_POST => 1,
				CURLOPT_CUSTOMREQUEST => "POST",
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_TIMEOUT => 1500,
				CURLOPT_POSTFIELDS => $request,
				CURLOPT_HTTPHEADER => array(
					'Authorization: Bearer ' . $this->token,
				),
			);

			curl_setopt_array($this->curl, $curl_options);
			$response = curl_exec($this->curl);
			$curl_info  = curl_getinfo($this->curl);

			// check if the token expired then again get the token by the same user token id and update the token
			// @todo: need to test the condition
			if ($curl_info['http_code'] == 440) {
				$this->get_token();
				$this->get_response($request);
			}
			curl_close($this->curl);

			if ($response === false) {
				return null;
			} else {
				return json_decode($response);
			}
		} else {
			return null;
		}
	}
}


$save_prefix = "https://ndl.iitkgp.ac.in/document/";
$filePath = "search-keys-2020-11-12.csv";
$file_output=fopen('search-result_doc_ids.csv','w+');
$csvFile = fopen($filePath, "r");
while (!feof($csvFile)) {
	$line = fgetcsv($csvFile, NULL, ",", '"', '"');
	if ($line !== FALSE) {
		$form_data = array(
			'key' => $line[0],
			'accessRights[]' => 'open'
		);

		$ndl_api = new NDLI_API();

		$ndl_api->get_token();
		$search_result = $ndl_api->get_response($form_data);
		$documents = $search_result->response->documents;
		if ($documents) {
			foreach ($documents as $document) {
				$out_line[]=$save_prefix.$document->id;
				fputcsv($file_output,$out_line);
				unset($out_line);
			}
		}
		else{
			print_r("0 results found for search ".$line[0].PHP_EOL);
		}
	}
}
fclose($file_output);
fclose($csvFile);