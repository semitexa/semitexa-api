# Semitexa API

External API product layer for Semitexa with machine-to-machine authentication, versioned routes, and structured error envelopes.

## Purpose

Provides the opt-in external API surface for Semitexa applications. Routes marked with `#[ExternalApi]` receive machine-facing JSON error envelopes, Bearer token M2M authentication via `MachineAuthHandler`, and API versioning with `#[ApiVersion]` including deprecation and sunset headers.

## Role in Semitexa

Depends on `semitexa/core` and `semitexa/auth`. Enriches Core's route metadata with `external_api` and `api_version` extension keys via `ApiRouteMetadataResolver`. Internal routes remain completely unaffected by this package.

## Key Features

- `#[ExternalApi]` attribute for opt-in API route designation
- `#[ApiVersion]` with deprecation and sunset metadata headers
- `MachineAuthHandler` supporting Bearer `{id}:{secret}` token format
- `ExternalApiExceptionMapper` producing machine-readable JSON error envelopes
- `MachinePrincipal` implementing `AuthenticatableInterface`
- `MachineCredential` entity with scopes, revocation, and audit support

## Notes

Only routes explicitly marked with `#[ExternalApi]` receive API behavior. All other routes continue to use Core's default exception mapping and response handling.
