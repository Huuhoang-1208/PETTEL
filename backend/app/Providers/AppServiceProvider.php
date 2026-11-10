<?php

namespace App\Providers;

use App\Domain\Booking\Payment\PaymentCancellationGateway;
use App\Domain\Booking\Payment\PaymentReleaseGateway;
use App\Domain\Booking\Payment\SandboxPaymentCancellationGateway;
use App\Domain\Booking\Payment\SandboxPaymentReleaseGateway;
use App\Models\User;
use App\Support\PerformanceQueryRecorder;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\MediaType;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Path;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentCancellationGateway::class, SandboxPaymentCancellationGateway::class);
        $this->app->bind(PaymentReleaseGateway::class, SandboxPaymentReleaseGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->booted(function (): void {
            Scramble::afterOpenApiGenerated(function (OpenApi $openApi): void {
                $excludedRequestProperties = [
                    'App\\Http\\Requests\\StoreBookingRequest' => ['Idempotency-Key'],
                    'App\\Http\\Requests\\CancelBookingRequest' => ['Idempotency-Key'],
                    'App\\Http\\Requests\\ListingRequest' => [
                        'id',
                        'user_id',
                        'caregiver_profile_id',
                        'status',
                        'capacity',
                        'version',
                        'suspension_source',
                        'published_at',
                        'created_at',
                        'updated_at',
                        'deleted_at',
                        'listing_media',
                        'availability_slots',
                    ],
                    'App\\Http\\Requests\\CaregiverProfileRequest' => [
                        'id',
                        'user_id',
                        'caregiver_profile_id',
                        'status',
                        'capacity',
                        'version',
                        'published_at',
                        'created_at',
                        'updated_at',
                        'deleted_at',
                    ],
                ];

                foreach ($excludedRequestProperties as $schemaName => $excludedProperties) {
                    $schema = $openApi->components->schemas[$schemaName] ?? null;
                    if ($schema instanceof Schema && $schema->type instanceof ObjectType) {
                        foreach ($excludedProperties as $property) {
                            unset($schema->type->properties[$property]);
                        }
                        $schema->type->required = array_values(array_diff($schema->type->required, $excludedProperties));
                    }
                }

                foreach ($openApi->paths as $path) {
                    foreach ($path->operations as $method => $operation) {
                        $idempotencyPath = strtolower($method) === 'post' && in_array($path->path, [
                            'bookings',
                            'bookings/{id}/cancel',
                            'bookings/{id}/sandbox-payments',
                        ], true);
                        $callbackPath = strtolower($method) === 'post' && $path->path === 'sandbox/payment-callback';

                        foreach ([
                            [$idempotencyPath, 'Idempotency-Key', '^[a-z0-9._:-]{1,128}$', 'Required request replay key.'],
                            [$callbackPath, 'X-Payment-Signature', null, 'Required HMAC signature of the callback payload.'],
                        ] as [$applies, $name, $pattern, $description]) {
                            if (!$applies) {
                                continue;
                            }

                            $parameter = Parameter::make($name, 'header')->required(true)->description($description);
                            $type = new StringType;
                            if ($pattern !== null) {
                                $type->pattern = $pattern;
                            }
                            $parameter->setSchema(Schema::fromType($type));
                            $operation->addParameters([$parameter]);
                        }
                    }
                }

                foreach ($openApi->paths as $path) {
                    if ($path->path !== 'bookings/{id}/messages/{messageId}/attachment/{mediaId}') {
                        continue;
                    }

                    $attachmentOperation = $path->operations['get'] ?? null;
                    if ($attachmentOperation === null) {
                        continue;
                    }

                    $firstAttachmentOperation = clone $attachmentOperation;
                    $firstAttachmentOperation->setOperationId('bookings.messages.attachment.first');
                    $firstAttachmentOperation->description = trim($firstAttachmentOperation->description . "\n\nWhen `mediaId` is omitted, the API streams the first visible attachment on this message.");
                    $firstAttachmentOperation->parameters = array_values(array_filter(
                        $firstAttachmentOperation->parameters,
                        fn($parameter): bool => !($parameter instanceof Parameter && $parameter->in === 'path' && $parameter->name === 'mediaId'),
                    ));

                    $openApi->addPath(
                        Path::make('bookings/{id}/messages/{messageId}/attachment')
                            ->addOperation($firstAttachmentOperation),
                    );

                    break;
                }

                $string = static function (string $format = '', array $enum = [], bool $nullable = false): StringType {
                    $type = new StringType;
                    $type->format($format)->nullable($nullable);
                    $type->enum = $enum;

                    return $type;
                };
                $object = static function (array $properties, array $required = []): ObjectType {
                    $type = new ObjectType;
                    foreach ($properties as $name => $property) {
                        $type->addProperty($name, $property);
                    }
                    $type->setRequired($required);

                    return $type;
                };
                $array = static function ($items): ArrayType {
                    $type = new ArrayType;
                    $type->setItems($items);

                    return $type;
                };
                $listingChange = static function () use ($object, $string): ObjectType {
                    return $object([
                        'id' => $string(),
                        'listing_id' => $string(),
                        'base_listing_version' => new IntegerType,
                        'proposed_data_json' => $object([
                            'schema_version' => new IntegerType,
                            'data' => new ObjectType,
                        ], ['schema_version', 'data']),
                        'status' => $string(),
                        'requested_by_user_id' => $string(),
                        'requested_at' => $string('date-time'),
                        'reviewed_by_user_id' => $string('', [], true),
                        'reviewed_at' => $string('date-time', [], true),
                        'decision_reason' => $string('', [], true),
                        'listing_version' => new IntegerType,
                    ], [
                        'id',
                        'listing_id',
                        'base_listing_version',
                        'proposed_data_json',
                        'status',
                        'requested_by_user_id',
                        'requested_at',
                        'reviewed_by_user_id',
                        'reviewed_at',
                        'decision_reason',
                    ]);
                };
                $schemaFor = static function (string $route, string $method, string $status = '200') use ($openApi): ?Schema {
                    foreach ($openApi->paths as $path) {
                        if (trim($path->path, '/') !== trim($route, '/')) {
                            continue;
                        }
                        $operation = $path->operations[strtolower($method)] ?? null;
                        if ($operation === null) {
                            return null;
                        }
                        foreach ($operation->responses ?? [] as $response) {
                            if (
                                !$response instanceof Response
                                || (string) $response->code !== $status
                                || !array_key_exists('application/json', $response->content)
                            ) {
                                continue;
                            }
                            $schema = $response->getContent('application/json');

                            return $schema instanceof Schema ? $schema : null;
                        }

                        return null;
                    }

                    return null;
                };
                $ensureCreatedResponse = static function (string $route, string $method) use ($openApi): void {
                    foreach ($openApi->paths as $path) {
                        if (trim($path->path, '/') !== trim($route, '/')) {
                            continue;
                        }
                        $operation = $path->operations[strtolower($method)] ?? null;
                        if (
                            $operation === null || collect($operation->responses ?? [])->contains(
                                fn($response): bool => $response instanceof Response && (string) $response->code === '201',
                            )
                        ) {
                            return;
                        }
                        foreach ($operation->responses ?? [] as $response) {
                            if ($response instanceof Response && (string) $response->code === '200') {
                                $created = clone $response;
                                $created->setCode('201')->setDescription('A new resource was created. Replays of the same idempotent operation return the existing resource with HTTP 200.');
                                $operation->addResponse($created);

                                return;
                            }
                        }

                        return;
                    }
                };
                foreach ([
                    ['bookings', 'post'],
                    ['bookings/{id}/reviews', 'post'],
                    ['bookings/{id}/sandbox-payments', 'post'],
                ] as [$route, $method]) {
                    $ensureCreatedResponse($route, $method);
                }
                foreach ($openApi->paths as $path) {
                    if (trim($path->path, '/') === 'bookings/{id}/reviews' && isset($path->operations['post'])) {
                        $path->operations['post']->responses = array_values(array_filter(
                            $path->operations['post']->responses ?? [],
                            fn($response): bool => !($response instanceof Response && (string) $response->code === '200'),
                        ));
                    }
                }
                // These responses are returned through generic arrays/resources, so Scramble
                // cannot preserve the source-level shapes. Keep these schemas explicit and
                // derived from the controller/resource code instead of accepting UnknownType.
                $quote = $object([
                    'price_total_vnd' => new IntegerType,
                    'price_unit' => $string(),
                    'quantity' => new IntegerType,
                    'pricing_version' => new IntegerType,
                    'cancellation_policy_version' => new IntegerType,
                ], ['price_total_vnd', 'price_unit', 'quantity', 'pricing_version', 'cancellation_policy_version']);
                if ($schema = $schemaFor('bookings/quote', 'post')) {
                    $schema->type = $quote;
                }

                $booking = $object([
                    'id' => $string(),
                    'status' => $string(),
                    'pet_id' => $string(),
                    'listing_id' => $string(),
                    'start_at' => $string('date-time'),
                    'end_at' => $string('date-time'),
                    'price_snapshot_vnd' => new IntegerType,
                    'payment_due_at' => $string('date-time', [], true),
                ], ['id', 'status', 'pet_id', 'listing_id', 'start_at', 'end_at', 'price_snapshot_vnd', 'payment_due_at']);
                if ($schema = $schemaFor('bookings', 'post', '200')) {
                    $schema->type = clone $booking;
                }
                if ($schema = $schemaFor('bookings', 'post', '201')) {
                    $schema->type = clone $booking;
                }

                $paymentStart = $object([
                    'data' => $object([
                        'id' => $string(),
                        'booking_id' => $string(),
                        'attempt_no' => new IntegerType,
                        'amount_vnd' => new IntegerType,
                        'status' => $string(),
                        'provider_ref' => $string(),
                        'payment_due_at' => $string('date-time', [], true),
                        'is_sandbox' => new BooleanType,
                    ], ['id', 'booking_id', 'attempt_no', 'amount_vnd', 'status', 'provider_ref', 'payment_due_at', 'is_sandbox']),
                ], ['data']);
                foreach (['200', '201'] as $status) {
                    if ($schema = $schemaFor('bookings/{id}/sandbox-payments', 'post', $status)) {
                        $schema->type = clone $paymentStart;
                    }
                }

                $reviewItem = $object([
                    'id' => $string(),
                    'rating' => new IntegerType,
                    'body' => $string('', [], true),
                    'is_demo' => new BooleanType,
                    'created_at' => $string('date-time'),
                ], ['id', 'rating', 'body', 'is_demo', 'created_at']);
                if ($schema = $schemaFor('listings/{id}/reviews', 'get')) {
                    $schema->type = $object([
                        'data' => $array($reviewItem),
                        'links' => $object([
                            'first' => $string('uri'),
                            'last' => $string('uri'),
                            'prev' => $string('uri', [], true),
                            'next' => $string('uri', [], true),
                        ], ['first', 'last', 'prev', 'next']),
                        'meta' => $object([
                            'current_page' => new IntegerType,
                            'last_page' => new IntegerType,
                            'per_page' => new IntegerType,
                            'total' => new IntegerType,
                        ], ['current_page', 'last_page', 'per_page', 'total']),
                        'rating_average' => (new NumberType)->nullable(true),
                        'review_count' => new IntegerType,
                    ], ['data', 'links', 'meta', 'rating_average', 'review_count']);
                }

                foreach ([
                    'caregiver/listings/{id}/change-requests',
                    'admin/listing-change-requests',
                ] as $route) {
                    if ($schema = $schemaFor($route, 'get')) {
                        if ($schema->type instanceof ObjectType) {
                            $schema->type->addProperty('data', $array($listingChange()));
                        }
                    }
                }

                if ($schema = $schemaFor('bookings/{id}/reviews', 'post')) {
                    $schema->type = $object([
                        'data' => $object([
                            'id' => $string(),
                            'booking_id' => $string(),
                            'listing_id' => $string(),
                            'rating' => new IntegerType,
                            'body' => $string('', [], true),
                            'status' => $string(),
                            'is_demo' => new BooleanType,
                            'created_at' => $string('date-time'),
                        ], ['id', 'booking_id', 'listing_id', 'rating', 'body', 'status', 'is_demo', 'created_at']),
                    ], ['data']);
                }
                if ($schema = $schemaFor('bookings/{id}/reviews', 'post', '201')) {
                    $schema->type = $object([
                        'data' => $object([
                            'id' => $string(),
                            'booking_id' => $string(),
                            'listing_id' => $string(),
                            'rating' => new IntegerType,
                            'body' => $string('', [], true),
                            'status' => $string(),
                            'is_demo' => new BooleanType,
                            'created_at' => $string('date-time'),
                        ], ['id', 'booking_id', 'listing_id', 'rating', 'body', 'status', 'is_demo', 'created_at']),
                    ], ['data']);
                }

                $bookingReportDecision = $object([
                    'data' => $object([
                        'report_id' => $string(),
                        'status' => $string('', ['RESOLVED']),
                        'decision' => $string('', ['COMPLETE', 'CANCEL_REFUND']),
                        'operation_type' => $string('', ['RELEASE', 'REFUND']),
                        'operation_id' => $string(),
                        'operation_status' => $string(),
                        'replayed' => new BooleanType,
                    ], [
                        'report_id',
                        'status',
                        'decision',
                        'operation_type',
                        'operation_id',
                        'operation_status',
                        'replayed',
                    ]),
                ], ['data']);
                foreach ($openApi->paths as $path) {
                    if (trim($path->path, '/') !== 'admin/reports/{id}/booking-decision') {
                        continue;
                    }

                    $operation = $path->operations['post'] ?? null;
                    if (
                        $operation === null || collect($operation->responses ?? [])->contains(
                            fn($response): bool => $response instanceof Response && (string) $response->code === '202',
                        )
                    ) {
                        break;
                    }

                    foreach ($operation->responses ?? [] as $response) {
                        if ($response instanceof Response && (string) $response->code === '200') {
                            $accepted = clone $response;
                            $accepted->setCode('202')->setDescription('A new booking decision operation was accepted for asynchronous processing. Replays return HTTP 200.');
                            $operation->addResponse($accepted);

                            break;
                        }
                    }

                    break;
                }
                foreach (['200', '202'] as $status) {
                    if ($schema = $schemaFor('admin/reports/{id}/booking-decision', 'post', $status)) {
                        $schema->type = clone $bookingReportDecision;
                    }
                }

                $setUnknownTypes = static function ($type, string $route = '', string $method = '') use (&$setUnknownTypes, $string, $object): void {
                    if ($type instanceof ObjectType) {
                        foreach ($type->properties as $name => $property) {
                            if (
                                $property instanceof UnknownType || in_array($name, [
                                    'sex',
                                    'size',
                                    'service_type',
                                    'area_code',
                                    'price_unit',
                                    'processing_status',
                                    'rejection_code',
                                    'mime_type',
                                    'listing_version',
                                    'created_at',
                                    'start_at',
                                    'end_at',
                                    'decided_at',
                                    'image_url',
                                    'thumbnail_url',
                                    'body',
                                    'result',
                                    'status',
                                    'from_status',
                                    'to_status',
                                    'reason',
                                    'request_id',
                                    'action',
                                    'target_type',
                                ], true)
                            ) {
                                $replacement = match ($name) {
                                    'sex' => $string('', ['MALE', 'FEMALE', 'UNKNOWN'], true),
                                    'size' => $string('', ['SMALL', 'MEDIUM', 'LARGE'], true),
                                    'service_type' => $string('', ['DAYCARE', 'BOARDING']),
                                    'area_code' => $string('', ['DN_CENTER', 'DN_NORTH', 'DN_SOUTH', 'DN_WEST', 'DN_EAST']),
                                    'price_unit' => $string('', ['HOUR', 'NIGHT']),
                                    'processing_status' => $string('', ['RECEIVED', 'PROCESSED', 'REJECTED']),
                                    'rejection_code' => $string('', ['PAYMENT_REFERENCE_MISMATCH', 'PAYMENT_AMOUNT_MISMATCH', 'PAYMENT_STATE_MISMATCH'], true),
                                    'mime_type' => $string('', ['image/jpeg', 'image/png', 'image/webp']),
                                    'listing_version' => new IntegerType,
                                    'created_at', 'start_at', 'end_at', 'decided_at' => $string('date-time'),
                                    'image_url', 'thumbnail_url' => $string('uri'),
                                    'body' => $string('', [], true),
                                    'result' => $string('', $route === 'sandbox/payment-callback'
                                        ? ['AUTHORIZED', 'FAILED'] : ['SUCCESS', 'FAILURE', 'TIMEOUT']),
                                    'status' => $string(),
                                    'from_status', 'request_id', 'reason' => $string('', [], true),
                                    'to_status', 'action', 'target_type' => $string(),
                                    default => $property,
                                };
                                $type->properties[$name] = $replacement;
                            } elseif (
                                $property instanceof ArrayType && $name === 'items'
                                && $route === 'listings/{id}/reviews' && $method === 'get'
                                && $property->items instanceof UnknownType
                            ) {
                                $property->items = $object([
                                    'id' => $string(),
                                    'rating' => new IntegerType,
                                    'body' => $string('', [], true),
                                    'is_demo' => new BooleanType,
                                    'created_at' => $string('date-time'),
                                ], ['id', 'rating', 'body', 'is_demo', 'created_at']);
                            } else {
                                $setUnknownTypes($property, $route, $method);
                            }
                        }
                        if ($type->additionalProperties instanceof UnknownType) {
                            $type->additionalProperties = new ObjectType;
                        }
                    } elseif ($type instanceof ArrayType) {
                        $setUnknownTypes($type->items, $route, $method);
                    } elseif ($type instanceof AnyOf) {
                        foreach ($type->items as $item) {
                            $setUnknownTypes($item, $route, $method);
                        }
                    }
                };

                foreach ($openApi->components->schemas as $schema) {
                    if ($schema instanceof Schema) {
                        $setUnknownTypes($schema->type);
                    }
                }
                $careUpdateSchema = $openApi->components->schemas['CareUpdateResource'] ?? null;
                if ($careUpdateSchema instanceof Schema && $careUpdateSchema->type instanceof ObjectType) {
                    $careUpdateSchema->type->properties['image_url'] = $string('uri', [], true);
                    if (($sender = $careUpdateSchema->type->properties['sender'] ?? null) instanceof ObjectType) {
                        $sender->properties['name'] = $string('', [], true);
                    }
                }
                foreach ($openApi->paths as $routePath => $path) {
                    foreach ($path->operations as $method => $operation) {
                        foreach ($operation->parameters as $parameter) {
                            if (
                                $parameter instanceof Parameter && $parameter->name === 'variant'
                                && $parameter->schema instanceof Schema
                            ) {
                                $parameter->schema->type = $string('', ['image', 'thumbnail']);
                            }
                            if (
                                $parameter instanceof Parameter && $parameter->name === 'change_request_id'
                                && $parameter->in === 'query'
                            ) {
                                $type = $string();
                                $type->pattern = '^[1-9][0-9]*$';
                                $parameter->setSchema(Schema::fromType($type));
                            }
                        }
                        if ($operation->requestBodyObject instanceof RequestBodyObject) {
                            $bodyContent = $operation->requestBodyObject->content['application/json'] ?? null;
                            $bodySchema = $bodyContent instanceof MediaType ? $bodyContent->schema : $bodyContent;
                            if ($bodySchema instanceof Schema) {
                                $setUnknownTypes($bodySchema->type, trim($path->path, '/'), $method);
                            }
                        }
                        foreach ($operation->responses ?? [] as $response) {
                            if (
                                !$response instanceof Response
                                || !array_key_exists('application/json', $response->content)
                            ) {
                                continue;
                            }
                            $responseSchema = $response->getContent('application/json');
                            if ($responseSchema instanceof Schema) {
                                $setUnknownTypes($responseSchema->type, trim($path->path, '/'), $method);
                            }
                        }
                    }
                }

                // Scramble infers request/response schemas, but it cannot infer the
                // authorization and state contract from middleware and domain rules.
                // Add operation-level context from the current API contracts. Keep
                // these notes descriptive: unresolved contract conflicts are recorded
                // in the BE-H06 matrix and are not silently decided here.
                $moduleByPath = [
                    'user/roles/' => ['M01 Identity & Roles', 'Authenticated active session; role changes apply to the current caller and are throttled.'],
                    'user' => ['M01 Identity & Roles', 'Authenticated active session; `/user` returns the current caller.'],
                    'pets' => ['M02 Pet Profiles', 'Requires an active OWNER session. Pet IDs are scoped to the authenticated owner; another owner’s pet is not disclosed.'],
                    'caregiver/profile' => ['M03 Caregiver & Listings', 'Requires an active CAREGIVER session; profile data is scoped to the authenticated caregiver.'],
                    'caregiver/listings' => ['M03 Caregiver & Listings', 'Requires an active CAREGIVER session; listing IDs are scoped to the authenticated caregiver. Listing edits are draft-only unless the operation is an explicit lifecycle action.'],
                    'caregiver/media' => ['M03 Caregiver & Listings', 'Requires an active CAREGIVER session. Media is private and must be owned by the caller before it can be attached or streamed.'],
                    'admin/' => ['M09 Reports & Admin', 'Requires an active ADMIN session. Administrative actions are subject to resource scope, current state, and audit rules.'],
                    'listings' => ['M04 Discovery & Smart Match', 'Public discovery exposes only listings eligible for public visibility. `pet_id` activates owner-scoped Smart Match rules; without it, manual filters remain public.'],
                    'bookings' => ['M05 Booking & Availability', 'Booking operations are scoped to the owner and caregiver participants. Booking state and availability are checked server-side; create/cancel/payment operations use the documented `Idempotency-Key` where applicable.'],
                    'sandbox/payment-callback' => ['M06 Payment Sandbox', 'Public callback endpoint. A valid `X-Payment-Signature` is required; callback replay is processed idempotently against the payment attempt and event.'],
                ];
                $operationNotes = [
                    'reports|post' => 'Creates a report for exactly one target. Booking and message targets are available only to a booking participant; the response does not expose private evidence.',
                    'reports/mine|get' => 'Returns only reports filed by the authenticated caller.',
                    'admin/reports|get' => 'Returns the paginated administrative report queue; status filtering is supported.',
                    'admin/reports/{id}|get' => 'Returns report metadata only; private booking/message evidence is excluded.',
                    'admin/reports/{id}/evidence|get' => 'Returns policy-authorized evidence for this report and records the admin access in the audit log.',
                    'admin/reports/{id}/status|post' => 'Changes report status with a required reason. A booking report must use the booking-decision endpoint to become RESOLVED.',
                    'admin/reports/{id}/booking-decision|post' => 'Resolves a booking report through COMPLETE or CANCEL_REFUND and creates the matching durable booking/payment operation; retries reuse the existing operation.',
                    'admin/reports/{id}/history|get' => 'Returns report status events and moderation actions with actor, reason, and timestamps.',
                    'admin/reports/{id}/moderation|post' => 'Hides or restores the reported listing/review through the shared moderation action and audit policy.',
                    'bookings|post' => 'Creates a REQUESTED booking from server-owned price and caller identity. A matching idempotency key replays the prior result; a key reused with changed business data conflicts.',
                    'bookings/quote|post' => 'Calculates the current quote without creating a booking or requiring an idempotency key.',
                    'bookings/{id}/accept|post' => 'Transitions an eligible REQUESTED booking to AWAITING_PAYMENT and creates one calendar hold; invalid state, expiry, or unavailable calendar is rejected.',
                    'bookings/{id}/start|post' => 'Only the assigned caregiver can transition a CONFIRMED booking to IN_PROGRESS; the booking and its status event are committed together.',
                    'bookings/{id}/request-completion|post' => 'Only the assigned caregiver can report completion from IN_PROGRESS, transitioning the booking to AWAITING_CONFIRMATION; this does not complete or release payment.',
                    'bookings/{id}/cancel|post' => 'Cancellation behavior depends on booking/payment state. Payment void/refund processing can be queued; the response may be accepted for asynchronous processing.',
                    'bookings/{id}/reject|post' => 'Caregiver rejection is allowed only for an eligible REQUESTED booking. A replay by the same caregiver does not add a second event.',
                    'bookings/{id}/care-updates|post' => 'Only the assigned caregiver may create an update while the booking is IN_PROGRESS; `body` and/or an image may be supplied.',
                    'bookings/{id}/care-updates|get' => 'Owner and assigned caregiver may read the private timeline. The confirmed contract returns 403 to a non-participant.',
                    'bookings/{id}/care-updates/{careUpdateId}/image|get' => 'Streams a private care-update image after checking booking participation and image ownership.',
                    'bookings/{id}/messages|get' => 'Only booking participants may read visible messages; outsiders receive the same not-found behavior as a missing booking.',
                    'bookings/{id}/messages|post' => 'Only booking participants may send messages. Optional attachments use multipart form data and private media storage.',
                    'bookings/{id}/messages/{messageId}/attachment/{mediaId}|get' => 'Streams an attachment only when it belongs to the visible message in a booking the caller may access. Omitting `mediaId` selects the first visible attachment.',
                    'bookings/{id}/messages/{messageId}/attachment|get' => 'Streams the first visible attachment only after checking booking participation and message visibility.',
                    'bookings/{id}/reviews|post' => 'Creates one review for an eligible completed booking. A second review remains rejected even if the first review is hidden.',
                    'bookings/{id}/sandbox-payments|post' => 'Starts or replays a sandbox payment attempt for the booking owner; the idempotency key prevents duplicate attempts.',
                    'bookings/{id}/sandbox-payments/{attemptId}/simulate|post' => 'Simulates the selected sandbox attempt. The booking, attempt, owner, payment state, and result must agree; replay and late callbacks preserve the recorded state.',
                    'sandbox/payment-callback|post' => 'Validates the signed callback and payment reference/amount/state before applying an idempotent payment transition.',
                    'listings|get' => 'Returns public, paginated listing results. Invalid filters return field validation errors; pet-based matching also validates owner, pet state, and required pet attributes.',
                    'listings/{id}|get' => 'Returns only a publicly visible listing; drafts and suspended/hidden listings are not exposed through this public route.',
                    'listings/{id}/reviews|get' => 'Returns visible, eligible reviews and a live rating aggregate; hidden/demo/ineligible reviews are excluded.',
                    'caregiver/media|post' => 'Accepts JPEG, PNG, or WebP image content subject to upload byte and pixel limits. The response does not expose storage keys or private storage metadata.',
                    'caregiver/listings/{id}/media|put' => 'Replaces the ordered media association for a draft listing. Media must be owned by the caregiver; duplicates, foreign media, and mutation of non-draft listings are rejected.',
                    'caregiver/listings/{id}/availability-slots|post' => 'Creates an availability interval for a caregiver-owned listing. Intervals use explicit timestamps and half-open boundary semantics; overlap and booking-hold constraints apply.',
                    'caregiver/listings/{id}/availability-slots/{slotId}|patch' => 'Updates an owned availability interval while preserving timestamp precision and held-booking constraints.',
                    'caregiver/listings/{id}/availability-slots/{slotId}|delete' => 'Deletes an owned availability interval only when the current booking holds permit the change.',
                    'caregiver/listings/{id}/change-requests|post' => 'Creates a versioned proposed listing change; the listing remains subject to review and concurrent version checks.',
                    'caregiver/listings/{id}/change-requests/{requestId}/withdraw|post' => 'Withdraws the caregiver’s pending change request subject to current request/listing state.',
                    'admin/listings/{id}/approve|post' => 'Approves an eligible listing after revalidating listing content, media, and caregiver eligibility; the transition is audited.',
                    'admin/listings/{id}/reject|post' => 'Rejects an eligible listing with the required decision reason and returns it to the documented editable state.',
                    'admin/listings/{id}/suspend|post' => 'Suspends the listing through an audited administrative state transition; restore rights depend on the suspension source.',
                    'caregiver/listings/{id}/submit|post' => 'Submits an eligible draft listing for review; state and caregiver eligibility are rechecked server-side.',
                    'caregiver/listings/{id}/archive|post' => 'Archives an eligible caregiver-owned listing while preserving booking history and enforcing active booking constraints.',
                    'caregiver/listings/{id}/restore|post' => 'Restores a caregiver-owned archived listing only when its state and caregiver eligibility permit it.',
                    'pets/{pet}/archive|patch' => 'Archives a pet owned by the authenticated user; archived pets are excluded from active Smart Match use.',
                    'pets/{pet}/activate|patch' => 'Activates a pet owned by the authenticated user, subject to the pet lifecycle rules.',
                    'bookings/{id}/confirm-completion|post' => 'Only the booking owner may confirm completion. The request creates or reuses one durable release operation; the booking remains AWAITING_CONFIRMATION until sandbox release succeeds.',
                ];
                $methodNames = [
                    'get' => 'Get',
                    'post' => 'Create',
                    'put' => 'Replace',
                    'patch' => 'Update',
                    'delete' => 'Delete',
                ];
                $responseDescriptions = [
                    '200' => 'The operation completed successfully.',
                    '201' => 'The resource was created successfully.',
                    '202' => 'The request was accepted for asynchronous processing.',
                    '204' => 'The operation completed successfully with no response body.',
                    '400' => 'The request structure is invalid.',
                    '401' => 'Authentication is required or the session is no longer valid.',
                    '403' => 'The authenticated caller does not have permission for this operation.',
                    '404' => 'The resource does not exist or is outside the caller’s visible scope.',
                    '409' => 'The requested operation conflicts with the current resource state or idempotency record.',
                    '422' => 'One or more request fields failed validation.',
                    '419' => 'The session CSRF token is missing or expired; obtain a fresh token before retrying the state-changing request.',
                    '429' => 'The request was rate limited.',
                    '500' => 'An unexpected server error occurred.',
                ];
                $errorSchema = Schema::fromType($object([
                    'error' => $object([
                        'code' => $string(),
                        'message' => $string(),
                        'details' => new ObjectType,
                        'request_id' => $string(),
                    ], ['code', 'message', 'request_id']),
                ], ['error']));
                $errorSchema->description = 'Standard API error envelope. `details` is omitted when there is no field or domain detail.';
                $openApi->components->schemas['ApiErrorEnvelope'] = $errorSchema;

                foreach ($openApi->paths as $path) {
                    $route = trim($path->path, '/');
                    foreach ($path->operations as $method => $operation) {
                        $method = strtolower($method);
                        $group = null;
                        foreach ($moduleByPath as $prefix => $details) {
                            if ($route === $prefix || str_starts_with($route, rtrim($prefix, '/') . '/')) {
                                $group = $details;
                                break;
                            }
                        }
                        $group ??= ['API', 'Authentication and visibility follow this operation’s route middleware and resource policy.'];

                        $humanPath = preg_replace('/\{([^}?]+)\??}/', '$1', $route);
                        $action = $methodNames[$method] ?? ucfirst($method);
                        if ($method === 'post' && preg_match('/\/(accept|cancel|reject|approve|archive|restore|suspend|submit|withdraw|retry|simulate)$/', $route, $matches)) {
                            $action = ucfirst($matches[1]);
                        } elseif ($method === 'post' && preg_match('/\/quote$/', $route)) {
                            $action = 'Quote';
                        }
                        $operation->summary = $action . ' ' . ucwords(str_replace('/', ' ', $humanPath));
                        $operation->description = trim(implode("\n\n", array_filter([
                            '**Module:** ' . $group[0] . '. ' . $group[1],
                            $operationNotes[$route . '|' . $method] ?? null,
                            'JSON errors use the API error envelope: `error.code`, `error.message`, optional `error.details`, and `error.request_id`. The listed response codes reflect the current Scramble source analysis and may not include every domain failure; see the BE-H06 route and contract matrix for gaps and owner decisions.',
                        ])));

                        foreach ($operation->responses ?? [] as $response) {
                            if ($response instanceof Response) {
                                $code = (string) $response->code;
                                if (isset($responseDescriptions[$code])) {
                                    $response->description = $responseDescriptions[$code];
                                }
                            }
                        }

                        $protected = preg_match('#^(admin|bookings|caregiver|pets|user)(/|$)#', $route) === 1;
                        $mutating = in_array($method, ['post', 'put', 'patch', 'delete'], true);
                        $hasResourceId = str_contains($route, '{');
                        $hasQueryContract = in_array($route, [
                            'listings',
                            'admin/listings',
                            'admin/listing-change-requests',
                            'caregiver/listings',
                            'caregiver/listings/{id}/change-requests',
                        ], true);
                        $statefulAction = $mutating && ($protected || str_starts_with($route, 'sandbox/'));
                        $expectedErrors = [];
                        if ($protected) {
                            $expectedErrors['401'] = $responseDescriptions['401'];
                            $expectedErrors['403'] = $responseDescriptions['403'];
                        }
                        if ($hasResourceId) {
                            $expectedErrors['404'] = $responseDescriptions['404'];
                        }
                        if ($statefulAction && (str_starts_with($route, 'bookings/') || str_starts_with($route, 'caregiver/listings/') || str_starts_with($route, 'admin/listings/'))) {
                            $expectedErrors['409'] = $responseDescriptions['409'];
                        }
                        if ($mutating || $hasQueryContract) {
                            $expectedErrors['422'] = $responseDescriptions['422'];
                        }
                        if ($protected && $mutating) {
                            $expectedErrors['419'] = $responseDescriptions['419'];
                        }
                        if ($route === 'sandbox/payment-callback') {
                            $expectedErrors['401'] = 'The callback signature is missing or invalid.';
                            $expectedErrors['409'] = 'The callback conflicts with the current payment attempt state.';
                            $expectedErrors['422'] = 'The callback payload failed validation.';
                        }
                        if ($route === 'listings') {
                            $expectedErrors['401'] = 'An active OWNER session is required when `pet_id` is supplied for Smart Match.';
                            $expectedErrors['404'] = 'The supplied pet is not visible to the authenticated owner.';
                            $expectedErrors['409'] = 'The supplied pet or matching context is not in a valid active state.';
                        }
                        foreach ($expectedErrors as $code => $description) {
                            $statusCode = (string) $code;
                            $existing = null;
                            foreach ($operation->responses ?? [] as $response) {
                                if ($response instanceof Response && (string) $response->code === $statusCode) {
                                    $existing = $response;
                                    break;
                                }
                            }
                            if ($existing === null) {
                                $existing = Response::make($statusCode)->setDescription($description);
                                $operation->addResponse($existing);
                            }
                            $existing->setContent('application/json', $errorSchema);
                        }
                    }
                }
            });
        });

        Gate::define('viewApiDocs', fn(User $user): bool => $user->status === 'ACTIVE' && $user->hasRole('ADMIN'));
        Gate::define(
            'viewPulse',
            static fn(User $user): bool =>
                $user instanceof User
                && $user->status === 'ACTIVE'
                && $user->hasRole('ADMIN')
        );

        if (config('performance.enabled') && app()->environment('performance')) {
            DB::listen(PerformanceQueryRecorder::record(...));
        }

        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(app()->isProduction());

        ResetPassword::createUrlUsing(fn(CanResetPassword $notifiable, string $token): string => rtrim(config('app.frontend_url'), '/')
            . '/reset-password?' . http_build_query([
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]));

        Password::defaults(
            fn(): ?Password => app()->isProduction()
                ? Password::min(12)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols()
                    ->uncompromised()
                : null,
        );
    }
}