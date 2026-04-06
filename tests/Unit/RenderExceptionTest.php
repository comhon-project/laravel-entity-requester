<?php

namespace Tests\Feature\Unit;

use Comhon\EntityRequester\Exceptions\NotSortableException;
use Illuminate\Http\JsonResponse;
use Tests\TestCase;

class RenderExceptionTest extends TestCase
{
    public function test_render_exception()
    {
        $response = (new NotSortableException('foo'))->render(request());
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals('{"message":"Property \'foo\' is not sortable"}', $response->getContent());
    }

    public function test_render_exception_fr()
    {
        $this->app->setLocale('fr');
        $response = (new NotSortableException('foo'))->render(request());
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals('{"message":"La propri\u00e9t\u00e9 \'foo\' n\'est pas triable"}', $response->getContent());
    }
}
