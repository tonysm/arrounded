<?php
namespace Arrounded;

/**
 * Object-oriented wrapper for CURL
 */
class Curl
{
	/**
	 * The internal CURL instance
	 *
	 * @var resource
	 */
	protected $curl;

	/**
	 * Build a new Curl instance
	 *
	 * @param string $url
	 */
	public function __construct($url = null)
	{
		$this->curl = curl_init();

		if ($url) {
			$this->url = $url;
		}
	}

	/**
	 * Set a CURL option
	 *
	 * @param string $key
	 * @param mixed  $value
	 */
	public function __set($key, $value)
	{
		$option = constant('CURLOPT_'.strtoupper($key));

		curl_setopt($this->curl, $option, $value);
	}

	/**
	 * Close the instance
	 *
	 * @return void
	 */
	public function close()
	{
		curl_close($this->curl);
	}

	/**
	 * Get an info on the current instance
	 *
	 * @param string $info
	 *
	 * @return mixed
	 */
	public function info($info)
	{
		$option = constant('CURLINFO_'.strtoupper($info));

		return curl_getinfo($this->curl, $option);
	}

	/**
	 * Send and get results
	 *
	 * @return mixed
	 */
	public function send()
	{
		return curl_exec($this->curl);
	}

	/**
	 * Get contents of the remote URL
	 *
	 * @return mixed
	 */
	public function getContents()
	{
		$this->returnTransfer = 1;

		return $this->send();
	}
}
