<?php

/*
 * This file is part of the WouterJEloquentBundle package.
 *
 * (c) 2014 Wouter de Jong
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WouterJ\EloquentBundle\Command;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use WouterJ\EloquentBundle\Migrations\Migrator;
use Prophecy\Argument;

/**
 * @author Wouter J <wouter@wouterj.nl>
 */
class MigrateCommandTest extends \PHPUnit_Framework_TestCase
{
    private $command;
    private $migrator;

    protected function setUp()
    {
        $this->migrator = $this->prophesize(Migrator::class);
        $this->migrator->getNotes()->willReturn([]);
        $this->migrator->paths()->willReturn([]);

        $container = $this->createContainer();
        $container->compile();

        $this->command = new MigrateCommand();
        $this->command->setContainer($container);
    }

    /** @test */
    public function it_asks_for_confirmation_in_prod()
    {
        $container = $this->createContainer();
        $container->setParameter('kernel.environment', 'prod');
        $container->compile();
        $this->command->setContainer($container);

        $this->migrator->run(Argument::cetera())->shouldNotBeCalled();

        TestCommand::create($this->command)
            ->answering("no")
            ->duringExecute()
            ->outputs('Are you sure you want to execute the migrations in production?');
    }

    /** @test */
    public function it_does_not_ask_for_confirmation_in_dev()
    {
        $this->migrator->run(Argument::cetera())->shouldBeCalled();

        TestCommand::create($this->command)
            ->execute()
            ->doesNotOutput('Are you sure you want to execute the migrations in production?');
    }

    /** @test */
    public function it_always_continues_when_force_is_passed()
    {
        $container = $this->createContainer();
        $container->setParameter('kernel.environment', 'prod');
        $container->compile();
        $this->command->setContainer($container);

        $this->migrator->run(Argument::cetera())->shouldBeCalled();

        TestCommand::create($this->command)
            ->passing('--force')
            ->duringExecute()
            ->doesNotOutput('Are you sure you want to execute the migrations in production?');
    }

    /** @test */
    public function it_uses_the_default_migration_path()
    {
        $this->migrator->run([__DIR__.'/migrations'], Argument::cetera())->shouldBeCalled();

        TestCommand::create($this->command)->execute();
    }

    /** @test */
    public function it_allows_to_specify_another_path()
    {
        $this->migrator->run([getcwd().'/db'], Argument::cetera())->shouldBeCalled();

        TestCommand::create($this->command)->passing('--path', 'db')->duringExecute();
    }

    /** @test */
    public function it_allows_multiple_migration_directories()
    {
        $this->migrator->paths()->willReturn(['/somewhere/migrations']);

        $this->migrator->run([__DIR__.'/migrations', '/somewhere/migrations'], Argument::cetera())->shouldBeCalled();

        TestCommand::create($this->command)->execute();
    }

    /** @test */
    public function it_allows_batching_migrations_one_by_one()
    {
        $this->migrator->run(Argument::any(), ['pretend' => false, 'step' => true])->shouldBeCalled();

        TestCommand::create($this->command)->passing('--step')->duringExecute();
    }

    /** @test */
    public function it_can_pretend_migrations_were_run()
    {
        $this->migrator->run(Argument::any(), ['pretend' => true, 'step' => false])->shouldBeCalled();

        TestCommand::create($this->command)->passing('--pretend')->duringExecute();
    }

    /** @test */
    public function it_seeds_after_migrations_when_seed_is_passed()
    {
        $this->migrator->run(Argument::cetera())->shouldBeCalled();

        $seedCommand = $this->prophesize(Command::class);
        $seedCommand->run(new ArrayInput(['command' => 'eloquent:seed']), Argument::any())->shouldBeCalled();

        $app = $this->prophesize(Application::class);
        $app->getHelperSet()->willReturn(new HelperSet());
        $app->getDefinition()->willReturn(new InputDefinition());
        $app->find('eloquent:seed')->willReturn($seedCommand->reveal());

        $this->command->setApplication($app->reveal());

        TestCommand::create($this->command)->passing('--seed')->duringExecute();
    }

    /** @test */
    public function it_outputs_migration_notes()
    {
        $this->migrator->getNotes()->willReturn([
            'Migrated: CreateFlightsTable',
            'Migrated: SomethingToTest',
        ]);

        $this->migrator->run(Argument::cetera())->shouldBeCalled();

        TestCommand::create($this->command)
            ->execute()
            ->outputs("Migrated: CreateFlightsTable\nMigrated: SomethingToTest");
    }

    private function createContainer()
    {
        $container = new Container();
        $container->set('wouterj_eloquent.migrator', $this->migrator->reveal());
        $container->setParameter('wouterj_eloquent.migration_path', __DIR__.'/migrations');
        $container->setParameter('kernel.environment', 'dev');

        return $container;
    }
}
