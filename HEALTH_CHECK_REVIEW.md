# Laravel Doctor Health Check Review

Reviewed against the current check implementations in `src/Checks` and installed Laravel framework internals in `vendor/laravel/framework/src`.

### ✅ VALID CHECKS

- SessionDriverMismatchCheck — correctly gates on `session.driver=database` and checks the configured sessions table.
- AnonymousComponentCheck — correctly validates registered anonymous component namespace directories.
- ComponentClassCheck — correctly validates registered class component aliases against autoloadable classes.
- ComponentNamespaceCheck — correctly validates registered view namespace hint paths.
- DuplicateRouteNamesCheck — correctly detects duplicate names in the runtime route collection.
- MissingControllerCheck — correctly validates controller classes from resolved route actions.

### ❌ INVALID / ⚠️ INCOMPLETE CHECKS

- MissingNamedRoutesCheck — ❌ false positives from comments/strings and invalidly suggests `url('name.with.dots')` should be `route()` when a dotted URL path may be intentional.
- CacheDriverNotRunningCheck — ⚠️ Memcached `connect()` can succeed without proving a server is reachable; named/custom cache stores are not checked.
- CacheTagsOnUnsupportedDriverCheck — ❌ wrong Laravel internals: `ArrayStore` extends `TaggableStore`, so array cache supports tags.
- RememberReturnsClosureCheck — ❌ not scoped to serializing stores; array cache can hold closures, and the regex is too narrow.
- AbortIfWrongHttpCodeCheck — ❌ uses `preg_match`, so only the first occurrence per file is checked; also scans comments/strings.
- ConfigCacheIncompatibleValuesCheck — ❌ regex can flag comments/strings instead of actual config values.
- EarlyConfigAccessCheck — ❌ wrong Laravel internals: configuration is loaded before service providers register.
- InterfaceBoundToDeletedConcreteCheck — ❌ does not resolve imported short class names, causing false positives.
- SingletonAfterFirstResolveCheck — ❌ wrong assumption: registering singletons in `boot()` is valid and common when intentionally delayed.
- AccessorMutatorStyleConflictCheck — ❌ mixing accessor styles is supported by Laravel; this is style, not a runtime health error.
- MissingGuardedOrFillableCheck — ❌ wrong Eloquent assumption: default `$guarded = ['*']` already protects mass assignment.
- SoftDeleteScopeConflictCheck — ❌ flags any `where('deleted_at')`, including query builder use and intentional manual filtering.
- ValueVsFirstOnNullCheck — ⚠️ real issue, but guard detection suppresses unrelated `@if` blocks and misses many cases.
- WithCountOnUndefinedRelationshipCheck — ❌ model guessing and “exists on any model” fallback create both false positives and false negatives.
- WrongRelationshipKeyConventionCheck — ❌ argument parsing is wrong for several relationship forms and flags intentional custom keys.
- AppKeyCheck — ⚠️ catches empty/placeholder keys but does not validate base64 format or cipher-length correctness.
- EnvExampleMismatchCheck — ❌ `.env` may intentionally contain local-only keys not present in `.env.example`.
- MissingEnvKeysCheck — ❌ flags `env('KEY', 'default')` even though missing keys with defaults are valid.
- ListenerMissingHandleMethodCheck — ❌ flags subscriber/invokable listener-style classes that do not require `handle()`.
- MissingListenerClassCheck — ⚠️ broad regex and incomplete class resolution miss missing listeners and can match non-registrations.
- UnserializableListenerPayloadCheck — ❌ flags any closure in queued event/listener files, including local non-payload closures.
- MissingPolicyClassCheck — ❌ does not resolve `use` imports, causing false positives for imported policy classes.
- BusChainCheck — ❌ `Bus::chain()` accepts job instances and closures; comma-splitting PHP code is not valid parsing.
- FailedJobTableMissingCheck — ❌ not scoped to `queue.failed.driver`; file, null, and DynamoDB failed-job drivers need no `failed_jobs` table.
- JobDependencyResolutionCheck — ❌ wrong queue internals: job constructor parameters are dispatch payload, not worker container dependencies.
- JobHasHandleMethodCheck — ⚠️ `Queueable` is a trait, so `is_subclass_of()` is wrong; only declared classes are inspected.
- JobTriesZeroCheck — ❌ wrong Laravel internals: `$tries = 0` means unlimited attempts and is valid with `retryUntil()`.
- MissingJobClassCheck — ⚠️ only static dispatch forms are detected; short unimported classes and other dispatch styles are missed.
- QueueDriverCheck — ❌ `sync` in production can be intentional; this is policy, not a correctness failure.
- MissingLivewireActionMethodCheck — ❌ maps parent Blade views to Livewire component classes incorrectly.
- MissingLivewireComponentCheck — ⚠️ misses dot notation, `@livewire()`, and self-closing tags without attributes.
- WireModelMissingPropertyCheck — ❌ maps component views incorrectly and flags valid nested/form object bindings.
- MailableMissingViewCheck — ⚠️ only checks first `->view()` and misses modern `Content`, `markdown`, and `text` mailables.
- MailableVariableMismatchCheck — ❌ unused variables can be intentional and variables may be consumed by partials/composers.
- TerminateMethodThrowsCheck — ❌ wrong Laravel internals: terminate exceptions are not silently swallowed by the kernel.
- UnregisteredMiddlewareCheck — ❌ parses Laravel 11 `Middleware::alias(array)` incorrectly and misses default aliases.
- UserBeforeAuthCheck — ❌ `$request->user()` is nullable by design and may be intentionally used before auth.
- DuplicateUrisCheck — ❌ ignores domain, constraints, and route ordering, so valid routes can be flagged.
- InvalidMiddlewareCheck — ❌ misses Laravel default aliases such as `can`, `signed`, `throttle`, and `verified`.
- MissingControllerMethodCheck — ⚠️ misses invalid invokable controller routes because non-`@` controller actions are skipped.
- DeletedScheduledCommandCheck — ⚠️ scans only `app/Console` and only the first scheduled command match per file.
- OverlappingJobsWithoutLockCheck — ❌ frequent scheduled tasks can be intentionally unlocked; file-level lock detection hides unlocked tasks.
- ScheduledCommandNotExistsCheck — ⚠️ scans only `app/Console`, checks only first match, and validates class existence rather than registration.
- ColumnMismatchCheck — ❌ only declared model classes are inspected and casts can be valid for non-column attributes.
- ForeignKeyCheck — ⚠️ hardcodes MySQL `information_schema` and silently passes unsupported drivers.
- InvalidCastsCheck — ❌ built-in/class cast handling is incomplete; misses `Castable`, inbound casts, class casts with arguments, and collection casts.
- MissingStorageSymlinkCheck — ❌ `public/storage` is only required when the public disk or public URLs are used.
- S3UrlWithoutConfigCheck — ❌ S3 can be valid with IAM/role credentials and without explicit key/secret values.
- StoreAsPathTraversalCheck — ❌ regex flags harmless `..` substrings and misses variable-driven traversal.
- UndefinedDiskCheck — ⚠️ uses `preg_match`, so only the first `Storage::disk()` call per file is checked.
- AuthorizeAlwaysFalseCheck — ❌ `authorize(): false` is documented and can be intentional.
- DeprecatedErrorApiCheck — ❌ scans any request file text and can flag comments/strings; it does not verify actual FormRequest API use.
- NonExistentRuleClassCheck — ⚠️ misses FQCNs, imported aliases, same-namespace missing classes, and non-`*Rule*` custom rules.
- MissingComponentCheck — ❌ regex can flag Blade comments/strings and only handles one simple `@component()` form.
- MissingExtendsCheck — ❌ regex can flag Blade comments/strings and only handles one simple `@extends()` form.
- MissingIncludeCheck — ❌ regex can flag Blade comments/strings and only handles one simple `@include()` form.
- StackPushMismatchCheck — ❌ `@push` without a scanned `@stack` is not always an error and may be intentional.
