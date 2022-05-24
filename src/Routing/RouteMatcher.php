<?php

declare(strict_types=1);

namespace Semperton\Framework\Routing;

use Psr\Http\Message\ServerRequestInterface;
use Semperton\Framework\Interfaces\RouteMatcherInterface;
use Semperton\Routing\Collection\RouteCollectionInterface;
use Semperton\Routing\Matcher\RouteMatcher as RoutingRouteMatcher;
use Semperton\Routing\MatchResult;

final class RouteMatcher implements RouteMatcherInterface
{
	protected RouteCollectionInterface $routeCollection;

	public function __construct(RouteCollectionInterface $routeCollection)
	{
		$this->routeCollection = $routeCollection;
	}

	public function matchRequest(ServerRequestInterface $request): MatchResult
	{
		return (new RoutingRouteMatcher($this->routeCollection))->matchRequest($request);
	}
}
