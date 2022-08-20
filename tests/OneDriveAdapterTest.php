<?php

namespace TomSim\FlysystemOneDrive\Tests;

use Microsoft\Graph\Graph;
use Microsoft\Graph\Http\GraphRequest;
use PHPUnit\Framework\TestCase;
use TomSim\FlysystemOneDrive\OneDriveAdapter;

class OneDriveAdapterTest extends TestCase
{
    /** @var \Microsoft\Graph\Graph|\PHPUnit_Framework_MockObject_MockObject */
    protected $graph;

    /** @var \Microsoft\Graph\Http\GraphRequest|\PHPUnit_Framework_MockObject_MockObject */
    public $graphRequest;

    /** @var \NicolasBeauvais\FlysystemOneDrive\OneDriveAdapter */
    protected $oneDriveAdapter;

    public function setUp(): void
    {
        $this->graph = $this->createMock(Graph::class);
        $this->graphRequest = $this->createMock(GraphRequest::class);

        $this->graph->method('createRequest')->willReturn($this->graphRequest);

        $this->oneDriveAdapter = new OneDriveAdapter($this->graph);
    }

    /** @test */
    public function it_can_run_tests()
    {
        $this->assertTrue(true);
    }
}
