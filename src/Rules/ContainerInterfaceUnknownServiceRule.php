<?php

declare(strict_types = 1);

namespace Lookyman\PHPStan\Symfony\Rules;

use Lookyman\PHPStan\Symfony\ServiceMap;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\String_;
use PHPStan\Type\ObjectType;
use PHPStan\Type\ThisType;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

final class ContainerInterfaceUnknownServiceRule implements Rule
{

	/**
	 * @var ServiceMap
	 */
	private $serviceMap;

	public function __construct(ServiceMap $symfonyServiceMap)
	{
		$this->serviceMap = $symfonyServiceMap;
	}

	public function getNodeType(): string
	{
		return MethodCall::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		$type = $scope->getType($node->var);
		$baseController = new ObjectType(Controller::class);
		$isInstanceOfController = $type instanceof ThisType && $baseController->isSupersetOf($type)->yes();
		$isContainerInterface = $type instanceof ObjectType && $type->getClassName() === ContainerInterface::class;
		$services = $this->serviceMap->getServices();
		return $node instanceof MethodCall
			&& $node->name === 'get'
			&& ($isContainerInterface || $isInstanceOfController)
			&& isset($node->args[0])
			&& $node->args[0] instanceof Arg
			&& $node->args[0]->value instanceof String_
			&& !\array_key_exists($node->args[0]->value->value, $services)
				? [\sprintf('Service "%s" is not registered in the container.', $node->args[0]->value->value)]
				: [];
	}
}
