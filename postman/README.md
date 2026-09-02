# RMIS Postman assets

These files are the version-controlled source for testing the implemented API v1 endpoints:

- `RMIS-v1.postman_collection.json` - importable collection.
- `RMIS-local.postman_environment.json` - safe environment template with blank secrets.
- `build-collection.php` - deterministic collection builder.
- `verify-collection.php` - compares Postman requests with Laravel's registered v1 routes.

## Import and first login

1. In Postman, select **Import** and import both JSON files.
2. Select the **RMIS Local** environment.
3. Set `base_url`, `login`, and `password`. Login resolves and stores `tenant_uuid` automatically when the account has one active workspace.
4. Run **01 - Authentication / Login**.
5. The Login test script stores `tenant_uuid`, `access_token`, `tenant_user_uuid`, and the active `branch_uuid` automatically.
6. If the account belongs to multiple workspaces, choose one from the returned `tenants` list and repeat Login with its `tenant_uuid` as an optional JSON field.
7. Fill other UUID variables as resources are created. Many create requests capture their returned UUID automatically.

Use only the UUID values exposed by the API. Do not place internal bigint IDs in collection routes or request bodies.

## Rebuild and verify

From the project root:

```powershell
php postman/build-collection.php
php postman/verify-collection.php
```

Run both commands whenever an API route is added, removed, renamed, or moved to another version.

## Backup rules

- Keep the builder, collection, environment template, and verifier in source control.
- Never commit real access tokens, passwords, OTPs, personal data, or production URLs.
- Keep secrets only in your private Postman environment/current values.
- If you export an environment containing runtime values, name it `*.private.postman_environment.json`; this folder ignores that pattern.
- Export important Postman changes before deleting or reorganising requests.
- Treat the Laravel routes and FormRequest validation rules as the authoritative API contract.
