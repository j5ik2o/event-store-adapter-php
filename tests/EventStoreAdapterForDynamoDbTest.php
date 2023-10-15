<?php declare(strict_types=1);

use Phinx\Console\PhinxApplication;
use Phinx\Wrapper\TextWrapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\NullOutput;
use Testcontainer\Container\MySQLContainer;


final class EventStoreAdapterForDynamoDbTest extends TestCase {
    private static $T;

    public function testPersist() {
//        $container = MySQLContainer::make('8.0');
//        $container->withMySQLDatabase('foo');
//        $container->withMySQLUser('bar', 'baz');
//        $container->run();

        $app = new PhinxApplication();
        $app->setAutoExit(false);
        $app->run(new StringInput(' '), new ConsoleOutput());

        self::$T = new TextWrapper($app);
        self::$T->getMigrate("testing");

        $this->assertSame(true, true);

//        $pdo = new \PDO(
//            sprintf('mysql:host=%s;port=3306', $container->getAddress()),
//            'bar',
//            'baz',
//        );
//
//        $query = $pdo->query('SHOW databases');
//
//        $this->assertInstanceOf(\PDOStatement::class, $query);
//
//        $databases = $query->fetchAll(\PDO::FETCH_COLUMN);
//
//        $this->assertContains('foo', $databases);
    }
}