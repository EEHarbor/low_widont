<?php

namespace LowWidontTests;

use Low\Widont\FluxCapacitor\Base\Mcp;

require_once dirname(__DIR__, 2) . '/support/ModelQuery.php';

class ControllerDataContractTest extends TestCase
{
    /**
     * Seed the actual controller's module-scoped settings before construction.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        \Low\Widont\FluxCapacitor\Conduit\StaticCache::put(['app_settings', 'low_widont'], ['version' => '4.1.1']);
    }

    /**
     * Preserve protected array data supplied by an existing controller subclass.
     *
     * @return void
     */
    public function testProtectedControllerData(): void
    {
        $controller = new ProtectedDataController();
        $this->paginate($controller);
        self::assertSame(array('seed' => true, 'pagination' => 'PAGINATION'), $controller->readData());
    }

    /**
     * Keep private subclass state legal without imposing a public parent field.
     *
     * @return void
     */
    public function testPrivateControllerData(): void
    {
        $controller = new PrivateDataController();
        self::assertSame(array('seed' => true), $controller->readData());
        self::assertTrue((new \ReflectionProperty($controller, 'data'))->isPrivate());
    }

    /**
     * Dispatch existing reference-returning magic access during pagination.
     *
     * @return void
     */
    public function testMagicControllerData(): void
    {
        $controller = new MagicDataController();
        $this->paginate($controller);
        self::assertSame(array('seed' => true, 'pagination' => 'PAGINATION'), $controller->readData());
        self::assertSame(array('data'), $controller->reads);
    }

    /**
     * Retain object identity for a protected ArrayAccess data container.
     *
     * @return void
     */
    public function testArrayAccessControllerData(): void
    {
        $controller = new ProtectedDataController();
        $data = new \ArrayObject(array('seed' => true));
        $controller->replaceData($data);
        $this->paginate($controller);
        self::assertSame($data, $controller->readData());
        self::assertSame(array('seed' => true, 'pagination' => 'PAGINATION'), $data->getArrayCopy());
    }

    /**
     * Exercise actual archive pagination with the established EE collaborators.
     *
     * @param Mcp $controller
     * @return void
     */
    private function paginate(Mcp $controller): void
    {
        $query = new ModelQuery(3);
        $input = $this->getMockBuilder(\stdClass::class)->addMethods(['get'])->getMock();
        $input->method('get')->willReturn(2);
        Environment::$ee->input = $input;
        $controller->flux = new class {
            /**
             * Resolve the caller route used by the actual pagination method.
             *
             * @param string $method
             * @return string
             */
            public function moduleURL($method) { return '/history'; }

            /**
             * Return the render result consumed by actual parent pagination.
             *
             * @param array $options
             * @return string
             */
            public function pagination($options) { return 'PAGINATION'; }
        };
        self::assertSame($query, $controller->paginateModel($query, '/history', 2));
    }
}

class ProtectedDataController extends Mcp
{
    protected $data = array('seed' => true);

    /**
     * Read the subclass-owned controller data.
     *
     * @return mixed
     */
    public function readData()
    {
        return $this->data;
    }

    /**
     * Supply an existing data container without changing its visibility.
     *
     * @param mixed $data
     * @return void
     */
    public function replaceData($data): void
    {
        $this->data = $data;
    }
}

class PrivateDataController extends Mcp
{
    private $data = array('seed' => true);

    /**
     * Read private controller state through its existing public API.
     *
     * @return array
     */
    public function readData(): array
    {
        return $this->data;
    }
}

class MagicDataController extends Mcp
{
    private $values = array('data' => array('seed' => true));
    public $reads = array();

    /**
     * Expose controller containers by reference as existing subclasses do.
     *
     * @param string $name
     * @return mixed
     */
    public function &__get($name)
    {
        $this->reads[] = $name;
        return $this->values[$name];
    }

    /**
     * Keep explicit magic assignments in the controller-owned store.
     *
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function __set($name, $value): void
    {
        $this->values[$name] = $value;
    }

    /**
     * Read the stored data without another magic dispatch.
     *
     * @return array
     */
    public function readData(): array
    {
        return $this->values['data'];
    }
}
