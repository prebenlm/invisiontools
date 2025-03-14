<?php
namespace Invision;
class VersionChecker
{
	private $version = null;
	private $development = false;
	private $response = null;
	private $versionInformation = null;
	private $isV5 = false;
	
	public function __construct(int $version=null, bool $isDev=false, bool $isV5=false)
	{
		if ($isV5) {
			$this->setIsV5($isV5);
		}
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

	public function setIsV5(bool $isV5)
	{
		$this->isV5 = $isV5;
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
		$client = new \GuzzleHttp\Client(['base_uri' => 'https://remoteservices.invisionpower.com/updateCheck' . ( $this->isV5 ? '5' : '' ) . '/' ]);
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
