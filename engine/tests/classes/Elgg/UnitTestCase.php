<?php

namespace Elgg;

use Elgg\Mocks\Di\MockServiceProvider;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Unit test abstraction class
 *
 * Extend this class to run PHPUnit tests without a database connection
 */
abstract class UnitTestCase extends BaseTestCase {

	/**
	 * {@inheritdoc}
	 */
	public static function createApplication() {

		Application::setInstance(null);

		$config = self::getTestingConfig();
		$sp = new MockServiceProvider($config);

		// persistentLogin service needs this set to instantiate without calling DB
		$sp->config->getCookieConfig();
		$sp->config->boot_complete = false;

		$app = Application::factory([
			'config' => $config,
			'service_provider' => $sp,
			'handle_exceptions' => false,
			'handle_shutdown' => false,
			'set_start_time' => false,
		]);

		Application::setInstance($app);

		if (in_array('--verbose', $_SERVER['argv'])) {
			Logger::$verbosity = ConsoleOutput::VERBOSITY_VERY_VERBOSE;
		} else {
			Logger::$verbosity = ConsoleOutput::VERBOSITY_NORMAL;
		}

		// turn off system log
		$app->_services->hooks->getEvents()->unregisterHandler('all', 'all', 'system_log_listener');
		$app->_services->hooks->getEvents()->unregisterHandler('log', 'systemlog', 'system_log_default_logger');

		return $app;
	}

	/**
	 * {@inheritdoc}
	 */
	final protected function setUp() {
		parent::setUp();

		_elgg_services()->config->site = $this->createSite([
			'url' => _elgg_config()->wwwroot,
			'name' => 'Testing Site',
			'description' => 'Testing Site',
		]);

		self::$_instance = $this;

		$this->up();
	}

	/**
	 * {@inheritdoc}
	 */
	final protected function tearDown() {
		$this->down();

		parent::tearDown();
	}

	/**
	 * {@inheritdoc}
	 */
	public function createUser(array $attributes = [], array $metadata = []) {
		$unique_id = uniqid('user');
		
		$defaults = [
			'name' => "John Doe {$unique_id}",
 			'username' => "john_doe_{$unique_id}",
			'email' => "john_doe_{$unique_id}@example.com",
			'banned' => 'no',
			'admin' => 'no',
		];
				
		$attributes = array_merge($defaults, $metadata, $attributes);

		$subtype = isset($attributes['subtype']) ? $attributes['subtype'] : 'foo_user';
		
		return _elgg_services()->entityTable->setup(null, 'user', $subtype, $attributes);
	}

	/**
	 * {@inheritdoc}
	 */
	public function createObject(array $attributes = [], array $metadata = []) {
		$attributes = array_merge($metadata, $attributes);

		$subtype = isset($attributes['subtype']) ? $attributes['subtype'] : 'foo_object';

		return _elgg_services()->entityTable->setup(null, 'object', $subtype, $attributes);
	}

	/**
	 * {@inheritdoc}
	 */
	public function createGroup(array $attributes = [], array $metadata = []) {
		$attributes = array_merge($metadata, $attributes);

		$subtype = isset($attributes['subtype']) ? $attributes['subtype'] : 'foo_group';

		return _elgg_services()->entityTable->setup(null, 'group', $subtype, $attributes);
	}

	/**
	 * {@inheritdoc}
	 */
	public function createSite(array $attributes = [], array $metadata = []) {
		$attributes = array_merge($metadata, $attributes);

		$subtype = isset($attributes['subtype']) ? $attributes['subtype'] : 'foo_site';

		return _elgg_services()->entityTable->setup(null, 'site', $subtype, $attributes);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function assertPreConditions() {
		parent::assertPreConditions();

		$this->assertInstanceOf(MockServiceProvider::class,  _elgg_services());
		$this->assertInstanceOf(\Elgg\Mocks\Database::class, _elgg_services()->db);
	}

	protected function assertPostConditions() {
		parent::assertPostConditions();

		$this->assertInstanceOf(MockServiceProvider::class,  _elgg_services());
		$this->assertInstanceOf(\Elgg\Mocks\Database::class, _elgg_services()->db);
	}

}

