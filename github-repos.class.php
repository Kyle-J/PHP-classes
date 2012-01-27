<?php

/**
 * Gets a list of a users repos from github
 * 
 * @author ianbarker
 *
 */
class GitHubRepos {

	protected $user;
	protected $apiUrl = 'https://api.github.com/';


	public function __construct($user) {

		$this->user = $user;

	}

	public function getRepos($type = 'public') {

		$url = $this->getUrl('users/' . $this->user . '/repos');
		$data = file_get_contents($url);
		$repos = json_decode($data);

		return $repos;

	}
	
	protected function getUrl($path = '', $params = array()) {
		
		$url = $this->apiUrl;

		if ($path) {
			if ($path[0] === '/') {
				$path = substr($path, 1);
			}
			$url .= $path;
		}

		if (!empty($params)) {
			$url .= '?' . http_build_query($params, null, '&');
		}

		return $url;

	}


}
