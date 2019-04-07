<?php

use Mockery\MockInterface;
use Scp\Api\ApiQuery;
use Scp\Api\ApiResponse;
use Scp\Server\Server;
use Scp\Server\ServerRepository;
use Scp\Support\Collection;
use Scp\Whmcs\Api;
use Scp\Whmcs\Database\Database;
use Scp\Whmcs\LogFactory;
use Scp\Whmcs\Server\Usage\UsageFormatter;
use Scp\Whmcs\Server\Usage\UsageUpdater;

class UsageUpdaterTest
    extends TestCase
{
    public function setUp(): void
    {
        $this->updater = new UsageUpdater(
            $this->api = Mockery::mock(Api::class),
            $this->database = Mockery::mock(Database::class),
            $this->log = Mockery::mock(LogFactory::class),
            $this->format = Mockery::mock(UsageFormatter::class),
            $servers = Mockery::mock(ServerRepository::class)
        );

        $this->response = Mockery::mock(ApiResponse::class);
        $this->query = Mockery::mock(ApiQuery::class);
        $servers
            ->shouldReceive('query')
            ->once()
            ->andReturn($this->query)
        ;
        $this->query
            ->shouldReceive('where')
            ->withArgs(['integration_id', 'me'])
            ->andReturn($this->query)
        ;
    }

    public function testRun()
    {
        $items = array_map(function (MockInterface $server) {
            $billing = new stdClass();
            $billing->id = 2;
            $usage = new stdClass();
            $usage->used = 600 * 1000;
            $usage->max = 5000 * 1000;
            $server
                ->shouldReceive('getAttribute')
                ->with('billing')
                ->andReturn($billing)
            ;
            $server->shouldReceive('getAttribute')
                   ->with('usage')
                   ->andReturn($usage)
            ;

            $this->format
                ->shouldReceive('bitsToMB')
                ->with($usage->used, 3)
                ->andReturn($usedOutput = 1000)
            ;
            $this->format
                ->shouldReceive('bitsToMB')
                ->with($usage->max, 3)
                ->andReturn($limitOutput = 800)
            ;

            $query = Mockery::mock(Illuminate\Database\Query\Builder::class);
            $this->database
                ->shouldReceive('table')
                ->with('tblhosting')
                ->andReturn($query)
            ;
            $query
                ->shouldReceive('where')
                ->with('id', $billing->id)
                ->andReturn($query)
            ;
            $query
                ->shouldReceive('update')
                ->with([
                    'bwusage' => $usedOutput,
                    'bwlimit' => $limitOutput,
                    'lastupdate' => 'now()',
                ])
                ->once()
            ;

            return $server;
        }, [
            Mockery::mock(Server::class),
            Mockery::mock(Server::class),
        ]);
        $this->query
            ->shouldReceive('chunk')
            ->andReturnUsing(function ($_, $callback) use ($items) {
                $callback(new Collection($items));

                // Test has to make assertions
                $this->assertEquals(true, true);
            })
        ;

        $this->log
            ->shouldReceive('activity')
            ->with('SynergyCP: Completed usage update')
        ;

        $this->updater->run();
    }
}
