<?php

declare(strict_types=1);

namespace Semitexa\Api\Pipeline;

use Semitexa\Api\Attribute\RequiresScope;
use Semitexa\Api\Auth\MachinePrincipal;
use Semitexa\Core\Attribute\AsPipelineListener;
use Semitexa\Core\Exception\AccessDeniedException;
use Semitexa\Core\Pipeline\AuthCheck;
use Semitexa\Core\Pipeline\PipelineListenerInterface;
use Semitexa\Core\Pipeline\RequestPipelineContext;

/**
 * Enforces #[RequiresScope] after authorization (priority 0) decided the
 * caller may reach the route at all: the principal it authenticated must be a
 * machine credential holding every named scope.
 */
#[AsPipelineListener(phase: AuthCheck::class, priority: 1)]
final class RequiresScopeListener implements PipelineListenerInterface
{
    public function handle(RequestPipelineContext $context): void
    {
        // Read up the class chain: a payload with payload parts arrives as a
        // generated subclass (PayloadFactory), and attributes are not
        // inherited — read off that subclass alone, the scope was never asked.
        $required = [];
        for ($class = new \ReflectionClass($context->requestDto); $class !== false && $required === []; $class = $class->getParentClass()) {
            $required = $class->getAttributes(RequiresScope::class);
        }
        if ($required === []) {
            return;
        }

        $principal = $context->authResult?->success === true ? $context->authResult->user : null;
        if (!$principal instanceof MachinePrincipal) {
            throw new AccessDeniedException('This route needs a machine credential.');
        }

        $missing = array_values(array_filter(
            $required[0]->newInstance()->scopes,
            static fn (string $scope): bool => !$principal->hasScope($scope),
        ));
        if ($missing !== []) {
            throw new AccessDeniedException('This credential lacks the scope ' . implode(', ', $missing) . '.');
        }
    }
}
