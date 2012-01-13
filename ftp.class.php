<?php

class FTPException extends Exception {
}

/**
 * 
 * FTP class
 * @author _ianbarker
 * 
 * @todo create a multithreaded download 
 * @todo create a multithreaded upload
 * 
 */

class ftp {

	protected static $localStoragePath;
	protected static $logging;
	protected static $passive = false;

	protected $conn; // the ftp connection

	public static function setLocalStoragePath($path) {

		if (!is_dir($path)) throw new FTPException('Provided storage path is not a valid directory ' . $path);
		if (!is_writable($path)) throw new FTPException('Provided storage path is not a writeable directory ' . $path);
		self::$localStoragePath = $path;
		self::log('Local storage path set to ' . $path);
	}

	public static function setPassive($passive) {

		self::$passive = $passive;
	}

	public static function setLogging($logging) {

		self::$logging = $logging;
	}

	public static function getLocalStoragePath() {

		return self::$localStoragePath;
	}

	/**
	 * 
	 * Create a new instance of the FTP object
	 * @param string $host
	 * @param string $username
	 * @param string $password
	 * @throws FTPException
	 */
	public function __construct($host, $username = 'anonymous', $password = '') {

		self::log("\n\n", false);
		self::log('New instance created');
		$conn = ftp_connect($host);
		if (!$conn) {
			self::log('Failed to connect to host ' . $host);
			throw new FTPException('Failed to connect to host ' . $host);
		}
		if (!ftp_login($conn, $username, $password)) {
			self::log('Login details incorrect for user ' . $username);
			throw new FTPException('Login details incorrect for user ' . $username);
		}

		if (self::$passive) {
			ftp_pasv($conn);
		}

		// should be a authenticated ftp connection
		$this->conn = $conn;

	}

	/**
	 * 
	 * Change to a directory
	 * @param string $dir
	 * @throws FTPException
	 * @return boolean
	 */
	public function cd($dir) {

		$result = @ftp_chdir($this->conn, $dir);
		if (!$result) {
			self::log('Failed to change to directory ' . $dir);
			throw new FTPException('Failed to change to directory ' . $dir);
		}
		self::log('Directory changed to ' . $dir);
		return true;
	}

	/**
	 * 
	 * Returns a list of stuff in the current directory
	 */
	public function ls() {

		self::log('Listing contents of ' . $this->pwd());
		$items = ftp_rawlist($this->conn, $dir = './');
		foreach ($items as &$item) {
			$parts = preg_split('/\s+/', $item, 9);
			$date = strtotime($parts[5] . ' ' . $parts[6] . ' ' . $parts[7]);
			$item = array(
				'name' => $parts[8],
				'size' => $parts[4],
				'date' => $date,
				'directory' => (substr($parts[0], 0, 1) == 'd') ? true : false
			);

		}
		self::log('Found ' . count($items) . ' items');
		return $items;
	}

	public function get($file, $target = '') {

		self::log('Fetching ' . $file['name']);
		$filename = $file['name'];
		if (!empty($target)) {

			$filename = basename($target);
			$target = dirname($target);

			if (substr($target, -1) != DIRECTORY_SEPARATOR) $target .= DIRECTORY_SEPARATOR;

			if (substr($target, 0, 1) == '/') {

				// absolute path
				$local_file = $target . $filename;
				if (!is_dir($target)) {
					// create directory
					if (mkdir($target, 0777, true)) {
						self::log('Created local storage directory ' . $target);
					} else {
						self::log('Failed to create local directory for storage ' . $target);
						throw new FTPException('Failed to create local directory for storage');
					}
				}

			} else {

				// relative path
				$local_file = self::$localStoragePath . $target . $filename;
				if (!is_dir(self::$localStoragePath . $target)) {
					// create the target dir
					if (mkdir(self::$localStoragePath . $target, 0777, true)) {
						self::log('Created local storage directory ' . self::$localStoragePath . $target);
					} else {
						self::log('Failed to create local directory for storage ' . self::$localStoragePath . $target);
						throw new FTPException('Failed to create local directory for storage');
					}
				}
			}
		} else {
			$local_file = self::$localStoragePath . $file['name'];
		}

		if (is_file($local_file)) {
			if (filesize($local_file) < $file['size']) {
				self::log('Resuming download of ' . $file['name'] . ' at ' . filesize($local_file));
				$mode = 'a';
				$resume = filesize($local_file);
			} else {
				// file is already downloaded
				self::log($file['name'] . ' already downloaded');
				return true;
			}
		} else {
			self::log('Started download of ' . $file['name'] . ' [' . $this->size_readable($file['size']) . ']');
			$mode = 'w';
			$resume = 0;
		}

		$h = fopen($local_file, $mode);
		if ($h !== false) {
			$download_start = microtime(true);
			if (ftp_fget($this->conn, $h, $file['name'], FTP_BINARY, $resume)) {
				$download_end = microtime(true);
				$download_time = ($download_end - $download_start);
				$download_speed = number_format(($file['size'] / 1000) / $download_time, 2); // kb/s
				self::log('Download of ' . $file['name'] . ' completed [' . number_format($download_time, 2) . 's - ' . $download_speed . 'kb/s ]');
				return true;
			} else {
				return false;
			}

		} else {

			self::log('Could not open local file for writing ' . $local_file);
			throw new FTPException('Could not open local file for writting');

		}

	}

	/**
	 * 
	 * Returns the current remote directory
	 */
	public function pwd() {

		return ftp_pwd($this->conn);
	}


	public function __destruct() {

		self::log('Closed FTP connection');
		ftp_close($this->conn);
	}

	private static function log($message, $timestamp = true) {

		if (self::$logging) {
			if ($timestamp) {
				$message = '[' . date('d.m.y H:i:s') . '] ' . $message . "\n";
			} else {
				$message = $message . "\n";
			}
			$log_path = dirname(__FILE__) . '/../../log/ftp_log';
			file_put_contents($log_path, $message, FILE_APPEND);
		}
	}

	/**
	 * Return human readable sizes
	 *
	 * @author      Aidan Lister <aidan@php.net>
	 * @version     1.3.0
	 * @link        http://aidanlister.com/2004/04/human-readable-file-sizes/
	 * @param       int     $size        size in bytes
	 * @param       string  $max         maximum unit
	 * @param       string  $system      'si' for SI, 'bi' for binary prefixes
	 * @param       string  $retstring   return string format
	 */
	private function size_readable($size, $max = null, $system = 'si', $retstring = '%01.2f%s') {

		// Pick units

		$systems['si']['prefix'] = array('B', 'K', 'MB', 'GB', 'TB', 'PB');
		$systems['si']['size'] = 1000;
		$systems['bi']['prefix'] = array('B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB');
		$systems['bi']['size'] = 1024;
		$sys = isset($systems[$system]) ? $systems[$system] : $systems['si'];

		// Max unit to display
		$depth = count($sys['prefix']) - 1;
		if ($max && false !== $d = array_search($max, $sys['prefix'])) {
			$depth = $d;
		}

		// Loop
		$i = 0;
		while ($size >= $sys['size'] && $i < $depth) {
			$size /= $sys['size'];
			$i++;
		}

		return sprintf($retstring, $size, $sys['prefix'][$i]);
	}


}
