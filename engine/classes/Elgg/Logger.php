<?php
namespace Elgg;

use Elgg\Printer\HtmlPrinter;

/**
 * WARNING: API IN FLUX. DO NOT USE DIRECTLY.
 *
 * Use the elgg_* versions instead.
 *
 * @access private
 *
 * @package    Elgg.Core
 * @subpackage Logging
 * @since      1.9.0
 */
class Logger {

	const OFF = 0;
	const ERROR = 400;
	const WARNING = 300;
	const NOTICE = 250;
	const INFO = 200;

	protected static $levels = [
		0 => 'OFF',
		200 => 'INFO',
		250 => 'NOTICE',
		300 => 'WARNING',
		400 => 'ERROR',
	];

	/**
	 * @var int
	 */
	public static $verbosity;

	/**
	 * @var int The logging level
	 */
	protected $level = self::ERROR;

	/**
	 * @var bool Display to user?
	 */
	protected $display = false;

	/**
	 * @var PluginHooksService
	 */
	protected $hooks;

	/**
	 * @var Context
	 */
	private $context;

	/**
	 * @var array
	 */
	private $disabled_stack;

	/**
	 * @var Printer
	 */
	private $printer;

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * Constructor
	 *
	 * @param PluginHooksService $hooks   Hooks service
	 * @param Context            $context Context service
	 * @param Config             $config  Config
	 * @param Printer            $printer Printer
	 */
	public function __construct(PluginHooksService $hooks, Context $context, Config $config, Printer $printer = null) {
		$this->hooks = $hooks;
		$this->context = $context;
		if (!isset($printer)) {
			$printer = new HtmlPrinter();
		}
		$this->printer = $printer;
		$this->config = $config;
		
		$php_error_level = error_reporting();
		
		if (($php_error_level & E_ALL) == E_ALL) {
			$this->level = self::INFO;
		} elseif (($php_error_level & E_NOTICE) == E_NOTICE) {
			$this->level = self::NOTICE;
		} elseif (($php_error_level & E_WARNING) == E_WARNING) {
			$this->level = self::WARNING;
		} elseif (($php_error_level & E_ERROR) == E_ERROR) {
			$this->level = self::ERROR;
		}
	}

	/**
	 * Set the logging level
	 *
	 * @param int $level The logging level
	 * @return void
	 */
	public function setLevel($level) {
		if (!$level) {
			// 0 or empty string
			$this->level = self::OFF;
			return;
		}

		// @todo Elgg has used string constants for logging levels
		if (is_string($level)) {
			$level = strtoupper($level);
			$level = array_search($level, self::$levels);

			if ($level !== false) {
				$this->level = $level;
			} else {
				$this->warn(__METHOD__ .": invalid level ignored.");
			}
			return;
		}

		if (isset(self::$levels[$level])) {
			$this->level = $level;
		} else {
			$this->warn(__METHOD__ .": invalid level ignored.");
		}
	}

	/**
	 * Get the current logging level
	 *
	 * @return int
	 */
	public function getLevel() {
		return $this->level;
	}

	/**
	 * Set whether the logging should be displayed to the user
	 *
	 * Whether data is actually displayed to the user depends on this setting
	 * and other factors such as whether we are generating a JavaScript or CSS
	 * file.
	 *
	 * @param bool $display Whether to display logging
	 * @return void
	 */
	public function setDisplay($display) {
		$this->display = $display;
	}

	/**
	 * Set custom printer
	 *
	 * @param Printer $printer Printer
	 * @return void
	 */
	public function setPrinter(Printer $printer) {
		$this->printer = $printer;
	}

	/**
	 * Add a message to the log
	 *
	 * @param string $message The message to log
	 * @param int    $level   The logging level
	 * @return bool Whether the messages was logged
	 */
	public function log($message, $level = self::NOTICE) {
		if ($this->disabled_stack) {
			// capture to top of stack
			end($this->disabled_stack);
			$key = key($this->disabled_stack);
			$this->disabled_stack[$key][] = [
				'message' => $message,
				'level' => $level,
			];
		}

		if ($this->level == self::OFF || $level < $this->level) {
			return false;
		}

		if (!array_key_exists($level, self::$levels)) {
			return false;
		}

		// when capturing, still use consistent return value
		if ($this->disabled_stack) {
			return true;
		}

		$levelString = self::$levels[$level];

		// notices and below never displayed to user
		$display = $this->display && $level > self::NOTICE;

		$this->process("$levelString: $message", $display, $level);

		return true;
	}

	/**
	 * Log message at the ERROR level
	 *
	 * @param string $message The message to log
	 * @return bool
	 */
	public function error($message) {
		return $this->log($message, self::ERROR);
	}

	/**
	 * Log message at the WARNING level
	 *
	 * @param string $message The message to log
	 * @return bool
	 */
	public function warn($message) {
		return $this->log($message, self::WARNING);
	}

	/**
	 * Log message at the NOTICE level
	 *
	 * @param string $message The message to log
	 * @return bool
	 */
	public function notice($message) {
		return $this->log($message, self::NOTICE);
	}

	/**
	 * Log message at the INFO level
	 *
	 * @param string $message The message to log
	 * @return bool
	 */
	public function info($message) {
		return $this->log($message, self::INFO);
	}

	/**
	 * Dump data to log or screen
	 *
	 * @param mixed $data    The data to log
	 * @param bool  $display Whether to include this in the HTML page
	 * @return void
	 */
	public function dump($data, $display = true) {
		$this->process($data, $display, self::ERROR);
	}

	/**
	 * Process logging data
	 *
	 * @param mixed $data    The data to process
	 * @param bool  $display Whether to display the data to the user. Otherwise log it.
	 * @param int   $level   The logging level for this data
	 * @return void
	 */
	protected function process($data, $display, $level) {
		
		// plugin can return false to stop the default logging method
		$params = [
			'level' => $level,
			'msg' => $data,
			'display' => $display,
			'to_screen' => $display,
		];

		if (!$this->hooks->trigger('debug', 'log', $params, true)) {
			return;
		}

		// Do not want to write to JS or CSS pages
		if ($this->context->contains('js') || $this->context->contains('css')) {
			$display = false;
		}

		// don't display in simplecache requests
		if ($this->config->bootcomplete) {
			$path = substr(current_page_url(), strlen(elgg_get_site_url()));
			if (preg_match('~^(cache|action|serve-file)/~', $path)) {
				$display = false;
			}
		}

		$this->printer->write($data, $display, $level);
	}

	/**
	 * Temporarily disable logging and capture logs (before tests)
	 *
	 * Call disable() before your tests and enable() after. enable() will return a list of
	 * calls to log() (and helper methods) that were not acted upon.
	 *
	 * @note This behaves like a stack. You must call enable() for each disable() call.
	 *
	 * @return void
	 * @see enable()
	 * @access private
	 * @internal
	 */
	public function disable() {
		$this->disabled_stack[] = [];
	}

	/**
	 * Restore logging and get record of log calls (after tests)
	 *
	 * @return array
	 * @see disable()
	 * @access private
	 * @internal
	 */
	public function enable() {
		return array_pop($this->disabled_stack);
	}

	/**
	 * Reset the hooks service for this instance (testing)
	 *
	 * @param PluginHooksService $hooks the plugin hooks service
	 *
	 * @return void
	 * @access private
	 * @internal
	 */
	public function setHooks(PluginHooksService $hooks) {
		$this->hooks = $hooks;
	}
}
