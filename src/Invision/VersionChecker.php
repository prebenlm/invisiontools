<?php
namespace Invision;
class VersionChecker
{
	private $version = null;
	private $development = false;
	private $response = null;
	private $versionInformation = null;
	
	public function __construct(int $version=null, bool $isDev=false)
	{
		if ($version) {
			$this->setVersion($version);
		}
		if ($isDev) {
			$this->isDevelopment($isDev);
		}
	}
	
	public function setVersion(int $version)
	{
		$this->version = $version;
	}
	
	public function isDevelopment(bool $development)
	{
		$this->development = $development;
	}
	
	public function getResponse()
	{
		if (!$this->response)
		{
			$this->request();
		}
		return $this->response;
	}
	
	public function request()
	{
		$client = new \GuzzleHttp\Client(['base_uri' => 'https://remoteservices.invisionpower.com/updateCheck/']);
		$params = [];
		if ($this->version) {
			$params['version'] = $this->version;
		}
		if ($this->development) {
			$params['development'] = 1;
		}
        $response = $client->get('', [
            'query'   =>$params,
            'on_stats' => function (\GuzzleHttp\TransferStats $stats) use (&$url) {
                $url = $stats->getEffectiveUri();
            }
        ]);

		if ($response->getStatusCode() == 200 AND $response->getHeader('Content-Type')[0] == 'application/json') {
			$this->versionInformation = json_decode($response->getBody());
		}
		
		$this->response = $response;
		return $response;
	}
	
	public function get($field=null)
	{
		if (!$this->versionInformation) {
			$this->request();
		}
		if ($field) {
			return $this->versionInformation[$field]; 
		}
		return $this->versionInformation;
	}
}
