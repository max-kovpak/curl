<?php

namespace Curl;

class CurlTest extends \PHPUnit_Framework_TestCase {

	const TEST_URL = 'http://php-curl-test.anezi.net/tests/server.php';

	var $test_url = 'http://php-curl-test.anezi.net/tests/server/';

	/**
	 *
	 * @var Curl
	 */
	protected $curl;

	function setUp() {
		$this->curl = new Curl();
		$this->curl->setOpt(CURLOPT_SSL_VERIFYPEER, FALSE);
		$this->curl->setOpt(CURLOPT_SSL_VERIFYHOST, FALSE);
	}

	function server($request_method, $data='') {
		$request_method = strtolower($request_method);
		$this->curl->$request_method(self::TEST_URL, $data);
		return $this->curl->response;
	}

	public function testExtensionLoaded() {
			
		$this->assertTrue(extension_loaded('curl'));
	}

	public function testUserAgent() {
			
		$this->curl->setUserAgent(Curl::USER_AGENT);
		$this->assertEquals(Curl::USER_AGENT, $this->server('GET', array(
				'test' => 'server',
				'key' => 'HTTP_USER_AGENT',
		)));

	}

	public function testGet() {
		$this->assertTrue($this->server('GET', array(
				'test' => 'server',
				'key' => 'REQUEST_METHOD',
		)) === 'GET');
	}

	public function testPostRequestMethod() {
		$this->assertTrue($this->server('POST', array(
				'test' => 'server',
				'key' => 'REQUEST_METHOD',
		)) === 'POST');
	}

	public function testPostData() {
		$this->assertTrue($this->server('POST', array(
				'test' => 'post',
				'key' => 'test',
		)) === 'post');
	}

	public function testPostMultidimensionalData() {

		$data = array(
				'key' => 'file',
				'file' => array(
						'wibble',
						'wubble',
						'wobble',
				),
		);

		$this->curl->post($this->test_url . 'post_multidimensional.php', $data);

		$this->assertEquals(
				'key=file&file%5B0%5D=wibble&file%5B1%5D=wubble&file%5B2%5D=wobble',
				$this->curl->response);

	}

	public function testPostFilePathUpload() {
		$file_path = $this->get_png();

		$data = array(
				'key' => 'image',
				'image' => "@" . $file_path,
		);
		
		$image = file_get_contents($file_path);

		$data = array(
				'key' => 'image',
				'image' => $image,
		);

		$this->curl->post($this->test_url . 'post_file_path_upload.php', $data);

		$this->assertEquals(
				array(
						'request_method' => 'POST',
						'key' => 'image',
						'mime_content_type' => 'image/png'
				),
				json_decode($this->curl->response, true));

		unlink($file_path);
	}

	public function testPutRequestMethod() {
		$this->assertTrue($this->server('PUT', array(
				'test' => 'server',
				'key' => 'REQUEST_METHOD',
		)) === 'PUT');
	}

	public function testPutData() {
		$this->assertTrue($this->server('PUT', array(
				'test' => 'put',
				'key' => 'test',
		)) === 'put');
	}

	public function testPutFileHandle() {
		$png = $this->create_png();
		$tmp_file = $this->create_tmp_file($png);

		$this->curl->setopt(CURLOPT_PUT, TRUE);
		$this->curl->setopt(CURLOPT_INFILE, $tmp_file);
		$this->curl->setopt(CURLOPT_INFILESIZE, strlen($png));
		$this->curl->put(self::TEST_URL, array(
				'test' => 'put_file_handle',
		));

		fclose($tmp_file);

		$this->assertTrue($this->curl->response === 'image/png');
	}

	public function testDelete() {
		$this->assertTrue($this->server('DELETE', array(
				'test' => 'server',
				'key' => 'REQUEST_METHOD',
		)) === 'DELETE');

		$this->assertTrue($this->server('DELETE', array(
				'test' => 'delete',
				'key' => 'test',
		)) === 'delete');
	}

	public function testBasicHttpAuth() {

		$data = array();

		$this->curl->get($this->test_url . 'http_basic_auth.php', $data);

		$this->assertEquals('canceled', $this->curl->response);

		$username = 'myusername';
		$password = 'mypassword';

		$this->curl->setBasicAuthentication($username, $password);

		$this->curl->get($this->test_url . 'http_basic_auth.php', $data);

		$this->assertEquals(
				'{"username":"myusername","password":"mypassword"}',
				$this->curl->response);
	}

	public function testReferrer() {
		$this->curl->setReferrer('myreferrer');
		$this->assertTrue($this->server('GET', array(
				'test' => 'server',
				'key' => 'HTTP_REFERER',
		)) === 'myreferrer');
	}

	public function testCookies() {
		$this->curl->setCookie('mycookie', 'yum');
		$this->assertTrue($this->server('GET', array(
				'test' => 'cookie',
				'key' => 'mycookie',
		)) === 'yum');
	}

	public function testError() {
		$this->curl->setOpt(CURLOPT_CONNECTTIMEOUT_MS, 2000);
		$this->curl->get('http://1.2.3.4/');
		$this->assertTrue($this->curl->error === TRUE);
		$this->assertTrue($this->curl->curl_error === TRUE);
		$this->assertTrue($this->curl->curl_error_code === CURLE_OPERATION_TIMEOUTED);
	}

	public function testHeaders() {
		$this->curl->setHeader('Content-Type', 'application/json');
		$this->curl->setHeader('X-Requested-With', 'XMLHttpRequest');
		$this->curl->setHeader('Accept', 'application/json');
		$this->assertTrue($this->server('GET', array(
				'test' => 'server',
				'key' => 'CONTENT_TYPE',
		)) === 'application/json');
		$this->assertTrue($this->server('GET', array(
				'test' => 'server',
				'key' => 'HTTP_X_REQUESTED_WITH',
		)) === 'XMLHttpRequest');
		$this->assertTrue($this->server('GET', array(
				'test' => 'server',
				'key' => 'HTTP_ACCEPT',
		)) === 'application/json');
	}

	function create_png() {
		// PNG image data, 1 x 1, 1-bit colormap, non-interlaced
		ob_start();
		imagepng(imagecreatefromstring(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7')));
		$raw_image = ob_get_contents();
		ob_end_clean();
		return $raw_image;
	}

	function create_tmp_file($data) {
		$tmp_file = tmpfile();
		fwrite($tmp_file, $data);
		rewind($tmp_file);
		return $tmp_file;
	}

	function get_png() {
		$tmp_filename = tempnam('/tmp', 'php-curl-class.');
		file_put_contents($tmp_filename, $this->create_png());
		return $tmp_filename;
	}
}
